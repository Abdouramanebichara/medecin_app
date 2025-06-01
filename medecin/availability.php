<?php
$pageTitle = "Gérer mes disponibilités";
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

// Traitement du formulaire d'ajout de disponibilité
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_availability'])) {
        $jour = $_POST['jour'];
        $heure_debut = $_POST['heure_debut'];
        $heure_fin = $_POST['heure_fin'];
        
        // Validation des données
        if (empty($jour) || empty($heure_debut) || empty($heure_fin)) {
            $error_message = "Tous les champs sont obligatoires.";
        } elseif ($heure_debut >= $heure_fin) {
            $error_message = "L'heure de début doit être antérieure à l'heure de fin.";
        } else {
            // Vérifier si une disponibilité existe déjà pour ce jour
            $stmt = $conn->prepare("SELECT id FROM disponibilites WHERE medecin_id = ? AND jour = ?");
            $stmt->execute([$medecin_id, $jour]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Mettre à jour la disponibilité existante
                $stmt = $conn->prepare("UPDATE disponibilites SET heure_debut = ?, heure_fin = ? WHERE id = ?");
                $stmt->execute([$heure_debut, $heure_fin, $existing['id']]);
                $success_message = "Disponibilité mise à jour avec succès.";
            } else {
                // Ajouter une nouvelle disponibilité
                $stmt = $conn->prepare("INSERT INTO disponibilites (medecin_id, jour, heure_debut, heure_fin) VALUES (?, ?, ?, ?)");
                $stmt->execute([$medecin_id, $jour, $heure_debut, $heure_fin]);
                $success_message = "Disponibilité ajoutée avec succès.";
            }
        }
    } elseif (isset($_POST['delete_availability']) && isset($_POST['availability_id'])) {
        $availability_id = intval($_POST['availability_id']);
        
        // Vérifier que la disponibilité appartient bien au médecin
        $stmt = $conn->prepare("SELECT id FROM disponibilites WHERE id = ? AND medecin_id = ?");
        $stmt->execute([$availability_id, $medecin_id]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            $stmt = $conn->prepare("DELETE FROM disponibilites WHERE id = ?");
            $stmt->execute([$availability_id]);
            $success_message = "Disponibilité supprimée avec succès.";
        } else {
            $error_message = "Action non autorisée.";
        }
    }
}

// Récupérer les disponibilités actuelles
$stmt = $conn->prepare("
    SELECT * FROM disponibilites 
    WHERE medecin_id = ? 
    ORDER BY 
        CASE jour
            WHEN 'lundi' THEN 1
            WHEN 'mardi' THEN 2
            WHEN 'mercredi' THEN 3
            WHEN 'jeudi' THEN 4
            WHEN 'vendredi' THEN 5
            WHEN 'samedi' THEN 6
            WHEN 'dimanche' THEN 7
        END
");
$stmt->execute([$medecin_id]);
$disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tableau de traduction des jours en français
$joursFr = [
    'lundi' => 'Lundi',
    'mardi' => 'Mardi',
    'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi',
    'vendredi' => 'Vendredi',
    'samedi' => 'Samedi',
    'dimanche' => 'Dimanche'
];
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Gérer mes disponibilités</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Disponibilités</li>
                </ol>
            </nav>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Mes disponibilités</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if (count($disponibilites) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Jour</th>
                                            <th>Heure de début</th>
                                            <th>Heure de fin</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($disponibilites as $dispo): ?>
                                        <tr>
                                            <td><?= $joursFr[$dispo['jour']] ?></td>
                                            <td><?= substr($dispo['heure_debut'], 0, 5) ?></td>
                                            <td><?= substr($dispo['heure_fin'], 0, 5) ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                            data-id="<?= $dispo['id'] ?>" 
                                                            data-jour="<?= $dispo['jour'] ?>" 
                                                            data-debut="<?= substr($dispo['heure_debut'], 0, 5) ?>" 
                                                            data-fin="<?= substr($dispo['heure_fin'], 0, 5) ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette disponibilité ?');">
                                                        <input type="hidden" name="availability_id" value="<?= $dispo['id'] ?>">
                                                        <button type="submit" name="delete_availability" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <img src="../assets/img/no-availability.svg" alt="Aucune disponibilité" style="max-width: 200px; opacity: 0.6;">
                                <h3 class="mt-4 text-muted">Aucune disponibilité définie</h3>
                                <p class="text-muted mb-4">Utilisez le formulaire ci-contre pour ajouter vos disponibilités.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="dashboard-card sticky-top" style="top: 20px; z-index: 100;">
                    <div class="dashboard-card-header">
                        <h2>Ajouter une disponibilité</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="jour" class="form-label">Jour de la semaine</label>
                                <select class="form-select" id="jour" name="jour" required>
                                    <option value="">Sélectionnez un jour</option>
                                    <option value="lundi">Lundi</option>
                                    <option value="mardi">Mardi</option>
                                    <option value="mercredi">Mercredi</option>
                                    <option value="jeudi">Jeudi</option>
                                    <option value="vendredi">Vendredi</option>
                                    <option value="samedi">Samedi</option>
                                    <option value="dimanche">Dimanche</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="heure_debut" class="form-label">Heure de début</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="heure_fin" class="form-label">Heure de fin</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="add_availability" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Ajouter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="dashboard-card mt-4">
                    <div class="dashboard-card-header">
                        <h2>Rappel</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <p>
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Vos disponibilités permettent aux patients de prendre rendez-vous en ligne aux créneaux horaires que vous avez définis.
                        </p>
                        <p>
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Si vous ajoutez une disponibilité pour un jour où vous en avez déjà une, l'ancienne sera remplacée par la nouvelle.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'édition -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Modifier la disponibilité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="availability_id">
                    
                    <div class="mb-3">
                        <label for="edit_jour" class="form-label">Jour de la semaine</label>
                        <select class="form-select" id="edit_jour" name="jour" required>
                            <option value="lundi">Lundi</option>
                            <option value="mardi">Mardi</option>
                            <option value="mercredi">Mercredi</option>
                            <option value="jeudi">Jeudi</option>
                            <option value="vendredi">Vendredi</option>
                            <option value="samedi">Samedi</option>
                            <option value="dimanche">Dimanche</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_heure_debut" class="form-label">Heure de début</label>
                        <input type="time" class="form-control" id="edit_heure_debut" name="heure_debut" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_heure_fin" class="form-label">Heure de fin</label>
                        <input type="time" class="form-control" id="edit_heure_fin" name="heure_fin" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_availability" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Remplir le modal avec les données de la disponibilité à éditer
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const jour = button.getAttribute('data-jour');
            const debut = button.getAttribute('data-debut');
            const fin = button.getAttribute('data-fin');
            
            editModal.querySelector('#edit_id').value = id;
            editModal.querySelector('#edit_jour').value = jour;
            editModal.querySelector('#edit_heure_debut').value = debut;
            editModal.querySelector('#edit_heure_fin').value = fin;
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>