<?php
// index.php

require_once 'etc/config.php';
require_once 'functions.php'; // Ce fichier contient maintenant toutes les fonctions utilitaires et de chiffrement/déchiffrement
require_once 'auth.php';
require_auth();

$conn = connectDB();

// ---------------------------------------------------
// 1. Récupération des données
// ---------------------------------------------------

// Récupère toutes les notices
$notices = [];
$sql_notices = "SELECT id, titre, lien, location FROM notices ORDER BY titre";
$result_notices = $conn->query($sql_notices);
if ($result_notices && $result_notices->num_rows > 0) {
    while ($row = $result_notices->fetch_assoc()) {
        $notices[] = $row;
    }
}

// Récupère toutes les entrées de l'annuaire
$annuaire = [];
$sql_annuaire = "SELECT id, titre, description, lien_image, lien_pc, lien_ios, lien_android, tags, location, login_id, password_id, note_id FROM annuaire ORDER BY titre";
$result_annuaire = $conn->query($sql_annuaire);
if ($result_annuaire && $result_annuaire->num_rows > 0) {
    while ($row = $result_annuaire->fetch_assoc()) {
        // DÉCHIFFRER LES DONNÉES DE L'ANNUAIRE ICI
        $row['login_id'] = decrypt_data($row['login_id']);
        $row['password_id'] = decrypt_data($row['password_id']);
        $row['note_id'] = decrypt_data($row['note_id']);
        $annuaire[] = $row;
    }
}

// Récupère tous les tags et lieux uniques pour les filtres à partir du tableau $annuaire
$annuaireTags = [];
$annuaireLocations = [];
foreach ($annuaire as $service) {
    if (!empty($service['tags'])) {
        foreach (explode(',', $service['tags']) as $tag) {
            $annuaireTags[] = trim($tag);
        }
    }
    if (!empty($service['location'])) {
        foreach (explode(',', $service['location']) as $loc) {
            $annuaireLocations[] = trim($loc);
        }
    }
}
$annuaireTags = array_unique(array_filter($annuaireTags));
sort($annuaireTags);
$annuaireLocations = array_unique(array_filter($annuaireLocations));
sort($annuaireLocations);

// Récupère les informations Wi-Fi
$wifi_credentials = [];
$sql_wifi = "SELECT id, location, ssid, password, qr_code_link FROM wifi_credentials ORDER BY location";
$result_wifi = $conn->query($sql_wifi);
if ($result_wifi && $result_wifi->num_rows > 0) {
    while ($row = $result_wifi->fetch_assoc()) {
        $wifi_credentials[] = $row;
    }
}

// Récupère le contenu du presse-papiers
// Récupère le contenu du presse-papiers
$contenu_texte = "";
$sql_clipboard = "SELECT texte FROM presse_papier";
$result_clipboard = $conn->query($sql_clipboard);
if ($result_clipboard && $result_clipboard->num_rows > 0) {
    $row = $result_clipboard->fetch_assoc();
    $contenu_texte = $row["texte"] ?? ''; // NE PAS DÉCHIFFRER ici
}

// Récupère la liste des fichiers privés
$privateFiles = [];
if (defined('PRIVATE_DIRECTORY') && is_dir(PRIVATE_DIRECTORY)) {
    $files = scandir(PRIVATE_DIRECTORY);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = PRIVATE_DIRECTORY . $file;
            if (is_file($filePath)) {
                $privateFiles[] = [
                    'name' => $file,
                    'folder' => 'transferts',
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath)
                ];
            }
        }
    }
}

$conn->close();

// --- Logique de filtrage pour l'annuaire (maintenue ici pour l'exemple mais peut être étendue) ---
$filtered_annuaire = $annuaire; // Par défaut, tous les services

$search_query = $_GET['search'] ?? '';
$location_filter = $_GET['location'] ?? '';
$tag_filter = $_GET['tag'] ?? '';

