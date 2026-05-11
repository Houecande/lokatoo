<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

if (estConnecte()) {
    logActivite($db, 'deconnexion', 'utilisateur', $_SESSION['user_id'] ?? 0, 'Déconnexion');
}

deconnecterUtilisateur();
header('Location: login.php');
exit;
?>