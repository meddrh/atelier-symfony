## Ce que nous construisons

Les contraintes intégrées (`NotBlank`, `Length`…) ne savent pas tout. Un **numéro de série**, par exemple, a un format propre à l'entreprise : de 4 à 40 caractères, des lettres majuscules, des chiffres et des tirets, sans commencer par un tiret. Quand une règle revient et qu'elle a un nom, on en fait **une contrainte à soi** : une petite classe qu'on pose sur une propriété comme `#[Assert\Length]`.

Une contrainte personnalisée est faite de **deux classes** :

- la **contrainte** elle-même (`NumeroDeSerie`) : elle porte le message et se pose en attribut ;
- son **validateur** (`NumeroDeSerieValidator`) : il contient la règle, et c'est lui qui construit la violation.

On verra ensuite comment **ordonner** les règles avec une **séquence de groupes** : ne pas montrer l'erreur de format du numéro de série tant que le nom ou la catégorie ne sont pas corrects.

---

## Étape 1 : la contrainte

Crée `src/Validator/NumeroDeSerie.php` :

```php
<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Un numéro de série : 4 à 40 caractères, lettres majuscules, chiffres et tirets, sans commencer par un tiret.
 * Une valeur vide n'est pas jugée ici : c'est le rôle de « NotBlank », quand le champ est obligatoire.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class NumeroDeSerie extends Constraint
{
    public string $message = "Le numéro de série « {{ valeur }} » n'est pas valide : 4 à 40 caractères, lettres majuscules, chiffres et tirets, sans commencer par un tiret.";

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
```

- `#[\Attribute(…)]` rend la classe utilisable en attribut, sur une propriété ou une méthode ;
- elle **étend** `Constraint` ;
- `$message` est le texte par défaut ; `{{ valeur }}` sera remplacé par la valeur refusée ;
- le constructeur accepte un `$message` et des `$groups` : on pourra écrire `#[NumeroDeSerie(groups: ['Strict'])]`. Il passe les groupes à `Constraint`, qui sait les gérer.

Le validateur se retrouve **par son nom** : Symfony cherche la classe `NumeroDeSerieValidator` dans le même espace de noms.

---

## Étape 2 : le validateur

Crée `src/Validator/NumeroDeSerieValidator.php` :

```php
<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NumeroDeSerieValidator extends ConstraintValidator
{
    public function validate(mixed $valeur, Constraint $contrainte): void
    {
        if (!$contrainte instanceof NumeroDeSerie) {
            throw new UnexpectedTypeException($contrainte, NumeroDeSerie::class);
        }

        // Une valeur absente est valide : « NotBlank » est fait pour l'interdire.
        if (null === $valeur || '' === $valeur) {
            return;
        }

        if (!\is_string($valeur)) {
            throw new UnexpectedValueException($valeur, 'string');
        }

        if (1 !== preg_match('/^[A-Z0-9][A-Z0-9-]{3,39}$/', $valeur)) {
            $this->context->buildViolation($contrainte->message)
                ->setParameter('{{ valeur }}', $valeur)
                ->addViolation();
        }
    }
}
```

Lis `validate()` dans l'ordre :

- les deux premiers contrôles sont des **gardes de programmation** : une exception si on a mal branché la classe (mauvaise contrainte, valeur qui n'est pas du texte). Ce ne sont pas des erreurs de l'utilisateur ;
- **une valeur vide est valide** : c'est la convention de toutes les contraintes intégrées. Si le champ doit être obligatoire, on ajoute `NotBlank` à côté, et chaque règle ne dit que ce qu'elle sait dire ;
- `preg_match` vérifie le format : une lettre majuscule ou un chiffre, puis de 3 à 39 lettres majuscules, chiffres ou tirets ;
- `buildViolation($message)` commence la construction d'une violation. `setParameter('{{ valeur }}', $valeur)` remplace le repère du message par la valeur refusée, et `addViolation()` l'enregistre. Sans cet appel, rien ne serait signalé.

Le validateur est un service : le câblage automatique le reconnaît (il étend `ConstraintValidator`) et le branche sans configuration.

---

## Étape 3 : poser la contrainte

Dans `src/Entity/Materiel.php`, ajoute la ligne `use App\Validator\NumeroDeSerie;` avec les autres, puis l'attribut sur la propriété, avec ceux qui s'y trouvent déjà :

```php
#[Assert\Length(max: 40, maxMessage: 'Le numéro de série ne doit pas dépasser {{ limit }} caractères.')]
#[NumeroDeSerie(groups: ['Strict'])]
private ?string $numeroSerie = null;
```

Le numéro de série est déjà mis en majuscules et débarrassé de ses espaces par le transformateur de données du sujet précédent : la contrainte juge donc la valeur **déjà nettoyée**.

`groups: ['Strict']` range cette règle dans un groupe qui n'est pas `Default`. L'étape 4 explique pourquoi.

---

## Étape 4 : la séquence de groupes

Un visiteur qui laisse le nom vide **et** se trompe de numéro de série reçoit deux erreurs d'un coup. Ce n'est pas faux, mais on préfère souvent **corriger dans l'ordre** : les erreurs de base d'abord, les règles plus fines ensuite. Une **séquence de groupes** valide les groupes les uns après les autres, et **s'arrête au premier groupe qui échoue**.

Ajoute cet attribut sur la classe `Materiel`, avec ceux qui s'y trouvent déjà :

```php
// Séquence : d'abord les règles de base (groupe « Materiel », qui remplace « Default » dans cette classe) ;
// le groupe « Strict » n'est examiné que si elles sont toutes respectées.
#[Assert\GroupSequence(['Materiel', 'Strict'])]
```

- `Materiel` désigne le groupe des règles « de base » : dans une classe qui déclare une séquence, `Default` est **remplacé par le nom de la classe**. Les contraintes sans groupe (nom, catégorie, longueur) y sont donc rangées automatiquement ;
- `Strict` vient ensuite : il n'est examiné que si **tout** `Materiel` est respecté ;
- un formulaire qui valide `Default` valide, en fait, cette séquence.

Le formulaire du matériel n'a pas changé : c'est l'entité qui a décidé de l'ordre.

---

## Étape 5 : vérifier

1. Crée un matériel avec un numéro de série `ab1` : l'erreur « Le numéro de série « AB1 » n'est pas valide… » apparaît (la valeur citée est celle qui a été nettoyée).
2. Recommence avec le **nom vide** et le même numéro : seule l'erreur du nom apparaît. Remplis le nom : l'erreur du numéro de série apparaît à son tour.
3. Essaie `ab12-cd9` : accepté, enregistré sous `AB12-CD9`. Laisse le numéro vide : accepté aussi.
4. Mets un matériel au rebut (partie précédente) : le motif reste exigé, la séquence n'y change rien, car le formulaire de rebut ne demande que le groupe `rebut`.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée la contrainte et son validateur, pose la contrainte sur le numéro de série, ajoute la séquence de groupes à `Materiel`, puis vérifie les quatre points de l'étape 5 et clique sur « Terminé ».
