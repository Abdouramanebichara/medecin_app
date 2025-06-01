<?php
$pageTitle = "Gestion des rendez-vous";
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

// Filtrer les rendez-vous
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$where_clause = "WHERE r.medecin_id = ?";

switch ($filter) {
    case 'today':
        $where_clause .= " AND r.date_rdv = CURDATE() AND r.statut IN ('en_attente', 'confirme')";
        break;
    case 'upcoming':
        $where_clause .= " AND r.date_rdv > CURDATE() AND r.statut IN ('en_attente', 'confirme')";
        break;
    case 'pending':
        $where_clause .= " AND r.statut = 'en_attente'";
        break;
    case 'confirmed':
        $where_clause .= " AND r.statut = 'confirme'";
        break;
    case 'completed':
        $where_clause .= " AND r.statut = 'complete'";
        break;
    case 'canceled':
        $where_clause .= " AND r.statut = 'annule'";
        break;
    case 'all':
        // Pas de conditions supplémentaires
        break;
}

// Gestion des actions sur les rendez-vous
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
        $action = $_POST['action'];
        $appointment_id = intval($_POST['appointment_id']);
        
        // Vérifier que le RDV appartient bien au médecin
        $stmt = $conn->prepare("SELECT id FROM rendez_vous WHERE id = ? AND medecin_id = ?");
        $stmt->execute([$appointment_id, $medecin_id]);
        $rdv_exists = $stmt->fetchColumn();
        
        if ($rdv_exists) {
            switch ($action) {
                case 'confirm':
                    // Confirmer le RDV
                    $stmt = $conn->prepare("UPDATE rendez_vous SET statut = 'confirme' WHERE id = ?");
                    $stmt->execute([$appointment_id]);
                    
                    // Envoyer une notification au patient
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, lu, date_envoi)
                        SELECT p.user_id, 'Votre rendez-vous du ' || DATE_FORMAT(r.date_rdv, '%d/%m/%Y') || ' à ' || DATE_FORMAT(r.heure_rdv, '%H:%i') || ' a été confirmé par le médecin.', 0, NOW()
                        FROM rendez_vous r
                        INNER JOIN patients p ON r.patient_id = p.id
                        WHERE r.id = ?
                    ");
                    $stmt->execute([$appointment_id]);
                    
                    $_SESSION['success'] = "Le rendez-vous a été confirmé avec succès.";
                    break;
                    
                case 'cancel':
                    // Annuler le RDV
                    $stmt = $conn->prepare("UPDATE rendez_vous SET statut = 'annule' WHERE id = ?");
                    $stmt->execute([$appointment_id]);
                    
                    // Envoyer une notification au patient
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, lu, date_envoi)
                        SELECT p.user_id, 'Votre rendez-vous du ' || DATE_FORMAT(r.date_rdv, '%d/%m/%Y') || ' à ' || DATE_FORMAT(r.heure_rdv, '%H:%i') || ' a été annulé par le médecin.', 0, NOW()
                        FROM rendez_vous r
                        INNER JOIN patients p ON r.patient_id = p.id
                        WHERE r.id = ?
                    ");
                    $stmt->execute([$appointment_id]);
                    
                    $_SESSION['success'] = "Le rendez-vous a été annulé avec succès.";
                    break;
                    
                case 'complete':
                    // Marquer le RDV comme terminé
                    $stmt = $conn->prepare("UPDATE rendez_vous SET statut = 'complete' WHERE id = ?");
                    $stmt->execute([$appointment_id]);
                    
                    // Créer une consultation associée
                    $stmt = $conn->prepare("INSERT INTO consultations (rdv_id, date) VALUES (?, NOW())");
                    $stmt->execute([$appointment_id]);
                    
                    $_SESSION['success'] = "Le rendez-vous a été marqué comme terminé. Vous pouvez maintenant ajouter une consultation.";
                    header("Location: consultations.php?rdv_id=" . $appointment_id);
                    exit;
                    break;
            }
            
            // Rediriger pour éviter la soumission multiple
            header("Location: appointments.php?filter=" . $filter);
            exit;
        } else {
            $_SESSION['error'] = "Action non autorisée.";
        }
    }
}

