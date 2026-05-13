<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Importation des classes PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Tentative d'inclusion des fichiers PHPMailer (ils doivent être dans libs/phpmailer/)
// Note : Si ces fichiers sont manquants, l'envoi échouera.
require_once '../libs/phpmailer/PHPMailer.php';
// SMTP.php et Exception.php sont indispensables pour PHPMailer 6+
if (file_exists('../libs/phpmailer/SMTP.php')) require_once '../libs/phpmailer/SMTP.php';
if (file_exists('../libs/phpmailer/Exception.php')) require_once '../libs/phpmailer/Exception.php';

$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $erreur = "Veuillez entrer votre adresse email.";
    } else {
        $stmt = $db->prepare("SELECT id, nom, prenom FROM utilisateur WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expire = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $db->prepare("UPDATE utilisateur SET reset_token = :token, reset_expires = :expire WHERE id = :id");
            $stmt->execute([
                ':token' => $token,
                ':expire' => $expire,
                ':id' => $user['id']
            ]);

            $resetLink = baseUrl("auth/reset_password.php?token=$token");
            
            // Configuration SMTP
            $mailConfig = require '../config/mail.php';
            
            $mail = new PHPMailer(true);

            try {
                // Vérifier si SMTP.php est présent avant de tenter l'envoi SMTP
                if (!class_exists('PHPMailer\PHPMailer\SMTP')) {
                    throw new Exception("Le fichier libs/phpmailer/SMTP.php est manquant. Impossible d'envoyer via SMTP.");
                }

                $mail->isSMTP();
                $mail->Host       = $mailConfig['host'];
                $mail->SMTPAuth   = $mailConfig['auth'];
                $mail->Username   = $mailConfig['username'];
                $mail->Password   = $mailConfig['password'];
                $mail->SMTPSecure = $mailConfig['secure'];
                $mail->Port       = $mailConfig['port'];
                $mail->CharSet    = 'UTF-8';

                // Destinataires
                $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
                $mail->addAddress($email, $user['prenom'] . ' ' . $user['nom']);

                // Contenu
                $mail->isHTML(true);
                $mail->Subject = 'Réinitialisation de votre mot de passe - Lokatoo';
                $mail->Body    = "
                    <h2>Bonjour {$user['prenom']},</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte Lokatoo.</p>
                    <p>Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe (ce lien expire dans 1 heure) :</p>
                    <p><a href='{$resetLink}' style='padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px;'>Réinitialiser mon mot de passe</a></p>
                    <p>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.</p>
                ";

                $mail->send();
                $message = "Un lien de réinitialisation a été envoyé à votre adresse email.";
            } catch (Exception $e) {
                // En cas d'échec de l'envoi, on affiche quand même le lien pour le test (simulation de secours)
                $erreur = "L'email n'a pas pu être envoyé. Erreur : {$mail->ErrorInfo}";
                $message = "<strong>Mode test (Simulation) :</strong> <br><a href='$resetLink' style='color:var(--accent); font-weight:bold;'>Cliquez ici pour réinitialiser (Lien généré malgré l'erreur SMTP)</a>";
            }
        } else {
            $erreur = "Si cet email existe dans notre base, un lien de réinitialisation vous sera envoyé.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mot de passe oublié — Lokatoo</title>
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
        <h1>Récupération de <span>compte.</span></h1>
        <p>Entrez votre email pour recevoir un lien de réinitialisation.</p>
    </div>

    <div class="right">
        <button class="toggle-theme" onclick="toggleTheme()" id="themeBtn">
            <svg id="themeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
            <span id="themeText">Sombre</span>
        </button>

        <div class="card">
            <h2>Mot de passe oublié</h2>
            <p class="subtitle">Pas de panique, nous allons vous aider.</p>

            <?php if ($erreur): ?>
                <div class="alerte" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?= $erreur ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alerte" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="email">Adresse email</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="votre@email.com" required />
                    </div>
                </div>

                <button type="submit" class="btn-connect">Envoyer le lien</button>
            </form>

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
