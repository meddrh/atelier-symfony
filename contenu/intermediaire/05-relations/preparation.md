## Penser avant de coder

Jusqu'ici, tes entités vivaient chacune dans leur coin. Dans ce sujet, elles se **parlent** : un matériel est affecté à un utilisateur, et un matériel reçoit des **tickets d'incident**. C'est le premier sujet où la base de données a une structure, pas seulement des lignes, et où une mauvaise décision de départ coûte cher : changer le sens d'une relation après coup oblige à refaire la migration et les données.

Voici ce qu'on te demande de réaliser :

- un **ticket** a un titre, une description, un statut (`ouvert`, `en_cours` ou `resolu`) et une date de création ;
- chaque ticket concerne **un seul matériel**, et ne peut pas exister sans lui ;
- depuis la fiche d'un matériel, on peut **créer un ticket** pour lui ;
- la fiche du matériel **liste ses tickets**, du plus récent au plus ancien ;
- un matériel qui a des tickets **ne peut pas être supprimé** : l'utilisateur est prévenu.

Décide maintenant de ce dont tu as besoin, avant de voir comment la démonstration relie les matériels aux utilisateurs. Le piège n'est pas la syntaxe : c'est de savoir **quelle table porte la clé étrangère**, et ce qui se passe quand on supprime.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour la relation, dessine mentalement deux tableaux et la flèche entre eux : où met-on la colonne qui pointe vers l'autre ?
- Pour la suppression, demande-toi ce que la base ferait d'un ticket dont le matériel a disparu.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
