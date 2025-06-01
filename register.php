
<?php
session_start();
require_once 'config/db.php';

// Si l'utilisateur est déjà connecté, rediriger selon son rôle
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'administrateur':
            header('Location: admin/dashboard.php');
            break;
        case 'medecin':
            header('Location: medecin/dashboard.php');
            break;
        case 'patient':
            header('Location: patient/dashboard.php');
            break;
    }
    exit;
}

$errors = [];
$nom = '';
$prenom = '';
$email = '';
$role = 'patient'; // Rôle par défaut
$specialite_id = '';

// Récupération des spécialités pour les médecins
$stmt = $conn->query("SELECT id, nom FROM specialites ORDER BY nom");
$specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données du formulaire
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'patient';
    $specialite_id = isset($_POST['specialite_id']) ? intval($_POST['specialite_id']) : 0;

    // Validation
    if (empty($nom)) {
        $errors[] = "Le nom est requis";
    }
    
    if (empty($prenom)) {
        $errors[] = "Le prénom est requis";
    }
    
    if (empty($email)) {
        $errors[] = "L'email est requis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    } elseif (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }

    // Vérification si le rôle est médecin et si une spécialité est sélectionnée
    if ($role === 'medecin' && $specialite_id <= 0) {
        $errors[] = "Veuillez sélectionner une spécialité";
    }

    // Vérification si l'email existe déjà
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Cet email est déjà utilisé";
        }
    }

    // Enregistrement si pas d'erreurs
    if (empty($errors)) {
        try {
            // Début de la transaction
            $conn->beginTransaction();
            
            // Hashage du mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertion dans la table utilisateurs
            $stmt = $conn->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $prenom, $email, $hashed_password, $role]);
            
            $user_id = $conn->lastInsertId();
            
            // Insertion dans la table spécifique selon le rôle
            if ($role === 'patient') {
                $stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                $stmt->execute([$user_id]);
            } elseif ($role === 'medecin') {
                $stmt = $conn->prepare("INSERT INTO medecins (user_id, specialite_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $specialite_id]);
            }
            
            // Valider la transaction
            $conn->commit();
            
            // Rediriger vers la page de connexion avec un message de succès
            $_SESSION['register_success'] = "Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.";
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollBack();
            $errors[] = "Une erreur est survenue lors de l'inscription : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - MedBook</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat me-2"></i>MedBook
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register.php">Inscription</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Register Section -->
    <section class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card auth-card">
                        <div class="card-body p-sm-5">
                            <div class="text-center mb-4">
                                <h2 class="fw-bold">Créer un compte</h2>
                                <p class="text-muted">Rejoignez MedBook et accédez à nos services de santé numériques</p>
                            </div>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form action="register.php" method="POST" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label class="form-label mb-3">Je suis :</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-check custom-radio">
                                                <input class="form-check-input" type="radio" name="role" id="role_patient" value="patient" <?= $role === 'patient' ? 'checked' : '' ?>>
                                                <label class="form-check-label w-100" for="role_patient">
                                                    <div class="role-card">
                                                        <div class="role-icon text-primary">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                        <div class="role-title">Patient</div>
                                                        <div class="role-desc">Je cherche un médecin</div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check custom-radio">
                                                <input class="form-check-input" type="radio" name="role" id="role_medecin" value="medecin" <?= $role === 'medecin' ? 'checked' : '' ?>>
                                                <label class="form-check-label w-100" for="role_medecin">
                                                    <div class="role-card">
                                                        <div class="role-icon text-info">
                                                            <i class="fas fa-user-md"></i>
                                                        </div>
                                                        <div class="role-title">Médecin</div>
                                                        <div class="role-desc">Je suis professionnel</div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Champs spécifiques pour les médecins -->
                                <div id="medecin-fields" class="mb-4" style="display: <?= $role === 'medecin' ? 'block' : 'none' ?>;">
                                    <label for="specialite_id" class="form-label">Votre spécialité <span class="text-danger">*</span></label>
                                    <select class="form-select" id="specialite_id" name="specialite_id" <?= $role === 'medecin' ? 'required' : '' ?>>
                                        <option value="">Sélectionner une spécialité</option>
                                        <?php foreach ($specialites as $specialite): ?>
                                            <option value="<?= $specialite['id'] ?>" <?= $specialite_id == $specialite['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($specialite['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Cette information est nécessaire pour que les patients puissent vous trouver.</div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($nom) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" value="<?= htmlspecialchars($prenom) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Au moins 8 caractères</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-4 form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label" for="terms">J'accepte les <a href="#">termes et conditions</a> et la <a href="#">politique de confidentialité</a></label>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Créer mon compte</button>
                                </div>
                            </form>
                            <div class="text-center mt-4">
                                <p>Vous avez déjà un compte ? <a href="login.php">Connectez-vous</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h4 class="mb-4">MedBook</h4>
                    <p>La révolution digitale au service de votre santé</p>
                </div>
                <div class="col-md-4">
                    <h4 class="mb-4">Liens utiles</h4>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="login.php">Connexion</a></li>
                        <li><a href="register.php">Inscription</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h4 class="mb-4">Contact</h4>
                    <p><i class="fas fa-envelope me-2"></i> contact@MedBook.com</p>
                    <p><i class="fas fa-phone me-2"></i> +237 657 926 556</p>
                    <div class="social-links mt-3">
                        <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2023 MedBook. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        });

        // Toggle médecin fields visibility
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const medecinFields = document.getElementById('medecin-fields');
                const specialiteSelect = document.getElementById('specialite_id');
                
                if (this.value === 'medecin') {
                    medecinFields.style.display = 'block';
                    specialiteSelect.setAttribute('required', 'required');
                } else {
                    medecinFields.style.display = 'none';
                    specialiteSelect.removeAttribute('required');
                }
            });
        });

        // Form validation
        (function () {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    // Additional validation
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    if (password.value !== confirmPassword.value) {
                        event.preventDefault();
                        alert('Les mots de passe ne correspondent pas.');
                    }
                    
                    const role = document.querySelector('input[name="role"]:checked').value;
                    const specialiteSelect = document.getElementById('specialite_id');
                    
                    if (role === 'medecin' && !specialiteSelect.value) {
                        event.preventDefault();
                        alert('Veuillez sélectionner une spécialité.');
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
