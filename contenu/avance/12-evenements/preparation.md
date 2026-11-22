## Penser avant de coder

Après la démonstration, ton application sait écouter les annonces du noyau et de la console. À toi d'**annoncer** quelque chose : ton **propre événement**.

Voici ce qu'on te demande de réaliser : garder la **trace** de chaque changement d'affectation d'un matériel.

- chaque changement écrit une ligne dans un **historique** : quel matériel, de qui, à qui, quand, et par qui (l'utilisateur connecté) ;
- le contrôleur **annonce** le changement par un événement `MaterielAffecteEvent`, **seulement** quand l'affectation a vraiment changé ;
- un **abonné** écoute cet événement et écrit la ligne ;
- une page `/materiels/{id}/historique` liste les changements, du plus récent au plus ancien.

Décide maintenant de ce dont tu as besoin, avant de lire la correction. Le piège de ce sujet : **le contrôleur ne doit pas écrire l'historique lui-même**. Il annonce ce qui s'est passé ; il ne sait pas, et ne doit pas savoir, qui écoute. Et un second piège : annoncer un changement qui **n'en est pas un** remplit l'historique de lignes inutiles.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour les données, relis la question de l'historique : « quoi, de qui, à qui, quand, par qui ». Lesquelles sont toujours connues ?
- Pour l'événement, cherche **où** et **quand** le contrôleur peut savoir qu'une valeur a changé.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
