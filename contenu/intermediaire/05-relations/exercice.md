## À faire

Tu sais relier un matériel à un utilisateur. À toi de gérer les **tickets d'incident**, en suivant ta fiche de préparation.

1. **L'entité `Ticket`** (classe `App\Entity\Ticket`, avec son dépôt et sa migration) :
   - `titre` : texte obligatoire, 150 caractères au plus ;
   - `description` : texte long, obligatoire ;
   - `statut` : `ouvert`, `en_cours` ou `resolu`. Un ticket neuf est `ouvert`. Toute autre valeur est refusée par le validateur ;
   - `creeLe` : la date de création, un `\DateTimeImmutable`, fixée à l'instant présent à la création ;
   - `materiel` : une relation **plusieurs-à-un** vers `Materiel`, **obligatoire**.
2. **Les méthodes d'accès** : `getId()`, `getTitre()`/`setTitre()`, `getDescription()`/`setDescription()`, `getStatut()`/`setStatut()`, `getCreeLe()`/`setCreeLe()`, `getMateriel()`/`setMateriel()`. Les tests les appellent ; les méthodes `set…` renvoient l'objet lui-même, comme celles de `Materiel`.
3. **Le côté inverse** : `Materiel::getTickets()` renvoie les tickets du matériel, **du plus récent au plus ancien**.
4. **Une page de création** `/materiels/{id}/tickets/nouveau` (route `ticket_nouveau`), avec un formulaire `TicketType` (champs `titre` et `description`) :
   - un matériel inconnu donne une page introuvable (404) ;
   - le ticket créé est lié au matériel de l'adresse ;
   - un formulaire invalide se réaffiche avec le code **422** ;
   - un formulaire valide **redirige** (302) vers la fiche du matériel avec un **message flash**.
5. **La fiche du matériel** : un lien « Signaler un incident » vers cette page, et la liste des tickets, **chacun dans un élément de classe `ticket`**. Sans ticket, la fiche affiche « Aucun ticket pour ce matériel. ».
6. **La suppression d'un matériel** qui a des tickets est **refusée** : rien n'est supprimé, le visiteur est redirigé vers `/materiels` avec un message d'erreur dans un élément de classe `flash`. Un matériel sans ticket se supprime toujours.

Contraintes :

- le bouton qui envoie le formulaire s'appelle « **Enregistrer** » ;
- les règles de validation sont **sur l'entité**, pas dans le formulaire ;
- la migration : cette fois, `make:migration` fonctionne (la table `ticket` est nouvelle). Relis le fichier généré avant de l'exécuter ;
- tout ce que faisait ton application avant doit continuer à fonctionner : plusieurs tests le vérifient, comme la démonstration.

Un conseil sur l'ordre : commence par l'entité et la migration, vérifie la table avec `doctrine:query:sql`, puis écris la page de création, puis seulement l'affichage et la suppression.

## Squelette des fichiers

Ce sujet crée quatre classes (l'entité `Ticket`, son dépôt, son type de formulaire et son contrôleur) et ajoute une méthode à `Materiel`. Le squelette ne te donne pas la solution : les colonnes, les règles de validation, la relation, le traitement du formulaire et les messages restent à écrire. Les gabarits et la migration ne sont pas dans ce squelette : ce ne sont pas des classes PHP.

L'entité ne montre que son attribut de classe et son identifiant. Tu ajoutes toi-même les colonnes (`titre`, `description`, `statut`, `creeLe`, `materiel`), leurs règles de validation et les méthodes d'accès, à la main ou avec `php bin/console make:entity`.

```php
<?php
// src/Entity/Ticket.php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // À toi : les colonnes, leurs règles de validation, puis les méthodes d'accès.
}
```

```php
<?php
// src/Repository/TicketRepository.php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // À toi : appeler le constructeur du parent, en lui donnant le gestionnaire et la classe de l'entité.
    }
}
```

```php
<?php
// src/Form/TicketType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // À toi : ajouter les champs titre et description.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // À toi : dire à quelle classe ce formulaire est lié.
    }
}
```

```php
<?php
// src/Controller/TicketController.php

namespace App\Controller;

use App\Repository\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TicketController extends AbstractController
{
    #[Route('/materiels/{id}/tickets/nouveau', name: 'ticket_nouveau', requirements: ['id' => '\d+'])]
    public function nouveau(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager): Response
    {
        // À toi : retrouver le matériel (page introuvable s'il est inconnu), afficher le formulaire de création d'un ticket lié
        // à ce matériel ; s'il est valide, enregistrer, annoncer le succès par un message flash et rediriger vers la fiche
        // du matériel ; sinon le réafficher (code 422).
    }
}
```

Enfin, `Materiel` existe déjà : la méthode ci-dessous est à **ajouter** dans ta classe, avec la propriété qui porte la relation (le côté inverse, que tu déclares toi-même) et les `use` qu'il faut, dont `Doctrine\Common\Collections\Collection`.

```php
// à ajouter dans src/Entity/Materiel.php

    /** @return Collection<int, Ticket> */
    public function getTickets(): Collection
    {
        // À toi : renvoyer les tickets de ce matériel, du plus récent au plus ancien.
    }
```

Chaque fichier se range selon PSR-4 : `src/Entity/` donne `App\Entity`, `src/Form/` donne `App\Form`, et ainsi de suite.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Un utilisateur est enregistré avec son nom et son adresse e-mail.
- Deux utilisateurs ne peuvent pas avoir la même adresse e-mail (violation de validation).
- Un matériel est affecté à un utilisateur, et le côté inverse (`getMateriels()`) le voit.
- Le dépôt liste les matériels affectés à un utilisateur, triés par nom.
- La page d'un utilisateur liste ses matériels, et seulement les siens.
- La page d'un utilisateur se trie par catégorie sur demande (`/utilisateurs/{id}/categorie`).
- Un ordre de tri inconnu donne une page introuvable (404).
- Le dépôt refuse un ordre de tri inconnu par une exception.
- Un utilisateur inconnu donne une page introuvable (404).
- Le formulaire du matériel propose les utilisateurs.
- Un matériel est affecté depuis le formulaire.
- Un matériel peut rester sans utilisateur.
- Un ticket est lié à un matériel et commence au statut « ouvert ».
- Un statut inconnu est invalide.
- Le formulaire de création d'un ticket s'affiche, avec ses champs `ticket[titre]` et `ticket[description]`.
- Le formulaire d'un matériel inconnu répond 404.
- Un ticket valide est créé, lié au matériel, daté de maintenant, et le visiteur est redirigé vers la fiche (302).
- Après cette redirection, un message flash est affiché.
- Un ticket sans titre est refusé avec le code 422, et rien n'est enregistré.
- Un ticket sans description est refusé avec le code 422, et rien n'est enregistré.
- La fiche du matériel liste ses tickets du plus récent au plus ancien.
- La fiche ne liste pas les tickets d'un autre matériel.
- La fiche d'un matériel sans ticket le dit.
- Un matériel qui a des tickets n'est pas supprimé : redirection, message, matériel et ticket toujours en base.
- Un matériel sans ticket est toujours supprimé.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
