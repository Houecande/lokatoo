<?php
// api/decaissements.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "../config/database.php"; // Ton instance PDO (souvent nommée $db ou $pdo)
require_once "../includes/response.php"; // Tes fonctions de réponse standardisées

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

switch ($method) {
    case 'GET':
        // ==========================================
        // RECCUPÉRER LA LISTE DES DÉCAISSEMENTS
        // ==========================================
        try {
            // Jointure d'exemple pour afficher le nom du bailleur ou de l'utilisateur demandeur
            $query = "SELECT d.*, b.nom AS bailleur_nom, b.prenom AS bailleur_prenom 
                      FROM decaissements d
                      LEFT JOIN bailleurs b ON d.bailleur_id = b.id
                      ORDER BY d.date_demande DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $decaissements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "data" => $decaissements
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Erreur lors de la récupération : " . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Lecture du flux JSON envoyé par VB.NET
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // 1. ACTION : VALIDATION GÉRANT
        if ($action === 'valider_gerant' || $action === 'valider') {
            if ($id <= 0) {
                echo json_encode(["success" => false, "message" => "ID de décaissement manquant ou invalide."]);
                exit();
            }
            try {
                // Met à jour le statut en 'valide_gerant'
                $query = "UPDATE decaissements SET statut = 'valide_gerant', date_validation_gerant = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([':id' => $id]);

                echo json_encode(["success" => true, "message" => "Décaissement validé par le gérant avec succès."]);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Erreur lors de la validation gérant : " . $e->getMessage()]);
            }
        }
        
        // 2. ACTION : APPROBATION DIRECTEUR GÉNÉRAL (DG)
        elseif ($action === 'approuver_dg') {
            if ($id <= 0) {
                echo json_encode(["success" => false, "message" => "ID de décaissement manquant."]);
                exit();
            }
            try {
                // Met à jour le statut en 'approuve' ou 'termine'
                $query = "UPDATE decaissements SET statut = 'approuve_dg', date_approbation_dg = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([':id' => $id]);

                echo json_encode(["success" => true, "message" => "Décaissement approuvé par la Direction Générale."]);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Erreur lors de l'approbation DG : " . $e->getMessage()]);
            }
        }
        
        // 3. ENREGISTREMENT D'UNE NOUVELLE DEMANDE DE DÉCAISSEMENT
        else {
            if (empty($data['montant']) || empty($data['bailleur_id']) || empty($data['motif'])) {
                echo json_encode(["success" => false, "message" => "Champs obligatoires manquants (montant, bailleur_id, motif)."]);
                exit();
            }

            try {
                $query = "INSERT INTO decaissements (bailleur_id, montant, motif, statut, date_demande, agence_id) 
                          VALUES (:bailleur_id, :montant, :motif, 'en_attente', NOW(), :agence_id)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':bailleur_id' => intval($data['bailleur_id']),
                    ':montant'     => floatval($data['montant']),
                    ':motif'       => htmlspecialchars($data['motif']),
                    ':agence_id'   => isset($data['agence_id']) ? intval($data['agence_id']) : 1
                ]);

                echo json_encode(["success" => true, "message" => "Demande de décaissement enregistrée avec succès !"]);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Erreur lors de la création du décaissement : " . $e->getMessage()]);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Méthode non autorisée par l'API Lokatoo."]);
        break;
}