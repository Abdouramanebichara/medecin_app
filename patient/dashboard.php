<?php
$pageTitle = "Tableau de bord - Patient";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle patient
requireRole('patient');

// Récupérer l'ID du patient
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_id = $patient['id'];

// Récupérer les statistiques
// 1. Nombre total de rendez-vous
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM rendez_vous WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$totalAppointments = $stmt->fetchColumn();

// 2. Rendez-vous à venir
$stmt = $conn->prepare("SELECT COUNT(*) AS upcoming FROM rendez_vous WHERE patient_id = ? AND date_rdv >= CURDATE() AND statut IN ('en_attente', 'confirme')");
$stmt->execute([$patient_id]);
$upcomingAppointments = $stmt->fetchColumn();

// 3. Rendez-vous passés
$stmt = $conn->prepare("SELECT COUNT(*) AS past FROM rendez_vous WHERE patient_id = ? AND (date_rdv < CURDATE() OR statut = 'complete')");
$stmt->execute([$patient_id]);
$pastAppointments = $stmt->fetchColumn();

// 4. Rendez-vous annulés
$stmt = $conn->prepare("SELECT COUNT(*) AS canceled FROM rendez_vous WHERE patient_id = ? AND statut = 'annule'");
$stmt->execute([$patient_id]);
$canceledAppointments = $stmt->fetchColumn();

// Récupérer les prochains rendez-vous
$stmt = $conn->prepare("
    SELECT r.*, u.prenom, u.nom, s.nom AS specialite 
    FROM rendez_vous r 
    INNER JOIN medecins m ON r.medecin_id = m.id 
    INNER JOIN utilisateurs u ON m.user_id = u.id 
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE r.patient_id = ? AND r.date_rdv >= CURDATE() AND r.statut IN ('en_attente', 'confirme')
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    LIMIT 5
");
$stmt->execute([$patient_id]);
$upcomingAppointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les dernières notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY date_envoi DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Tableau de bord</h1>
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
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?= $totalAppointments ?></div>
                    <div class="stat-label">Total rendez-vous</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card secondary h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value"><?= $upcomingAppointments ?></div>
                    <div class="stat-label">Rendez-vous à venir</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card success h-100">
                    <div class="stat-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-value"><?= $pastAppointments ?></div>
                    <div class="stat-label">Rendez-vous passés</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card warning h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-value"><?= $canceledAppointments ?></div>
                    <div class="stat-label">Rendez-vous annulés</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Prochains rendez-vous</h2>
                        <a href="appointments.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if (count($upcomingAppointmentsList) > 0): ?>
                            <?php foreach ($upcomingAppointmentsList as $appointment): 
                                $appointmentDate = new DateTime($appointment['date_rdv']);
                                $day = $appointmentDate->format('d');
                                $month = $appointmentDate->format('M');
                                
                                $badgeClass = '';
                                $statusText = '';
                                switch ($appointment['statut']) {
                                    case 'en_attente':
                                        $badgeClass = 'bg-warning text-dark';
                                        $statusText = 'En attente';
                                        break;
                                    case 'confirme':
                                        $badgeClass = 'bg-success';
                                        $statusText = 'Confirmé';
                                        break;
                                    case 'annule':
                                        $badgeClass = 'bg-danger';
                                        $statusText = 'Annulé';
                                        break;
                                    case 'complete':
                                        $badgeClass = 'bg-info';
                                        $statusText = 'Terminé';
                                        break;
                                }
                            ?>
                                <div class="card mb-3 appointment-card <?= $appointment['statut'] ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-auto">
                                                <div class="appointment-date">
                                                    <span class="day"><?= $day ?></span>
                                                    <span class="month"><?= $month ?></span>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <h5 class="card-title mb-1">Dr. <?= htmlspecialchars($appointment['prenom'] . ' ' . $appointment['nom']) ?></h5>
                                                <p class="card-subtitle text-muted mb-2"><?= htmlspecialchars($appointment['specialite']) ?></p>
                                                <div class="appointment-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?= date('H:i', strtotime($appointment['heure_rdv'])) ?>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <span class="badge <?= $badgeClass ?>"><?= $statusText ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                <p>Vous n'avez pas de rendez-vous à venir.</p>
                                <a href="take-appointment.php" class="btn btn-primary">Prendre rendez-vous</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Statistiques de rendez-vous</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="appointmentsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <h2>Notifications</h2>
                        <a href="notifications.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="dashboard-card-body p-0">
                        <div class="notifications-list">
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item d-flex align-items-center <?= $notification['lu'] ? '' : 'unread' ?>">
                                        <div class="notification-icon">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?= htmlspecialchars($notification['message']) ?></div>
                                            <div class="notification-time">
                                                <?= date('d/m/Y H:i', strtotime($notification['date_envoi'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                    <p>Vous n'avez pas de notifications.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Actions rapides</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="d-grid gap-2">
                            <a href="take-appointment.php" class="btn btn-primary">
                                <i class="fas fa-calendar-plus me-2"></i> Prendre rendez-vous
                            </a>
                            <a href="appointments.php" class="btn btn-outline-primary">
                                <i class="fas fa-calendar-alt me-2"></i> Gérer mes rendez-vous
                            </a>
                            <a href="history.php" class="btn btn-outline-primary">
                                <i class="fas fa-history me-2"></i> Historique médical
                            </a>
                            <a href="doctors.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-md me-2"></i> Voir les médecins
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Graphique des rendez-vous
var ctx = document.getElementById('appointmentsChart').getContext('2d');
var appointmentsChart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['À venir', 'Passés', 'Annulés'],
        datasets: [{
            data: [<?= $upcomingAppointments ?>, <?= $pastAppointments ?>, <?= $canceledAppointments ?>],
            backgroundColor: [
                '#4a8cca',
                '#28a745',
                '#ffc107'
            ],
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