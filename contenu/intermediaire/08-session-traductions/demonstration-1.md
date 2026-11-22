## Ce que nous construisons

La liste des matériels doit pouvoir être **filtrée** : tous, seulement ceux qui sont affectés à quelqu'un, seulement ceux qui sont libres. Et le visiteur ne veut pas **recliquer** sur son filtre à chaque page : s'il choisit « libres », puis ouvre une fiche, puis revient à la liste, il veut retrouver « libres ».

C'est le rôle de la **session** : un petit espace que le serveur garde **pour chaque visiteur**, entre deux requêtes. HTTP, lui, n'a pas de mémoire : chaque requête arrive sans rien savoir des précédentes. La session est un identifiant (un **cookie**) que le navigateur renvoie à chaque requête, et qui permet au serveur de retrouver ses notes.

Dans cette partie :

- le filtre choisi dans l'adresse (`/materiels?filtre=libres`) est **mémorisé en session** ;
- la requête suivante, sans paramètre, **reprend** le filtre mémorisé ;
- une valeur inconnue — dans l'adresse **ou** dans la session — ramène au filtre par défaut, sans erreur ;
- un **message flash** prévient le visiteur quand son filtre n'existe pas.

---

## Étape 1 : ne jamais faire confiance à ce qui vient du visiteur ni de la session

Une adresse peut contenir n'importe quoi : `?filtre=pirate`, ou `?filtre[]=x`. La session, elle, est écrite par **ton** code, mais on la relit plus tard : le format peut avoir changé, une ancienne valeur peut traîner, une valeur peut être d'un autre type que prévu. Dans les deux cas, la règle est la même :

> on ne **lit** pas une valeur et on s'en sert : on la **vérifie** contre la liste de ce qui est permis, et sinon on prend la valeur par défaut.

C'est une **liste blanche** : on énumère ce qui est autorisé, au lieu d'essayer de deviner ce qui est interdit. Ici, c'est un tableau de constantes.

---

## Étape 2 : le dépôt

Dans `src/Repository/MaterielRepository.php`, ajoute ces constantes et cette méthode à la fin de la classe :

```php
public const FILTRE_TOUS = 'tous';
public const FILTRE_AFFECTES = 'affectes';
public const FILTRE_LIBRES = 'libres';
public const FILTRES = [self::FILTRE_TOUS, self::FILTRE_AFFECTES, self::FILTRE_LIBRES];
public const FILTRE_PAR_DEFAUT = self::FILTRE_TOUS;

/**
 * Les matériels selon le filtre de la liste : tous, affectés à quelqu'un, ou libres.
 *
 * @return list<Materiel>
 */
public function findSelonLeFiltre(string $filtre): array
{
    $constructeur = $this->createQueryBuilder('m')->orderBy('m.id', 'ASC');

    if (self::FILTRE_AFFECTES === $filtre) {
        $constructeur->andWhere('m.utilisateur IS NOT NULL');
    } elseif (self::FILTRE_LIBRES === $filtre) {
        $constructeur->andWhere('m.utilisateur IS NULL');
    }

    return $constructeur->getQuery()->getResult();
}
```

- `FILTRES` est la liste blanche, `FILTRE_PAR_DEFAUT` la valeur de repli ;
- `findSelonLeFiltre()` construit la requête avec le `QueryBuilder` : `IS NOT NULL` garde les matériels qui ont un utilisateur, `IS NULL` ceux qui n'en ont pas. Le filtre « tous » n'ajoute aucune condition ;
- le texte reçu n'est **jamais** collé dans la requête : il sert seulement à choisir entre trois branches écrites par nous.

---

## Étape 3 : le contrôleur

Dans `src/Controller/MaterielController.php`, ajoute la ligne `use Symfony\Contracts\Translation\TranslatorInterface;` avec les autres, la constante de la clé de session en haut de la classe, et remplace la méthode `liste()` :

```php
// Clé de la session sous laquelle le filtre de la liste est mémorisé.
private const CLE_FILTRE = 'materiels.filtre';
```

