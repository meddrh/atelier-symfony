## Ce que nous construisons

Le formulaire du matériel accepte maintenant une facture. Trois choses le rendent plus « sur mesure » :

- le **numéro de série** est saisi n'importe comment (`  ab 12 cd `) : nous voulons l'enregistrer proprement, en majuscules et sans espaces. C'est un **transformateur de données** ;
- la **date de retour** n'a de sens que pour un matériel qui existe déjà : le champ ne doit apparaître **qu'en modification**. C'est un **événement de formulaire** ;
- chaque champ sera entouré d'un élément qui permettra de le mettre en forme. C'est un **thème de formulaire**.

---

## Étape 1 : le type de formulaire complet

Voici `src/Form/MaterielType.php` en entier. Les parties nouvelles sont expliquées juste après ; le champ `facture` est celui de la partie 1.

```php
<?php

namespace App\Form;

use App\Entity\Materiel;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class MaterielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('categorie', TextType::class, ['label' => 'Catégorie'])
            ->add('numeroSerie', TextType::class, ['label' => 'Numéro de série', 'required' => false])
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => 'nom',
                'label' => 'Affecté à',
                'required' => false,
                'placeholder' => 'Personne',
            ])
            ->add('facture', FileType::class, [
                'label' => 'Facture (PDF, 2 Mo au plus)',
                // Le champ ne correspond à aucune propriété : le contrôleur range le fichier et enregistre son nom.
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['application/pdf'],
                        maxSizeMessage: 'La facture ne doit pas dépasser {{ limit }} {{ suffix }}.',
                        mimeTypesMessage: 'La facture doit être un fichier PDF.',
                    ),
                ],
            ])
        ;

        // Le numéro de série est saisi n'importe comment : on l'enregistre en majuscules et sans espaces.
        $builder->get('numeroSerie')->addModelTransformer(new CallbackTransformer(
            static fn (?string $enBase): ?string => $enBase,
            static function (?string $saisi): ?string {
                $propre = strtoupper((string) preg_replace('/\s+/', '', (string) $saisi));

                return '' === $propre ? null : $propre;
            },
        ));

        // La date de retour n'a de sens qu'une fois le matériel créé : le champ n'existe qu'en modification.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $evenement): void {
            $materiel = $evenement->getData();
            if ($materiel instanceof Materiel && null !== $materiel->getId()) {
                $evenement->getForm()->add('dateRetour', DateType::class, [
                    'label' => 'Date de retour',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => false,
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Materiel::class,
        ]);
    }
}
```

---

## Étape 2 : un transformateur de données

Le formulaire manipule des **chaînes de caractères**, l'objet manipule ses propres types. Un **transformateur** convertit dans les deux sens. Ici, le type ne change pas (texte contre texte), mais la valeur est **nettoyée** à l'enregistrement :

```php
$builder->get('numeroSerie')->addModelTransformer(new CallbackTransformer(
    static fn (?string $enBase): ?string => $enBase,
    static function (?string $saisi): ?string {
        $propre = strtoupper((string) preg_replace('/\s+/', '', (string) $saisi));

        return '' === $propre ? null : $propre;
    },
));
```

- `$builder->get('numeroSerie')` récupère le champ déjà ajouté ; `addModelTransformer()` y attache le transformateur.
- `CallbackTransformer` prend **deux fonctions**. La première, `transform`, va de la **donnée de l'objet vers le champ** (ici, on affiche la valeur telle quelle). La seconde, `reverseTransform`, va du **champ vers l'objet** : elle est appelée à l'envoi.
- `static fn (…) => …` est une fonction fléchée ; `static function (…) { … }` une fonction anonyme complète (voir le rappel sur les fonctions anonymes). `static` dit qu'elles n'ont pas besoin de `$this`.
- `reverseTransform` supprime tous les espaces (`\s+`), met en majuscules, et renvoie `null` si rien ne reste : une saisie de simples espaces devient « pas de numéro ».

Essaie : saisis `  ab 12 cd-9 ` ; la fiche affiche `AB12CD-9`.

---

## Étape 3 : un champ qui n'existe qu'en modification

