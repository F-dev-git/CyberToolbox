<?php
require_once __DIR__ . '/auth.php';
require_auth();
require_once 'etc/config.php';

/**
 * Connects to the database and returns the connection object.
 * @return mysqli
 */
function connectDB() {
    $conn = new mysqli(DB_SERVERNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Vérifier la connexion et la terminer en cas d'erreur
    if ($conn->connect_error) {
        die("La connexion à la base de données a échoué : " . $conn->connect_error);
    }
    
    // Définir le jeu de caractères pour la connexion
    // C'est la ligne clé pour les caractères accentués
    $conn->set_charset("utf8mb4");

    return $conn;
}

$conn = connectDB(); 

// Requête pour récupérer le texte du presse-papiers
$sql = "SELECT texte FROM presse_papier";
$result = $conn->query($sql);
$contenu_texte = ""; // Initialisation de la variable

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $contenu_texte = $row["texte"];
}

// Fermeture de la connexion
$conn->close();

// Renvoyer le texte en tant que simple réponse
echo $contenu_texte;