<?php
// api/bailleurs.php — GET / POST / PUT / DELETE
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user   = getTokenUser();
requireRole($user, ['directeur_general','gerant','secretaire']);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $rows = $db->query("
            SELECT b.*,
                   COUNT(bi.id) AS nb_biens
            FROM bailleur b
            LEFT JOIN bien_immobilier bi ON bi.bailleur_id = b.id
            GROUP BY b.id
            ORDER BY b.nom ASC
        ")->fetchAll();
        ok($rows);
        break;

    case 'POST':
        if (empty($body['nom']) || empty($body['prenom']) || empty($body['telephone']))
            badRequest('Nom, prénom et téléphone obligatoires');
        $stmt = $db->prepare("INSERT INTO bailleur (nom,prenom,telephone,email,adresse,piece_identite,rib)
                               VALUES (:nom,:prenom,:telephone,:email,:adresse,:piece,:rib)");
        $stmt->execute([
            ':nom'      => trim($body['nom']),
            ':prenom'   => trim($body['prenom']),
            ':telephone'=> trim($body['telephone']),
            ':email'    => trim($body['email'] ?? ''),
            ':adresse'  => trim($body['adresse'] ?? ''),
            ':piece'    => trim($body['piece_identite'] ?? ''),
            ':rib'      => trim($body['rib'] ?? ''),
        ]);
        created(['id' => $db->lastInsertId()], 'Bailleur créé');
        break;

    case 'PUT':
        if (!$id) badRequest('ID manquant');
        $stmt = $db->prepare("UPDATE bailleur SET nom=:nom,prenom=:prenom,telephone=:telephone,
                               email=:email,adresse=:adresse,piece_identite=:piece,rib=:rib WHERE id=:id");
        $stmt->execute([
            ':nom'      => trim($body['nom']),
            ':prenom'   => trim($body['prenom']),
            ':telephone'=> trim($body['telephone']),
            ':email'    => trim($body['email'] ?? ''),
            ':adresse'  => trim($body['adresse'] ?? ''),
            ':piece'    => trim($body['piece_identite'] ?? ''),
            ':rib'      => trim($body['rib'] ?? ''),
            ':id'       => $id,
        ]);
        ok(['id'=>$id], 'Bailleur modifié');
        break;

    case 'DELETE':
        if (!$id) badRequest('ID manquant');
        $nb = $db->prepare("SELECT COUNT(*) FROM bien_immobilier WHERE bailleur_id=?");
        $nb->execute([$id]);
        if ($nb->fetchColumn() > 0) badRequest('Ce bailleur possède des biens — impossible de supprimer');
        $db->prepare("DELETE FROM bailleur WHERE id=?")->execute([$id]);
        ok(null, 'Bailleur supprimé');
        break;

    default: badRequest('Méthode non autorisée');
}