<?php
// ============================================================
//  api/biens.php — GET / POST / PUT / DELETE
//  IMPORTANT : response.php doit etre inclus EN PREMIER
// ============================================================

// Bloquer les erreurs PHP immédiatement
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user   = getTokenUser();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {

    case 'GET':
        try {
            $rows = $db->query("
                SELECT
                    bi.id,
                    bi.bailleur_id,
                    bi.type,
                    bi.adresse,
                    bi.quartier,
                    bi.ville,
                    bi.surface_m2,
                    bi.nb_pieces,
                    bi.loyer_mensuel,
                    bi.charges,
                    bi.statut,
                    bi.description,
                    bi.created_at,
                    CONCAT(b.prenom, ' ', b.nom) AS bailleur_nom,
                    b.telephone                  AS bailleur_tel
                FROM bien_immobilier bi
                JOIN bailleur b ON b.id = bi.bailleur_id
                ORDER BY bi.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            ok($rows);
        } catch (Exception $e) {
            serverError('Erreur BDD : ' . $e->getMessage());
        }
        break;

    case 'POST':
        if (empty($body['bailleur_id']))   badRequest('Bailleur obligatoire');
        if (empty($body['type']))          badRequest('Type obligatoire');
        if (empty($body['adresse']))       badRequest('Adresse obligatoire');
        if (empty($body['loyer_mensuel'])) badRequest('Loyer mensuel obligatoire');

        try {
            $stmt = $db->prepare("
                INSERT INTO bien_immobilier
                    (bailleur_id, type, adresse, quartier, ville,
                     surface_m2, nb_pieces, loyer_mensuel, charges, statut, description)
                VALUES
                    (:bid, :type, :adresse, :quartier, :ville,
                     :surface, :pieces, :loyer, :charges, :statut, :desc)
            ");
            $stmt->execute([
                ':bid'     => (int)$body['bailleur_id'],
                ':type'    => trim($body['type']),
                ':adresse' => trim($body['adresse']),
                ':quartier'=> trim($body['quartier'] ?? ''),
                ':ville'   => trim($body['ville'] ?? 'Cotonou'),
                ':surface' => !empty($body['surface_m2']) ? (float)$body['surface_m2'] : null,
                ':pieces'  => !empty($body['nb_pieces'])  ? (int)$body['nb_pieces']    : null,
                ':loyer'   => (float)$body['loyer_mensuel'],
                ':charges' => (float)($body['charges'] ?? 0),
                ':statut'  => in_array($body['statut'] ?? 'libre', ['libre','occupe','travaux'])
                              ? $body['statut'] : 'libre',
                ':desc'    => trim($body['description'] ?? ''),
            ]);
            $newId = $db->lastInsertId();
            $db->prepare("INSERT INTO journal_activite
                          (utilisateur_id, action, table_cible, id_cible, ip)
                          VALUES (?, 'bien_cree', 'bien_immobilier', ?, ?)")
               ->execute([$user['id'], $newId, $_SERVER['REMOTE_ADDR'] ?? '']);
            created(['id' => $newId], 'Bien créé avec succès');
        } catch (Exception $e) {
            serverError('Erreur création : ' . $e->getMessage());
        }
        break;

    case 'PUT':
        if (!$id)                          badRequest('ID bien manquant');
        if (empty($body['bailleur_id']))   badRequest('Bailleur obligatoire');
        if (empty($body['type']))          badRequest('Type obligatoire');
        if (empty($body['adresse']))       badRequest('Adresse obligatoire');
        if (empty($body['loyer_mensuel'])) badRequest('Loyer mensuel obligatoire');

        try {
            $stmt = $db->prepare("
                UPDATE bien_immobilier SET
                    bailleur_id   = :bid,
                    type          = :type,
                    adresse       = :adresse,
                    quartier      = :quartier,
                    ville         = :ville,
                    surface_m2    = :surface,
                    nb_pieces     = :pieces,
                    loyer_mensuel = :loyer,
                    charges       = :charges,
                    statut        = :statut,
                    description   = :desc
                WHERE id = :id
            ");
            $stmt->execute([
                ':bid'     => (int)$body['bailleur_id'],
                ':type'    => trim($body['type']),
                ':adresse' => trim($body['adresse']),
                ':quartier'=> trim($body['quartier'] ?? ''),
                ':ville'   => trim($body['ville'] ?? 'Cotonou'),
                ':surface' => !empty($body['surface_m2']) ? (float)$body['surface_m2'] : null,
                ':pieces'  => !empty($body['nb_pieces'])  ? (int)$body['nb_pieces']    : null,
                ':loyer'   => (float)$body['loyer_mensuel'],
                ':charges' => (float)($body['charges'] ?? 0),
                ':statut'  => in_array($body['statut'] ?? 'libre', ['libre','occupe','travaux'])
                              ? $body['statut'] : 'libre',
                ':desc'    => trim($body['description'] ?? ''),
                ':id'      => $id,
            ]);
            $db->prepare("INSERT INTO journal_activite
                          (utilisateur_id, action, table_cible, id_cible, ip)
                          VALUES (?, 'bien_modifie', 'bien_immobilier', ?, ?)")
               ->execute([$user['id'], $id, $_SERVER['REMOTE_ADDR'] ?? '']);
            ok(['id' => $id], 'Bien modifié avec succès');
        } catch (Exception $e) {
            serverError('Erreur modification : ' . $e->getMessage());
        }
        break;

    case 'DELETE':
        if (!$id) badRequest('ID bien manquant');

        try {
            // Vérifier contrat actif ou suspendu
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM contrat_location
                WHERE bien_id = ? AND statut IN ('en_cours', 'suspendu')
            ");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                badRequest('Ce bien a un contrat actif — résiliez d\'abord le contrat avant de supprimer');
            }

            // Vérifier contrats historiques
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM contrat_location WHERE bien_id = ?");
            $stmt2->execute([$id]);
            $nbContrats = (int)$stmt2->fetchColumn();

            if ($nbContrats > 0) {
                // Archiver au lieu de supprimer
                $db->prepare("
                    UPDATE bien_immobilier
                    SET statut = 'travaux',
                        description = CONCAT(COALESCE(description, ''), ' [Archivé le " . date('d/m/Y') . "]')
                    WHERE id = ?
                ")->execute([$id]);
                $db->prepare("INSERT INTO journal_activite
                              (utilisateur_id, action, table_cible, id_cible, details, ip)
                              VALUES (?, 'bien_archive', 'bien_immobilier', ?, 'Archivé - historique contrat existant', ?)")
                   ->execute([$user['id'], $id, $_SERVER['REMOTE_ADDR'] ?? '']);
                ok(['id' => $id, 'archive' => true],
                   'Bien archivé (il possède un historique de contrats)');
            } else {
                // Suppression directe
                $db->prepare("DELETE FROM bien_immobilier WHERE id = ?")->execute([$id]);
                $db->prepare("INSERT INTO journal_activite
                              (utilisateur_id, action, table_cible, id_cible, ip)
                              VALUES (?, 'bien_supprime', 'bien_immobilier', ?, ?)")
                   ->execute([$user['id'], $id, $_SERVER['REMOTE_ADDR'] ?? '']);
                ok(null, 'Bien supprimé avec succès');
            }
        } catch (Exception $e) {
            serverError('Erreur suppression : ' . $e->getMessage());
        }
        break;

    default:
        badRequest('Méthode non autorisée');
}