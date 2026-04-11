<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        if ($user === '' || $pass === '') {
                $error = 'Veuillez renseigner l\'identifiant et le mot de passe.';
        } else {
                if (login_user($user, $pass)) {
                        $redirect = $_GET['redirect'] ?? 'index.php';
                        header('Location: ' . $redirect);
                        exit;
                }
                $error = 'Identifiants invalides.';
        }
}

?>
<!doctype html>
<html lang="fr">
<head>
        <meta charset="utf-8">
        <title>Connexion — CyberToolbox</title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <link rel="stylesheet" href="style.css?v=2">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">F-dev-git | CyberToolbox</h1>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded">
                <?php echo htmlspecialchars($error, ENT_QUOTES); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Identifiant</label>
                <input name="username" required class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Mot de passe</label>
                <input name="password" type="password" required class="mt-1 block w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-md shadow-sm">Se connecter</button>
            </div>
        </form>

    </div>
</body>
</html>
