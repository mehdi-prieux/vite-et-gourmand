# Configuration de la connexion MySQL

`backend/config/database.php` lit les variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` et `DB_PASSWORD` dans l'environnement du processus PHP. `.env.example` est un modèle documentaire : **le code ne charge pas automatiquement un fichier `.env`**.

Pour un déploiement, définir ces variables dans la configuration de l'hébergeur, utiliser un utilisateur MySQL dédié disposant uniquement des droits nécessaires à l'application et ne jamais versionner de véritables identifiants.

Pour un développement local sans variables définies, les valeurs de repli historiques (`localhost`, port `3306`, base `vite_gourmand`, utilisateur `root`, mot de passe vide) restent actives pour éviter de casser immédiatement l'installation existante. Elles ne sont **pas adaptées à la production**.

Le script `database/mysql/create_database.sql` est un script de création initiale, pas une migration : ne pas le réexécuter sur une base contenant déjà des tables ou des données. Faire une sauvegarde et tester sur une base isolée avant toute opération de migration. Aucune base de données n'a été modifiée depuis GitHub.
