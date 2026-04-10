<?php
// gestion_notices.php
require_once __DIR__ . '/auth.php';
require_auth();
require_once __DIR__ . '/etc/config.php';
require_once __DIR__ . '/functions.php';

// Définit le chemin du dossier de notices.
$notices_folder_path = './' . (defined('NOTICES_DIRECTORY') ? NOTICES_DIRECTORY : 'notices/');

// ---------------------------------------------------
// 1. Logique de traitement des formulaires (ajout, modification, suppression)
// ---------------------------------------------------

$conn = connectDB();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Traitement de l'ajout d'une notice
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $titre = htmlspecialchars($_POST['titre']);
        $location = htmlspecialchars($_POST['location']);
        $lien = htmlspecialchars($_POST['lien']);

        if (!empty($titre) && !empty($location) && !empty($lien)) {
            $stmt = $conn->prepare("INSERT INTO notices (titre, location, lien) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $titre, $location, $lien);
            $stmt->execute();
            $stmt->close();
            header("Location: gestion_notices.php?message=add_success");
            exit();
        }
    }

    // Traitement de la modification d'une notice
    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = intval($_POST['id']);
        $titre = htmlspecialchars($_POST['titre']);
        $location = htmlspecialchars($_POST['location']);

        if ($id > 0 && !empty($titre) && !empty($location)) {
            $stmt = $conn->prepare("UPDATE notices SET titre = ?, location = ? WHERE id = ?");
            $stmt->bind_param("ssi", $titre, $location, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: gestion_notices.php?message=edit_success");
            exit();
        }
    }

    // Traitement de la suppression d'une notice de la BDD (maintenant en POST)
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = intval($_POST['id']);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM notices WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            header("Location: gestion_notices.php?message=delete_success");
            exit();
        }
    }

    // Traitement de la suppression d'un fichier local (nouveau)
    if (isset($_POST['action']) && $_POST['action'] == 'delete_local_file') {
        $file_name = basename($_POST['file'] ?? '');
        $base = realpath(__DIR__ . '/' . rtrim($notices_folder_path, '/'));
        $file_path = $base ? realpath($base . DIRECTORY_SEPARATOR . $file_name) : false;
        if ($file_path && $base && strpos($file_path, $base) === 0 && file_exists($file_path)) {
            unlink($file_path);
            header("Location: gestion_notices.php?message=file_delete_success");
            exit();
        }
    }
}

// ---------------------------------------------------
// 2. Récupération et comparaison des données pour l'affichage
// ---------------------------------------------------

// Récupère toutes les notices de la base de données
$registered_notices = [];
$sql = "SELECT id, titre, lien, location FROM notices ORDER BY titre";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $registered_notices[] = $row;
    }
}

// Récupère la liste des fichiers sur le serveur
$server_files = [];
if (is_dir($notices_folder_path)) {
    $files = glob($notices_folder_path . '*.pdf');
    foreach ($files as $file) {
        $server_files[] = basename($file);
    }
}

// Compare les deux listes
$registered_file_names = array_column($registered_notices, 'lien');
$unregistered_files = array_diff($server_files, $registered_file_names);

// Trouve les notices dans la BDD mais pas sur le serveur (notices orphelines)
$orphaned_notices_liens = array_diff($registered_file_names, $server_files);
$orphaned_notices = array_filter($registered_notices, function($notice) use ($orphaned_notices_liens) {
    return in_array($notice['lien'], $orphaned_notices_liens);
});

$conn->close();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Notices</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .container { max-width: 1024px; }
    </style>
