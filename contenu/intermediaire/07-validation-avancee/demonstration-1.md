## Ce que nous construisons

Jusqu'ici, une entité était valide ou non, une fois pour toutes. Mais une règle ne vaut pas toujours **à tout moment**. Quand on **met un matériel au rebut**, on veut un **motif** : pourquoi n'est-il plus utilisé ? Quand on crée ou qu'on modifie ce même matériel, personne ne demande de motif.

Les **groupes de validation** règlent ce problème : on range les règles dans des groupes nommés, et chaque formulaire dit quels groupes il applique. Dans cette partie :

- une propriété `motifRebut` sur `Materiel`, obligatoire **seulement** dans le groupe `rebut` ;
- un formulaire de mise au rebut qui n'applique que ce groupe ;
- une règle qui touche **deux** propriétés à la fois, écrite avec une **méthode de rappel** : un matériel encore affecté à quelqu'un ne peut pas être mis au rebut.

La partie 2 écrira ses propres contraintes.

---

## Étape 1 : le groupe par défaut et les autres

Sans précision, une contrainte appartient au groupe `Default`, et la validation d'un objet n'examine que ce groupe. Vérifie-le avec ce que tu sais déjà : `#[Assert\NotBlank]` sur le nom est dans `Default`, donc un formulaire de matériel valide le nom.

Pour ranger une règle à part, on lui donne un **autre nom de groupe** :

```php
#[Assert\NotBlank(message: 'Le motif de mise au rebut est obligatoire.', groups: ['rebut'])]
```

Cette règle n'est **plus** dans `Default` : le formulaire de création n'y touche pas. Elle ne s'applique que si on demande le groupe `rebut`.

---

## Étape 2 : l'entité

Dans `src/Entity/Materiel.php`, ajoute la propriété sous `$dateRetour` :

```php
// Renseigné seulement à la mise au rebut ; obligatoire dans le groupe « rebut ».
#[ORM\Column(length: 255, nullable: true)]
#[Assert\NotBlank(message: 'Le motif de mise au rebut est obligatoire.', groups: ['rebut'])]
#[Assert\Length(max: 255, maxMessage: 'Le motif ne doit pas dépasser {{ limit }} caractères.', groups: ['rebut'])]
private ?string $motifRebut = null;
```

Ses méthodes d'accès, à la fin de la classe (`estAuRebut()` dit si le matériel a un motif, donc s'il est au rebut) :

```php
public function getMotifRebut(): ?string
{
    return $this->motifRebut;
}

public function setMotifRebut(?string $motifRebut): self
{
    $this->motifRebut = $motifRebut;

    return $this;
}

public function estAuRebut(): bool
{
    return null !== $this->motifRebut;
}
```

Lis `groups: ['rebut']` : la contrainte `Length` est rangée dans le même groupe, pour qu'un motif trop long soit aussi refusé à ce moment-là, et seulement à ce moment-là.

---

## Étape 3 : le formulaire de mise au rebut

Crée `src/Form/MiseAuRebutType.php` :

```php
<?php

namespace App\Form;

use App\Entity\Materiel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MiseAuRebutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('motifRebut', TextType::class, ['label' => 'Motif de la mise au rebut']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Materiel::class,
            // Seules les règles du groupe « rebut » s'appliquent à ce formulaire.
            'validation_groups' => ['rebut'],
        ]);
    }
}
```

L'option `validation_groups` dit quels groupes ce formulaire applique : **seulement** `rebut`. Les règles du groupe `Default` (nom, catégorie…) ne sont donc pas examinées ici : on n'édite que le motif.

Dans `MaterielType`, rien ne change : sans `validation_groups`, il applique `Default`, et le motif n'y est pas exigé.

---

## Étape 4 : le contrôleur

Dans `src/Controller/MaterielController.php`, ajoute la ligne `use App\Form\MiseAuRebutType;` avec les autres, puis cette méthode, avant `supprimer()` :

```php
#[Route('/materiels/{id}/rebut', name: 'materiel_rebut', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
public function rebut(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager): Response
{
    $materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');

    $formulaire = $this->createForm(MiseAuRebutType::class, $materiel);
    $formulaire->handleRequest($request);

    if ($formulaire->isSubmitted() && $formulaire->isValid()) {
        $entityManager->flush();

        $this->addFlash('success', 'Le matériel a été mis au rebut.');

        return $this->redirectToRoute('materiel_fiche', ['id' => $materiel->getId()]);
    }

    return $this->renderForm('materiel/rebut.html.twig', [
        'materiel' => $materiel,
        'formulaire' => $formulaire,
    ]);
}
```

Le schéma est celui de `modifier()` : on lit le matériel, on crée le formulaire, on l'applique à la requête, puis `isValid()` décide. Un motif vide ne passe pas `isValid()` : le formulaire se réaffiche avec le code **422**, et rien n'est enregistré. Il n'y a pas d'appel à `persist()` : l'objet vient de la base, Doctrine le suit déjà.

