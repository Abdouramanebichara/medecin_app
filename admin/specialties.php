<?php
$pageTitle = "Gestion des spécialités";
require_once '../config/db.php';
require_once '../includes/header.php';

// Vérifier que l'utilisateur est connecté et a le rôle administrateur
requireRole('administrateur');

// Traiter l'ajout d'une nouvelle spécialité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_specialty'])) {
    $nom = trim($_POST['nom']);
    
    if (empty($nom)) {
        $_SESSION['error'] = "Le nom de la spécialité est obligatoire.";
    } else {
        // Vérifier si la spécialité existe déjà
        $stmt = $conn->prepare("SELECT COUNT(*) FROM specialites WHERE nom = ?");
        $stmt->execute([$nom]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Cette spécialité existe déjà.";
        } else {
            $stmt = $conn->prepare("INSERT INTO specialites (nom) VALUES (?)");
            $stmt->execute([$nom]);
            $_SESSION['success'] = "La spécialité a été ajoutée avec succès.";
            header("Location: specialties.php");
            exit;
        }
    }
}

// Traiter la modification d'une spécialité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_specialty'])) {
    $id = intval($_POST['id']);
    $nom = trim($_POST['nom']);
    
    if (empty($nom)) {
        $_SESSION['error'] = "Le nom de la spécialité est obligatoire.";
    } else {
        // Vérifier si la spécialité existe déjà
        $stmt = $conn->prepare("SELECT COUNT(*) FROM specialites WHERE nom = ? AND id != ?");
        $stmt->execute([$nom, $id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Cette spécialité existe déjà.";
        } else {
            $stmt = $conn->prepare("UPDATE specialites SET nom = ? WHERE id = ?");
            $stmt->execute([$nom, $id]);
            $_SESSION['success'] = "La spécialité a été modifiée avec succès.";
            header("Location: specialties.php");
            exit;
        }
    }
}

// Traiter la suppression d'une spécialité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_specialty'])) {
    $id = intval($_POST['id']);
    
    // Vérifier si la spécialité est utilisée par des médecins
    $stmt = $conn->prepare("SELECT COUNT(*) FROM medecins WHERE specialite_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error'] = "Impossible de supprimer cette spécialité car elle est utilisée par des médecins.";
    } else {
        $stmt = $conn->prepare("DELETE FROM specialites WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "La spécialité a été supprimée avec succès.";
        header("Location: specialties.php");
        exit;
    }
}

// Récupérer toutes les spécialités
$search = isset($_GET['search']) ? $_GET['search'] : '';
$params = [];
$where_clause = "";

if (!empty($search)) {
    $where_clause = "WHERE nom LIKE ?";
    $params[] = "%" . $search . "%";
}

$stmt = $conn->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM medecins m WHERE m.specialite_id = s.id) as nb_medecins
    FROM specialites s
    $where_clause
    ORDER BY s.nom
");
$stmt->execute($params);
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Gestion des spécialités</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Spécialités</li>
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
            <div class="col-md-8">
                <div class="dashboard-card">
                    <div class="dashboard-card-header d-flex justify-content-between align-items-center">
                        <h2>Liste des spécialités</h2>
                        <form action="" method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if (count($specialties) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Nombre de médecins</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($specialties as $specialty): ?>
                                        <tr>
                                            <td><?= $specialty['id'] ?></td>
                                            <td><?= htmlspecialchars($specialty['nom']) ?></td>
                                            <td><?= $specialty['nb_medecins'] ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-primary" title="Modifier" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                            data-id="<?= $specialty['id'] ?>" 
                                                            data-nom="<?= htmlspecialchars($specialty['nom']) ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($specialty['nb_medecins'] == 0): ?>
                                                        <button type="button" class="btn btn-sm btn-danger" title="Supprimer" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                                data-id="<?= $specialty['id'] ?>" 
                                                                data-nom="<?= htmlspecialchars($specialty['nom']) ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <img src="../assets/img/no-results.svg" alt="Aucune spécialité" style="max-width: 200px; opacity: 0.6;">
                                <h3 class="mt-4 text-muted">Aucune spécialité trouvée</h3>
                                <p class="text-muted mb-4">
                                    <?= empty($search) ? 
                                        "Aucune spécialité n'a encore été enregistrée." : 
                                        "Aucune spécialité ne correspond à votre recherche." ?>
                                </p>
                                <?php if (!empty($search)): ?>
                                    <a href="specialties.php" class="btn btn-primary">
                                        <i class="fas fa-times me-2"></i>Effacer la recherche
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Ajouter une spécialité</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom de la spécialité</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="add_specialty" class="btn btn-success">
                                    <i class="fas fa-plus-circle me-2"></i>Ajouter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de modification -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Modifier la spécialité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_nom" class="form-label">Nom de la spécialité</label>
                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="edit_specialty" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer la spécialité <strong id="delete_nom"></strong> ?</p>
            </div>
            <div class="modal-footer">
                <form action="" method="post">
                    <input type="hidden" id="delete_id" name="id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="delete_specialty" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Remplir le modal de modification
document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var nom = button.getAttribute('data-nom');
    
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nom').value = nom;
});

// Remplir le modal de suppression
document.getElementById('deleteModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var nom = button.getAttribute('data-nom');
    
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_nom').textContent = nom;
});
</script>

<?php require_once '../includes/footer.php'; ?>