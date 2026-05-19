<?php
// api/etats_lieux.php
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

switch ($method) {
    case 'GET':
        // RÉCUPÉRER TOUS LES ÉTATS DES LIEUX
        try {
            // Jointure pour avoir des informations explicites sur le contrat, le bien et le locataire
            $query = "SELECT el.*, c.code_contrat, b.titre AS bien_titre, l.nom AS locataire_nom, l.prenom AS locataire_prenom
                      FROM etats_lieux el
                      LEFT JOIN contrats c ON el.contrat_id = c.id
                      LEFT JOIN biens b ON c.bien_id = b.id
                      LEFT JOIN locataires l ON c.locataire_id = l.id
                      ORDER BY el.date_constat DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $etatsLieux = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "data" => $etatsLieux
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Erreur lors de la récupération : " . $e->getMessage()]);
        }
        break;

    case 'POST':
        // CRÉER UN NOUVEL ÉTAT DES LIEUX
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Validation des champs indispensables requis par le métier
        if (empty($data['contrat_id']) || empty($data['type_etat']) || empty($data['statut_general'])) {
            echo json_encode([
                "success" => false, 
                "message" => "Champs obligatoires manquants (contrat_id, type_etat, statut_general)."
            ]);
            exit();
        }

        // Le type d'état doit généralement être 'entree' ou 'sortie'
        $type_etat = htmlspecialchars($data['type_etat']);
        $statut_general = htmlspecialchars($data['statut_general']);
        $commentaires = isset($data['commentaires']) ? htmlspecialchars($data['commentaires']) : null;
        $contrat_id = intval($data['contrat_id']);
        
        // Permet de stocker l'état sous forme de JSON ou de chaîne textuelle structurée (ex: électricité: OK, murs: propre)
        $details_pieces = isset($data['details_pieces']) ? json_encode($data['details_pieces']) : null; 

        try {
            $db->beginTransaction();

            // 1. Insertion de l'état des lieux
            $queryInsert = "INSERT INTO etats_lieux (contrat_id, type_etat, date_constat, statut_general, details_pieces, commentaires) 
                            VALUES (:contrat_id, :type_etat, NOW(), :statut_general, :details_pieces, :commentaires)";
            
            $stmtInsert = $db->prepare($queryInsert);
            $stmtInsert->execute([
                ':contrat_id' => $contrat_id,
                ':type_etat' => $type_etat,
                ':statut_general' => $statut_general,
                ':details_pieces' => $details_pieces,
                ':commentaires' => $commentaires
            ]);

            // 2. Logique métier automatique : Si c'est un état des lieux d'entrée et qu'il est validé, on active le contrat
            if ($type_etat === 'entree' && ($statut_general === 'bon' || $statut_general === 'acceptable')) {
                $queryUpdateContrat = "UPDATE contrats SET statut = 'actif' WHERE id = :contrat_id AND statut = 'en_attente'";
                $stmtUpdate = $db->prepare($queryUpdateContrat);
                $stmtUpdate->execute([':contrat_id' => $contrat_id]);
            }

            $db->commit();
            echo json_encode(["success" => true, "message" => "L'état des lieux a été enregistré avec succès !"]);

        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(["success" => false, "message" => "Erreur lors de l'enregistrement de l'état des lieux : " . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Méthode non autorisée."]);
        break;
}