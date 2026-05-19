<?php
// api/resiliations.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "../config/database.php"; // Ton instance PDO ($db)
require_once "../includes/response.php";

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

switch ($method) {
    case 'GET':
        // RÉCUPÉRER TOUTES LES RÉSILIATIONS
        try {
            // Jointure pour obtenir les détails du contrat, du locataire et du bien concerné
            $query = "SELECT r.*, c.code_contrat, l.nom AS locataire_nom, l.prenom AS locataire_prenom, b.titre AS bien_titre
                      FROM resiliations r
                      LEFT JOIN contrats c ON r.contrat_id = c.id
                      LEFT JOIN locataires l ON c.locataire_id = l.id
                      LEFT JOIN biens b ON c.bien_id = b.id
                      ORDER BY r.date_demande DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $resiliations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "data" => $resiliations
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Erreur lors de la récupération : " . $e->getMessage()]);
        }
        break;

    case 'POST':
        // TRAITER UNE DEMANDE DE RÉSILIATION
        if ($action === 'traiter') {
            if ($id <= 0) {
                echo json_encode(["success" => false, "message" => "ID de résiliation manquant ou invalide."]);
                exit();
            }

            // Lecture du corps JSON envoyé par VB.NET (.statut et .observations)
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (empty($data['statut'])) {
                echo json_encode(["success" => false, "message" => "Le statut de traitement est obligatoire."]);
                exit();
            }

            try {
                $db->beginTransaction();

                // 1. Mettre à jour la table des résiliations
                $queryResiliation = "UPDATE resiliations 
                                     SET statut = :statut, 
                                         observations = :observations, 
                                         date_traitement = NOW() 
                                     WHERE id = :id";
                
                $stmtRes = $db->prepare($queryResiliation);
                $stmtRes->execute([
                    ':statut' => htmlspecialchars($data['statut']),
                    ':observations' => isset($data['observations']) ? htmlspecialchars($data['observations']) : null,
                    ':id' => $id
                ]);

                // 2. Si la résiliation est validée/approuvée, libérer le contrat et le bien
                if ($data['statut'] === 'approuvee' || $data['statut'] === 'validee') {
                    
                    // Récupérer le contrat_id lié à cette résiliation pour mettre à jour son statut
                    $queryGetContrat = "SELECT contrat_id FROM resiliations WHERE id = :id";
                    $stmtGet = $db->prepare($queryGetContrat);
                    $stmtGet->execute([':id' => $id]);
                    $resRow = $stmtGet->fetch(PDO::FETCH_ASSOC);

                    if ($resRow) {
                        $contratId = $resRow['contrat_id'];

                        // Rompre/Résilier le contrat
                        $queryUpdateContrat = "UPDATE contrats SET statut = 'resilie', date_fin_reelle = NOW() WHERE id = :contrat_id";
                        $stmtContrat = $db->prepare($queryUpdateContrat);
                        $stmtContrat->execute([':contrat_id' => $contratId]);

                        // Passer le bien correspondant en statut 'libre' ou 'disponible'
                        $queryUpdateBien = "UPDATE biens SET statut = 'disponible' WHERE id = (SELECT bien_id FROM contrats WHERE id = :contrat_id)";
                        $stmtBien = $db->prepare($queryUpdateBien);
                        $stmtBien->execute([':contrat_id' => $contratId]);
                    }
                }

                $db->commit();
                echo json_encode(["success" => true, "message" => "La demande de résiliation a été traitée avec succès !"]);

            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(["success" => false, "message" => "Erreur lors du traitement de la résiliation : " . $e->getMessage()]);
            }
        } else {
            // Optionnel : Ajout d'une route POST sans action si l'application permet de soumettre une demande initiale
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (empty($data['contrat_id']) || empty($data['motif'])) {
                echo json_encode(["success" => false, "message" => "Champs requis manquants (contrat_id, motif)."]);
                exit();
            }

            try {
                $queryNew = "INSERT INTO resiliations (contrat_id, motif, date_demande, statut) VALUES (:contrat_id, :motif, NOW(), 'en_attente')";
                $stmtNew = $db->prepare($queryNew);
                $stmtNew->execute([
                    ':contrat_id' => intval($data['contrat_id']),
                    ':motif' => htmlspecialchars($data['motif'])
                ]);

                echo json_encode(["success" => true, "message" => "Demande de résiliation soumise avec succès."]);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Erreur lors de la création de la demande : " . $e->getMessage()]);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Méthode non supportée."]);
        break;
}