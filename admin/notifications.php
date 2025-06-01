<?php
$pageTitle = "Notifications";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

$user_id = $_SESSION['user_id'];

// Traitement des actions sur les notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        // Marquer une notification spécifique comme lue
        $notification_id = intval($_POST['notification_id']);
        $stmt = $conn->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
    } 
    elseif (isset($_POST['mark_all_read'])) {
        // Marquer toutes les notifications comme lues
        $stmt = $conn->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    elseif (isset($_POST['delete_notification']) && isset($_POST['notification_id'])) {
        // Supprimer une notification spécifique
        $notification_id = intval($_POST['notification_id']);
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
    }
    elseif (isset($_POST['delete_all'])) {
        // Supprimer toutes les notifications
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    // Redirection pour éviter la soumission multiple
    header("Location: notifications.php");
    exit;
}

// Récupérer les notifications de l'utilisateur
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY date_envoi DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les notifications non lues
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetchColumn();

// Traitement du formulaire d'envoi de notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $recipient_role = $_POST['recipient_role'];
    $message = trim($_POST['message']);
    
    if (empty($message)) {
        $_SESSION['error'] = "Le message de la notification est obligatoire.";
    } else {
        try {
            // Récupérer tous les utilisateurs du rôle spécifié ou tous les utilisateurs
            if ($recipient_role === 'all') {
                $stmt = $conn->prepare("SELECT id FROM utilisateurs");
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("SELECT id FROM utilisateurs WHERE role = ?");
                $stmt->execute([$recipient_role]);
            }
            
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $message_with_sender = "Message de l'administrateur: " . $message;
            
            // Insérer une notification pour chaque destinataire
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, lu, date_envoi) VALUES (?, ?, 0, NOW())");
            
            foreach ($recipients as $recipient_id) {
                $stmt->execute([$recipient_id, $message_with_sender]);
            }
            
            $_SESSION['success'] = count($recipients) . " notification(s) envoyée(s) avec succès.";
            header("Location: notifications.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'envoi de la notification : " . $e->getMessage();
        }
    }
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Notifications</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Notifications</li>
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

        <div class="row">
            <div class="col-md-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h2>Mes notifications</h2>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger"><?= $unread_count ?> non lue(s)</span>
                            <?php endif; ?>
                        </div>
                        <?php if (count($notifications) > 0): ?>
                            <div class="btn-group">
                                <form method="post" class="d-inline">
                                    <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-check-double me-2"></i>Tout marquer comme lu
                                    </button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer toutes les notifications ?');">
                                    <button type="submit" name="delete_all" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash me-2"></i>Tout supprimer
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="dashboard-card-body p-0">
                        <?php if (count($notifications) > 0): ?>
                            <div class="notifications-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item d-flex align-items-center <?= $notification['lu'] ? '' : 'unread' ?>">
                                        <div class="notification-icon">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                <?= htmlspecialchars($notification['message']) ?>
                                                <?php if (!$notification['lu']): ?>
                                                    <span class="badge bg-danger ms-2">Nouveau</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="notification-time">
                                                <i class="far fa-clock me-1"></i> <?= date('d/m/Y H:i', strtotime($notification['date_envoi'])) ?>
                                            </div>
                                        </div>
                                        <div class="notification-actions ms-auto">
                                            <?php if (!$notification['lu']): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-primary" title="Marquer comme lu">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <img src="../assets/img/no-notifications.svg" alt="Aucune notification" style="max-width: 200px; opacity: 0.6;">
                                <h3 class="mt-4 text-muted">Aucune notification</h3>
                                <p class="text-muted">Vous n'avez pas de notifications pour le moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Envoyer une notification</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="recipient_role" class="form-label">Destinataires</label>
                                <select class="form-select" id="recipient_role" name="recipient_role" required>
                                    <option value="all">Tous les utilisateurs</option>
                                    <option value="patient">Tous les patients</option>
                                    <option value="medecin">Tous les médecins</option>
                                    <option value="administrateur">Tous les administrateurs</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="send_notification" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer la notification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="dashboard-card mt-4">
                    <div class="dashboard-card-header">
                        <h2>Aide</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <p>
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Les notifications sont un moyen efficace de communiquer avec les utilisateurs de la plateforme.
                        </p>
                        <p>
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            <strong>Conseils :</strong>
                        </p>
                        <ul>
                            <li>Soyez clair et concis dans vos messages.</li>
                            <li>Évitez d'envoyer trop de notifications à la fois.</li>
                            <li>Privilégiez les notifications ciblées plutôt que générales.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>