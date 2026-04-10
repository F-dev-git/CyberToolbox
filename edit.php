<?php
require_once __DIR__ . '/auth.php';
require_auth();
require_once __DIR__ . '/etc/config.php';

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

// Prepare the update statement
$sql = "UPDATE presse_papier SET texte = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Failed to prepare update statement: " . mysqli_error($conn));
}

// Assuming you're receiving the new text from a form field named 'new_text':
$new_text = $_POST['new_text'];

// Bind the parameter
mysqli_stmt_bind_param($stmt, "s", $new_text);

// Execute the update statement
if (!mysqli_stmt_execute($stmt)) {
    die("Failed to execute update statement: " . mysqli_error($conn));
}

// Close the statement
mysqli_stmt_close($stmt);

// Close the connection
mysqli_close($conn);

// Redirect back to the editor page (assuming the URL is correct)
header('Location: index.php');
exit;
?>
