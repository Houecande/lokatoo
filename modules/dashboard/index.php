<?php
/**
 * ============================================================
 *  TABLEAU DE BORD (DASHBOARD)
 * ============================================================
 * Gère l'affichage des statistiques et des accès rapides
 * en fonction du rôle de l'utilisateur connecté.
 */
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireConnexion();

$user = utilisateurConnecte();
$role = getRole();

// ── RÉCUPÉRATION DES STATISTIQUES ───────────────────────────

// Nombre total de locataires enregistrés
$nb_locataires = $db->query("SELECT COUNT(*) FROM locataire")->fetchColumn();

// Statistiques sur le parc immobilier
$nb_biens      = $db->query("SELECT COUNT(*) FROM bien_immobilier")->fetchColumn();
$nb_biens_libres = $db->query("SELECT COUNT(*) FROM bien_immobilier WHERE statut='libre'")->fetchColumn();

// Contrats de location en cours de validité
$nb_contrats   = $db->query("SELECT COUNT(*) FROM contrat_location WHERE statut='en_cours'")->fetchColumn();

// Flux financiers du mois courant
$mois_actuel   = date('Y-m-01');
$stmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(montant),0) FROM paiement WHERE mois_concerne = :mois AND statut='valide'");
$stmt->execute([':mois' => $mois_actuel]);
[$nb_paiements, $total_paiements] = $stmt->fetch(PDO::FETCH_NUM);

// Alertes : Impayés et décaissements en attente
$nb_impayes    = $db->query("SELECT COUNT(*) FROM impaye WHERE statut='en_cours'")->fetchColumn();
$nb_decaiss    = $db->query("SELECT COUNT(*) FROM decaissement WHERE statut NOT IN ('paye')")->fetchColumn();

// ── CONFIGURATION DU MENU LATÉRAL ───────────────────────────
$menus = [
    ['icon' => '👤', 'label' => 'Locataires',    'url' => baseUrl('modules/locataires/index.php'),
     'roles' => ['directeur_general','gerant','secretaire','agent_immobilier']],
    ['icon' => '🏘️', 'label' => 'Biens',          'url' => baseUrl('modules/biens/index.php'),
     'roles' => ['directeur_general','gerant','secretaire','agent_immobilier']],
    ['icon' => '🖋️', 'label' => 'Contrats',       'url' => baseUrl('modules/contrats/index.php'),
     'roles' => ['directeur_general','gerant','agent_immobilier']],
    ['icon' => '💵', 'label' => 'Paiements',      'url' => baseUrl('modules/paiements/index.php'),
     'roles' => ['directeur_general','gerant','secretaire','agent_immobilier']],
    ['icon' => '💼', 'label' => 'Bailleurs',      'url' => baseUrl('modules/bailleurs/index.php'),
     'roles' => ['directeur_general','gerant']],
    ['icon' => '📤', 'label' => 'Décaissements',  'url' => baseUrl('modules/decaissement/index.php'),
     'roles' => ['directeur_general','gerant','secretaire']],
    ['icon' => '📋', 'label' => 'États des lieux','url' => baseUrl('modules/entrees_sorties/index.php'),
     'roles' => ['directeur_general','gerant','agent_immobilier']],
    ['icon' => '✂️', 'label' => 'Résiliations',   'url' => baseUrl('modules/resiliations/index.php'),
     'roles' => ['directeur_general','gerant']],
    ['icon' => '👔', 'label' => 'Personnel',      'url' => baseUrl('modules/personnel/index.php'),
     'roles' => ['directeur_general']],
    ['icon' => '🏢', 'label' => 'Agence',         'url' => baseUrl('modules/agence/index.php'),
     'roles' => ['directeur_general']],
];

// Filtrage des menus selon les permissions du rôle
$menus_autorises = array_filter($menus, fn($m) => in_array($role, $m['roles'], true));

// Libellés lisibles pour les rôles
$labels_role = [
    'directeur_general' => 'Directeur Général',
    'gerant'            => 'Gérant',
    'secretaire'        => 'Secrétaire',
    'agent_immobilier'  => 'Agent Immobilier',
    'locataire'         => 'Locataire',
];
$label_role = $labels_role[$role] ?? $role;
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Tableau de bord — Lokatoo</title>
    <!-- Chargement des styles centralisés -->
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="dashboard-body">

