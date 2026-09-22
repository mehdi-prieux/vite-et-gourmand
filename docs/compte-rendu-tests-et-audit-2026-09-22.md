# Compte rendu des tests et audit Studi — 22 septembre 2026

## 1. Références et règle de décision

L’audit se fonde en priorité sur :

1. `énoncé studi.pdf`, considéré comme le cahier des charges officiel ;
2. `studi.pdf`, utilisé comme réponse cible et aide à l’interprétation, sans considérer ses affirmations comme des preuves de réalisation ;
3. `brief_cowork_vite_et_gourmand.pdf`, utilisé uniquement pour reprendre le travail déjà effectué.

Une fonctionnalité est marquée **validée** seulement si son code est présent et si un contrôle exécutable ou visuel a réussi. Les textes des PDF ne sont pas traités comme des instructions d’exécution.

## 2. Sécurité des données et périmètre

- Projet : `/Users/mehdi/Desktop/Projets/vite-et-gourmand`.
- Branche constatée : `fix/database-config-env`.
- Base de recette unique : `vite_gourmand_test`.
- Avant toute campagne ou écriture directe, la connexion PDO a exécuté `SELECT DATABASE()` et vérifié la valeur exacte `vite_gourmand_test`.
- Le scénario automatisé refuse de démarrer si `DB_NAME` n’est pas explicitement égal à `vite_gourmand_test` (sortie 42), puis contrôle encore la base réellement sélectionnée.
- Ce garde-fou a été observé en fonctionnement : un lancement sans `DB_NAME` s’est arrêté avant toute écriture.
- Aucune commande de cette intervention ne s’est connectée à `vite_gourmand` pour écrire. Cette base n’a pas été modifiée.
- Les migrations nouvelles ont été appliquées uniquement à `vite_gourmand_test`.
- Contrôle final : zéro utilisateur, menu, plat ou intervention temporaire `codex.*`/`Codex` restant.
- La modification locale préexistante de `frontend/client.html`, notamment l’utilisation sûre de `byId('order-form').reset()`, a été conservée.
- Aucune commande Git destructive, aucun commit, aucune fusion et aucune fusion de pull request.

## 3. Anomalies corrigées

### Critique — collision du mot de passe applicatif avec le mot de passe MySQL

`backend/config/database.php` déclarait une variable générique `$password`. Son inclusion écrasait la variable métier des endpoints d’inscription et de connexion. L’application pouvait hacher et vérifier le mot de passe MySQL vide à la place du mot de passe saisi.

Correction : variables renommées en `$dbHost`, `$dbPort`, `$dbName`, `$dbUsername`, `$dbPassword`.

Preuve après correction : mauvais mot de passe → 401 ; bon mot de passe → 200 ; réinitialisation puis ancien mot de passe → 401.

### Haute — fuite de stock lors du nettoyage des tests

Le premier nettoyage supprimait des commandes non annulées sans restituer le stock. Le stock de test a été réparé uniquement dans `vite_gourmand_test`, puis le nettoyage a été rendu transactionnellement cohérent : chaque commande temporaire non annulée restitue exactement une unité.

Preuve : stock avant/après les scénarios d’annulation identique ; nouvelle campagne sans dérive.

### Haute — livraison hors Bordeaux absente

La commande refusait ou ignorait la livraison hors Bordeaux. Un calcul serveur a été ajouté : géocodage, distance routière, puis `5 € + 0,59 €/km`. En environnement de test, une distance déterministe explicitement configurée évite tout appel externe.

Preuve : Mérignac à 10 km → 10,90 € de livraison ; total recalculé côté serveur à la création et à la modification.

### Moyenne — erreur après connexion du personnel

Après une attente asynchrone, `event.currentTarget` devenait nul et l’interface affichait `Cannot read properties of null (reading 'reset')` malgré une connexion réussie.

Correction : conservation immédiate de la référence du formulaire avant les appels asynchrones.

Preuve : reconnexion navigateur avec le message « Connexion réussie » et aucun avertissement/erreur console.

