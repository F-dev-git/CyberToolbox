<?php
// functions.php

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
    $conn->set_charset("utf8mb4");

    return $conn;
}

/**
 * Formats a file size in a human-readable format.
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Retrieves files from a specified directory.
 * @param string $dirPath
 * @param string $folderType
 * @return array
 */
function getFilesFromDirectory($dirPath, $folderType) {
    $dirFiles = [];
    if (is_dir($dirPath) && ($dir = opendir($dirPath))) {
        while (($entry = readdir($dir)) !== false) {
            $filePath = $dirPath . $entry;
            if (is_file($filePath) && $entry !== "." && $entry !== "..") {
                $dirFiles[] = [
                    'name' => $entry,
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath),
                    'folder' => $folderType,
                    'path' => $filePath
                ];
            }
        }
        closedir($dir);
    }
    return $dirFiles;
}

/**
 * Utility function to pass parameters by reference for mysqli_stmt_bind_param.
 * Required for PHP 5.3+ when using call_user_func_array with bind_param.
 * @param array $arr
 * @return array
 */
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) //PHP 5.3+
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

/**
 * Encrypts data using AES-256-CBC.
 *
 * @param string|null $data The data to encrypt.
 * @return string|null The base64 encoded IV and encrypted data, or null if input is null/empty.
 */
function encrypt_data($data) {
    // Si les données sont nulles ou vides, retourne null pour stocker NULL en BDD
    if (empty($data)) {
        return null;
    }
    // Vérifie si les constantes de chiffrement sont définies
    if (!defined('ENCRYPTION_KEY') || !defined('ENCRYPTION_CIPHER') || !defined('ENCRYPTION_IV_LENGTH')) {
        error_log("Constantes de chiffrement non définies dans config.php !");
        return null; // ou jeter une exception
    }

    $iv = openssl_random_pseudo_bytes(ENCRYPTION_IV_LENGTH);
    $encrypted = openssl_encrypt($data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    if ($encrypted === false) {
        error_log("Erreur de chiffrement : " . openssl_error_string());
        return null;
    }
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypts data using AES-256-CBC.
 *
 * @param string|null $encrypted_data The base64 encoded IV and encrypted data.
 * @return string|null The decrypted data, or an empty string/null on error or if input is null/empty.
 */
function decrypt_data($encrypted_data) {
    // Si les données sont nulles ou vides, retourne une chaîne vide
    if (empty($encrypted_data)) {
        return '';
    }
    // Vérifie si les constantes de chiffrement sont définies
    if (!defined('ENCRYPTION_KEY') || !defined('ENCRYPTION_CIPHER') || !defined('ENCRYPTION_IV_LENGTH')) {
        error_log("Constantes de chiffrement non définies dans config.php !");
        return ''; // ou jeter une exception
    }

    $decoded = base64_decode($encrypted_data);
    if ($decoded === false || strlen($decoded) < ENCRYPTION_IV_LENGTH) {
        error_log("Erreur de décodage ou IV manquant.");
        return '';
    }
    $iv = substr($decoded, 0, ENCRYPTION_IV_LENGTH);
    $encrypted = substr($decoded, ENCRYPTION_IV_LENGTH);
    $decrypted = openssl_decrypt($encrypted, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    if ($decrypted === false) {
        error_log("Erreur de déchiffrement : " . openssl_error_string());
        return '';
    }
    return $decrypted;
}

?>