<?php
ob_start(); // Démarre la mise en tampon de la sortie
require_once __DIR__ . '/auth.php';
require_auth();
// Charger la configuration (doit définir PRIVATE_DIRECTORY)
require_once __DIR__ . '/etc/config.php';

// Répertoire de base pour les transferts publics
$publicDirectory = __DIR__ . '/../public/transferts/';

// Répertoire pour les transferts privés — DOIT provenir de la configuration
if (!defined('PRIVATE_DIRECTORY') || trim(PRIVATE_DIRECTORY) === '') {
    header('Location: index.php?status=error&messages=' . urlencode(json_encode(['Erreur: PRIVATE_DIRECTORY non défini dans etc/config.php'])), true, 303);
    exit();
}

// Chemin absolu du dossier privé (PRIVATE_DIRECTORY est relatif au dossier CT/)
// Normalize en supprimant slashes finaux
$privateDirectory = __DIR__ . '/' . rtrim(PRIVATE_DIRECTORY, '/');

// Vérifier si des fichiers ont été envoyés via le formulaire
if (isset($_FILES['fichiers'])) {
    $nombreFichiers = count($_FILES['fichiers']['name']);
    $tousLesFichiersImportes = true;
    $erreurs = [];

    // Récupérer le type de dossier choisi par l'utilisateur (public ou private)
    $selectedFolderType = isset($_POST['typeFichier']) ? $_POST['typeFichier'] : '';

    // Déterminer le dossier de destination en fonction du choix de l'utilisateur
    $dossierDestination = '';
    if ($selectedFolderType === 'public') {
        $dossierDestination = $publicDirectory;
    } elseif ($selectedFolderType === 'private') {
        $dossierDestination = $privateDirectory;
    } else {
        // Gérer le cas où le type de dossier est invalide (par mesure de sécurité)
        $erreurs[] = "Erreur : Type de dossier de destination invalide.";
        $tousLesFichiersImportes = false;
    }

    // Si le dossier de destination est valide, procéder à l'importation
    if ($dossierDestination !== '' && $tousLesFichiersImportes) {
        // Créer le dossier de destination s'il n'existe pas
        if (!is_dir($dossierDestination)) {
            if (!mkdir($dossierDestination, 0755, true)) {
                $erreurs[] = "Erreur : Impossible de créer le dossier de destination : " . htmlspecialchars($dossierDestination);
                $tousLesFichiersImportes = false;
            }
        }

        if ($tousLesFichiersImportes) { // Continuer seulement si la création du dossier a réussi
            for ($i = 0; $i < $nombreFichiers; $i++) {
                $nomFichier = $_FILES['fichiers']['name'][$i];
                $fichierTemporaire = $_FILES['fichiers']['tmp_name'][$i];
                $erreurUpload = $_FILES['fichiers']['error'][$i]; // Code d'erreur d'upload

                // Vérifier s'il n'y a pas eu d'erreur d'upload côté client/serveur
                if ($erreurUpload === UPLOAD_ERR_OK) {
                    // Nettoyer le nom de fichier pour éviter les problèmes de sécurité (ex: path traversal)
                    $nomFichierSecurise = basename($nomFichier);
                    // Construire le chemin final en ajoutant le slash de séparation
                    $destinationFinale = $dossierDestination . '/' . $nomFichierSecurise;

                    // Déplacer le fichier téléchargé vers son emplacement final
                    if (move_uploaded_file($fichierTemporaire, $destinationFinale)) {
                        // Fichier importé avec succès
                    } else {
                        $erreurs[] = "Erreur : Le fichier \"" . htmlspecialchars($nomFichier) . "\" n'a pas pu être déplacé. Chemin cible: " . htmlspecialchars($destinationFinale) . ". Vérifiez les permissions du dossier.";
                        $tousLesFichiersImportes = false;
                    }
                } else {
                    // Gérer les erreurs d'upload PHP (taille max, etc.)
                    $errorMessage = "Erreur d'upload pour \"" . htmlspecialchars($nomFichier) . "\": ";
                    switch ($erreurUpload) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $errorMessage .= "Taille du fichier trop grande.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $errorMessage .= "Le fichier n'a été que partiellement téléchargé.";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $errorMessage .= "Aucun fichier n'a été téléchargé.";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $errorMessage .= "Dossier temporaire manquant.";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $errorMessage .= "Échec de l'écriture du fichier sur le disque.";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $errorMessage .= "Une extension PHP a arrêté l'upload du fichier.";
                            break;
                        default:
                            $errorMessage .= "Erreur inconnue.";
                            break;
                    }
                    $erreurs[] = $errorMessage;
                    $tousLesFichiersImportes = false;
                }
            }
        }
    }

    // Redirection après le traitement de tous les fichiers
    if ($tousLesFichiersImportes && empty($erreurs)) {
        // Redirection vers la page principale avec un message de succès
        header('Location: index.php?status=success');
        exit();
    } else {
        // S'il y a des erreurs, afficher les messages et ne pas rediriger immédiatement
        // Ou rediriger avec un paramètre d'erreur pour l'afficher sur index.php
        header('Location: index.php?status=error&messages=' . urlencode(json_encode($erreurs)));
        exit();
    }
} else {
    // Si aucun fichier n'a été sélectionné du tout
    header('Location: index.php?status=no_file_selected');
    exit();
}

ob_end_flush(); // Envoie la sortie mise en tampon
