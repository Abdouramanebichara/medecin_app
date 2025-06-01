<?php
$pageTitle = "Prendre un rendez-vous";
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

// Récupérer toutes les spécialités
$stmt = $conn->query("SELECT id, nom FROM specialites ORDER BY nom");
$specialites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Variables pour la prise de rendez-vous
$specialite_id = null;
$medecin_id = null;
$date_rdv = null;
$medecins = [];
$disponibilites = [];
$errors = [];
$success = false;

// Traitement du formulaire de sélection de spécialité
if (isset($_POST['select_specialite'])) {
    $specialite_id = $_POST['specialite_id'];
    
    // Récupérer les médecins de cette spécialité
    $stmt = $conn->prepare("
        SELECT m.id, u.prenom, u.nom
        FROM medecins m
        JOIN utilisateurs u ON m.user_id = u.id
        WHERE m.specialite_id = ?
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$specialite_id]);
    $medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Traitement du formulaire de sélection de médecin
if (isset($_POST['select_medecin'])) {
    $specialite_id = $_POST['specialite_id'];
    $medecin_id = $_POST['medecin_id'];
    
    // Récupérer les médecins de cette spécialité (pour maintenir l'état)
    $stmt = $conn->prepare("
        SELECT m.id, u.prenom, u.nom
        FROM medecins m
        JOIN utilisateurs u ON m.user_id = u.id
        WHERE m.specialite_id = ?
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$specialite_id]);
    $medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les disponibilités du médecin pour les 30 prochains jours
    $debut_periode = date('Y-m-d');
    $fin_periode = date('Y-m-d', strtotime('+30 days'));
    
    // Récupérer les jours disponibles selon les plages horaires configurées
    $stmt = $conn->prepare("
        SELECT DISTINCT jour
        FROM disponibilites
        WHERE medecin_id = ?
    ");
    $stmt->execute([$medecin_id]);
    $jours_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Mapper les jours de la semaine aux nombres (1=lundi, 7=dimanche)
    $jour_mapping = [
        'lundi' => 1,
        'mardi' => 2,
        'mercredi' => 3,
        'jeudi' => 4,
        'vendredi' => 5,
        'samedi' => 6,
        'dimanche' => 7
    ];
    
    // Convertir les jours disponibles en nombres de jour de la semaine
    $jours_disponibles_num = [];
    foreach ($jours_disponibles as $jour) {
        $jours_disponibles_num[] = $jour_mapping[$jour];
    }
    
    // Générer les dates disponibles
    $dates_disponibles = [];
    $date_courante = new DateTime($debut_periode);
    $date_fin = new DateTime($fin_periode);
    
    while ($date_courante <= $date_fin) {
        $jour_semaine = $date_courante->format('N'); // 1 (lundi) à 7 (dimanche)
        
        if (in_array($jour_semaine, $jours_disponibles_num)) {
            $dates_disponibles[] = $date_courante->format('Y-m-d');
        }
        
        $date_courante->modify('+1 day');
    }
    
    // Récupérer les rendez-vous déjà pris pour ce médecin sur cette période
    $stmt = $conn->prepare("
        SELECT date_rdv, heure_rdv
        FROM rendez_vous
        WHERE medecin_id = ? 
        AND date_rdv BETWEEN ? AND ? 
        AND statut IN ('en_attente', 'confirme')
    ");
    $stmt->execute([$medecin_id, $debut_periode, $fin_periode]);
    $rendez_vous_existants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer un tableau associatif des rendez-vous existants pour faciliter la vérification
    $rdv_occupes = [];
    foreach ($rendez_vous_existants as $rdv) {
        $rdv_occupes[$rdv['date_rdv']][] = $rdv['heure_rdv'];
    }
    
    // Structurer les disponibilités par date
    foreach ($dates_disponibles as $date) {
        // Remplaçons strftime() par date()
        $jour_semaine_fr = strtolower(date('l', strtotime($date))); // jour en anglais
        
        // Conversion du jour en français
        $jours_en_fr = [
            'monday' => 'lundi',
            'tuesday' => 'mardi',
            'wednesday' => 'mercredi',
            'thursday' => 'jeudi',
            'friday' => 'vendredi',
            'saturday' => 'samedi',
            'sunday' => 'dimanche'
        ];
        
        $jour_semaine_fr = $jours_en_fr[$jour_semaine_fr];
        
        // Récupérer les heures disponibles pour ce jour de la semaine
        $stmt = $conn->prepare("
            SELECT heure_debut, heure_fin
            FROM disponibilites
            WHERE medecin_id = ? AND jour = ?
        ");
        $stmt->execute([$medecin_id, $jour_semaine_fr]);
        $plages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $heures_disponibles = [];
        
        // Pour chaque plage horaire
        foreach ($plages as $plage) {
            $heure_debut = new DateTime($plage['heure_debut']);
            $heure_fin = new DateTime($plage['heure_fin']);
            
            // Créer des créneaux de 30 minutes
            $creneau = clone $heure_debut;
            while ($creneau < $heure_fin) {
                $heure_creneau = $creneau->format('H:i:s');
                
                // Vérifier si ce créneau est déjà pris
                if (!isset($rdv_occupes[$date]) || !in_array($heure_creneau, $rdv_occupes[$date])) {
                    $heures_disponibles[] = $heure_creneau;
                }
                
                $creneau->modify('+30 minutes');
            }
        }
        
        // Ajouter cette date et ses heures disponibles
        if (!empty($heures_disponibles)) {
            $disponibilites[] = [
                'date' => $date,
                // Remplaçons strftime() par date()
                'jour' => date('l j F Y', strtotime($date)), // Format: "Lundi 18 Mai 2023"
                'heures' => $heures_disponibles
            ];
        }
    }
}

// Traitement du formulaire de prise de rendez-vous final
if (isset($_POST['submit_rdv'])) {
    $specialite_id = $_POST['specialite_id'];
    $medecin_id = $_POST['medecin_id'];
    $date_rdv = $_POST['date_rdv'];
    $heure_rdv = $_POST['heure_rdv'];
    $motif = trim($_POST['motif']);
    
    // Validation
    if (empty($date_rdv)) {
        $errors[] = "La date du rendez-vous est requise";
    }
    
    if (empty($heure_rdv)) {
        $errors[] = "L'heure du rendez-vous est requise";
    }
    
    if (empty($motif)) {
        $errors[] = "Le motif du rendez-vous est requis";
    }
    
    // Vérifier que le créneau est toujours disponible
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM rendez_vous 
        WHERE medecin_id = ? AND date_rdv = ? AND heure_rdv = ? 
        AND statut IN ('en_attente', 'confirme')
    ");
    $stmt->execute([$medecin_id, $date_rdv, $heure_rdv]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Ce créneau horaire n'est plus disponible. Veuillez en choisir un autre.";
    }
    
    // Si pas d'erreurs, enregistrer le rendez-vous
    if (empty($errors)) {
        try {
            // Début de la transaction
            $conn->beginTransaction();
            
            // Insérer le rendez-vous
            $stmt = $conn->prepare("
                INSERT INTO rendez_vous (patient_id, medecin_id, date_rdv, heure_rdv, motif, statut) 
                VALUES (?, ?, ?, ?, ?, 'en_attente')
            ");
            $stmt->execute([$patient_id, $medecin_id, $date_rdv, $heure_rdv, $motif]);
            
            $rdv_id = $conn->lastInsertId();
            
            // Récupérer les informations du médecin pour la notification
            $stmt = $conn->prepare("
                SELECT u.id AS user_id, u.prenom, u.nom 
                FROM medecins m 
                JOIN utilisateurs u ON m.user_id = u.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$medecin_id]);
            $medecin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Créer une notification pour le patient
            $message_patient = "Votre rendez-vous avec Dr. {$medecin['prenom']} {$medecin['nom']} le " . date('d/m/Y à H:i', strtotime($date_rdv . ' ' . $heure_rdv)) . " a été enregistré. Il est en attente de confirmation.";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$user_id, $message_patient]);
            
            // Créer une notification pour le médecin
            $message_medecin = "Nouveau rendez-vous le " . date('d/m/Y à H:i', strtotime($date_rdv . ' ' . $heure_rdv)) . " avec le patient " . $_SESSION['prenom'] . " " . $_SESSION['nom'] . ". Motif: " . substr($motif, 0, 50) . (strlen($motif) > 50 ? '...' : '');
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$medecin['user_id'], $message_medecin]);
            
            // Valider la transaction
            $conn->commit();
            
            $success = true;
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollBack();
            $errors[] = "Erreur lors de l'enregistrement du rendez-vous: " . $e->getMessage();
        }
    }
    
    // Réinitialiser les données pour le formulaire en cas d'erreur
    if (!empty($errors)) {
        // Récupérer les médecins de cette spécialité (pour maintenir l'état)
        $stmt = $conn->prepare("
            SELECT m.id, u.prenom, u.nom
            FROM medecins m
            JOIN utilisateurs u ON m.user_id = u.id
            WHERE m.specialite_id = ?
            ORDER BY u.nom, u.prenom
        ");
        $stmt->execute([$specialite_id]);
        $medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Régénérer les disponibilités
        // (code similaire à celui du traitement du formulaire de sélection de médecin)
    }
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h1>Prendre un rendez-vous</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Prendre un rendez-vous</li>
                </ol>
            </nav>
        </div>
        
        <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i> Votre rendez-vous a été enregistré avec succès ! Il est actuellement en attente de confirmation par le médecin.
            <div class="mt-3">
                <a href="appointments.php" class="btn btn-sm btn-success">Voir mes rendez-vous</a>
                <a href="take-appointment.php" class="btn btn-sm btn-outline-primary ms-2">Prendre un autre rendez-vous</a>
            </div>
        </div>
        <?php else: ?>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h2>
                    <?php if ($medecin_id && !empty($disponibilites)): ?>
                        3. Choisir la date et l'heure
                    <?php elseif ($specialite_id && !empty($medecins)): ?>
                        2. Choisir un médecin
                    <?php else: ?>
                        1. Choisir une spécialité médicale
                    <?php endif; ?>
                </h2>
            </div>
            <div class="dashboard-card-body">
                <div class="progress mb-4">
                    <?php if ($medecin_id && !empty($disponibilites)): ?>
                        <div class="progress-bar" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">Étape 3/3</div>
                    <?php elseif ($specialite_id && !empty($medecins)): ?>
                        <div class="progress-bar" role="progressbar" style="width: 66%" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100">Étape 2/3</div>
                    <?php else: ?>
                        <div class="progress-bar" role="progressbar" style="width: 33%" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100">Étape 1/3</div>
                    <?php endif; ?>
                </div>
                
                <?php if ($medecin_id && !empty($disponibilites)): ?>
                <!-- Étape 3: Sélection de la date et l'heure -->
                <form method="post" action="take-appointment.php">
                    <input type="hidden" name="specialite_id" value="<?= htmlspecialchars($specialite_id) ?>">
                    <input type="hidden" name="medecin_id" value="<?= htmlspecialchars($medecin_id) ?>">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="date_rdv" class="form-label">Date du rendez-vous</label>
                            <select class="form-select" id="date_rdv" name="date_rdv" required>
                                <option value="">Choisir une date</option>
                                <?php foreach ($disponibilites as $dispo): 
                                    // Formatage de la date en français
                                    setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');
                                    $jour_formatte = date('l j F Y', strtotime($dispo['date']));
                                    // Traduire les jours et mois en français
                                    $jours_en = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    $jours_fr = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                                    $mois_en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                    $mois_fr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
                                    
                                    $jour_formatte = str_replace($jours_en, $jours_fr, $jour_formatte);
                                    $jour_formatte = str_replace($mois_en, $mois_fr, $jour_formatte);
                                ?>
                                <option value="<?= htmlspecialchars($dispo['date']) ?>"><?= $jour_formatte ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="heure_rdv" class="form-label">Heure du rendez-vous</label>
                            <select class="form-select" id="heure_rdv" name="heure_rdv" required>
                                <option value="">Choisir une heure</option>
                                <!-- Les heures seront chargées dynamiquement via JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="motif" class="form-label">Motif de la consultation</label>
                        <textarea class="form-control" id="motif" name="motif" rows="3" placeholder="Décrivez brièvement le motif de votre consultation..." required></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-primary" onclick="window.history.back();">
                            <i class="fas fa-arrow-left me-2"></i> Retour
                        </button>
                        <button type="submit" name="submit_rdv" class="btn btn-primary">
                            <i class="fas fa-calendar-check me-2"></i> Confirmer le rendez-vous
                        </button>
                    </div>
                </form>
                
                <script>
                // Stocker toutes les disponibilités dans une variable JavaScript
                var disponibilites = <?= json_encode($disponibilites) ?>;
                
                // Fonction pour charger les heures disponibles en fonction de la date sélectionnée
                document.getElementById('date_rdv').addEventListener('change', function() {
                    var selectedDate = this.value;
                    var heuresSelect = document.getElementById('heure_rdv');
                    
                    // Vider la liste des heures
                    heuresSelect.innerHTML = '<option value="">Choisir une heure</option>';
                    
                    // Si une date est sélectionnée, charger les heures disponibles
                    if (selectedDate) {
                        // Trouver les heures disponibles pour cette date
                        for (var i = 0; i < disponibilites.length; i++) {
                            if (disponibilites[i].date === selectedDate) {
                                var heures = disponibilites[i].heures;
                                
                                // Ajouter chaque heure comme option
                                for (var j = 0; j < heures.length; j++) {
                                    var option = document.createElement('option');
                                    option.value = heures[j];
                                    option.textContent = heures[j].substr(0, 5); // Format HH:MM
                                    heuresSelect.appendChild(option);
                                }
                                
                                break;
                            }
                        }
                    }
                });
                </script>
                
                <?php elseif ($specialite_id && !empty($medecins)): ?>
                <!-- Étape 2: Sélection du médecin -->
                <form method="post" action="take-appointment.php">
                    <input type="hidden" name="specialite_id" value="<?= htmlspecialchars($specialite_id) ?>">
                    
                    <div class="mb-4">
                        <label for="medecin_id" class="form-label">Sélectionner un médecin</label>
                        <select class="form-select" id="medecin_id" name="medecin_id" required>
                            <option value="">Choisir un médecin</option>
                            <?php foreach ($medecins as $medecin): ?>
                            <option value="<?= htmlspecialchars($medecin['id']) ?>">Dr. <?= htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-primary" onclick="window.history.back();">
                            <i class="fas fa-arrow-left me-2"></i> Retour
                        </button>
                        <button type="submit" name="select_medecin" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i> Continuer
                        </button>
                    </div>
                </form>
                
                <?php else: ?>
                <!-- Étape 1: Sélection de la spécialité -->
                <form method="post" action="take-appointment.php">
                    <div class="mb-4">
                        <label for="specialite_id" class="form-label">Sélectionner une spécialité médicale</label>
                        <select class="form-select" id="specialite_id" name="specialite_id" required>
                            <option value="">Choisir une spécialité</option>
                            <?php foreach ($specialites as $specialite): ?>
                            <option value="<?= htmlspecialchars($specialite['id']) ?>"><?= htmlspecialchars($specialite['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="select_specialite" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i> Continuer
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-card mt-4">
            <div class="dashboard-card-header">
                <h2>Informations utiles</h2>
            </div>
            <div class="dashboard-card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="feature-icon me-3">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div>
                                <h5>Préparation</h5>
                                <p>Veuillez apporter votre carte d'identité et votre carte d'assurance lors de votre rendez-vous.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="feature-icon me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h5>Ponctualité</h5>
                                <p>Merci d'arriver 15 minutes avant l'heure de votre rendez-vous pour les formalités administratives.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="feature-icon me-3">
                                <i class="fas fa-ban"></i>
                            </div>
                            <div>
                                <h5>Annulation</h5>
                                <p>En cas d'empêchement, merci de nous informer au moins 24h à l'avance.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>