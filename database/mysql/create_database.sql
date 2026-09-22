CREATE DATABASE IF NOT EXISTS vite_gourmand;
USE vite_gourmand;

CREATE TABLE utilisateur (
    utilisateur_id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telephone VARCHAR(20),
    adresse VARCHAR(255),
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('utilisateur','employe','administrateur') DEFAULT 'utilisateur',
    actif BOOLEAN DEFAULT TRUE
);

CREATE TABLE menu (
    menu_id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150) NOT NULL,
    description TEXT,
    theme VARCHAR(50),
    regime VARCHAR(50),
    nombre_personnes_min INT NOT NULL,
    prix DECIMAL(10,2) NOT NULL,
    conditions_menu TEXT,
    stock INT DEFAULT 0,
    disponible BOOLEAN DEFAULT TRUE
);

CREATE TABLE image_menu (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id INT NOT NULL,
    chemin_image VARCHAR(255),
    FOREIGN KEY(menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE
);

CREATE TABLE plat (
    plat_id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    type_plat ENUM('entrée','plat','dessert'),
    description TEXT
);

CREATE TABLE menu_plat (
    menu_id INT,
    plat_id INT,
    PRIMARY KEY(menu_id,plat_id),
    FOREIGN KEY(menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
    FOREIGN KEY(plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE
);

CREATE TABLE allergene (
    allergene_id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100)
);

CREATE TABLE plat_allergene (
    plat_id INT,
    allergene_id INT,
    PRIMARY KEY(plat_id, allergene_id),
    FOREIGN KEY(plat_id) REFERENCES plat(plat_id),
    FOREIGN KEY(allergene_id) REFERENCES allergene(allergene_id)
);

CREATE TABLE commande (
    commande_id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    menu_id INT NOT NULL,
    date_prestation DATE,
    heure_livraison TIME,
    lieu_livraison VARCHAR(255),
    nombre_personnes INT,
    statut ENUM(
        'en attente',
        'accepté',
        'en préparation',
        'en cours de livraison',
        'livré',
        'en attente du retour matériel',
        'terminée'
    ) NOT NULL DEFAULT 'en attente',
    prix_total DECIMAL(10,2),
    FOREIGN KEY(utilisateur_id) REFERENCES utilisateur(utilisateur_id),
    FOREIGN KEY(menu_id) REFERENCES menu(menu_id)
);

CREATE TABLE suivi_commande (
    suivi_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT,
    ancien_statut VARCHAR(50),
    nouveau_statut VARCHAR(50),
    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(commande_id) REFERENCES commande(commande_id)
);

CREATE TABLE avis (
    avis_id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    commande_id INT,
    note INT CHECK(note BETWEEN 1 AND 5),
    commentaire TEXT,
    valide BOOLEAN DEFAULT FALSE,
    FOREIGN KEY(utilisateur_id) REFERENCES utilisateur(utilisateur_id),
    FOREIGN KEY(commande_id) REFERENCES commande(commande_id)
);

CREATE TABLE horaire (
    horaire_id INT AUTO_INCREMENT PRIMARY KEY,
    jour VARCHAR(20),
    heure_ouverture TIME,
    heure_fermeture TIME
);

CREATE TABLE commande_plat (
    commande_plat_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    plat_id INT NOT NULL,
    quantite INT DEFAULT 1,
    FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    FOREIGN KEY (plat_id) REFERENCES plat(plat_id)
);
