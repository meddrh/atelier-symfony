## Ce que nous construisons

Au sujet 10, tu as fermé les pages selon le **rôle**. Un rôle répond à « que fait cette **personne** ? ». Mais certaines questions portent sur un **objet précis** :

- le gestionnaire peut-il modifier **ce** matériel ? Oui, comme tous les autres ;
- l'utilisateur à qui **ce** matériel est affecté peut-il le modifier ? Non : il peut seulement le **consulter** ;
- peut-il en consulter un autre ? Non plus.

Une règle de rôle dans `access_control` ne sait pas regarder l'objet. Il faut un **contrôle des droits** : une classe qui reçoit l'**utilisateur connecté**, une **action** (« modifier ») et l'**objet** concerné, et qui répond « oui » ou « non ».

Cette première partie construit le contrôle des droits des matériels, l'utilise dans les contrôleurs avec `denyAccessUnlessGranted()`, puis masque les boutons dans Twig avec `is_granted()`.

---

## Étape 1 : la classe qui décide

Crée `src/Security/MaterielVoter.php`. Symfony la reconnaît toute seule (`autoconfigure`) : aucune configuration à écrire.

```php
<?php

namespace App\Security;

use App\Entity\Materiel;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

/**
 * Le contrôle des droits sur un matériel : qui peut le consulter, le modifier, le supprimer.
 *
 * - le gestionnaire (et l'administrateur, qui le contient) modifie tout ;
 * - l'utilisateur à qui le matériel est affecté peut seulement le consulter ;
 * - seul l'administrateur supprime.
 */
class MaterielVoter extends Voter
{
    public const CONSULTER = 'CONSULTER';
    public const MODIFIER = 'MODIFIER';
    public const SUPPRIMER = 'SUPPRIMER';

    public function __construct(private Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Materiel
            && \in_array($attribute, [self::CONSULTER, self::MODIFIER, self::SUPPRIMER], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }

        // On compare les identifiants, pas les objets : « === » sur deux objets demande que ce soit la MÊME instance en mémoire.
        // Un utilisateur relu par une autre requête, ou un mandataire de Doctrine, est le même compte sans être le même objet.
        /** @var Materiel $subject */
        return match ($attribute) {
            self::CONSULTER => $this->security->isGranted('ROLE_TECHNICIEN')
                || (null !== $utilisateur->getId() && $subject->getUtilisateur()?->getId() === $utilisateur->getId()),
            self::MODIFIER => $this->security->isGranted('ROLE_GESTIONNAIRE'),
            self::SUPPRIMER => $this->security->isGranted('ROLE_ADMIN'),
        };
    }
}
```

Lis-la de haut en bas :

