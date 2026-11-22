## À faire

Tu sais importer des matériels depuis un fichier CSV. À toi de faire la même chose pour les **fournisseurs**, en suivant ta fiche de préparation.

Écris la commande **`app:fournisseur:importer`** (classe `App\Command\ImporterFournisseurCommand`) :

- elle reçoit un **argument** `fichier`, obligatoire : le chemin d'un fichier CSV ;
- le fichier a trois colonnes séparées par un point-virgule : `nom;email;telephone`. La première ligne contient les en-têtes et n'est pas un fournisseur ;
- chaque ligne **valide** est enregistrée en base ;
- chaque ligne **invalide** est **signalée avec son numéro**, sous la forme « Ligne 3 refusée : … », puis ignorée : les autres lignes sont quand même importées. Le numéro est celui d'un tableur : l'en-tête est la ligne 1, le premier fournisseur la ligne 2 ;
- une ligne est invalide si le nom est vide, si l'adresse e-mail est invalide, ou si l'adresse e-mail est **déjà utilisée**, soit par un fournisseur déjà en base, soit par une ligne plus haut dans le même fichier ;
- un téléphone absent ou vide est enregistré comme absent (`null`) ;
- si le **fichier n'existe pas** (ou n'est pas lisible), la commande affiche une erreur et renvoie le code **1** sans rien enregistrer ;
- dans tous les autres cas, y compris quand des lignes sont refusées, elle renvoie le code **0** : le fichier a pu être lu.

Contraintes :

- utilise l'attribut `#[AsCommand]`, un `SymfonyStyle` pour la sortie, et une description de commande ;
- **ne réécris aucune règle de validation** : celles de l'entité `Fournisseur` s'appliquent ici aussi ;
- une adresse répétée **dans le fichier** doit être refusée : réfléchis à ce que cela implique sur le moment où tu enregistres. Dans la démonstration, un seul `flush()` terminait le lot ; ici, ce serait trop tard.

Les fichiers CSV utilisés par les tests sont dans le dossier `tests/Exercices/Console/`. Ouvre-les : ils montrent exactement ce que ta commande doit lire.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- La commande `app:materiel:importer` existe, avec une description et un argument `fichier`.
- L'import du matériel enregistre les lignes valides et répond avec le code 0.
- L'import du matériel signale la ligne refusée (la ligne 3, nom vide) avec son numéro, et pas les lignes valides.
- Avec l'option `--simuler`, rien n'est enregistré.
- L'import du matériel sur un fichier absent renvoie le code 1.
- La commande `app:fournisseur:importer` existe, avec une description et un argument `fichier`.
- Les lignes valides d'un fichier de fournisseurs sont importées, avec leur nom, leur e-mail et leur téléphone.
- Un téléphone vide ou absent est enregistré comme absent.
- Les lignes invalides (e-mail invalide, nom vide) sont signalées avec leur numéro ; les lignes valides sont importées ; le code de retour reste 0.
- Une adresse e-mail déjà prise par un fournisseur en base est signalée avec le numéro de sa ligne.
- Une adresse e-mail répétée dans le fichier est signalée à sa deuxième apparition.
- Un fichier qui ne contient que l'en-tête n'importe rien et renvoie le code 0.
- Un fichier absent renvoie le code 1, sans rien enregistrer.
