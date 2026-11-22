## Ce que nous construisons

Jusqu'ici, tu as **écouté** des événements que d'autres annonçaient : le noyau (`kernel.request`, `kernel.response`, `kernel.exception`) et la console (`console.terminate`). Cette partie fait l'autre moitié : **annoncer toi-même** un événement, qui n'existe nulle part dans Symfony, depuis ton propre code.

Le cas, toujours sur les matériels : quand un matériel est **mis au rebut**, le contrôleur doit en laisser une trace dans le journal de l'atelier (celui de la partie 2). Deux manières de le faire :

- le contrôleur écrit lui-même dans le journal : il connaît alors le journal, et chaque nouvelle réaction (un e-mail, une statistique) lui ajoute une ligne ;
- le contrôleur **annonce** « ce matériel vient d'être mis au rebut », et **un écouteur** écrit dans le journal. Demain, un second écouteur pourra réagir au même fait **sans que le contrôleur change**.

Tu vas écrire la seconde :

| Élément | Rôle |
|---|---|
| une **classe d'événement** | un objet ordinaire qui **porte les faits** (ici : le matériel) ; son nom, pour le répartiteur, est celui de sa classe |
| `EventDispatcherInterface::dispatch()` | **annonce** l'événement à tous ceux qui l'écoutent |
| un **écouteur** | réagit à l'annonce : ici, il écrit une ligne dans le journal |

---

## Étape 1 : la classe d'événement

Un événement maison est une **classe**, sans règle compliquée : elle reçoit les faits dans son constructeur et les rend par des méthodes. Crée `src/Event/MaterielMisAuRebutEvent.php` :

```php
<?php

namespace App\Event;

use App\Entity\Materiel;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Événement maison : un matériel vient d'être mis au rebut. Il ne porte que le fait (quel matériel : le motif se lit
 * sur lui). Celui qui l'émet (le contrôleur) ne sait pas qui l'écoute, et ceux qui l'écoutent ne savent pas qui l'émet.
 * Son nom, pour le répartiteur, est celui de sa classe : App\Event\MaterielMisAuRebutEvent.
 */
class MaterielMisAuRebutEvent extends Event
{
    public function __construct(private Materiel $materiel)
    {
    }

    public function getMateriel(): Materiel
    {
        return $this->materiel;
    }
}
```

- elle **étend** `Symfony\Contracts\EventDispatcher\Event`, une classe de base minuscule : elle permet à un écouteur de dire « les suivants n'ont pas besoin d'être prévenus » (`stopPropagation()`). Tu n'en as pas besoin ici, mais c'est l'usage ;
- les faits entrent **une seule fois**, par le constructeur, et sont **lus** par des méthodes : l'événement décrit ce qui s'est passé, personne ne le modifie ;
- on ne met dedans que ce dont les écouteurs ont besoin pour réagir **seuls**. Ici, le matériel suffit : le motif de la mise au rebut se lit sur lui ;
- son **nom** pour le répartiteur est celui de sa **classe** : `App\Event\MaterielMisAuRebutEvent`. Les événements du noyau ont gardé un nom en texte (`kernel.response`), pour des raisons historiques ; pour les tiens, la classe fait office de nom ;
- le nom est au **passé** : l'événement annonce un fait **accompli**, pas une demande.

---

## Étape 2 : un écouteur

Crée `src/EventListener/JournalDuRebutListener.php` :

```php
<?php

namespace App\EventListener;

use App\Event\MaterielMisAuRebutEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Écrit dans le journal de l'atelier une ligne chaque fois qu'un matériel est mis au rebut. */
class JournalDuRebutListener
{
    public function __construct(private LoggerInterface $journal)
    {
    }

    // Pas de « event: » ici : Symfony lit le TYPE du premier argument pour savoir quel événement est écouté.
    #[AsEventListener]
    public function surMiseAuRebut(MaterielMisAuRebutEvent $evenement): void
    {
        $materiel = $evenement->getMateriel();

        $this->journal->info('Le matériel « {nom} » (n° {id}) a été mis au rebut : {motif}.', [
            'nom' => $materiel->getNom(),
            'id' => $materiel->getId(),
            'motif' => $materiel->getMotifRebut(),
        ]);
    }
}
```