```php
#[Route('/materiels', name: 'materiel_liste')]
public function liste(Request $request, MaterielRepository $materielRepository, TranslatorInterface $traducteur): Response
{
    $session = $request->getSession();
    $demande = $request->query->get('filtre');

    if (null !== $demande) {
        // Le visiteur choisit un filtre : on le vérifie, puis on le mémorise.
        $filtre = $demande;
        if (!\in_array($filtre, MaterielRepository::FILTRES, true)) {
            $this->addFlash('warning', $traducteur->trans('materiel.filtre_inconnu'));
            $filtre = MaterielRepository::FILTRE_PAR_DEFAUT;
        }
        $session->set(self::CLE_FILTRE, $filtre);
    } else {
        // Sinon, on reprend celui de la session, qui peut être absent ou altéré : on le revérifie toujours.
        $filtre = $session->get(self::CLE_FILTRE);
        if (!\is_string($filtre) || !\in_array($filtre, MaterielRepository::FILTRES, true)) {
            $filtre = MaterielRepository::FILTRE_PAR_DEFAUT;
        }
    }

    return $this->render('materiel/liste.html.twig', [
        'materiels' => $materielRepository->findSelonLeFiltre($filtre),
        'filtres' => MaterielRepository::FILTRES,
        'filtre' => $filtre,
    ]);
}
```

Lis `liste()` dans l'ordre :

- `$request->getSession()` donne la session du visiteur. Elle ne démarre (et le cookie n'est envoyé) que si on lit ou écrit dedans ;
- `$request->query->get('filtre')` lit le paramètre de l'adresse. `null` veut dire « absent » ;
- **si le visiteur a choisi un filtre** : on le vérifie avec `\in_array(…, true)` (le dernier argument, `true`, compare aussi le type). S'il n'existe pas, on **prévient** avec un message flash et on prend la valeur par défaut. Dans tous les cas, on **mémorise** le résultat avec `$session->set()` ;
- **sinon** : on reprend `$session->get()`, et on le revérifie. `\is_string()` écarte les valeurs d'un autre type (une liste, un nombre) que le `\in_array` strict refuserait de toute façon, mais qu'il vaut mieux écarter explicitement ;
- le message flash passe par `$traducteur->trans()` : le texte vit dans un catalogue de traductions, la partie 2 l'explique. Pour l'instant, retiens que `trans('materiel.filtre_inconnu')` renvoie la phrase française que l'on voit à l'écran ;
- `render()` reçoit la liste filtrée, la liste des filtres permis et le filtre en cours, pour que le gabarit puisse les afficher.

Un **message flash** est un message gardé en session **jusqu'à son prochain affichage** : le gabarit de base l'affiche, puis il disparaît. C'est ce qui permet de prévenir le visiteur juste après une redirection, comme tu l'as vu après un enregistrement.

---

## Étape 4 : le gabarit

Dans `templates/materiel/liste.html.twig`, ajoute ce bloc sous le lien « Ajouter un matériel » :

```twig
<nav class="filtres">
    {% for choix in filtres %}
        {% if choix == filtre %}
            <strong>{{ ('materiel.filtre.' ~ choix)|trans }}</strong>
        {% else %}
            <a href="{{ path('materiel_liste', {filtre: choix}) }}">{{ ('materiel.filtre.' ~ choix)|trans }}</a>
        {% endif %}
    {% endfor %}
</nav>
```

Pour chaque filtre permis : celui qui est en cours s'affiche en gras, les autres sont des liens vers `/materiels?filtre=…`. Le texte de chaque lien est traduit par `trans` (partie 2). La clé est construite avec `~`, l'opérateur Twig qui **colle** deux textes : `('materiel.filtre.' ~ choix)|trans`.

---

## Étape 5 : le catalogue du message

Le message flash utilise la clé `materiel.filtre_inconnu`, et les liens utilisent `materiel.filtre.tous`, `.affectes`, `.libres`. Crée `translations/messages+intl-icu.fr.yaml` avec **seulement** ceci pour l'instant (la partie 2 le complète) :

```yaml
materiel:
    filtre:
        tous: Tous
        affectes: Affectés
        libres: Libres
    filtre_inconnu: "Ce filtre n'existe pas : tous les matériels sont affichés."
```

Les guillemets de la dernière ligne sont nécessaires : sans eux, les deux-points suivis d'une espace casseraient la lecture du fichier.

---

## Étape 6 : vérifier

1. Ouvre `/materiels` : tous les matériels sont là.
2. Clique sur « Libres » : seuls les matériels sans utilisateur restent. Ouvre une fiche, reviens à `/materiels` **sans paramètre** : le filtre « Libres » est toujours appliqué.
3. Clique sur « Tous » : la liste complète revient, et le restera.
4. Essaie `/materiels?filtre=pirate` : la liste complète s'affiche et le message « Ce filtre n'existe pas… » apparaît une fois.
5. Dans les outils du navigateur, repère le cookie de session : sans lui, le serveur ne retrouverait pas ton filtre.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute les constantes et la méthode du dépôt, remplace `liste()`, complète le gabarit et crée le catalogue français. Vérifie les cinq points de l'étape 6, puis clique sur « Terminé ».
