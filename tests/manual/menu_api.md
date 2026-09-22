# Vérifications de l'API des menus

À effectuer sur une base de test isolée après import de `database/mysql/create_database.sql` et `database/mysql/insert_data.sql`.

1. Appeler `backend/api/menu.php` et vérifier que la réponse JSON contient les deux menus de démonstration, leurs prix, stocks, conditions et disponibilité.
2. Vérifier que « Menu Noël » contient les trois plats associés, avec leur type et leur description.
3. Vérifier que « Menu Printemps » contient une liste de plats vide : aucune association n'est insérée pour ce menu dans les données de démonstration.
4. Vérifier qu'un menu sans plats associés reste présent dans la réponse.
5. Vérifier que les caractères accentués sont correctement encodés en UTF-8.

Ces scénarios sont des tests manuels **à exécuter** : ils ne constituent pas des résultats de test.
