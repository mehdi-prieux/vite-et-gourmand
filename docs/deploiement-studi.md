# Préparation du déploiement Studi

## Démonstration gratuite urgente : état et limites

InfinityFree est un candidat gratuit pour PHP 8.3, MySQL/MariaDB, HTTPS et un sous-domaine sans carte bancaire. Aucun compte d'hébergement ni URL applicative n'a encore été fourni ; aucune recette hébergée n'est validée. L'offre impose une création de compte et une validation personnelles. Elle bloque `mail()` PHP et n'autorise pas les variables d'environnement usuelles : l'application peut lire un `backend/config/local.php` non versionné, à créer sur l'hébergement depuis `local.php.example`. Ne saisir les véritables identifiants MySQL que dans ce fichier privé, jamais dans Git ni dans la copie Studi. Ne déployer qu'une base de démonstration sans données réelles et avec les seuls comptes de démonstration destinés au jury.

Parcours minimal une fois le compte créé : créer un sous-domaine gratuit, créer une base MySQL dédiée, importer une copie adaptée des SQL (remplacer `CREATE DATABASE`/`USE vite_gourmand` par la base attribuée par l'hébergeur), transférer les dossiers `frontend`, `backend` et les ressources nécessaires dans `htdocs`, puis configurer le site et ouvrir `https://<sous-domaine>/frontend/`. Tester d'abord la connexion MySQL et les sessions HTTPS, puis les parcours principaux avec une recette navigateur. Ne pas qualifier les e-mails ou les statistiques de validés : `mail()` est indisponible et la projection CouchDB actuelle est limitée à la destination locale de test approuvée. Aucun accès CouchDB distant ne doit être ajouté sans autorisation spécifique.

## Préconditions

Choisir un hébergeur PHP avec HTTPS, une base MySQL/MariaDB et une instance CouchDB 3 accessible uniquement à l'application. Une URL publique de l'hébergeur suffit ; un domaine personnalisé n'est pas exigé. Disposer d'une adresse d'envoi d'e-mails, d'une adresse de contact et des coordonnées légales réelles à publier. Faire une sauvegarde de toute base existante avant migration. Ne jamais utiliser les comptes de démonstration en production.

## Étapes de mise en ligne

1. Créer la base relationnelle et un utilisateur SQL à privilèges limités. Le script `database/mysql/create_database.sql` cible historiquement `vite_gourmand` : adapter explicitement le nom de la nouvelle base avant son exécution. Ne jamais exécuter le script de création sur une base contenant déjà des données.
2. Pour une base existante, examiner les migrations `database/mysql/migrations/` dans l'ordre, les appliquer après sauvegarde et vérifier chaque schéma. La recette automatisée ne doit être lancée que sur `vite_gourmand_test`.
3. Créer la base CouchDB documentaire et un compte réservé à cette base. Définir `COUCHDB_URL`, `COUCHDB_DATABASE`, `COUCHDB_USER` et `COUCHDB_PASSWORD`. Utiliser HTTPS pour une instance distante ; la base de recette NoSQL doit se terminer par `_test`.
4. Déployer les fichiers PHP et `frontend/`, puis définir toutes les variables de `.env.example` dans la configuration serveur. Configurer `APP_BASE_URL`, `CONTACT_EMAIL`, `MAIL_FROM` et un transport d'e-mail réellement opérationnel.
5. Configurer un service de géocodage et de routage adapté à la charge et aux conditions d'utilisation. Vérifier un cas Bordeaux et un cas hors Bordeaux.
6. La projection automatique actuelle est volontairement limitée à la destination locale de test approuvée. Pour une destination de production, demander l'accord sur l'URL, la base et les champs, adapter cette garde, puis exécuter une reprise idempotente de l'historique et comparer nombre de commandes et chiffre d'affaires avant l'ouverture au public. L'outil local de reprise `scripts/synchroniser_statistiques_test.php` ne cible que `vite_gourmand_test` et `vite_gourmand_stats_test`. Tant que la projection de production n'est pas activée et vérifiée, l'écran statistiques signale son indisponibilité.
7. Remplacer les mentions légales et CGV pédagogiques par les informations officielles validées. Vérifier les droits RGPD, l'adresse de contact, l'hébergeur et les conditions de retour du matériel.
8. Effectuer une recette sur la préproduction, contrôler les logs et la sécurité HTTPS, puis seulement publier. Fournir les liens publics du dépôt, de l'application et de l'outil de projet dans la copie à rendre Studi.

## Recette de préproduction à exécuter après déploiement, non réalisée à ce jour

| Parcours | Contrôle attendu |
|---|---|
| Public | Accueil, menu, filtres, fiche, avis publiés, horaires et liens vers mentions légales/CGV, contact reçu par e-mail. |
| Client | Inscription, connexion, profil, réinitialisation, création et modification/annulation autorisées, prix et frais de livraison, suivi et avis. |
| Personnel | Accès limité par rôle, commandes filtrées, transitions, notification de retour matériel, catalogue, horaires et modération. |
| Administration | Gestion des employés ; nombre de commandes et chiffre d'affaires issus de CouchDB avec filtres menu/période comparés à MySQL. |
| Technique | HTTPS et cookie de session sécurisé ; rejet des accès non autorisés et CSRF ; e-mails réellement délivrés ; responsive à 320 px et clavier ; absence de clés, comptes de démonstration, journaux et modes `APP_ENV=test`, `MAIL_TRANSPORT=log` ou `DELIVERY_TEST_DISTANCE_KM` sur l'instance publique. |

Avant toute campagne de recette avec écritures, utiliser **une base MySQL de préproduction isolée**, jamais `vite_gourmand`. La recette automatisée fournie n'accepte que `vite_gourmand_test` et vérifie `SELECT DATABASE()` ; elle ne doit pas être pointée vers la production. Le serveur PHP doit recevoir une indication HTTPS fiable de l'hébergeur afin que les sessions de production fonctionnent avec des cookies `Secure`.

## Retour arrière

Préparer une sauvegarde SQL et CouchDB, conserver l'artefact applicatif précédent, puis restaurer l'application et les bases ensemble si le nouveau schéma est incompatible. Ne jamais utiliser `git reset --hard` ou une suppression globale comme stratégie de retour arrière.

## Intervention du propriétaire nécessaire

Choisir l'hébergeur, créer les ressources MySQL et, si possible, CouchDB, fournir les coordonnées légales réelles de l'entreprise et configurer un transport e-mail réel. Le suivi public est disponible sur <https://github.com/mehdi-prieux/vite-et-gourmand/issues>. Le domaine personnalisé reste facultatif. Aucun secret ni identité juridique n'est inventé dans le dépôt.
