## Ce que nous construisons

On peut ajouter un matériel. Il reste à pouvoir le **modifier** et le **supprimer**. La modification ressemble beaucoup à l'ajout. La suppression est plus délicate : c'est une action dangereuse, qu'il ne faut jamais pouvoir déclencher **par erreur ni à l'insu de l'utilisateur**.

---

## Étape 1 : modifier

Le formulaire est le même ; seul l'objet change. Au lieu d'un `Materiel` vide, on donne au formulaire **un matériel qui existe déjà** : il se remplit tout seul avec ses valeurs. Ajoute cette méthode au contrôleur :

```php
#[Route('/materiels/{id}/modifier', name: 'materiel_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
public function modifier(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager): Response
{
    $materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');

    $formulaire = $this->createForm(MaterielType::class, $materiel);
    $formulaire->handleRequest($request);

    if ($formulaire->isSubmitted() && $formulaire->isValid()) {
        // L'objet vient de la base : Doctrine le suit déjà, pas besoin de persist().
        $entityManager->flush();

        $this->addFlash('success', 'Le matériel a été modifié.');

        return $this->redirectToRoute('materiel_fiche', ['id' => $materiel->getId()]);
    }

    return $this->renderForm('materiel/modifier.html.twig', [
        'materiel' => $materiel,
        'formulaire' => $formulaire,
    ]);
}
```

Compare avec `nouveau()` :

- l'adresse porte un identifiant, avec sa restriction `\d+` ;
- l'objet est **cherché** dans la base (avec une page introuvable s'il n'existe pas) au lieu d'être créé ;
- il n'y a **pas de `persist()`** : Doctrine suit déjà les objets qu'il a lus. Pendant `handleRequest`, le formulaire a modifié l'objet ; `flush()` envoie la différence à la base.

Crée `templates/materiel/modifier.html.twig`. Il réutilise le même sous-gabarit :

```twig
{% extends 'base.html.twig' %}

{% block title %}Modifier {{ materiel.nom }}{% endblock %}

{% block body %}
    <h1>Modifier le matériel</h1>

    {{ include('materiel/_formulaire.html.twig') }}

    <p><a href="{{ path('materiel_fiche', {id: materiel.id}) }}">Annuler</a></p>
{% endblock %}
```

Et remplace `templates/materiel/fiche.html.twig` pour ajouter le lien :

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ materiel.nom }}{% endblock %}

{% block body %}
    <h1>{{ materiel.nom }}</h1>

    <p>Catégorie : {{ materiel.categorie }}</p>

    <p>
        <a href="{{ path('materiel_modifier', {id: materiel.id}) }}">Modifier</a>
        <a href="{{ path('materiel_liste') }}">Retour à la liste</a>
    </p>
{% endblock %}
```

---

## Étape 2 : pourquoi la suppression est un cas à part

Imagine que la suppression soit un simple lien : `<a href="/materiels/3/supprimer">`. Deux problèmes :

1. **Un lien s'ouvre avec `GET`**, et `GET` doit rester une méthode **sans effet** : les moteurs de recherche, les extensions de navigateur et les systèmes de préchargement ouvrent des liens sans que personne ne clique. Un lien de suppression serait déclenché tout seul.
2. **Une attaque** : un site malveillant peut cacher dans sa page une image dont l'adresse est `http://ton-application/materiels/3/supprimer`. Si l'utilisateur est connecté chez toi, son navigateur envoie la requête, et le matériel disparaît. Cette attaque porte un nom : la **falsification de requête inter-sites** (CSRF en anglais, et c'est ce nom qu'on retrouve partout).

Les deux réponses :

- la suppression n'accepte que **`POST`** (`methods: ['POST']`) : un lien ne suffit plus, il faut un formulaire ;
- ce formulaire contient un **jeton secret**, propre à la session de l'utilisateur et à l'action. Le serveur fabrique le jeton quand il dessine la page ; à l'envoi, il vérifie qu'on le lui renvoie. Le site malveillant ne peut pas deviner ce jeton : sa requête est refusée.

---

