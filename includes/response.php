<?php
//  includes/response.php
//  Fonction unique pour toutes les réponses JSON de l'API

function jsonResponse(bool $success, mixed $data = null, string $message = '', int $code = 200): void
{
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

// Raccourcis
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

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}