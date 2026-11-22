## Penser avant de coder

Tes tickets d'incident sont utiles, mais un utilisateur voudrait **joindre une capture d'écran** de l'erreur, **ranger ses tickets par étiquettes** (« réseau », « urgent ») et ne pouvoir **changer le statut** qu'une fois le ticket créé. Ce sont trois choses nouvelles pour un formulaire : un **fichier** venu de l'extérieur, une valeur qui change de **forme** entre l'écran et l'objet, et un champ qui **dépend de l'état** de l'objet.

Voici ce qu'on te demande de réaliser :

- une **capture d'écran** (PNG ou JPEG, 2 Mo au plus), facultative, jointe à un ticket ;
- des **étiquettes** : l'utilisateur écrit `réseau, urgent`, le ticket garde la liste `['réseau', 'urgent']` ;
- un **statut** modifiable, mais **pas à la création** : un ticket naît toujours « ouvert » ;
- une page de **modification** d'un ticket ;
- chaque champ des formulaires du ticket entouré d'un élément de classe `champ`, comme pour le matériel.

Décide maintenant de ce dont tu as besoin, avant de voir comment la démonstration le fait pour la facture d'un matériel. Le piège de ce sujet est de **faire confiance au visiteur** : son fichier, son nom de fichier, et ce qu'il prétend envoyer.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour le fichier, imagine **un visiteur qui veut nuire** : que peut-il écrire dans le nom ? dans le contenu ? Qu'est-ce que tu enregistres en base, et qu'est-ce que tu ne reprends jamais de lui ?
- Pour les étiquettes, écris un exemple de ce que voit l'utilisateur et de ce que garde l'objet.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
