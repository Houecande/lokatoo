<?php
// api/personnel.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Chargement de ta configuration de base de données
require_once dirname(__DIR__) . "/config/db.php"; 

if (isset($pdo) && !isset($db)) {
    $db = $pdo;
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        try {
            // Utilisation de ta table réelle 'utilisateur'
            $query = "SELECT id, nom, prenom, email, telephone, role, actif FROM utilisateur WHERE role != 'locataire' ORDER BY nom ASC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(["success" => true, "data" => $personnel]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Erreur SQL : " . $e->getMessage()]);
        }
        break;
        
    case 'POST':
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        // Vérification des champs requis d'après ton schéma de base de données
        if (!empty($data['nom']) && !empty($data['prenom']) && !empty($data['email']) && !empty($data['mot_de_passe'])) {
            try {
                // Correspondance exacte avec les colonnes de ta table 'utilisateur'
                $query = "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role, actif, agence_id) 
                          VALUES (:nom, :prenom, :email, :mot_de_passe, :telephone, :role, 1, :agence_id)";
                
                // Sécurisation du mot de passe (hachage BCrypt conforme à la taille VARCHAR(255) de ta base)
                $hashedPassword = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
                
                // Mappage des rôles VB.NET vers les valeurs ENUM de ta base MySQL
                $roleFormulaire = isset($data['role']) ? strtolower(trim($data['role'])) : 'gerant';
                $roleEnum = 'gerant'; // Valeur par défaut
                
                if ($roleFormulaire === 'gerant' || $roleFormulaire === 'directeur_general' || $roleFormulaire === 'secretaire' || $roleFormulaire === 'agent_immobilier') {
                    $roleEnum = $roleFormulaire;
                }

                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nom' => htmlspecialchars($data['nom']),
                    ':prenom' => htmlspecialchars($data['prenom']),
                    ':email' => htmlspecialchars($data['email']),
                    ':mot_de_passe' => $hashedPassword,
                    ':telephone' => isset($data['telephone']) ? htmlspecialchars($data['telephone']) : null,
                    ':role' => $roleEnum,
                    ':agence_id' => 1 // ID de l'agence par défaut insérée par ton script SQL
                ]);

                echo json_encode(["success" => true, "message" => "Utilisateur créé avec succès dans l'agence !"]);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Erreur lors de l'insertion MySQL : " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Données incomplètes. 'nom', 'prenom', 'email' et 'mot_de_passe' sont obligatoires."]);
        }
        break;
        
    case 'PUT':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if ($id > 0) {
            try {
                $query = "UPDATE utilisateur SET nom = :nom, prenom = :prenom, email = :email, telephone = :telephone, role = :role WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nom' => htmlspecialchars($data['nom']),
                    ':prenom' => htmlspecialchars($data['prenom']),
                    ':email' => htmlspecialchars($data['email']),
                    ':telephone' => htmlspecialchars($data['telephone']),
                    ':role' => htmlspecialchars($data['role']),
                    ':id' => $id
                ]);
                echo json_encode(["success" => true, "message" => "Utilisateur mis à jour avec succès !"]);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Erreur lors de la modification : " . $e->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "ID d'utilisateur manquant."]);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            try {
                $query = "DELETE FROM utilisateur WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute([':id' => $id]);
                echo json_encode(["success" => true, "message" => "Utilisateur supprimé avec succès !"]);
            } catch (Exception $e) {
                echo json_encode(["success" => false, "message" => "Erreur lors de la suppression : " . $e->getMessage()]);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Méthode non autorisée."]);
        break;
}