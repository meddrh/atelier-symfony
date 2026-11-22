## Ce que nous construisons

Une page que **tout le monde voit pareil** (le catalogue des matériels, sans compte) n'a pas besoin d'être refabriquée à chaque visite. Le **cache HTTP** permet à un navigateur, ou à un relais placé entre le site et les visiteurs, de la garder. Le serveur règle deux choses, avec deux en-têtes :

| Besoin | En-tête | Question posée |
|---|---|---|
| **Expiration** | `Cache-Control: public, max-age=300` | « Combien de temps puis-je la garder sans te demander ? » |
| **Validation** | `ETag` (et, en retour, `If-None-Match`) | « Ma copie est-elle encore la bonne ? » : le serveur répond **304**, sans corps, si oui |

Les deux se complètent : pendant 300 secondes, la copie est servie **sans aucune requête** ; ensuite, une requête **légère** (le 304) vérifie si elle est encore valable.

### Types de cache, côté client et côté serveur

- Un cache **privé** est celui du navigateur d'**un** visiteur : il peut garder une page qui lui est propre.
- Un cache **partagé** (un relais, un CDN) sert **plusieurs** visiteurs : il ne doit garder qu'une page **identique pour tous**. C'est ce que dit la directive `public`.
- **Côté client** : le navigateur applique `max-age` et renvoie `If-None-Match`. **Côté serveur** : un relais devant l'application peut répondre à sa place (la directive `s-maxage` ne vise que lui).

**Règle de sécurité : jamais `public` sur une page réservée à un compte.** Un relais la garderait et la servirait à quelqu'un d'autre. C'est pourquoi le catalogue est une **page à part**, volontairement publique et sans donnée personnelle, et non la liste des matériels (réservée aux comptes), qui ne porte jamais `public`.

---

## Étape 1 : une page publique, ouverte exprès

Dans `config/packages/security.yaml`, ajoute la règle **avant** la règle finale `^/` (la première qui correspond s'applique) :

```yaml
        # Le catalogue est public, VOLONTAIREMENT : il ne montre que le nom et la catégorie des matériels. Une page
        # réservée à des comptes ne doit jamais être mise en cache public (voir CatalogueController).
        - { path: ^/catalogue, roles: PUBLIC_ACCESS }
```

---

## Étape 2 : le contrôleur

Crée `src/Controller/CatalogueController.php` :

```php
<?php

namespace App\Controller;

use App\Repository\MaterielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CatalogueController extends AbstractController
{
    /** Combien de secondes un cache partagé (un relais, un CDN) peut garder la page sans nous interroger. */
    private const DUREE = 300;

    #[Route('/catalogue', name: 'catalogue', methods: ['GET'])]
    public function catalogue(Request $requete, MaterielRepository $materielRepository): Response
    {
        $materiels = $materielRepository->findBy([], ['nom' => 'ASC', 'id' => 'ASC']);

        // La page est la même pour tout le monde (public) : elle ne montre que le nom et la catégorie, et ne touche
        // ni à la session ni à l'utilisateur connecté (un cache public ne doit jamais servir la page d'un compte à un autre).
        $reponse = new Response();
        $reponse->setPublic();
        $reponse->setMaxAge(self::DUREE);
        // L'ETag résume ce que la page montre : si rien n'a changé, il reste le même.
        $reponse->setEtag(md5(json_encode(array_map(
            static fn ($materiel): array => [$materiel->getId(), $materiel->getNom(), $materiel->getCategorie()],
            $materiels,
        ), \JSON_THROW_ON_ERROR)));

        // Le navigateur (ou le relais) redemande avec « If-None-Match » : si l'ETag est identique, on répond 304 sans corps.
        if ($reponse->isNotModified($requete)) {
            return $reponse;
        }

        return $this->render('catalogue/liste.html.twig', ['materiels' => $materiels], $reponse);
    }
}
```

Observe :

- `setPublic()` et `setMaxAge()` écrivent `Cache-Control: public, max-age=300` ;
- `setEtag()` donne la « version » de la page : un résumé de **ce que la page montre**. Si un matériel change, l'étiquette change ;
- `isNotModified($requete)` compare l'ETag à l'en-tête `If-None-Match` de la requête. S'ils sont identiques, il **transforme la réponse en 304** et vide son corps : on la rend **sans fabriquer la page** (le gabarit n'est pas évalué) ;
- sinon `render()` reçoit la réponse déjà réglée comme troisième argument : le gabarit remplit son corps, les en-têtes de cache restent.

---

## Étape 3 : un gabarit qui n'étend pas la base

Crée `templates/catalogue/liste.html.twig` :

```twig
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Catalogue du parc</title>
    </head>
    <body>
        {# Cette page n'étend pas base.html.twig : la base lit les messages de la session et l'utilisateur connecté,
           et une page qui le fait ne peut plus être mise en cache public. #}
        <h1>Catalogue du parc</h1>

        <ul>
            {% for materiel in materiels %}
                <li>{{ materiel.nom }} ({{ materiel.categorie }})</li>
            {% else %}
                <li>Aucun matériel dans le catalogue.</li>
            {% endfor %}
        </ul>
    </body>
</html>
```

Ce gabarit **n'étend pas** `base.html.twig` : la base lit les messages de la session et l'utilisateur connecté. Une page qui touche à la session reçoit un cookie, et Symfony la marque alors comme **non partageable** (`private`) : le cache public ne servirait plus à rien.

---

## Étape 4 : le voir avec `curl`

Lance le serveur de développement du projet, puis :

```bash
curl -i http://localhost:8000/catalogue
```

Tu dois lire `Cache-Control: max-age=300, public` et une ligne `ETag: "…"`. Recopie cette valeur (guillemets compris) et redemande :

```bash
curl -i -H 'If-None-Match: "<la valeur recopiée>"' http://localhost:8000/catalogue
```

La réponse est un **`HTTP/1.1 304 Not Modified`**, sans corps. Ajoute un matériel, puis redemande avec la même étiquette : tu reçois de nouveau **200** et une **nouvelle** étiquette.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Ajoute la règle d'accès, le contrôleur et le gabarit, puis lance `bin/exercice test` : les tests du catalogue (public, mis en cache, 304, étiquette qui change) doivent passer. Clique sur « Terminé ».