// Récupérer les rendez-vous
$stmt = $conn->prepare("
    SELECT r.*, 
           u.prenom as patient_prenom, u.nom as patient_nom,
           p.telephone as patient_telephone
    FROM rendez_vous r
    INNER JOIN patients p ON r.patient_id = p.id
    INNER JOIN utilisateurs u ON p.user_id = u.id
    $where_clause
    ORDER BY r.date_rdv, r.heure_rdv
");
$stmt->execute([$medecin_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Gestion des rendez-vous</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rendez-vous</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="mb-4 d-flex flex-wrap gap-2">
            <a href="?filter=today" class="btn <?= $filter === 'today' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fas fa-calendar-day me-2"></i>Aujourd'hui
            </a>
            <a href="?filter=upcoming" class="btn <?= $filter === 'upcoming' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fas fa-calendar-week me-2"></i>À venir
            </a>
            <a href="?filter=pending" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fas fa-clock me-2"></i>En attente
            </a>
            <a href="?filter=confirmed" class="btn <?= $filter === 'confirmed' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fas fa-check me-2"></i>Confirmés
            </a>
            <a href="?filter=completed" class="btn <?= $filter === 'completed' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fas fa-check-double me-2"></i>Terminés
            </a>
            <a href="?filter=canceled" class="btn <?= $filter === 'canceled' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fas fa-ban me-2"></i>Annulés
            </a>
            <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fas fa-list me-2"></i>Tous
            </a>
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
                                    <th>Patient</th>
                                    <th>Contact</th>
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
                                    
                                    // Vérifier si le rendez-vous est aujourd'hui
                                    $isToday = date('Y-m-d') === $appointment['date_rdv'];
                                ?>
                                <tr class="<?= $isToday ? 'table-primary' : '' ?>">
                                    <td><?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></td>
                                    <td><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></td>
                                    <td><?= htmlspecialchars($appointment['patient_prenom'] . ' ' . $appointment['patient_nom']) ?></td>
                                    <td>
                                        <?php if (!empty($appointment['patient_telephone'])): ?>
                                            <a href="tel:<?= $appointment['patient_telephone'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($appointment['patient_telephone']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-link p-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($appointment['motif']) ?>">
                                            <?= htmlspecialchars(substr($appointment['motif'], 0, 30) . (strlen($appointment['motif']) > 30 ? '...' : '')) ?>
                                        </button>
                                    </td>
                                    <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($appointment['statut'] == 'en_attente'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Confirmer">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($appointment['statut'] == 'confirme'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                    <input type="hidden" name="action" value="complete">
                                                    <button type="submit" class="btn btn-sm btn-primary" title="Marquer comme terminé">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($appointment['statut'] == 'complete'): ?>
                                                <a href="consultations.php?rdv_id=<?= $appointment['id'] ?>" class="btn btn-sm btn-info" title="Voir consultation">
                                                    <i class="fas fa-clipboard-list"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (in_array($appointment['statut'], ['en_attente', 'confirme'])): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Annuler">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $appointment['id'] ?>" title="Détails">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </div>
                                        
                                        
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
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de détails -->
                                        <div class="modal fade" id="detailsModal<?= $appointment['id'] ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?= $appointment['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="detailsModalLabel<?= $appointment['id'] ?>">Détails du rendez-vous</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <h6>Informations du patient</h6>
                                                            <p><strong>Nom :</strong> <?= htmlspecialchars($appointment['patient_prenom'] . ' ' . $appointment['patient_nom']) ?></p>
                                                            <p><strong>Téléphone :</strong> <?= !empty($appointment['patient_telephone']) ? htmlspecialchars($appointment['patient_telephone']) : 'Non renseigné' ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <h6>Informations du rendez-vous</h6>
                                                            <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></p>
                                                            <p><strong>Heure :</strong> <?= date('H:i', strtotime($appointment['heure_rdv'])) ?></p>
                                                            <p><strong>Statut :</strong> <span class="badge <?= $statusClass ?>"><?= $statusText ?></span></p>
                                                            <p><strong>Créé le :</strong> <?= date('d/m/Y H:i', strtotime($appointment['date_creation'])) ?></p>
                                                        </div>
                                                        <div>
                                                            <h6>Motif de la consultation</h6>
                                                            <p><?= nl2br(htmlspecialchars($appointment['motif'])) ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                        
                                                        <?php if ($appointment['statut'] == 'en_attente'): ?>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                            <input type="hidden" name="action" value="confirm">
                                                            <button type="submit" class="btn btn-success">Confirmer</button>
                                                        </form>
                                                        <?php endif; ?>

                                                        <?php if ($appointment['statut'] == 'confirme'): ?>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" class="btn btn-primary">Marquer comme terminé</button>
                                                        </form>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($appointment['statut'], ['en_attente', 'confirme'])): ?>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">
                                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <button type="submit" class="btn btn-danger">Annuler</button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
<script>
    // Initialiser les tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>

<?php require_once '../includes/footer.php'; ?>