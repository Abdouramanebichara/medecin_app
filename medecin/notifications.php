<?php
$pageTitle = "Notifications";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle médecin
requireRole('medecin');

$user_id = $_SESSION['user_id'];

// Marquer les notifications comme lues
if (isset($_GET['mark_read']) && $_GET['mark_read'] == 'all') {
    $stmt = $conn->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Rediriger pour éviter la soumission multiple
    header("Location: notifications.php?marked=1");
    exit;
}

// Récupérer toutes les notifications de l'utilisateur
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY date_envoi DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer les notifications comme lues lors de la visite de la page
$stmt = $conn->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ? AND lu = 0");
$stmt->execute([$user_id]);
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

        <?php if (isset($_GET['marked'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Toutes les notifications ont été marquées comme lues.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                <h2>Vos notifications</h2>
                <?php if (count($notifications) > 0): ?>
                <a href="?mark_read=all" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-check-double me-2"></i>Marquer tout comme lu
                </a>
                <?php endif; ?>
            </div>
            <div class="dashboard-card-body p-0">
                <?php if (count($notifications) > 0): ?>
                    <div class="notifications-list">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item d-flex">
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
</div>

<?php require_once '../includes/footer.php'; ?>