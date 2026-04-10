<?php
// delete_file.php
require_once __DIR__ . '/auth.php';
require_auth();
require_once __DIR__ . '/etc/config.php';

// Check if a filename was provided via POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['filename'])) {
    
    // Get the filename from the form input
    $filename = basename($_POST['filename']);
    
    // Build secure absolute paths
    $base = realpath(__DIR__ . '/' . rtrim(PRIVATE_DIRECTORY, '/'));
    $filePath = $base ? realpath($base . DIRECTORY_SEPARATOR . $filename) : false;

    // SECURITY CHECK: Ensure the file is within the allowed directory.
    if ($filePath && $base && strpos($filePath, $base) === 0) {
        // Check if the file exists and delete it
        if (is_file($filePath) && file_exists($filePath)) {
            if (unlink($filePath)) {
                header("Location: index.php?delete_success=1");
                exit();
            } else {
                header("Location: index.php?delete_failed=1&message=" . urlencode("La suppression du fichier a échoué."));
                exit();
            }
        } else {
            header("Location: index.php?delete_failed=1&message=" . urlencode("Fichier non trouvé."));
            exit();
        }
    } else {
        header("Location: index.php?delete_failed=1&message=" . urlencode("Type de dossier non autorisé pour la suppression."));
        exit();
    }
} else {
    // No filename provided, redirect with an error message
    header("Location: index.php?delete_failed=1&message=" . urlencode("Paramètre de fichier manquant."));
    exit();
}