- les **actions** sont des chaînes que tu choisis : `CONSULTER`, `MODIFIER`, `SUPPRIMER`. Des constantes évitent les fautes de frappe ;
- `supports()` répond à « est-ce **mon** affaire ? ». Cette classe ne s'occupe que d'un `Materiel` et de ses trois actions. Pour tout le reste (un ticket, un autre mot), elle **s'abstient** : un autre contrôle répondra ;
- `voteOnAttribute()` répond « oui » (`true`) ou « non » (`false`) pour **un** utilisateur, **une** action et **un** objet ;
- pour les rôles, on demande à `Security::isGranted()` plutôt que de lire `getRoles()` : le service connaît la **hiérarchie** (un administrateur est aussi gestionnaire et technicien), `getRoles()` non ;
- `match` fait correspondre chaque action à sa règle. Le gestionnaire (et donc l'administrateur) **modifie** ; l'administrateur seul **supprime** ; **consulter** est permis à un technicien ou plus, et à l'utilisateur à qui le matériel est **affecté** (on compare les **identifiants** : `$subject->getUtilisateur()?->getId() === $utilisateur->getId()`). Comparer les objets avec `===` serait fragile : cela suppose la **même instance** en mémoire, alors qu'un même compte relu par une autre requête, ou un mandataire de Doctrine, est un autre objet. Le test `null !== $utilisateur->getId()` écarte aussi le cas de deux objets sans identifiant, qui seraient « égaux » à tort.

Au début de `voteOnAttribute()`, tout ce qui n'est pas un `Utilisateur` (un visiteur anonyme, par exemple) reçoit toujours « non » : on ne décide pas d'un droit pour quelqu'un qu'on ne connaît pas.

---

## Étape 2 : refuser dans le contrôleur

Dans `src/Controller/MaterielController.php`, ajoute le `use` :

```php
use App\Security\MaterielVoter;
```

Puis, dans les actions, **juste après** avoir chargé le matériel, pose la question :

```php
// fiche()
$materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');
$this->denyAccessUnlessGranted(MaterielVoter::CONSULTER, $materiel);

// modifier() et rebut()
$this->denyAccessUnlessGranted(MaterielVoter::MODIFIER, $materiel);

// supprimer()
// Le refus arrive avant tout : même un jeton CSRF valide n'ouvre pas la suppression à qui n'en a pas le droit.
$this->denyAccessUnlessGranted(MaterielVoter::SUPPRIMER, $materiel);
```

`denyAccessUnlessGranted()` ne renvoie rien quand c'est « oui ». Quand c'est « non », elle lève une exception que Symfony transforme en **403**. L'appel est placé **avant** la lecture du formulaire ou la vérification du jeton : un refus ne doit jamais laisser le temps de modifier quoi que ce soit, même en `POST`.

Remarque : l'ordre « d'abord 404, puis 403 » est volontaire. On ne peut pas décider du droit sur un objet qui n'existe pas.

---

## Étape 3 : masquer les boutons dans Twig

Le refus du contrôleur est la **vraie** protection. Mais on n'a pas envie de proposer un bouton qui mène à un 403 : Twig pose la même question avec `is_granted()`, en lui donnant l'objet.

Dans `templates/materiel/fiche.html.twig`, remplace les liens « Modifier » et « Mettre au rebut » :

```twig
        {# Le bouton masqué n'est qu'un confort : le refus réel est celui du contrôleur. #}
        {% if is_granted('MODIFIER', materiel) %}
            <a href="{{ path('materiel_modifier', {id: materiel.id}) }}">Modifier</a>
            {% if not materiel.estAuRebut %}
                <a href="{{ path('materiel_rebut', {id: materiel.id}) }}">Mettre au rebut</a>
            {% endif %}
        {% endif %}
```

Dans `templates/materiel/liste.html.twig`, remplace le contenu de la boucle, après `<li>` :

```twig
                {% if is_granted('CONSULTER', materiel) %}
                    <a href="{{ path('materiel_fiche', {id: materiel.id}) }}">{{ materiel.nom }}</a>
                {% else %}
                    {{ materiel.nom }}
                {% endif %}
                ({{ materiel.categorie }})
                {% if is_granted('MODIFIER', materiel) %}
                    <a href="{{ path('materiel_modifier', {id: materiel.id}) }}">Modifier</a>
                {% endif %}
                {% if is_granted('SUPPRIMER', materiel) %}
                    <form method="post" action="{{ path('materiel_supprimer', {id: materiel.id}) }}" style="display: inline">
                        <input type="hidden" name="_token" value="{{ csrf_token('supprimer_materiel' ~ materiel.id) }}">
                        <button type="submit">Supprimer</button>
                    </form>
                {% endif %}
```

La liste montre le nom de chaque matériel, mais ne propose le lien vers la fiche, la modification et la suppression que si le droit existe.

---

## Étape 4 : essayer

1. Dans un terminal, donne-toi un rôle (une seule fois) :

```bash
docker compose exec app php bin/console doctrine:query:sql "UPDATE utilisateur SET roles = '[\"ROLE_GESTIONNAIRE\"]' WHERE email = 'ton-adresse'"
```

2. Connecte-toi sur `/connexion` : la liste propose « Modifier », mais **pas** « Supprimer ».
3. Remplace `ROLE_GESTIONNAIRE` par `ROLE_ADMIN` : le bouton « Supprimer » apparaît.
4. Avec `ROLE_TECHNICIEN`, ouvre `/materiels/1/modifier` à la main : tu reçois **403**, même si aucun bouton ne t'y menait.
5. Relance `php bin/phpunit tests/Junior` : les tests restent verts (ils se connectent déjà comme technicien, qui consulte tout).

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée `MaterielVoter`, ajoute les quatre appels dans le contrôleur et modifie les deux gabarits. Fais ensuite les vérifications de l'étape 4, puis clique sur « Terminé ».
