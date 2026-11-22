## À faire

Tu sais décider d'un droit sur un **objet** (les matériels) et écrire une règle en expression. À toi de protéger les **tickets**, de désactiver un compte pour de bon, et de choisir **comment les contrôles se combinent**, en suivant ta fiche de préparation. Tes tests créent leurs propres utilisateurs : rien n'est à préparer en base.

1. **Le technicien et l'état du compte.**
   - `Ticket` reçoit un `technicien` : une relation vers `Utilisateur`, **facultative** (un ticket peut ne pas en avoir). Si l'utilisateur est supprimé, le champ revient à « personne » : les tickets restent.
   - `Utilisateur` reçoit `actif`, un booléen **vrai par défaut** (`isActif()`, `setActif()`). Les comptes déjà en base restent actifs.
   - Une **seule** migration, nouvelle (ne modifie jamais une ancienne). Génère-la, **relis-la** (SQLite recrée parfois la table), puis lance les tests : `MigrationsTest` te montre le SQL qui manque.
2. **Le contrôle des droits sur les tickets** : une classe `App\Security\TicketVoter` avec les actions `MODIFIER` et `SUPPRIMER`.
   - `MODIFIER` : le **technicien chargé du ticket**, tout **gestionnaire** et tout **administrateur**. Un autre technicien, ou un ticket sans technicien pour un simple technicien : refus.
   - `SUPPRIMER` : **l'administrateur seulement**.
   - Les rôles se testent avec `isGranted()` du service `Security`, qui connaît la hiérarchie.
3. **Les contrôleurs et les gabarits.**
   - `TicketController` : `modifier()` et `cloturer()` refusent avec `denyAccessUnlessGranted()`, **en `GET` comme en `POST`**, avant de lire le formulaire.
   - Une nouvelle action `supprimer()` : route `/tickets/{id}/supprimer`, **`POST` seulement**, nom `ticket_supprimer`. Le contrôle des droits passe **avant** la vérification du jeton CSRF (identifiant du jeton : `'supprimer_ticket'.$ticket->getId()`). Après la suppression, retour à la fiche du matériel.
   - `templates/ticket/_ticket.html.twig` : les liens « Modifier » et « Clôturer » et le bouton « Supprimer » (un petit formulaire en `POST`, avec le jeton) n'apparaissent que si `is_granted(…)` répond « oui ». Le nom du technicien s'affiche quand il y en a un.
   - **Créer demande les mêmes droits que modifier.** `MaterielController::nouveau()` est réservé au gestionnaire (`denyAccessUnlessGranted('ROLE_GESTIONNAIRE')`, le lien « Ajouter un matériel » masqué sinon). `TicketController::nouveau()` (signaler un incident) exige de pouvoir **consulter** le matériel : un utilisateur simple ne crée pas de ticket sur un matériel qui n'est pas le sien. Côté fournisseurs, création et modification sont réservées au gestionnaire, la suppression à l'administrateur. Chaque refus est testé en `GET` **et** en `POST`, sans rien changer en base.
4. **Le compte désactivé.** Une classe `App\Security\CompteActifVoter` répond **« non »** à **toute** question d'un utilisateur dont `isActif()` est faux, et **s'abstient** sinon.
   - Elle ne dit jamais « oui ».
   - Elle laisse passer l'attribut `PUBLIC_ACCESS` (la page de connexion reste ouverte).
   - Indice : elle ne regarde ni l'action ni l'objet. Elle n'a donc pas besoin de la classe de base `Voter` (et de son `supports()`) : elle implémente directement `VoterInterface`, avec sa seule méthode `vote()`, comme dans la démonstration, partie 3 (voir le squelette plus bas).
   - Attention : tous les utilisateurs ne sont pas des `Utilisateur`. Le lecteur d'inventaire de la démonstration 10-3 est un utilisateur en mémoire, sans méthode `isActif()` : vérifie le type (`instanceof Utilisateur`) avant d'appeler `isActif()`, sinon `/inventaire` plante.
5. **La stratégie de décision.** Dans `config/packages/security.yaml`, la clé `access_decision_manager` : un seul « non » doit l'emporter (stratégie **`unanimous`**).
   - Vérifie toi-même, **avant de cliquer sur « Terminé »**, que le test du compte désactivé **échoue** quand tu remets la stratégie par défaut (`affirmative`), puis remets `unanimous`.

Contraintes :

- le refus est **dans le contrôleur** : un bouton masqué ne compte pas. Chaque droit est testé par un appel direct, en `GET` **et** en `POST`, et le test vérifie que **rien n'a changé** en base ;
- un utilisateur sans le droit reçoit **403**, jamais une page à moitié affichée ni une redirection ;
- le contrôle des droits **ne lit pas** `getRoles()` : il demande à `isGranted()`, qui connaît la hiérarchie ;
- un conseil : lance aussi tes tests du sujet 10 et ceux du début de ce sujet (`tests/Junior` compris), pour voir tout de suite si un changement les casse.

Un conseil sur l'ordre : commence par les deux champs et la migration, puis `TicketVoter` avec ses refus en `GET`, ensuite les boutons. Garde le compte désactivé pour la fin : tant que la stratégie n'est pas changée, le test qui le concerne reste rouge, c'est normal.

## Squelette des fichiers

