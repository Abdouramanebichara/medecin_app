<?php
$pageTitle = "Gestion des médecins";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Filtrer les médecins
$specialite_id = isset($_GET['specialite']) ? intval($_GET['specialite']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Préparer la requête SQL de base
$sql = "
    SELECT m.id as medecin_id, 
           u.id as user_id, 
           u.nom, 
           u.prenom, 
           u.email,
           u.date_creation,
           s.id as specialite_id,
           s.nom as specialite,
           (SELECT COUNT(*) FROM disponibilites d WHERE d.medecin_id = m.id) as disponibilites,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id = m.id) as rendez_vous,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id = m.id AND r.statut = 'complete') as consultations
    FROM medecins m
    INNER JOIN utilisateurs u ON m.user_id = u.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE 1=1
";

// Ajouter les conditions de filtrage
$params = [];
if ($specialite_id > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $specialite_id;
}

if (!empty($search)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR s.nom LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

$sql .= " ORDER BY u.nom, u.prenom";

// Exécuter la requête
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les spécialités pour le filtre
$stmt = $conn->query("SELECT id, nom FROM specialites ORDER BY nom");
$specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Gestion des médecins</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Médecins</li>
                </ol>
            </nav>
        </div>

        <div class="dashboard-card mb-4">
            <div class="dashboard-card-header">
                <h2>Rechercher un médecin</h2>
            </div>
            <div class="dashboard-card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Recherche par nom, prénom ou email</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher...">
                    </div>
                    <div class="col-md-4">
                        <label for="specialite" class="form-label">Filtrer par spécialité</label>
                        <select class="form-select" id="specialite" name="specialite">
                            <option value="0">Toutes les spécialités</option>
                            <?php foreach ($specialites as $spec): ?>
                                <option value="<?= $spec['id'] ?>" <?= $specialite_id == $spec['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($spec['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                <h2><?= count($doctors) ?> Médecins</h2>
                <a href="users.php?role=medecin" class="btn btn-success btn-sm">
                    <i class="fas fa-user-md me-2"></i>Ajouter un médecin
                </a>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($doctors) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Spécialité</th>
                                    <th>Disponibilités</th>
                                    <th>Rendez-vous</th>
                                    <th>Consultations</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?= $doctor['user_id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php 
                                                $initials = strtoupper(substr($doctor['prenom'], 0, 1) . substr($doctor['nom'], 0, 1));
                                                ?>
                                                <span><?= $initials ?></span>
                                            </div>
                                            Dr. <?= htmlspecialchars($doctor['prenom'] . ' ' . $doctor['nom']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($doctor['email']) ?></td>
                                    <td><?= htmlspecialchars($doctor['specialite']) ?></td>
                                    <td><?= $doctor['disponibilites'] ?></td>
                                    <td><?= $doctor['rendez_vous'] ?></td>
                                    <td><?= $doctor['consultations'] ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="doctor-detail.php?id=<?= $doctor['medecin_id'] ?>" class="btn btn-sm btn-primary" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="user-edit.php?id=<?= $doctor['user_id'] ?>" class="btn btn-sm btn-secondary" title="Modifier">
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
                        <img src="../assets/img/no-doctors.svg" alt="Aucun médecin" style="max-width: 200px; opacity: 0.6;">
                        <h3 class="mt-4 text-muted">Aucun médecin trouvé</h3>
                        <p class="text-muted mb-4">Aucun médecin ne correspond à vos critères de recherche.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="doctors.php" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i>Réinitialiser la recherche
                            </a>
                            <a href="users.php?role=medecin" class="btn btn-success">
                                <i class="fas fa-user-md me-2"></i>Ajouter un médecin
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>