## Étape 3 : la suppression côté contrôleur

Ajoute cette méthode :

```php
#[Route('/materiels/{id}/supprimer', name: 'materiel_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
public function supprimer(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager): Response
{
    $materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');

    if (!$this->isCsrfTokenValid('supprimer_materiel'.$materiel->getId(), (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'La suppression a été refusée : le jeton de sécurité est invalide.');

        return $this->redirectToRoute('materiel_liste');
    }

    $entityManager->remove($materiel);
    $entityManager->flush();

    $this->addFlash('success', 'Le matériel a été supprimé.');

    return $this->redirectToRoute('materiel_liste');
}
```

- `$request->request` contient les champs envoyés par `POST` ; `->get('_token')` lit le champ caché du formulaire (nous le créons à l'étape suivante). Le `(string)` transforme une valeur absente en texte vide, qui sera refusé.
- `isCsrfTokenValid('supprimer_materiel'.$materiel->getId(), $jeton)` demande à Symfony : « ce jeton est-il bien celui que j'ai fabriqué pour **cette action sur ce matériel** ? ». Le premier argument est un **identifiant** de jeton, libre mais qui doit être **identique** à celui du gabarit. Ajouter l'identifiant du matériel évite qu'un jeton valable pour le matériel 3 serve à supprimer le matériel 4.
- Si le jeton est mauvais, on **n'exécute rien** : ni `remove`, ni `flush`. On prévient l'utilisateur et on revient à la liste.
- Sinon, `remove()` marque l'objet à supprimer, `flush()` envoie le `DELETE` à la base.

---

## Étape 4 : le formulaire de suppression dans la liste

Remplace `templates/materiel/liste.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Matériels{% endblock %}

{% block body %}
    <h1>Matériels</h1>

    <p><a href="{{ path('materiel_nouveau') }}">Ajouter un matériel</a></p>

    <ul>
        {% for materiel in materiels %}
            <li>
                <a href="{{ path('materiel_fiche', {id: materiel.id}) }}">{{ materiel.nom }}</a>
                ({{ materiel.categorie }})
                <a href="{{ path('materiel_modifier', {id: materiel.id}) }}">Modifier</a>
                <form method="post" action="{{ path('materiel_supprimer', {id: materiel.id}) }}" style="display: inline">
                    <input type="hidden" name="_token" value="{{ csrf_token('supprimer_materiel' ~ materiel.id) }}">
                    <button type="submit">Supprimer</button>
                </form>
            </li>
        {% else %}
            <li>Aucun matériel enregistré.</li>
        {% endfor %}
    </ul>
{% endblock %}
```

- `method="post"` : un formulaire, pas un lien.
- `csrf_token('supprimer_materiel' ~ materiel.id)` fabrique le jeton. L'opérateur `~` colle deux textes ensemble : c'est le pendant, dans Twig, du `.` de PHP. L'identifiant obtenu est **le même** que dans le contrôleur.
- Le champ est caché (`type="hidden"`) et se nomme `_token`, comme le contrôleur le lit.
- Le bouton s'appelle « Supprimer » : les tests le cherchent par ce texte.

---

## Étape 5 : vérifier, y compris l'attaque

1. Modifie un matériel : le formulaire est prérempli, et le message « Le matériel a été modifié. » apparaît.
2. Supprime un matériel avec son bouton : il disparaît de la liste, avec son message.
3. **Joue l'attaquant.** Dans les outils du navigateur, onglet Éléments, change la valeur du champ caché `_token` d'un matériel, puis clique sur « Supprimer » : le matériel **reste**, avec le message « La suppression a été refusée ». Ouvre aussi directement `http://localhost:8000/materiels/1/supprimer` dans la barre d'adresse : c'est un `GET`, la réponse est **405** (méthode non autorisée).

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute les méthodes `modifier()` et `supprimer()`, les gabarits de modification, de fiche et de liste. Fais les trois vérifications de l'étape 5, y compris celle de l'attaquant. Clique ensuite sur « Terminé » : l'exercice reprend tout cela, sur les fournisseurs.
