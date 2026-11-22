## Penser avant de coder

Un responsable t'apporte un fichier CSV (un tableau enregistré en texte, une ligne par fournisseur) et te dit : « Peux-tu mettre ça dans l'application ? ». Saisir cinquante fournisseurs dans le formulaire du sujet précédent serait long et plein d'erreurs. La bonne réponse est une **commande de la console** : un petit programme que l'on lance dans un terminal, avec le fichier en paramètre.

Voici ce qu'on te demande de réaliser :

- une commande qui **lit un fichier CSV** de fournisseurs (colonnes : nom, adresse e-mail, téléphone) ;
- les lignes **valides** sont enregistrées en base ;
- les lignes **invalides** (nom vide, adresse e-mail invalide ou déjà utilisée) ne bloquent pas les autres : elles sont **signalées avec leur numéro de ligne**, pour que le responsable puisse corriger son fichier ;
- si le **fichier n'existe pas**, la commande s'arrête avec un message clair et un code de retour d'échec.

Une commande n'a ni page, ni formulaire, ni utilisateur devant l'écran : personne ne peut lui répondre en cours de route. Tout ce qu'elle a à dire passe par sa **sortie**, et le résultat (réussi ou non) par son **code de retour**, que d'autres programmes (une tâche planifiée, un script de déploiement) liront.

Décide maintenant de ce dont tu as besoin, avant de voir comment la démonstration le fait pour le matériel.

## Comment remplir la fiche

- Écris avec tes mots, comme à un collègue.
- Pense à ce qui existe déjà : tu as déjà écrit des règles de validation sur l'entité `Fournisseur`. Faut-il les réécrire ?
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
