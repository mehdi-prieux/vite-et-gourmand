-- À exécuter sur la base explicitement sélectionnée par l'opérateur.
-- Ne contient volontairement aucune instruction USE.
CREATE TABLE reinitialisation_mot_de_passe (
    reinitialisation_id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expire_le DATETIME NOT NULL,
    utilise_le DATETIME NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE CASCADE,
    INDEX idx_reset_utilisateur (utilisateur_id),
    INDEX idx_reset_expiration (expire_le)
);
