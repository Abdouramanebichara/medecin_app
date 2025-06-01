<?php
$pageTitle = "Mon profil";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle médecin
requireRole('medecin');

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT u.*, m.id as medecin_id, m.specialite_id, s.nom as specialite 
    FROM utilisateurs u 
    JOIN medecins m ON u.id = m.user_id 
    JOIN specialites s ON m.specialite_id = s.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Liste des spécialités
$stmt = $conn->prepare("SELECT id, nom FROM specialites ORDER BY nom");
$stmt->execute();
$specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire de modification du profil
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $specialite_id = intval($_POST['specialite_id']);
    
    if (empty($nom) || empty($prenom) || empty($email)) {
        $error_message = "Les champs Nom, Prénom et Email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Veuillez entrer une adresse email valide.";
    } else {
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $error_message = "Cette adresse email est déjà utilisée par un autre compte.";
        } else {
            try {
                // Début de la transaction
                $conn->beginTransaction();
                
                // Mettre à jour les informations de base
                $stmt = $conn->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, email = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $email, $user_id]);
                
                // Mettre à jour la spécialité
                $stmt = $conn->prepare("UPDATE medecins SET specialite_id = ? WHERE user_id = ?");
                $stmt->execute([$specialite_id, $user_id]);
                
                // Valider la transaction
                $conn->commit();
                
                // Mettre à jour les données de session
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['email'] = $email;
                
                // Recharger les données utilisateur
                $stmt = $conn->prepare("
                    SELECT u.*, m.id as medecin_id, m.specialite_id, s.nom as specialite 
                    FROM utilisateurs u 
                    JOIN medecins m ON u.id = m.user_id 
                    JOIN specialites s ON m.specialite_id = s.id 
                    WHERE u.id = ?
                ");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $success_message = "Votre profil a été mis à jour avec succès.";
            } catch (PDOException $e) {
                // Annuler la transaction en cas d'erreur
                $conn->rollBack();
                $error_message = "Une erreur est survenue lors de la mise à jour du profil : " . $e->getMessage();
            }
        }
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Tous les champs de mot de passe sont obligatoires.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Vérifier le mot de passe actuel
        $stmt = $conn->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_hash = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $current_hash)) {
            $error_message = "Le mot de passe actuel est incorrect.";
        } else {
            // Mettre à jour le mot de passe
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);
            
            $success_message = "Votre mot de passe a été modifié avec succès.";
        }
    }
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Mon profil</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mon profil</li>
                </ol>
            </nav>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Informations du compte</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="profile-overview text-center mb-4">
                            <div class="profile-avatar mb-3">
                                <?php 
                                $initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
                                ?>
                                <span><?= $initials ?></span>
                            </div>
                            <h4>Dr. <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h4>
                            <p class="text-muted"><?= htmlspecialchars($user['specialite']) ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <div class="form-control-static"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Date d'inscription</label>
                            <div class="form-control-static">
                                <?= date('d/m/Y', strtotime($user['date_creation'])) ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Spécialité</label>
                            <div class="form-control-static">
                                <?= htmlspecialchars($user['specialite']) ?>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="availability.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-check me-2"></i>Gérer mes disponibilités
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <h2>Modifier le profil</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form action="" method="post">
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
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="specialite_id" class="form-label">Spécialité <span class="text-danger">*</span></label>
                                <select class="form-select" id="specialite_id" name="specialite_id" required>
                                    <?php foreach ($specialites as $specialite): ?>
                                        <option value="<?= $specialite['id'] ?>" <?= $user['specialite_id'] == $specialite['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($specialite['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Changer le mot de passe</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form action="" method="post" id="password-form">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="update_password" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Changer le mot de passe
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('password-form').addEventListener('submit', function(event) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        event.preventDefault();
        alert('Le nouveau mot de passe et sa confirmation ne correspondent pas.');
    }
    
    if (newPassword.length < 8) {
        event.preventDefault();
        alert('Le nouveau mot de passe doit contenir au moins 8 caractères.');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>