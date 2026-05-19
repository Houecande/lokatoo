<?php
// api/contrats.php — GET / POST / PUT
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Gestion des requêtes CORS au cas où VB.NET ou un navigateur en aurait besoin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$user   = getTokenUser();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        // Récupération globale des contrats avec les jointures nécessaires
        $rows = $db->query("
            SELECT cl.*,
                   CONCAT(l.prenom,' ',l.nom)   AS locataire_nom,
                   l.telephone                   AS locataire_tel,
                   bi.adresse                    AS bien_adresse,
                   bi.type                       AS bien_type,
                   bi.quartier                   AS bien_quartier,
                   CONCAT(u.prenom,' ',u.nom)    AS agent_nom
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
        if (empty($body['locataire_id']) || empty($body['bien_id']) || empty($body['date_debut']) || empty($body['loyer'])) {
            badRequest('Locataire, bien, date début et loyer obligatoires');
        }

        // 1. Vérifier si l'utilisateur connecté possède un ID dans la table agent_immobilier
        $agentRow = $db->prepare("SELECT id FROM agent_immobilier WHERE utilisateur_id = ?");
        $agentRow->execute([$user['id']]);
        $agent = $agentRow->fetch();

        $agentId = null;

        if (!$agent) {
            // 2. CORRECTION CRITIQUE : Si c'est un gérant ou DG, on l'ajoute automatiquement à la table agent_immobilier
            if (in_array($user['role'], ['gerant', 'directeur_general'])) {
                $numeroCarte = 'AUTO-' . strtoupper($user['role']) . '-' . $user['id'];
                
                $insAgent = $db->prepare("INSERT INTO agent_immobilier (utilisateur_id, numero_carte) VALUES (?, ?)");
                $insAgent->execute([$user['id'], $numeroCarte]);
                
                // On récupère l'id de l'agent fraichement créé
                $agentId = $db->lastInsertId();
            } else {
                // Si c'est un autre rôle non autorisé à signer des baux
                badRequest('Utilisateur non reconnu comme agent et droits insuffisants pour signer un contrat.');
            }
        } else {
            $agentId = $agent['id'];
        }

        // 3. Insertion du contrat de location sécurisée avec l'agentId valide
        $stmt = $db->prepare("INSERT INTO contrat_location
            (locataire_id, bien_id, agent_id, date_debut, date_fin, duree_mois, loyer, caution, jour_echeance, statut, observations)
            VALUES (:lid, :bid, :aid, :debut, :fin, :duree, :loyer, :caution, :echeance, 'en_cours', :obs)");
            
        $stmt->execute([
            ':lid'      => (int)$body['locataire_id'],
            ':bid'      => (int)$body['bien_id'],
            ':aid'      => $agentId, // Utilisation de l'identifiant d'agent valide
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
        $db->prepare("UPDATE bien_immobilier SET statut='occupe' WHERE id=?")->execute([(int)$body['bien_id']]);
        
        created(['id' => $newId], 'Contrat créé');
        break;

    case 'PUT':
        if (!$id) badRequest('ID manquant');
        
        $stmt = $db->prepare("UPDATE contrat_location SET
            date_fin=:fin, duree_mois=:duree, loyer=:loyer,
            caution=:caution, jour_echeance=:echeance,
            statut=:statut, observations=:obs WHERE id=:id");
            
        $stmt->execute([
            ':fin'      => !empty($body['date_fin']) ? $body['date_fin'] : null,
            ':duree'    => !empty($body['duree_mois']) ? (int)$body['duree_mois'] : null,
            ':loyer'    => (float)$body['loyer'],
            ':caution'  => (float)($body['caution'] ?? 0),
            ':echeance' => (int)($body['jour_echeance'] ?? 5),
            ':statut'   => $body['statut'] ?? 'en_cours',
            ':obs'      => trim($body['observations'] ?? ''),
            ':id'       => $id,
        ]);
        
        // Si le contrat passe en statut résilié/terminé → libérer automatiquement le bien immobilier
        if (in_array($body['statut'] ?? '', ['termine', 'resilie'])) {
            $bien = $db->prepare("SELECT bien_id FROM contrat_location WHERE id=?");
            $bien->execute([$id]);
            $bienId = $bien->fetchColumn();
            if ($bienId) {
                $db->prepare("UPDATE bien_immobilier SET statut='libre' WHERE id=?")->execute([$bienId]);
            }
        }
        ok(['id' => $id], 'Contrat modifié');
        break;

    default:
        badRequest('Méthode non autorisée');
}