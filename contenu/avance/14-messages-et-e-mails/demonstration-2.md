## Ce que nous construisons

La première partie traitait le message **tout de suite**, dans la requête : si l'envoi de l'e-mail dure trois secondes, l'utilisateur attend trois secondes. Cette seconde partie met les messages en **file d'attente** : la requête pose le message et répond aussitôt ; un autre processus, lancé à part, le traite.

| Mot | Sens |
|---|---|
| **transport** | l'endroit où attendent les messages (ici une table de la base) |
| **routage** | la règle qui dit quel message va dans quel transport |
| **`messenger:consume`** | la commande qui tourne en continu, lit la file et appelle les gestionnaires |

---

## Étape 1 : un transport, réglé par une variable

Remplace le contenu de `config/packages/messenger.yaml` :

```yaml
framework:
    messenger:
        # Remet les services à zéro après chaque message traité par `messenger:consume` (le processus vit longtemps).
        reset_on_message: true

        transports:
            # La file d'attente « async » : doctrine://default en développement (la base de l'application), in-memory:// en test
            # (.env.test). La variable a une valeur par défaut dans config/services.yaml. Le transport Doctrine ne crée pas
            # sa table tout seul (auto_setup: false) : c'est une migration qui s'en charge.
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    auto_setup: false

        routing:
            # Ces messages ne sont pas traités tout de suite : ils partent dans la file « async », et un processus
            # `bin/console messenger:consume async` les traite à part.
            App\Message\MaterielAffecteNotification: async
            App\Message\TicketAffecte: async
```

Quelques points :

- le transport s'appelle `async` ; son adresse (le « DSN ») vient de la variable `MESSENGER_TRANSPORT_DSN` : `doctrine://default` en développement (la base de l'application), `in-memory://` en test, où rien n'est écrit nulle part (c'est déjà réglé dans `.env.test`) ;
- le `routing` envoie le message vers ce transport : **sans cette ligne, le message reste traité sur place** ;
- `auto_setup: false` : le transport ne crée pas sa table tout seul, une **migration** s'en charge (étape 3).

Donne à la variable une valeur par défaut dans `config/services.yaml`, sinon le démarrage échoue partout où elle manque :

```yaml
parameters:
    # La file d'attente des messages : valeur par défaut. .env.test la remplace par in-memory://.
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default'
```

---

## Étape 2 : les liens absolus, hors de toute requête

Le gestionnaire ne tourne plus dans une requête : il tourne dans la **console**, où rien ne dit sur quel site on est. Sans réglage, un lien absolu serait `http://localhost/…`. L'option `default_uri` du routeur donne l'adresse du site. Remplace `config/packages/routing.yaml` :

```yaml
framework:
    router:
        utf8: true

        # Le contexte des adresses hors d'une requête HTTP : le gestionnaire d'un message tourne dans la console, où rien
        # ne dit sur quel site on est. Sans cette option, un lien absolu dans un e-mail serait http://localhost/…
        default_uri: '%app.url_publique%'

when@prod:
    framework:
        router:
            strict_requirements: null
```

Et ajoute le paramètre dans `config/services.yaml` :

```yaml
parameters:
    # L'adresse publique de l'application, pour les liens absolus écrits hors d'une requête (voir config/packages/routing.yaml).
    env(URL_PUBLIQUE): 'http://localhost:8000'
    app.url_publique: '%env(URL_PUBLIQUE)%'
```

En production, on fixe la variable `URL_PUBLIQUE` à l'adresse réelle du site.

---

## Étape 3 : la table de la file d'attente

Le transport Doctrine a besoin d'une table `messenger_messages`. On la crée avec une migration, comme toute table :

```bash
docker compose exec app php bin/console doctrine:migrations:diff
```

Relis la migration générée : elle doit contenir **une seule table** (`messenger_messages`) et ses trois index. Si elle contient autre chose, tu as modifié une entité sans le vouloir. Voici celle du corrigé :

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La table de la file d'attente de Messenger (transport doctrine://default, en développement).
 *
 * Générée par `doctrine:migrations:diff` : un transport Doctrine ajoute sa table au schéma. Le transport est réglé
 * avec `auto_setup: false` pour que cette migration soit la seule à la créer. En test, le transport est en mémoire.
 */
final class Version20261122023519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'La table messenger_messages, file d\'attente du transport async';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
    }
}
```

Puis joue-la :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console doctrine:schema:validate
```

---

## Étape 4 : consommer la file

Affecte un matériel à un utilisateur depuis la page « Modifier » : la page répond tout de suite. Le message **attend** dans la table. Lance le consommateur :

```bash
docker compose exec app php bin/console messenger:consume async -vv
```

Il lit le message, appelle le gestionnaire, puis **l'acquitte** (le retire de la file). Arrête-le avec `Ctrl+C`. Deux options utiles : `--limit=1` s'arrête après un message, `--time-limit=3600` après une heure.

Si le gestionnaire lève une erreur, Symfony **réessaie** trois fois, avec un délai croissant, puis abandonne le message. En production, un gestionnaire de processus (comme `supervisor`) relance `messenger:consume` quand il s'arrête.

Deux commandes pour regarder la file :

```bash
docker compose exec app php bin/console messenger:stats
docker compose exec app php bin/console debug:messenger
```

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée la configuration du transport et du routage, le paramètre `app.url_publique`, la migration, puis essaie le cycle complet : poster, attendre, consommer. Clique sur « Terminé ».