- l'attribut `#[AsEventListener]` placé **sur la méthode** la déclare comme écouteur. Cette fois, **aucun nom d'événement** n'est écrit : Symfony lit le **type** du premier argument (`MaterielMisAuRebutEvent`) et en déduit l'événement écouté. Dans la partie 1, le nom était obligatoire parce que `ResponseEvent` sert à plusieurs événements du noyau ; une classe qui n'a **qu'un** événement n'a pas ce problème ;
- le journal arrive par le constructeur, comme n'importe quel service. Il reste à lui dire **lequel** : le journal de l'atelier, `app.journal` (partie 2), pas le journal par défaut de Symfony.

Dans `config/services.yaml`, ajoute ce service **sous** `services:` (avant `App\Service\RangeurDeFichiers`) :

```yaml
    # Le journal du rebut : l'écouteur reçoit le journal de l'atelier par le nom de son argument.
    App\EventListener\JournalDuRebutListener:
        arguments:
            $journal: '@app.journal'
```

---

## Étape 3 : annoncer l'événement depuis le contrôleur

Dans `src/Controller/MaterielController.php`, ajoute deux `use` :

```php
use App\Event\MaterielMisAuRebutEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
```

(le contrôleur n'avait encore aucune raison d'annoncer quoi que ce soit : le second `use` est nouveau). Dans la méthode `rebut()`, ajoute le **répartiteur** à la liste des arguments :

```php
    public function rebut(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager, EventDispatcherInterface $repartiteur): Response
```

Puis, dans le `if ($formulaire->isSubmitted() && $formulaire->isValid())`, **juste après** `$entityManager->flush();` :

```php
            // On annonce le fait accompli : le contrôleur ne sait pas qui écoute (ici, le journal de l'atelier).
            $repartiteur->dispatch(new MaterielMisAuRebutEvent($materiel));
```

- `dispatch()` reçoit l'objet événement : le répartiteur retrouve ses écouteurs par la **classe** de l'objet, et les appelle l'un après l'autre. Il rend l'événement, dont tu n'as pas besoin ici ;
- l'annonce vient **après** `flush()` : on annonce ce qui **est arrivé**. Si l'enregistrement avait échoué, le matériel ne serait pas au rebut et personne ne devrait être prévenu ;
- elle est **dans** le `if` du formulaire valide : un motif vide est refusé, le matériel n'est pas mis au rebut, rien n'est annoncé ;
- s'il n'y avait **aucun** écouteur, `dispatch()` ne ferait rien : celui qui annonce n'a pas besoin de savoir si quelqu'un écoute.

---

## Étape 4 : les tests, puis l'essai

Crée `tests/Junior/MaterielAuRebutTest.php` :

