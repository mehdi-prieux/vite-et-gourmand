-- À exécuter sur la base explicitement sélectionnée par l'opérateur.
-- Ne contient volontairement aucune instruction USE.
ALTER TABLE commande
    ADD COLUMN ville_livraison VARCHAR(100) NULL AFTER lieu_livraison,
    ADD COLUMN distance_km DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER ville_livraison,
    ADD COLUMN frais_livraison DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER distance_km;

UPDATE commande SET ville_livraison = 'Bordeaux' WHERE ville_livraison IS NULL;
