## Ce que nous construisons

La première partie a composé des services **par étiquette**. Cette seconde partie montre trois autres outils du conteneur, qui servent tous à **changer ce que Symfony fabrique sans modifier la classe d'origine** :

- une **fabrique** : une méthode qui construit un service, quand `new` ne suffit pas ;
- un **décorateur** : un service qui prend la place d'un autre, lui délègue le travail et y ajoute un comportement ;
- une **passe de compilation** : du code qui relit toutes les définitions de services avant que le conteneur soit figé, pour les vérifier ou les modifier.

---

## Étape 1 : un second format, construit par une fabrique

Le format JSON a besoin d'une **option** (indenter ou non la sortie), qui dépend du mode de débogage. Symfony ne sait pas deviner une option de type `int` : une **fabrique** décide.

Crée `src/Export/JsonMaterielExport.php` :

```php
<?php

namespace App\Export;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Construit par une fabrique (App\Export\FabriqueExportJson) : voir config/services.yaml. */
#[AutoconfigureTag('app.format_export', ['format' => 'json'])]
class JsonMaterielExport implements FormatExportInterface
{
    public function __construct(private int $options)
    {
    }

    public function typeMime(): string
    {
        return 'application/json';
    }

    public function exporter(iterable $materiels): string
    {
        $lignes = [];
        foreach ($materiels as $materiel) {
            $lignes[] = ['id' => $materiel->getId(), 'nom' => $materiel->getNom(), 'categorie' => $materiel->getCategorie()];
        }

        return json_encode($lignes, $this->options | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }
}
```

Puis la fabrique, `src/Export/FabriqueExportJson.php` :

```php
<?php

namespace App\Export;

/** Une fabrique : une méthode qui construit un service. Ici, l'indentation dépend du mode de débogage. */
class FabriqueExportJson
{
    public static function creer(bool $lisible): JsonMaterielExport
    {
        return new JsonMaterielExport($lisible ? \JSON_PRETTY_PRINT : 0);
    }
}
```

Et déclare le service dans `config/services.yaml`, à la suite des services déjà décrits :

```yaml
    # Une fabrique : la méthode statique `creer()` construit le service ; l'argument est la valeur d'un paramètre du noyau.
    App\Export\JsonMaterielExport:
        factory: ['App\Export\FabriqueExportJson', 'creer']
        arguments:
            $lisible: '%kernel.debug%'
```

- `factory: [classe, méthode]` dit à Symfony : « pour obtenir ce service, appelle cette méthode statique » ;
- `'%kernel.debug%'` est un **paramètre** du noyau (vrai en développement, faux en production) : la fabrique le reçoit par son nom d'argument ;
- l'étiquette `app.format_export` de la classe fonctionne comme avant : le format `json` apparaît tout seul dans l'exporteur.

---

## Étape 2 : un décorateur qui journalise chaque export

On veut une ligne de journal à chaque export, **sans toucher** à `ExporteurDeMateriels`. Crée `src/Export/ExporteurJournalise.php` :

```php
<?php

namespace App\Export;

use Psr\Log\LoggerInterface;

/**
 * Un décorateur : il a la même interface que le service qu'il enveloppe, lui délègue le travail, et y ajoute
 * quelque chose (ici, une ligne de journal par export). Il est branché dans config/services.yaml par `decorates:`.
 */
class ExporteurJournalise implements ExporteurInterface
{
    public function __construct(
        private ExporteurInterface $interieur,
        private LoggerInterface $journal,
    ) {
    }

    public function formats(): array
    {
        return $this->interieur->formats();
    }

    public function typeMime(string $format): string
    {
        return $this->interieur->typeMime($format);
    }

    public function exporter(string $format, iterable $materiels): string
    {
        $contenu = $this->interieur->exporter($format, $materiels);
        $this->journal->info(sprintf('Export des matériels au format %s (%d octets).', $format, \strlen($contenu)));

        return $contenu;
    }
}
```

Un décorateur a toujours la même forme : il **implémente la même interface** que le service enveloppé, le reçoit dans son constructeur, lui délègue chaque appel et ajoute son comportement.

Il se branche dans `config/services.yaml`. C'est aussi là qu'on ajoute un **alias** pour l'interface :

