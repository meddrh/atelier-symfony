# Atelier Symfony

Plateforme d'apprentissage de Symfony 5.4 : une courte séance chaque matin, un sujet à la fois.
Chaque sujet suit le même déroulé : préparer, regarder la démonstration, faire l'exercice.

## Installation

**Première installation : suis le guide pas à pas [INSTALLATION.md](INSTALLATION.md)** (Windows, macOS,
Linux, vérification et problèmes courants). Le résumé ci-dessous sert d'aide-mémoire.

Il faut seulement Docker (et git).

Mohammed te transmet une branche `livraison` : soit l'adresse d'un dépôt privé, soit un fichier
`livraison.bundle`. Tu travailles toujours sur ta propre branche, `travail`, jamais sur `livraison`.

```sh
# avec un dépôt privé :
git clone --branch livraison <adresse du dépôt> atelier
# ou avec le fichier reçu :
git clone --branch livraison livraison.bundle atelier

cd atelier
git switch -c travail          # ta branche : c'est là que tu codes et que tu valides ton travail
```

Sous Linux, si ton identifiant n'est pas 1000 (`id -u`), règle-le avant de construire l'image (voir
« Fichiers appartenant à root » plus bas).

```sh
docker compose build
docker compose run --rm app composer install
docker compose up -d
```

Ouvre ensuite <http://localhost:8000/formation>.

## Chaque matin

1. Ouvre <http://localhost:8000/formation> : la page « Séance du jour » te montre ce que tu fais
   aujourd'hui. La durée (15 minutes par défaut) se change avec la pastille ⏱ en haut à droite.
2. Fiche de préparation : remplis chaque partie avant de coder, puis compare avec la correction.
3. Démonstration : lis-la étape par étape, puis reproduis le code dans ton projet.
4. Exercice : code dans ton projet (`src/`, `templates/`), puis lance les tests :

   ```sh
   docker compose exec app bin/exercice test
   ```

   Tests verts : l'exercice est validé et tu obtiens un badge. Après 4 lancements sans succès,
   le bouton « Demander de l'aide » apparaît dans la plateforme.

   La plateforme et ton application sont deux programmes séparés : la plateforme a son propre
   noyau Symfony, sa configuration (`config/formation/`) et son cache (`var/cache/formation_*`), et
   ne lit rien de ton code ni de ta configuration. Quoi que tu casse dans ton projet (une erreur de
   syntaxe, `config/services.yaml`, `config/packages/`, `config/routes/`, `templates/base.html.twig`,
   `src/Kernel.php`…), `/formation` et `bin/exercice` continuent de marcher. Une erreur dans ton
   code se voit donc seulement dans ton application (`/fournisseurs`…) et dans `bin/exercice test` :
   l'essai est alors compté rouge, avec le nom du fichier à corriger.

   Si la plateforme elle-même affiche une erreur, ce n'est pas ton code : préviens Mohammed.

## Recevoir une mise à jour

Quand Mohammed publie une nouvelle livraison (un nouveau niveau, une correction), tu la fusionnes dans
ta branche `travail`. Ton code et ta progression ne sont pas perdus.

1. **Valide ton travail d'abord** : git refuse de fusionner par-dessus des changements non validés.

   ```sh
   git status                      # ce qui n'est pas encore validé
   git add .
   git commit -m "Mon travail du jour"
   ```

2. **Récupère la livraison**, selon ce que Mohammed t'a transmis :

   ```sh
   git fetch <adresse du dépôt> livraison:livraison     # dépôt privé (ou « git fetch origin livraison:livraison »)
   git fetch livraison.bundle livraison:livraison       # fichier reçu : ton nouveau livraison.bundle
   ```

3. **Fusionne-la dans ta branche** (tu dois être sur `travail`) :

   ```sh
   git switch travail
   git merge livraison
   ```

   Si git signale un conflit (le plus souvent dans `config/services.yaml`, que tu modifies et que la
   livraison modifie aussi), ouvre le fichier, garde **les deux** versions (les blocs de `parameters:`
   notamment), puis `git add` et `git commit`. En cas de doute, appelle Mohammed avant de valider.

4. **Reconstruis l'environnement** : une mise à jour peut ajouter des paquets (le niveau Avancé en
   ajoute), et sans cela ton application ne démarre plus.

   ```sh
   docker compose build
   docker compose run --rm app composer install
   ```

## Fichiers appartenant à root

Le conteneur tourne maintenant en utilisateur ordinaire (identifiant 1000 par défaut, réglé par
`APP_UID` et `APP_GID` dans `compose.yaml`). Si ton dossier `var/` ou `vendor/` a été créé par l'ancienne
version, qui tournait en root, ces fichiers appartiennent à root et ne sont plus écrivables : tu verras
des erreurs « Permission denied » (cache, base de progression, `composer install`).

Si ton identifiant Linux n'est pas 1000 (vérifie avec `id -u` et `id -g`), règle-le **d'abord**, avant la
construction de l'image **et** avant la commande `chown` (le nom `app` dans le conteneur reçoit cet identifiant) :

```sh
export APP_UID=$(id -u) APP_GID=$(id -g)     # à mettre dans ~/.bashrc pour ne plus y penser
```

**Une seule fois, après la mise à jour**, construis l'image puis rends-toi les fichiers :

```sh
docker compose build
docker compose run --rm -u root app chown -R app:app /app
```

