<?php
$pageTitle = "Mes patients";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle médecin
requireRole('medecin');

// Récupérer l'ID du médecin
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM medecins WHERE user_id = ?");
$stmt->execute([$user_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);
$medecin_id = $medecin['id'];

// Récupérer les patients (ceux qui ont au moins un RDV avec ce médecin)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = "";
$params = [$medecin_id];

if (!empty($search)) {
    $where_clause = "AND (u.nom LIKE ? OR u.prenom LIKE ? OR p.telephone LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param, $search_param);
}

$stmt = $conn->prepare("
    SELECT DISTINCT 
        p.id as patient_id, 
        u.id as user_id,
        u.nom, 
        u.prenom, 
        p.telephone, 
        p.date_naissance, 
        p.sexe,
        (SELECT COUNT(*) FROM rendez_vous rv WHERE rv.patient_id = p.id AND rv.medecin_id = ?) as rdv_count,
        (SELECT MAX(rv2.date_rdv) FROM rendez_vous rv2 WHERE rv2.patient_id = p.id AND rv2.medecin_id = ? AND rv2.statut = 'complete') as last_visit
    FROM patients p
    INNER JOIN utilisateurs u ON p.user_id = u.id
    INNER JOIN rendez_vous r ON p.id = r.patient_id
    WHERE r.medecin_id = ? $where_clause
    GROUP BY p.id
    ORDER BY u.nom, u.prenom
");

// Ajouter les paramètres additionnels pour la requête (medecin_id répété 2 fois dans SELECT et une fois dans WHERE)
array_unshift($params, $medecin_id, $medecin_id);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Mes patients</h1>
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
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par nom, prénom ou téléphone...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h2><?= count($patients) ?> Patients</h2>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($patients) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Contact</th>
                                    <th>Âge</th>
                                    <th>Sexe</th>
                                    <th>Rendez-vous</th>
                                    <th>Dernière visite</th>
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
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="patient-avatar me-2">
                                                <?php 
                                                $initials = strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1));
                                                ?>
                                                <span><?= $initials ?></span>
                                            </div>
                                            <div>
                                                <?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($patient['telephone'])): ?>
                                            <a href="tel:<?= htmlspecialchars($patient['telephone']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($patient['telephone']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $age ?: '<span class="text-muted">Non renseigné</span>' ?></td>
                                    <td><?= $sexeDisplay ?></td>
                                    <td><?= $patient['rdv_count'] ?></td>
                                    <td>
                                        <?= $patient['last_visit'] ? date('d/m/Y', strtotime($patient['last_visit'])) : '<span class="text-muted">Jamais</span>' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="patient-detail.php?id=<?= $patient['patient_id'] ?>" class="btn btn-sm btn-primary" title="Voir fiche patient">
                                                <i class="fas fa-user"></i>
                                            </a>
                                            <a href="patient-history.php?id=<?= $patient['patient_id'] ?>" class="btn btn-sm btn-info" title="Historique médical">
                                                <i class="fas fa-history"></i>
                                            </a>
                                            <a href="appointments.php?patient_id=<?= $patient['patient_id'] ?>" class="btn btn-sm btn-secondary" title="Voir rendez-vous">
                                                <i class="fas fa-calendar-alt"></i>
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
                        <p class="text-muted mb-4">
                            <?= empty($search) ? 
                                "Vous n'avez pas encore de patients. Ils apparaîtront ici quand vous aurez des rendez-vous." : 
                                "Aucun patient ne correspond à votre recherche." ?>
                        </p>
                        <?php if (!empty($search)): ?>
                            <a href="patients.php" class="btn btn-primary">
                                <i class="fas fa-times me-2"></i>Effacer la recherche
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>