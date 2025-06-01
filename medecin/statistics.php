<?php
$pageTitle = "Statistiques";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle médecin
requireRole('medecin');

// Récupérer l'ID du médecin
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT m.id, m.specialite_id, s.nom AS specialite FROM medecins m JOIN specialites s ON m.specialite_id = s.id WHERE m.user_id = ?");
$stmt->execute([$user_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);
$medecin_id = $medecin['id'];
$specialite = $medecin['specialite'];

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
    default:
        $date_debut = date('Y-m-d', strtotime('-1 month'));
        $date_fin = date('Y-m-d');
        $label_periode = 'du mois dernier';
}

// 1. Statistiques générales
// Nombre total de rendez-vous
$stmt = $conn->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = ? AND date_rdv BETWEEN ? AND ?");
$stmt->execute([$medecin_id, $date_debut, $date_fin]);
$total_rdv = $stmt->fetchColumn();

// Nombre de consultations terminées
$stmt = $conn->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = ? AND statut = 'complete' AND date_rdv BETWEEN ? AND ?");
$stmt->execute([$medecin_id, $date_debut, $date_fin]);
$total_consultations = $stmt->fetchColumn();

// Nombre de rendez-vous annulés
$stmt = $conn->prepare("SELECT COUNT(*) FROM rendez_vous WHERE medecin_id = ? AND statut = 'annule' AND date_rdv BETWEEN ? AND ?");
$stmt->execute([$medecin_id, $date_debut, $date_fin]);
$total_annulations = $stmt->fetchColumn();

// Nombre de patients uniques vus
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT patient_id) FROM rendez_vous 
    WHERE medecin_id = ? AND statut = 'complete' AND date_rdv BETWEEN ? AND ?
");
$stmt->execute([$medecin_id, $date_debut, $date_fin]);
$patients_uniques = $stmt->fetchColumn();

