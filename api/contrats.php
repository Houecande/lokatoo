<?php
// Désactiver l'affichage des erreurs HTML au milieu du flux pour éviter de casser le JSON VB.NET
error_reporting(E_ALL);
ini_set('display_errors', 0);

// En-têtes obligatoires pour une API REST
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gestion des requêtes de pré-vérification CORS (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -------------------------------------------------------------------------
// 1. CONNEXION À LA BASE DE DONNÉES
// -------------------------------------------------------------------------
$host = "localhost";
$db_name = "lokatoo"; 
$username = "root";
$password = "";

// ⚠️ CHANGE LE NOM ICI SI TA TABLE S'APPELLE AUTREMENT (ex: "contrat" sans "s")
$table = "contrats"; 

try {
    $pdo = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erreur de connexion à la base de données : " . $exception->getMessage()
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 2. TRAITEMENT DES REQUÊTES HTTP
// -------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ==========================================
    // CAS GET : Récupération des contrats
    // ==========================================
    case 'GET':
        try {
            // Requête SQL combinée pour récupérer les infos complètes nécessaires à ton DataGridView
            $query = "SELECT c.*, 
                             l.nom AS locataire_nom, l.prenom AS locataire_prenom,
                             b.titre AS bien_titre, b.type AS bien_type
                      FROM {$table} c
                      LEFT JOIN locataires l ON c.locataire_id = l.id
                      LEFT JOIN biens b ON c.bien_id = b.id
                      ORDER BY c.id DESC";
                      
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $contrats = $stmt->fetchAll();

            echo json_encode([
                "success" => true,
                "message" => "Liste des contrats récupérée.",
                "data" => $contrats
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erreur lors de la récupération : " . $e->getMessage()
            ]);
        }
        break;

    // ==========================================
    // CAS POST : Création d'un contrat
    // ==========================================
    case 'POST':
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data) {
                echo json_encode(["success" => false, "message" => "Données reçues malformées."]);
                exit;
            }

            // Validation minimale des champs obligatoires
            if (!isset($data['locataire_id']) || !isset($data['bien_id']) || !isset($data['date_debut'])) {
                echo json_encode(["success" => false, "message" => "Champs obligatoires manquants."]);
                exit;
            }

            $query = "INSERT INTO {$table} (locataire_id, bien_id, date_debut, date_fin, loyer_mensuel, montant_caution, frais_agence, statut) 
                      VALUES (:locataire_id, :bien_id, :date_debut, :date_fin, :loyer_mensuel, :montant_caution, :frais_agence, :statut)";
            
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([
                ':locataire_id'    => $data['locataire_id'],
                ':bien_id'         => $data['bien_id'],
                ':date_debut'      => $data['date_debut'],
                ':date_fin'        => $data['date_fin'] ?? null,
                ':loyer_mensuel'   => $data['loyer_mensuel'] ?? 0,
                ':montant_caution' => $data['montant_caution'] ?? 0,
                ':frais_agence'    => $data['frais_agence'] ?? 0,
                ':statut'          => $data['statut'] ?? 'en_cours'
            ]);

            if ($result) {
                echo json_encode([
                    "success" => true,
                    "message" => "Contrat créé avec succès.",
                    "data" => ["id" => $pdo->lastInsertId()]
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Échec de l'insertion."]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
        }
        break;

    // ==========================================
    // CAS PUT : Modification du contrat (Statut ou global)
    // ==========================================
    case 'PUT':
        try {
            // Récupérer l'ID passé en paramètre d'URL (?id=X)
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if ($id <= 0) {
                echo json_encode(["success" => false, "message" => "ID de contrat manquant ou invalide."]);
                exit;
            }

            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data) {
                echo json_encode(["success" => false, "message" => "Aucune donnée à modifier reçue."]);
                exit;
            }

            // CORRECTION SÉCURISÉE : Évite l'erreur "Undefined array key"
            $statut        = $data['statut'] ?? null;
            $loyer_mensuel = $data['loyer_mensuel'] ?? null;
            $montant_caution= $data['montant_caution'] ?? null;
            $date_debut    = $data['date_debut'] ?? null;
            $date_fin      = $data['date_fin'] ?? null;
            $frais_agence  = $data['frais_agence'] ?? null;

            // Détection : Si l'application envoie UNIQUEMENT le statut
            if (count($data) === 1 && isset($data['statut'])) {
                $query = "UPDATE {$table} SET statut = :statut WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute([
                    ':statut' => $statut,
                    ':id'     => $id
                ]);
            } else {
                // Sinon, c'est une mise à jour complète depuis un formulaire complet
                $query = "UPDATE {$table} SET 
                            date_debut = COALESCE(:date_debut, date_debut),
                            date_fin = COALESCE(:date_fin, date_fin),
                            loyer_mensuel = COALESCE(:loyer_mensuel, loyer_mensuel),
                            montant_caution = COALESCE(:montant_caution, montant_caution),
                            frais_agence = COALESCE(:frais_agence, frais_agence)";
                
                $params = [
                    ':date_debut'      => $date_debut,
                    ':date_fin'        => $date_fin,
                    ':loyer_mensuel'   => $loyer_mensuel,
                    ':montant_caution' => $montant_caution,
                    ':frais_agence'    => $frais_agence,
                    ':id'              => $id
                ];

                if ($statut !== null) {
                    $query .= ", statut = :statut";
                    $params[':statut'] = $statut;
                }

                $query .= " WHERE id = :id";
                
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute($params);
            }

            if ($result) {
                echo json_encode([
                    "success" => true,
                    "message" => "Contrat modifié avec succès.",
                    "data" => ["id" => $id]
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Aucune modification n'a été appliquée."]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Erreur lors de la modification : " . $e->getMessage()]);
        }
        break;

    // ==========================================
    // CAS PAR DÉFAUT
    // ==========================================
    default:
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "message" => "Méthode HTTP " . $method . " non autorisée."
        ]);
        break;
}