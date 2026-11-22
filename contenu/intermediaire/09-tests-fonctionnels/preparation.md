## Penser avant de coder

Jusqu'ici, des tests écrits **par d'autres** t'ont dit si ton code était bon. Cette fois, **tu écris les tests** de tes tickets, et l'exercice juge **tes tests** : il les lance sur ton application, puis sur plusieurs **copies où l'on a cassé quelque chose volontairement**. Un bon test passe quand l'application est saine, **et échoue quand elle ne l'est plus**. Un test qui passe toujours donne une fausse confiance : c'est pire que pas de test.

Voici ce qu'on te demande de réaliser, dans `tests/Junior/TicketTest.php` :

- la liste des tickets répond correctement ;
- une création valide **redirige** vers la fiche du matériel ;
- un titre vide est **refusé** ;
- le filtre de statut de la liste fait ce qu'il dit.

Décide maintenant de ce que chaque test vérifiera, avant de voir comment la démonstration le fait pour les matériels. Le piège de ce sujet : **vérifier la réponse et oublier ce qu'elle a produit**.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour chaque test, finis cette phrase : « ce test devient rouge si… ». Si tu ne sais pas la finir, le test ne vérifie sans doute rien.
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
