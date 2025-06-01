<?php
$pageTitle = "Gestion des patients";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Filtrer les patients
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Préparer la requête SQL de base
$sql = "
    SELECT p.id as patient_id, 
           u.id as user_id, 
           u.nom, 
           u.prenom, 
           u.email,
           u.date_creation,
           p.telephone,
           p.date_naissance,
           p.sexe,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.patient_id = p.id) as rendez_vous,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.patient_id = p.id AND r.statut = 'complete') as consultations
    FROM patients p
    INNER JOIN utilisateurs u ON p.user_id = u.id
    WHERE 1=1
";

// Ajouter les conditions de filtrage
$params = [];
if (!empty($search)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR p.telephone LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

$sql .= " ORDER BY u.nom, u.prenom";

// Exécuter la requête
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Gestion des patients</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Patients</li>
                </ol>
            </nav>
        </div>

        <div class="dashboard-card mb-4">
            <div class="dashboard-card-header">
                <h2>Rechercher un patient</h2>
            </div>
            <div class="dashboard-card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-10">
                        <label for="search" class="form-label">Recherche par nom, prénom, email ou téléphone</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                <h2><?= count($patients) ?> Patients</h2>
                <a href="users.php?role=patient" class="btn btn-success btn-sm">
                    <i class="fas fa-user-plus me-2"></i>Ajouter un patient
                </a>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($patients) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Âge</th>
                                    <th>Sexe</th>
                                    <th>Rendez-vous</th>
                                    <th>Consultations</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient): 
                                    // Calcul de l'âge
                                    $age = '';
                                    if (!empty($patient['date_naissance'])) {
                                        $birthdate = new DateTime($patient['date_naissance']);
                                        $today = new DateTime();
                                        $age = $birthdate->diff($today)->y . ' ans';
                                    }
                                    
                                    // Formatage du sexe
                                    $sexeDisplay = '';
                                    switch ($patient['sexe']) {
                                        case 'M':
                                            $sexeDisplay = 'Homme';
                                            break;
                                        case 'F':
                                            $sexeDisplay = 'Femme';
                                            break;
                                        case 'Autre':
                                            $sexeDisplay = 'Autre';
                                            break;
                                        default:
                                            $sexeDisplay = 'Non spécifié';
                                    }
                                ?>
                                <tr>
                                    <td><?= $patient['user_id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php 
                                                $initials = strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1));
                                                ?>
                                                <span><?= $initials ?></span>
                                            </div>
                                            <?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($patient['email']) ?></td>
                                    <td>
                                        <?php if (!empty($patient['telephone'])): ?>
                                            <?= htmlspecialchars($patient['telephone']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $age ?: '<span class="text-muted">Non renseigné</span>' ?></td>
                                    <td><?= $sexeDisplay ?></td>
                                    <td><?= $patient['rendez_vous'] ?></td>
                                    <td><?= $patient['consultations'] ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="patient-detail.php?id=<?= $patient['patient_id'] ?>" class="btn btn-sm btn-primary" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="user-edit.php?id=<?= $patient['user_id'] ?>" class="btn btn-sm btn-secondary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="../assets/img/no-patients.svg" alt="Aucun patient" style="max-width: 200px; opacity: 0.6;">
                        <h3 class="mt-4 text-muted">Aucun patient trouvé</h3>
                        <p class="text-muted mb-4">Aucun patient ne correspond à vos critères de recherche.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="patients.php" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i>Réinitialiser la recherche
                            </a>
                            <a href="users.php?role=patient" class="btn btn-success">
                                <i class="fas fa-user-plus me-2"></i>Ajouter un patient
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>