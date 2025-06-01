<?php
$pageTitle = "Tableau de bord - Médecin";
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

// Récupérer les statistiques
// 1. Nombre total de rendez-vous
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM rendez_vous WHERE medecin_id = ?");
$stmt->execute([$medecin_id]);
$totalAppointments = $stmt->fetchColumn();

// 2. Rendez-vous à venir
$stmt = $conn->prepare("SELECT COUNT(*) AS upcoming FROM rendez_vous WHERE medecin_id = ? AND date_rdv >= CURDATE() AND statut IN ('en_attente', 'confirme')");
$stmt->execute([$medecin_id]);
$upcomingAppointments = $stmt->fetchColumn();

// 3. Rendez-vous du jour
$stmt = $conn->prepare("SELECT COUNT(*) AS today FROM rendez_vous WHERE medecin_id = ? AND date_rdv = CURDATE() AND statut IN ('en_attente', 'confirme')");
$stmt->execute([$medecin_id]);
$todayAppointments = $stmt->fetchColumn();

// 4. Consultations terminées
$stmt = $conn->prepare("SELECT COUNT(*) AS completed FROM rendez_vous WHERE medecin_id = ? AND statut = 'complete'");
$stmt->execute([$medecin_id]);
$completedAppointments = $stmt->fetchColumn();

