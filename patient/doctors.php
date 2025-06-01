<?php
$pageTitle = "Nos médecins";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle patient
requireRole('patient');

// Gérer le filtrage et la recherche
$specialite_id = isset($_GET['specialite']) ? intval($_GET['specialite']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Préparer la requête SQL de base
$sql = "
    SELECT m.id as medecin_id, u.id as user_id, u.nom, u.prenom, s.id as specialite_id, s.nom as specialite
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
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR s.nom LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY u.nom, u.prenom";

// Exécuter la requête
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les spécialités pour le filtrage
$stmt = $conn->query("SELECT id, nom FROM specialites ORDER BY nom");
$specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Nos médecins</h1>
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
                    <div class="col-md-5">
                        <label for="search" class="form-label">Recherche par nom ou prénom</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher...">
                    </div>
                    <div class="col-md-5">
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

        <div class="row">
            <?php if (count($doctors) > 0): ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="col-md-6 col-xl-4 mb-4">
                        <div class="card doctor-card h-100">
                            <div class="card-body">
                                <div class="doctor-avatar">
                                    <?php 
                                    $initials = strtoupper(substr($doctor['prenom'], 0, 1) . substr($doctor['nom'], 0, 1));
                                    ?>
                                    <span><?= $initials ?></span>
                                </div>
                                <h4 class="doctor-name">Dr. <?= htmlspecialchars($doctor['prenom'] . ' ' . $doctor['nom']) ?></h4>
                                <p class="doctor-specialty">
                                    <i class="fas fa-stethoscope me-2"></i><?= htmlspecialchars($doctor['specialite']) ?>
                                </p>
                                
                                <?php
                                // Récupérer les disponibilités du médecin
                                $stmt = $conn->prepare("
                                    SELECT jour, heure_debut, heure_fin
                                    FROM disponibilites
                                    WHERE medecin_id = ?
                                    ORDER BY 
                                        CASE jour
                                            WHEN 'lundi' THEN 1
                                            WHEN 'mardi' THEN 2
                                            WHEN 'mercredi' THEN 3
                                            WHEN 'jeudi' THEN 4
                                            WHEN 'vendredi' THEN 5
                                            WHEN 'samedi' THEN 6
                                            WHEN 'dimanche' THEN 7
                                        END
                                ");
                                $stmt->execute([$doctor['medecin_id']]);
                                $disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Traduction des jours en français
                                $joursFr = [
                                    'lundi' => 'Lundi',
                                    'mardi' => 'Mardi',
                                    'mercredi' => 'Mercredi',
                                    'jeudi' => 'Jeudi',
                                    'vendredi' => 'Vendredi',
                                    'samedi' => 'Samedi',
                                    'dimanche' => 'Dimanche'
                                ];
                                ?>
                                
                                <div class="doctor-availability mt-3 mb-3">
                                    <h6><i class="fas fa-clock me-2"></i>Disponibilités</h6>
                                    <?php if (count($disponibilites) > 0): ?>
                                        <ul class="list-unstyled small">
                                            <?php foreach ($disponibilites as $dispo): ?>
                                                <li>
                                                    <span class="day"><?= $joursFr[$dispo['jour']] ?> :</span>
                                                    <span class="hours">
                                                        <?= substr($dispo['heure_debut'], 0, 5) ?> - <?= substr($dispo['heure_fin'], 0, 5) ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-muted small">Aucune disponibilité renseignée</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center">
                                    <a href="take-appointment.php?medecin=<?= $doctor['medecin_id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-calendar-plus me-2"></i>Prendre rendez-vous
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="dashboard-card-body text-center py-5">
                            <img src="../assets/img/no-results.svg" alt="Aucun résultat" style="max-width: 200px; opacity: 0.6;">
                            <h3 class="mt-4 text-muted">Aucun médecin trouvé</h3>
                            <p class="text-muted mb-4">Aucun médecin ne correspond à vos critères de recherche.</p>
                            <a href="doctors.php" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i>Réinitialiser la recherche
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>