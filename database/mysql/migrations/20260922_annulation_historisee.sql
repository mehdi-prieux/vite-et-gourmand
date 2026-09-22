-- À appliquer une seule fois sur une base existante, avant de déployer l'API d'annulation historisée.
-- Conserve les commandes et leurs références dans suivi_commande, avis et commande_plat.
USE vite_gourmand;

ALTER TABLE commande
    MODIFY COLUMN statut ENUM(
        'en attente',
        'accepté',
        'en préparation',
        'en cours de livraison',
        'livré',
        'en attente du retour matériel',
        'terminée',
        'annulée'
    ) NOT NULL DEFAULT 'en attente';
