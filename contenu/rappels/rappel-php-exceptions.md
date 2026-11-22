## Pourquoi ce rappel

Dans le sujet qui arrive, ton code va **refuser** des valeurs (un tri inconnu, un matériel qu'on ne peut pas supprimer), parcourir des **collections** d'objets et passer de petites fonctions à d'autres fonctions. Cela repose sur quatre notions de PHP que tu as déjà croisées : les exceptions, la bibliothèque standard (SPL), les traits et les fonctions anonymes. En version courte, avec le code que tu retrouveras.

## Les exceptions

Une **exception** est un objet qui dit « quelque chose s'est mal passé » et qui **interrompt** le code en cours. On la lance avec `throw`, on la rattrape avec `try` et `catch`.

```php
function diviser(int $a, int $b): float
{
    if (0 === $b) {
        throw new \InvalidArgumentException('Le diviseur ne peut pas être zéro.');
    }

    return $a / $b;
}

try {
    echo diviser(10, 0);
} catch (\InvalidArgumentException $erreur) {
    echo 'Impossible : '.$erreur->getMessage();
} finally {
    echo ' (fin du calcul)';
}
```

- le code du `try` s'arrête à la première exception lancée ;
- le `catch` qui correspond au **type** de l'exception s'exécute ; les autres sont ignorés ;
- le bloc `finally` s'exécute **toujours**, qu'il y ait eu une exception ou non (utile pour fermer un fichier) ;
- une exception que personne ne rattrape remonte jusqu'à Symfony, qui affiche une page d'erreur : c'est le cas d'un code 500.

Les exceptions forment une **hiérarchie** : `\InvalidArgumentException` est une `\LogicException`, qui est une `\Exception`. Un `catch (\Exception $e)` rattrape donc toutes les exceptions « filles ». On peut aussi rattraper deux types d'un coup : `catch (\TypeError | \ValueError $e)`. Attention : PHP lance aussi des erreurs internes (`\TypeError`, `\ValueError`, `\DivisionByZeroError`…) qui sont des `\Error`, une famille **à part** : un `catch (\Exception $e)` ne les rattrape pas.

Tu peux écrire tes propres exceptions : une classe qui hérite d'une autre.

```php
class MaterielIntrouvable extends \RuntimeException
{
}
```

Une règle de bon sens : **lance** une exception quand l'appelant a fait une erreur que tu ne peux pas réparer ; **rattrape**-la seulement si tu sais quoi faire ensuite. Un `catch` vide cache les erreurs, il ne les règle pas.

## La bibliothèque standard (SPL)

La **SPL** (*Standard PHP Library*, « bibliothèque standard de PHP ») regroupe des classes et des interfaces toutes prêtes. Tu en retrouveras deux familles dans Symfony :

- des **exceptions** aux noms parlants : `\InvalidArgumentException` (« tu m'as donné une mauvaise valeur »), `\LogicException` (« le code est mal utilisé »), `\RuntimeException` (« un problème est survenu à l'exécution »), `\OutOfBoundsException`… Choisir la bonne rend l'erreur lisible sans commentaire ;
- des **interfaces de parcours** : `\Countable` (on peut faire `count($objet)`), `\IteratorAggregate` et `\ArrayAccess` (on peut écrire `foreach ($objet as …)` et `$objet['cle']`). La collection `Doctrine\Common\Collections\Collection`, que tu vas utiliser pour les relations, implémente ces interfaces : c'est pour cela qu'un `foreach` ou un `count()` marche dessus comme sur un tableau.

```php
foreach ($utilisateur->getMateriels() as $materiel) {
    echo $materiel->getNom();
}

echo count($utilisateur->getMateriels());
```

## Les traits

Un **trait** est un morceau de code qu'une classe peut **« coller »** chez elle avec `use`. PHP ne permet d'hériter que d'une seule classe ; un trait permet de **réutiliser** du code sans héritage.

```php
trait Horodate
{
    private ?\DateTimeImmutable $creeLe = null;

    public function getCreeLe(): ?\DateTimeImmutable
    {
        return $this->creeLe;
    }
}

class Ticket
{
    use Horodate;
}
```

La classe `Ticket` se comporte exactement comme si le code du trait était écrit dedans. Ne confonds pas ce `use` (dans la classe) avec le `use` du haut du fichier, qui importe un nom de classe. Symfony en emploie : la classe `Kernel` de ton projet contient `use MicroKernelTrait;`. Un trait n'est **pas** un type : on ne peut pas écrire `function f(Horodate $x)`.

## Les fonctions anonymes

Une **fonction anonyme** (ou **fermeture**) est une fonction sans nom, qu'on peut ranger dans une variable ou passer à une autre fonction.

```php
$noms = array_map(function (Materiel $materiel): string {
    return $materiel->getNom();
}, $materiels);
```

Depuis PHP 7.4, la **fonction fléchée** donne la même chose en une ligne ; elle lit automatiquement les variables du code autour d'elle :

```php
$noms = array_map(fn (Materiel $materiel) => $materiel->getNom(), $materiels);

$prefixe = 'Salle ';
$etiquettes = array_map(fn (string $nom) => $prefixe.$nom, $noms);
```

Avec une fonction anonyme classique, on dit explicitement quelles variables on emprunte : `function ($nom) use ($prefixe) { … }`. Dans la suite du parcours, tu verras surtout des fonctions anonymes pour **trier** (`usort`), **filtrer** (`array_filter`) ou **transformer** (`array_map`), et dans les formulaires pour décrire comment convertir une valeur.

## À retenir

- `throw` interrompt, `catch` rattrape par type, `finally` s'exécute toujours.
- Les exceptions de la SPL disent **quelle sorte** d'erreur c'est : prends la plus précise.
- Un trait partage du code sans héritage ; ce n'est pas un type.
- Une fonction anonyme est une valeur : on la passe à `array_map`, `usort`, `array_filter`.
