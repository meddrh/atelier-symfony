## Ce que nous construisons

Au début de ce sujet, un seul contrôle des droits répondait aux questions sur les matériels : `MaterielVoter`. Dans une vraie application, **plusieurs** contrôles répondent à la même question, et leurs réponses peuvent se contredire. Cette partie en ajoute un second, toujours sur les matériels :

- **un matériel encore affecté à quelqu'un ne se supprime pas** : on le retire d'abord de son utilisateur ;
- ce contrôle ne dit **jamais « oui »** : il dit « non », ou « ce n'est pas mon affaire » ;
- pour l'écrire, on n'étend pas la classe `Voter` : on implémente directement l'interface **`VoterInterface`**, qui laisse choisir entre les **trois** réponses possibles ;
- puis on regarde ce qui se passe quand `MaterielVoter` dit « oui » (l'administrateur supprime) et que le nouveau contrôle dit « non » : **qui gagne ?** Cela dépend de la **stratégie de décision**.

Dans l'exercice, tu retrouveras ces idées dans une autre situation, avec ses propres pièges : ici, le contrôle ne regarde qu'un objet précis.

---

## Étape 1 : un contrôle qui choisit lui-même sa réponse

La classe `Voter` que tu as étendue en partie 1 est une **aide** : elle implémente l'interface `VoterInterface` à ta place, en la réduisant à deux questions (`supports()` : est-ce mon affaire ? ; `voteOnAttribute()` : oui ou non ?). Elle a un coût : tu ne peux répondre que « oui » ou « non » **aux questions qui sont ton affaire**, et rien d'autre.

L'interface elle-même n'a **qu'une méthode**, `vote()`, qui rend l'un de **trois** entiers (des constantes de l'interface) :

| Constante | Valeur | Sens |
|---|---|---|
| `ACCESS_GRANTED` | 1 | « oui » |
| `ACCESS_DENIED` | -1 | « non » |
| `ACCESS_ABSTAIN` | 0 | « ce n'est pas mon affaire » : je ne me prononce pas |

Crée `src/Security/MaterielAffecteVoter.php`. Symfony le reconnaît tout seul (`autoconfigure` : toute classe qui implémente `VoterInterface` est enregistrée comme contrôle des droits) :

```php
<?php

namespace App\Security;

use App\Entity\Materiel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Un matériel encore affecté à quelqu'un ne se supprime pas : on le retire d'abord de son utilisateur.
 *
 * Ce contrôle ne dit jamais « oui » : il dit « non » (ACCESS_DENIED) ou « ce n'est pas mon affaire » (ACCESS_ABSTAIN).
 * Pour la suppression, c'est MaterielVoter qui dit « oui » à l'administrateur ; que le « non » d'ici l'emporte ou non
 * dépend de la stratégie de décision (voir tests/Junior/MaterielAffecteVoterTest.php).
 *
 * Il implémente VoterInterface plutôt que d'étendre Voter : il veut choisir lui-même entre les trois réponses, au lieu
 * de laisser la classe de base les déduire d'un « supports() » et d'un booléen.
 */
class MaterielAffecteVoter implements VoterInterface
{
    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        // Pas mon affaire : autre chose qu'un matériel, ou une autre action que la suppression.
        if (!$subject instanceof Materiel || !\in_array(MaterielVoter::SUPPRIMER, $attributes, true)) {
            return self::ACCESS_ABSTAIN;
        }

        return null !== $subject->getUtilisateur() ? self::ACCESS_DENIED : self::ACCESS_ABSTAIN;
    }
}
```

Lis-la :

- `vote()` reçoit le **jeton** de l'utilisateur connecté, l'**objet** (`$subject`) et la **liste** des actions demandées (`$attributes`). Elle rend un entier, jamais un booléen ;
- la première condition écarte tout ce qui n'est pas son affaire : un autre objet qu'un matériel, ou une action autre que `SUPPRIMER`. Elle **s'abstient** : un autre contrôle répondra. S'abstenir n'est ni « oui » ni « non » : c'est **ne pas voter** ;
- ensuite, un matériel affecté (`getUtilisateur()` n'est pas `null`) reçoit **« non »**. Un matériel libre reçoit une **abstention**, pas un « oui » : ce contrôle n'a aucune raison d'autoriser quoi que ce soit, c'est `MaterielVoter` qui autorise ;
- on compare l'action à la **constante** de `MaterielVoter` (`MaterielVoter::SUPPRIMER`) : une seule source pour le mot `SUPPRIMER` ;
- avec `Voter`, une classe qui ne dit jamais « oui » devrait détourner `voteOnAttribute()` pour fabriquer un « non » ou ne pas voter ; ici, la réponse est **écrite telle qu'elle est pensée**.

---

## Étape 2 : le tester, seul, puis avec les deux stratégies

Un contrôle des droits est une classe ordinaire : pas besoin du noyau ni de la base pour lui poser une question. Crée `tests/Junior/MaterielAffecteVoterTest.php` :

```php
<?php

namespace App\Tests\Junior;

use App\Entity\Materiel;
use App\Entity\Ticket;
use App\Entity\Utilisateur;
use App\Security\MaterielAffecteVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Pas de noyau, pas de base : un contrôle des droits est une classe ordinaire, on lui pose la question directement.
 */
class MaterielAffecteVoterTest extends TestCase
{
    public function testUnMaterielAffecteEstRefuseALaSuppression(): void
    {
        $vote = (new MaterielAffecteVoter())->vote($this->token(), $this->materielAffecte(), ['SUPPRIMER']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function testUnMaterielLibreNEstPasConcerne(): void
    {
        $vote = (new MaterielAffecteVoter())->vote($this->token(), new Materiel(), ['SUPPRIMER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vote, 'Il ne dit jamais « oui » : il s\'abstient.');
    }

    public function testUneAutreActionNEstPasConcernee(): void
    {
        $vote = (new MaterielAffecteVoter())->vote($this->token(), $this->materielAffecte(), ['MODIFIER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vote);
    }

    public function testUnAutreObjetNEstPasConcerne(): void
    {
        $vote = (new MaterielAffecteVoter())->vote($this->token(), new Ticket(), ['SUPPRIMER']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vote);
    }

    public function testAvecLaStrategieParDefautUnSeulOuiSuffit(): void
    {
        $gestionnaire = new AccessDecisionManager([new MaterielAffecteVoter(), $this->leControleDesMaterielsDitOui()], new AffirmativeStrategy());

        self::assertTrue($gestionnaire->decide($this->token(), ['SUPPRIMER'], $this->materielAffecte()), 'Le « oui » l\'emporte sur le « non ».');
    }

    public function testAvecLaStrategieUnanimeUnSeulNonEmporte(): void
    {
        $gestionnaire = new AccessDecisionManager([new MaterielAffecteVoter(), $this->leControleDesMaterielsDitOui()], new UnanimousStrategy());

        self::assertFalse($gestionnaire->decide($this->token(), ['SUPPRIMER'], $this->materielAffecte()), 'Le « non » l\'emporte sur le « oui ».');
    }

    public function testAvecLaStrategieUnanimeUnMaterielLibreSeSupprime(): void
    {
        $gestionnaire = new AccessDecisionManager([new MaterielAffecteVoter(), $this->leControleDesMaterielsDitOui()], new UnanimousStrategy());

        self::assertTrue($gestionnaire->decide($this->token(), ['SUPPRIMER'], new Materiel()), 'Une abstention ne bloque personne.');
    }

    private function token(): TokenInterface
    {
        return new UsernamePasswordToken(new Utilisateur(), 'main', ['ROLE_ADMIN']);
    }

    private function materielAffecte(): Materiel
    {
        return (new Materiel())->setUtilisateur(new Utilisateur());
    }

    /** Un contrôle qui joue le rôle de MaterielVoter pour l'administrateur : il dit toujours « oui ». */
    private function leControleDesMaterielsDitOui(): VoterInterface
    {
        return new class() implements VoterInterface {
            public function vote(TokenInterface $token, mixed $subject, array $attributes): int
            {
                return self::ACCESS_GRANTED;
            }
        };
    }
}
```

- les **quatre premiers tests** interrogent le contrôle seul : « non » pour un matériel affecté, abstention dans tous les autres cas ;
- les **trois derniers** fabriquent à la main un **gestionnaire de décision** (`AccessDecisionManager`, la classe qui combine les réponses de tous les contrôles) avec **deux** contrôles : le nôtre, et une classe anonyme qui dit toujours « oui » et joue le rôle de `MaterielVoter` pour l'administrateur. On lui donne chaque fois une **stratégie** différente, et on pose la **même** question ;
- avec `AffirmativeStrategy` (celle de Symfony par défaut), la réponse est **oui** ; avec `UnanimousStrategy`, **non**. Le dernier test montre que, pour un matériel libre, l'abstention du nouveau contrôle ne gêne personne.

Lance-les :

```bash
docker compose exec app php bin/phpunit tests/Junior/MaterielAffecteVoterTest.php
```

---

## Étape 3 : ce que change « unanime »

Symfony interroge **tous** les contrôles, puis la **stratégie** décide comment combiner leurs réponses. Prenons une question où trois contrôles répondent : un « oui », un « non », une abstention.

| Stratégie | Règle | Résultat ici |
|---|---|---|
| `affirmative` (par défaut) | un seul « oui » suffit | **accès accordé** |
| `consensus` | la majorité l'emporte (à égalité : réglage `allow_if_equal_granted_denied`) | égalité 1 contre 1 : accordé par défaut |
| `unanimous` | **un seul « non » refuse** ; les abstentions comptent pour rien | **accès refusé** |
| `priority` | le premier contrôle qui ne s'abstient pas décide | dépend de l'ordre des contrôles |

Si **tous** s'abstiennent, le réglage `allow_if_all_abstain` décide ; il est **faux** par défaut : refusé.

**Essaie-le dans l'application**, en trois temps.

1. Donne-toi le rôle `ROLE_ADMIN` (la commande `doctrine:query:sql` de la partie 1, avec `ROLE_ADMIN` à la place de `ROLE_GESTIONNAIRE`), connecte-toi, et ouvre `/materiels` : les matériels **affectés** à quelqu'un portent un bouton « Supprimer » (ne clique pas dessus). Le « non » de `MaterielAffecteVoter` ne compte pour rien : le « oui » de `MaterielVoter` suffit.
2. Dans `config/packages/security.yaml`, ajoute cette clé, **au même niveau** que `providers` et `firewalls` (sous `security:`) :

```yaml
    access_decision_manager:
        strategy: unanimous
```

Recharge `/materiels` : le bouton « Supprimer » a **disparu** des matériels affectés, et il reste sur les matériels libres. La même question, posée avec une autre stratégie, a reçu une autre réponse.
3. **Retire** ces deux lignes avant de continuer : l'exercice te demandera de choisir la stratégie à ton tour, pour une autre raison. Le contrôle que tu viens d'écrire reste en place ; il attend simplement qu'une stratégie lui donne du poids.

Une précaution quand tu choisis `unanimous` : **tous** les contrôles de l'application passent par elle, pas seulement les tiens. Un contrôle qui dit « non » par erreur bloque alors tout, même quand les autres disent « oui ». Après un changement de stratégie, relance **tous** tes tests.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée la classe et son test, lance-le, puis fais l'essai de l'étape 3 (et retire la clé à la fin). Relance enfin `php bin/phpunit tests/Junior`, puis clique sur « Terminé ».
