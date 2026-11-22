## Penser avant de coder

Jusqu'ici, tes fournisseurs étaient écrits dans le contrôleur. Dans ce sujet, ils vont vivre dans une **base de données** : ils survivront à un rechargement, et on pourra en ajouter sans toucher au code. C'est le premier sujet où une erreur d'ordre coûte cher : si tu écris le contrôleur avant la table, rien ne s'affiche et les messages d'erreur ne disent pas pourquoi.

Voici ce qu'on te demande de réaliser :

- conserver pour chaque fournisseur un **nom**, une adresse **e-mail** (deux fournisseurs ne peuvent pas avoir la même) et un **téléphone** (facultatif) ;
- afficher la liste des fournisseurs **triés par nom** ;
- afficher la fiche d'un fournisseur, avec une page introuvable si l'identifiant n'existe pas.

Décide maintenant de ce dont tu as besoin, avant de voir comment la démonstration le fait pour le matériel.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte des attributs.
- Pour les champs, pense à trois questions : quel **type** ? **obligatoire** ou facultatif ? **unique** ou non ?
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
