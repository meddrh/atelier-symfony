## À faire

Tu sais écouter les événements du noyau et de la console. À toi de **créer le tien** : l'historique des affectations, en suivant ta fiche de préparation. Tes tests créent leurs propres utilisateurs : rien n'est à préparer en base.

1. **L'entité `HistoriqueAffectation`**, avec son dépôt (`HistoriqueAffectationRepository`) et une **migration nouvelle** (ne modifie jamais une ancienne ; relis ce qui est généré, joue-la depuis une base vide et vérifie que `doctrine:schema:validate` est satisfait ; le test `MigrationsTest` fait cette vérification pour toi et, quand il est rouge, **affiche le SQL qui manque**). Propriétés, avec ces noms :
   - `materiel` : obligatoire. L'historique d'un matériel supprimé disparaît avec lui ;
   - `ancienUtilisateur` et `nouvelUtilisateur` : facultatifs. Si un utilisateur est supprimé, la ligne reste et le champ revient à « personne » ;
   - `faitLe` : une date et heure (`\DateTimeImmutable`) ;
   - `auteur` : l'utilisateur connecté, facultatif, avec la même règle de suppression.
   - Une méthode de dépôt donne l'historique d'un matériel, du plus récent au plus ancien (à date égale, la ligne la plus récente d'abord).
2. **L'événement `App\Event\MaterielAffecteEvent`**, qui porte le matériel, l'ancien utilisateur et le nouvel utilisateur : `new MaterielAffecteEvent($materiel, $ancien, $nouvel)`, lus par `getMateriel()`, `getAncienUtilisateur()` et `getNouvelUtilisateur()`. Son nom, pour le répartiteur, est celui de **sa classe**.
3. **L'abonné** : il écoute l'événement et **enregistre** la ligne, avec l'utilisateur connecté comme auteur et le moment présent. Sans utilisateur connecté, l'auteur reste vide.
4. **L'émission, dans le contrôleur des matériels.**
   - `modifier()` annonce l'événement **seulement si l'affectation a changé** (y compris vers « personne »). Enregistrer le formulaire sans changer l'affectation n'annonce **rien** et n'écrit **aucune ligne**.
   - `nouveau()` l'annonce quand le matériel est créé **déjà affecté** (ancien : personne).
   - Le contrôleur **n'écrit pas** l'historique lui-même : il annonce, et c'est tout.
5. **La page** `/materiels/{id}/historique` (nom de route `materiel_historique`), du plus récent au plus ancien : date, ancien utilisateur (ou « personne »), nouvel utilisateur (ou « personne »), auteur (ou « inconnu »), **une ligne par `li` de classe `affectation`**. Un matériel inconnu donne 404 ; seul qui peut **consulter** le matériel la voit (un utilisateur simple reçoit 403 sur le matériel d'un autre). La fiche du matériel propose le lien « Historique des affectations ».

Contraintes :

- l'événement est annoncé **une seule fois** par changement, et jamais pour une affectation inchangée : testé en comptant les annonces ;
- chaque changement garde son auteur : testé avec un gestionnaire connecté ;
- un conseil : lance aussi tes tests des démonstrations (l'en-tête `X-Atelier`, la maintenance, la page « Matériel indisponible », la durée dans le journal, l'annonce du rebut) et ceux des sujets précédents (`tests/Junior` compris), pour voir tout de suite si un changement les casse ;
- le mode maintenance n'est **jamais** actif par défaut : sans variable `APP_MAINTENANCE`, toutes les pages répondent normalement.

Un conseil sur l'ordre : écris d'abord l'abonné et teste-le **seul**, en annonçant l'événement à la main (le répartiteur est le service `event_dispatcher`), avant de toucher au contrôleur. Si tu cherches quelle valeur comparer dans `modifier()`, rappelle-toi ce que le formulaire fait à l'objet **pendant** `handleRequest()`.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Chaque réponse porte l'en-tête `X-Atelier`, même une erreur 404.
- Le mode maintenance est inactif par défaut.
- Le mode maintenance répond 503 à toute requête, avec l'en-tête `Retry-After` et la page « Maintenance en cours ».
- Un matériel au rebut ne peut plus être affecté : page « Matériel indisponible » (409), rien n'est enregistré.
- Un matériel au rebut reste modifiable quand son affectation ne change pas.
- Les autres exceptions gardent leur page habituelle (404).
- La durée d'une commande de l'application est écrite dans le journal.
- Une ligne d'historique enregistre le matériel, l'ancien et le nouvel utilisateur, la date et l'auteur.
- L'événement porte le matériel et les deux utilisateurs.
- L'abonné enregistre la ligne, avec l'utilisateur connecté comme auteur.
- Changer l'affectation enregistre une ligne par changement (y compris vers « personne »), avec l'auteur.
- Aucune ligne n'est écrite quand l'affectation ne change pas.
- L'événement n'est annoncé que lorsque l'affectation change, une seule fois.
- Un matériel créé déjà affecté enregistre son affectation ; un matériel créé sans utilisateur n'écrit rien.
- La page d'historique liste du plus récent au plus ancien, avec « personne » et l'auteur, et ne montre que les lignes de ce matériel.
- La page d'historique sans ligne, l'accès sans connexion, l'accès refusé et le matériel inconnu.
- La fiche du matériel propose le lien vers l'historique.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