Ce sujet crée deux classes, les deux contrôles de droits, et ajoute des méthodes à `Ticket`, `Utilisateur` et `TicketController`. Le squelette ne te donne pas la solution : qui a le droit de quoi, la suppression et le choix du refus restent à écrire. Les contrôleurs `MaterielController`, `FournisseurController` et les méthodes `modifier()`, `cloturer()` et `nouveau()` de `TicketController` ne changent pas de signature : ils gagnent seulement des appels à `denyAccessUnlessGranted()`, c'est le travail de l'exercice et il n'y a pas de squelette pour cela. Il n'y en a pas non plus pour les propriétés `technicien` et `actif` (à déclarer toi-même, avec leur colonne), la migration, `security.yaml` ni les gabarits : ce ne sont pas des méthodes à ajouter, ou pas des classes PHP.

Le contrôle des droits sur les tickets. La classe de base `Voter` impose deux méthodes, **protégées** : leurs signatures sont exactes, sinon la classe ne compile pas. Les deux actions sont des constantes, pour que le contrôleur n'écrive pas deux fois le même texte.

```php
<?php
// src/Security/TicketVoter.php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class TicketVoter extends Voter
{
    public const MODIFIER = 'MODIFIER';
    public const SUPPRIMER = 'SUPPRIMER';

    public function __construct(Security $security)
    {
        // À toi : garder le service qui sait si un rôle est accordé.
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // À toi : dire si cette classe s'occupe de la question (quelle action, sur quel objet).
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // À toi : répondre pour MODIFIER et pour SUPPRIMER, selon les règles de la consigne.
    }
}
```

Le contrôle du compte désactivé. Il implémente l'interface `VoterInterface` : sa seule méthode est imposée, avec cette signature.

```php
<?php
// src/Security/CompteActifVoter.php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class CompteActifVoter implements VoterInterface
{
    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        // À toi : répondre « non » à un compte désactivé, s'abstenir sinon (et laisser passer PUBLIC_ACCESS).
    }
}
```

Les classes que tu as déjà reçoivent des méthodes, **à ajouter** à la suite des autres. Ces blocs ne s'écrivent pas tels quels dans un nouveau fichier. Dans `Ticket`, à côté de ta propriété `technicien` :

```php
// à ajouter dans src/Entity/Ticket.php

    public function getTechnicien(): ?Utilisateur
    {
        // À toi : renvoyer le technicien chargé du ticket, ou null.
    }

    public function setTechnicien(?Utilisateur $technicien): self
    {
        // À toi : enregistrer le technicien et renvoyer le ticket lui-même.
    }
```

Dans `Utilisateur`, à côté de ta propriété `actif` :

```php
// à ajouter dans src/Entity/Utilisateur.php

    public function isActif(): bool
    {
        // À toi : dire si le compte est actif.
    }

    public function setActif(bool $actif): self
    {
        // À toi : enregistrer l'état du compte et renvoyer l'utilisateur lui-même.
    }
```

Dans `TicketController`, la nouvelle action de suppression :

```php
// à ajouter dans src/Controller/TicketController.php

    #[Route('/tickets/{id}/supprimer', name: 'ticket_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimer(int $id, Request $request, TicketRepository $ticketRepository, EntityManagerInterface $entityManager): Response
    {
        // À toi : retrouver le ticket (page introuvable s'il est inconnu), contrôler le droit AVANT le jeton CSRF,
        // supprimer, annoncer le résultat par un message flash et revenir à la fiche du matériel.
    }
```

N'oublie pas le `use App\Security\TicketVoter;` en haut de `TicketController`. Le dossier `src/Security/` donne l'espace de noms `App\Security` : c'est la règle PSR-4.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Le gestionnaire modifie tous les matériels (modifier et mettre au rebut).
- Un technicien ne modifie pas un matériel : 403.
- L'utilisateur à qui un matériel est affecté le consulte sans pouvoir le modifier : liens masqués, et 403 en `GET` comme en `POST`, sans rien changer.
- Le gestionnaire voit les liens de modification d'un matériel.
- Un utilisateur simple ne consulte pas un matériel qui ne lui est pas affecté : 403.
- Seul l'administrateur supprime un matériel : 403 pour le gestionnaire, suppression par le formulaire pour l'administrateur.
- La liste des matériels masque les boutons interdits.
- La règle `allow_if` réserve le résumé du parc aux techniciens, en lecture seulement.
- Un ticket peut avoir un technicien, ou non.
- Un technicien modifie et clôture ses tickets seulement : 403 sur ceux des autres et sur ceux sans technicien.
- Un appel direct en `POST` sur le ticket d'un autre est refusé avant tout changement.
- Le gestionnaire modifie tous les tickets.
- Les liens « Modifier » et « Clôturer » sont masqués pour les tickets des autres, et le bouton « Supprimer » pour un technicien.
- Seul l'administrateur supprime un ticket : 403 pour le technicien chargé et pour le gestionnaire, rien ne change avec un jeton CSRF faux, suppression par le formulaire.
- Un utilisateur simple ne crée pas de matériel : 403 en `GET` comme en `POST`, rien n'est enregistré ; le lien « Ajouter un matériel » est masqué.
- Un utilisateur simple ne crée pas de ticket sur un matériel qui ne lui est pas affecté : 403 en `GET` comme en `POST`, aucun ticket créé ; il en crée sur le sien, et un technicien sur tous.
- Les actions de gestion des fournisseurs sont réservées : 403 pour un utilisateur simple (création, modification, suppression), et seul l'administrateur supprime.
- Un compte est actif par défaut.
- Un compte désactivé qui garde `ROLE_ADMIN` est refusé (403) : c'est le test qui prouve la stratégie.
- Le même administrateur, actif, passe.
- Un compte désactivé est refusé sur toute l'application.
- La page de connexion reste ouverte à un compte désactivé.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
