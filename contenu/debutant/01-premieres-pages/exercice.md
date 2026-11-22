## À faire

Tu as vu comment créer des pages pour le matériel. À toi de faire la même chose pour les **fournisseurs**, en repartant de ta fiche de préparation.

Crée deux pages :

1. **`/fournisseurs`** : la liste des fournisseurs. Chaque nom est un lien vers la fiche du fournisseur.
2. **`/fournisseurs/{id}`** : la fiche d'un fournisseur, avec son nom et sa ville.

Les données sont écrites en dur dans le contrôleur (la base de données vient au sujet suivant) :

| Identifiant | Nom | Ville |
|---|---|---|
| 1 | Dupont Informatique | Lyon |
| 2 | Bureau Express | Nantes |
| 3 | Papeterie Martin | Lille |

Contraintes :

- l'identifiant doit être **un nombre** : une adresse comme `/fournisseurs/abc` ne correspond à aucune page ;
- un identifiant qui n'existe pas (`/fournisseurs/999`) répond par la page **introuvable** ;
- les deux pages **héritent** du gabarit `base.html.twig`, comme celles du matériel ;
- tes pages du matériel doivent continuer à fonctionner : le premier test les vérifie.

## Pour lancer les tests

Dans un terminal, à la racine du projet :

```bash
docker compose exec app bin/exercice test
```

La commande lance les tests, enregistre le résultat et te dit s'ils passent tous. Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Les pages du matériel de la démonstration fonctionnent : `/materiels` répond (code 200) et affiche « Ordinateur portable ».
- La fiche `/materiels/2` affiche « Écran 24 pouces ».
- `/materiels/999` répond 404.
- `/fournisseurs` répond et montre les trois noms de fournisseurs.
- La liste contient un lien par fournisseur, vers `/fournisseurs/{id}`.
- `/fournisseurs/2` affiche « Bureau Express ».
- `/fournisseurs/999` répond 404.
- `/fournisseurs/abc` répond 404 (et non 500).
- Les pages sont de vraies pages HTML, avec une balise `title`, héritées du gabarit de base.