---

## Étape 5 : les gabarits

Crée `templates/materiel/rebut.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Mettre {{ materiel.nom }} au rebut{% endblock %}

{% block body %}
    <h1>Mettre {{ materiel.nom }} au rebut</h1>

    {{ form_start(formulaire, {attr: {novalidate: 'novalidate'}}) }}
        {{ form_errors(formulaire) }}
        {{ form_widget(formulaire) }}

        <button type="submit">Mettre au rebut</button>
    {{ form_end(formulaire) }}

    <p><a href="{{ path('materiel_fiche', {id: materiel.id}) }}">Annuler</a></p>
{% endblock %}
```

`form_errors(formulaire)` affiche les erreurs qui concernent le formulaire **dans son ensemble** (et pas un champ). C'est utile pour une règle qui n'est rattachée à aucun champ ; celle de l'étape 6, elle, désigne un champ.

Dans `templates/materiel/fiche.html.twig`, affiche le motif sous la facture :

```twig
{% if materiel.estAuRebut %}
    <p>Au rebut : {{ materiel.motifRebut }}</p>
{% endif %}
```

Et propose le lien, à côté de « Modifier » (seulement tant que le matériel n'est pas au rebut) :

```twig
{% if not materiel.estAuRebut %}
    <a href="{{ path('materiel_rebut', {id: materiel.id}) }}">Mettre au rebut</a>
{% endif %}
```

`materiel.estAuRebut` appelle la méthode `estAuRebut()` : Twig sait aussi lire les méthodes dont le nom commence par `is`, `has` ou `get`, mais ici on écrit le nom de la méthode telle quelle.

---

## Étape 6 : une règle qui touche deux propriétés

« Un matériel affecté à quelqu'un ne peut pas être mis au rebut » compare **deux** propriétés (`utilisateur` et `motifRebut`). Une contrainte posée sur une seule propriété ne peut pas le dire. On écrit alors une **méthode de rappel** : une méthode de l'entité, marquée `#[Assert\Callback]`, que le validateur appelle.

Ajoute-la à la fin de `Materiel`, avec le `use` correspondant :

```php
use Symfony\Component\Validator\Context\ExecutionContextInterface;
```

```php
/** Règle qui porte sur deux propriétés à la fois : un matériel affecté n'est pas mis au rebut. */
#[Assert\Callback(groups: ['rebut'])]
public function verifierQueLeMaterielEstLibre(ExecutionContextInterface $contexte): void
{
    if (null !== $this->utilisateur) {
        $contexte->buildViolation("Un matériel encore affecté à quelqu'un ne peut pas être mis au rebut.")
            ->atPath('motifRebut')
            ->addViolation();
    }
}
```

Lis-la dans l'ordre :

- `groups: ['rebut']` : elle ne s'applique que pour la mise au rebut, comme le motif ;
- la méthode reçoit un **contexte d'exécution** ; si la règle est violée, on construit une **violation** avec `buildViolation()` ;
- `atPath('motifRebut')` rattache l'erreur au champ `motifRebut` : c'est là que le formulaire l'affichera. Sans `atPath`, l'erreur serait rattachée à l'objet entier ;
- `addViolation()` l'enregistre. La méthode ne renvoie rien : seules les violations comptent.

Comme tout ce qui est lu par le validateur, la méthode est publique et sans argument autre que le contexte.

---

## Étape 7 : la migration

Une seule colonne de texte : `make:migration` fait le travail sans difficulté.

```bash
docker compose exec app php bin/console make:migration
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console doctrine:schema:update --dump-sql --complete
```

Relis le fichier généré : il contient `ALTER TABLE materiel ADD COLUMN motif_rebut VARCHAR(255) DEFAULT NULL`, et la dernière commande répond « Nothing to update ». Pas de commentaire de type à conserver : ce piège (voir les sujets précédents) ne concerne que les colonnes de date et de liste.

---

## Étape 8 : vérifier

1. Ouvre la fiche d'un matériel, clique sur « Mettre au rebut » et envoie le formulaire **vide** : l'erreur « Le motif de mise au rebut est obligatoire. » apparaît, rien n'est enregistré.
2. Écris un motif : retour sur la fiche, qui affiche « Au rebut : … ». Le lien « Mettre au rebut » a disparu.
3. Prends un matériel **affecté** à un utilisateur : même avec un motif, la mise au rebut est refusée avec le message de la méthode de rappel.
4. Crée ou modifie un matériel : aucun motif n'est demandé.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute la propriété et ses règles, la méthode de rappel, le formulaire, la route, les deux gabarits et la migration. Vérifie les quatre points de l'étape 8, puis clique sur « Terminé ».
