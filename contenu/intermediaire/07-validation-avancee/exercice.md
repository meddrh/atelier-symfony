## À faire

Tu sais ranger des règles dans des groupes, écrire une méthode de rappel et une contrainte personnalisée. À toi de faire respecter les règles des **tickets**, en suivant ta fiche de préparation.

1. **Deux propriétés** sur `Ticket` : `resolution` (texte long, facultatif en base) avec `getResolution()` et `setResolution()`, et `resoluLe` (date et heure immuable, facultative) avec `getResoluLe()` et `setResoluLe()`. Une migration les ajoute : génère-la avec `make:migration`, **sans l'exécuter**, et relis le fichier. Une colonne de date perd, avec `ALTER TABLE … ADD COLUMN`, le commentaire de type que Doctrine lit sous SQLite : **avant** de lancer `doctrine:migrations:migrate`, recrée donc la table `ticket` dans le fichier, comme aux sujets précédents. Vérifie ensuite avec `doctrine:schema:update --dump-sql --complete` : il doit répondre qu'il n'y a rien à mettre à jour. Le test `MigrationsTest` de ce sujet fait la même vérification sur une base vide, et quand il est rouge il **affiche le SQL qui manque** : lance-le (`docker compose exec app bin/exercice test`) avant de chercher à l'œil. Si tu as déjà exécuté la migration, défais-la avec `doctrine:migrations:migrate prev`, corrige le fichier, puis relance `doctrine:migrations:migrate`.
2. **La clôture** : la page `/tickets/{id}/cloturer` (route `ticket_cloturer`, `GET` et `POST`, 404 si le ticket est inconnu) affiche un formulaire avec **un seul champ**, la résolution, et un bouton « **Clôturer** ». La résolution est obligatoire, **uniquement** dans le groupe de validation `cloture` : le formulaire de clôture n'applique que ce groupe. Après un succès, le ticket passe à `resolu`, `resoluLe` reçoit l'instant présent, un message flash l'annonce, et on revient sur la fiche du matériel. Si la résolution est vide, le formulaire se réaffiche avec le code **422** et le message : `La résolution est obligatoire pour clôturer un ticket.`
3. **Pas ailleurs** : créer et modifier un ticket ne demandent jamais de résolution.
4. **La méthode de rappel** : une date de résolution antérieure à la date de création est une violation, rattachée à `resoluLe`, avec le message : `La date de résolution ne peut pas précéder la création du ticket.` La règle s'applique avec le groupe `Default` et avec le groupe `cloture`.
5. **La contrainte personnalisée** `TitreLisible`, dans `src/Validator/` avec son validateur `TitreLisibleValidator`, posée sur le titre du ticket :
   - moins de 5 caractères : `Le titre doit contenir au moins 5 caractères.` (le nombre vient d'un repère `{{ minimum }}` rempli par `setParameter()`) ;
   - écrit tout en majuscules (au moins une lettre, aucune minuscule) : `Le titre ne doit pas être écrit tout en majuscules.` ;
   - un titre vide n'est **pas** son affaire : elle l'ignore, et c'est `NotBlank` qui parle. Un titre vide ne produit donc qu'**une** seule erreur ;
   - les espaces autour du titre ne comptent pas dans la longueur.
6. **Le lien « Clôturer »** apparaît, dans la liste des tickets de la fiche du matériel, **seulement** pour les tickets qui ne sont pas déjà résolus. La résolution d'un ticket s'affiche à côté de lui, quand elle existe.

Contraintes :

- le bouton du formulaire de clôture s'appelle « **Clôturer** », celui des autres formulaires « **Enregistrer** » ;
- tous les messages sont en **français**, exactement ceux de l'énoncé ;
- ton application doit continuer à faire tout ce qu'elle faisait avant : plusieurs tests le vérifient, comme la démonstration.

Un conseil sur l'ordre : commence par `TitreLisible`, qui s'essaie dans le formulaire de création d'un ticket, puis les propriétés et la migration, et seulement ensuite la page de clôture. Essaie à la main : un titre `BUG`, un titre `IMPRIMANTE EN PANNE`, puis un titre normal.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Un numéro de série bien formé est accepté et enregistré en majuscules.
- Un matériel sans numéro de série reste valide.
- Un numéro de série mal formé (trop court, caractères interdits, tiret au début) est refusé (422) avec un message en français.
- La contrainte `NumeroDeSerie` et son validateur sont des classes de `App\Validator`.
- Le message de la violation cite la valeur refusée.
- La séquence de groupes ne montre que l'erreur du nom tant que le nom est vide.
- Le numéro de série est examiné dès que les erreurs de base sont corrigées.
- Le formulaire de mise au rebut demande un motif.
- Une mise au rebut sans motif est refusée (422) et ne change rien.
- Une mise au rebut avec motif est enregistrée et affichée sur la fiche.
- Un matériel affecté à un utilisateur ne peut pas être mis au rebut.
- Mettre au rebut un matériel inconnu répond 404.
- Le motif de mise au rebut n'est exigé ni à la création ni à la modification d'un matériel.
- La page de clôture demande une résolution.
- Une clôture sans résolution est refusée (422), et le ticket reste ouvert.
- Une clôture avec résolution passe le ticket à « résolu » et enregistre la date de résolution.
- Clôturer un ticket inconnu répond 404.
- La résolution n'est exigée ni à la création ni à la modification d'un ticket, mais l'est dans le groupe `cloture`.
- Une date de résolution antérieure à la création est une violation rattachée à `resoluLe`.
- Une date de résolution postérieure à la création est valide.
- Un titre trop court ou écrit tout en majuscules est refusé (422) avec le message prévu.
- Des titres lisibles (`Wi-Fi`, `Écran noir`, `Imprimante HS`, `Bug n° 404`) sont acceptés.
- La contrainte `TitreLisible` et son validateur sont des classes de `App\Validator`.
- Un titre vide ne produit qu'une seule erreur.
- La fiche du matériel propose « Clôturer » pour un ticket ouvert, pas pour un ticket résolu.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
