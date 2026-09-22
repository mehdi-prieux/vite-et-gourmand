# Comptes de démonstration : état actuel

Le script `database/mysql/insert_data.sql` insère trois comptes (`client@test.com`, `employee@test.com` et `admin@test.com`) avec la même valeur littérale `$2y$10$password` dans `mot_de_passe`.

**Attention :** cette chaîne n'est pas un hash bcrypt utilisable. Aucun mot de passe de connexion fonctionnel ne peut être déduit de ces données. Ne pas communiquer de faux identifiants de démonstration dans le dossier Studi et ne pas présenter l'authentification comme testée.

## À faire avant la démonstration

1. Implémenter et tester l'authentification PHP avec `password_hash()` lors de la création d'un compte et `password_verify()` lors de la connexion.
2. Générer localement des mots de passe de démonstration distincts, puis leurs hashes via `password_hash()` ; ne publier que les hashes dans les données de test et transmettre les mots de passe de démonstration par un canal approprié.
3. Vérifier séparément les droits du client, de l'employé et de l'administrateur.
4. Mettre à jour le manuel utilisateur uniquement après réussite des tests de connexion.

Ne jamais réutiliser ces comptes de démonstration en production.
