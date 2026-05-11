<?php

require_once '../config/db.php';
require_once '../includes/auth.php';

// Redirection automatique si l'utilisateur est déjà authentifié
if (estConnecte()) {
    redirectionParRole();
}

$erreur = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mdp   = trim($_POST['mot_de_passe'] ?? '');

    if (empty($email) || empty($mdp)) {
        $erreur = "Veuillez renseigner tous les champs obligatoires.";
    } else {
        // Recherche de l'utilisateur par email
        $stmt = $db->prepare("
            SELECT id, nom, prenom, email, mot_de_passe, role, actif
            FROM utilisateur
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Vérification des identifiants et du statut du compte
        if (!$user || !password_verify($mdp, $user['mot_de_passe'])) {
            $erreur = "Identifiants invalides. Veuillez réessayer.";
        } elseif (!$user['actif']) {
            $erreur = "Votre accès a été suspendu. Contactez votre administrateur.";
        } else {
            // Authentification réussie
            connecterUtilisateur($user);
            logActivite($db, 'connexion', 'utilisateur', $user['id'], 'Authentification réussie');
            redirectionParRole();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion — Lokatoo</title>
    
    <!-- Styles principaux -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <!-- Section Latérale (Hero) -->
    <div class="left">
        <div class="brand-wrapper">
            <div class="left-logo">
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
            <div class="badge-service">
                <span class="dot"></span> GESTION IMMOBILIÈRE
            </div>
        </div>

        <h1>Gérez vos biens,<br>vos locataires<br>et vos <span>paiements.</span></h1>
        <p>Plateforme tout-en un pour agences immoilières.</br>Simple rapide et sécurisé</p>
    </div>

    <!-- Section Authentification -->
    <div class="right">

        <!-- Sélecteur de thème -->
        <button class="toggle-theme" onclick="toggleTheme()" id="themeBtn">
            <svg id="themeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <!-- Moon icon (default) -->
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
            <span id="themeText">Sombre</span>
        </button>

        <div class="card">
            <h2>Bienvenue ✋</h2>
            <p class="subtitle">Veuillez vous identifier pour accéder à votre espace.</p>

            <!-- Notification d'erreur -->
            <?php if ($erreur): ?>
                <div class="alerte">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?= htmlspecialchars($erreur) ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire de connexion -->
            <form method="POST" action="">

                <div class="field">
                    <label for="email">Adresse email</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="votre@email.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autocomplete="email"
                        />
                    </div>
                </div>

                <div class="field">
                    <label for="mot_de_passe">Mot de passe</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        </span>
                        <input
                            type="password"
                            id="mot_de_passe"
                            name="mot_de_passe"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        />
                        <button type="button" class="eye-btn" onclick="toggleMdp()" id="eyeBtn" title="Afficher/Masquer le mot de passe">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>

                <div class="forgot">
                    <a href="#">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-connect">Se connecter</button>

            </form>

            <div class="card-footer">
                &copy; <?= date('Y') ?> Lokatoo &bull; Excellence en Gestion Immobilière
            </div>

        </div>
    </div>

    <script>
        /**
         * Alterne l'affichage du mot de passe
         */
        function toggleMdp() {
            const input = document.getElementById('mot_de_passe');
            const icon  = document.getElementById('eyeIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }

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
