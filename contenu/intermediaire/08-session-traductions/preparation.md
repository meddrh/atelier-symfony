## Penser avant de coder

Ton application sait lister des tickets sur la fiche d'un matériel. Il manque **la vue d'ensemble** : une page qui liste **tous** les tickets, qu'on peut **filtrer par statut**, qui se souvient du filtre choisi, et qui parle **français et anglais**. Trois idées nouvelles : une valeur qui **survit d'une requête à l'autre**, une langue qui fait partie de l'**adresse**, et un compteur dont la **phrase change** selon le nombre.

Voici ce qu'on te demande de réaliser :

- une page `/fr/tickets` et `/en/tickets` ; l'adresse `/tickets` sans langue redirige vers `/fr/tickets` ;
- un **filtre par statut** (`ouvert`, `en_cours`, `resolu`, ou `tous`), mémorisé en session ; par défaut, les tickets ouverts ;
- chaque ticket affiche son statut **traduit** ;
- un **compteur** dont la phrase s'accorde : « Aucun ticket ouvert », « 1 ticket ouvert », « 3 tickets ouverts », et ses équivalents anglais ;
- un lien depuis la fiche d'un matériel.

Décide maintenant de ce dont tu as besoin, avant de voir comment la démonstration le fait pour la liste des matériels. Le piège de ce sujet : **ce qui n'est pas vérifié finit par s'afficher** : une valeur de session inattendue, une clé de traduction manquante, un pluriel qui dit « 0 ticket ouverts ».

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour la session, demande-toi ce qui peut s'y trouver **autre** que ce que tu y as écrit.
- Pour les traductions, écris **les phrases** avant les clés : lis-les à voix haute pour 0, 1 et 3 tickets, en français puis en anglais.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
