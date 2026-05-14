<?php
//  api/auth.php
//  POST /api/auth.php   → { success, token, user }
//  POST /api/auth.php?action=logout → { success }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Seul POST est accepté
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    badRequest('Méthode non autorisée');
}

// Lire le body JSON envoyé par VB.NET
$body = json_decode(file_get_contents('php://input'), true);

// Récupérer email et mot de passe
$email = trim($body['email'] ?? '');
$mdp   = trim($body['mot_de_passe'] ?? '');

// ── Validation basique ──────────────────────────────────────
if (empty($email) || empty($mdp)) {
    badRequest('Email et mot de passe requis');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    badRequest('Format email invalide');
}

// ── Recherche dans la BDD ───────────────────────────────────
$stmt = $db->prepare("
    SELECT id, nom, prenom, email, mot_de_passe, role, actif
    FROM utilisateur
    WHERE email = :email
    LIMIT 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// ── Vérification ────────────────────────────────────────────
if (!$user) {
    unauthorized('Email ou mot de passe incorrect');
}

if (!$user['actif']) {
    unauthorized('Compte désactivé — contactez l\'administrateur');
}

if (!password_verify($mdp, $user['mot_de_passe'])) {
    unauthorized('Email ou mot de passe incorrect');
}

// ── Créer le token JWT ──────────────────────────────────────
$token = creerToken([
    'id'     => $user['id'],
    'nom'    => $user['nom'],
    'prenom' => $user['prenom'],
    'email'  => $user['email'],
    'role'   => $user['role'],
]);

// ── Journal de connexion ────────────────────────────────────
$log = $db->prepare("
    INSERT INTO journal_activite (utilisateur_id, action, details, ip)
    VALUES (:uid, 'connexion', 'Connexion via API', :ip)
");
$log->execute([
    ':uid' => $user['id'],
    ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
]);

// ── Réponse finale ──────────────────────────────────────────
ok([
    'token' => $token,
    'user'  => [
        'id'     => $user['id'],
        'nom'    => $user['nom'],
        'prenom' => $user['prenom'],
        'email'  => $user['email'],
        'role'   => $user['role'],
    ],
], 'Connexion réussie');