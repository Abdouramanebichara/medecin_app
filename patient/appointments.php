<?php
$pageTitle = "Mes rendez-vous";
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

// Filtrer les rendez-vous
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "WHERE r.patient_id = ?";

switch ($filter) {
    case 'upcoming':
        $where_clause .= " AND r.date_rdv >= CURDATE() AND r.statut IN ('en_attente', 'confirme')";
        break;
    case 'past':
        $where_clause .= " AND (r.date_rdv < CURDATE() OR r.statut = 'complete')";
        break;
    case 'canceled':
        $where_clause .= " AND r.statut = 'annule'";
        break;
}

// Récupérer les rendez-vous
$stmt = $conn->prepare("
    SELECT r.*, 
           u.prenom as medecin_prenom, u.nom as medecin_nom,
           s.nom as specialite 
    FROM rendez_vous r
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN utilisateurs u ON m.user_id = u.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    $where_clause
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Mes Rendez-vous</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mes Rendez-vous</li>
                </ol>
            </nav>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="btn-group" role="group">
                    <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-calendar-alt me-2"></i>Tous
                    </a>
                    <a href="?filter=upcoming" class="btn <?= $filter === 'upcoming' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-calendar-day me-2"></i>À venir
                    </a>
                    <a href="?filter=past" class="btn <?= $filter === 'past' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-history me-2"></i>Passés
                    </a>
                    <a href="?filter=canceled" class="btn <?= $filter === 'canceled' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-calendar-times me-2"></i>Annulés
                    </a>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <a href="take-appointment.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Nouveau rendez-vous
                </a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h2><?= count($appointments) ?> Rendez-vous</h2>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($appointments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Médecin</th>
                                    <th>Spécialité</th>
                                    <th>Motif</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): 
                                    // Déterminer la classe et le texte du badge de statut
                                    switch ($appointment['statut']) {
                                        case 'en_attente':
                                            $statusClass = 'bg-warning text-dark';
                                            $statusText = 'En attente';
                                            break;
                                        case 'confirme':
                                            $statusClass = 'bg-success';
                                            $statusText = 'Confirmé';
                                            break;
                                        case 'annule':
                                            $statusClass = 'bg-danger';
                                            $statusText = 'Annulé';
                                            break;
                                        case 'complete':
                                            $statusClass = 'bg-info';
                                            $statusText = 'Terminé';
                                            break;
                                        default:
                                            $statusClass = 'bg-secondary';
                                            $statusText = 'Inconnu';
                                    }
                                    
                                    // Vérifier si le rendez-vous peut être annulé (48h avant)
                                    $canCancel = false;
                                    if (($appointment['statut'] == 'en_attente' || $appointment['statut'] == 'confirme')) {
                                        $rdvDateTime = strtotime($appointment['date_rdv'] . ' ' . $appointment['heure_rdv']);
                                        $now = time();
                                        $diff = $rdvDateTime - $now;
                                        $canCancel = ($diff > 48 * 3600); // 48 heures en secondes
                                    }
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></td>
                                    <td><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></td>
                                    <td>Dr. <?= htmlspecialchars($appointment['medecin_prenom'] . ' ' . $appointment['medecin_nom']) ?></td>
                                    <td><?= htmlspecialchars($appointment['specialite']) ?></td>
                                    <td><?= htmlspecialchars(substr($appointment['motif'], 0, 30) . (strlen($appointment['motif']) > 30 ? '...' : '')) ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                    <td>
                                        <?php if ($appointment['statut'] == 'complete'): ?>
                                            <a href="history.php?rdv_id=<?= $appointment['id'] ?>" class="btn btn-sm btn-info" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <div class="btn-group">
                                                <?php if ($canCancel): ?>
                                                <button type="button" class="btn btn-sm btn-danger" title="Annuler" onclick="confirmCancel(<?= $appointment['id'] ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-primary" title="Détails" data-bs-toggle="modal" data-bs-target="#appointmentModal<?= $appointment['id'] ?>">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            </div>
                                            
                                            
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="../assets/img/no-appointments.svg" alt="Aucun rendez-vous" style="max-width: 200px; opacity: 0.6;">
                        <h3 class="mt-4 text-muted">Aucun rendez-vous trouvé</h3>
                        <p class="text-muted mb-4">Vous n'avez pas de rendez-vous correspondant à votre sélection.</p>
                        <a href="take-appointment.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Prendre rendez-vous
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les détails du RDV -->
                                            <div class="modal fade" id="appointmentModal<?= $appointment['id'] ?>" tabindex="-1" aria-labelledby="appointmentModalLabel<?= $appointment['id'] ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="appointmentModalLabel<?= $appointment['id'] ?>">Détails du rendez-vous</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>Date:</strong> <?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></p>
                                                            <p><strong>Heure:</strong> <?= date('H:i', strtotime($appointment['heure_rdv'])) ?></p>
                                                            <p><strong>Médecin:</strong> Dr. <?= htmlspecialchars($appointment['medecin_prenom'] . ' ' . $appointment['medecin_nom']) ?></p>
                                                            <p><strong>Spécialité:</strong> <?= htmlspecialchars($appointment['specialite']) ?></p>
                                                            <p><strong>Motif:</strong> <?= htmlspecialchars($appointment['motif']) ?></p>
                                                            <p><strong>Statut:</strong> <span class="badge <?= $statusClass ?>"><?= $statusText ?></span></p>
                                                            <p><strong>Créé le:</strong> <?= date('d/m/Y H:i', strtotime($appointment['date_creation'])) ?></p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                            <?php if ($canCancel): ?>
                                                            <button type="button" class="btn btn-danger" onclick="confirmCancel(<?= $appointment['id'] ?>)">Annuler le rendez-vous</button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
<script>
function confirmCancel(appointmentId) {
    if (confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')) {
        window.location.href = `cancel-appointment.php?id=${appointmentId}`;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>