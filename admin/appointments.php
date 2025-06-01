<?php
$pageTitle = "Gestion des rendez-vous";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Filtrer les rendez-vous
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Préparer la requête SQL de base
$sql = "
    SELECT r.*, 
           p.id as patient_id,
           up.prenom as patient_prenom, up.nom as patient_nom,
           m.id as medecin_id,
           um.prenom as medecin_prenom, um.nom as medecin_nom,
           s.nom as specialite
    FROM rendez_vous r
    INNER JOIN patients p ON r.patient_id = p.id
    INNER JOIN utilisateurs up ON p.user_id = up.id
    INNER JOIN medecins m ON r.medecin_id = m.id
    INNER JOIN utilisateurs um ON m.user_id = um.id
    INNER JOIN specialites s ON m.specialite_id = s.id
    WHERE 1=1
";

// Ajouter les conditions de filtrage
$params = [];

if (!empty($filter_status)) {
    $sql .= " AND r.statut = ?";
    $params[] = $filter_status;
}

if (!empty($filter_date)) {
    switch ($filter_date) {
        case 'today':
            $sql .= " AND r.date_rdv = CURDATE()";
            break;
        case 'tomorrow':
            $sql .= " AND r.date_rdv = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $sql .= " AND r.date_rdv BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'future':
            $sql .= " AND r.date_rdv > CURDATE()";
            break;
        case 'past':
            $sql .= " AND r.date_rdv < CURDATE()";
            break;
    }
}

if (!empty($search)) {
    $sql .= " AND (up.nom LIKE ? OR up.prenom LIKE ? OR um.nom LIKE ? OR um.prenom LIKE ?)";
    $search_param = "%" . $search . "%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

$sql .= " ORDER BY r.date_rdv DESC, r.heure_rdv DESC";

// Exécuter la requête
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Gestion des rendez-vous</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rendez-vous</li>
                </ol>
            </nav>
        </div>

        <div class="dashboard-card mb-4">
            <div class="dashboard-card-header">
                <h2>Filtrer les rendez-vous</h2>
            </div>
            <div class="dashboard-card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente" <?= $filter_status === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="confirme" <?= $filter_status === 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                            <option value="annule" <?= $filter_status === 'annule' ? 'selected' : '' ?>>Annulé</option>
                            <option value="complete" <?= $filter_status === 'complete' ? 'selected' : '' ?>>Terminé</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="date" class="form-label">Date</label>
                        <select class="form-select" id="date" name="date">
                            <option value="">Toutes les dates</option>
                            <option value="today" <?= $filter_date === 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
                            <option value="tomorrow" <?= $filter_date === 'tomorrow' ? 'selected' : '' ?>>Demain</option>
                            <option value="week" <?= $filter_date === 'week' ? 'selected' : '' ?>>Cette semaine</option>
                            <option value="future" <?= $filter_date === 'future' ? 'selected' : '' ?>>À venir</option>
                            <option value="past" <?= $filter_date === 'past' ? 'selected' : '' ?>>Passés</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Recherche (patient/médecin)</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher...">
                    </div>
                    <div class="col-12 text-end">
                        <a href="appointments.php" class="btn btn-secondary me-2">Réinitialiser</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h2><?= count($appointments) ?> Rendez-vous</h2>
            </div>
            <div class="dashboard-card-body">
                <?php if (count($appointments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date & Heure</th>
                                    <th>Patient</th>
                                    <th>Médecin</th>
                                    <th>Spécialité</th>
                                    <th>Statut</th>
                                    <th>Date de création</th>
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
                                    
                                    // Vérifier si le rendez-vous est aujourd'hui
                                    $isToday = date('Y-m-d') === $appointment['date_rdv'];
                                ?>
                                <tr class="<?= $isToday ? 'table-primary' : '' ?>">
                                    <td><?= $appointment['id'] ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?><br>
                                        <small><?= date('H:i', strtotime($appointment['heure_rdv'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php 
                                                $initials = strtoupper(substr($appointment['patient_prenom'], 0, 1) . substr($appointment['patient_nom'], 0, 1));
                                                ?>
                                                <span><?= $initials ?></span>
                                            </div>
                                            <?= htmlspecialchars($appointment['patient_prenom'] . ' ' . $appointment['patient_nom']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?php 
                                                $initials = strtoupper(substr($appointment['medecin_prenom'], 0, 1) . substr($appointment['medecin_nom'], 0, 1));
                                                ?>
                                                <span><?= $initials ?></span>
                                            </div>
                                            Dr. <?= htmlspecialchars($appointment['medecin_prenom'] . ' ' . $appointment['medecin_nom']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($appointment['specialite']) ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                    <td><?= date('d/m/Y H:i', strtotime($appointment['date_creation'])) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $appointment['id'] ?>" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="../assets/img/no-appointments.svg" alt="Aucun rendez-vous" style="max-width: 200px; opacity: 0.6;">
                        <h3 class="mt-4 text-muted">Aucun rendez-vous trouvé</h3>
                        <p class="text-muted mb-4">Aucun rendez-vous ne correspond à vos critères de recherche.</p>
                        <a href="appointments.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-2"></i>Réinitialiser la recherche
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de détails -->
                                        <div class="modal fade" id="detailsModal<?= $appointment['id'] ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?= $appointment['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="detailsModalLabel<?= $appointment['id'] ?>">Détails du rendez-vous #<?= $appointment['id'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Informations du rendez-vous</h6>
                                                                <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($appointment['date_rdv'])) ?></p>
                                                                <p><strong>Heure :</strong> <?= date('H:i', strtotime($appointment['heure_rdv'])) ?></p>
                                                                <p><strong>Statut :</strong> <span class="badge <?= $statusClass ?>"><?= $statusText ?></span></p>
                                                                <p><strong>Date de création :</strong> <?= date('d/m/Y H:i', strtotime($appointment['date_creation'])) ?></p>
                                                                <p><strong>Spécialité :</strong> <?= htmlspecialchars($appointment['specialite']) ?></p>
                                                                
                                                                <h6 class="mt-4">Motif de la consultation</h6>
                                                                <p><?= nl2br(htmlspecialchars($appointment['motif'])) ?></p>
                                                            </div>
                                                            
                                                            <div class="col-md-6">
                                                                <h6>Patient</h6>
                                                                <p><strong>Nom :</strong> <?= htmlspecialchars($appointment['patient_prenom'] . ' ' . $appointment['patient_nom']) ?></p>
                                                                <p><strong>Identifiant :</strong> <?= $appointment['patient_id'] ?></p>
                                                                <a href="patient-detail.php?id=<?= $appointment['patient_id'] ?>" class="btn btn-sm btn-outline-primary mb-4">
                                                                    <i class="fas fa-user me-2"></i>Voir profil du patient
                                                                </a>
                                                                
                                                                <h6>Médecin</h6>
                                                                <p><strong>Nom :</strong> Dr. <?= htmlspecialchars($appointment['medecin_prenom'] . ' ' . $appointment['medecin_nom']) ?></p>
                                                                <p><strong>Identifiant :</strong> <?= $appointment['medecin_id'] ?></p>
                                                                <a href="doctor-detail.php?id=<?= $appointment['medecin_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-user-md me-2"></i>Voir profil du médecin
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
<?php require_once '../includes/footer.php'; ?>