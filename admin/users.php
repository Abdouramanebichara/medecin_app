<?php
$pageTitle = "Gestion des utilisateurs";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Traiter la suppression d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    try {
        // Commencer une transaction
        $conn->beginTransaction();
        
        // Supprimer l'utilisateur
        $stmt = $conn->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Valider la transaction
        $conn->commit();
        
        $_SESSION['success'] = "L'utilisateur a été supprimé avec succès.";
        header("Location: users.php");
        exit;
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollBack();
        $_SESSION['error'] = "Erreur lors de la suppression de l'utilisateur : " . $e->getMessage();
    }
}

// Traiter l'ajout d'un nouvel utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    
    if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($role)) {
        $_SESSION['error'] = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Adresse email invalide.";
    } elseif (strlen($password) < 8) {
        $_SESSION['error'] = "Le mot de passe doit comporter au moins 8 caractères.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $conn->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $email_exists = $stmt->fetchColumn();
            
            if ($email_exists) {
                $_SESSION['error'] = "Cette adresse email est déjà utilisée.";
            } else {
                // Commencer une transaction
                $conn->beginTransaction();
                
                // Crypter le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer le nouvel utilisateur
                $stmt = $conn->prepare("
                    INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $prenom, $email, $hashed_password, $role]);
                $new_user_id = $conn->lastInsertId();
                
                // Si c'est un patient, créer l'enregistrement correspondant
                if ($role === 'patient') {
                    $stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                    $stmt->execute([$new_user_id]);
                }
                // Si c'est un médecin, créer l'enregistrement correspondant
                elseif ($role === 'medecin') {
                    $specialite_id = isset($_POST['specialite_id']) ? intval($_POST['specialite_id']) : 1;
                    $stmt = $conn->prepare("INSERT INTO medecins (user_id, specialite_id) VALUES (?, ?)");
                    $stmt->execute([$new_user_id, $specialite_id]);
                }
                
                // Valider la transaction
                $conn->commit();
                
                $_SESSION['success'] = "L'utilisateur a été créé avec succès.";
                header("Location: users.php");
                exit;
            }
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollBack();
            $_SESSION['error'] = "Erreur lors de la création de l'utilisateur : " . $e->getMessage();
        }
    }
}

// Récupérer les spécialités pour le formulaire d'ajout de médecin
$stmt = $conn->query("SELECT id, nom FROM specialites ORDER BY nom");
$specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrer les utilisateurs
$role = isset($_GET['role']) ? $_GET['role'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_clauses = [];
$params = [];

if (!empty($role)) {
    $where_clauses[] = "role = ?";
    $params[] = $role;
}

if (!empty($search)) {
    $where_clauses[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param, $search_param);
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Récupérer les utilisateurs
$sql = "SELECT * FROM utilisateurs" . $where_sql . " ORDER BY date_creation DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Gestion des utilisateurs</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Utilisateurs</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Rechercher des utilisateurs</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, prénom ou email">
                            </div>
                            <div class="col-md-4">
                                <label for="role" class="form-label">Rôle</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">Tous les rôles</option>
                                    <option value="patient" <?= $role === 'patient' ? 'selected' : '' ?>>Patients</option>
                                    <option value="medecin" <?= $role === 'medecin' ? 'selected' : '' ?>>Médecins</option>
                                    <option value="administrateur" <?= $role === 'administrateur' ? 'selected' : '' ?>>Administrateurs</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Rechercher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Actions</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="d-grid">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-user-plus me-2"></i>Ajouter un utilisateur
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                <h2><?= count($users) ?> Utilisateurs</h2>
                <div class="btn-group" role="group">
                    <a href="users.php" class="btn btn-sm <?= $role === '' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Tous (<?= $conn->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn() ?>)
                    </a>
                    <a href="users.php?role=patient" class="btn btn-sm <?= $role === 'patient' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Patients (<?= $conn->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'patient'")->fetchColumn() ?>)
                    </a>
                    <a href="users.php?role=medecin" class="btn btn-sm <?= $role === 'medecin' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Médecins (<?= $conn->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'medecin'")->fetchColumn() ?>)
                    </a>
                    <a href="users.php?role=administrateur" class="btn btn-sm <?= $role === 'administrateur' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Admins (<?= $conn->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'administrateur'")->fetchColumn() ?>)
                    </a>
                </div>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($users) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Date de création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php 
                                                $initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
                                                ?>
                                                <span><?= $initials ?></span>
                                            </div>
                                            <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php
                                        switch ($user['role']) {
                                            case 'patient':
                                                echo '<span class="badge bg-info">Patient</span>';
                                                break;
                                            case 'medecin':
                                                echo '<span class="badge bg-success">Médecin</span>';
                                                break;
                                            case 'administrateur':
                                                echo '<span class="badge bg-danger">Administrateur</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['date_creation'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="user-edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != $_SESSION['user_id']): // Ne pas permettre de supprimer son propre compte ?>
                                                <button type="button" class="btn btn-sm btn-danger" title="Supprimer" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                        data-user-id="<?= $user['id'] ?>" 
                                                        data-user-name="<?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="../assets/img/no-results.svg" alt="Aucun résultat" style="max-width: 200px; opacity: 0.6;">
                        <h3 class="mt-4 text-muted">Aucun utilisateur trouvé</h3>
                        <p class="text-muted mb-4">Aucun utilisateur ne correspond à vos critères de recherche.</p>
                        <a href="users.php" class="btn btn-primary">
                            <i class="fas fa-times me-2"></i>Effacer la recherche
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="userName"></strong> ?</p>
                <p class="text-danger">Cette action est irréversible et supprimera toutes les données associées à cet utilisateur.</p>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <input type="hidden" name="user_id" id="userId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'ajout d'utilisateur -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Ajouter un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_role" name="role" required>
                            <option value="">Sélectionner un rôle</option>
                            <option value="patient">Patient</option>
                            <option value="medecin">Médecin</option>
                            <option value="administrateur">Administrateur</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 medecin-fields" style="display: none;">
                        <label for="specialite_id" class="form-label">Spécialité <span class="text-danger">*</span></label>
                        <select class="form-select" id="specialite_id" name="specialite_id">
                            <?php foreach ($specialites as $specialite): ?>
                                <option value="<?= $specialite['id'] ?>"><?= htmlspecialchars($specialite['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_user" class="btn btn-success">Ajouter l'utilisateur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Remplir le modal de confirmation de suppression
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var userId = button.getAttribute('data-user-id');
    var userName = button.getAttribute('data-user-name');
    
    document.getElementById('userId').value = userId;
    document.getElementById('userName').textContent = userName;
});

// Afficher/masquer les champs spécifiques aux médecins
document.getElementById('add_role').addEventListener('change', function() {
    var medecinFields = document.querySelector('.medecin-fields');
    if (this.value === 'medecin') {
        medecinFields.style.display = 'block';
        document.getElementById('specialite_id').required = true;
    } else {
        medecinFields.style.display = 'none';
        document.getElementById('specialite_id').required = false;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>