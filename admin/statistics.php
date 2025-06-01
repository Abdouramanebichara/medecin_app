<?php
$pageTitle = "Statistiques";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Période pour les statistiques
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'mois';
$date_debut = null;
$date_fin = null;

switch ($periode) {
    case 'semaine':
        $date_debut = date('Y-m-d', strtotime('-1 week'));
        $date_fin = date('Y-m-d');
        $label_periode = 'de la semaine dernière';
        break;
    case 'mois':
        $date_debut = date('Y-m-d', strtotime('-1 month'));
        $date_fin = date('Y-m-d');
        $label_periode = 'du mois dernier';
        break;
    case 'trimestre':
        $date_debut = date('Y-m-d', strtotime('-3 months'));
        $date_fin = date('Y-m-d');
        $label_periode = 'du trimestre';
        break;
    case 'annee':
        $date_debut = date('Y-m-d', strtotime('-1 year'));
        $date_fin = date('Y-m-d');
        $label_periode = 'de l\'année';
        break;
    case 'tous':
        $date_debut = '1970-01-01';
        $date_fin = date('Y-m-d');
        $label_periode = 'de toute la période';
        break;
    default:
        $date_debut = date('Y-m-d', strtotime('-1 month'));
        $date_fin = date('Y-m-d');
        $label_periode = 'du mois dernier';
}

// 1. Statistiques générales
// Nombre d'utilisateurs total
$stmt = $conn->query("SELECT COUNT(*) FROM utilisateurs");
$total_users = $stmt->fetchColumn();

// Nombre de patients
$stmt = $conn->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'patient'");
$total_patients = $stmt->fetchColumn();

// Nombre de médecins
$stmt = $conn->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'medecin'");
$total_doctors = $stmt->fetchColumn();