### Fonctionnelles — lacunes comblées

- site public cohérent, pages menus et détail, filtres dynamiques, avis validés et horaires ;
- contact et e-mails testables sans envoi réel ;
- profil client, avis post-commande et modération ;
- mot de passe oublié avec jeton aléatoire haché, expirant et à usage unique ;
- gestion administrateur des employés ;
- création, modification et suppression des menus, plats et horaires ;
- composition des menus, allergènes et galerie d’images ;
- modification/annulation d’une commande par le personnel après contact et motif tracés ;
- e-mails de bienvenue, confirmation, retour de matériel et fin de commande ;
- statistiques administrateur filtrables ;
- hashes valides et mots de passe documentés pour les comptes de démonstration ;
- README et modèle de configuration remis en état.

## 4. Résultats de la recette fonctionnelle

Scénario reproductible : `tests/functional/api_http.sh`.

Résultat final : **PASS** sur `vite_gourmand_test`, données temporaires nettoyées.

### Public et comptes

- méthodes HTTP et types de contenu incorrects refusés ;
- catalogue, plats, allergènes, images, horaires et avis publics consultables ;
- seuls les avis validés sont publics ;
- formulaire de contact validé, erreurs 422 et e-mail journalisé ;
- inscription, unicité de l’e-mail, rôle client imposé et e-mail de bienvenue ;
- téléphone et adresse postale obligatoires côté serveur ;
- politique de mot de passe, hash bcrypt, connexion et refus d’un mauvais mot de passe ;
- profil consultable et modifiable ;
- demande de réinitialisation non énumérable, jeton 64 caractères, expiration et usage unique ;
- déconnexion et invalidation de session.

### Commande client

- menu disponible, minimum de convives et stock contrôlés ;
- prix proportionnel et remise de 10 % dès cinq personnes supplémentaires ;
- Bordeaux : 0 € de livraison ;
- hors Bordeaux : forfait et kilométrage ajoutés au total ;
- commande future créée, visible par son propriétaire et historisée ;
- modification uniquement en attente, sans changement de menu ;
- annulation uniquement en attente, historique conservé et stock restitué ;
- progression complète jusqu’à `terminée` ;
- avis 1–5 après fin, doublon refusé, modération puis publication.

### Personnel et administrateur

- séparation des rôles, compte actif recontrôlé en base et protection CSRF ;
- commandes filtrées par statut ou client ;
- transitions de statut autorisées et sauts refusés ;
- modification et annulation après contact e-mail/téléphone et motif obligatoire ;
- intervention d’employé historisée ;
- création/modification/suppression des menus, plats et horaires ;
- association des plats, des allergènes et d’une galerie ;
- suppression protégée si l’élément reste utilisé ;
- modération des avis ;
- création d’un employé par l’administrateur, notification sans mot de passe, activation/désactivation ;
- aucune création d’administrateur proposée par l’application ;
- statistiques filtrées par menu et dates, nombre de commandes et chiffre d’affaires.

## 5. Tests techniques

| Contrôle | Résultat |
|---|---|
| Syntaxe de tous les PHP avec PHP 8.5.10 | Validé |
| Syntaxe du script Bash de recette | Validé |
| `git diff --check` | Validé |
| HTML avec Tidy | Aucune erreur ; avertissements uniquement sur `minlength`, attribut HTML5 mal connu de cette version de Tidy |
| Contrôle navigateur accueil/catalogue public | Validé |
| Contrôle navigateur connexion personnel | Validé après correction |
| Contrôle navigateur catalogue personnel connecté | Validé : formulaires, édition, allergènes, galerie et horaires présents |
| Console navigateur sur les écrans contrôlés | Aucune erreur après correction |
| Vérification automatique JavaScript par Node | Non exécutée : Node.js absent de l’environnement |
| Test anti-production | Validé : arrêt avant écriture sans `DB_NAME=vite_gourmand_test` |
| Nettoyage final de la base de test | Validé : tous les compteurs temporaires à zéro |