if (!empty($search_query) || !empty($location_filter) || !empty($tag_filter)) {
    $filtered_annuaire = array_filter($annuaire, function ($service) use ($search_query, $location_filter, $tag_filter) {
        $match = true;

        if (!empty($search_query)) {
            $search_query_lower = strtolower($search_query);
            $title_lower = strtolower($service['titre'] ?? '');
            $description_lower = strtolower($service['description'] ?? '');
            $tags_lower = strtolower($service['tags'] ?? '');

            $match = $match && (
                strpos($title_lower, $search_query_lower) !== false ||
                strpos($description_lower, $search_query_lower) !== false ||
                strpos($tags_lower, $search_query_lower) !== false
            );
        }

        if (!empty($location_filter)) {
            $locations = explode(',', $service['location'] ?? '');
            $locations = array_map('trim', $locations);
            $match = $match && in_array($location_filter, $locations);
        }

        if (!empty($tag_filter)) {
            $tags = explode(',', $service['tags'] ?? '');
            $tags = array_map('trim', $tags);
            $match = $match && in_array($tag_filter, $tags);
        }

        return $match;
    });
}


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="app.json" />
    <link rel="shortcut icon" href="/icones/favicon.ico">
    <link rel="icon" type="image/png" sizes="48x48" href="/icones/favicon_48.png">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">
    <title>CyberToolbox</title>
    <link rel="stylesheet" href="style.css?v=2.5">

    <style>
        .container {
            max-width: 1280px;
        }

        .tab-button {
            background-color: #e5e7eb;
            color: #4b5563;
        }

        .tab-button.active {
            background-color: #6366f1;
            color: #ffffff;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        #messageBox {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        #messageBox.show {
            opacity: 1;
            visibility: visible;
        }

        .app-block {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }

        .app-main-content {
            display: flex;
            align-items: center;
            flex-grow: 1;
            min-width: 0;
        }

        .app-icon {
            flex-shrink: 0;
            margin-right: 1.5rem;
        }

        .app-icon img {
            width: 4rem;
            height: 4rem;
            border-radius: 0.5rem;
        }

        .app-info {
            flex-grow: 1;
            min-width: 0;
        }

        .app-name {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .app-description {
            font-size: 0.875rem;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .open-button {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            transition: background-color 0.2s;
            flex-shrink: 0;
            margin-top: 1rem;
            width: 100%;
            text-align: center;
        }

        /* boutons de filtre de localisation — état par défaut */
        .location-filter-btn {
            background-color: #e5e7eb;
            /* gris clair */
            color: #374151;
            /* texte sombre */
            transition: background-color 0.2s, color 0.2s;
        }

        /* état actif */
        .location-filter-btn.active-location {
            background-color: #2563eb;
            /* bleu */
            color: #ffffff;
        }

        /* variante dark si nécessaire (si vous gérez un thème dark via body.dark ou autre) */
        body.dark .location-filter-btn {
            background-color: #374151;
            color: #e5e7eb;
        }

        body.dark .location-filter-btn.active-location {
            background-color: #1aa815ff;
            color: #ffffff;
        }

        @media (min-width: 640px) {
            .app-block {
                flex-wrap: nowrap;
            }

            .open-button {
                margin-top: 0;
                width: auto;
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4 dark:bg-gray-900">

    <div class="container bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-none p-4 sm:p-8 space-y-6 sm:space-y-8 max-w-screen-xl mx-auto">
        <h1 class="text-4xl font-bold text-center text-indigo-700 dark:text-indigo-400">CyberToolbox</h1>

        <div class="flex flex-wrap justify-center gap-2 mb-4">
            <button class="location-filter-btn bg-blue-600 dark:bg-blue-600 text-white dark:text-white py-2 px-4 rounded-full shadow-md hover:bg-blue-700 dark:hover:bg-blue-700 transition-colors duration-200" data-filter="all">Tout</button>
            <button class="location-filter-btn bg-gray-300 dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-2 px-4 rounded-full shadow-md hover:bg-gray-400 dark:hover:bg-gray-600 transition-colors duration-200" data-filter="toulouse">Toulouse</button>
            <button class="location-filter-btn bg-gray-300 dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-2 px-4 rounded-full shadow-md hover:bg-gray-400 dark:hover:bg-gray-600 transition-colors duration-200" data-filter="yssac">Yssac</button>
            <button class="location-filter-btn bg-gray-300 dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-2 px-4 rounded-full shadow-md hover:bg-gray-400 dark:hover:bg-gray-600 transition-colors duration-200" data-filter="laparrouquial">Laparrouquial</button>
            <button class="location-filter-btn bg-gray-300 dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-2 px-4 rounded-full shadow-md hover:bg-gray-400 dark:hover:bg-gray-600 transition-colors duration-200" data-filter="clermont">Clermont</button>
        </div>

        <div class="flex flex-wrap justify-center gap-2 p-2 bg-gray-200 dark:bg-gray-700 rounded-full">
            <button id="annuaire-tab" class="tab-button active flex-1 py-3 px-6 rounded-full text-sm font-medium transition-colors duration-200">Annuaire des services</button>
            <button id="notices-tab" class="tab-button flex-1 py-3 px-6 rounded-full text-sm font-medium transition-colors duration-200">Notices</button>
            <button id="wifi-tab" class="tab-button flex-1 py-3 px-6 rounded-full text-sm font-medium transition-colors duration-200">Wi-Fi</button>
            <button id="transferts-tab" class="tab-button flex-1 py-3 px-6 rounded-full text-sm font-medium transition-colors duration-200">Transferts</button>
            <button id="presse-papier-tab" class="tab-button flex-1 py-3 px-6 rounded-full text-sm font-medium transition-colors duration-200">Presse-papiers</button>
        </div>

        <div id="annuaire-content" class="content-section p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow-inner active">
            <div id="annuaire-filters" class="content-section p-4 bg-gray-100 dark:bg-gray-700 rounded-lg shadow-inner mb-6 active">
                <h2 class="text-xl font-semibold mb-2 text-gray-700 dark:text-gray-200">Filtrer par tag :</h2>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center space-x-2 text-gray-700 dark:text-gray-200">
                        <input type="checkbox" data-filter="all" class="filter-checkbox annuaire-filter-checkbox" checked>
                        <span>Tout</span>
                    </label>
                    <?php foreach ($annuaireTags as $tag): ?>
                        <label class="flex items-center space-x-2 text-gray-700 dark:text-gray-200">
                            <input type="checkbox" data-filter="<?= htmlspecialchars($tag) ?>" class="filter-checkbox annuaire-filter-checkbox">
                            <span><?= htmlspecialchars($tag) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600 dark:text-indigo-400">Services disponibles</h2>
            <?php if (empty($annuaire)): ?>
                <p class="text-gray-500 dark:text-gray-400">Aucun service trouvé dans l'annuaire.</p><?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($annuaire as $service_row):
                                                                                                            $serviceTags = explode(',', $service_row['tags']);
                                                                                                            $serviceLocation = explode(',', $service_row['location']);

                                                                                                            // Vérifie si au moins un des champs d'identifiants a une valeur après déchiffrement
                                                                                                            $has_credentials = !empty($service_row['login_id']) || !empty($service_row['password_id']) || !empty($service_row['note_id']);
                    ?>
                        <a href="<?= htmlspecialchars($service_row["lien_pc"]) ?>"
                            target="_blank"
                            class="app-block bg-gray-100 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 shadow-md dark:shadow-none 
           p-8 rounded-lg flex flex-col justify-between transform transition-all duration-700 hover:scale-105"
                            data-link-pc="<?= htmlspecialchars($service_row["lien_pc"]) ?>"
                            data-link-ios="<?= htmlspecialchars($service_row["lien_ios"]) ?>"
                            data-link-android="<?= htmlspecialchars($service_row["lien_android"]) ?>"
                            data-titre="<?= htmlspecialchars($service_row['titre']) ?>"
                            data-login-id="<?= htmlspecialchars($service_row['login_id']) ?>"
                            data-password-id="<?= htmlspecialchars($service_row['password_id']) ?>"
                            data-note-id="<?= htmlspecialchars($service_row['note_id']) ?>"
                            data-tags="<?= htmlspecialchars($service_row['tags']) ?>"
                            data-location="<?= htmlspecialchars($service_row['location']) ?>">

                            <div class="flex flex-col w-full">
                                <div class="app-main-content flex items-start mb-2 w-full">
                                    <div class="app-icon flex-shrink-0 mr-4 self-start">
                                        <?php if (!empty($service_row["lien_image"])): ?>
                                            <img src="<?= htmlspecialchars($service_row["lien_image"]) ?>" alt="<?= htmlspecialchars($service_row["titre"]) ?>"
                                                class="w-16 h-16 object-contain rounded-full bg-white dark:bg-gray-800">
                                        <?php else: ?>
                                            <svg class="w-16 h-16 text-gray-400 dark:text-gray-500 rounded-full border border-gray-200 dark:border-gray-600 p-1" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v-6h-2v6zm0-8h2V7h-2v2z"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="app-info text-left flex-grow w-0 self-start">
                                        <h3 class="app-name text-xl font-semibold text-gray-900 dark:text-white mt-0 mb-0"> <?= htmlspecialchars($service_row["titre"]) ?></h3>
                                        <p class="app-description text-gray-500 dark:text-gray-400 text-sm mt-0 mb-0"> <?= htmlspecialchars($service_row["description"]) ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs mt-1 w-full">
                                    <?php foreach ($serviceTags as $tag): ?>
                                        <?php if (!empty(trim($tag))): ?>
                                            <span class="inline-block bg-indigo-100 dark:bg-indigo-700 text-indigo-800 dark:text-indigo-200 px-2 py-0.5 rounded-full text-xs font-medium"><?= htmlspecialchars(ucfirst(trim($tag))) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php foreach ($serviceLocation as $ServiceLoc): ?>
                                        <?php if (!empty(trim($ServiceLoc))): ?>
                                            <span class="inline-block bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-200 px-2 py-0.5 rounded-full text-xs font-medium"><?= htmlspecialchars(ucfirst(trim($ServiceLoc))) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="app-buttons-container flex flex-col items-center justify-center w-full mt-4">
                                <?php if ($has_credentials): ?>
                                    <button type="button"
                                        class="open-credentials-modal-btn w-full bg-blue-600 dark:bg-blue-600 text-white dark:text-white hover:bg-blue-700 dark:hover:bg-blue-700 mt-2 flex items-center justify-center py-2 px-4 rounded-lg text-sm transition-colors duration-200"
                                        data-titre="<?= htmlspecialchars($service_row['titre']) ?>"
                                        data-login="<?= htmlspecialchars($service_row['login_id']) ?>"
                                        data-password="<?= htmlspecialchars($service_row['password_id']) ?>"
                                        data-note="<?= htmlspecialchars($service_row['note_id']) ?>">
                                        <img class="w-4 h-4 mr-2" src="icones/password_white.svg" alt="password_icon">
                                        Identifiants
                                    </button>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div><?php endif; ?>
        </div>

        <div id="credentialsModal" class="modal fixed inset-0 m-0 p-0 flex items-center justify-center z-[1000] hidden backdrop-blur-sm">
            <div class="modal-content bg-white dark:bg-gray-700 rounded-lg shadow-xl p-6 w-full max-w-md relative animate-fadeIn">
                <button class="close-button absolute top-3 right-3 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-3xl font-bold leading-none" id="closeModalBtn"><img src="icones/close.svg" alt="icone_close"></button>
                <h3 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-200" id="modalServiceTitle"></h3>
                <div class="space-y-4 text-left">
                    <p id="modalLogin" class="text-gray-700 dark:text-gray-300 flex items-center relative">
                        <strong>Login :</strong> <span class="ml-2 font-mono text-base break-all flex-grow" id="loginValue"></span>
                        <button class="copy-to-clipboard ml-auto p-1 bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500" data-target="loginValue">
                            <svg class="w-4 h-4 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </button>
                    </p>
                    <p id="modalPassword" class="text-gray-700 dark:text-gray-300 flex items-center relative">
                        <strong>Mot de passe :</strong> <span class="ml-2 font-mono text-base break-all flex-grow" id="passwordValue"></span>
                        <button class="copy-to-clipboard ml-auto p-1 bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500" data-target="passwordValue">
                            <svg class="w-4 h-4 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </button>
                    </p>
                    <div id="modalNoteContainer" class="hidden">
                        <p class="text-gray-700 dark:text-gray-300 font-semibold mt-4">Note :</p>
                        <p id="modalNote" class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words text-sm border-t border-gray-200 dark:border-gray-600 pt-2 mt-2"></p>
                    </div>
                </div>
                <div class="mt-6">
                    <a href="#" id="modalOpenServiceBtn" target="_blank" rel="noopener noreferrer"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm text-center inline-flex items-center justify-center transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <img class="w-4 h-4 mr-2" src="icones/open_in_new_white.svg" alt="icone_open_in_new">
                        Ouvrir le service
                    </a>
                    <br><br>
                    <a href="#" id="modalOpenAppBtn" target="_blank" rel="noopener noreferrer"
                        class="hidden w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm text-center items-center justify-center transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <img class="w-4 h-4 mr-2" src="icones/open_in_new_white.svg" alt="icone_open_in_new">
                        Ouvrir l'application
                    </a>
                </div>
            </div>
        </div>

        <div id="notices-content" class="content-section p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow-inner">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600 dark:text-indigo-400">Poser une question à une IA</h2>
            <p class="mb-6 text-gray-700 dark:text-gray-300 text-base">Cliquez <a href="https://notebooklm.google.com/notebook/" target="_blank" class="text-blue-600 dark:text-blue-400 underline hover:text-blue-800 dark:hover:text-blue-300">ici</a> pour poser une question technique ou pratique à Notebook LM. Attention, une IA peut se tromper et le corpus de notices qui lui est soumis peut ne pas être parfaitement à jour. Vous devrez peut-être vous connecter à votre compte Google.</p>
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600 dark:text-indigo-400">Notices des appareils</h2>
            <div id="notices-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (!empty($notices)): ?>
                    <?php foreach ($notices as $notice): ?>
                        <div class='notice-item flex flex-col sm:flex-row items-start justify-start gap-4 p-3 mb-2 bg-gray-100 dark:bg-gray-700 rounded-lg shadow-sm hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200' data-location='<?= htmlspecialchars($notice['location']) ?>'>
                            <div class='flex-1 text-left mb-2 sm:mb-0 min-w-0'>
                                <a href="download.php?dir=notices&f=<?= urlencode($notice['lien']) ?>" class='text-blue-600 dark:text-blue-400 hover:underline font-medium break-all'><?= htmlspecialchars($notice['titre']) ?></a>
                                <div class='text-sm text-gray-500 dark:text-gray-400 mt-1'>
                                    Lieu(x):
                                    <?php
                                    $locations = explode(',', $notice['location']);
                                    foreach ($locations as $loc) {
                                        echo '<span class="inline-block bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 text-xs px-2 rounded-full mr-1">' . htmlspecialchars(ucfirst(trim($loc))) . '</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class='flex items-center gap-2 mt-2 sm:mt-0'>
                                <a href="download.php?dir=notices&f=<?= urlencode($notice['lien']) ?>" class='flex items-center text-gray-600 dark:text-gray-400 hover:text-blue-700 dark:hover:text-blue-500 transition-colors duration-200' title='Télécharger'>
                                    <img src="icones/download.svg" alt="icone download">
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class='text-gray-500 dark:text-gray-400 italic text-center'>Aucune notice disponible pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <div id="wifi-content" class="content-section p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow-inner text-center">
            <h2 class="text-2xl font-semibold mb-6 text-indigo-600 dark:text-indigo-400">Informations Wi-Fi</h2>
            <?php
            if (!empty($wifi_credentials)) {
                $uniqueLocations = [];
                foreach ($wifi_credentials as $credential) {
                    $uniqueLocations[$credential['location']][] = $credential;
                }
                foreach ($uniqueLocations as $location => $networks) {
                    $location = htmlspecialchars($location);
            ?>
                    <div id="wifi-section-<?= $location ?>" class="wifi-section mb-6" data-location="<?= $location ?>">
                        <h3 class="text-2xl font-semibold text-indigo-600 dark:text-indigo-400 mb-4"><?= ucfirst($location) ?></h3>
                        <?php foreach ($networks as $network_row): ?>
                            <div class="mb-6 p-4 bg-white dark:bg-gray-700 rounded-lg shadow-sm dark:shadow-none">
                                <p class="text-lg font-bold text-gray-800 dark:text-gray-200">Nom du réseau (SSID) : <span class="font-normal text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($network_row["ssid"]) ?></span></p>
                                <p class="text-lg font-bold text-gray-800 dark:text-gray-200 mt-2">Mot de passe : <span class="font-normal text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($network_row["password"]) ?></span></p>
                                <h4 class="text-xl font-semibold text-indigo-600 dark:text-indigo-400 mb-4 mt-4">Scannez pour vous connecter</h4>
                                <img class="mx-auto border border-gray-300 dark:border-gray-600 rounded-lg p-2 max-w-full h-auto" src="download.php?dir=qr&f=<?= urlencode(basename($network_row['qr_code_link'])) ?>" alt="QR Code pour le Wi-Fi">
                            </div>
                        <?php endforeach; ?>
                    </div>
            <?php
                }
            } else {
                echo "<p class='text-gray-500 dark:text-gray-400 italic text-center'>Aucune information Wi-Fi disponible pour le moment.</p>";
            }
            ?>
        </div>

        <div id="transferts-content" class="content-section p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow-inner">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600 dark:text-indigo-400">Fichiers Transmis</h2>
            <div class="file-list mb-6 max-h-96 overflow-y-auto p-2 rounded-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600">
                <?php if (!empty($privateFiles)): ?>
                    <?php foreach ($privateFiles as $file):
                        $fullFilePathForAction = PRIVATE_DIRECTORY . $file['name'];
                    ?>
                        <div class='flex flex-col sm:flex-row items-center justify-between p-3 mb-2 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-sm hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors duration-200'>
                            <div class='flex-1 text-left mb-2 sm:mb-0'>
                                <a href="download.php?f=<?= urlencode($file['name']) ?>" class='text-blue-600 dark:text-blue-400 hover:underline font-medium break-all'><?= htmlspecialchars($file['name']) ?></a>
                                <p class='text-sm text-gray-500 dark:text-gray-400'>Dossier: <span class='font-semibold'>transferts</span> | Taille: <?= formatBytes($file['size']) ?> | Modifié: <?= date("d/m/Y H:i", $file['modified']) ?></p>
                            </div>
                            <div class='flex items-center gap-2'>
                                <a href="download.php?f=<?= urlencode($file['name']) ?>" class='flex items-center text-gray-600 dark:text-gray-400 hover:text-blue-700 dark:hover:text-blue-500 transition-colors duration-200' title='Télécharger'>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                        <polyline points="7 10 12 15 17 10" />
                                        <line x1="12" x2="12" y1="15" y2="3" />
                                    </svg>
                                </a>
                                <form action='delete_file.php' method='post'>
                                    <input type='hidden' name='filename' value='<?= htmlspecialchars($file['name']) ?>'>
                                    <button type='submit' class='flex items-center text-red-600 dark:text-red-500 hover:text-red-800 dark:hover:text-red-600 transition-colors duration-200' title='Supprimer'>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2">
                                            <path d="M3 6h18" />
                                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                                            <line x1="10" x2="10" y1="11" y2="17" />
                                            <line x1="14" x2="14" y1="11" y2="17" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class='text-gray-500 dark:text-gray-400 italic text-center'>Aucun fichier n'a été transmis pour le moment.</p>
                <?php endif; ?>
            </div>
            <div class="text-center mt-6">
                <a href="purge.php" class="inline-block bg-red-600 dark:bg-red-500 text-white dark:text-white font-bold py-2 px-6 rounded-lg shadow-md hover:bg-red-700 dark:hover:bg-red-600 transition-all duration-300 transform hover:scale-105">Vider la liste</a>
            </div>
            <h2 class="text-2xl font-semibold mt-8 mb-4 text-indigo-600 dark:text-indigo-400">Importer un Fichier</h2>
            <form action="importer_fichiers.php" method="post" enctype="multipart/form-data" class="space-y-6">
                <input hidden type="radio" id="typePrivate" name="typeFichier" value="private" class="form-radio text-green-600 dark:text-green-500 h-5 w-5" checked>
                <div class="flex flex-col items-center">
                    <input type="file" id="fichiers" name="fichiers[]" multiple required class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 dark:file:bg-blue-600 file:text-blue-700 dark:file:text-white hover:file:bg-blue-100 dark:hover:file:bg-blue-700 cursor-pointer">
                </div>
                <input type="submit" value="Envoyer" class="w-full bg-blue-600 dark:bg-blue-600 text-white dark:text-white font-bold py-3 px-8 rounded-lg shadow-md hover:bg-blue-700 dark:hover:bg-blue-700 transition-all duration-300 transform hover:scale-105 cursor-pointer">
            </form>
        </div>

        <div id="presse-papier-content" class="content-section p-6 bg-gray-50 dark:bg-gray-800 rounded-lg shadow-inner">
            <h2 class="text-2xl font-semibold mb-4 text-indigo-600 dark:text-indigo-400">Presse-papiers</h2>
            <div id="dialog-presse-papier" class="p-8">
                <div class="flex flex-wrap justify-center gap-2 mb-4">
                    <button id="copyButton" class="bg-blue-500 dark:bg-blue-600 text-white dark:text-white py-2 px-4 rounded-lg hover:bg-blue-600 dark:hover:bg-blue-700 transition-colors">Copier</button>
                    <button id="pasteButton" class="bg-gray-400 dark:bg-gray-600 text-white dark:text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-500 dark:hover:bg-gray-500 transition-colors">Coller</button>
                    <button id="refreshButton" class="bg-gray-300 dark:bg-gray-700 text-gray-800 dark:text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition-colors">Rafraîchir</button>
                    <button id="eraseButton" class="bg-red-500 dark:bg-red-600 text-white dark:text-white py-2 px-4 rounded-lg hover:bg-red-600 dark:hover:bg-red-700 transition-colors">Vider</button>
                </div>
                <form action="edit.php" method="post" class="flex flex-col items-center gap-4">
                    <textarea id="myTextarea" name="new_text" rows="4" cols="80" class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 dark:focus:border-blue-400" autocomplete="off"><?= htmlspecialchars($contenu_texte) ?></textarea>
                    <input type="submit" value="Enregistrer" class="w-full bg-blue-600 dark:bg-blue-600 text-white dark:text-white font-bold py-3 px-8 rounded-lg shadow-md hover:bg-blue-700 dark:hover:bg-blue-700 transition-all duration-300 transform hover:scale-105 cursor-pointer">
                </form>
            </div>
        </div>
    </div>


    <div id="messageBox"></div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('.tab-button');
            const contentSections = document.querySelectorAll('.content-section');
            const locationFilterButtons = document.querySelectorAll('.location-filter-btn');
            const noticeItems = document.querySelectorAll('.notice-item');
            const wifiSections = document.querySelectorAll('.wifi-section');
            const annuaireFilterCheckboxes = document.querySelectorAll('.annuaire-filter-checkbox');
            const annuaireItems = document.querySelectorAll('.app-block'); // Ces sont maintenant des <a>

            const copyButton = document.getElementById('copyButton');
            const pasteButton = document.getElementById('pasteButton');
            const refreshButton = document.getElementById('refreshButton');
            const eraseButton = document.getElementById('eraseButton');
            const myTextarea = document.getElementById('myTextarea');
            const messageBox = document.getElementById('messageBox'); // Votre messageBox existante

            // Variables pour stocker les filtres actifs
            let activeLocationFilter = localStorage.getItem('locationFilter') || 'all'; // Default to 'all'
            let activeTagFilters = []; // Va stocker les tags actifs pour l'annuaire

            // --- Fonctions utilitaires ---

            function showMessage(message) {
                messageBox.textContent = message;
                messageBox.classList.add('show');
                setTimeout(() => messageBox.classList.remove('show'), 3000);
            }

            function getLinkForDevice(pcLink, iosLink, androidLink) {
                const userAgent = navigator.userAgent || navigator.vendor || window.opera;
                if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
                    return iosLink || pcLink;
                }
                if (/android/i.test(userAgent)) {
                    return androidLink || pcLink;
                }
                return pcLink;
            }

            function showTab(targetId) {
                tabButtons.forEach(btn => btn.classList.remove('active'));
                document.getElementById(targetId.replace('-content', '-tab')).classList.add('active');
                contentSections.forEach(section => {
                    section.classList.toggle('active', section.id === targetId);
                });
                const annuaireFilters = document.getElementById('annuaire-filters');
                if (targetId === 'annuaire-content') {
                    annuaireFilters.style.display = 'block';
                } else {
                    annuaireFilters.style.display = 'none';
                }
                localStorage.setItem('activeTab', targetId);
                applyAllFilters();
            }

            // --- Logique de filtrage principale ---
            function applyAllFilters() {
                // Mise à jour de l'état visuel des boutons de localisation
                locationFilterButtons.forEach(btn => {
                    const filterValue = btn.getAttribute('data-filter').toLowerCase();
                    const isActive = (filterValue === activeLocationFilter);
                    // gestion CSS via une seule classe
                    btn.classList.toggle('active-location', isActive);
                    // accessibilité
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                // --- Calcul de activeTagFilters ---
                activeTagFilters = Array.from(annuaireFilterCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.getAttribute('data-filter').toLowerCase());

                if (activeTagFilters.includes('all')) {
                    activeTagFilters = ['all'];
                }

                // Filtrage des notices
                noticeItems.forEach(item => {
                    const locationsString = item.getAttribute('data-location').toLowerCase();
                    const locations = locationsString.split(',').map(loc => loc.trim());
                    const isVisible = activeLocationFilter === 'all' || locations.includes(activeLocationFilter);
                    item.style.display = isVisible ? 'flex' : 'none';
                });

                // Filtrage des sections Wi-Fi
                wifiSections.forEach(section => {
                    const sectionLocation = section.getAttribute('data-location').toLowerCase();
                    section.style.display = (activeLocationFilter === 'all' || sectionLocation === activeLocationFilter) ? 'block' : 'none';
                });

                // Filtrage de l'Annuaire (par Lieu ET par Tags)
                annuaireItems.forEach(item => { // app-block est un <a>
                    const itemLocationsString = item.getAttribute('data-location').toLowerCase();
                    const itemLocations = itemLocationsString.split(',').map(loc => loc.trim());
                    const itemTagsString = item.getAttribute('data-tags').toLowerCase();
                    const itemTags = itemTagsString.split(',').map(tag => tag.trim());

                    // 1. Vérification du filtre de lieu
                    const isVisibleByLocation = activeLocationFilter === 'all' ||
                        itemLocations.includes(activeLocationFilter) ||
                        itemLocations.includes('all');

                    // 2. Vérification du filtre par tag
                    let isVisibleByTag = false;
                    if (activeTagFilters.includes('all') || activeTagFilters.length === 0) {
                        isVisibleByTag = true;
                    } else {
                        for (let filterTag of activeTagFilters) {
                            if (itemTags.includes(filterTag)) {
                                isVisibleByTag = true;
                                break;
                            }
                        }
                    }

                    // L'élément est visible si les deux conditions (lieu ET tag) sont remplies
                    item.style.display = (isVisibleByLocation && isVisibleByTag) ? 'flex' : 'none';
                });
            }

            // --- Événements ---

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetId = button.id.replace('-tab', '-content');
                    showTab(targetId);
                });
            });

            locationFilterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    activeLocationFilter = this.getAttribute('data-filter').toLowerCase();
                    localStorage.setItem('locationFilter', activeLocationFilter);
                    applyAllFilters();
                });
            });

            // --- GESTION DES FILTRES DE TAGS POUR L'ANNUAIRE ---
            annuaireFilterCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', (event) => {
                    const filterType = checkbox.getAttribute('data-filter');
                    const allCheckbox = document.querySelector('.annuaire-filter-checkbox[data-filter="all"]');

                    // 1. Gère l'exclusivité de 'Tout'
                    if (filterType === 'all' && checkbox.checked) {
                        annuaireFilterCheckboxes.forEach(cb => {
                            if (cb !== allCheckbox) {
                                cb.checked = false;
                            }
                        });
                    } else if (filterType !== 'all' && checkbox.checked) {
                        allCheckbox.checked = false;
                    }

                    // 2. Assure qu'au moins un filtre (ou 'Tout') est actif si rien n'est coché
                    const anyOtherChecked = Array.from(annuaireFilterCheckboxes).some(cb => cb.checked && cb.getAttribute('data-filter') !== 'all');
                    if (!anyOtherChecked && !allCheckbox.checked) {
                        allCheckbox.checked = true;
                    }

                    const currentTagFilters = Array.from(annuaireFilterCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.getAttribute('data-filter').toLowerCase());
                    localStorage.setItem('annuaireTagFilters', JSON.stringify(currentTagFilters));

                    applyAllFilters();
                });
            });

            // --- LOGIQUE POUR LA MODALE DES IDENTIFIANTS (Mise à jour) ---

            const credentialsModal = document.getElementById('credentialsModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const modalServiceTitle = document.getElementById('modalServiceTitle');
            const loginValueSpan = document.getElementById('loginValue');
            const passwordValueSpan = document.getElementById('passwordValue');
            const modalNoteContainer = document.getElementById('modalNoteContainer');
            const modalNote = document.getElementById('modalNote');
            const modalOpenServiceBtn = document.getElementById('modalOpenServiceBtn');
            const modalOpenAppBtn = document.getElementById('modalOpenAppBtn');

            // Écouteur pour tous les boutons d'ouverture de la modale "Identifiants"
            document.querySelectorAll('.open-credentials-modal-btn').forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault(); // Empêche le comportement par défaut (ouverture du lien <a> parent)
                    event.stopPropagation(); // Empêche l'événement de remonter au <a>.app-block parent

                    const appBlock = this.closest('.app-block'); // Récupère le lien <a> parent
                    if (appBlock) {
                        modalServiceTitle.textContent = appBlock.dataset.titre;

                        // Assurez-vous que les attributs de données sont corrects (data-login-id etc.)
                        const loginId = appBlock.dataset.loginId || '';
                        const passwordId = appBlock.dataset.passwordId || '';
                        const noteId = appBlock.dataset.noteId || '';

                        loginValueSpan.textContent = loginId;
                        passwordValueSpan.textContent = passwordId;

                        if (noteId && noteId !== '') {
                            modalNote.textContent = noteId;
                            modalNoteContainer.classList.remove('hidden');
                        } else {
                            modalNote.textContent = '';
                            modalNoteContainer.classList.add('hidden');
                        }

                        // Configure le lien du bouton "Ouvrir le service" dans la modale
                        const pcLink = appBlock.getAttribute('data-link-pc');
                        const iosLink = appBlock.getAttribute('data-link-ios');
                        const androidLink = appBlock.getAttribute('data-link-android');
                        modalOpenServiceBtn.href = pcLink;
                        modalOpenAppBtn.href = getLinkForDevice(pcLink, iosLink, androidLink);

                        // Afficher le bouton "Ouvrir l'application" uniquement si constlink != lien PC
                        if (typeof constlink !== 'undefined') {
                            if (constlink !== pcLink) {
                                modalOpenAppBtn.classList.remove('hidden');
                                modalOpenAppBtn.classList.add('flex');
                            } else {
                                modalOpenAppBtn.classList.add('hidden');
                                modalOpenAppBtn.classList.remove('flex');
                            }
                        } else {
                            // Fallback : montrer si le lien calculé pour l'appareil est différent du lien PC
                            if (modalOpenAppBtn.href && modalOpenAppBtn.href !== pcLink) {
                                modalOpenAppBtn.classList.remove('hidden');
                                modalOpenAppBtn.classList.add('flex');
                            } else {
                                modalOpenAppBtn.classList.add('hidden');
                                modalOpenAppBtn.classList.remove('flex');
                            }
                        }

                        credentialsModal.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    }
                });
            });

            // Fermer la modale avec le bouton 'x'
            closeModalBtn.addEventListener('click', () => {
                credentialsModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            });

            // Fermer la modale en cliquant en dehors du contenu
            credentialsModal.addEventListener('click', (event) => {
                if (event.target === credentialsModal) {
                    credentialsModal.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                }
            });

            // Logique pour copier le login/password
            document.querySelectorAll('.copy-to-clipboard').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const textToCopy = document.getElementById(targetId).textContent;
                    if (navigator.clipboard && textToCopy) {
                        navigator.clipboard.writeText(textToCopy)
                            .then(() => showMessage('Copié !'))
                            .catch(err => console.error('Erreur de copie:', err));
                    } else {
                        showMessage('Impossible de copier, veuillez le faire manuellement.');
                    }
                });
            });


            // --- Initialisation au chargement de la page ---

            // Appliquer les liens spécifiques à l'appareil pour les liens .app-block (boutons "Ouvrir")
            annuaireItems.forEach(link => { // annuaireItems est déjà un NodeList de <a>.app-block
                const pcLink = link.getAttribute('data-link-pc');
                const iosLink = link.getAttribute('data-link-ios');
                const androidLink = link.getAttribute('data-link-android');
                const finalLink = getLinkForDevice(pcLink, iosLink, androidLink);
                link.href = finalLink; // Met à jour le href du lien <a>.app-block
            });


            // Charger l'onglet actif sauvegardé
            const savedTab = localStorage.getItem('activeTab');
            if (savedTab) {
                showTab(savedTab);
            } else {
                showTab('annuaire-content');
            }

            // Charger l'état des checkboxes pour les tags
            const savedTagFilters = JSON.parse(localStorage.getItem('annuaireTagFilters')) || ['all'];
            annuaireFilterCheckboxes.forEach(cb => {
                const filterValue = cb.getAttribute('data-filter').toLowerCase();
                cb.checked = savedTagFilters.includes(filterValue);
            });

            // Si aucun tag n'est coché après restauration, on force 'Tout'
            const anyChecked = Array.from(annuaireFilterCheckboxes).some(cb => cb.checked);
            if (!anyChecked) {
                document.querySelector('.annuaire-filter-checkbox[data-filter="all"]').checked = true;
            }

            // Appliquer tous les filtres une fois les états initiaux chargés
            applyAllFilters();


            // Sauvegarde de l'état des filtres de tags avant de quitter la page
            window.addEventListener('beforeunload', () => {
                const currentTagFilters = Array.from(annuaireFilterCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.getAttribute('data-filter').toLowerCase());
                localStorage.setItem('annuaireTagFilters', JSON.stringify(currentTagFilters));
            });

            // --- Fonctionnalités Presse-papiers (inchangées) ---
            copyButton.addEventListener('click', () => {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(myTextarea.value)
                        .then(() => showMessage('Copié !'))
                        .catch(err => console.error('Erreur de copie:', err));
                } else {
                    myTextarea.select();
                    document.execCommand('copy');
                    showMessage('Copié !');
                }
            });

            pasteButton.addEventListener('click', async () => {
                try {
                    const text = await navigator.clipboard.readText();
                    myTextarea.value = text;
                    showMessage('Collé !');
                } catch (err) {
                    showMessage('Impossible d\'accéder au presse-papiers, veuillez le faire manuellement.');
                }
            });

            refreshButton.addEventListener('click', () => {
                fetch('get_clipboard_text.php')
                    .then(response => response.ok ? response.text() : Promise.reject('Erreur réseau'))
                    .then(text => {
                        myTextarea.value = text.trim();
                        showMessage('Rafraîchi !');
                    })
                    .catch(error => {
                        console.error('Erreur lors du rafraîchissement :', error);
                        showMessage('Erreur de rafraîchissement.');
                    });
            });

            eraseButton.addEventListener('click', () => {
                myTextarea.value = '';
                showMessage('Contenu effacé !');
            });
            /*
                    // =========================================================
                    // === INTÉGRATION DU SERVICE WORKER (PWA) ===
                    // =========================================================
                    if ('serviceWorker' in navigator) {
                        // L'enregistrement est effectué dans l'événement 'load' pour ne pas bloquer le chargement initial de la page
                        window.addEventListener('load', () => {
                            // Le chemin '/sw.js' est relatif à la racine de ton site.
                            navigator.serviceWorker.register('/sw.js')
                                .then((registration) => {
                                    console.log('Service Worker enregistré avec succès. Scope:', registration.scope);
                                })
                                .catch((error) => {
                                    console.error('Échec de l\'enregistrement du Service Worker:', error);
                                });
                        });
                    } else {
                        console.log('Service Workers non supportés par ce navigateur.');
                    }
                    // =========================================================
            */
        });
    </script>
</body>

</html>