<!-- ══════════════════════════════════════════════════════════
     SIDEBAR : Navigation latérale
     ══════════════════════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon">
            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                <path d="M22,16c-3.309,0-6,2.691-6,6s2.691,6,6,6s6-2.691,6-6S25.309,16,22,16z"/>
                <path d="M22,12c-5.514,0-10,4.486-10,10s4.486,10,10,10s10-4.486,10-10S27.514,12,22,12z M22,30c-4.411,0-8-3.589-8-8s3.589-8,8-8s8,3.589,8,8S26.411,30,22,30z"/>
                <path d="M44,22C44,9.85,34.15,0,22,0S0,9.85,0,22s9.85,22,22,22S44,34.15,44,22z M22,34c-6.617,0-12-5.383-12-12s5.383-12,12-12s12,5.383,12,12S28.617,34,22,34z"/>
                <path d="M63.414,50.586L44.13,31.302c-1.084,2.575-2.61,4.915-4.475,6.939L64,62.582V52C64,51.47,63.789,50.961,63.414,50.586z"/>
                <path d="M29.852,44.68l0.734,0.734C30.961,45.789,31.47,46,32,46h4v4c0,1.104,0.896,2,2,2h4v4c0,1.104,0.896,2,2,2h4v4c0,1.104,0.896,2,2,2h12.582l-24.34-24.344C35.844,41.862,32.997,43.59,29.852,44.68z"/>
            </svg>
        </div>
        <span class="brand-name">Loka<span>too</span></span>
    </div>

    <div class="sidebar-user">
        <div class="user-name"><?= e($user['prenom'] . ' ' . $user['nom']) ?></div>
        <div class="user-role"><?= e($label_role) ?></div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Général</div>
        <a class="nav-item active" href="index.php">
            <span class="nav-icon">📊</span> Tableau de bord
        </a>

        <?php foreach ($menus_autorises as $menu): ?>
        <a class="nav-item" href="<?= e($menu['url']) ?>">
            <span class="nav-icon"><?= $menu['icon'] ?></span>
            <?= e($menu['label']) ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a class="btn-logout" href="<?= baseUrl('auth/logout.php') ?>">
             <-- Deconnexion
        </a>
    </div>
</aside>

<!-- ══════════════════════════════════════════════════════════
     MAIN : Zone de contenu principal
     ══════════════════════════════════════════════════════════ -->
