<?php
//  api/dashboard.php
//  GET /api/dashboard.php  → stats + paiements récents + biens libres
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    badRequest('Méthode non autorisée');
}

// Vérifier le token
$user = getTokenUser();

// Stats générales 
$nb_locataires = $db->query("SELECT COUNT(*) FROM locataire")->fetchColumn();
$nb_biens      = $db->query("SELECT COUNT(*) FROM bien_immobilier")->fetchColumn();
$nb_biens_libres = $db->query("SELECT COUNT(*) FROM bien_immobilier WHERE statut='libre'")->fetchColumn();
$nb_contrats   = $db->query("SELECT COUNT(*) FROM contrat_location WHERE statut='en_cours'")->fetchColumn();
$nb_impayes    = $db->query("SELECT COUNT(*) FROM impaye WHERE statut='en_cours'")->fetchColumn();

// Paiements du mois en cours
$mois = date('Y-m-01');
$stmt = $db->prepare("
    SELECT COUNT(*) as nb, COALESCE(SUM(montant), 0) as total
    FROM paiement
    WHERE mois_concerne = :mois AND statut = 'valide'
");
$stmt->execute([':mois' => $mois]);
$paie = $stmt->fetch();

// Paiements récents (10 derniers)
$paiements = $db->query("
    SELECT
        CONCAT(l.prenom, ' ', l.nom) AS locataire,
        p.montant,
        p.mode,
        DATE_FORMAT(p.date_paiement, '%d/%m/%Y') AS date_paiement,
        p.statut
    FROM paiement p
    JOIN contrat_location c ON c.id = p.contrat_id
    JOIN locataire l        ON l.id = c.locataire_id
    ORDER BY p.date_paiement DESC
    LIMIT 10
")->fetchAll();

// Biens disponibles 
$biens = $db->query("
    SELECT
        b.adresse,
        b.type,
        b.quartier,
        b.loyer_mensuel
    FROM bien_immobilier b
    WHERE b.statut = 'libre'
    ORDER BY b.created_at DESC
    LIMIT 10
")->fetchAll();

// Réponse
ok([
    'nb_locataires'          => (int)$nb_locataires,
    'nb_biens'               => (int)$nb_biens,
    'nb_biens_libres'        => (int)$nb_biens_libres,
    'nb_contrats'            => (int)$nb_contrats,
    'nb_impayes'             => (int)$nb_impayes,
    'nb_paiements_mois'      => (int)$paie['nb'],
    'total_paiements_mois'   => (float)$paie['total'],
    'paiements_recents'      => $paiements,
    'biens_disponibles'      => $biens,
]);