<?php
//  includes/auth.php
//  Gestion des sessions, authentification et contrôle des rôles
//  Usage : require_once '../includes/auth.php';

// Démarrer la session si elle n'est pas encore active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  CONSTANTES DES RÔLES
define('ROLE_DG',         'directeur_general');
define('ROLE_GERANT',     'gerant');
define('ROLE_SECRETAIRE', 'secretaire');
define('ROLE_AGENT',      'agent_immobilier');
define('ROLE_LOCATAIRE',  'locataire');

//  VÉRIFIER SI L'UTILISATEUR EST CONNECTÉ
function estConnecte(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

//  RÉCUPÉRER L'UTILISATEUR CONNECTÉ
function utilisateurConnecte(): array
{
    return [
        'id'     => $_SESSION['user_id']     ?? null,
        'nom'    => $_SESSION['user_nom']    ?? '',
        'prenom' => $_SESSION['user_prenom'] ?? '',
        'email'  => $_SESSION['user_email']  ?? '',
        'role'   => $_SESSION['user_role']   ?? '',
    ];
}

//  RÉCUPÉRER LE RÔLE DE L'UTILISATEUR CONNECTÉ
function getRole(): string
{
    return $_SESSION['user_role'] ?? '';
}

//  VÉRIFIER SI L'UTILISATEUR A UN RÔLE PRÉCIS
//  Exemple : aRole(ROLE_GERANT) ou aRole([ROLE_GERANT, ROLE_DG])
function aRole(string|array $roles): bool
{
    $roleActuel = getRole();
    if (is_array($roles)) {
        return in_array($roleActuel, $roles, true);
    }
    return $roleActuel === $roles;
}

//  PROTÉGER UNE PAGE — REDIRIGER SI NON CONNECTÉ
//  Appeler en haut de chaque page protégée
function requireConnexion(): void
{
    if (!estConnecte()) {
        header('Location: ' . baseUrl('auth/login.php'));
        exit;
    }
}

//  PROTÉGER UNE PAGE PAR RÔLE
//  Exemple : requireRole([ROLE_GERANT, ROLE_DG])
function requireRole(string|array $roles): void
{
    requireConnexion();
    if (!aRole($roles)) {
        // Rediriger vers le dashboard avec un message d'accès refusé
        $_SESSION['flash_error'] = "Accès refusé. Vous n'avez pas les droits nécessaires.";
        header('Location: ' . baseUrl('dashboard/index.php'));
        exit;
    }
}

//  CONSTRUIRE L'URL DE BASE DU PROJET
//  Permet de générer des liens corrects depuis n'importe quel
//  sous-dossier (modules/locataires/, auth/, etc.)
function baseUrl(string $chemin = ''): string
{
    $protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Nom du dossier racine du projet dans www/
    $racine    = '/lokatoo';
    return $protocole . '://' . $host . $racine . '/' . ltrim($chemin, '/');
}


//  MESSAGES FLASH (succès / erreur affichés une seule fois)
function setFlash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): string
{
    $message = $_SESSION['flash_' . $type] ?? '';
    unset($_SESSION['flash_' . $type]);
    return $message;
}

function hasFlash(string $type): bool
{
    return !empty($_SESSION['flash_' . $type]);
}

//  CONNEXION UTILISATEUR — Appelée depuis auth/login.php
function connecterUtilisateur(array $user): void
{
    // Regénérer l'ID de session pour éviter la fixation de session
    session_regenerate_id(true);

    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_nom']    = $user['nom'];
    $_SESSION['user_prenom'] = $user['prenom'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_role']   = $user['role'];
}

//  DÉCONNEXION
function deconnecterUtilisateur(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

//  REDIRECTION APRÈS LOGIN SELON LE RÔLE
function redirectionParRole(): void
{
    $role = getRole();
    $destinations = [
        ROLE_DG         => baseUrl('dashboard/index.php'),
        ROLE_GERANT     => baseUrl('dashboard/index.php'),
        ROLE_SECRETAIRE => baseUrl('dashboard/index.php'),
        ROLE_AGENT      => baseUrl('dashboard/index.php'),
        ROLE_LOCATAIRE  => baseUrl('dashboard/index.php'),
    ];
    $url = $destinations[$role] ?? baseUrl('auth/login.php');
    header('Location: ' . $url);
    exit;
}

//  SÉCURITÉ — Échapper les sorties HTML
function e(string $valeur): string
{
    return htmlspecialchars($valeur, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

//  JOURNAL — Enregistrer une action dans journal_activite
function logActivite(PDO $db, string $action, string $table = '', int $idCible = 0, string $details = ''): void
{
    try {
        $stmt = $db->prepare("
            INSERT INTO journal_activite 
                (utilisateur_id, action, table_cible, id_cible, details, ip)
            VALUES 
                (:uid, :action, :table, :id_cible, :details, :ip)
        ");
        $stmt->execute([
            ':uid'      => $_SESSION['user_id'] ?? null,
            ':action'   => $action,
            ':table'    => $table,
            ':id_cible' => $idCible ?: null,
            ':details'  => $details,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (PDOException $e) {
        // Le log ne doit jamais faire planter l'application
        error_log('Erreur journal_activite : ' . $e->getMessage());
    }
}