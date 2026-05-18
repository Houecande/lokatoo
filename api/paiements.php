<?php
// api/paiements.php — GET / POST
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user   = getTokenUser();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$contratId = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;

switch ($method) {
    case 'GET':
        $where = $contratId ? "WHERE p.contrat_id = $contratId" : "";
        $rows = $db->query("
            SELECT p.*,
                   CONCAT(l.prenom,' ',l.nom) AS locataire_nom,
                   bi.adresse                 AS bien_adresse,
                   DATE_FORMAT(p.mois_concerne,'%m/%Y') AS mois_libelle,
                   DATE_FORMAT(p.date_paiement,'%d/%m/%Y %H:%i') AS date_libelle
            FROM paiement p
            JOIN contrat_location cl ON cl.id = p.contrat_id
            JOIN locataire l         ON l.id  = cl.locataire_id
            JOIN bien_immobilier bi  ON bi.id = cl.bien_id
            $where
            ORDER BY p.date_paiement DESC
        ")->fetchAll();
        ok($rows);
        break;

    case 'POST':
        if (empty($body['contrat_id']) || empty($body['montant']) || empty($body['mode']) || empty($body['mois_concerne']))
            badRequest('Contrat, montant, mode et mois sont obligatoires');

        // Récupérer agent
        $agentRow = $db->prepare("SELECT id FROM agent_immobilier WHERE utilisateur_id=?");
        $agentRow->execute([$user['id']]);
        $agent = $agentRow->fetch();

        $stmt = $db->prepare("INSERT INTO paiement
            (contrat_id,mois_concerne,montant,mode,statut,reference_mm,numero_mm,enregistre_par)
            VALUES (:cid,:mois,:montant,:mode,'valide',:ref,:num,:agent)");
        $stmt->execute([
            ':cid'     => (int)$body['contrat_id'],
            ':mois'    => $body['mois_concerne'],
            ':montant' => (float)$body['montant'],
            ':mode'    => $body['mode'],
            ':ref'     => trim($body['reference_mm'] ?? ''),
            ':num'     => trim($body['numero_mm'] ?? ''),
            ':agent'   => $agent ? $agent['id'] : null,
        ]);
        $newId = $db->lastInsertId();

        // Régulariser impayé si existant
        $imp = $db->prepare("UPDATE impaye SET statut='regularise', paiement_id=?
                              WHERE contrat_id=? AND mois_concerne=? AND statut='en_cours'");
        $imp->execute([$newId, (int)$body['contrat_id'], $body['mois_concerne']]);

        created(['id' => $newId], 'Paiement enregistré');
        break;

    default: badRequest('Méthode non autorisée');
}