// Récupérer les rendez-vous du jour
$stmt = $conn->prepare("
    SELECT r.*, u.prenom, u.nom 
    FROM rendez_vous r 
    INNER JOIN patients p ON r.patient_id = p.id 
    INNER JOIN utilisateurs u ON p.user_id = u.id 
    WHERE r.medecin_id = ? AND r.date_rdv = CURDATE() AND r.statut IN ('en_attente', 'confirme')
    ORDER BY r.heure_rdv ASC
");
$stmt->execute([$medecin_id]);
$todayAppointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les prochains rendez-vous (à venir, hors aujourd'hui)
$stmt = $conn->prepare("
    SELECT r.*, u.prenom, u.nom 
    FROM rendez_vous r 
    INNER JOIN patients p ON r.patient_id = p.id 
    INNER JOIN utilisateurs u ON p.user_id = u.id 
    WHERE r.medecin_id = ? AND r.date_rdv > CURDATE() AND r.statut IN ('en_attente', 'confirme')
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    LIMIT 5
");
$stmt->execute([$medecin_id]);
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

// Récupérer les données pour le graphique des rendez-vous par jour de la semaine
$stmt = $conn->prepare("
    SELECT 
        DAYOFWEEK(date_rdv) AS day_of_week, 
        COUNT(*) as count
    FROM rendez_vous
    WHERE medecin_id = ?
    GROUP BY DAYOFWEEK(date_rdv)
    ORDER BY DAYOFWEEK(date_rdv)
");
$stmt->execute([$medecin_id]);
$appointmentsByDayOfWeek = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conversion des nombres de jour en noms de jour en français
$dayMapping = [
    1 => 'Dimanche',
    2 => 'Lundi',
    3 => 'Mardi',
    4 => 'Mercredi',
    5 => 'Jeudi',
    6 => 'Vendredi',
    7 => 'Samedi'
];

$dayLabels = [];
$dayData = [];

// Initialiser tous les jours à 0
foreach ($dayMapping as $dayNum => $dayName) {
    $dayLabels[] = $dayName;
    $dayData[$dayNum] = 0;
}

// Remplir avec les données réelles
foreach ($appointmentsByDayOfWeek as $day) {
    $dayNum = $day['day_of_week'];
    // Vérifier si $dayNum est une clé valide avant d'affecter la valeur
    if (isset($dayData[$dayNum])) {
        $dayData[$dayNum] = $day['count'];
    }
}

// Réordonner pour commencer par lundi (optionnel)
$dayLabels = array_slice($dayLabels, 1); // Dimanche en dernier
$dayLabels[] = 'Dimanche';

// Réorganiser les données pour correspondre à l'ordre des labels
$dayDataOrdered = [];
// D'abord les jours 2 à 7 (lundi à samedi)
for ($i = 2; $i <= 7; $i++) {
    $dayDataOrdered[] = $dayData[$i] ?? 0;
}
// Ensuite le jour 1 (dimanche)
$dayDataOrdered[] = $dayData[1] ?? 0;
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

        <div class="alert alert-info" role="alert">
            <i class="fas fa-user-md me-2"></i> Vous êtes connecté en tant que médecin spécialiste en <?= ($specialite) ?>.
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
                <div class="stat-card accent h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value"><?= $todayAppointments ?></div>
                    <div class="stat-label">Rendez-vous aujourd'hui</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card secondary h-100">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-value"><?= $upcomingAppointments ?></div>
                    <div class="stat-label">Rendez-vous à venir</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card success h-100">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-value"><?= $completedAppointments ?></div>
                    <div class="stat-label">Consultations terminées</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <?php if (count($todayAppointmentsList) > 0): ?>
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Rendez-vous d'aujourd'hui</h2>
                    </div>
                    <div class="dashboard-card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Heure</th>
                                        <th>Patient</th>
                                        <th>Motif</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayAppointmentsList as $appointment): 
                                        $statusBadge = '';
                                        switch ($appointment['statut']) {
                                            case 'en_attente':
                                                $statusBadge = '<span class="badge bg-warning text-dark">En attente</span>';
                                                break;
                                            case 'confirme':
                                                $statusBadge = '<span class="badge bg-success">Confirmé</span>';
                                                break;
                                        }
                                    ?>
                                    <tr>
                                        <td><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></td>
                                        <td><?= htmlspecialchars($appointment['prenom'] . ' ' . $appointment['nom']) ?></td>
                                        <td><?= htmlspecialchars(substr($appointment['motif'], 0, 30) . (strlen($appointment['motif']) > 30 ? '...' : '')) ?></td>
                                        <td><?= $statusBadge ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-success" title="Confirmer">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary" title="Consultation">
                                                    <i class="fas fa-stethoscope"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" title="Annuler">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="dashboard-card">
                    <div class="dashboard-card-body text-center py-5">
                        <i class="fas fa-calendar-check fa-4x text-muted mb-3"></i>
                        <h3 class="mb-3">Aucun rendez-vous aujourd'hui</h3>
                        <p class="text-muted mb-0">Vous n'avez pas de rendez-vous prévu pour aujourd'hui.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Répartition des rendez-vous</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="chart-container">
                            <canvas id="weeklyAppointmentsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Prochains rendez-vous</h2>
                        <a href="appointments.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="dashboard-card-body p-0">
                        <?php if (count($upcomingAppointmentsList) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Heure</th>
                                            <th>Patient</th>
                                            <th>Motif</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcomingAppointmentsList as $appointment): 
                                            $statusBadge = '';
                                            switch ($appointment['statut']) {
                                                case 'en_attente':
                                                    $statusBadge = '<span class="badge bg-warning text-dark">En attente</span>';
                                                    break;
                                                case 'confirme':
                                                    $statusBadge = '<span class="badge bg-success">Confirmé</span>';
                                                    break;
                                            }
                                        ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></td>
                                            <td><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></td>
                                            <td><?= htmlspecialchars($appointment['prenom'] . ' ' . $appointment['nom']) ?></td>
                                            <td><?= htmlspecialchars(substr($appointment['motif'], 0, 30) . (strlen($appointment['motif']) > 30 ? '...' : '')) ?></td>
                                            <td><?= $statusBadge ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                <p>Vous n'avez pas de rendez-vous à venir.</p>
                            </div>
                        <?php endif; ?>
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
                            <a href="availability.php" class="btn btn-primary">
                                <i class="fas fa-calendar-week me-2"></i> Gérer mes disponibilités
                            </a>
                            <a href="appointments.php" class="btn btn-outline-primary">
                                <i class="fas fa-calendar-alt me-2"></i> Tous les rendez-vous
                            </a>
                            <a href="consultations.php" class="btn btn-outline-primary">
                                <i class="fas fa-clipboard-list me-2"></i> Consultations
                            </a>
                            <a href="patients.php" class="btn btn-outline-primary">
                                <i class="fas fa-procedures me-2"></i> Mes patients
                            </a>
                            <a href="statistics.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-line me-2"></i> Mes statistiques
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration du graphique de rendez-vous par jour de semaine
var ctx = document.getElementById('weeklyAppointmentsChart').getContext('2d');
var weeklyChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_values($dayLabels)) ?>,
        datasets: [{
            label: 'Nombre de rendez-vous',
            data: <?= json_encode(array_values($dayDataOrdered)) ?>,
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
</script>

<?php require_once '../includes/footer.php'; ?>