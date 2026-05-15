<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user   = getTokenUser();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Corps JSON de la requête (POST/PUT) 
$body = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {

    case 'GET':
        $locataires = $db->query("
            SELECT
                l.id,
                l.nom,
                l.prenom,
                l.telephone,
                l.email,
                l.cni,
                l.profession,
                l.garant_nom,
                l.garant_telephone,
                l.garant_adresse,
                l.created_at,
                -- Contrat actif s'il existe
                cl.id        AS contrat_id,
                cl.loyer     AS contrat_loyer,
                cl.statut    AS contrat_statut,
                bi.adresse   AS bien_adresse,
                bi.type      AS bien_type,
                bi.quartier  AS bien_quartier
            FROM locataire l
            LEFT JOIN contrat_location cl
                ON cl.locataire_id = l.id AND cl.statut = 'en_cours'
            LEFT JOIN bien_immobilier bi
                ON bi.id = cl.bien_id
            ORDER BY l.nom ASC, l.prenom ASC
        ")->fetchAll();

        // Structurer le contrat actif en sous-objet
        $result = [];
        foreach ($locataires as $row) {
            $contrat = null;
            if ($row['contrat_id']) {
                $contrat = [
                    'id'      => $row['contrat_id'],
                    'loyer'   => $row['contrat_loyer'],
                    'statut'  => $row['contrat_statut'],
                    'adresse' => $row['bien_adresse'],
                    'type'    => $row['bien_type'],
                    'quartier'=> $row['bien_quartier'],
                ];
            }
            $result[] = [
                'id'               => $row['id'],
                'nom'              => $row['nom'],
                'prenom'           => $row['prenom'],
                'telephone'        => $row['telephone'],
                'email'            => $row['email'],
                'cni'              => $row['cni'],
                'profession'       => $row['profession'],
                'garant_nom'       => $row['garant_nom'],
                'garant_telephone' => $row['garant_telephone'],
                'garant_adresse'   => $row['garant_adresse'],
                'created_at'       => $row['created_at'],
                'contrat_actif'    => $contrat,
            ];
        }
        ok($result);
        break;

    case 'POST':
        // Champs obligatoires
        if (empty($body['nom']) || empty($body['prenom']) || empty($body['telephone'])) {
            badRequest('Nom, prénom et téléphone sont obligatoires');
        }

        $stmt = $db->prepare("
            INSERT INTO locataire
                (nom, prenom, telephone, email, cni, profession,
                 garant_nom, garant_telephone, garant_adresse)
            VALUES
                (:nom, :prenom, :telephone, :email, :cni, :profession,
                 :garant_nom, :garant_telephone, :garant_adresse)
        ");
        $stmt->execute([
            ':nom'              => trim($body['nom']),
            ':prenom'           => trim($body['prenom']),
            ':telephone'        => trim($body['telephone']),
            ':email'            => trim($body['email'] ?? ''),
            ':cni'              => trim($body['cni'] ?? ''),
            ':profession'       => trim($body['profession'] ?? ''),
            ':garant_nom'       => trim($body['garant_nom'] ?? ''),
            ':garant_telephone' => trim($body['garant_telephone'] ?? ''),
            ':garant_adresse'   => trim($body['garant_adresse'] ?? ''),
        ]);

        $newId = $db->lastInsertId();

        // Journal
        $db->prepare("INSERT INTO journal_activite (utilisateur_id, action, table_cible, id_cible, ip)
                      VALUES (?, 'locataire_cree', 'locataire', ?, ?)")
           ->execute([$user['id'], $newId, $_SERVER['REMOTE_ADDR'] ?? '']);

        created(['id' => $newId], 'Locataire créé avec succès');
        break;

    case 'PUT':
        if (!$id) badRequest('ID locataire manquant');

        if (empty($body['nom']) || empty($body['prenom']) || empty($body['telephone'])) {
            badRequest('Nom, prénom et téléphone sont obligatoires');
        }

        $stmt = $db->prepare("
            UPDATE locataire SET
                nom              = :nom,
                prenom           = :prenom,
                telephone        = :telephone,
                email            = :email,
                cni              = :cni,
                profession       = :profession,
                garant_nom       = :garant_nom,
                garant_telephone = :garant_telephone,
                garant_adresse   = :garant_adresse
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom'              => trim($body['nom']),
            ':prenom'           => trim($body['prenom']),
            ':telephone'        => trim($body['telephone']),
            ':email'            => trim($body['email'] ?? ''),
            ':cni'              => trim($body['cni'] ?? ''),
            ':profession'       => trim($body['profession'] ?? ''),
            ':garant_nom'       => trim($body['garant_nom'] ?? ''),
            ':garant_telephone' => trim($body['garant_telephone'] ?? ''),
            ':garant_adresse'   => trim($body['garant_adresse'] ?? ''),
            ':id'               => $id,
        ]);

        $db->prepare("INSERT INTO journal_activite (utilisateur_id, action, table_cible, id_cible, ip)
                      VALUES (?, 'locataire_modifie', 'locataire', ?, ?)")
           ->execute([$user['id'], $id, $_SERVER['REMOTE_ADDR'] ?? '']);

        ok(['id' => $id], 'Locataire modifié avec succès');
        break;

    case 'DELETE':
        if (!$id) badRequest('ID locataire manquant');

        // Vérifier qu'il n'a pas de contrat actif
        $contratActif = $db->prepare("
            SELECT COUNT(*) FROM contrat_location
            WHERE locataire_id = ? AND statut = 'en_cours'
        ");
        $contratActif->execute([$id]);
        if ($contratActif->fetchColumn() > 0) {
            badRequest('Impossible de supprimer : ce locataire a un contrat actif');
        }

        $db->prepare("DELETE FROM locataire WHERE id = ?")->execute([$id]);

        $db->prepare("INSERT INTO journal_activite (utilisateur_id, action, table_cible, id_cible, ip)
                      VALUES (?, 'locataire_supprime', 'locataire', ?, ?)")
           ->execute([$user['id'], $id, $_SERVER['REMOTE_ADDR'] ?? '']);

        ok(null, 'Locataire supprimé avec succès');
        break;

    default:
        badRequest('Méthode non autorisée');
}
