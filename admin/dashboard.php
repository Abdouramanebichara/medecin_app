<?php
$pageTitle = "Tableau de bord - Administration";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Statistiques
// 1. Nombre total d'utilisateurs
$stmt = $conn->query("SELECT COUNT(*) AS total FROM utilisateurs");
$totalUsers = $stmt->fetchColumn();

// 2. Nombre de patients
$stmt = $conn->query("SELECT COUNT(*) AS total FROM utilisateurs WHERE role = 'patient'");
$totalPatients = $stmt->fetchColumn();

// 3. Nombre de médecins
$stmt = $conn->query("SELECT COUNT(*) AS total FROM utilisateurs WHERE role = 'medecin'");
$totalDoctors = $stmt->fetchColumn();

// 4. Nombre de rendez-vous
$stmt = $conn->query("SELECT COUNT(*) AS total FROM rendez_vous");
$totalAppointments = $stmt->fetchColumn();

// 5. Répartition des rendez-vous par statut
$stmt = $conn->query("
    SELECT 
        statut, 
        COUNT(*) AS count 
    FROM rendez_vous 
    GROUP BY statut
");
$appointmentsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Rendez-vous par mois (6 derniers mois)
$stmt = $conn->query("
    SELECT 
        DATE_FORMAT(date_rdv, '%Y-%m') AS month, 
        COUNT(*) AS count 
    FROM rendez_vous 
    WHERE date_rdv >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_rdv, '%Y-%m')
    ORDER BY DATE_FORMAT(date_rdv, '%Y-%m')
");
$appointmentsByMonth = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Derniers utilisateurs inscrits
$stmt = $conn->query("
    SELECT id, nom, prenom, email, role, date_creation
    FROM utilisateurs
    ORDER BY date_creation DESC
    LIMIT 5
");
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Derniers rendez-vous
$stmt = $conn->query("
    SELECT r.*, 
           p.user_id AS patient_user_id,
           up.nom AS patient_nom, 
           up.prenom AS patient_prenom,
           m.user_id AS medecin_user_id,
           um.nom AS medecin_nom, 
           um.prenom AS medecin_prenom,
           s.nom AS specialite
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    JOIN utilisateurs up ON p.user_id = up.id
    JOIN medecins m ON r.medecin_id = m.id
    JOIN utilisateurs um ON m.user_id = um.id
    JOIN specialites s ON m.specialite_id = s.id
    ORDER BY r.date_creation DESC
    LIMIT 5
");
$recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Tableau de bord administrateur</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active" aria-current="page">Tableau de bord</li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card primary h-100">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <div class="stat-label">Utilisateurs totaux</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card success h-100">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="stat-value"><?= $totalDoctors ?></div>
                    <div class="stat-label">Médecins</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card info h-100">
                    <div class="stat-icon">
                        <i class="fas fa-procedures"></i>
                    </div>
                    <div class="stat-value"><?= $totalPatients ?></div>
                    <div class="stat-label">Patients</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card accent h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= $totalAppointments ?></div>
                    <div class="stat-label">Rendez-vous</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Rendez-vous par mois</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="appointmentsByMonthChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h2>Répartition par statut</h2>
                            </div>
                            <div class="dashboard-card-body">
                                <div class="chart-container">
                                    <canvas id="appointmentsByStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h2>Répartition utilisateurs</h2>
                            </div>
                            <div class="dashboard-card-body">
                                <div class="chart-container">
                                    <canvas id="usersByRoleChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Derniers rendez-vous</h2>
                        <a href="appointments.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="dashboard-card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Médecin</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAppointments as $appointment): 
                                        $statusBadge = '';
                                        switch ($appointment['statut']) {
                                            case 'en_attente':
                                                $statusBadge = '<span class="badge bg-warning text-dark">En attente</span>';
                                                break;
                                            case 'confirme':
                                                $statusBadge = '<span class="badge bg-success">Confirmé</span>';
                                                break;
                                            case 'annule':
                                                $statusBadge = '<span class="badge bg-danger">Annulé</span>';
                                                break;
                                            case 'complete':
                                                $statusBadge = '<span class="badge bg-info">Terminé</span>';
                                                break;
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($appointment['patient_prenom'] . ' ' . $appointment['patient_nom']) ?></td>
                                        <td>Dr. <?= htmlspecialchars($appointment['medecin_prenom'] . ' ' . $appointment['medecin_nom']) ?> (<?= htmlspecialchars($appointment['specialite']) ?>)</td>
                                        <td><?= date('d/m/Y H:i', strtotime($appointment['date_rdv'] . ' ' . $appointment['heure_rdv'])) ?></td>
                                        <td><?= $statusBadge ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Derniers utilisateurs</h2>
                        <a href="users.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="dashboard-card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentUsers as $user): 
                                $roleBadge = '';
                                switch ($user['role']) {
                                    case 'administrateur':
                                        $roleBadge = '<span class="badge bg-danger">Admin</span>';
                                        break;
                                    case 'medecin':
                                        $roleBadge = '<span class="badge bg-success">Médecin</span>';
                                        break;
                                    case 'patient':
                                        $roleBadge = '<span class="badge bg-info">Patient</span>';
                                        break;
                                }
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h5>
                                    <?= $roleBadge ?>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($user['email']) ?></p>
                                <small class="text-muted">Inscrit le <?= date('d/m/Y', strtotime($user['date_creation'])) ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Actions rapides</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="d-grid gap-2">
                            <a href="users.php" class="btn btn-primary">
                                <i class="fas fa-users me-2"></i> Gérer les utilisateurs
                            </a>
                            <a href="doctors.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-md me-2"></i> Gérer les médecins
                            </a>
                            <a href="specialties.php" class="btn btn-outline-primary">
                                <i class="fas fa-stethoscope me-2"></i> Gérer les spécialités
                            </a>
                            <a href="appointments.php" class="btn btn-outline-primary">
                                <i class="fas fa-calendar-alt me-2"></i> Gérer les rendez-vous
                            </a>
                            <a href="statistics.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-pie me-2"></i> Statistiques avancées
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Préparation des données pour les graphiques
var appointmentStatusData = {
    labels: [],
    counts: [],
    colors: []
};

<?php
$statusColors = [
    'en_attente' => '#ffc107',
    'confirme' => '#28a745',
    'annule' => '#dc3545',
    'complete' => '#17a2b8'
];

$statusLabels = [
    'en_attente' => 'En attente',
    'confirme' => 'Confirmé',
    'annule' => 'Annulé',
    'complete' => 'Terminé'
];

foreach ($appointmentsByStatus as $status) {
    echo "appointmentStatusData.labels.push('" . $statusLabels[$status['statut']] . "');\n";
    echo "appointmentStatusData.counts.push(" . $status['count'] . ");\n";
    echo "appointmentStatusData.colors.push('" . $statusColors[$status['statut']] . "');\n";
}
?>

var monthLabels = [];
var appointmentCounts = [];

<?php
foreach ($appointmentsByMonth as $item) {
    $date = DateTime::createFromFormat('Y-m', $item['month']);
    $monthLabel = $date->format('M Y');
    echo "monthLabels.push('" . $monthLabel . "');\n";
    echo "appointmentCounts.push(" . $item['count'] . ");\n";
}
?>

// Graphique rendez-vous par statut
var statusChart = new Chart(document.getElementById('appointmentsByStatusChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: appointmentStatusData.labels,
        datasets: [{
            data: appointmentStatusData.counts,
            backgroundColor: appointmentStatusData.colors,
            borderWidth: 0
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

// Graphique rendez-vous par mois
var monthlyChart = new Chart(document.getElementById('appointmentsByMonthChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Nombre de rendez-vous',
            data: appointmentCounts,
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
                    precision: 0
                }
            }
        }
    }
});

// Graphique répartition des utilisateurs
var userRoleChart = new Chart(document.getElementById('usersByRoleChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: ['Patients', 'Médecins', 'Administrateurs'],
        datasets: [{
            data: [<?= $totalPatients ?>, <?= $totalDoctors ?>, <?= $totalUsers - $totalPatients - $totalDoctors ?>],
            backgroundColor: ['#17a2b8', '#28a745', '#dc3545'],
            borderWidth: 0
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
</script>

<?php require_once '../includes/footer.php'; ?>