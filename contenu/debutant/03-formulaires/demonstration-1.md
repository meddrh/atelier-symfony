## Ce que nous construisons

Nous allons permettre d'**ajouter un matériel** depuis le navigateur. Le visiteur remplit un formulaire ; si ses données sont bonnes, le matériel est enregistré et un message le confirme ; si elles sont mauvaises, le formulaire se réaffiche avec les erreurs. La modification et la suppression viennent en partie 2.

Quatre pièces collaborent, et il faut les distinguer :

- l'**entité** porte les **règles de validation** (« le nom est obligatoire ») ;
- le **type de formulaire** décrit les champs à afficher ;
- le **contrôleur** reçoit l'envoi, vérifie, enregistre ou réaffiche ;
- le **gabarit** dessine le formulaire.

---

## Étape 1 : les règles de validation sur l'entité

Une règle de validation s'écrit avec un attribut, **sur la propriété qu'elle contrôle**. Ouvre `src/Entity/Materiel.php` et ajoute la ligne `use` ainsi que les attributs `#[Assert\…]` :

```php
<?php

namespace App\Entity;

use App\Repository\MaterielRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MaterielRepository::class)]
class Materiel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 120, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Length(max: 60, maxMessage: 'La catégorie ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $categorie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;

        return $this;
    }
}
```

Ce que disent les attributs :

- `#[Assert\NotBlank]` : la valeur ne doit pas être vide (ni `null`, ni une chaîne vide).
- `#[Assert\Length(max: 120)]` : pas plus de 120 caractères. La base refuserait un texte plus long avec une erreur technique ; ici, l'utilisateur reçoit un message lisible.
- Le texte `{{ limit }}` est remplacé par la valeur de `max`.

Pourquoi sur l'entité plutôt que dans le formulaire ? Parce que la règle décrit **ce qu'est un matériel valide**, pas la façon de le saisir. Si un jour un import lit un fichier au lieu d'un formulaire, la même règle s'appliquera.

Ces attributs sont **en plus** de `#[ORM\Column]` : l'un parle à la base de données, l'autre au validateur. Ils ne se remplacent pas.

---

## Étape 2 : le type de formulaire

Un **type de formulaire** est une classe qui liste les champs. Crée `src/Form/MaterielType.php` :

```php
<?php

namespace App\Form;

use App\Entity\Materiel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaterielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('categorie', TextType::class, ['label' => 'Catégorie'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Materiel::class,
        ]);
    }
}
```

- `add('nom', TextType::class, …)` : un champ de texte lié à la propriété `nom` de l'objet. Le premier argument est le **nom de la propriété** ; il doit correspondre exactement.
- `'data_class' => Materiel::class` : le formulaire remplit et lit des objets `Materiel`. Sans cette ligne, il fabriquerait un simple tableau.
- Les champs du formulaire se nomment `materiel[nom]` et `materiel[categorie]` dans le HTML : le préfixe vient du nom de la classe (`MaterielType` donne `materiel`). Retiens-le, les tests s'en servent.

Tu peux générer un type avec `php bin/console make:form`. Là aussi, nous l'écrivons à la main pour que rien ne soit magique.

---

## Étape 3 : le contrôleur

Ouvre `src/Controller/MaterielController.php`. Voici la liste complète des `use` dont il aura besoin à la fin de ce sujet, puis la nouvelle méthode :

```php
<?php

namespace App\Controller;

use App\Entity\Materiel;
use App\Form\MaterielType;
use App\Repository\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
```

```php
#[Route('/materiels/nouveau', name: 'materiel_nouveau', methods: ['GET', 'POST'])]
public function nouveau(Request $request, EntityManagerInterface $entityManager): Response
{
    $materiel = new Materiel();
    $formulaire = $this->createForm(MaterielType::class, $materiel);
    $formulaire->handleRequest($request);

    if ($formulaire->isSubmitted() && $formulaire->isValid()) {
        $entityManager->persist($materiel);
        $entityManager->flush();

        $this->addFlash('success', 'Le matériel a été ajouté.');

        return $this->redirectToRoute('materiel_fiche', ['id' => $materiel->getId()]);
    }

    return $this->renderForm('materiel/nouveau.html.twig', [
        'formulaire' => $formulaire,
    ]);
}
```

Lis cette méthode comme un récit, dans l'ordre :

