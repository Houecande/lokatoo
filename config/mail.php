<?php
// Configuration SMTP pour PHPMailer
return [
    'host'       => 'smtp.gmail.com',      // Hôte SMTP (ex: smtp.gmail.com)
    'auth'       => true,                  // Activer l'authentification SMTP
    'username'   => 'votre-email@gmail.com',// Nom d'utilisateur SMTP
    'password'   => 'votre-mot-de-passe',  // Mot de passe SMTP
    'secure'     => 'tls',                 // 'tls' ou 'ssl'
    'port'       => 587,                   // Port SMTP (587 pour TLS, 465 pour SSL)
    'from_email' => 'noreply@lokatoo.com', // Email de l'expéditeur
    'from_name'  => 'Lokatoo'              // Nom de l'expéditeur
];
