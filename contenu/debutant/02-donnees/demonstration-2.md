## Ce que nous construisons

La table `materiel` existe et contient trois lignes. Les pages `/materiels` et `/materiels/2` lisent encore le tableau en dur. Nous allons les brancher sur la base : le contrôleur ne connaîtra plus les données, il **demandera au dépôt**.

---

## Étape 1 : recevoir le dépôt

Dans une méthode de contrôleur, il suffit d'ajouter le dépôt dans la liste des arguments, avec son type :

```php
public function liste(MaterielRepository $materielRepository): Response
```

Symfony lit le type, fabrique l'objet et le passe à la méthode. C'est le **câblage automatique** : tu demandes ce dont tu as besoin, tu ne le fabriques pas. Tu n'écris jamais `new MaterielRepository(...)`.

---

## Étape 2 : lire les données

Le dépôt hérite de méthodes toutes prêtes :

| Appel | Résultat |
|---|---|
| `$materielRepository->findAll()` | tous les objets de la table, dans une liste |
| `$materielRepository->find($id)` | l'objet dont la clé primaire vaut `$id`, ou `null` s'il n'existe pas |
| `$materielRepository->findBy([], ['nom' => 'ASC'])` | tous les objets, triés (ici par nom croissant) |

Voici le contrôleur complet. Remplace le contenu de `src/Controller/MaterielController.php` :

```php
<?php

namespace App\Controller;

use App\Repository\MaterielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MaterielController extends AbstractController
{
    #[Route('/materiels', name: 'materiel_liste')]
    public function liste(MaterielRepository $materielRepository): Response
    {
        return $this->render('materiel/liste.html.twig', [
            'materiels' => $materielRepository->findAll(),
        ]);
    }

    #[Route('/materiels/{id}', name: 'materiel_fiche', requirements: ['id' => '\d+'])]
    public function fiche(int $id, MaterielRepository $materielRepository): Response
    {
        $materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');

        return $this->render('materiel/fiche.html.twig', [
            'materiel' => $materiel,
        ]);
    }
}
```

Remarque : la ligne qui lève la page introuvable n'a presque pas changé. `find()` renvoie `null` pour un identifiant inconnu, exactement comme le tableau en dur.

---

## Étape 3 : adapter les gabarits

Les données ne sont plus des tableaux, mais des objets `Materiel`. Dans Twig, `materiel.nom` fonctionne toujours : Twig appelle `getNom()` tout seul. En revanche, la liste n'a plus de clé pour l'identifiant : il faut le lire sur l'objet (`materiel.id`).

Remplace `templates/materiel/liste.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Matériels{% endblock %}

{% block body %}
    <h1>Matériels</h1>

    <ul>
        {% for materiel in materiels %}
            <li>
                <a href="{{ path('materiel_fiche', {id: materiel.id}) }}">{{ materiel.nom }}</a>
                ({{ materiel.categorie }})
            </li>
        {% else %}
            <li>Aucun matériel enregistré.</li>
        {% endfor %}
    </ul>
{% endblock %}
```

La partie `{% else %}` d'une boucle ne s'exécute que si la liste est vide.

Le gabarit `materiel/fiche.html.twig` n'a pas besoin de changer : il lisait déjà `materiel.nom` et `materiel.categorie`.

---

## Étape 4 : vérifier

Recharge `http://localhost:8000/materiels` : les trois matériels de la base s'affichent. Ajoute-en un par SQL :

```bash
docker compose exec app php bin/console doctrine:query:sql "INSERT INTO materiel (nom, categorie) VALUES ('Imprimante laser', 'Informatique')"
```

Recharge la page : il apparaît, **sans que tu aies modifié une ligne de code**. C'est tout l'intérêt d'une base de données. Vérifie aussi que `/materiels/999` répond toujours 404.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Remplace le contrôleur et le gabarit de la liste, puis recharge tes pages. Quand la liste affiche le contenu de la base et que l'identifiant inconnu donne une page introuvable, clique sur « Terminé ». L'exercice porte sur les fournisseurs.
