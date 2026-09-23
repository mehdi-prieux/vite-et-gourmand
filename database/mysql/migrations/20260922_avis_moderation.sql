-- À exécuter sur la base explicitement sélectionnée par l'opérateur.
-- Ne contient volontairement aucune instruction USE.
ALTER TABLE avis
    ADD COLUMN statut ENUM('en attente','validé','refusé') NOT NULL DEFAULT 'en attente' AFTER commentaire;

UPDATE avis SET statut = 'validé' WHERE valide = 1;