```php
<?php

namespace App\Tests\Junior;

use App\Entity\Materiel;
use App\Event\MaterielMisAuRebutEvent;
use App\EventListener\JournalDuRebutListener;
use Psr\Log\AbstractLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MaterielAuRebutTest extends WebTestCase
{
    use AvecBaseVierge;

    public function testLEcouteurEcritUneLigneDansLeJournal(): void
    {
        // Pas de noyau : un faux journal qui garde ce qu'on lui écrit, et on annonce l'événement à la main.
        $journal = new class() extends AbstractLogger {
            /** @var list<array{0: mixed, 1: mixed, 2: array<string, mixed>}> */
            public array $lignes = [];

            public function log($level, $message, array $context = []): void
            {
                $this->lignes[] = [$level, $message, $context];
            }
        };
        $materiel = (new Materiel())->setNom('Imprimante')->setMotifRebut('Dalle fissurée');

        (new JournalDuRebutListener($journal))->surMiseAuRebut(new MaterielMisAuRebutEvent($materiel));

        self::assertCount(1, $journal->lignes);
        self::assertSame('info', $journal->lignes[0][0]);
        self::assertSame('Imprimante', $journal->lignes[0][2]['nom']);
        self::assertSame('Dalle fissurée', $journal->lignes[0][2]['motif']);
    }

    public function testLeRebutAnnonceUnEvenementQuiPorteLeMateriel(): void
    {
        $client = static::createClient();
        $entityManager = $this->creerUneBaseVierge();
        $this->seConnecter($client, $entityManager);
        $materiel = (new Materiel())->setNom('Imprimante')->setCategorie('Informatique');
        $entityManager->persist($materiel);
        $entityManager->flush();

        // Sans cette ligne, le client redémarre le noyau à chaque requête et l'écouteur ajouté ci-dessous disparaît.
        $client->disableReboot();
        $recus = [];
        static::getContainer()->get('event_dispatcher')->addListener(MaterielMisAuRebutEvent::class, static function (MaterielMisAuRebutEvent $evenement) use (&$recus): void {
            $recus[] = $evenement;
        });

        $client->request('GET', '/materiels/'.$materiel->getId().'/rebut');
        $client->submitForm('Mettre au rebut', ['mise_au_rebut[motifRebut]' => 'Dalle fissurée']);

        self::assertResponseRedirects('/materiels/'.$materiel->getId());
        self::assertCount(1, $recus, 'Un matériel mis au rebut : un événement.');
        self::assertSame('Imprimante', $recus[0]->getMateriel()->getNom());
    }

    public function testUnMotifVideNAnnonceRien(): void
    {
        $client = static::createClient();
        $entityManager = $this->creerUneBaseVierge();
        $this->seConnecter($client, $entityManager);
        $materiel = (new Materiel())->setNom('Imprimante')->setCategorie('Informatique');
        $entityManager->persist($materiel);
        $entityManager->flush();

        $client->disableReboot();
        $recus = [];
        static::getContainer()->get('event_dispatcher')->addListener(MaterielMisAuRebutEvent::class, static function (MaterielMisAuRebutEvent $evenement) use (&$recus): void {
            $recus[] = $evenement;
        });

        $client->request('GET', '/materiels/'.$materiel->getId().'/rebut');
        $client->submitForm('Mettre au rebut', ['mise_au_rebut[motifRebut]' => '  ']);

        self::assertCount(0, $recus, 'Le formulaire est refusé : le fait n\'a pas eu lieu, rien n\'est annoncé.');
    }
}
```

- le **premier test** n'a ni noyau ni base : l'écouteur est une classe ordinaire. On lui donne un faux journal (une classe anonyme qui **garde** ce qu'on lui écrit), on annonce l'événement **à la main**, et on regarde la ligne écrite. C'est l'ordre conseillé : l'écouteur d'abord, seul ;
- les **deux autres** passent par le contrôleur. Ils **ajoutent un écouteur de test** au répartiteur (`event_dispatcher`) pour **compter** les annonces et lire ce qu'elles portent : un rebut valide en annonce **une**, un motif vide **aucune** ;
- `disableReboot()` est indispensable : par défaut, le client redémarre le noyau à chaque requête, et l'écouteur ajouté juste avant disparaîtrait avec lui.

Lance-les :

```bash
docker compose exec app php bin/phpunit tests/Junior/MaterielAuRebutTest.php
```

Puis essaie dans l'application : ouvre un matériel **non affecté**, clique sur « Mettre au rebut », donne un motif, valide. Ouvre `var/log/atelier.log` : une ligne dit que le matériel a été mis au rebut, avec son motif.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée l'événement et l'écouteur, ajoute le service et l'annonce dans `rebut()`, écris le test, lance-le, puis fais l'essai dans l'application. Relance enfin `php bin/phpunit tests/Junior`, puis clique sur « Terminé ».
