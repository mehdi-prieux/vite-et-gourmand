USE vite_gourmand;


-- UTILISATEURS

INSERT INTO utilisateur
(nom,prenom,email,telephone,adresse,mot_de_passe,role)
VALUES

('Dupont',
'Jean',
'client@test.com',
'0600000000',
'Bordeaux',
'$2y$12$VBVBOf1mbFEF2aU/DbhOXOZBt.c1kUf.dLp6iHZQjLbCmDIpp3Gvm',
'utilisateur'),


('Martin',
'Sophie',
'employee@test.com',
'0611111111',
'Bordeaux',
'$2y$12$BV4wNv5SlzdZzbwLBmkWB.7JeMEpq82sWMccJXuXCLZTruHAVRrK6',
'employe'),


('Admin',
'José',
'admin@test.com',
'0622222222',
'Bordeaux',
'$2y$12$pT8.y/TSkg5ZCaMiz7uemu8MKuRmlVY56z9OXXZ/n3hNfc7Gy058i',
'administrateur');



-- MENUS

INSERT INTO menu
(titre,description,theme,regime,nombre_personnes_min,prix,conditions_menu,stock)
VALUES

(
'Menu Noël',
'Menu complet pour les fêtes',
'Noël',
'classique',
10,
450,
'Commander 7 jours avant',
5
),

(
'Menu Printemps',
'Menu événementiel',
'événement',
'végétarien',
5,
250,
'Réservation obligatoire',
10
);



-- PLATS

INSERT INTO plat
(nom,type_plat,description)
VALUES

('Saumon fumé',
'entrée',
'Entrée froide'),

('Poulet rôti',
'plat',
'Plat principal'),

('Fondant chocolat',
'dessert',
'Dessert maison');



-- ASSOCIATION MENU PLAT

INSERT INTO menu_plat VALUES
(1,1),
(1,2),
(1,3);



-- ALLERGENES

INSERT INTO allergene(nom)
VALUES
('Gluten'),
('Lait'),
('Poisson');



INSERT INTO plat_allergene VALUES
(1,3),
(3,2);



-- HORAIRES

INSERT INTO horaire
(jour,heure_ouverture,heure_fermeture)
VALUES

('Lundi','09:00','18:00'),
('Mardi','09:00','18:00'),
('Mercredi','09:00','18:00'),
('Jeudi','09:00','18:00'),
('Vendredi','09:00','18:00');



-- COMMANDE TEST

INSERT INTO commande
(utilisateur_id,menu_id,date_prestation,heure_livraison,lieu_livraison,ville_livraison,nombre_personnes,prix_total)
VALUES

(1,1,'2030-01-15','12:00','1 place de la Bourse','Bordeaux',15,607.50);
