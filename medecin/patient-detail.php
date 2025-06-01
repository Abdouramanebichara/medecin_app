<?php
$pageTitle = "Détails du patient";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle médecin
requireRole('medecin');

// Récupérer l'ID du patient
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$patient_id) {
    $_SESSION['error'] = "ID du patient non spécifié.";
    header("Location: patients.php");
    exit;
}

// Récupérer l'ID du médecin connecté
$stmt = $conn->prepare("SELECT id FROM medecins WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medecin) {
    $_SESSION['error'] = "Profil médecin non trouvé.";
    header("Location: dashboard.php");
    exit;
}
$medecin_id = $medecin['id'];

// Récupérer les informations du patient
$stmt = $conn->prepare("
    SELECT p.*, 
           u.id as user_id, u.nom, u.prenom, u.email, u.date_creation,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.patient_id = p.id AND r.medecin_id = ?) as total_rdv,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.patient_id = p.id AND r.medecin_id = ? AND r.statut = 'complete') as total_consultations
    FROM patients p
    INNER JOIN utilisateurs u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$medecin_id, $medecin_id, $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $_SESSION['error'] = "Patient non trouvé.";
    header("Location: patients.php");
    exit;
}

// Récupérer les rendez-vous du patient avec ce médecin
$stmt = $conn->prepare("
    SELECT r.*, s.nom as specialite
    FROM rendez_vous r
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE r.patient_id = ? AND r.medecin_id = ?
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
    LIMIT 5
");
$stmt->execute([$patient_id, $medecin_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le prochain rendez-vous s'il existe
$stmt = $conn->prepare("
    SELECT r.*, s.nom as specialite
    FROM rendez_vous r
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE r.patient_id = ? AND r.medecin_id = ? AND r.date_rdv >= CURDATE() AND r.statut != 'annule'
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    LIMIT 1
");
$stmt->execute([$patient_id, $medecin_id]);
$nextAppointment = $stmt->fetch(PDO::FETCH_ASSOC);
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
                        </div>
                    </div>
                </div>

                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <h2>Statistiques</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="row g-4 text-center">
                            <div class="col-6">
                                <div class="stat-value"><?= $patient['total_rdv'] ?? 0 ?></div>
                                <div class="stat-label">Rendez-vous</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-value"><?= $patient['total_consultations'] ?? 0 ?></div>
                                <div class="stat-label">Consultations</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($nextAppointment): ?>
                <div class="dashboard-card">
                    <div class="dashboard-card-header bg-primary text-white">
                        <h2>Prochain rendez-vous</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="fas fa-calendar-day fs-1 text-primary"></i>
                            </div>
                            <h4><?= date('d/m/Y', strtotime($nextAppointment['date_rdv'])) ?></h4>
                            <p class="fs-5"><?= date('H:i', strtotime($nextAppointment['heure_rdv'])) ?></p>
                            <p class="text-muted"><?= htmlspecialchars($nextAppointment['motif'] ?? 'Aucun motif spécifié') ?></p>
                            
                            <?php
                            // Déterminer la classe et le texte du badge de statut
                            $statusClass = '';
                            $statusText = '';
                            switch ($nextAppointment['statut']) {
                                case 'en_attente':
                                    $statusClass = 'bg-warning text-dark';
                                    $statusText = 'En attente';
                                    break;
                                case 'confirme':
                                    $statusClass = 'bg-success';
                                    $statusText = 'Confirmé';
                                    break;
                                default:
                                    $statusClass = 'bg-secondary';
                                    $statusText = 'Inconnu';
                            }
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                            
                            <div class="mt-3">
                                <a href="appointment-detail.php?id=<?= $nextAppointment['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye me-2"></i>Détails
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-8">
                <div class="row">
                    <div class="col-12">
                        <div class="dashboard-card mb-4">
                            <div class="dashboard-card-header">
                                <h2>Actions</h2>
                            </div>
                            <div class="dashboard-card-body">
                                <div class="row g-3">
                                    <div class="col-lg-4 col-md-6">
                                        <a href="patient-history.php?id=<?= $patient_id ?>" class="btn btn-outline-primary w-100 py-3">
                                            <i class="fas fa-history fs-4 d-block mb-2"></i>
                                            Historique médical
                                        </a>
                                    </div>
                                    <div class="col-lg-4 col-md-6">
                                        <a href="appointment-new.php?patient_id=<?= $patient_id ?>" class="btn btn-outline-success w-100 py-3">
                                            <i class="fas fa-calendar-plus fs-4 d-block mb-2"></i>
                                            Nouveau rendez-vous
                                        </a>
                                    </div>
                                    <?php if ($patient['total_consultations'] > 0): ?>
                                    <div class="col-lg-4 col-md-6">
                                        <a href="consultation-new.php?patient_id=<?= $patient_id ?>" class="btn btn-outline-info w-100 py-3">
                                            <i class="fas fa-stethoscope fs-4 d-block mb-2"></i>
                                            Nouvelle consultation
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h2>Derniers rendez-vous</h2>
                                <?php if (count($appointments) > 5): ?>
                                <a href="appointments.php?patient_id=<?= $patient_id ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
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
                                                ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></td>
                                                    <td><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></td>
                                                    <td>
                                                        <?= !empty($appointment['motif']) 
                                                            ? (strlen($appointment['motif']) > 30 
                                                                ? htmlspecialchars(substr($appointment['motif'], 0, 30)) . '...' 
                                                                : htmlspecialchars($appointment['motif']))
                                                            : '<span class="text-muted">Non spécifié</span>' ?>
                                                    </td>
                                                    <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                                    <td>
                                                        <a href="appointment-detail.php?id=<?= $appointment['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($appointment['statut'] === 'complete'): 
                                                            // Vérifier s'il existe une consultation pour ce rendez-vous
                                                            $stmt = $conn->prepare("SELECT id FROM consultations WHERE rdv_id = ?");
                                                            $stmt->execute([$appointment['id']]);
                                                            $consultationExists = $stmt->fetch(PDO::FETCH_ASSOC);
                                                        ?>
                                                            <?php if ($consultationExists): ?>
                                                            <a href="consultation-edit.php?id=<?= $consultationExists['id'] ?>" class="btn btn-sm btn-outline-info ms-1">
                                                                <i class="fas fa-clipboard-list"></i>
                                                            </a>
                                                            <?php else: ?>
                                                            <a href="consultation-new.php?rdv_id=<?= $appointment['id'] ?>" class="btn btn-sm btn-outline-success ms-1">
                                                                <i class="fas fa-plus"></i>
                                                            </a>
                                                            <?php endif; ?>
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
                                        <h3 class="mt-4 text-muted">Aucun rendez-vous</h3>
                                        <p class="text-muted">Ce patient n'a pas encore de rendez-vous avec vous.</p>
                                        <a href="appointment-new.php?patient_id=<?= $patient_id ?>" class="btn btn-primary mt-2">
                                            <i class="fas fa-calendar-plus me-2"></i>Planifier un rendez-vous
                                        </a>
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

<?php require_once '../includes/footer.php'; ?>