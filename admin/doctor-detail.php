<?php
$pageTitle = "Détails du médecin";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Récupérer l'ID du médecin
$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$doctor_id) {
    $_SESSION['error'] = "ID du médecin non spécifié.";
    header("Location: doctors.php");
    exit;
}

// Récupérer les informations du médecin
$stmt = $conn->prepare("
    SELECT m.*, 
           u.id as user_id, u.nom, u.prenom, u.email, u.date_creation,
           s.nom as specialite_nom,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id = m.id) as total_rdv,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id = m.id AND r.statut = 'complete') as total_consultations,
           (SELECT COUNT(DISTINCT patient_id) FROM rendez_vous r WHERE r.medecin_id = m.id) as total_patients
    FROM medecins m
    INNER JOIN utilisateurs u ON m.user_id = u.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE m.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    $_SESSION['error'] = "Médecin non trouvé.";
    header("Location: doctors.php");
    exit;
}

// Récupérer les disponibilités du médecin
$stmt = $conn->prepare("
    SELECT * FROM disponibilites 
    WHERE medecin_id = ?
    ORDER BY FIELD(jour, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche')
");
$stmt->execute([$doctor_id]);
$disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les rendez-vous récents du médecin
$stmt = $conn->prepare("
    SELECT r.*, 
           CONCAT(u.prenom, ' ', u.nom) as patient_nom
    FROM rendez_vous r
    INNER JOIN patients p ON r.patient_id = p.id
    INNER JOIN utilisateurs u ON p.user_id = u.id
    WHERE r.medecin_id = ?
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 10
");
$stmt->execute([$doctor_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques des rendez-vous
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'confirme' THEN 1 ELSE 0 END) as confirmes,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules,
        SUM(CASE WHEN statut = 'complete' THEN 1 ELSE 0 END) as completes
    FROM rendez_vous
    WHERE medecin_id = ?
");
$stmt->execute([$doctor_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Traduire les jours de la semaine en français
$joursFrancais = [
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
            <h1>Détails du médecin</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="doctors.php">Médecins</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dr. <?= htmlspecialchars($doctor['prenom'] . ' ' . $doctor['nom']) ?></li>
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
            <!-- Informations personnelles -->
            <div class="col-md-4">
                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <h2>Informations personnelles</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="text-center mb-4">
                            <div class="doctor-avatar mx-auto mb-3">
                                <?php 
                                $initials = strtoupper(substr($doctor['prenom'], 0, 1) . substr($doctor['nom'], 0, 1));
                                ?>
                                <span><?= $initials ?></span>
                            </div>
                            <h3>Dr. <?= htmlspecialchars($doctor['prenom'] . ' ' . $doctor['nom']) ?></h3>
                            <p class="text-primary"><?= htmlspecialchars($doctor['specialite_nom']) ?></p>
                        </div>
                        
                        <div class="doctor-info">
                            <p><strong>Email :</strong> <?= htmlspecialchars($doctor['email']) ?></p>
                            <p><strong>Spécialité :</strong> <?= htmlspecialchars($doctor['specialite_nom']) ?></p>
                            <p><strong>Date d'inscription :</strong> <?= date('d/m/Y', strtotime($doctor['date_creation'])) ?></p>
                        </div>
                        
                        <div class="mt-4 d-grid gap-2">
                            <a href="user-edit.php?id=<?= $doctor['user_id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Modifier le profil
                            </a>
                            
                            <a href="#" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disableAccountModal">
                                <i class="fas fa-user-slash me-2"></i>Désactiver le compte
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Statistiques</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="row g-4 text-center">
                            <div class="col-6">
                                <div class="stat-value"><?= $doctor['total_patients'] ?></div>
                                <div class="stat-label">Patients</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-value"><?= $doctor['total_rdv'] ?></div>
                                <div class="stat-label">Rendez-vous</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-value"><?= $doctor['total_consultations'] ?></div>
                                <div class="stat-label">Consultations</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-value"><?= $stats['annules'] ?? 0 ?></div>
                                <div class="stat-label">Annulations</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Disponibilités et Rendez-vous -->
            <div class="col-md-8">
                <div class="row">
                    <!-- Disponibilités -->
                    <div class="col-12 mb-4">
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h2>Disponibilités</h2>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDisponibilitesModal">
                                    <i class="fas fa-edit me-1"></i>Modifier
                                </button>
                            </div>
                            <div class="dashboard-card-body">
                                <?php if (count($disponibilites) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Jour</th>
                                                    <th>Horaires</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $jourDispo = [];
                                                foreach ($disponibilites as $dispo) {
                                                    $jourDispo[$dispo['jour']][] = [
                                                        'debut' => $dispo['heure_debut'],
                                                        'fin' => $dispo['heure_fin']
                                                    ];
                                                }
                                                
                                                foreach ($joursFrancais as $jour => $jourFr):
                                                    $hasDispos = isset($jourDispo[$jour]);
                                                ?>
                                                <tr>
                                                    <td><?= $jourFr ?></td>
                                                    <td>
                                                        <?php if ($hasDispos): ?>
                                                            <?php foreach ($jourDispo[$jour] as $horaire): ?>
                                                                <div>
                                                                    <?= date('H:i', strtotime($horaire['debut'])) ?> - <?= date('H:i', strtotime($horaire['fin'])) ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Indisponible</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted">Aucune disponibilité n'a été définie pour ce médecin.</p>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editDisponibilitesModal">
                                            <i class="fas fa-plus me-2"></i>Ajouter des disponibilités
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques des rendez-vous -->
                    <div class="col-12 mb-4">
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h2>Statistiques des rendez-vous</h2>
                            </div>
                            <div class="dashboard-card-body">
                                <div class="row justify-content-center">
                                    <div class="col-md-8">
                                        <canvas id="appointmentsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Derniers rendez-vous -->
                    <div class="col-12">
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h2>Derniers rendez-vous</h2>
                                <?php if ($doctor['total_rdv'] > 10): ?>
                                <a href="appointments.php?doctor_id=<?= $doctor_id ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
                                <?php endif; ?>
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
                                                    <th>Motif</th>
                                                    <th>Statut</th>
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
                                                ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></td>
                                                    <td><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></td>
                                                    <td><?= htmlspecialchars($appointment['patient_nom']) ?></td>
                                                    <td>
                                                        <?= !empty($appointment['motif']) 
                                                            ? (strlen($appointment['motif']) > 30 
                                                                ? htmlspecialchars(substr($appointment['motif'], 0, 30)) . '...' 
                                                                : htmlspecialchars($appointment['motif']))
                                                            : '<span class="text-muted">Non spécifié</span>' ?>
                                                    </td>
                                                    <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <img src="../assets/img/no-appointments.svg" alt="Aucun rendez-vous" style="max-width: 200px; opacity: 0.6;">
                                        <h3 class="mt-4 text-muted">Aucun rendez-vous</h3>
                                        <p class="text-muted">Ce médecin n'a pas encore de rendez-vous enregistrés.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour désactiver le compte -->
<div class="modal fade" id="disableAccountModal" tabindex="-1" aria-labelledby="disableAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="disableAccountModalLabel">Désactiver le compte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir désactiver le compte du Dr. <?= htmlspecialchars($doctor['prenom'] . ' ' . $doctor['nom']) ?> ?</p>
                <p class="text-danger">Cette action désactivera temporairement l'accès du médecin à la plateforme.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form action="doctor-disable.php" method="post">
                    <input type="hidden" name="user_id" value="<?= $doctor['user_id'] ?>">
                    <button type="submit" class="btn btn-danger">Désactiver</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour éditer les disponibilités -->
<div class="modal fade" id="editDisponibilitesModal" tabindex="-1" aria-labelledby="editDisponibilitesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDisponibilitesModalLabel">Modifier les disponibilités</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <form id="disponibilitesForm" action="doctor-disponibilites.php" method="post">
                    <input type="hidden" name="medecin_id" value="<?= $doctor_id ?>">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Vous pouvez définir plusieurs plages horaires par jour en cliquant sur le bouton "Ajouter une plage horaire".
                    </div>
                    
                    <?php foreach ($joursFrancais as $jour => $jourFr): ?>
                        <div class="mb-4">
                            <h5><?= $jourFr ?></h5>
                            <div class="form-check mb-2">
                                <input class="form-check-input jour-toggle" type="checkbox" id="enable-<?= $jour ?>" 
                                       data-jour="<?= $jour ?>" <?= isset($jourDispo[$jour]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enable-<?= $jour ?>">
                                    Disponible ce jour
                                </label>
                            </div>
                            
                            <div id="plages-<?= $jour ?>" class="plages-container <?= isset($jourDispo[$jour]) ? '' : 'd-none' ?>">
                                <?php 
                                if (isset($jourDispo[$jour])) {
                                    foreach ($jourDispo[$jour] as $index => $horaire): 
                                ?>
                                    <div class="row gx-2 mb-2 plage-row">
                                        <div class="col-5">
                                            <input type="time" class="form-control" name="disponibilites[<?= $jour ?>][debut][]" 
                                                   value="<?= date('H:i', strtotime($horaire['debut'])) ?>" required>
                                        </div>
                                        <div class="col-5">
                                            <input type="time" class="form-control" name="disponibilites[<?= $jour ?>][fin][]" 
                                                   value="<?= date('H:i', strtotime($horaire['fin'])) ?>" required>
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-outline-danger remove-plage">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach;
                                } else {
                                ?>
                                    <div class="row gx-2 mb-2 plage-row">
                                        <div class="col-5">
                                            <input type="time" class="form-control" name="disponibilites[<?= $jour ?>][debut][]" 
                                                   value="09:00" required disabled>
                                        </div>
                                        <div class="col-5">
                                            <input type="time" class="form-control" name="disponibilites[<?= $jour ?>][fin][]" 
                                                   value="17:00" required disabled>
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-outline-danger remove-plage" disabled>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php } ?>
                                
                                <button type="button" class="btn btn-sm btn-outline-primary add-plage mt-2" data-jour="<?= $jour ?>"
                                        <?= isset($jourDispo[$jour]) ? '' : 'disabled' ?>>
                                    <i class="fas fa-plus me-1"></i>Ajouter une plage horaire
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js - Statistiques des rendez-vous
    var ctx = document.getElementById('appointmentsChart').getContext('2d');
    var appointmentsChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['En attente', 'Confirmés', 'Annulés', 'Terminés'],
            datasets: [{
                data: [
                    <?= $stats['en_attente'] ?? 0 ?>,
                    <?= $stats['confirmes'] ?? 0 ?>,
                    <?= $stats['annules'] ?? 0 ?>,
                    <?= $stats['completes'] ?? 0 ?>
                ],
                backgroundColor: [
                    '#ffc107', // warning - en attente
                    '#28a745', // success - confirmés
                    '#dc3545', // danger - annulés
                    '#17a2b8'  // info - terminés
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Gestion des disponibilités
    document.querySelectorAll('.jour-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const jour = this.dataset.jour;
            const plagesContainer = document.getElementById('plages-' + jour);
            const inputs = plagesContainer.querySelectorAll('input');
            const buttons = plagesContainer.querySelectorAll('button');
            
            if (this.checked) {
                plagesContainer.classList.remove('d-none');
                inputs.forEach(input => input.disabled = false);
                buttons.forEach(button => button.disabled = false);
            } else {
                plagesContainer.classList.add('d-none');
                inputs.forEach(input => input.disabled = true);
                buttons.forEach(button => button.disabled = true);
            }
        });
    });
    
    // Ajouter une plage horaire
    document.querySelectorAll('.add-plage').forEach(function(button) {
        button.addEventListener('click', function() {
            const jour = this.dataset.jour;
            const plagesContainer = document.getElementById('plages-' + jour);
            const newRow = document.createElement('div');
            newRow.className = 'row gx-2 mb-2 plage-row';
            newRow.innerHTML = `
                <div class="col-5">
                    <input type="time" class="form-control" name="disponibilites[${jour}][debut][]" value="09:00" required>
                </div>
                <div class="col-5">
                    <input type="time" class="form-control" name="disponibilites[${jour}][fin][]" value="17:00" required>
                </div>
                <div class="col-2">
                    <button type="button" class="btn btn-outline-danger remove-plage">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Insérer avant le bouton d'ajout
            plagesContainer.insertBefore(newRow, this);
            
            // Ajouter l'événement pour supprimer la plage
            newRow.querySelector('.remove-plage').addEventListener('click', function() {
                plagesContainer.removeChild(newRow);
            });
        });
    });
    
    // Supprimer une plage horaire
    document.querySelectorAll('.remove-plage').forEach(function(button) {
        button.addEventListener('click', function() {
            const row = this.closest('.plage-row');
            const plagesContainer = row.parentNode;
            // Ne pas supprimer s'il n'y a qu'une seule plage
            if (plagesContainer.querySelectorAll('.plage-row').length > 1) {
                plagesContainer.removeChild(row);
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>