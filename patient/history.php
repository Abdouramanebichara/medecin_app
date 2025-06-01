<?php
$pageTitle = "Historique médical";
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

// Si un ID de RDV est spécifié, afficher les détails de la consultation
$consultation_details = null;
if (isset($_GET['rdv_id'])) {
    $rdv_id = $_GET['rdv_id'];
    
    // Vérifier que le RDV appartient bien au patient
    $stmt = $conn->prepare("
        SELECT * FROM rendez_vous 
        WHERE id = ? AND patient_id = ? AND statut = 'complete'
    ");
    $stmt->execute([$rdv_id, $patient_id]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rdv) {
        // Récupérer les détails de la consultation
        $stmt = $conn->prepare("
            SELECT c.*, 
                   r.date_rdv, r.heure_rdv,
                   u.prenom as medecin_prenom, u.nom as medecin_nom,
                   s.nom as specialite
            FROM consultations c
            INNER JOIN rendez_vous r ON c.rdv_id = r.id
            INNER JOIN medecins m ON r.medecin_id = m.id
            INNER JOIN utilisateurs u ON m.user_id = u.id
            INNER JOIN specialites s ON m.specialite_id = s.id
            WHERE c.rdv_id = ?
        ");
        $stmt->execute([$rdv_id]);
        $consultation_details = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Redirection en cas de tentative d'accès non autorisé
        $_SESSION['error'] = "Vous n'êtes pas autorisé à accéder à cette consultation.";
        header("Location: history.php");
        exit;
    }
}

// Récupérer l'historique des consultations
$stmt = $conn->prepare("
    SELECT c.id, c.date, r.date_rdv, r.heure_rdv, 
           u.prenom as medecin_prenom, u.nom as medecin_nom,
           s.nom as specialite
    FROM consultations c
    INNER JOIN rendez_vous r ON c.rdv_id = r.id
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN utilisateurs u ON m.user_id = u.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE r.patient_id = ? AND r.statut = 'complete'
    ORDER BY c.date DESC
");
$stmt->execute([$patient_id]);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Historique médical</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Historique médical</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($consultation_details): ?>
            <div class="dashboard-card mb-4">
                <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                    <h2>Détails de la consultation</h2>
                    <a href="history.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Retour à l'historique
                    </a>
                </div>
                <div class="dashboard-card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Date de la consultation :</strong> <?= date('d/m/Y', strtotime($consultation_details['date_rdv'])) ?></p>
                            <p><strong>Heure :</strong> <?= date('H:i', strtotime($consultation_details['heure_rdv'])) ?></p>
                            <p><strong>Médecin :</strong> Dr. <?= htmlspecialchars($consultation_details['medecin_prenom'] . ' ' . $consultation_details['medecin_nom']) ?></p>
                            <p><strong>Spécialité :</strong> <?= htmlspecialchars($consultation_details['specialite']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="consultation-stamp">
                                <div class="stamp-date"><?= date('d/m/Y', strtotime($consultation_details['date'])) ?></div>
                                <div class="stamp-doctor">Dr. <?= htmlspecialchars($consultation_details['medecin_prenom'] . ' ' . $consultation_details['medecin_nom']) ?></div>
                                <div class="stamp-specialty"><?= htmlspecialchars($consultation_details['specialite']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="consultation-section">
                        <h4 class="consultation-section-title">
                            <i class="fas fa-clipboard-list me-2"></i>Notes de consultation
                        </h4>
                        <div class="consultation-content">
                            <?= nl2br(htmlspecialchars($consultation_details['notes'] ?? 'Aucune note disponible.')) ?>
                        </div>
                    </div>

                    <div class="consultation-section">
                        <h4 class="consultation-section-title">
                            <i class="fas fa-prescription-bottle-alt me-2"></i>Ordonnance
                        </h4>
                        <div class="consultation-content">
                            <?php if (!empty($consultation_details['ordonnance'])): ?>
                                <div class="prescription-box">
                                    <?= nl2br(htmlspecialchars($consultation_details['ordonnance'])) ?>
                                </div>
                                <div class="text-end mt-3">
                                    <button class="btn btn-outline-primary" onclick="printPrescription()">
                                        <i class="fas fa-print me-2"></i>Imprimer l'ordonnance
                                    </button>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Aucune ordonnance n'a été prescrite lors de cette consultation.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>Mes consultations passées</h2>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($consultations) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Médecin</th>
                                    <th>Spécialité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($consultation['date_rdv'] . ' ' . $consultation['heure_rdv'])) ?></td>
                                    <td>Dr. <?= htmlspecialchars($consultation['medecin_prenom'] . ' ' . $consultation['medecin_nom']) ?></td>
                                    <td><?= htmlspecialchars($consultation['specialite']) ?></td>
                                    <td>
                                        <a href="history.php?rdv_id=<?= $consultation['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye me-2"></i>Voir détails
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="../assets/img/no-history.svg" alt="Aucun historique" style="max-width: 200px; opacity: 0.6;">
                        <h3 class="mt-4 text-muted">Aucune consultation passée</h3>
                        <p class="text-muted mb-4">Vous n'avez pas encore de consultation dans votre historique médical.</p>
                        <a href="appointments.php" class="btn btn-primary">
                            <i class="fas fa-calendar-alt me-2"></i>Voir mes rendez-vous
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function printPrescription() {
    var content = document.querySelector('.prescription-box').innerHTML;
    var doctorInfo = document.querySelector('.stamp-doctor').textContent;
    var specialtyInfo = document.querySelector('.stamp-specialty').textContent;
    var dateInfo = document.querySelector('.stamp-date').textContent;
    
    var printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Ordonnance médicale</title>
            <style>
                body { font-family: 'Arial', sans-serif; padding: 20px; line-height: 1.6; }
                .header { border-bottom: 2px solid #4a8cca; padding-bottom: 10px; margin-bottom: 20px; }
                .prescription { white-space: pre-wrap; margin: 20px 0; }
                .footer { margin-top: 40px; text-align: right; }
                @media print {
                    .no-print { display: none; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Ordonnance médicale</h2>
                <p>${doctorInfo} - ${specialtyInfo}</p>
                <p>Date: ${dateInfo}</p>
            </div>
            <div class="prescription">
                ${content}
            </div>
            <div class="footer">
                <p>Signature du médecin:</p>
                <div style="height: 60px;"></div>
                <p>${doctorInfo}</p>
            </div>
            <div class="no-print" style="text-align: center; margin-top: 30px;">
                <button onclick="window.print();" style="padding: 10px 20px;">Imprimer</button>
                <button onclick="window.close();" style="padding: 10px 20px; margin-left: 10px;">Fermer</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php require_once '../includes/footer.php'; ?>