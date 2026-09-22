-- À exécuter sur la base explicitement sélectionnée par l'opérateur.
-- Ne contient volontairement aucune instruction USE.
CREATE TABLE intervention_commande (
    intervention_id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    employe_id INT NOT NULL,
    type_intervention ENUM('modification','annulation') NOT NULL,
    mode_contact ENUM('email','telephone') NOT NULL,
    motif TEXT NOT NULL,
    cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE,
    FOREIGN KEY (employe_id) REFERENCES utilisateur(utilisateur_id),
    INDEX idx_intervention_commande (commande_id)
);
