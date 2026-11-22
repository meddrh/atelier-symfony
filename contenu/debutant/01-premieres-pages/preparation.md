## Penser avant de coder

Dans ce sujet, tu vas créer les pages des **fournisseurs** du parc informatique : une page qui les liste, une page qui montre l'un d'eux. Avant de voir la démonstration (qui, elle, porte sur le matériel), prends quinze minutes pour **décider ce que tu vas construire**.

Pourquoi sur le papier d'abord ? Parce que la difficulté, ce n'est presque jamais la syntaxe : c'est de savoir **quoi créer et dans quel ordre**. Un développeur qui a répondu à ces questions écrit son code en ligne droite. Celui qui ne l'a pas fait écrit, efface, recommence.

Les trois fournisseurs, écrits en dur dans le code pour l'instant (la base de données arrive au sujet suivant) :

| Identifiant | Nom | Ville |
|---|---|---|
| 1 | Dupont Informatique | Lyon |
| 2 | Bureau Express | Nantes |
| 3 | Papeterie Martin | Lille |

## Un exemple de fiche déjà remplie

Pour voir ce qu'on attend, voici une fiche remplie pour **une autre page** que les tiennes : une page « À propos » qui affiche le nom du service (« Parc informatique ») et son responsable (« Léa Martin »), écrits en dur dans le code. Observe le **niveau de détail** : des phrases courtes, des noms précis, pas de code complet.

| Partie | Exemple de réponse |
|---|---|
| **Pages à créer** | Une seule page : « À propos ». Elle affiche le nom du service et le nom de son responsable. |
| **Routes** | `/a-propos`, nom `a_propos`. Pas de paramètre, donc aucune restriction à écrire. |
| **Fichiers à créer** | `src/Controller/AProposController.php` : une classe, une méthode `index`, qui donne les deux textes au gabarit. `templates/a_propos/index.html.twig` : il hérite de `templates/base.html.twig` et affiche les deux textes. |
| **Cas particuliers** | `/a-propos/equipe` : aucune route ne correspond, le serveur répond **404**. Il n'y a pas d'identifiant, donc pas de cas « inconnu » à gérer dans le contrôleur. |
| **Ordre des étapes** | 1. Le contrôleur et sa route, avec un texte simple : la page s'ouvre déjà. 2. Le gabarit, appelé par le contrôleur. 3. Les deux textes passés au gabarit. 4. Essayer `/a-propos` puis `/a-propos/equipe`. |

Ce que cette fiche fait bien, et que ta fiche doit faire aussi : elle donne le **nom de la route** et l'**adresse** ; elle nomme les **fichiers** et dit ce que fait chacun ; elle prévoit le cas où **ça ne marche pas** (404) ; elle commence par **ce qui se voit le plus vite** dans le navigateur.

## Comment remplir la fiche

- Tu n'as pas besoin de connaître la syntaxe exacte : écris avec tes mots, comme à un collègue.
- Chaque partie doit être remplie pour passer à la suite. Une réponse approximative vaut mieux qu'une case vide.
- Ce n'est pas noté. Après l'envoi, tu verras la fiche corrigée à côté de la tienne.
