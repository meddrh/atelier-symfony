## Ce que nous construisons

Les matériels sont reliés aux utilisateurs dans la base. Il reste à **s'en servir** : afficher les matériels d'un utilisateur avec une requête écrite dans le dépôt, créer la page de l'utilisateur, et permettre de choisir l'utilisateur dans le formulaire du matériel.

---

## Étape 1 : une requête dans le dépôt

Pour lire des matériels selon un critère, on écrit une **méthode dans le dépôt**, pas dans le contrôleur : le dépôt est l'endroit où vivent les requêtes. Ouvre `src/Repository/MaterielRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\Materiel;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Materiel>
 */
class MaterielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Materiel::class);
    }

    public const ORDRES = ['nom', 'categorie'];

    /**
     * Les matériels affectés à un utilisateur, triés par nom ou par catégorie.
     *
     * @return list<Materiel>
     */
    public function findAffectesA(Utilisateur $utilisateur, string $ordre = 'nom'): array
    {
        if (!\in_array($ordre, self::ORDRES, true)) {
            throw new \InvalidArgumentException(sprintf('Ordre de tri inconnu : « %s ».', $ordre));
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('m.'.$ordre, 'ASC')
            ->addOrderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

Lis la méthode `findAffectesA()` :

- `createQueryBuilder('m')` ouvre une requête sur les matériels ; `m` est un **alias**, le nom court qu'on donne à `Materiel` dans la requête.
- `andWhere('m.utilisateur = :utilisateur')` pose une condition. Le texte `:utilisateur` est un **paramètre nommé**, rempli par `setParameter()`. Ne colle **jamais** une valeur dans le texte de la requête : le paramètre est transmis séparément, et c'est ce qui empêche l'injection SQL.
- `orderBy()` ne peut pas recevoir un paramètre : le nom d'une colonne n'est pas une valeur. Comme il vient de l'extérieur (l'adresse de la page), on le **compare à une liste fermée** (`ORDRES`) et on lève une exception `InvalidArgumentException` s'il n'y est pas. Une valeur inconnue s'arrête ici, avant la base.
- `getResult()` renvoie un tableau d'objets `Materiel`.

---

## Étape 2 : la page d'un utilisateur

Crée `src/Controller/UtilisateurController.php` :

```php
<?php

namespace App\Controller;

use App\Repository\MaterielRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UtilisateurController extends AbstractController
{
    #[Route('/utilisateurs', name: 'utilisateur_liste')]
    public function liste(UtilisateurRepository $utilisateurRepository): Response
    {
        return $this->render('utilisateur/liste.html.twig', [
            'utilisateurs' => $utilisateurRepository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route(
        '/utilisateurs/{id}/{ordre}',
        name: 'utilisateur_fiche',
        requirements: ['id' => '\d+', 'ordre' => 'nom|categorie'],
        defaults: ['ordre' => 'nom'],
    )]
    public function fiche(int $id, string $ordre, UtilisateurRepository $utilisateurRepository, MaterielRepository $materielRepository): Response
    {
        $utilisateur = $utilisateurRepository->find($id) ?? throw $this->createNotFoundException('Utilisateur introuvable.');

        return $this->render('utilisateur/fiche.html.twig', [
            'utilisateur' => $utilisateur,
            'materiels' => $materielRepository->findAffectesA($utilisateur, $ordre),
            'ordre' => $ordre,
        ]);
    }
}
```

La route de la fiche contient deux paramètres :

- `{id}` : l'identifiant, limité aux chiffres par `\d+` ;
- `{ordre}` : le tri, limité à `nom` ou `categorie` par `nom|categorie`. Une autre valeur (`/utilisateurs/1/prix`) ne correspond à **aucune route** : le visiteur reçoit une page introuvable (404), pas une erreur.

`defaults: ['ordre' => 'nom']` donne une **valeur par défaut** au paramètre : l'adresse `/utilisateurs/1` fonctionne sans le tri, et Symfony passe `nom` au contrôleur. Un paramètre qui a une valeur par défaut est **facultatif** dans l'adresse.

---

## Étape 3 : les gabarits de l'utilisateur

Crée `templates/utilisateur/liste.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Utilisateurs{% endblock %}

{% block body %}
    <h1>Utilisateurs</h1>

    <ul>
        {% for utilisateur in utilisateurs %}
            <li>
                <a href="{{ path('utilisateur_fiche', {id: utilisateur.id}) }}">{{ utilisateur.nom }}</a>
                ({{ utilisateur.email }})
            </li>
        {% else %}
            <li>Aucun utilisateur enregistré.</li>
        {% endfor %}
    </ul>
{% endblock %}
```

Puis `templates/utilisateur/fiche.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ utilisateur.nom }}{% endblock %}

{% block body %}
    <h1>{{ utilisateur.nom }}</h1>

    <p>{{ utilisateur.email }}</p>

    <h2>Matériels affectés</h2>

    <p>
        Trier par :
        <a href="{{ path('utilisateur_fiche', {id: utilisateur.id, ordre: 'nom'}) }}">nom</a>
        <a href="{{ path('utilisateur_fiche', {id: utilisateur.id, ordre: 'categorie'}) }}">catégorie</a>
    </p>

    <ul>
        {% for materiel in materiels %}
            <li>
                <a href="{{ path('materiel_fiche', {id: materiel.id}) }}">{{ materiel.nom }}</a>
                ({{ materiel.categorie }})
            </li>
        {% else %}
            <li>Aucun matériel affecté.</li>
        {% endfor %}
    </ul>

    <p><a href="{{ path('utilisateur_liste') }}">Retour à la liste</a></p>
{% endblock %}
```

Dans `templates/base.html.twig`, ajoute un lien vers la liste des utilisateurs dans l'en-tête :

```twig
<header>
    <a href="{{ path('materiel_liste') }}">Parc informatique</a>
    <a href="{{ path('utilisateur_liste') }}">Utilisateurs</a>
</header>
```

---

## Étape 4 : choisir l'utilisateur dans le formulaire du matériel

Un champ qui propose **les lignes d'une autre table** s'écrit avec `EntityType`. Remplace `src/Form/MaterielType.php` :

```php
<?php

namespace App\Form;

use App\Entity\Materiel;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => 'nom',
                'label' => 'Affecté à',
                'required' => false,
                'placeholder' => 'Personne',
            ])
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

- `'class' => Utilisateur::class` : Doctrine va chercher tous les utilisateurs pour remplir la liste.
- `'choice_label' => 'nom'` : le texte de chaque option est la propriété `nom`.
- `'required' => false` et `'placeholder'` : le matériel peut n'être affecté à personne ; la première option, vide, s'appelle « Personne ».
- Le champ se nomme `utilisateur`, comme la propriété de `Materiel` : à l'envoi, le formulaire appelle `setUtilisateur()` avec **l'objet** choisi, pas avec un numéro.

---

## Étape 5 : afficher l'affectation sur la fiche du matériel

Dans `templates/materiel/fiche.html.twig`, sous la catégorie, ajoute :

```twig
<p>
    Affecté à :
    {% if materiel.utilisateur %}
        <a href="{{ path('utilisateur_fiche', {id: materiel.utilisateur.id}) }}">{{ materiel.utilisateur.nom }}</a>
    {% else %}
        personne
    {% endif %}
</p>
```

`{% if materiel.utilisateur %}` est vrai quand un utilisateur est affecté. Twig lit `materiel.utilisateur` en appelant `getUtilisateur()`.

---

## Étape 6 : vérifier

1. Ouvre `http://localhost:8000/utilisateurs` : tes deux utilisateurs apparaissent.
2. Ouvre la fiche d'Alice : le matériel 2 est listé. Clique sur « catégorie » : l'adresse devient `/utilisateurs/1/categorie`.
3. Essaie `/utilisateurs/1/prix` : page introuvable.
4. Modifie un matériel : la liste « Affecté à » propose les deux utilisateurs. Choisis-en un, enregistre, et retrouve le matériel sur sa fiche.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute la méthode du dépôt, le contrôleur, les deux gabarits et le lien de l'en-tête, remplace `MaterielType` et complète la fiche du matériel. Vérifie les quatre points de l'étape 6, puis clique sur « Terminé ».