Un **écouteur d'événement de formulaire** est une fonction que Symfony appelle à un moment précis de la vie du formulaire. `PRE_SET_DATA` arrive **juste avant** que le formulaire reçoive l'objet à afficher : on peut y ajouter ou retirer des champs.

```php
$builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $evenement): void {
    $materiel = $evenement->getData();
    if ($materiel instanceof Materiel && null !== $materiel->getId()) {
        $evenement->getForm()->add('dateRetour', DateType::class, [
            'label' => 'Date de retour',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'required' => false,
        ]);
    }
});
```

- `$evenement->getData()` donne l'objet qui va remplir le formulaire : un `Materiel`.
- Un matériel **neuf** n'a pas encore d'identifiant (`getId()` vaut `null`) ; un matériel venant de la base en a un. On n'ajoute `dateRetour` que dans le second cas.
- `$evenement->getForm()->add(…)` ajoute le champ dynamiquement. `DateType` avec `'widget' => 'single_text'` donne un seul champ de date ; `'input' => 'datetime_immutable'` indique que l'objet porte un `\DateTimeImmutable`, comme la propriété.

Vérifie : `/materiels/nouveau` n'a pas de champ « Date de retour » ; `/materiels/1/modifier` en a un.

---

## Étape 4 : un thème de formulaire

Par défaut, Symfony dessine chaque champ (étiquette, erreurs, widget) avec un gabarit prêt à l'emploi. Un **thème de formulaire** est un gabarit à nous qui **remplace** un de ces morceaux. Crée `templates/form/champ.html.twig` :

```twig
{% extends 'form_div_layout.html.twig' %}

{% block form_row %}
    <div class="champ">
        {{ parent() }}
    </div>
{% endblock %}
```

- `{% extends 'form_div_layout.html.twig' %}` part du thème par défaut : on ne réécrit que ce qui change.
- `form_row` est le bloc qui dessine **une ligne de formulaire** : étiquette, erreurs et champ. `{{ parent() }}` appelle le dessin d'origine ; nous l'entourons simplement d'un `<div class="champ">`.

Un thème s'applique avec la balise `form_theme`, dans le gabarit qui dessine le formulaire. Remplace `templates/materiel/_formulaire.html.twig` :

```twig
{% form_theme formulaire 'form/champ.html.twig' %}

{{ form_start(formulaire, {attr: {novalidate: 'novalidate'}}) }}
    {{ form_widget(formulaire) }}

    <button type="submit">Enregistrer</button>
{{ form_end(formulaire) }}
```

Regarde le code source de `/materiels/nouveau` : chaque champ est dans un `div` de classe `champ`.

---

## Étape 5 : montrer les nouvelles informations

Dans `templates/materiel/fiche.html.twig`, sous la catégorie, ajoute :

```twig

<p>Numéro de série : {{ materiel.numeroSerie ?? 'non renseigné' }}</p>

<p>Facture : {{ materiel.facture ? 'jointe' : 'aucune' }}</p>

<p>
    Affecté à :
    {% if materiel.utilisateur %}
        <a href="{{ path('utilisateur_fiche', {id: materiel.utilisateur.id}) }}">{{ materiel.utilisateur.nom }}</a>
    {% else %}
        personne
    {% endif %}
</p>
```

```twig

<p>Facture : {{ materiel.facture ? 'jointe' : 'aucune' }}</p>

<p>
    Affecté à :
    {% if materiel.utilisateur %}
        <a href="{{ path('utilisateur_fiche', {id: materiel.utilisateur.id}) }}">{{ materiel.utilisateur.nom }}</a>
    {% else %}
        personne
    {% endif %}
</p>
```

`??` signifie « si la valeur est absente ou `null`, prends celle-ci » : l'écran dit « non renseigné » au lieu d'afficher un blanc.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Complète `MaterielType` (transformateur et écouteur), crée le thème, utilise-le dans `_formulaire.html.twig` et affiche les deux informations sur la fiche. Vérifie l'enregistrement d'un numéro de série mal écrit, l'absence de la date de retour à la création et sa présence à la modification, puis clique sur « Terminé ».