<div class="main">

    <!-- Topbar : Barre supérieure -->
    <div class="topbar">
        <span class="topbar-title">Vue d'ensemble</span>
        <div class="topbar-right">
            <button class="btn-theme" onclick="toggleTheme()" id="themeBtn">🌙 Sombre</button>
        </div>
    </div>

    <!-- Notifications flash (Succès/Erreur) -->
    <?php if (hasFlash('success')): ?>
        <div class="flash flash-success"><?= e(getFlash('success')) ?></div>
    <?php endif; ?>
    <?php if (hasFlash('error')): ?>
        <div class="flash flash-error"><?= e(getFlash('error')) ?></div>
    <?php endif; ?>

    <div class="content">

        <!-- En-tête de bienvenue -->
        <div class="greeting">
            <h1>👋 <?= e($user['prenom']) ?></h1>
            <p><?= date('l d F Y') ?> — <?= e($label_role) ?></p>
        </div>

        <!-- Cartes de statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Locataires 👤</div>
                <div class="stat-value stat-info"><?= $nb_locataires ?></div>
                <div class="stat-sub">inscrits</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Biens 🏘️</div>
                <div class="stat-value stat-accent"><?= $nb_biens ?></div>
                <div class="stat-sub"><?= $nb_biens_libres ?> disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Contrats 🖋️</div>
                <div class="stat-value stat-accent"><?= $nb_contrats ?></div>
                <div class="stat-sub">en vigueur</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Paiements 💵</div>
                <div class="stat-value stat-accent"><?= $nb_paiements ?></div>
                <div class="stat-sub"><?= number_format($total_paiements, 0, ',', ' ') ?> FCFA</div>
            </div>
            <?php if (aRole([ROLE_GERANT, ROLE_DG, ROLE_SECRETAIRE])): ?>
            <div class="stat-card">
                <div class="stat-label">Impayés 🛑</div>
                <div class="stat-value <?= $nb_impayes > 0 ? 'stat-danger' : 'stat-accent' ?>">
                    <?= $nb_impayes ?>
                </div>
                <div class="stat-sub">dossiers</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Décaissements 📤</div>
                <div class="stat-value stat-warning"><?= $nb_decaiss ?></div>
                <div class="stat-sub">à valider</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Raccourcis rapides -->
        <div class="section-title">Accès rapides</div>
        <div class="quick-grid">
            <?php foreach ($menus_autorises as $menu): ?>
            <a class="quick-card" href="<?= e($menu['url']) ?>">
                <div class="quick-icon"><?= $menu['icon'] ?></div>
                <div class="quick-label"><?= e($menu['label']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Alertes de gestion (Uniquement pour le staff) -->
        <?php if (aRole([ROLE_GERANT, ROLE_DG, ROLE_SECRETAIRE])): ?>
        <div class="section-title">Alertes & Suivi</div>
        <div class="alerts-grid">

            <!-- Liste des derniers impayés -->
            <div class="alert-box">
                <div class="alert-box-title">🛑 Retards de paiement</div>
                <?php
                $impayes = $db->query("
                    SELECT i.mois_concerne, i.montant,
                           l.nom, l.prenom
                    FROM impaye i
                    JOIN contrat_location c ON c.id = i.contrat_id
                    JOIN locataire l ON l.id = c.locataire_id
                    WHERE i.statut = 'en_cours'
                    ORDER BY i.mois_concerne ASC
                    LIMIT 5
                ")->fetchAll();
                if ($impayes): ?>
                    <?php foreach ($impayes as $imp): ?>
                    <div class="alert-item">
                        <span><?= e($imp['prenom'] . ' ' . $imp['nom']) ?></span>
                        <span class="badge badge-danger">
                            <?= number_format($imp['montant'], 0, ',', ' ') ?> FCFA
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert-item"><span style="color:var(--text-muted)">Tout est à jour 🎉</span></div>
                <?php endif; ?>
            </div>

            <!-- Liste des décaissements prioritaires -->
            <div class="alert-box">
                <div class="alert-box-title">📤 Reversements bailleurs</div>
                <?php
                $decaissements = $db->query("
                    SELECT d.periode, d.montant_net, d.statut,
                           b.nom, b.prenom
                    FROM decaissement d
                    JOIN bailleur b ON b.id = d.bailleur_id
                    WHERE d.statut != 'paye'
                    ORDER BY d.date_creation DESC
                    LIMIT 5
                ")->fetchAll();
                if ($decaissements): ?>
                    <?php foreach ($decaissements as $dec): ?>
                    <div class="alert-item">
                        <span><?= e($dec['prenom'] . ' ' . $dec['nom']) ?> — <?= e($dec['periode']) ?></span>
                        <span class="badge badge-warning">
                            <?= number_format($dec['montant_net'], 0, ',', ' ') ?> FCFA
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert-item"><span style="color:var(--text-muted)">Aucun reversement en attente</span></div>
                <?php endif; ?>
            </div>

        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->

<script>
    /**
         * Gestion du thème (Clair / Sombre)
         */
        function toggleTheme() {
            const html = document.documentElement;
            const text = document.getElementById('themeText');
            const icon = document.getElementById('themeIcon');
            
            if (html.dataset.theme === 'dark') {
                html.dataset.theme = 'light';
                text.textContent = 'Clair';
                icon.innerHTML = '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>';
                localStorage.setItem('theme', 'light');
            } else {
                html.dataset.theme = 'dark';
                text.textContent = 'Sombre';
                icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
                localStorage.setItem('theme', 'dark');
            }
        }

        /**
         * Initialisation du thème au chargement
         */
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.dataset.theme = savedTheme;
            
            const text = document.getElementById('themeText');
            const icon = document.getElementById('themeIcon');
            
            if (savedTheme === 'light') {
                text.textContent = 'Clair';
                icon.innerHTML = '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>';
            } else {
                text.textContent = 'Sombre';
                icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
            }
        })();
    </script>
</body>
</html>