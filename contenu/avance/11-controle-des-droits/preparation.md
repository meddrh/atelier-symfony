## Penser avant de coder

Après le sujet 10, ton application sait **qui** est connecté, et elle ferme les pages selon le **rôle**. Mais un rôle ne dit rien sur **un objet précis** : un technicien peut ouvrir les tickets, pourtant il ne doit **modifier que les siens**.

Voici ce qu'on te demande de réaliser :

- un ticket peut avoir un **technicien** (facultatif) ;
- un technicien ne modifie et ne clôture que **ses** tickets, un gestionnaire les modifie **tous**, **seul l'administrateur** supprime ;
- les boutons que l'utilisateur n'a pas le droit d'utiliser sont **masqués**, **et** l'appel direct de l'adresse reçoit un **403** ;
- un compte **désactivé** est refusé partout, **même s'il a le rôle `ROLE_ADMIN`**.

Décide maintenant de ce dont tu as besoin, avant de lire la correction. Deux pièges dans ce sujet. Le premier : **le bouton caché ne protège rien**, tu l'as déjà vu au sujet 10. Le second : quand plusieurs contrôles répondent à la même question, **comment combine-t-on leurs réponses** ? Si tu ne le décides pas, la valeur par défaut le décide pour toi.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour les droits, imagine quatre personnes face au même ticket : un technicien qui n'en est pas chargé, le technicien chargé, un gestionnaire, un administrateur.
- Pour le compte désactivé, cherche **qui** doit dire « non », et **ce qui se passe** quand un contrôle dit « oui » et un autre « non ».
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