1. `methods: ['GET', 'POST']` : la **même adresse** sert à deux choses. Un `GET` (le visiteur ouvre la page) affiche le formulaire vide ; un `POST` (il clique sur « Enregistrer ») envoie ses données.
2. `createForm(...)` fabrique le formulaire autour d'un objet `Materiel` vide.
3. `handleRequest($request)` regarde la requête : si c'est un `POST`, il copie les valeurs envoyées **dans l'objet** et lance la validation. Si c'est un `GET`, il ne fait rien.
4. `isSubmitted() && isValid()` : on n'enregistre que si le formulaire a bien été envoyé **et** que toutes les règles sont respectées.
5. `persist()` puis `flush()` : l'objet est ajouté à la base. `persist` dit « suis cet objet », `flush` envoie réellement les requêtes SQL.
6. On **redirige** vers la fiche au lieu d'afficher directement une page. C'est volontaire : si le visiteur recharge la page après l'enregistrement, son navigateur ne renverra pas le formulaire une seconde fois (il n'y aura pas de doublon). On appelle cela « rediriger après un envoi ».
7. Si le formulaire est invalide (ou simplement pas envoyé), on arrive au dernier `return` : `renderForm()` affiche le gabarit avec le formulaire et ses erreurs. Avec `renderForm()`, Symfony répond automatiquement avec le code **422** (« entité non traitable ») quand un formulaire envoyé est invalide. Ce code dit au navigateur et aux outils : « j'ai bien reçu ta demande, mais elle est refusée ».

Pourquoi `/materiels/nouveau` ne se confond-il pas avec `/materiels/{id}` ? Parce que la route de la fiche a la restriction `requirements: ['id' => '\d+']` : `nouveau` n'est pas un nombre, elle ne correspond donc pas. Sans cette restriction, Symfony aurait cru que « nouveau » était un identifiant.

---

## Étape 4 : les gabarits

Le dessin du formulaire sert pour l'ajout **et** pour la modification (partie 2) : on le met dans un petit gabarit à part, dont le nom commence par un tiret bas pour signaler qu'il n'est pas une page. Crée `templates/materiel/_formulaire.html.twig` :

```twig
{{ form_start(formulaire, {attr: {novalidate: 'novalidate'}}) }}
    {{ form_widget(formulaire) }}

    <button type="submit">Enregistrer</button>
{{ form_end(formulaire) }}
```

- `form_start` ouvre la balise `<form>` ; `form_end` la ferme (et affiche au passage tout champ que tu aurais oublié de dessiner).
- `form_widget(formulaire)` dessine tous les champs, leur étiquette et leurs messages d'erreur, ainsi qu'un **champ caché** qui contient le jeton de protection du formulaire (nous y revenons en partie 2).
- `novalidate` demande au navigateur de ne pas faire sa propre vérification : ici, nous voulons voir les messages **du serveur**, ceux qui comptent. Un navigateur peut toujours être contourné.
- Le bouton est écrit à la main : le type de formulaire ne décrit que les champs.

Crée ensuite la page, `templates/materiel/nouveau.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Ajouter un matériel{% endblock %}

{% block body %}
    <h1>Ajouter un matériel</h1>

    {{ include('materiel/_formulaire.html.twig') }}

    <p><a href="{{ path('materiel_liste') }}">Retour à la liste</a></p>
{% endblock %}
```

`include()` insère un autre gabarit à cet endroit, avec les mêmes variables : le sous-gabarit retrouve donc `formulaire`.

Dans `templates/materiel/liste.html.twig`, ajoute un lien juste après le titre `<h1>` (la liste complète est donnée en partie 2) :

```twig
<p><a href="{{ path('materiel_nouveau') }}">Ajouter un matériel</a></p>
```

---

## Étape 5 : afficher le message flash

Un **message flash** est un message qui vit **le temps d'une seule page** : le contrôleur le range dans la session avec `addFlash()`, la page suivante l'affiche, puis il disparaît. C'est ce qui permet de confirmer une action *après* une redirection.

Remplace `templates/base.html.twig`, qui doit désormais les afficher :

```twig
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>{% block title %}Parc informatique{% endblock %}</title>
    </head>
    <body>
        <header>
            <a href="{{ path('materiel_liste') }}">Parc informatique</a>
        </header>

        {% for type, messages in app.flashes %}
            {% for message in messages %}
                <p class="flash flash-{{ type }}">{{ message }}</p>
            {% endfor %}
        {% endfor %}

        {% block body %}{% endblock %}
    </body>
</html>
```

`app` est une variable que Twig reçoit toujours de Symfony ; `app.flashes` donne tous les messages en attente, rangés par type (`success`, `error`…). Le type devient une classe CSS : `flash-success`, `flash-error`. La classe `flash` commune permettra de les retrouver, et les tests la cherchent.

---

## Étape 6 : vérifier

1. Ouvre `http://localhost:8000/materiels/nouveau`, laisse le nom vide et clique sur « Enregistrer » : le formulaire revient avec « Le nom est obligatoire. ». Dans les outils du navigateur (onglet Réseau), la réponse porte le code **422**.
2. Remplis les deux champs : tu arrives sur la fiche, avec « Le matériel a été ajouté. » en haut. Recharge la page : le message a disparu, et rien n'a été ajouté une seconde fois.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute les règles sur `Materiel`, crée `MaterielType`, la méthode `nouveau()`, les deux gabarits, le lien dans la liste et l'affichage des messages dans `base.html.twig`. Vérifie les deux cas de l'étape 6, puis clique sur « Terminé ». La partie 2 ajoute la modification et la suppression.
