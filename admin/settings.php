<?php
$pageTitle = "Paramètres du système";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Traitement du formulaire de paramètres généraux
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Ici, vous pourriez mettre à jour les paramètres de l'application
    // Par exemple, stockés dans une table de configuration en base de données
    $_SESSION['success'] = "Les paramètres ont été mis à jour avec succès.";
    header("Location: settings.php");
    exit;
}

// Traitement du formulaire de sauvegarde de la base de données
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_database'])) {
    try {
        // Nom du fichier de sauvegarde
        $backup_file = 'medical_app_backup_' . date("Y-m-d_H-i-s") . '.sql';
        
        // Chemin du fichier de sauvegarde
        $backup_path = '../backups/';
        
        // Créer le répertoire de sauvegarde s'il n'existe pas
        if (!file_exists($backup_path)) {
            mkdir($backup_path, 0777, true);
        }
        
        // Commande pour sauvegarder la base de données
        // Note: Cela nécessite que mysqldump soit accessible et que l'utilisateur PHP ait les permissions nécessaires
        $command = "mysqldump --host={$host} --user={$username} --password={$password} {$dbname} > {$backup_path}{$backup_file}";
        
        // Exécution de la commande
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $_SESSION['success'] = "La base de données a été sauvegardée avec succès dans le fichier {$backup_file}.";
        } else {
            $_SESSION['error'] = "Erreur lors de la sauvegarde de la base de données.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header("Location: settings.php");
    exit;
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Paramètres du système</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Paramètres</li>
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
            <div class="col-md-6">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Paramètres généraux</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="app_name" class="form-label">Nom de l'application</label>
                                <input type="text" class="form-control" id="app_name" name="app_name" value="MedBook">
                            </div>
                            
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Email de l'administrateur</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" value="admin@MedBook.com">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="maintenance_mode" name="maintenance_mode">
                                <label class="form-check-label" for="maintenance_mode">Mode maintenance</label>
                            </div>
                            
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer les paramètres
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Sauvegarde de la base de données</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <p>Vous pouvez créer une sauvegarde de la base de données à tout moment. Cette fonctionnalité est utile avant d'apporter des modifications importantes au système.</p>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="backup_comment" class="form-label">Commentaire (optionnel)</label>
                                <textarea class="form-control" id="backup_comment" name="backup_comment" rows="3" placeholder="Ajoutez un commentaire pour cette sauvegarde..."></textarea>
                            </div>
                            
                            <button type="submit" name="backup_database" class="btn btn-primary">
                                <i class="fas fa-database me-2"></i>Créer une sauvegarde
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="dashboard-card mt-4">
                    <div class="dashboard-card-header">
                        <h2>Informations système</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="mb-3">
                            <span class="fw-bold">Version PHP:</span> <?= phpversion() ?>
                        </div>
                        <div class="mb-3">
                            <span class="fw-bold">Version MySQL:</span> <?= $conn->query('select version()')->fetchColumn() ?>
                        </div>
                        <div class="mb-3">
                            <span class="fw-bold">Serveur:</span> <?= $_SERVER['SERVER_SOFTWARE'] ?>
                        </div>
                        <div class="mb-3">
                            <span class="fw-bold">Date et heure du serveur:</span> <?= date('d/m/Y H:i:s') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Options avancées</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetModal">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Réinitialiser l'application
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-primary">
                                        <i class="fas fa-cogs me-2"></i>Vérifier les mises à jour
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-secondary">
                                        <i class="fas fa-file-alt me-2"></i>Générer un rapport système
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation de réinitialisation -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetModalLabel">Confirmer la réinitialisation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> <strong>Attention !</strong> Cette action est irréversible.
                </div>
                <p>Êtes-vous sûr de vouloir réinitialiser l'application ? Toutes les données seront supprimées.</p>
                <p>Pour confirmer, veuillez écrire <strong>"RÉINITIALISER"</strong> dans le champ ci-dessous :</p>
                <input type="text" class="form-control" id="confirmReset" placeholder="Écrivez RÉINITIALISER">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="resetBtn" disabled>
                    <i class="fas fa-trash me-2"></i>Réinitialiser l'application
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('confirmReset').addEventListener('input', function() {
    const resetBtn = document.getElementById('resetBtn');
    if (this.value === 'RÉINITIALISER') {
        resetBtn.disabled = false;
    } else {
        resetBtn.disabled = true;
    }
});

document.getElementById('resetBtn').addEventListener('click', function() {
    // Ici, vous pourriez ajouter le code pour réinitialiser l'application
    alert('Fonctionnalité de réinitialisation non implémentée dans cette version démo.');
    document.getElementById('resetModal').querySelector('.btn-close').click();
});
</script>

<?php require_once '../includes/footer.php'; ?>