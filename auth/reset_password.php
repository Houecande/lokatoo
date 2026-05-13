<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

$token = $_GET['token'] ?? '';
$erreur = '';
$success = false;

if (empty($token)) {
    header('Location: login.php');
    exit;
}

// Vérifier si le token est valide et non expiré
$stmt = $db->prepare("SELECT id FROM utilisateur WHERE reset_token = :token AND reset_expires > NOW() LIMIT 1");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch();

if (!$user) {
    $erreur = "Le lien de réinitialisation est invalide ou a expiré.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $mdp = $_POST['mot_de_passe'] ?? '';
    $conf = $_POST['confirm_mot_de_passe'] ?? '';

    if (strlen($mdp) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($mdp !== $conf) {
        $erreur = "Les mots de passe ne correspondent pas.";
    } else {
        $hash = password_hash($mdp, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE utilisateur SET mot_de_passe = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id");
        $stmt->execute([
            ':hash' => $hash,
            ':id' => $user['id']
        ]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Réinitialiser le mot de passe — Lokatoo</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
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
        </div>
        <h1>Nouveau <span>départ.</span></h1>
        <p>Choisissez un mot de passe robuste pour protéger vos données.</p>
    </div>

    <div class="right">
        <button class="toggle-theme" onclick="toggleTheme()" id="themeBtn">
            <svg id="themeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
            <span id="themeText">Sombre</span>
        </button>

        <div class="card">
            <h2>Réinitialisation</h2>
            
            <?php if ($success): ?>
                <div class="alerte" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                    Votre mot de passe a été réinitialisé avec succès ! <br>
                    <a href="login.php" style="color:var(--accent); font-weight:bold;">Connectez-vous maintenant</a>
                </div>
            <?php elseif ($erreur): ?>
                <div class="alerte" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?= $erreur ?>
                </div>
            <?php endif; ?>

            <?php if (!$success && $user): ?>
            <form method="POST">
                <div class="field">
                    <label for="mot_de_passe">Nouveau mot de passe</label>
                    <div class="input-wrap">
                        <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="••••••••" required />
                    </div>
                </div>

                <div class="field">
                    <label for="confirm_mot_de_passe">Confirmer le mot de passe</label>
                    <div class="input-wrap">
                        <input type="password" id="confirm_mot_de_passe" name="confirm_mot_de_passe" placeholder="••••••••" required />
                    </div>
                </div>

                <button type="submit" class="btn-connect">Enregistrer</button>
            </form>
            <?php endif; ?>

            <div class="card-footer" style="margin-top: 2rem; text-align: center;">
                <a href="login.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">&larr; Retour à la connexion</a>
            </div>
        </div>
    </div>

    <script>
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
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.dataset.theme = savedTheme;
            const text = document.getElementById('themeText');
            const icon = document.getElementById('themeIcon');
            if (savedTheme === 'light') {
                text.textContent = 'Clair';
                icon.innerHTML = '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>';
            }
        })();
    </script>
</body>
</html>