## 6. Audit final par rapport à l’énoncé Studi

### Fonctionnalités validées

#### Visiteur

- accueil avec présentation de l’entreprise, ancienneté, professionnalisme, menus mis en avant et avis validés ;
- navigation vers accueil, menus, espaces de connexion et contact ;
- horaires dynamiques et liens légaux dans le pied de page ;
- catalogue accessible sans compte ;
- filtres dynamiques prix min/max, thème, régime et nombre de personnes ;
- fiche menu avec description, thème, régime, minimum, prix, stock, conditions mises en évidence, plats, types et allergènes ;
- galerie gérable et affichable ;
- bouton de commande avec menu présélectionné ;
- formulaire de contact envoyant un e-mail.

#### Client

- inscription avec identité, GSM, adresse, e-mail, mot de passe fort, rôle client et bienvenue par e-mail ;
- connexion, déconnexion et mot de passe oublié ;
- profil consultable/modifiable ;
- coordonnées du profil rappelées et adresse préremplie lors de la commande ;
- adresse, date, heure, ville, menu et convives ;
- minimum, remise et détail des frais de livraison calculés par le serveur ;
- commandes et historique consultables ;
- modification/annulation avant acceptation, menu non modifiable ;
- suivi des statuts ;
- e-mail à la fin et avis avec note/commentaire après terminaison.

#### Employé

- gestion complète des menus, plats, associations, allergènes, images, stock, disponibilité et horaires ;
- consultation et filtres des commandes ;
- modification/annulation avec mode de contact et motif obligatoires ;
- transitions de statut et historique ;
- notification de retour du matériel sous dix jours ouvrés avec mention des 600 € ;
- modération des avis.

#### Administrateur

- toutes les capacités du personnel ;
- création et activation/désactivation des employés ;
- e-mail de création sans mot de passe ;
- absence de création d’administrateur depuis l’application ;
- tableau et graphique de commandes par menu ;
- chiffre d’affaires avec filtres de menu et de période.

### Éléments partiels ou à faire valider avant production

- **Conditions de délai** : le texte des conditions est mis en avant, mais un délai tel que « commander sept jours avant » n’est pas un champ structuré et n’est donc pas bloqué automatiquement par le serveur.
- **Statistiques NoSQL, lors de la première recette** : l’écran et les filtres fonctionnaient alors sur MySQL. Cette observation historique a été corrigée ensuite : l'API lit maintenant CouchDB exclusivement. Aucun serveur CouchDB local n'était disponible pour une validation de bout en bout.
- **E-mail** : le transport de test par journal est validé. Le transport `mail()` de l’hébergeur n’a pas été testé dans un environnement public.
- **Distance** : le calcul externe OpenStreetMap/OSRM est codé. La recette utilise volontairement une distance déterministe ; la disponibilité, les quotas et les conditions d’usage des services publics doivent être validés ou remplacés avant production.
- **Retour matériel** : l’e-mail lors du passage au statut attendu et le texte « 10 jours ouvrés / 600 € » sont présents ; aucun traitement planifié ne détecte automatiquement le dépassement des dix jours.
- **Mentions légales et CGV** : pages accessibles, mais les identités juridiques, l’hébergeur, le responsable du traitement et les durées de conservation sont encore des contenus provisoires.
- **Accessibilité** : structure sémantique, labels, lien d’évitement, focus visible, annonces et réduction des animations présents ; aucun audit RGAA exhaustif ni test avec lecteur d’écran n’a été réalisé.
- **Interface administrateur** : activation/désactivation conforme à la notion de compte actif ; il n’existe pas de suppression physique, choix prudent mais à confirmer avec l’évaluateur.

### Éléments encore manquants au dossier Studi

