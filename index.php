<?php
session_start();

// Si l'utilisateur est déjà connecté avec un rôle défini, rediriger selon son rôle
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'administrateur':
            header('Location: admin/dashboard.php');
            exit;
        case 'medecin':
            header('Location: medecin/dashboard.php');
            exit;
        case 'patient':
            header('Location: patient/dashboard.php');
            exit;
        default:
            // Si le rôle n'est pas reconnu, vous pouvez rediriger vers une page par défaut
            header('Location: login.php');
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedBook - Accueil</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat me-2"></i>MedBook
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Inscription</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 animate-left">
                    <h1 class="display-4 fw-bold mb-4">Écoutez votre corps, il sait ce dont vous avez besoin</h1>
                    <p class="lead mb-4">Gérez vos consultations en ligne : prise de RDV, historique et rappels, tout en un.</p>
                    <div class="d-flex gap-3">
                        <a href="register.php" class="btn btn-primary btn-lg">S'inscrire</a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">Se connecter</a>
                    </div>
                </div>
                <div class="col-md-6 d-none d-md-block animate-right">
                    <img src="assets/img/doctor.jpg" alt="MedBook Illustration" class="img-fluid hero-img">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-header text-center mb-5">
                <h2 class="fw-bold">Nos services</h2>
                <p class="text-muted">Simplifiez votre parcours santé, du premier rendez-vous au suivi médical</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 feature-item">
                    <div class="card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3>Rendez-vous en ligne</h3>
                            <p>Consultez les meilleurs spécialistes rapidement et sans attente.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 feature-item">
                    <div class="card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>Historique médical</h3>
                            <p>Retrouvez tous vos documents et ordonnances en un seul endroit sécurisé.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 feature-item">
                    <div class="card h-100">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <h3>Rappels automatiques</h3>
                            <p>Plus d’oubli : notifications par e-mail ou SMS pour vos prochains RDV.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h4 class="mb-4">MedBook</h4>
                    <p>La révolution digitale au service de votre santé</p>
                </div>
                <div class="col-md-4">
                    <h4 class="mb-4">Liens utiles</h4>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="login.php">Connexion</a></li>
                        <li><a href="register.php">Inscription</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h4 class="mb-4">Contact</h4>
                    <p><i class="fas fa-envelope me-2"></i> contact@MedBook.com</p>
                    <p><i class="fas fa-phone me-2"></i> +237 657 926 556</p>
                    <div class="social-links mt-3">
                        <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2023 MedBook. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ScrollReveal for animations -->
    <script src="https://unpkg.com/scrollreveal"></script>
    <script>
        // Initialisation de ScrollReveal
        ScrollReveal().reveal('.animate-left', { 
            origin: 'left',
            distance: '50px',
            duration: 1000,
            delay: 200
        });
        ScrollReveal().reveal('.animate-right', { 
            origin: 'right',
            distance: '50px',
            duration: 1000,
            delay: 200
        });
        ScrollReveal().reveal('.feature-item', { 
            interval: 200,
            scale: 0.85,
            delay: 300
        });
    </script>
</body>
</html>
