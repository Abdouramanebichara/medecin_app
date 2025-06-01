<?php
// Configuration de la base de données
$host = "localhost";
$dbname = "medical_app";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// medecin/patient-history.php medecin/patient-detail admin/doctor-detail.php
?>

