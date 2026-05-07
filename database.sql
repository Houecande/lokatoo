CREATE DATABASE IF NOT EXISTS lokatoo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lokatoo;

CREATE TABLE agence (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(150) NOT NULL,
    adresse         VARCHAR(255),
    telephone       VARCHAR(20),
    email           VARCHAR(150),
    logo            VARCHAR(255),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO agence (nom, adresse, telephone, email)
VALUES ('Agence Immobilière', 'Cotonou, Bénin', '+229 01 00 00 00 00', 'contact@agence.bj');

CREATE TABLE utilisateur (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255) NOT NULL,
    telephone       VARCHAR(20),
    role            ENUM('directeur_general','gerant','secretaire',
                         'agent_immobilier','locataire') NOT NULL,
    actif           TINYINT(1) NOT NULL DEFAULT 1,
    agence_id       INT UNSIGNED,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_agence FOREIGN KEY (agence_id)
        REFERENCES agence(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Compte DirecteurGeneral par défaut  (mot de passe : Admin@1234)
INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, agence_id)
VALUES ('Admin', 'DG',
        'dg@agence.bj',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'directeur_general', 1);

CREATE TABLE directeur_general (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id      INT UNSIGNED NOT NULL UNIQUE,
    date_prise_fonction DATE,
    CONSTRAINT fk_dg_user FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateur(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO directeur_general (utilisateur_id) VALUES (1);

CREATE TABLE gerant (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED NOT NULL UNIQUE,
    agence_id       INT UNSIGNED NOT NULL,
    CONSTRAINT fk_gerant_user   FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateur(id) ON DELETE CASCADE,
    CONSTRAINT fk_gerant_agence FOREIGN KEY (agence_id)
        REFERENCES agence(id)      ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE secretaire (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED NOT NULL UNIQUE,
    CONSTRAINT fk_sec_user FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateur(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE agent_immobilier (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED NOT NULL UNIQUE,
    numero_carte    VARCHAR(50),
    CONSTRAINT fk_agent_user FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateur(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bailleur (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    telephone       VARCHAR(20)  NOT NULL,
    email           VARCHAR(150),
    adresse         VARCHAR(255),
    piece_identite  VARCHAR(100),
    rib             VARCHAR(100),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE locataire (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED UNIQUE,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    telephone       VARCHAR(20)  NOT NULL,
    email           VARCHAR(150),
    cni             VARCHAR(50),
    profession      VARCHAR(100),
    garant_nom      VARCHAR(200),
    garant_telephone VARCHAR(20),
    garant_adresse  VARCHAR(255),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_locataire_user FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE bien_immobilier (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bailleur_id     INT UNSIGNED NOT NULL,
    type            ENUM('appartement','maison','villa',
                         'bureau','boutique','studio') NOT NULL,
    adresse         VARCHAR(255) NOT NULL,
    quartier        VARCHAR(100),
    ville           VARCHAR(100) DEFAULT 'Cotonou',
    surface_m2      DECIMAL(8,2),
    nb_pieces       TINYINT UNSIGNED,
    loyer_mensuel   DECIMAL(10,2) NOT NULL,
    charges         DECIMAL(10,2) DEFAULT 0,
    statut          ENUM('libre','occupe','travaux') NOT NULL DEFAULT 'libre',
    description     TEXT,
    photo           VARCHAR(255),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bien_bailleur FOREIGN KEY (bailleur_id)
        REFERENCES bailleur(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE contrat_location (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locataire_id    INT UNSIGNED NOT NULL,
    bien_id         INT UNSIGNED NOT NULL,
    agent_id        INT UNSIGNED NOT NULL,
    date_debut      DATE NOT NULL,
    date_fin        DATE,
    duree_mois      TINYINT UNSIGNED,
    loyer           DECIMAL(10,2) NOT NULL,
    caution         DECIMAL(10,2) DEFAULT 0,
    jour_echeance   TINYINT UNSIGNED DEFAULT 5,
    statut          ENUM('en_cours','termine','resilie','suspendu')
                    NOT NULL DEFAULT 'en_cours',
    observations    TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contrat_locataire FOREIGN KEY (locataire_id)
        REFERENCES locataire(id)        ON DELETE RESTRICT,
    CONSTRAINT fk_contrat_bien      FOREIGN KEY (bien_id)
        REFERENCES bien_immobilier(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_contrat_agent     FOREIGN KEY (agent_id)
        REFERENCES agent_immobilier(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE paiement (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrat_id      INT UNSIGNED NOT NULL,
    mois_concerne   DATE NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    mode            ENUM('especes','mtn','moov','celtiis') NOT NULL,
    statut          ENUM('en_attente','valide','echec','rembourse')
                    NOT NULL DEFAULT 'en_attente',
    reference_mm    VARCHAR(100),
    numero_mm       VARCHAR(20),
    callback_data   TEXT,
    enregistre_par  INT UNSIGNED,
    date_paiement   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recu_pdf        VARCHAR(255),
    CONSTRAINT fk_paiement_contrat FOREIGN KEY (contrat_id)
        REFERENCES contrat_location(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_paiement_agent   FOREIGN KEY (enregistre_par)
        REFERENCES agent_immobilier(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE impaye (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrat_id      INT UNSIGNED NOT NULL,
    mois_concerne   DATE NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    statut          ENUM('en_cours','regularise','annule')
                    NOT NULL DEFAULT 'en_cours',
    paiement_id     INT UNSIGNED,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_impaye_contrat  FOREIGN KEY (contrat_id)
        REFERENCES contrat_location(id) ON DELETE RESTRICT,
    CONSTRAINT fk_impaye_paiement FOREIGN KEY (paiement_id)
        REFERENCES paiement(id)         ON DELETE SET NULL
) ENGINE=InnoDB;


CREATE TABLE entree_sortie (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contrat_id          INT UNSIGNED NOT NULL,
    agent_id            INT UNSIGNED NOT NULL,
    type                ENUM('entree','sortie') NOT NULL,
    date_etat           DATE NOT NULL,
    etat_general        ENUM('bon','moyen','mauvais') NOT NULL DEFAULT 'bon',
    observations        TEXT,
    photos              TEXT,
    signature_locataire VARCHAR(255),
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_etat_contrat FOREIGN KEY (contrat_id)
        REFERENCES contrat_location(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_etat_agent   FOREIGN KEY (agent_id)
        REFERENCES agent_immobilier(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;


CREATE TABLE decaissement (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bailleur_id         INT UNSIGNED NOT NULL,
    periode             VARCHAR(7)   NOT NULL,
    montant_brut        DECIMAL(10,2) NOT NULL,
    commission_agence   DECIMAL(10,2) DEFAULT 0,
    montant_net         DECIMAL(10,2) NOT NULL,
    statut              ENUM('brouillon','soumis','valide_gerant',
                             'approuve_dg','paye') NOT NULL DEFAULT 'brouillon',
    cree_par            INT UNSIGNED,
    valide_par          INT UNSIGNED,
    approuve_par        INT UNSIGNED,
    date_creation       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_validation     TIMESTAMP NULL,
    date_approbation    TIMESTAMP NULL,
    date_paiement       TIMESTAMP NULL,
    mode_versement      ENUM('virement','especes','mobile_money'),
    reference_versement VARCHAR(100),
    observations        TEXT,
    CONSTRAINT fk_decaiss_bailleur   FOREIGN KEY (bailleur_id)
        REFERENCES bailleur(id)            ON DELETE RESTRICT,
    CONSTRAINT fk_decaiss_secretaire FOREIGN KEY (cree_par)
        REFERENCES secretaire(id)          ON DELETE SET NULL,
    CONSTRAINT fk_decaiss_gerant     FOREIGN KEY (valide_par)
        REFERENCES gerant(id)              ON DELETE SET NULL,
    CONSTRAINT fk_decaiss_dg         FOREIGN KEY (approuve_par)
        REFERENCES directeur_general(id)   ON DELETE SET NULL
) ENGINE=InnoDB;


CREATE TABLE journal_activite (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED,
    action          VARCHAR(100) NOT NULL,
    table_cible     VARCHAR(50),
    id_cible        INT UNSIGNED,
    details         TEXT,
    ip              VARCHAR(45),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_user FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB;


CREATE INDEX idx_bien_bailleur     ON bien_immobilier(bailleur_id);
CREATE INDEX idx_contrat_locataire ON contrat_location(locataire_id);
CREATE INDEX idx_contrat_bien      ON contrat_location(bien_id);
CREATE INDEX idx_contrat_agent     ON contrat_location(agent_id);
CREATE INDEX idx_paiement_contrat  ON paiement(contrat_id);
CREATE INDEX idx_paiement_mois     ON paiement(mois_concerne);
CREATE INDEX idx_impaye_contrat    ON impaye(contrat_id);
CREATE INDEX idx_etat_contrat      ON entree_sortie(contrat_id);
CREATE INDEX idx_decaiss_bailleur  ON decaissement(bailleur_id);
CREATE INDEX idx_decaiss_statut    ON decaissement(statut);
CREATE INDEX idx_journal_user      ON journal_activite(utilisateur_id);