```yaml
    # Un alias : qui demande l'interface reçoit ce service, ou son décorateur s'il y en a un (voir plus bas).
    App\Export\ExporteurInterface: '@App\Export\ExporteurDeMateriels'

    # Une décoration : ce service prend la place de l'exporteur, qui devient `.inner` pour lui.
    App\Export\ExporteurJournalise:
        decorates: App\Export\ExporteurInterface
        arguments:
            $interieur: '@.inner'
            $journal: '@app.journal'
```

- `decorates:` désigne l'identifiant du service remplacé. Celui qui demandait `ExporteurInterface` reçoit **maintenant le décorateur** ;
- `@.inner` est le service d'origine, renommé automatiquement pour le décorateur ;
- l'alias est ce qui permet de décorer **l'interface** : le contrôleur n'a rien à changer, et ne sait pas qu'un décorateur existe ;
- l'attribut `#[AsDecorator]` n'existe qu'à partir de Symfony 6.1 : en 5.4, c'est `decorates:` dans le fichier.

**Décorer ou remplacer ?** Pour changer le comportement d'un service du **framework** (le journal, le routeur…), on le décore plutôt que de le remplacer : on garde ce qu'il sait faire et on n'ajoute que la différence. Remplacer un service par son identifiant fonctionne aussi (la dernière définition gagne), mais on perd tout le comportement d'origine.

---

## Étape 3 : une passe de compilation, enregistrée dans le noyau

Une erreur dans les étiquettes (un format sans nom, deux formats identiques) ne devrait pas attendre qu'un visiteur fasse un export : elle doit empêcher l'application de **démarrer**. C'est le travail d'une passe de compilation.

Crée `src/DependencyInjection/Compiler/FormatsExportPass.php` (ce dossier est déjà exclu de la déclaration automatique des services, dans `config/services.yaml`) :

```php
<?php

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Une passe de compilation : du code qui lit et modifie les définitions de services une fois qu'elles sont toutes
 * connues, avant que le conteneur soit figé. Celle-ci refuse, au démarrage, un format sans nom ou en double.
 */
class FormatsExportPass implements CompilerPassInterface
{
    public const ETIQUETTE = 'app.format_export';

    public function process(ContainerBuilder $container): void
    {
        $formats = [];
        foreach ($container->findTaggedServiceIds(self::ETIQUETTE) as $id => $etiquettes) {
            foreach ($etiquettes as $attributs) {
                $format = $attributs['format'] ?? null;
                if (!\is_string($format) || '' === $format) {
                    throw new InvalidArgumentException(sprintf('Le service « %s » porte l\'étiquette « %s » sans attribut « format ».', $id, self::ETIQUETTE));
                }
                if (isset($formats[$format])) {
                    throw new InvalidArgumentException(sprintf('Le format « %s » est déclaré deux fois : « %s » et « %s ».', $format, $formats[$format], $id));
                }
                $formats[$format] = $id;
            }
        }
    }
}
```

Une passe est enregistrée dans le noyau, par la méthode `build()`. Modifie `src/Kernel.php` :

```php
<?php

namespace App;

use App\DependencyInjection\Compiler\FormatsExportPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /** Les passes de compilation s'enregistrent ici : elles s'exécutent quand le conteneur est construit, avant la mise en cache. */
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new FormatsExportPass());
    }
}
```

- le conteneur est **compilé une fois**, puis mis en cache : la passe ne coûte rien à chaque requête ;
- `findTaggedServiceIds()` donne, pour une étiquette, chaque service et la liste de ses attributs ;
- lever une `InvalidArgumentException` à ce moment arrête la construction du conteneur : l'erreur est vue au démarrage, avec le nom du service fautif.

---

## Étape 4 : essayer

1. Ouvre `/materiels/export/json` : la sortie est indentée (le mode de débogage est actif en développement).
2. Fais un export CSV et un export JSON, puis lis `var/log/atelier.log` : chaque export a laissé une ligne.
3. Pour voir la décoration en place :

```bash
docker compose exec app php bin/console debug:container 'App\Export\ExporteurInterface'
```

4. Casse volontairement une étiquette : change `'format' => 'json'` en `'format' => 'csv'` dans `JsonMaterielExport`, vide le cache (`docker compose exec app php bin/console cache:clear`) et lance une commande : la passe refuse le **format en double**, et dit lequel. Remets ensuite `json`.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée le format JSON et sa fabrique, le décorateur, la passe et son enregistrement dans `Kernel::build()`, et ajoute les trois blocs dans `config/services.yaml`. Fais ensuite les vérifications de l'étape 4. Clique sur « Terminé ».
