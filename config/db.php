<?php
    try{
        //Connexion à la base de donnée
    
        $db=new PDO ('mysql:host=127.0.0.1;dbname=lokatoo;charset=utf8mb4', 'root', '',
            [
                //options de connexion
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                //fetch mode par défaut : tableau associatif
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                //désactive l'émulation des requêtes préparées pour utiliser les vraies requêtes préparées de MySQL
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) { 
        //si la connexion échoue, le script s'arrête et affiche l'erreur
        die("Erreur de connexion : " . $e->getMessage());
    }
?>