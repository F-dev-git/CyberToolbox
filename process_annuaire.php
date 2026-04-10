<?php
// process_annuaire.php
require_once __DIR__ . '/auth.php';
require_auth();
require_once __DIR__ . '/etc/config.php';
require_once __DIR__ . '/functions.php';

$redirect_url = 'gestion_annuaire.php';
$message = '';
$message_type = '';

$conn = connectDB();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // L'action est maintenant lue directement depuis le champ POST 'action' du formulaire principal
    // Ou du formulaire de suppression si 'action=delete' est passé via GET.
    $action = $_POST['action'] ?? $_GET['action'] ?? 'add'; 

    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $lien_image = trim($_POST['lien_image'] ?? '');
    $lien_pc = trim($_POST['lien_pc'] ?? '');
    $lien_ios = trim($_POST['lien_ios'] ?? '');
    $lien_android = trim($_POST['lien_android'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    // Convertir les chaînes vides en NULL pour la base de données pour les champs optionnels
    $description = $description === '' ? null : $description;
    $lien_image = $lien_image === '' ? null : $lien_image;
    $lien_ios = $lien_ios === '' ? null : $lien_ios;
    $lien_android = $lien_android === '' ? null : $lien_android;
    $tags = $tags === '' ? null : $tags;
    $location = $location === '' ? null : $location;

    $login_id_raw = trim($_POST['login_id'] ?? '');
    $password_id_raw = trim($_POST['password_id'] ?? '');
    $note_id_raw = trim($_POST['note_id'] ?? '');

    $login_id_encrypted = encrypt_data($login_id_raw);
    $password_id_encrypted = encrypt_data($password_id_raw);
    $note_id_encrypted = encrypt_data($note_id_raw);

    switch ($action) {
        case 'add':
            if (empty($titre) || empty($lien_pc)) {
                $message = "Le titre et le lien PC sont obligatoires pour ajouter un service.";
                $message_type = 'error';
                break;
            }

            $stmt = $conn->prepare("INSERT INTO annuaire (titre, description, lien_image, lien_pc, lien_ios, lien_android, tags, location, login_id, password_id, note_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", 
                $titre, $description, $lien_image, $lien_pc, $lien_ios, $lien_android, 
                $tags, $location, $login_id_encrypted, $password_id_encrypted, $note_id_encrypted
            );
            
            if ($stmt->execute()) {
                $message = "Service '$titre' ajouté avec succès !";
                $message_type = 'success';
            } else {
                $message = "Erreur lors de l'ajout du service : " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
            break;

        case 'update':
            // Pour une modification, l'ID est essentiel.
            if ($id === null) {
                $message = "ID de service manquant pour la modification.";
                $message_type = 'error';
                break;
            }
            if (empty($titre) || empty($lien_pc)) {
                $message = "Le titre et le lien PC sont obligatoires pour modifier un service.";
                $message_type = 'error';
                break;
            }

            $stmt = $conn->prepare("UPDATE annuaire SET titre = ?, description = ?, lien_image = ?, lien_pc = ?, lien_ios = ?, lien_android = ?, tags = ?, location = ?, login_id = ?, password_id = ?, note_id = ? WHERE id = ?");
            $stmt->bind_param("sssssssssssi", 
                $titre, $description, $lien_image, $lien_pc, $lien_ios, $lien_android, 
                $tags, $location, $login_id_encrypted, $password_id_encrypted, $note_id_encrypted, $id
            );
            
            if ($stmt->execute()) {
                $message = "Service '$titre' (ID: $id) mis à jour avec succès !";
                $message_type = 'success';
            } else {
                $message = "Erreur lors de la mise à jour du service : " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
            break;

        case 'delete':
            if ($id === null) {
                $message = "ID de service manquant pour la suppression.";
                $message_type = 'error';
                break;
            }

            $stmt = $conn->prepare("DELETE FROM annuaire WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "Service (ID: $id) supprimé avec succès !";
                $message_type = 'success';
            } else {
                $message = "Erreur lors de la suppression du service : " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
            break;

        default:
            $message = "Action non reconnue.";
            $message_type = 'error';
            break;
    }
} else {
    $message = "Méthode de requête non autorisée.";
    $message_type = 'error';
}

$conn->close();

header("Location: $redirect_url?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>