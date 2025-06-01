<?php
$pageTitle = "Historique du patient";
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

// Récupérer les informations du patient
$stmt = $conn->prepare("
    SELECT p.*, 
           u.id as user_id, u.nom, u.prenom, u.email, u.date_creation,
           (SELECT COUNT(*) FROM rendez_vous r WHERE r.patient_id = p.id) as total_rdv,
           (SELECT COUNT(*) FROM rendez_vous r 
            INNER JOIN medecins m ON r.medecin_id = m.id
            WHERE r.patient_id = p.id AND m.user_id = ? AND r.statut = 'complete') as total_consultations_avec_ce_medecin
    FROM patients p
    INNER JOIN utilisateurs u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$_SESSION['user_id'], $patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $_SESSION['error'] = "Patient non trouvé.";
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

// Récupérer les consultations du patient avec ce médecin
$stmt = $conn->prepare("
    SELECT c.*, r.date_rdv, r.heure_rdv, r.motif
    FROM consultations c
    INNER JOIN rendez_vous r ON c.rdv_id = r.id
    WHERE r.patient_id = ? AND r.medecin_id = ?
    ORDER BY r.date_rdv DESC, r.heure_rdv DESC
");
$stmt->execute([$patient_id, $medecin_id]);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Historique du patient</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="patients.php">Patients</a></li>
                    <li class="breadcrumb-item"><a href="patient-detail.php?id=<?= $patient_id ?>"><?= htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Historique</li>
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
                        <h2>Informations du patient</h2>
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
                        </div>
                        
                        <div class="patient-info">
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
                            
                            <p><strong>Téléphone :</strong> <?= !empty($patient['telephone']) ? htmlspecialchars($patient['telephone']) : '<span class="text-muted">Non renseigné</span>' ?></p>
                            <p><strong>Email :</strong> <?= htmlspecialchars($patient['email']) ?></p>
                            <p><strong>Date de naissance :</strong> <?= !empty($patient['date_naissance']) ? date('d/m/Y', strtotime($patient['date_naissance'])) : '<span class="text-muted">Non renseignée</span>' ?></p>
                            <p><strong>Âge :</strong> <?= !empty($age) ? $age : '<span class="text-muted">Non renseigné</span>' ?></p>
                            <p><strong>Sexe :</strong> <?= $sexeDisplay ?></p>
                        </div>
                        
                        <div class="mt-4">
                            <p><strong>Nombre de consultations :</strong> <?= $patient['total_consultations_avec_ce_medecin'] ?></p>
                            <a href="patient-detail.php?id=<?= $patient_id ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user me-2"></i>Voir le profil complet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <h2>Historique des consultations</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if (count($consultations) > 0): ?>
                            <div class="accordion" id="consultationsAccordion">
                                <?php foreach ($consultations as $index => $consultation): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#consultation-<?= $consultation['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                                                <div>
                                                    <strong>Consultation du <?= date('d/m/Y', strtotime($consultation['date_rdv'])) ?></strong> à <?= date('H:i', strtotime($consultation['heure_rdv'])) ?>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="consultation-<?= $consultation['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#consultationsAccordion">
                                            <div class="accordion-body">
                                                <h5>Motif de la consultation</h5>
                                                <p><?= htmlspecialchars($consultation['motif']) ?? '<span class="text-muted">Aucun motif spécifié</span>' ?></p>
                                                
                                                <?php if (!empty($consultation['notes'])): ?>
                                                    <h5>Notes de consultation</h5>
                                                    <div class="consultation-notes mb-4">
                                                        <?= nl2br(htmlspecialchars($consultation['notes'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($consultation['ordonnance'])): ?>
                                                    <h5>Ordonnance</h5>
                                                    <div class="consultation-prescription p-3 border rounded bg-light mb-4">
                                                        <?= nl2br(htmlspecialchars($consultation['ordonnance'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-3 text-end">
                                                    <a href="consultation-edit.php?id=<?= $consultation['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit me-1"></i> Modifier cette consultation
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <img src="../assets/img/no-data.svg" alt="Aucune consultation" style="max-width: 200px; opacity: 0.6;">
                                <h3 class="mt-4 text-muted">Aucune consultation</h3>
                                <p class="text-muted">Vous n'avez pas encore effectué de consultation avec ce patient.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>