<?php 
$currentPage = basename($_SERVER['PHP_SELF']);
$baseDir = dirname($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';

// Déterminer le dossier actuel pour construire les liens correctement
switch ($role) {
    case 'administrateur':
        $dashboardPath = './dashboard.php';
        $profilePath = './profile.php';
        $notificationsPath = './notifications.php';
        $logoutPath = '../logout.php';
        break;
    case 'medecin':
        $dashboardPath = './dashboard.php';
        $profilePath = './profile.php';
        $notificationsPath = './notifications.php';
        $logoutPath = '../logout.php';
        break;
    case 'patient':
        $dashboardPath = './dashboard.php';
        $profilePath = './profile.php';
        $notificationsPath = './notifications.php';
        $logoutPath = '../logout.php';
        break;
    default:
        $dashboardPath = '../login.php';
        $profilePath = '../login.php';
        $notificationsPath = '../login.php';
        $logoutPath = '../login.php';
}

// Menu items selon le rôle
$menuItems = [];

if ($role === 'administrateur') {
    $menuItems = [
        ['icon' => 'fas fa-chart-line', 'text' => 'Tableau de bord', 'link' => './dashboard.php', 'active' => $currentPage === 'dashboard.php'],
        ['icon' => 'fas fa-users', 'text' => 'Utilisateurs', 'link' => './users.php', 'active' => $currentPage === 'users.php'],
        ['icon' => 'fas fa-user-md', 'text' => 'Médecins', 'link' => './doctors.php', 'active' => $currentPage === 'doctors.php'],
        ['icon' => 'fas fa-procedures', 'text' => 'Patients', 'link' => './patients.php', 'active' => $currentPage === 'patients.php'],
        ['icon' => 'fas fa-calendar-alt', 'text' => 'Rendez-vous', 'link' => './appointments.php', 'active' => $currentPage === 'appointments.php'],
        ['icon' => 'fas fa-stethoscope', 'text' => 'Spécialités', 'link' => './specialties.php', 'active' => $currentPage === 'specialties.php'],
        ['icon' => 'fas fa-chart-pie', 'text' => 'Statistiques', 'link' => './statistics.php', 'active' => $currentPage === 'statistics.php'],
    ];
} elseif ($role === 'medecin') {
    $menuItems = [
        ['icon' => 'fas fa-chart-line', 'text' => 'Tableau de bord', 'link' => './dashboard.php', 'active' => $currentPage === 'dashboard.php'],
        ['icon' => 'fas fa-calendar-alt', 'text' => 'Rendez-vous', 'link' => './appointments.php', 'active' => $currentPage === 'appointments.php'],
        ['icon' => 'fas fa-procedures', 'text' => 'Patients', 'link' => './patients.php', 'active' => $currentPage === 'patients.php'],
        ['icon' => 'fas fa-clipboard-list', 'text' => 'Consultations', 'link' => './consultations.php', 'active' => $currentPage === 'consultations.php'],
        ['icon' => 'fas fa-calendar-check', 'text' => 'Disponibilités', 'link' => './availability.php', 'active' => $currentPage === 'availability.php'],
        ['icon' => 'fas fa-chart-pie', 'text' => 'Statistiques', 'link' => './statistics.php', 'active' => $currentPage === 'statistics.php'],
    ];
} elseif ($role === 'patient') {
    $menuItems = [
        ['icon' => 'fas fa-chart-line', 'text' => 'Tableau de bord', 'link' => './dashboard.php', 'active' => $currentPage === 'dashboard.php'],
        ['icon' => 'fas fa-calendar-plus', 'text' => 'Prendre RDV', 'link' => './take-appointment.php', 'active' => $currentPage === 'take-appointment.php'],
        ['icon' => 'fas fa-calendar-alt', 'text' => 'Mes rendez-vous', 'link' => './appointments.php', 'active' => $currentPage === 'appointments.php'],
        ['icon' => 'fas fa-clipboard-list', 'text' => 'Historique', 'link' => './history.php', 'active' => $currentPage === 'history.php'],
        ['icon' => 'fas fa-user-md', 'text' => 'Médecins', 'link' => './doctors.php', 'active' => $currentPage === 'doctors.php'],
    ];
}
?>

<div class="sidebar">
    <div class="sidebar-header">
        <a href="<?= $dashboardPath ?>" class="sidebar-brand">
            <i class="fas fa-heartbeat me-2"></i>
            <span>MedBook</span>
        </a>
        <button id="sidebarToggle" class="btn d-lg-none p-0">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php 
            $initials = '';
            if (isset($_SESSION['nom']) && isset($_SESSION['prenom'])) {
                $initials = strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1));
            }
            ?>
            <span><?= $initials ?></span>
        </div>
        <div class="user-info">
            <h5><?= ($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? '') ?></h5>
            <span class="user-role <?= $role ?>"><?= ucfirst($role ?? '') ?></span>
        </div>
    </div>
    
    <ul class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
        <li class="sidebar-item <?= $item['active'] ? 'active' : '' ?>">
            <a href="<?= $item['link'] ?>" class="sidebar-link">
                <i class="<?= $item['icon'] ?> sidebar-icon"></i>
                <span><?= $item['text'] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <div class="sidebar-footer">
        <a href="<?= $profilePath ?>" class="sidebar-link" data-bs-toggle="tooltip" data-bs-placement="top" title="Profil">
            <i class="fas fa-user-circle"></i>
        </a>
        <a href="<?= $notificationsPath ?>" class="sidebar-link position-relative" data-bs-toggle="tooltip" data-bs-placement="top" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadNotificationsCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $unreadNotificationsCount ?>
            </span>
            <?php endif; ?>
        </a>
        <a href="<?= $logoutPath ?>" class="sidebar-link" data-bs-toggle="tooltip" data-bs-placement="top" title="Déconnexion">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>