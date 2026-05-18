<?php
// api/biens.php — GET / POST / PUT / DELETE
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user   = getTokenUser();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $rows = $db->query("
            SELECT bi.*,
                   CONCAT(b.prenom,' ',b.nom) AS bailleur_nom
            FROM bien_immobilier bi
            JOIN bailleur b ON b.id = bi.bailleur_id
            ORDER BY bi.created_at DESC
        ")->fetchAll();
        ok($rows);
        break;

    case 'POST':
        if (empty($body['bailleur_id']) || empty($body['type']) || empty($body['adresse']) || empty($body['loyer_mensuel']))
            badRequest('Bailleur, type, adresse et loyer sont obligatoires');
        $stmt = $db->prepare("INSERT INTO bien_immobilier
            (bailleur_id,type,adresse,quartier,ville,surface_m2,nb_pieces,loyer_mensuel,charges,statut,description)
            VALUES (:bid,:type,:adresse,:quartier,:ville,:surface,:pieces,:loyer,:charges,:statut,:desc)");
        $stmt->execute([
            ':bid'     => (int)$body['bailleur_id'],
            ':type'    => $body['type'],
            ':adresse' => trim($body['adresse']),
            ':quartier'=> trim($body['quartier'] ?? ''),
            ':ville'   => trim($body['ville'] ?? 'Cotonou'),
            ':surface' => $body['surface_m2'] ?? null,
            ':pieces'  => $body['nb_pieces'] ?? null,
            ':loyer'   => (float)$body['loyer_mensuel'],
            ':charges' => (float)($body['charges'] ?? 0),
            ':statut'  => $body['statut'] ?? 'libre',
            ':desc'    => trim($body['description'] ?? ''),
        ]);
        created(['id' => $db->lastInsertId()], 'Bien créé');
        break;

    case 'PUT':
        if (!$id) badRequest('ID manquant');
        $stmt = $db->prepare("UPDATE bien_immobilier SET
            bailleur_id=:bid,type=:type,adresse=:adresse,quartier=:quartier,
            ville=:ville,surface_m2=:surface,nb_pieces=:pieces,
            loyer_mensuel=:loyer,charges=:charges,statut=:statut,description=:desc
            WHERE id=:id");
        $stmt->execute([
            ':bid'     => (int)$body['bailleur_id'],
            ':type'    => $body['type'],
            ':adresse' => trim($body['adresse']),
            ':quartier'=> trim($body['quartier'] ?? ''),
            ':ville'   => trim($body['ville'] ?? 'Cotonou'),
            ':surface' => $body['surface_m2'] ?? null,
            ':pieces'  => $body['nb_pieces'] ?? null,
            ':loyer'   => (float)$body['loyer_mensuel'],
            ':charges' => (float)($body['charges'] ?? 0),
            ':statut'  => $body['statut'] ?? 'libre',
            ':desc'    => trim($body['description'] ?? ''),
            ':id'      => $id,
        ]);
        ok(['id'=>$id], 'Bien modifié');
        break;

    case 'DELETE':
        if (!$id) badRequest('ID manquant');
        $nb = $db->prepare("SELECT COUNT(*) FROM contrat_location WHERE bien_id=? AND statut='en_cours'");
        $nb->execute([$id]);
        if ($nb->fetchColumn() > 0) badRequest('Ce bien a un contrat actif — impossible de supprimer');
        $db->prepare("DELETE FROM bien_immobilier WHERE id=?")->execute([$id]);
        ok(null, 'Bien supprimé');
        break;

    default: badRequest('Méthode non autorisée');
}