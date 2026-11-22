## Ce que nous construisons

Quand un matériel est affecté à quelqu'un, on veut le **prévenir par e-mail**. Écrire cet envoi dans le contrôleur serait une erreur : envoyer un e-mail est **lent** (un serveur distant répond), il peut **échouer** (le serveur est en panne), et ce n'est pas le travail du contrôleur. On fait donc deux choses :

- le contrôleur (ou, ici, un abonné à l'événement du sujet 12) **poste un message** : une petite fiche qui dit « il faut prévenir cet utilisateur de ce matériel » ;
- un **gestionnaire de message** (`message handler`) lit la fiche et envoie l'e-mail.

C'est le composant **Messenger** : un **bus** reçoit des messages et les remet au bon gestionnaire. Cette première partie fait marcher l'ensemble **sur place** (le gestionnaire est appelé tout de suite) ; la seconde partie met les messages **en file d'attente**, pour les traiter plus tard et ailleurs.

| Élément | Rôle |
|---|---|
| le **message** | une classe qui décrit ce qui s'est passé, sans aucune logique |
| le **gestionnaire** | une classe marquée `#[AsMessageHandler]`, dont la méthode `__invoke()` reçoit le message |
| le **bus** (`MessageBusInterface`) | celui à qui on donne le message : `dispatch()` |

---

## Étape 1 : le message

Crée `src/Message/MaterielAffecteNotification.php` :

```php
<?php

namespace App\Message;

/**
 * « Un matériel vient d'être affecté à cet utilisateur : il faut le prévenir. »
 *
 * Un message ne porte que des identifiants, jamais des entités : il attend dans une file, il est écrit puis relu, et
 * l'entité aurait changé (ou disparu) quand on le traitera. Le gestionnaire recharge ce dont il a besoin.
 */
final class MaterielAffecteNotification
{
    public function __construct(
        private int $materielId,
        private int $utilisateurId,
    ) {
    }

    public function getMaterielId(): int
    {
        return $this->materielId;
    }

    public function getUtilisateurId(): int
    {
        return $this->utilisateurId;
    }
}
```

Une règle d'or : **un message ne porte que des identifiants, jamais des entités**. Dans la partie suivante, il sera écrit dans une file puis relu, parfois longtemps après : une entité entière serait copiée, et périmée (voire supprimée) au moment du traitement. Le gestionnaire recharge ce dont il a besoin, tel que c'est **à ce moment-là**.

---

## Étape 2 : le gestionnaire, qui envoie l'e-mail

Crée `src/MessageHandler/MaterielAffecteNotificationHandler.php` :

```php
<?php

namespace App\MessageHandler;

use App\Message\MaterielAffecteNotification;
use App\Repository\MaterielRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Envoie l'e-mail « un matériel vous a été affecté », quand le message est sorti de la file. */
#[AsMessageHandler]
class MaterielAffecteNotificationHandler
{
    public function __construct(
        private MaterielRepository $materielRepository,
        private UtilisateurRepository $utilisateurRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        // La valeur vient de `bind` dans config/services.yaml : l'argument s'appelle $expediteur, et Symfony ne sait pas deviner un texte.
        private string $expediteur,
    ) {
    }

    public function __invoke(MaterielAffecteNotification $message): void
    {
        $materiel = $this->materielRepository->find($message->getMaterielId());
        $utilisateur = $this->utilisateurRepository->find($message->getUtilisateurId());

        // Le message a attendu : le matériel ou l'utilisateur a pu être supprimé, ou le matériel confié à un autre.
        // Dans ces cas il n'y a plus rien à dire : on s'arrête sans erreur (réessayer ne changerait rien).
        if (null === $materiel || null === $utilisateur || $materiel->getUtilisateur()?->getId() !== $utilisateur->getId()) {
            return;
        }

        $courriel = (new TemplatedEmail())
            ->from(new Address($this->expediteur, 'Parc informatique'))
            ->to(new Address($utilisateur->getEmail(), $utilisateur->getNom()))
            ->subject('Matériel affecté : '.$materiel->getNom())
            ->textTemplate('email/materiel_affecte.txt.twig')
            ->htmlTemplate('email/materiel_affecte.html.twig')
            ->context([
                'materiel' => $materiel,
                'utilisateur' => $utilisateur,
                // Un lien ABSOLU : un e-mail est lu hors du site, une adresse relative n'y mène nulle part.
                'lien' => $this->urlGenerator->generate('materiel_fiche', ['id' => $materiel->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailer->send($courriel);
    }
}
```

Observe :

- `#[AsMessageHandler]` suffit : Symfony repère la classe (`autoconfigure`) et la relie au message **par le type de l'argument de `__invoke()`** ;
- l'e-mail est un `TemplatedEmail` : son corps vient de **modèles Twig** (un texte brut, un HTML), comme une page ;
- `from()` utilise un **paramètre**, et pas une adresse écrite en dur : l'expéditeur change selon l'environnement. Symfony ne sait pas deviner une valeur de type texte, on la désigne dans `config/services.yaml` (étape 4) ;
- le lien est généré avec `UrlGeneratorInterface::ABSOLUTE_URL` : un e-mail se lit **hors du site**, une adresse relative (`/materiels/3`) n'y mène nulle part ;
- si le matériel ou l'utilisateur a disparu entre-temps, le gestionnaire s'arrête **sans erreur** : réessayer ne changerait rien.

---

## Étape 3 : les modèles de l'e-mail

Crée `templates/email/materiel_affecte.txt.twig` :

```twig
Bonjour {{ utilisateur.nom }},

Le matériel « {{ materiel.nom }} » ({{ materiel.categorie }}) vous a été affecté.

Sa fiche : {{ lien }}

Parc informatique
```

Et `templates/email/materiel_affecte.html.twig` :

```twig
<p>Bonjour {{ utilisateur.nom }},</p>

<p>Le matériel « {{ materiel.nom }} » ({{ materiel.categorie }}) vous a été affecté.</p>

<p><a href="{{ lien }}">Voir sa fiche</a></p>

<p>Parc informatique</p>
```

Le texte brut sert aux messageries sans HTML, et aux tests : c'est lui que `assertEmailTextBodyContains()` lit.

---

## Étape 4 : l'expéditeur, par un paramètre

Dans `config/services.yaml`, ajoute le paramètre, et dis à Symfony quelle valeur donner à tout argument nommé `$expediteur` :

```yaml
parameters:
    # L'expéditeur des e-mails de l'application.
    app.expediteur: 'parc@exemple.test'

services:
    _defaults:
        autowire: true
        autoconfigure: true
        # Tout service dont un argument s'appelle $expediteur reçoit ce paramètre : les gestionnaires de messages (sujet 14).
        bind:
            $expediteur: '%app.expediteur%'
```

(`parameters:` et `_defaults:` existent déjà dans ton fichier : tu ajoutes seulement les lignes `app.expediteur` et `bind`.)

---

## Étape 5 : poster le message depuis l'événement du sujet 12

Au sujet 12, le contrôleur annonce `MaterielAffecteEvent` chaque fois qu'une affectation change, **sans savoir qui écoute**. On s'y abonne une seconde fois : le contrôleur ne change pas. Crée `src/EventSubscriber/NotificationAffectationSubscriber.php` :

```php
<?php

namespace App\EventSubscriber;

use App\Event\MaterielAffecteEvent;
use App\Message\MaterielAffecteNotification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Le sujet 12 a créé l'événement « un matériel a changé d'affectation » : on s'y abonne une seconde fois, sans toucher
 * au contrôleur. L'abonné de l'historique écrit une ligne ; celui-ci poste un message pour prévenir le nouvel utilisateur.
 */
class NotificationAffectationSubscriber implements EventSubscriberInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [MaterielAffecteEvent::class => 'notifier'];
    }

    public function notifier(MaterielAffecteEvent $evenement): void
    {
        $nouvel = $evenement->getNouvelUtilisateur();
        // Un matériel qui retourne « à personne » n'a personne à prévenir.
        if (null === $nouvel) {
            return;
        }

        $this->bus->dispatch(new MaterielAffecteNotification($evenement->getMateriel()->getId(), $nouvel->getId()));
    }
}
```

Un matériel qui retourne « à personne » n'a **personne à prévenir** : le message n'est pas posté.

---

## Étape 6 : essayer

1. Liste les gestionnaires que Symfony a trouvés :

```bash
docker compose exec app php bin/console debug:messenger
```

2. Sans configuration de transport, un message est traité **immédiatement**, dans la requête. Affecte un matériel à un utilisateur depuis la page « Modifier » : l'e-mail est « envoyé » vers `null://null` (le serveur de test qui jette tout), tu ne le vois donc pas. La preuve viendra des tests de l'exercice.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée le message, le gestionnaire, les deux modèles, le paramètre avec son `bind`, et l'abonné. Lance `debug:messenger`, puis clique sur « Terminé ».
