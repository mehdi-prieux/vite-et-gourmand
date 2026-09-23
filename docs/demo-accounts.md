# Comptes de démonstration

Le script `database/mysql/insert_data.sql` insère trois comptes dont les mots de passe sont des hashes bcrypt valides :

- client : `client@test.com` / `Client!Demo2026` ;
- employé : `employee@test.com` / `Employee!Demo2026` ;
- administrateur : `admin@test.com` / `Admin!Demo2026`.

Ces identifiants sont exclusivement destinés à une instance locale ou de démonstration. Ils doivent être remplacés avant toute mise en production.

L'inscription utilise `password_hash()` et la connexion `password_verify()`. Les trois rôles sont aussi couverts par le scénario fonctionnel automatisé, qui crée ses propres comptes temporaires dans `vite_gourmand_test` et les supprime après la campagne.

Ne jamais réutiliser ces comptes de démonstration en production.