</head>
<body class="bg-gray-100 p-8">

    <div class="container mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-3xl font-bold mb-6 text-indigo-700">Gestion des notices</h1>

        <?php if (isset($_GET['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">
                    <?php 
                        switch($_GET['message']) {
                            case 'add_success': echo "Notice ajoutée avec succès !"; break;
                            case 'edit_success': echo "Notice modifiée avec succès !"; break;
                            case 'delete_success': echo "Notice supprimée avec succès !"; break;
                            case 'file_delete_success': echo "Fichier local supprimé avec succès !"; break;
                        }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-gray-700">Notices enregistrées (<?= count($registered_notices) ?>)</h2>
            <?php if (empty($registered_notices)): ?>
                <p class="text-gray-500 italic">Aucune notice n'est actuellement enregistrée dans la base de données.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($registered_notices as $notice): ?>
                        <div class="bg-gray-50 p-4 rounded-lg shadow-sm flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-lg font-bold"><?= htmlspecialchars($notice['titre']) ?></p>
                                <p class="text-sm text-gray-600">
                                    Lieu(x) : 
                                    <?php
                                    // Divise la chaîne par les virgules et les espaces, puis affiche chaque lieu
                                    $locations = explode(',', $notice['location']);
                                    foreach ($locations as $loc) {
                                        echo htmlspecialchars(ucfirst(trim($loc))) . ' ';
                                    }
                                    ?>
                                    | Fichier : <a href="download.php?dir=notices&f=<?= urlencode($notice['lien']) ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($notice['lien']) ?></a>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="btn-edit bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600" data-id="<?= $notice['id'] ?>">Modifier</button>
                                <form action="gestion_notices.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette notice de la base de données ? Le fichier ne sera pas effacé.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $notice['id'] ?>">
                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Supprimer</button>
                                </form>
                            </div>
                        </div>
                        <form class="form-edit p-4 border border-gray-200 rounded-lg hidden" id="form-edit-<?= $notice['id'] ?>" action="gestion_notices.php" method="POST">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $notice['id'] ?>">
                            <div class="mb-2">
                                <label class="block text-gray-700">Titre</label>
                                <input type="text" name="titre" value="<?= htmlspecialchars($notice['titre']) ?>" class="w-full mt-1 p-2 border rounded">
                            </div>
                            <div class="mb-2">
                                <label class="block text-gray-700">Lieu(x) (séparés par une virgule)</label>
                                <input type="text" name="location" value="<?= htmlspecialchars($notice['location']) ?>" class="w-full mt-1 p-2 border rounded">
                            </div>
                            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Enregistrer</button>
                            <button type="button" class="btn-cancel bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600" data-id="<?= $notice['id'] ?>">Annuler</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-gray-700">Notices à ajouter (<?= count($unregistered_files) ?>)</h2>
            <?php if (empty($unregistered_files)): ?>
                <p class="text-gray-500 italic">Tous les fichiers du dossier "<?= htmlspecialchars($notices_folder_path) ?>" sont déjà enregistrés.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($unregistered_files as $file): ?>
                        <div class="bg-yellow-50 p-4 rounded-lg shadow-sm flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-lg font-bold">Fichier : <?= htmlspecialchars($file) ?></p>
                                <div class="flex items-end gap-2 mt-2">
                                    <form action="gestion_notices.php" method="POST">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="lien" value="<?= htmlspecialchars($file) ?>">
                                        <div class="flex gap-2 items-end">
                                            <div>
                                                <label class="block text-sm text-gray-600">Titre</label>
                                                <input type="text" name="titre" value="<?= htmlspecialchars(pathinfo($file, PATHINFO_FILENAME)) ?>" class="p-2 border rounded">
                                            </div>
                                            <div>
                                                <label class="block text-sm text-gray-600">Lieu(x) (séparés par une virgule)</label>
                                                <input type="text" name="location" class="p-2 border rounded">
                                            </div>
                                            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Ajouter</button>
                                        </div>
                                    </form>
                                    <form action="gestion_notices.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier localement ?');">
                                        <input type="hidden" name="action" value="delete_local_file">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars($file) ?>">
                                        <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Supprimer le fichier local</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-gray-700">Notices orphelines (<?= count($orphaned_notices) ?>)</h2>
            <?php if (empty($orphaned_notices)): ?>
                <p class="text-gray-500 italic">Aucune notice orpheline n'a été trouvée dans la base de données.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($orphaned_notices as $notice): ?>
                        <div class="bg-red-50 p-4 rounded-lg shadow-sm flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-lg font-bold"><?= htmlspecialchars($notice['titre']) ?></p>
                                <p class="text-sm text-gray-600">
                                Lieu(x) :
                                <?php
                                    // Divise la chaîne par les virgules et les espaces, puis affiche chaque lieu
                                    $locations = explode(',', $notice['location']);
                                    foreach ($locations as $loc) {
                                        echo htmlspecialchars(ucfirst(trim($loc))) . ' ';
                                    }
                                ?>
                                | <span class="text-red-700 font-bold">Fichier introuvable sur le serveur : <?= htmlspecialchars($notice['lien']) ?></span></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <form action="gestion_notices.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette entrée de la BDD ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $notice['id'] ?>">
                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Supprimer de la BDD</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const form = document.getElementById(`form-edit-${id}`);
                // Cache tous les autres formulaires
                document.querySelectorAll('.form-edit').forEach(f => {
                    if (f.id !== `form-edit-${id}`) {
                        f.classList.add('hidden');
                    }
                });
                // Affiche ou cache le formulaire cliqué
                form.classList.toggle('hidden');
            });
        });
        document.querySelectorAll('.btn-cancel').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const form = document.getElementById(`form-edit-${id}`);
                form.classList.add('hidden');
            });
        });
    </script>
</body>
</html>