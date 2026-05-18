<?php
//  includes/auth_check.php
//  Vérifie le token JWT à chaque appel API
//  Usage : require_once '../includes/auth_check.php';
//          $user = getTokenUser(); // retourne les infos du user
require_once __DIR__ . '/../includes/response.php';

// ── Clé secrète — CHANGER en production
define('JWT_SECRET', 'lokatoo_secret_2025_changez_moi');
define('JWT_EXPIRE',  60 * 60 * 8); // 8 heures

//  CRÉER UN TOKEN JWT (appelé dans api/auth.php au login)
function creerToken(array $payload): string
{
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + JWT_EXPIRE;
    $payload['iat'] = time();
    $body    = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

//  VÉRIFIER ET DÉCODER UN TOKEN JWT
function verifierToken(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $body, $sig] = $parts;
    $sigAttendue = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));

    if (!hash_equals($sigAttendue, $sig)) return null;

    $payload = json_decode(base64url_decode($body), true);
    if (!$payload || $payload['exp'] < time()) return null;

    return $payload;
}

//  RÉCUPÉRER L'UTILISATEUR DEPUIS LE HEADER Authorization
//  Appelée en haut de chaque endpoint protégé
function getTokenUser(): array
{
    $header = '';

    // 1. Tentative classique via $_SERVER
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } 
    // 2. Secours pour Apache / WAMP (Si VB.NET envoie le header mais qu'Apache le masque)
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        // On gère la casse au cas où (Authorization ou authorization)
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    // Vérification du format Bearer
    if (empty($header) || !str_starts_with($header, 'Bearer ')) {
        unauthorized('Token manquant ou invalide');
    }

    $token   = substr($header, 7);
    $payload = verifierToken($token);

    if (!$payload) {
        unauthorized('Token expiré ou invalide');
    }

    return $payload;
}

//  VÉRIFIER LE RÔLE DE L'UTILISATEUR
//  Exemple : requireRole($user, ['gerant', 'directeur_general'])
function requireRole(array $user, array $rolesAutorises): void
{
    if (!in_array($user['role'], $rolesAutorises, true)) {
        forbidden('Vous n\'avez pas les droits pour cette action');
    }
}

//  HELPERS BASE64 URL-safe
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}