- statistiques réellement alimentées et vérifiées sur une instance CouchDB ;
- déploiement public et preuves de fonctionnement sur l’hébergeur ;
- URL de dépôt public et URL de l’outil de gestion de projet à renseigner dans le dossier de rendu ;
- manuel utilisateur final au format PDF avec comptes de démonstration (produit depuis cet audit initial) ;
- charte graphique PDF avec couleurs, typographies et trois maquettes ordinateur + trois mobiles (produite depuis cet audit initial) ;
- dossier de gestion de projet : backlog/tâches, organisation, suivi et preuves (document local produit ; lien vers l'outil réel manquant) ;
- documentation technique finale : choix argumentés, environnement, MCD, cas d’utilisation, diagrammes de séquence et procédure de déploiement (documents locaux produits) ;
- validation juridique des mentions légales, de la politique de confidentialité et des CGV ;
- contrôles d'accessibilité pertinents et corrections documentées (audit ciblé produit depuis cet audit initial).

## 7. Décision de fin d’audit

Le parcours applicatif principal est désormais fonctionnel et couvert : visiteur, client, employé et administrateur. Les anomalies critiques constatées ont été corrigées sans toucher à la base `vite_gourmand` ni écraser les modifications locales.

Le projet ne doit cependant pas encore être présenté comme **totalement conforme et prêt à livrer** : la validation réelle CouchDB, le déploiement, les données juridiques réelles et les liens publics restent nécessaires. Les documents locaux ont été produits après ce premier bilan ; aucune publication ou fusion ne doit être entreprise sans décision explicite.

## Addendum de finalisation — 22 septembre 2026

Cet addendum actualise les constats ci-dessus sans effacer l'historique de la première recette.

- **Obligatoire selon l'énoncé (pages 9 à 12)** : les graphiques de commandes doivent lire une base non relationnelle. L'API de statistiques lit désormais CouchDB et répond 503 si ce service est absent ; elle n'affiche plus des données MySQL sous une étiquette NoSQL. La projection automatique est codée pour la seule destination locale et les seuls champs explicitement approuvés par le propriétaire ; l'outil de reprise historique est préparé mais n'a pas pu être exécuté faute de service CouchDB local. Exigence **non validée de bout en bout**.
- **Non exigé** : une relance automatique dix jours après le passage « en attente du retour matériel ». L'énoncé demande un e-mail lors du passage à ce statut rappelant le délai de dix jours ouvrés et les 600 EUR ; ce parcours a été validé dans la recette précédente. Aucun ordonnanceur supplémentaire n'est justifié.
- **Obligatoire** : manuel utilisateur PDF, charte graphique et six maquettes. Livrables produits dans `output/pdf/`, avec diagrammes et documentation dans `docs/`. Le lien vers un outil réel de gestion de projet reste à fournir.
- **Accessibilité demandée** : corrections ciblées consignées dans `docs/audit-accessibilite-rgaa.md`. Une déclaration de conformité globale n'est pas revendiquée ; l'énoncé ne demande pas un rapport d'audit exhaustif séparé.
- **Obligatoire avant publication** : contenus juridiques réels, transport e-mail, hébergement et URL publique, et autorisation du propriétaire. Un domaine personnalisé n'est pas requis. Les pages juridiques présentes restent explicitement provisoires.
- **Recommandé, non prescrit** : tests unitaires, tests navigateur automatisés et CI. Le scénario API complet offre déjà une recette de non-régression ; ne pas installer Node.js uniquement pour une chaîne d'outillage supplémentaire.
- **Non-régression après correction** : `SELECT DATABASE()` a retourné exactement `vite_gourmand_test`, puis `DB_NAME=vite_gourmand_test bash tests/functional/api_http.sh` a terminé `PASS` deux fois (commandes temporaires 46 et 50 nettoyées). Aucune écriture sur `vite_gourmand`.
- **Git** : modifications locales préservées, dont `byId('order-form').reset()` dans `frontend/client.html` ; aucun reset, commit, push, déploiement ou fusion de la PR n°2.
- **Synthèse détaillée pour remise** : voir `docs/matrice-recette-finale-studi.md` pour la matrice « Exigence | Implémentation | Preuve/test | Résultat | Action restante » et le regroupement de commits proposé.

## Addendum de poursuite — exigences strictement nécessaires

- CouchDB reste la solution NoSQL retenue. Le propriétaire a autorisé **uniquement** `http://127.0.0.1:5984/vite_gourmand_stats_test` pour les champs identifiant de commande, identifiant/titre du menu, date de prestation, statut et montant total. Les six mutations de commande concernées déclenchent maintenant une projection après validation SQL ; le service vérifie effectivement `SELECT DATABASE() = vite_gourmand_test`. Aucun nom, e-mail, téléphone ni adresse n'est sélectionné. La production n'est pas activée.
- L'outil `scripts/synchroniser_statistiques_test.php` a réussi en `--dry-run` : trois commandes SQL à reprendre. **Aucun `--apply` ni test de graphiques sur CouchDB réel n'a eu lieu** : CouchDB n'est pas installé localement, l'écriture dans Homebrew a été refusée par l'environnement, et le propriétaire préfère un hébergement CouchDB encore non choisi. Ne pas marquer l'exigence NoSQL validée.
- Le dépôt <https://github.com/mehdi-prieux/vite-et-gourmand> est public (`private=false`, branche par défaut `main`), mais les modifications locales ne sont ni commitées ni poussées. Branches distantes : `main`, `fix/database-config-env`, `fix/duplicate-commande-table` ; `develop` absente. La PR n°2 est ouverte et non fusionnée.

## Addendum de livraison urgente du 22 septembre 2026

Les constats ci-dessus décrivent l'état avant la préparation de livraison. Depuis, la recette HTTP complète a été rejouée avec succès sur `vite_gourmand_test`, après contrôle effectif de `SELECT DATABASE()` ; la commande temporaire a été nettoyée. La syntaxe de tous les fichiers PHP a été contrôlée. Les corrections ont été regroupées en commits thématiques API/SQL/tests puis interfaces, sans réinitialisation Git. Le suivi public a été ouvert dans [GitHub Issues](https://github.com/mehdi-prieux/vite-et-gourmand/issues) avec une issue fermée et trois ouvertes, sans faux historique. Une copie Studi éditable Word a été créée et relue visuellement ; elle reste locale et ignorée par Git, car elle contient l'identité du candidat. Le nom et la date de naissance ne sont pas publiés sur GitHub. CouchDB demeure non validé sur une instance réelle ; le serveur local n'écoute pas sur `127.0.0.1:5984` et son installation est bloquée par les permissions de ce Mac. Le déploiement public et l'envoi réel d'e-mails ne sont pas déclarés réussis.
- Les deux PDF ont été relus : manuel de 2 pages avec identifiants ; charte de 7 pages avec trois vues bureau et trois mobiles. Les mots de passe publiés correspondent aux hachages de `database/mysql/insert_data.sql`. Les comptes déjà existants dans `vite_gourmand_test` n'ont **pas** ces hachages ; ils ont été conservés sans modification.
- Accessibilité : contrôle navigateur de 14 pages pour champs non étiquetés, boutons vides et images sans `alt` dans les états non connectés ; aucun défaut trouvé. À 320 px, onze pages clés ne débordent pas. Le premier Tab sur l'inscription atteint le lien d'évitement. Ajout des liens d'évitement/repères et du focus visible aux pages secondaires concernées. Pas de déclaration RGAA globale.
- Liens de pied de page vers mentions légales et CGV vérifiés. La règle pédagogique des dix jours ouvrés et 600 EUR figure dans les CGV ; les identités légales et informations de traitement réelles restent à renseigner avant publication. Aucun SIRET fictif n'a été ajouté.
- Configuration durcie : `APP_ENV=test` refuse une base SQL différente de `vite_gourmand_test`, `APP_ENV=production` exige des paramètres SQL explicites et une adresse d'envoi réelle, et le transport de journalisation des e-mails est interdit hors test.
- Après ces modifications, `SELECT DATABASE()` a confirmé `vite_gourmand_test`, la recette API complète a terminé `PASS` (commande temporaire 58 nettoyée), la syntaxe PHP/Bash et `git diff --check` n'ont signalé aucune erreur.
