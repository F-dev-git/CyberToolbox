<?php
ob_start(); // Start output buffering
require_once __DIR__ . '/auth.php';
require_auth();
require_once __DIR__ . '/etc/config.php';

// Directory for private transfers - must come from config
if (!defined('PRIVATE_DIRECTORY') || trim(PRIVATE_DIRECTORY) === '') {
    header('Location: index.php?status=error&messages=' . urlencode(json_encode(['Erreur: PRIVATE_DIRECTORY non défini dans etc/config.php'])), true, 303);
    exit();
}

// Resolve absolute path for PRIVATE_DIRECTORY (relative to CT/)
$privateDirectory = realpath(__DIR__ . '/' . rtrim(PRIVATE_DIRECTORY, '/'));
if ($privateDirectory === false) {
    $privateDirectory = __DIR__ . '/' . rtrim(PRIVATE_DIRECTORY, '/') . '/';
} else {
    $privateDirectory = rtrim($privateDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

$success = true;
$messages = [];

/**
 * Function to clear a directory of all its files.
 * Does not delete subdirectories, only files.
 *
 * @param string $dirPath The path of the directory to clear.
 * @param string $folderName The folder's name (for success/error messages).
 * @return bool True if the directory was cleared successfully or was already empty, False on error.
 */
function purgeDirectory($dirPath, $folderName, &$messages) {
    if (!is_dir($dirPath)) {
        // The folder doesn't exist, nothing to clear. Considered a success.
        $messages[] = "The '{$folderName}' folder doesn't exist, nothing to purge.";
        return true;
    }

    if ($dir = opendir($dirPath)) {
        $filesDeletedInFolder = 0;
        while (($entry = readdir($dir)) !== false) {
            if ($entry != "." && $entry != "..") {
                $filePath = $dirPath . $entry;
                if (is_file($filePath)) {
                    if (unlink($filePath)) {
                        $filesDeletedInFolder++;
                    } else {
                        $messages[] = "Error: Could not delete the file '{$entry}' in '{$folderName}'. Check permissions.";
                        return false; // Purge failed for this folder
                    }
                }
            }
        }
        closedir($dir);
        if ($filesDeletedInFolder > 0) {
            $messages[] = "{$filesDeletedInFolder} file(s) deleted from the '{$folderName}' folder.";
        } else {
            $messages[] = "The '{$folderName}' folder is already empty.";
        }
        return true;
    } else {
        $messages[] = "Error: Could not open the '{$folderName}' folder for purging. Check permissions.";
        return false; // Purge failed for this folder
    }
}

// Attempt to purge the private directory
if (!purgeDirectory($privateDirectory, 'private', $messages)) {
    $success = false;
}

// Redirect to the main page with a status and messages
if ($success) {
    header('Location: index.php?status=purged&messages=' . urlencode(json_encode($messages)));
    exit();
} else {
    header('Location: index.php?status=purge_failed&messages=' . urlencode(json_encode($messages)));
    exit();
}

ob_end_flush(); // Flush the output buffer
?>