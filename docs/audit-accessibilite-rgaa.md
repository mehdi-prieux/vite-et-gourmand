# Audit d'accessibilité RGAA — état au 22 septembre 2026

## Périmètre et méthode

Référence : RGAA 4.1.2, critères et tests publiés par la DINUM. Contrôle ciblé des parcours accueil, catalogue, fiche menu, contact, inscription, connexion/commande client, espace employé et administration. Inspection du HTML/CSS/JavaScript, navigation au clavier sur l'accueil et l'inscription, inspection des noms accessibles et vérification calculée de contrastes représentatifs. Cet examen ne constitue **pas** un audit exhaustif des 106 critères RGAA et ne permet pas d'afficher un taux de conformité réglementaire ; l'énoncé Studi ne demande pas un rapport d'audit exhaustif séparé.

## Constats et corrections

| Sujet RGAA | Constat | Correction / preuve | État |
|---|---|---|---|
| Structure et repères (9.1–9.2, 12.6) | Les nouvelles pages publiques ont un titre principal, une navigation et un contenu principal. Les espaces client et employé avaient un contenu de connexion sans repère principal ; plusieurs pages secondaires manquaient de lien d'évitement. | Un unique repère `main` et un lien « Aller au contenu » sur toutes les pages HTML, y compris annulation, inscription, espace et modification. Structure contrôlée par HTML Tidy et navigateur. | Corrigé ; contrôle lecteur d'écran restant. |
| Navigation clavier et focus (12.8, 10.7) | Sur l'accueil et l'inscription, le premier Tab atteint le lien « Aller au contenu ». Le contour jaune initial était trop peu contrasté sur le fond clair (environ 1,7:1). | Contour brun `#8a5300`, environ 6,2:1 sur le fond clair, appliqué aux pages publiques et espaces concernés. | Corrigé sur les vues contrôlées ; parcours clavier connecté restant. |
| Liens explicites (6.1) | Les cartes répétaient « Voir le détail » sans distinguer le menu dans le nom accessible. | Nom accessible « Voir le détail : [titre] » dans l'accueil et le catalogue. | Corrigé. |
| Information non exclusivement colorée (3.1) | Les erreurs de formulaire comportent un texte et les statuts sont libellés. | Inspection du code et contrôle ponctuel du rendu. | Contrôlé partiellement. |
| Formulaires (11.1–11.13) | Les champs principaux ont des libellés visibles et les messages dynamiques utilisent `role=status` ou `aria-live`. | Inspection des contrôles présents dans 14 pages : aucun champ sans libellé, bouton vide ou image sans `alt` trouvé dans les états non connectés. | Contrôlé sur cet échantillon ; états connectés et erreurs à compléter. |
| Contenu adaptable (10.11) | Grilles responsives et largeur limitée par `min()`/`max-width`. | Navigateur à 320 px : aucun débordement horizontal sur accueil, menus, fiche, contact, client, employé, admin, annulation, espace, inscription et modification. | Validé à 320 px sur ces vues ; zoom 200 % non documenté. |
| Horaires (10.1, contenu attendu) | Le pied de page omettait le week-end quand aucune ligne SQL n'existait. | Affiche désormais lundi à dimanche ; jours sans plage renseignée marqués « Fermé ». | Corrigé ; vérifier la réalité des horaires avec l'entreprise. |

## Limites à ne pas confondre avec une exigence de livrable supplémentaire

1. Tester au clavier tous les parcours représentatifs, notamment filtres, commande, modal éventuel et administration, sans piège de focus.
2. Tester avec VoiceOver/Safari et NVDA/Firefox les noms, états et annonces après chargement asynchrone.
3. Vérifier chaque image, icône, texte alternatif et média, y compris les données chargées depuis le catalogue.
4. Mesurer les contrastes de toutes les combinaisons réelles, y compris boutons désactivés, états de validation et contenus éditoriaux.
5. Vérifier 320 px, zoom 200 % et 400 %, orientation mobile, impression et préférences de mouvement.
6. Produire la grille complète des critères applicables/non applicables et un taux calculé avant toute déclaration de conformité RGAA. Le présent document n'est pas cette déclaration.

Référence officielle : <https://accessibilite.numerique.gouv.fr/methode/criteres-et-tests/>.
