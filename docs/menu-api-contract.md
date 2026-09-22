# Contrat de l'API des menus

`backend/api/menu.php` doit retourner un tableau JSON de menus. Chaque objet comprend les colonnes de la table `menu` (`menu_id`, `titre`, `description`, `theme`, `regime`, `nombre_personnes_min`, `prix`, `conditions_menu`, `stock`, `disponible`) et un tableau `plats`.

Chaque élément de `plats` contient les champs de la table `plat` (`plat_id`, `nom`, `type_plat`, `description`). Un menu sans association dans `menu_plat` doit être retourné avec `plats: []`.

Les données de démonstration associent trois plats au menu Noël et aucun au menu Printemps. Les champs numériques renvoyés par PDO peuvent être des chaînes selon la configuration du pilote : les consommateurs doivent en tenir compte tant qu'un contrat de typage strict n'est pas défini.

Le endpoint ne réalise actuellement ni filtre ni pagination. Ce document décrit la cible de la prochaine correction du modèle, pas une fonctionnalité déjà testée.
