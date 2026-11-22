## Penser avant de coder

Après la démonstration, ton application sait **qui** se connecte. Il reste à décider **qui a le droit de quoi**, et à ouvrir une seconde porte, pour les **programmes** : un flux JSON qui ne s'ouvre qu'avec un **jeton**.

Voici ce qu'on te demande de réaliser :

- trois **rôles** qui s'emboîtent : technicien, gestionnaire, administrateur ;
- les tickets sont réservés aux techniciens (et à ceux qui les dépassent), la liste des utilisateurs aux administrateurs ;
- une page **« Mon compte »** qui montre l'utilisateur connecté, sans que le contrôleur ait à le chercher ;
- une adresse `/flux/mes-tickets` qui répond en **JSON** avec les tickets ouverts de l'utilisateur que désigne un jeton, envoyé dans l'en-tête `X-Jeton`.

Décide maintenant de ce dont tu as besoin, avant de lire la correction. Le piège de ce sujet : **le bouton caché ne protège rien**. Ce qui protège une page, c'est la règle qui s'exécute **avant** le contrôleur, quelle que soit la façon dont on atteint l'adresse. Et un jeton faux doit toujours recevoir la même réponse propre, jamais une erreur du serveur.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour les accès, imagine trois visiteurs : un anonyme, un utilisateur sans rôle, un technicien. Que voit chacun sur chaque page ?
- Pour le flux, pense à **l'appelant** : ce n'est pas une personne devant un navigateur.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
