-- Sauvegarder la base avant application. Exécuter UNE SEULE FOIS sur une base existante.
-- Conserve les commandes existantes et leur statut ; les nouvelles demandes seront en attente.
ALTER TABLE commande
  MODIFY statut ENUM(
    'en attente',
    'accepté',
    'en préparation',
    'en cours de livraison',
    'livré',
    'en attente du retour matériel',
    'terminée'
  ) NOT NULL DEFAULT 'en attente';
