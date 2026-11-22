## À faire

Tu sais écrire un test fonctionnel et vérifier ce qu'une action a produit. À toi d'écrire les tests des **tickets**, en suivant ta fiche de préparation.

1. Crée `tests/Junior/TicketTest.php` : une classe `App\Tests\Junior\TicketTest` qui étend `WebTestCase` et réutilise le trait `AvecBaseVierge` de la démonstration (`setUp()` : le client de test, puis la base vierge, puis un matériel).
2. Écris **au moins quatre tests**, sur ces quatre comportements de ton application :
   - la **liste** des tickets (`/fr/tickets`) répond correctement ;
   - une **création valide** (`/materiels/{id}/tickets/nouveau`) ;
   - un **titre vide** est refusé ;
   - le **filtre de statut** de la liste.
3. Chaque test **vérifie ce que l'action a produit**, pas seulement ce qu'elle a répondu. Pour chacun, finis cette phrase : « ce test devient rouge si… ».
4. Lance-les : `docker compose exec app php bin/phpunit tests/Junior/TicketTest.php`. Ils doivent passer.
5. **Casse ton application à la main**, un défaut après l'autre, et vérifie que **au moins un** de tes tests devient rouge à chaque fois, et qu'**au moins un** reste vert. Remets l'application en état.

Ce que fait l'exercice : il lance d'abord les tests de la démonstration, puis **tes** tests sur une copie saine de ton application, puis **tes** tests sur plusieurs copies de ton application où **un seul comportement a été cassé** à chaque fois. À chaque copie cassée, tes tests **doivent échouer**, mais **pas tous** : au moins un doit rester vert, ce qui prouve que la copie démarre et que l'échec vient bien du défaut. Si tous tes tests échouent, chacun regarde trop de choses à la fois : écris-en qui ne vérifient qu'un seul comportement. S'ils passent, le message te dit quelle version cassée leur a échappé, **sans te dire ce qui a été cassé** : c'est à toi de trouver ce que ton test ne vérifie pas.

Contraintes :

- tes tests lisent et écrivent dans la base de **test**, jamais dans celle de développement ;
- un test ne dépend d'aucun autre : chacun fabrique ses données ;
- ton application, elle, ne change pas : cet exercice ne demande aucune modification de `src/` ni de `templates/` ;
- les copies de ton application sont créées sous `var/` puis supprimées, même si un lancement échoue : l'exercice dure quelques dizaines de secondes au plus.

Un conseil sur l'ordre : écris un test, **vois-le vert**, puis casse à la main ce qu'il est censé protéger et **vois-le rouge**, avant d'écrire le suivant. Les défauts qui échappent à un test sont presque toujours des choses que le test **n'a pas regardées** : un effet en base, un texte à l'écran, un cas où une autre ligne ne devait pas être touchée.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Le fichier de tests de la démonstration existe et ses tests passent sur l'application.
- Tes tests des tickets (au moins quatre) existent et passent sur une copie saine de l'application.
- Tes tests échouent sur la première version cassée de l'application.
- Tes tests échouent sur la deuxième version cassée de l'application.
- Tes tests échouent sur la troisième version cassée de l'application.
- Tes tests échouent sur la quatrième version cassée de l'application.
- Tes tests échouent sur la cinquième version cassée de l'application.
