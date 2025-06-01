<?php
$pageTitle = "Consultations";
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

// Si un ID de RDV est spécifié
$consultation_details = null;
$patient_details = null;
$rdv_details = null;
$rdv_id = isset($_GET['rdv_id']) ? intval($_GET['rdv_id']) : 0;

if ($rdv_id > 0) {
    // Vérifier que le RDV appartient bien au médecin et qu'il est terminé
    $stmt = $conn->prepare("
        SELECT r.*, 
               u.nom as patient_nom, u.prenom as patient_prenom,
               p.telephone as patient_telephone, p.date_naissance, p.sexe
        FROM rendez_vous r
        INNER JOIN patients p ON r.patient_id = p.id
        INNER JOIN utilisateurs u ON p.user_id = u.id
        WHERE r.id = ? AND r.medecin_id = ? AND r.statut = 'complete'
    ");
    $stmt->execute([$rdv_id, $medecin_id]);
    $rdv_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rdv_details) {
        // Récupérer les détails de la consultation si elle existe
        $stmt = $conn->prepare("
            SELECT * FROM consultations 
            WHERE rdv_id = ?
        ");
        $stmt->execute([$rdv_id]);
        $consultation_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Traitement du formulaire de mise à jour de consultation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_consultation'])) {
            $notes = trim($_POST['notes']);
            $ordonnance = trim($_POST['ordonnance']);
            
            if ($consultation_details) {
                // Mise à jour d'une consultation existante
                $stmt = $conn->prepare("
                    UPDATE consultations 
                    SET notes = ?, ordonnance = ? 
                    WHERE rdv_id = ?
                ");
                $stmt->execute([$notes, $ordonnance, $rdv_id]);
            } else {
                // Création d'une nouvelle consultation
                $stmt = $conn->prepare("
                    INSERT INTO consultations (rdv_id, notes, ordonnance, date) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$rdv_id, $notes, $ordonnance]);
            }
            
            // Rediriger pour éviter la soumission multiple
            header("Location: consultations.php?rdv_id=" . $rdv_id . "&saved=1");
            exit;
        }
    } else {
        // Redirection si le RDV n'est pas valide
        $_SESSION['error'] = "Rendez-vous non trouvé ou non autorisé.";
        header("Location: consultations.php");
        exit;
    }
}

// Récupérer les consultations passées
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = "WHERE r.medecin_id = ? AND r.statut = 'complete' AND c.id IS NOT NULL";
$params = [$medecin_id];

if (!empty($search)) {
    $where_clause .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param);
}

$stmt = $conn->prepare("
    SELECT c.id, c.date, c.rdv_id,
           r.date_rdv, r.heure_rdv,
           u.nom as patient_nom, u.prenom as patient_prenom,
           p.id as patient_id
    FROM consultations c
    INNER JOIN rendez_vous r ON c.rdv_id = r.id
    INNER JOIN patients p ON r.patient_id = p.id
    INNER JOIN utilisateurs u ON p.user_id = u.id
    $where_clause
    ORDER BY c.date DESC
");
$stmt->execute($params);
$consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1><?= $rdv_id ? 'Détails de la consultation' : 'Consultations' ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <?php if ($rdv_id): ?>
                        <li class="breadcrumb-item"><a href="consultations.php">Consultations</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Détails</li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page">Consultations</li>
                    <?php endif; ?>
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

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                La consultation a été enregistrée avec succès.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <?php if ($rdv_details): ?>
            <div class="dashboard-card mb-4">
                <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                    <h2>Informations du patient</h2>
                    <a href="consultations.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Retour aux consultations
                    </a>
                </div>
                <div class="dashboard-card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><?= htmlspecialchars($rdv_details['patient_prenom'] . ' ' . $rdv_details['patient_nom']) ?></h4>
                            
                            <?php
                            // Calcul de l'âge
                            $age = '';
                            if (!empty($rdv_details['date_naissance'])) {
                                $birthdate = new DateTime($rdv_details['date_naissance']);
                                $today = new DateTime();
                                $age = $birthdate->diff($today)->y . ' ans';
                            }
                            
                            // Formatage du sexe
                            $sexeDisplay = '';
                            switch ($rdv_details['sexe']) {
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
                            
                            <p><strong>Âge:</strong> <?= $age ?: 'Non renseigné' ?></p>
                            <p><strong>Sexe:</strong> <?= $sexeDisplay ?></p>
                            <p><strong>Téléphone:</strong> <?= !empty($rdv_details['patient_telephone']) ? htmlspecialchars($rdv_details['patient_telephone']) : 'Non renseigné' ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="consultation-details">
                                <p><strong>Date de consultation:</strong> <?= date('d/m/Y', strtotime($rdv_details['date_rdv'])) ?></p>
                                <p><strong>Heure:</strong> <?= date('H:i', strtotime($rdv_details['heure_rdv'])) ?></p>
                                <p><strong>Motif:</strong> <?= htmlspecialchars($rdv_details['motif']) ?></p>
                            </div>
                            <div class="mt-3">
                                <a href="patient-history.php?id=<?= $rdv_details['patient_id'] ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-history me-2"></i>Historique du patient
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2>Détails de la consultation</h2>
                </div>
                <div class="dashboard-card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes médicales</label>
                            <textarea class="form-control" id="notes" name="notes" rows="5" placeholder="Saisissez vos notes médicales ici..."><?= htmlspecialchars($consultation_details['notes'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ordonnance" class="form-label">Ordonnance</label>
                            <textarea class="form-control" id="ordonnance" name="ordonnance" rows="8" placeholder="Saisissez l'ordonnance ici..."><?= htmlspecialchars($consultation_details['ordonnance'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="save_consultation" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer la consultation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-card mb-4">
                <div class="dashboard-card-header">
                    <h2>Rechercher une consultation</h2>
                </div>
                <div class="dashboard-card-body">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par nom ou prénom du patient...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2>Consultations passées</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if (count($consultations) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consultations as $consultation): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($consultation['date'])) ?></td>
                                        <td><?= htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']) ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="consultations.php?rdv_id=<?= $consultation['rdv_id'] ?>" class="btn btn-sm btn-primary" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="printConsultation(<?= $consultation['rdv_id'] ?>)" class="btn btn-sm btn-info" title="Imprimer">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <img src="../assets/img/no-consultations.svg" alt="Aucune consultation" style="max-width: 200px; opacity: 0.6;">
                            <h3 class="mt-4 text-muted">Aucune consultation trouvée</h3>
                            <p class="text-muted mb-4">
                                <?= empty($search) ? 
                                    "Vous n'avez pas encore de consultations enregistrées." : 
                                    "Aucune consultation ne correspond à votre recherche." ?>
                            </p>
                            <?php if (!empty($search)): ?>
                                <a href="consultations.php" class="btn btn-primary">
                                    <i class="fas fa-times me-2"></i>Effacer la recherche
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function printConsultation(rdvId) {
    // Créer un nouvel onglet pour l'impression
    var printWindow = window.open('print-consultation.php?rdv_id=' + rdvId, '_blank');
}
</script>

<?php require_once '../includes/footer.php'; ?>