// Nombre de rendez-vous
$stmt = $conn->prepare("SELECT COUNT(*) FROM rendez_vous WHERE date_rdv BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$total_appointments = $stmt->fetchColumn();

// Nombre de consultations
$stmt = $conn->prepare("SELECT COUNT(*) FROM rendez_vous WHERE statut = 'complete' AND date_rdv BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$total_consultations = $stmt->fetchColumn();

// Nombre de rendez-vous annulés
$stmt = $conn->prepare("SELECT COUNT(*) FROM rendez_vous WHERE statut = 'annule' AND date_rdv BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$total_cancellations = $stmt->fetchColumn();

// 2. Données pour graphiques
// Utilisateurs par rôle
$stmt = $conn->query("SELECT role, COUNT(*) as count FROM utilisateurs GROUP BY role");
$users_by_role = $stmt->fetchAll(PDO::FETCH_ASSOC);

$role_labels = [];
$role_data = [];
$role_colors = [];

$role_mapping = [
    'patient' => 'Patients',
    'medecin' => 'Médecins',
    'administrateur' => 'Administrateurs'
];

$role_colors_mapping = [
    'patient' => '#4a8cca',
    'medecin' => '#17a2b8',
    'administrateur' => '#dc3545'
];

foreach ($users_by_role as $role) {
    $role_labels[] = $role_mapping[$role['role']] ?? $role['role'];
    $role_data[] = $role['count'];
    $role_colors[] = $role_colors_mapping[$role['role']] ?? '#6c757d';
}

// Rendez-vous par statut
$stmt = $conn->prepare("
    SELECT statut, COUNT(*) as count 
    FROM rendez_vous 
    WHERE date_rdv BETWEEN ? AND ? 
    GROUP BY statut
");
$stmt->execute([$date_debut, $date_fin]);
$appointments_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_labels = [];
$status_data = [];
$status_colors = [];

$status_mapping = [
    'en_attente' => 'En attente',
    'confirme' => 'Confirmé',
    'annule' => 'Annulé',
    'complete' => 'Terminé'
];

$status_colors_mapping = [
    'en_attente' => '#ffc107',
    'confirme' => '#28a745',
    'annule' => '#dc3545',
    'complete' => '#17a2b8'
];

foreach ($appointments_by_status as $status) {
    $status_labels[] = $status_mapping[$status['statut']] ?? $status['statut'];
    $status_data[] = $status['count'];
    $status_colors[] = $status_colors_mapping[$status['statut']] ?? '#6c757d';
}

// Spécialités les plus demandées
$stmt = $conn->prepare("
    SELECT s.nom as specialite, COUNT(*) as count
    FROM rendez_vous r
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE r.date_rdv BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$date_debut, $date_fin]);
$specialties_by_demand = $stmt->fetchAll(PDO::FETCH_ASSOC);

$specialties_labels = [];
$specialties_data = [];

foreach ($specialties_by_demand as $specialty) {
    $specialties_labels[] = $specialty['specialite'];
    $specialties_data[] = $specialty['count'];
}

// Évolution des rendez-vous dans le temps
if ($periode == 'semaine' || $periode == 'mois') {
    // Grouper par jour
    $group_by = 'date_rdv';
    $format = '%d/%m';
} else if ($periode == 'trimestre') {
    // Grouper par semaine
    $group_by = "DATE_FORMAT(date_rdv, '%Y-%v')";
    $format = 'Sem %v';
} else {
    // Grouper par mois
    $group_by = "DATE_FORMAT(date_rdv, '%Y-%m')";
    $format = '%b %Y';
}

$stmt = $conn->prepare("
    SELECT 
        $group_by as date_group,
        COUNT(*) as count
    FROM rendez_vous 
    WHERE date_rdv BETWEEN ? AND ?
    GROUP BY date_group
    ORDER BY date_group
");
$stmt->execute([$date_debut, $date_fin]);
$appointments_over_time = $stmt->fetchAll(PDO::FETCH_ASSOC);

$timeline_labels = [];
$timeline_data = [];

foreach ($appointments_over_time as $point) {
    if ($periode == 'semaine' || $periode == 'mois') {
        // Pour les jours
        $timeline_labels[] = date('d/m', strtotime($point['date_group']));
    } else if ($periode == 'trimestre') {
        // Pour les semaines
        list($year, $week) = explode('-', $point['date_group']);
        $timeline_labels[] = "S$week";
    } else {
        // Pour les mois
        list($year, $month) = explode('-', $point['date_group']);
        $timeline_labels[] = date('M Y', strtotime("$year-$month-01"));
    }
    $timeline_data[] = $point['count'];
}

// Médecins les plus sollicités
$stmt = $conn->prepare("
    SELECT 
        CONCAT(u.prenom, ' ', u.nom) as medecin_nom,
        s.nom as specialite,
        COUNT(r.id) as nb_rdv
    FROM rendez_vous r
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN utilisateurs u ON m.user_id = u.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE r.date_rdv BETWEEN ? AND ?
    GROUP BY r.medecin_id
    ORDER BY nb_rdv DESC
    LIMIT 5
");
$stmt->execute([$date_debut, $date_fin]);
$top_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Statistiques</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Statistiques</li>
                </ol>
            </nav>
        </div>

        <div class="mb-4">
            <div class="btn-group" role="group">
                <a href="?periode=semaine" class="btn <?= $periode === 'semaine' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar-week me-2"></i>Semaine
                </a>
                <a href="?periode=mois" class="btn <?= $periode === 'mois' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar-alt me-2"></i>Mois
                </a>
                <a href="?periode=trimestre" class="btn <?= $periode === 'trimestre' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar me-2"></i>Trimestre
                </a>
                <a href="?periode=annee" class="btn <?= $periode === 'annee' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-calendar-alt me-2"></i>Année
                </a>
                <a href="?periode=tous" class="btn <?= $periode === 'tous' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fas fa-infinity me-2"></i>Tout
                </a>
            </div>
        </div>

        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            Statistiques <?= $label_periode ?> (du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?>)
        </div>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="stat-card primary h-100">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= $total_users ?></div>
                    <div class="stat-label">Utilisateurs</div>
                    <div class="stat-breakdown">
                        <span><?= $total_patients ?> patients</span>
                        <span>•</span>
                        <span><?= $total_doctors ?> médecins</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="stat-card success h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= $total_appointments ?></div>
                    <div class="stat-label">Rendez-vous</div>
                    <div class="stat-breakdown">
                        <span><?= $total_consultations ?> consultations</span>
                        <span>•</span>
                        <span><?= $total_cancellations ?> annulations</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="stat-card info h-100">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-value">
                        <?= $total_appointments > 0 ? round(($total_consultations / $total_appointments) * 100) : 0 ?>%
                    </div>
                    <div class="stat-label">Taux de consultations</div>
                    <div class="stat-breakdown">
                        <div class="progress">
                            <div class="progress-bar bg-info" role="progressbar" 
                                style="width: <?= $total_appointments > 0 ? ($total_consultations / $total_appointments) * 100 : 0 ?>%" 
                                aria-valuenow="<?= $total_appointments > 0 ? ($total_consultations / $total_appointments) * 100 : 0 ?>" 
                                aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Évolution des rendez-vous</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="timelineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Répartition des utilisateurs</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="roleChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Rendez-vous par statut</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Spécialités les plus demandées</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if (count($specialties_by_demand) > 0): ?>
                            <div class="chart-container">
                                <canvas id="specialtiesChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <p class="text-muted">Aucune donnée disponible pour cette période.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Médecins les plus sollicités</h2>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($top_doctors) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Médecin</th>
                                    <th>Spécialité</th>
                                    <th>Nombre de rendez-vous</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_doctors as $index => $doctor): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>Dr. <?= htmlspecialchars($doctor['medecin_nom']) ?></td>
                                    <td><?= htmlspecialchars($doctor['specialite']) ?></td>
                                    <td><?= $doctor['nb_rdv'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted">Aucune donnée disponible pour cette période.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration du graphique d'évolution
var ctxTimeline = document.getElementById('timelineChart').getContext('2d');
var timelineChart = new Chart(ctxTimeline, {
    type: 'line',
    data: {
        labels: <?= json_encode($timeline_labels) ?>,
        datasets: [{
            label: 'Nombre de rendez-vous',
            data: <?= json_encode($timeline_data) ?>,
            backgroundColor: 'rgba(74, 140, 202, 0.2)',
            borderColor: '#4a8cca',
            borderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Configuration du graphique des rôles
var ctxRole = document.getElementById('roleChart').getContext('2d');
var roleChart = new Chart(ctxRole, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($role_labels) ?>,
        datasets: [{
            data: <?= json_encode($role_data) ?>,
            backgroundColor: <?= json_encode($role_colors) ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Configuration du graphique des statuts
var ctxStatus = document.getElementById('statusChart').getContext('2d');
var statusChart = new Chart(ctxStatus, {
    type: 'pie',
    data: {
        labels: <?= json_encode($status_labels) ?>,
        datasets: [{
            data: <?= json_encode($status_data) ?>,
            backgroundColor: <?= json_encode($status_colors) ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

<?php if (count($specialties_by_demand) > 0): ?>
// Configuration du graphique des spécialités
var ctxSpecialties = document.getElementById('specialtiesChart').getContext('2d');
var specialtiesChart = new Chart(ctxSpecialties, {
    type: 'bar',
    data: {
        labels: <?= json_encode($specialties_labels) ?>,
        datasets: [{
            label: 'Nombre de rendez-vous',
            data: <?= json_encode($specialties_data) ?>,
            backgroundColor: '#17a2b8',
            borderColor: '#17a2b8',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>