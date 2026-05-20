<?php
// ============================================================
//  includes/response.php
//  DOIT etre inclus EN PREMIER dans chaque fichier api/
// ============================================================

// 1. Bloquer tout affichage d'erreur PHP
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// 2. Tampon de sortie pour intercepter tout output parasite
if (!ob_get_level()) ob_start();

// 3. Intercepter les erreurs fatales PHP et les convertir en JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level()) ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur PHP : ' . $err['message']
                       . ' — ligne ' . $err['line']
                       . ' (' . basename($err['file']) . ')',
            'data'    => null,
        ], JSON_UNESCAPED_UNICODE);
    }
});

// 4. Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Répondre aux preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Fonctions de réponse ────────────────────────────────────

function jsonResponse(bool $success, mixed $data = null, string $message = '', int $code = 200): void
{
    if (ob_get_level()) ob_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ok(mixed $data = null, string $message = ''): void
{
    jsonResponse(true, $data, $message, 200);
}

function created(mixed $data = null, string $message = 'Créé avec succès'): void
{
    jsonResponse(true, $data, $message, 201);
}

function badRequest(string $message = 'Requête invalide'): void
{
    jsonResponse(false, null, $message, 400);
}

function unauthorized(string $message = 'Non autorisé'): void
{
    jsonResponse(false, null, $message, 401);
}

function forbidden(string $message = 'Accès refusé'): void
{
    jsonResponse(false, null, $message, 403);
}

function notFound(string $message = 'Ressource introuvable'): void
{
    jsonResponse(false, null, $message, 404);
}

function serverError(string $message = 'Erreur serveur'): void
{
    jsonResponse(false, null, $message, 500);
}