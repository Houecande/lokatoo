<?php
// api/impayes.php — GET
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_check.php';

getTokenUser();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') badRequest('Méthode non autorisée');

$rows = $db->query("
    SELECT i.*,
           CONCAT(l.prenom,' ',l.nom) AS locataire_nom,
           l.telephone                AS locataire_tel,
           bi.adresse                 AS bien_adresse,
           DATE_FORMAT(i.mois_concerne,'%m/%Y') AS mois_libelle
    FROM impaye i
    JOIN contrat_location cl ON cl.id = i.contrat_id
    JOIN locataire l         ON l.id  = cl.locataire_id
    JOIN bien_immobilier bi  ON bi.id = cl.bien_id
    WHERE i.statut = 'en_cours'
    ORDER BY i.mois_concerne ASC
")->fetchAll();
ok($rows);