Sous macOS (Docker Desktop), les droits des fichiers partagés sont gérés par Docker : rien à faire.
Sous Windows, le projet est rangé dans WSL, un vrai système Linux : vérifie `id -u` et `id -g` dans le terminal
Ubuntu, et suis les étapes ci-dessus si ce n'est pas 1000.

## Recommencer de zéro

La progression est enregistrée dans `var/formation.sqlite`. Supprime ce fichier pour repartir
du début. Ton code, lui, reste en place.

## Pour Mohammed

- Architecture : `public/routeur.php` (serveur `php -S`) envoie `/formation…` et `/atelier/…` à
  `public/formation.php` (noyau `src/Formation/FormationKernel.php`), tout le reste à
  `public/index.php` (noyau du junior, `App\Kernel`). La console de la plateforme est
  `bin/formation` (celle du junior reste `bin/console`). Le port 8000 sert les deux.
- Limites connues : la plateforme dépend encore de `vendor/` (un `composer install` raté, un
  `vendor/` supprimé ou un `composer.json` cassé suivi d'un `composer dump-autoload` l'arrêtent),
  de `src/Formation/`, `config/formation/` et `templates/formation/`, et de `var/` (cache, base
  de progression).
- Vérifier que le contenu est cohérent : `docker compose run --rm app bin/formation formation:contenu:verifier`
- Vérifier qu'un corrigé est rouge sans solution et vert avec : `outils/verifier-corriges.sh 03-formulaires`
- Vérifier tous les corrigés, un sujet après l'autre, en s'arrêtant au premier échec : `outils/verifier-tous-les-corriges.sh` (long : à lancer de temps en temps, jamais pendant d'autres tests).
- Règle d'or du contenu : **ne jamais renommer ni supprimer un identifiant de bloc** (la progression du junior est indexée par identifiant : il perdrait ce qu'il a fait). Pour en ajouter un nouveau, ajoute-le aussi à `tests/Formation/fixtures/identifiants-de-blocs.txt` ; le test `IdentifiantsDeBlocsTest` échoue si un identifiant de cette liste disparaît.
- Tests de la plateforme : `docker compose run --rm app php bin/phpunit`
- Les corrigés sont dans `outils/corriges/` : ils restent dans ton dépôt de travail, jamais chez le junior (voir la livraison ci-dessous).
- Un junior qui reçoit une mise à jour suit « Recevoir une mise à jour » (ci-dessus) : il a une branche `travail`, valide son travail, fusionne `livraison`, puis `docker compose build` et `composer install`. Si `config/services.yaml` entre en conflit, il faut garder les deux blocs de `parameters:`.

### Livraison au junior

Le junior ne reçoit pas ton dépôt : il reçoit une **branche `livraison`**, générée à partir de la branche
courante par `outils/preparer-livraison.sh`. Elle ne contient ni `outils/corriges/`, ni les trois scripts
`outils/verifier-corriges.sh`, `outils/verifier-tous-les-corriges.sh` et `outils/preparer-livraison.sh`, ni
`docs/`, ni `.claude/`, ni les tests de la plateforme qui ne marchent qu'avec les corrigés
(`tests/Formation/Outils/`, `IsolationDuJuniorTest`, `BinExerciceCodeCasseTest`). Tout le reste est livré, y compris
`tests/Formation/AvecCopieDuProjet.php` (l'exercice 09 en a besoin).

1. **Générer** : valide ton travail (`git commit`), puis, depuis la branche à livrer :

   ```sh
   outils/preparer-livraison.sh
   ```

   Le script ne livre que ce qui est **validé** : il ne touche ni à ton index, ni à ton arbre de travail, ne
   pousse rien, et dit s'il reste des modifications non validées. Il crée la branche `livraison` la première
   fois ; ensuite chaque livraison est un commit **enfant** de la précédente (c'est ce qui permet au junior
   de fusionner sans conflit artificiel). Sans changement depuis la dernière livraison, il le dit et ne crée rien.
   Ne retire jamais la branche `livraison` et ne la reconstruis jamais à zéro : le junior ne pourrait plus fusionner.

2. **Transmettre** (au choix, rien n'est poussé automatiquement) :

   ```sh
   # dépôt distant privé (tu le crées toi-même) :
   git push <dépôt privé> livraison
   # ou un fichier, sans dépôt distant :
   git bundle create livraison.bundle livraison
   ```

   Le fichier `livraison.bundle` contient tout l'historique de `livraison` (jamais celui de ta branche de
   travail, ni les corrigés) : donne-le au junior, qui fait `git clone --branch livraison livraison.bundle atelier`
   la première fois, puis `git fetch livraison.bundle livraison:livraison` à chaque mise à jour.

3. **Règle de mise à jour** : une nouvelle livraison par lot terminé (`outils/preparer-livraison.sh`, puis un nouveau
   bundle ou un `git push`). Dis au junior de valider son travail avant de fusionner, et de refaire
   `docker compose build` puis `docker compose run --rm app composer install`. Ne modifie jamais un fichier
   livré après coup sans refaire une livraison : le junior ne verrait pas le changement.

4. **Vérifier** : `docker compose run --rm app php bin/phpunit --group lent tests/Formation/Outils/PreparerLivraisonTest.php`
   (copie jetable : branche sans les chemins exclus, `bin/exercice` du sujet 01 rouge comme attendu, exercice 09 complet, seconde
   livraison enfant de la première, index et arbre de travail intacts).

## Versions

- PHP 8.1, Symfony 5.4
- Doctrine ORM 2.20, PHPUnit 9.6
