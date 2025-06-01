<?php
$pageTitle = "Détails du patient";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Récupérer l'ID du patient
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$patient_id) {
    $_SESSION['error'] = "ID du patient non spécifié.";
    header("Location: patients.php");
    exit;
}

// Récupérer les informations du patient
$stmt = $conn->prepare("
    SELECT p.*, 
           u.id as user_id, u.nom, u.prenom, u.email, u.date_creation,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.patient_id = p.id) as total_rdv,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.patient_id = p.id AND r.statut = 'complete') as total_consultations
    FROM patients p
    INNER JOIN utilisateurs u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $_SESSION['error'] = "Patient non trouvé.";
    header("Location: patients.php");
    exit;
}

// Récupérer les rendez-vous du patient
$stmt = $conn->prepare("
    SELECT r.*, 
           CONCAT(u.prenom, ' ', u.nom) as medecin_nom,
           s.nom as specialite
    FROM rendez_vous r
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN utilisateurs u ON m.user_id = u.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE r.patient_id = ?
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 10
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Détails du patient</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="patients.php">Patients</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></li>
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <h2>Informations personnelles</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="text-center mb-4">
                            <div class="patient-avatar mx-auto mb-3">
                                <?php 
                                $initials = strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1));
                                ?>
                                <span><?= $initials ?></span>
                            </div>
                            <h3><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></h3>
                            <p class="text-muted">Patient</p>
                        </div>
                        
                        <div class="patient-info">
                            <p><strong>Email :</strong> <?= htmlspecialchars($patient['email']) ?></p>
                            <p><strong>Téléphone :</strong> <?= !empty($patient['telephone']) ? htmlspecialchars($patient['telephone']) : '<span class="text-muted">Non renseigné</span>' ?></p>
                            
                            <?php
                            // Calcul de l'âge
                            $age = '';
                            if (!empty($patient['date_naissance'])) {
                                $birthdate = new DateTime($patient['date_naissance']);
                                $today = new DateTime();
                                $age = $birthdate->diff($today)->y . ' ans';
                            }
                            
                            // Formatage du sexe
                            $sexeDisplay = '';
                            switch ($patient['sexe']) {
                                case 'M':
                                    $sexeDisplay = 'Homme';
                                    break;
                                case 'F':
                                    $sexeDisplay = 'Femme';
                                    break;
                                case 'Autre':
                                    $sexeDisplay = 'Autre';
                                    break;
                                default:
                                    $sexeDisplay = 'Non spécifié';
                            }
                            ?>
                            
                            <p><strong>Date de naissance :</strong> <?= !empty($patient['date_naissance']) ? date('d/m/Y', strtotime($patient['date_naissance'])) : '<span class="text-muted">Non renseignée</span>' ?></p>
                            <p><strong>Âge :</strong> <?= !empty($age) ? $age : '<span class="text-muted">Non renseigné</span>' ?></p>
                            <p><strong>Sexe :</strong> <?= $sexeDisplay ?></p>
                            <p><strong>Date d'inscription :</strong> <?= date('d/m/Y H:i', strtotime($patient['date_creation'])) ?></p>
                        </div>
                        
                        <div class="mt-4 d-grid gap-2">
                            <a href="user-edit.php?id=<?= $patient['user_id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Modifier le profil
                            </a>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Statistiques</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="row g-4 text-center">
                            <div class="col-6">
                                <div class="stat-value"><?= $patient['total_rdv'] ?></div>
                                <div class="stat-label">Rendez-vous</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-value"><?= $patient['total_consultations'] ?></div>
                                <div class="stat-label">Consultations</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Derniers rendez-vous</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if (count($appointments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Médecin</th>
                                            <th>Spécialité</th>
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
                                            <td>
                                                <?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?><br>
                                                <small><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></small>
                                            </td>
                                            <td>Dr. <?= htmlspecialchars($appointment['medecin_nom']) ?></td>
                                            <td><?= htmlspecialchars($appointment['specialite']) ?></td>
                                            <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($patient['total_rdv'] > 10): ?>
                                <div class="text-center mt-3">
                                    <a href="appointments.php?patient_id=<?= $patient_id ?>" class="btn btn-sm btn-outline-primary">
                                        Voir tous les rendez-vous
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-5">
                                <img src="../assets/img/no-appointments.svg" alt="Aucun rendez-vous" style="max-width: 200px; opacity: 0.6;">
                                <h3 class="mt-4 text-muted">Aucun rendez-vous</h3>
                                <p class="text-muted">Ce patient n'a pas encore pris de rendez-vous.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($patient['total_consultations'] > 0): ?>
                <div class="dashboard-card mt-4">
                    <div class="dashboard-card-header">
                        <h2>Historique médical</h2>
                        <a href="#" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="dashboard-card-body">
                        <?php
                        // Récupérer les dernières consultations
                        $stmt = $conn->prepare("
                            SELECT c.*, r.date_rdv, r.heure_rdv,
                                   CONCAT(u.prenom, ' ', u.nom) as medecin_nom,
                                   s.nom as specialite
                            FROM consultations c
                            INNER JOIN rendez_vous r ON c.rdv_id = r.id
                            INNER JOIN medecins m ON r.medecin_id = m.id
                            INNER JOIN utilisateurs u ON m.user_id = u.id
                            INNER JOIN specialites s ON m.specialite_id = s.id
                            WHERE r.patient_id = ?
                            ORDER BY c.date DESC
                            LIMIT 5
                        ");
                        $stmt->execute([$patient_id]);
                        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php if (count($consultations) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($consultations as $consultation): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">Consultation du <?= date('d/m/Y', strtotime($consultation['date_rdv'])) ?></h5>
                                        <small><?= date('H:i', strtotime($consultation['heure_rdv'])) ?></small>
                                    </div>
                                    <p class="mb-1">Dr. <?= htmlspecialchars($consultation['medecin_nom']) ?> - <?= htmlspecialchars($consultation['specialite']) ?></p>
                                    <?php if (!empty($consultation['notes'])): ?>
                                        <small class="text-muted"><?= htmlspecialchars(substr($consultation['notes'], 0, 100)) ?>...</small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">Aucune consultation n'a été enregistrée pour ce patient.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>