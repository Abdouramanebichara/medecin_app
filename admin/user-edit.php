<?php
$pageTitle = "Modifier un utilisateur";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Récupérer l'ID de l'utilisateur
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    $_SESSION['error'] = "ID de l'utilisateur non spécifié.";
    header("Location: users.php");
    exit;
}

// Récupérer les informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "Utilisateur non trouvé.";
    header("Location: users.php");
    exit;
}

// Récupérer les données spécifiques au rôle
$role_data = null;
switch ($user['role']) {
    case 'patient':
        $stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $role_data = $stmt->fetch(PDO::FETCH_ASSOC);
        break;
        
    case 'medecin':
        $stmt = $conn->prepare("
            SELECT m.*, s.nom as specialite
            FROM medecins m
            INNER JOIN specialites s ON m.specialite_id = s.id
            WHERE m.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $role_data = $stmt->fetch(PDO::FETCH_ASSOC);
        break;
}

// Récupérer toutes les spécialités (pour les médecins)
$specialites = [];
if ($user['role'] === 'medecin') {
    $stmt = $conn->query("SELECT id, nom FROM specialites ORDER BY nom");
    $specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $new_password = trim($_POST['new_password']);
    
    // Données spécifiques au rôle
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : null;
    $date_naissance = isset($_POST['date_naissance']) ? trim($_POST['date_naissance']) : null;
    $sexe = isset($_POST['sexe']) ? $_POST['sexe'] : null;
    $specialite_id = isset($_POST['specialite_id']) ? intval($_POST['specialite_id']) : null;
    
    if (empty($nom) || empty($prenom) || empty($email) || empty($role)) {
        $_SESSION['error'] = "Les champs Nom, Prénom, Email et Rôle sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "L'email n'est pas valide.";
    } else {
        try {
            // Vérifier si l'email existe déjà (pour un autre utilisateur)
            $stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = "Cet email est déjà utilisé par un autre compte.";
            } else {
                // Commencer la transaction
                $conn->beginTransaction();
                
                // Mise à jour des données de base de l'utilisateur
                $sql = "UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, role = ?";
                $params = [$nom, $prenom, $email, $role];
                
                // Si un nouveau mot de passe est fourni
                if (!empty($new_password)) {
                    if (strlen($new_password) < 8) {
                        throw new Exception("Le mot de passe doit contenir au moins 8 caractères.");
                    }
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql .= ", mot_de_passe = ?";
                    $params[] = $hashed_password;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $user_id;
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                // Gestion des données spécifiques au rôle
                // Si le rôle a changé, supprimer les anciennes données de rôle et créer les nouvelles
                if ($role !== $user['role']) {
                    // Supprimer les anciennes données de rôle
                    switch ($user['role']) {
                        case 'patient':
                            $stmt = $conn->prepare("DELETE FROM patients WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            break;
                        case 'medecin':
                            $stmt = $conn->prepare("DELETE FROM medecins WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            break;
                    }
                    
                    // Créer les nouvelles données de rôle
                    switch ($role) {
                        case 'patient':
                            $stmt = $conn->prepare("INSERT INTO patients (user_id, telephone, date_naissance, sexe) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$user_id, $telephone, $date_naissance ?: null, $sexe]);
                            break;
                        case 'medecin':
                            if (!$specialite_id) {
                                throw new Exception("Veuillez sélectionner une spécialité pour le médecin.");
                            }
                            $stmt = $conn->prepare("INSERT INTO medecins (user_id, specialite_id) VALUES (?, ?)");
                            $stmt->execute([$user_id, $specialite_id]);
                            break;
                    }
                } else {
                    // Mettre à jour les données de rôle existantes
                    switch ($role) {
                        case 'patient':
                            $stmt = $conn->prepare("UPDATE patients SET telephone = ?, date_naissance = ?, sexe = ? WHERE user_id = ?");
                            $stmt->execute([$telephone, $date_naissance ?: null, $sexe, $user_id]);
                            break;
                        case 'medecin':
                            if (!$specialite_id) {
                                throw new Exception("Veuillez sélectionner une spécialité pour le médecin.");
                            }
                            $stmt = $conn->prepare("UPDATE medecins SET specialite_id = ? WHERE user_id = ?");
                            $stmt->execute([$specialite_id, $user_id]);
                            break;
                    }
                }
                
                // Tout s'est bien passé, valider la transaction
                $conn->commit();
                
                $_SESSION['success'] = "Les informations de l'utilisateur ont été mises à jour avec succès.";
                header("Location: users.php");
                exit;
            }
        } catch (Exception $e) {
            // En cas d'erreur, annuler la transaction
            $conn->rollBack();
            $_SESSION['error'] = "Erreur: " . $e->getMessage();
        }
    }
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Modifier un utilisateur</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="users.php">Utilisateurs</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Modifier</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Modifier les informations de l'utilisateur</h2>
            </div>
            <div class="dashboard-card-body">
                <form method="post" id="editUserForm">
                    <h5 class="mb-3">Informations générales</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenom" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="patient" <?= $user['role'] === 'patient' ? 'selected' : '' ?>>Patient</option>
                                <option value="medecin" <?= $user['role'] === 'medecin' ? 'selected' : '' ?>>Médecin</option>
                                <option value="administrateur" <?= $user['role'] === 'administrateur' ? 'selected' : '' ?>>Administrateur</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Laissez vide pour ne pas modifier">
                        <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                    </div>
                    
                    <!-- Champs spécifiques aux patients -->
                    <div id="patient_fields" class="mt-4" <?= $user['role'] !== 'patient' ? 'style="display: none;"' : '' ?>>
                        <h5 class="mb-3">Informations du patient</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" value="<?= isset($role_data['telephone']) ? htmlspecialchars($role_data['telephone']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="date_naissance" class="form-label">Date de naissance</label>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?= isset($role_data['date_naissance']) ? $role_data['date_naissance'] : '' ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sexe" class="form-label">Sexe</label>
                            <select class="form-select" id="sexe" name="sexe">
                                <option value="">Non spécifié</option>
                                <option value="M" <?= isset($role_data['sexe']) && $role_data['sexe'] === 'M' ? 'selected' : '' ?>>Homme</option>
                                <option value="F" <?= isset($role_data['sexe']) && $role_data['sexe'] === 'F' ? 'selected' : '' ?>>Femme</option>
                                <option value="Autre" <?= isset($role_data['sexe']) && $role_data['sexe'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Champs spécifiques aux médecins -->
                    <div id="doctor_fields" class="mt-4" <?= $user['role'] !== 'medecin' ? 'style="display: none;"' : '' ?>>
                        <h5 class="mb-3">Informations du médecin</h5>
                        <div class="mb-3">
                            <label for="specialite_id" class="form-label">Spécialité <span class="text-danger">*</span></label>
                            <select class="form-select" id="specialite_id" name="specialite_id">
                                <option value="">Sélectionnez une spécialité</option>
                                <?php foreach ($specialites as $specialite): ?>
                                    <option value="<?= $specialite['id'] ?>" <?= isset($role_data['specialite_id']) && $role_data['specialite_id'] == $specialite['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($specialite['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex justify-content-between">
                        <a href="users.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" name="update_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('role').addEventListener('change', function() {
    const role = this.value;
    const patientFields = document.getElementById('patient_fields');
    const doctorFields = document.getElementById('doctor_fields');
    
    if (role === 'patient') {
        patientFields.style.display = 'block';
        doctorFields.style.display = 'none';
        document.getElementById('specialite_id').removeAttribute('required');
    } else if (role === 'medecin') {
        patientFields.style.display = 'none';
        doctorFields.style.display = 'block';
        document.getElementById('specialite_id').setAttribute('required', 'required');
    } else {
        patientFields.style.display = 'none';
        doctorFields.style.display = 'none';
        document.getElementById('specialite_id').removeAttribute('required');
    }
});

document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const role = document.getElementById('role').value;
    const specialiteId = document.getElementById('specialite_id').value;
    
    if (role === 'medecin' && !specialiteId) {
        e.preventDefault();
        alert('Veuillez sélectionner une spécialité pour le médecin.');
    }
    
    const newPassword = document.getElementById('new_password').value;
    if (newPassword && newPassword.length < 8) {
        e.preventDefault();
        alert('Le mot de passe doit contenir au moins 8 caractères.');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>