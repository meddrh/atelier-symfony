## À faire

Les matériels sont en base. À toi de faire la même chose pour les **fournisseurs**, en suivant ta fiche de préparation.

1. Crée l'entité **`Fournisseur`** (`App\Entity\Fournisseur`) avec :
   - `nom` : texte, obligatoire ;
   - `email` : texte, obligatoire, **unique** ;
   - `telephone` : texte, **facultatif**.
2. Crée son dépôt, génère la migration et exécute-la.
3. Remplace les fournisseurs écrits en dur par la lecture dans la base :
   - `/fournisseurs` : la liste, **triée par nom** ;
   - `/fournisseurs/{id}` : la fiche, avec le nom, l'e-mail et le téléphone s'il existe ; **404** si l'identifiant est inconnu.

Les tests attendent exactement ces méthodes sur l'entité : `getId()`, `getNom()`, `setNom()`, `getEmail()`, `setEmail()`, `getTelephone()`, `setTelephone()`. Chaque méthode `set…` renvoie l'objet lui-même (`return $this;`), comme pour `Materiel`.

Les tests fabriquent eux-mêmes leurs fournisseurs : tu n'as pas besoin d'en ajouter dans la base pour qu'ils passent. Mais **exécute quand même la migration** et ajoute un fournisseur à la main : sans cela, tes pages ne fonctionneraient pas dans le navigateur.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

## Tests attendus

- La liste `/materiels` affiche les matériels de la base, et plus ceux du tableau en dur.
- La fiche `/materiels/{id}` affiche le nom et la catégorie lus dans la base.
- `/materiels/999` répond 404.
- Un fournisseur enregistré puis relu depuis la base garde son nom, son e-mail et son téléphone.
- Le téléphone est facultatif : sans valeur, il est relu comme absent.
- Deux fournisseurs ne peuvent pas partager le même e-mail : la base refuse le second.
- La liste `/fournisseurs` est triée par ordre alphabétique de nom.
- La liste `/fournisseurs` ne contient plus les fournisseurs écrits en dur.
- La fiche `/fournisseurs/{id}` affiche le nom et l'e-mail.
- `/fournisseurs/999` répond 404.
- `/fournisseurs/abc` répond 404.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
