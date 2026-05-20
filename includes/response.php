<?php
//  includes/response.php
//  À inclure EN PREMIER dans chaque fichier API

// Bloquer TOUT affichage d'erreur PHP dans la réponse HTTP
// Les erreurs iront dans les logs serveur uniquement
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Intercepter les erreurs fatales et les retourner en JSON propre
register_shutdown_function(function () {
    $erreur = error_get_last();
    if ($erreur && in_array($erreur['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Vider tout ce qui aurait pu être affiché avant
        if (ob_get_level()) ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur serveur : ' . $erreur['message'] .
                         ' (ligne ' . $erreur['line'] . ' dans ' . basename($erreur['file']) . ')',
            'data'    => null,
        ], JSON_UNESCAPED_UNICODE);
    }
});

// Démarrer le tampon de sortie pour pouvoir nettoyer si besoin
ob_start();

function jsonResponse(bool $success, mixed $data = null, string $message = '', int $code = 200): void
{
    if (ob_get_level()) ob_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
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

// Répondre aux preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}