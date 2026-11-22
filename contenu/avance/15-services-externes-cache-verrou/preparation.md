## Penser avant de coder

Après la démonstration, ton application sait appeler un service externe sans en dépendre, garder une réponse en mémoire, et dire au navigateur « rien n'a changé » par un code 304. À toi de l'appliquer à **l'historique des affectations** :

- l'**export CSV** (sujet 13) reçoit un **ETag** et répond **304** quand le navigateur a déjà la bonne version ;
- une commande **`app:historique:archiver`** envoie ce CSV à une API d'archivage ;
- cette commande ne doit **jamais tourner deux fois en même temps** : un second lancement simultané est refusé, avec un message et un code de sortie non nul ;
- et si l'API d'archivage est en panne ou trop lente, la personne qui lance la commande lit **une phrase claire**, pas une trace d'erreur.

Décide maintenant de ce dont tu as besoin, avant de lire la correction. Trois pièges : **de quoi calculer un ETag qui ne se trompe jamais**, **toutes les façons dont l'envoi peut échouer** (une panne et un délai dépassé ne sont pas la même exception), et **ce qui libère le verrou** quand la commande échoue.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour l'ETag, demande-toi tout ce qui peut changer **dans le fichier** : une affectation enregistrée, mais aussi un matériel ou un utilisateur renommé. Un résumé de l'historique seul les voit-il ?
- Pour l'archivage, énumère les cas d'échec **un par un** : chacun aura son test.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
