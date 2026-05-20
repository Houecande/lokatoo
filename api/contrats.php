<?php
//  api/contrats.php — GET / POST / PUT
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
            SELECT
                cl.id,
                cl.locataire_id,
                cl.bien_id,
                cl.date_debut,
                cl.date_fin,
                cl.duree_mois,
                cl.loyer,
                cl.caution,
                cl.jour_echeance,
                cl.statut,
                cl.observations,
                cl.created_at,
                CONCAT(l.prenom, ' ', l.nom)  AS locataire_nom,
                l.telephone                    AS locataire_tel,
                bi.adresse                     AS bien_adresse,
                bi.type                        AS bien_type,
                bi.quartier                    AS bien_quartier,
                bi.loyer_mensuel               AS bien_loyer,
                CONCAT(u.prenom, ' ', u.nom)   AS agent_nom
            FROM contrat_location cl
            JOIN locataire l         ON l.id  = cl.locataire_id
            JOIN bien_immobilier bi  ON bi.id = cl.bien_id
            JOIN agent_immobilier ai ON ai.id = cl.agent_id
            JOIN utilisateur u       ON u.id  = ai.utilisateur_id
            ORDER BY cl.created_at DESC
        ")->fetchAll();
        ok($rows);
        break;

    case 'POST':
    
        // Validation champs obligatoires
        if (empty($body['locataire_id'])) badRequest('locataire_id manquant');
        if (empty($body['bien_id']))      badRequest('bien_id manquant');
        if (empty($body['date_debut']))   badRequest('date_debut manquante');
        if (empty($body['loyer']))        badRequest('loyer manquant');

        // Récupérer agent_immobilier lié à cet utilisateur
        $agentRow = $db->prepare("
            SELECT id FROM agent_immobilier WHERE utilisateur_id = ?
        ");
        $agentRow->execute([$user['id']]);
        $agent = $agentRow->fetch();

        // Si l'utilisateur n'est pas un agent, chercher n'importe quel agent
        if (!$agent) {
            $agent = $db->query("SELECT id FROM agent_immobilier LIMIT 1")->fetch();
        }
        if (!$agent) badRequest('Aucun agent disponible pour signer ce contrat');

        $stmt = $db->prepare("
            INSERT INTO contrat_location
                (locataire_id, bien_id, agent_id, date_debut, date_fin,
                 duree_mois, loyer, caution, jour_echeance, statut, observations)
            VALUES
                (:lid, :bid, :aid, :debut, :fin,
                 :duree, :loyer, :caution, :echeance, 'en_cours', :obs)
        ");
        $stmt->execute([
            ':lid'      => (int)$body['locataire_id'],
            ':bid'      => (int)$body['bien_id'],
            ':aid'      => $agent['id'],
            ':debut'    => $body['date_debut'],
            ':fin'      => !empty($body['date_fin']) ? $body['date_fin'] : null,
            ':duree'    => !empty($body['duree_mois']) ? (int)$body['duree_mois'] : null,
            ':loyer'    => (float)$body['loyer'],
            ':caution'  => (float)($body['caution'] ?? 0),
            ':echeance' => (int)($body['jour_echeance'] ?? 5),
            ':obs'      => trim($body['observations'] ?? ''),
        ]);

        $newId = $db->lastInsertId();

        // Marquer le bien comme occupé
        $db->prepare("
            UPDATE bien_immobilier SET statut = 'occupe' WHERE id = ?
        ")->execute([(int)$body['bien_id']]);

        // Journal
        $db->prepare("
            INSERT INTO journal_activite (utilisateur_id, action, table_cible, id_cible, ip)
            VALUES (?, 'contrat_cree', 'contrat_location', ?, ?)
        ")->execute([$user['id'], $newId, $_SERVER['REMOTE_ADDR'] ?? '']);

        created(['id' => $newId], 'Contrat créé avec succès');
        break;

    // ════════════════════════════════════════════════════════
    case 'PUT':
    // ════════════════════════════════════════════════════════
        if (!$id) badRequest('ID contrat manquant');

        // Récupérer le contrat actuel pour avoir toutes les valeurs
        $actuel = $db->prepare("SELECT * FROM contrat_location WHERE id = ?");
        $actuel->execute([$id]);
        $contrat = $actuel->fetch();
        if (!$contrat) notFound('Contrat introuvable');

        // Fusionner avec les nouvelles valeurs (on ne modifie que ce qui est envoyé)
        $nouveauStatut = isset($body['statut']) ? $body['statut'] : $contrat['statut'];
        $nouveauLoyer  = isset($body['loyer'])  ? (float)$body['loyer'] : (float)$contrat['loyer'];
        $dateFin       = isset($body['date_fin']) ? $body['date_fin'] : $contrat['date_fin'];
        $duree         = isset($body['duree_mois']) ? (int)$body['duree_mois'] : $contrat['duree_mois'];
        $caution       = isset($body['caution']) ? (float)$body['caution'] : (float)$contrat['caution'];
        $echeance      = isset($body['jour_echeance']) ? (int)$body['jour_echeance'] : (int)$contrat['jour_echeance'];
        $obs           = isset($body['observations']) ? trim($body['observations']) : $contrat['observations'];

        $stmt = $db->prepare("
            UPDATE contrat_location SET
                statut        = :statut,
                loyer         = :loyer,
                date_fin      = :date_fin,
                duree_mois    = :duree,
                caution       = :caution,
                jour_echeance = :echeance,
                observations  = :obs
            WHERE id = :id
        ");
        $stmt->execute([
            ':statut'   => $nouveauStatut,
            ':loyer'    => $nouveauLoyer,
            ':date_fin' => $dateFin,
            ':duree'    => $duree,
            ':caution'  => $caution,
            ':echeance' => $echeance,
            ':obs'      => $obs,
            ':id'       => $id,
        ]);

        // Si contrat terminé ou résilié → libérer le bien
        if (in_array($nouveauStatut, ['termine', 'resilie'])) {
            $db->prepare("
                UPDATE bien_immobilier SET statut = 'libre'
                WHERE id = (SELECT bien_id FROM contrat_location WHERE id = ?)
            ")->execute([$id]);
        }

        // Journal
        $db->prepare("
            INSERT INTO journal_activite (utilisateur_id, action, table_cible, id_cible, details, ip)
            VALUES (?, 'contrat_modifie', 'contrat_location', ?, ?, ?)
        ")->execute([
            $user['id'], $id,
            'Nouveau statut : ' . $nouveauStatut,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        ok(['id' => $id, 'statut' => $nouveauStatut], 'Contrat modifié avec succès');
        break;

    default:
        badRequest('Méthode non autorisée');
}