// 2. Données pour graphiques
// Rendez-vous par jour de la semaine
$stmt = $conn->prepare("
    SELECT 
        DAYOFWEEK(date_rdv) AS jour_semaine,
        COUNT(*) AS nombre
    FROM rendez_vous 
    WHERE medecin_id = ? AND date_rdv BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(date_rdv)
    ORDER BY jour_semaine
");
$stmt->execute([$medecin_id, $date_debut, $date_fin]);
$rdv_par_jour = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conversion des jours numériques en noms de jours
$jours_semaine = [
    1 => 'Dimanche',
    2 => 'Lundi',
    3 => 'Mardi',
    4 => 'Mercredi',
    5 => 'Jeudi',
    6 => 'Vendredi',
    7 => 'Samedi'
];

$labels_jours = [];
$data_jours = [];

// Initialiser toutes les valeurs à 0
foreach ($jours_semaine as $num => $nom) {
    $labels_jours[$num] = $nom;
    $data_jours[$num] = 0;
}

// Remplir avec les données réelles
foreach ($rdv_par_jour as $jour) {
    $data_jours[$jour['jour_semaine']] = $jour['nombre'];
}

// Réordonner pour commencer par lundi
$labels_jours_reordered = [];
$data_jours_reordered = [];

for ($i = 2; $i <= 7; $i++) {
    $labels_jours_reordered[] = $labels_jours[$i];
    $data_jours_reordered[] = $data_jours[$i];
}
// Ajouter dimanche à la fin
$labels_jours_reordered[] = $labels_jours[1];
$data_jours_reordered[] = $data_jours[1];

// Rendez-vous par statut
$stmt = $conn->prepare("
    SELECT 
        statut,
        COUNT(*) AS nombre
    FROM rendez_vous 
    WHERE medecin_id = ? AND date_rdv BETWEEN ? AND ?
    GROUP BY statut
");
$stmt->execute([$medecin_id, $date_debut, $date_fin]);
$rdv_par_statut = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels_statut = [];
$data_statut = [];
$colors_statut = [];

$statut_mapping = [
    'en_attente' => 'En attente',
    'confirme' => 'Confirmé',
    'annule' => 'Annulé',
    'complete' => 'Terminé'
];

$statut_colors = [
    'en_attente' => '#ffc107',
    'confirme' => '#28a745',
    'annule' => '#dc3545',
    'complete' => '#17a2b8'
];

foreach ($rdv_par_statut as $statut) {
    $statut_nom = $statut_mapping[$statut['statut']] ?? $statut['statut'];
    $labels_statut[] = $statut_nom;
    $data_statut[] = $statut['nombre'];
    $colors_statut[] = $statut_colors[$statut['statut']] ?? '#6c757d';
}

// Évolution des rendez-vous sur la période
if ($periode == 'semaine' || $periode == 'mois') {
    // Grouper par jour
    $group_by = 'DAY(date_rdv)';
    $format = '%d/%m';
} else if ($periode == 'trimestre') {
    // Grouper par semaine
    $group_by = 'WEEK(date_rdv)';
    $format = 'Sem %v';
} else {
    // Grouper par mois
    $group_by = 'MONTH(date_rdv)';
    $format = '%b';
}

$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(date_rdv, '$format') AS periode_label,
        $group_by AS periode_valeur,
        COUNT(*) AS nombre
    FROM rendez_vous 
    WHERE medecin_id = ? AND date_rdv BETWEEN ? AND ?
    GROUP BY periode_label, periode_valeur
    ORDER BY date_rdv
");
$stmt->execute([$medecin_id, $date_debut, $date_fin]);
$evolution_rdv = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels_evolution = [];
$data_evolution = [];

foreach ($evolution_rdv as $point) {
    $labels_evolution[] = $point['periode_label'];
    $data_evolution[] = $point['nombre'];
}
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

        <div class="mb-4 d-flex flex-wrap justify-content-between">
            <div>
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
                </div>
            </div>
        </div>

        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            Statistiques <?= $label_periode ?> (du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?>)
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card primary h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= $total_rdv ?></div>
                    <div class="stat-label">Rendez-vous totaux</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card success h-100">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-value"><?= $total_consultations ?></div>
                    <div class="stat-label">Consultations réalisées</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card danger h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-value"><?= $total_annulations ?></div>
                    <div class="stat-label">Rendez-vous annulés</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card info h-100">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= $patients_uniques ?></div>
                    <div class="stat-label">Patients reçus</div>
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
                            <canvas id="evolutionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Répartition par statut</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="statutChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Rendez-vous par jour de semaine</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="joursChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <h2>Performance</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="performance-stat">
                                    <div class="performance-label">Taux de complétion</div>
                                    <div class="performance-value">
                                        <?= $total_rdv > 0 ? round(($total_consultations / $total_rdv) * 100) : 0 ?>%
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                            style="width: <?= $total_rdv > 0 ? ($total_consultations / $total_rdv) * 100 : 0 ?>%" 
                                            aria-valuenow="<?= $total_rdv > 0 ? ($total_consultations / $total_rdv) * 100 : 0 ?>" 
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="performance-stat">
                                    <div class="performance-label">Taux d'annulation</div>
                                    <div class="performance-value">
                                        <?= $total_rdv > 0 ? round(($total_annulations / $total_rdv) * 100) : 0 ?>%
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-danger" role="progressbar" 
                                            style="width: <?= $total_rdv > 0 ? ($total_annulations / $total_rdv) * 100 : 0 ?>%" 
                                            aria-valuenow="<?= $total_rdv > 0 ? ($total_annulations / $total_rdv) * 100 : 0 ?>" 
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="performance-summary">
                                    <h5>Résumé</h5>
                                    <p>
                                        Sur la période du <?= date('d/m/Y', strtotime($date_debut)) ?> au <?= date('d/m/Y', strtotime($date_fin)) ?>,
                                        vous avez reçu <?= $patients_uniques ?> patients différents pour un total de <?= $total_consultations ?> consultations.
                                        <?php if ($total_annulations > 0): ?>
                                            <?= $total_annulations ?> rendez-vous ont été annulés, 
                                            soit un taux d'annulation de <?= $total_rdv > 0 ? round(($total_annulations / $total_rdv) * 100) : 0 ?>%.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration du graphique d'évolution
var ctxEvolution = document.getElementById('evolutionChart').getContext('2d');
var evolutionChart = new Chart(ctxEvolution, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels_evolution) ?>,
        datasets: [{
            label: 'Nombre de rendez-vous',
            data: <?= json_encode($data_evolution) ?>,
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
                    stepSize: 1,
                    precision: 0
                }
            }
        },
        plugins: {
            title: {
                display: true,
                text: 'Évolution du nombre de rendez-vous'
            }
        }
    }
});

// Configuration du graphique des statuts
var ctxStatut = document.getElementById('statutChart').getContext('2d');
var statutChart = new Chart(ctxStatut, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($labels_statut) ?>,
        datasets: [{
            data: <?= json_encode($data_statut) ?>,
            backgroundColor: <?= json_encode($colors_statut) ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            title: {
                display: true,
                text: 'Répartition par statut'
            }
        }
    }
});

// Configuration du graphique des jours
var ctxJours = document.getElementById('joursChart').getContext('2d');
var joursChart = new Chart(ctxJours, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_jours_reordered) ?>,
        datasets: [{
            label: 'Nombre de rendez-vous',
            data: <?= json_encode($data_jours_reordered) ?>,
            backgroundColor: '#4a8cca',
            borderColor: '#4a8cca',
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
                    stepSize: 1,
                    precision: 0
                }
            }
        },
        plugins: {
            title: {
                display: true,
                text: 'Rendez-vous par jour de la semaine'
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>