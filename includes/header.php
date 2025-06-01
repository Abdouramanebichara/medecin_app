<?php
// Vérifier si la session est active, sinon la démarrer
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction pour vérifier le rôle de l'utilisateur
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Redirection en fonction du rôle si accès non autorisé
function requireRole($role) {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }
    
    if ($_SESSION['role'] !== $role) {
        switch ($_SESSION['role']) {
            case 'administrateur':
                header('Location: ../admin/dashboard.php');
                break;
            case 'medecin':
                header('Location: ../medecin/dashboard.php');
                break;
            case 'patient':
                header('Location: ../patient/dashboard.php');
                break;
        }
        exit;
    }
}

// Fonction pour obtenir le nombre de notifications non lues
function getUnreadNotificationsCount() {
    if (!isLoggedIn()) return 0;
    
    require_once __DIR__ . '/../config/db.php';
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchColumn();
}

$unreadNotificationsCount = getUnreadNotificationsCount();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'MedBook' ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-body">