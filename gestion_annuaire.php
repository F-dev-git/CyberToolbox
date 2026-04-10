<?php
// gestion_annuaire.php
require_once __DIR__ . '/auth.php';
require_auth();
require_once __DIR__ . '/etc/config.php';
require_once __DIR__ . '/functions.php';

$conn = connectDB();

$message = $_GET['message'] ?? '';
$message_type = $_GET['type'] ?? '';

$services = [];
$sql = "SELECT id, titre, description, lien_image, lien_pc, lien_ios, lien_android, tags, location, login_id, password_id, note_id FROM annuaire ORDER BY titre";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['login_id'] = decrypt_data($row['login_id']);
        $row['password_id'] = decrypt_data($row['password_id']);
        $row['note_id'] = decrypt_data($row['note_id']);
        $services[] = $row;
    }
} else {
    $message = "Erreur lors de la récupération des services : " . $conn->error;
    $message_type = 'error';
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'Annuaire - CyberToolbox</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 1280px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen p-4">

    <div class="container mx-auto bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-none p-6 sm:p-8 space-y-8">
        <h1 class="text-3xl font-bold text-center text-indigo-700 dark:text-indigo-400 mb-8">Gestion de l'Annuaire</h1>

        <?php if ($message): ?>
            <div id="statusMessage" class="message <?= htmlspecialchars($message_type) ?> p-3 rounded-md mb-4">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Retour à l'annuaire
            </a>
        </div>

        <h2 class="text-2xl font-semibold text-indigo-600 dark:text-indigo-400 mb-4 mt-8">Services Existants</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white dark:bg-gray-700 rounded-lg shadow-md">
                <thead>
                    <tr class="bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-left text-sm font-medium">
                        <th class="py-3 px-4 rounded-tl-lg">Titre</th>
                        <th class="py-3 px-4">Tags</th>
                        <th class="py-3 px-4">Lieu</th>
                        <th class="py-3 px-4">Login</th>
                        <th class="py-3 px-4">Mot de passe</th>
                        <th class="py-3 px-4">Note</th>
                        <th class="py-3 px-4 rounded-tr-lg">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $service): ?>
                            <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="py-2 px-4"><?= htmlspecialchars($service['titre'] ?? '') ?></td>
                                <td class="py-2 px-4 text-xs"><?= htmlspecialchars($service['tags'] ?? '') ?></td>
                                <td class="py-2 px-4 text-xs"><?= htmlspecialchars($service['location'] ?? '') ?></td>
                                <td class="py-2 px-4 text-xs"><?= htmlspecialchars($service['login_id'] ?? '') ?></td>
                                <td class="py-2 px-4 text-xs"><?= htmlspecialchars($service['password_id'] ?? '') ?></td>
                                <td class="py-2 px-4 text-xs max-w-xs overflow-hidden whitespace-nowrap text-ellipsis"><?= htmlspecialchars($service['note_id'] ?? '') ?></td>
                                <td class="py-2 px-4 flex space-x-2">
                                    <button type="button" 
                                            class="edit-btn bg-yellow-500 hover:bg-yellow-600 text-white text-xs py-1 px-3 rounded-md transition-colors"
                                            data-id="<?= htmlspecialchars($service['id'] ?? '') ?>"
                                            data-titre="<?= htmlspecialchars($service['titre'] ?? '') ?>"
                                            data-description="<?= htmlspecialchars($service['description'] ?? '') ?>"
                                            data-lien_image="<?= htmlspecialchars($service['lien_image'] ?? '') ?>"
                                            data-lien_pc="<?= htmlspecialchars($service['lien_pc'] ?? '') ?>"
                                            data-lien_ios="<?= htmlspecialchars($service['lien_ios'] ?? '') ?>"
                                            data-lien_android="<?= htmlspecialchars($service['lien_android'] ?? '') ?>"
                                            data-tags="<?= htmlspecialchars($service['tags'] ?? '') ?>"
                                            data-location="<?= htmlspecialchars($service['location'] ?? '') ?>"
                                            data-login_id="<?= htmlspecialchars($service['login_id'] ?? '') ?>"
                                            data-password_id="<?= htmlspecialchars($service['password_id'] ?? '') ?>"
                                            data-note_id="<?= htmlspecialchars($service['note_id'] ?? '') ?>">
                                        Modifier
                                    </button>
                                    <form action="process_annuaire.php?action=delete" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');" class="inline-block">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($service['id'] ?? '') ?>">
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs py-1 px-3 rounded-md transition-colors">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-4 px-4 text-center text-gray-500 dark:text-gray-400">Aucun service n'est enregistré.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 class="text-2xl font-semibold text-indigo-600 dark:text-indigo-400 mt-8 mb-4" id="form-title">Ajouter un Service à l'Annuaire</h2>
        <form action="process_annuaire.php" method="post" class="space-y-4 p-6 bg-gray-50 dark:bg-gray-700 rounded-lg shadow-inner">
            <input type="hidden" name="id" id="serviceId"> 
            <input type="hidden" name="action" id="formAction" value="add"> <div>
                <label for="titre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Titre :</label>
                <input type="text" id="titre" name="titre" required class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description :</label>
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white"></textarea>
            </div>
            <div>
                <label for="lien_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lien Image (URL) :</label>
                <input type="url" id="lien_image" name="lien_image" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="lien_pc" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lien PC (URL) :</label>
                <input type="url" id="lien_pc" name="lien_pc" required class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="lien_ios" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lien iOS (URL) :</label>
                <input type="url" id="lien_ios" name="lien_ios" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="lien_android" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lien Android (URL) :</label>
                <input type="url" id="lien_android" name="lien_android" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="tags" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tags (séparés par des virgules) :</label>
                <input type="text" id="tags" name="tags" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lieu(x) (séparés par des virgules) :</label>
                <input type="text" id="location" name="location" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            
            <h3 class="text-xl font-semibold text-indigo-600 dark:text-indigo-400 mt-6 mb-4">Identifiants (chiffrés à l'enregistrement)</h3>
            <div>
                <label for="login_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Login :</label>
                <input type="text" id="login_id" name="login_id" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="password_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mot de passe :</label>
                <input type="text" id="password_id" name="password_id" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
            </div>
            <div>
                <label for="note_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Note :</label>
                <textarea id="note_id" name="note_id" rows="3" class="mt-1 block w-full p-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600 text-gray-900 dark:text-white"></textarea>
            </div>

            <div class="flex justify-end space-x-4">
                <button type="button" id="cancelEditBtn" class="bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors hidden">Annuler la modification</button>
                <button type="submit" id="submitFormBtn" class="bg-blue-600 dark:bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 dark:hover:bg-blue-700 transition-colors">Ajouter le Service</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form');
            const serviceIdField = document.getElementById('serviceId');
            const formActionField = document.getElementById('formAction'); // Le nouveau champ caché pour l'action
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            const submitFormBtn = document.getElementById('submitFormBtn');
            const editButtons = document.querySelectorAll('.edit-btn');
            const formTitle = document.getElementById('form-title');
            const statusMessage = document.getElementById('statusMessage');

            // Fonction pour réinitialiser le formulaire en mode "Ajouter"
            const resetFormToAddMode = () => {
                form.reset(); 
                serviceIdField.value = ''; 
                formActionField.value = 'add'; // Assure que l'action est "add"
                submitFormBtn.textContent = 'Ajouter le Service'; 
                formTitle.textContent = 'Ajouter un Service à l\'Annuaire';
                cancelEditBtn.classList.add('hidden'); // Cache le bouton d'annulation
            };

            // Gérer le clic sur les boutons "Modifier"
            editButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remplir le formulaire avec les données du service sélectionné
                    serviceIdField.value = button.dataset.id;
                    formActionField.value = 'update'; // Définit l'action sur "update"
                    document.getElementById('titre').value = button.dataset.titre;
                    document.getElementById('description').value = button.dataset.description;
                    document.getElementById('lien_image').value = button.dataset.lien_image;
                    document.getElementById('lien_pc').value = button.dataset.lien_pc;
                    document.getElementById('lien_ios').value = button.dataset.lien_ios;
                    document.getElementById('lien_android').value = button.dataset.lien_android;
                    document.getElementById('tags').value = button.dataset.tags;
                    document.getElementById('location').value = button.dataset.location;
                    document.getElementById('login_id').value = button.dataset.login_id;
                    document.getElementById('password_id').value = button.dataset.password_id;
                    document.getElementById('note_id').value = button.dataset.note_id;

                    // Mettre à jour le bouton submit pour indiquer une modification
                    submitFormBtn.textContent = 'Modifier le Service';
                    formTitle.textContent = 'Modifier le Service';
                    cancelEditBtn.classList.remove('hidden'); // Affiche le bouton d'annulation
                    
                    // Remonter en haut de page pour que le formulaire soit visible
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            });

            // Gérer le bouton "Annuler la modification"
            cancelEditBtn.addEventListener('click', resetFormToAddMode);

            // Initialiser le formulaire en mode "Ajouter" quand la page charge
            resetFormToAddMode();

            // Vider les paramètres d'URL après l'affichage du message
            if (statusMessage) {
                const url = new URL(window.location.href);
                url.searchParams.delete('message');
                url.searchParams.delete('type');
                window.history.replaceState({}, document.title, url.toString());
            }
        });
    </script>
</body>
</html>