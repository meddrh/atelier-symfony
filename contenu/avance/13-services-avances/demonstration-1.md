## Ce que nous construisons

Jusqu'ici, tes services sont **câblés** : un contrôleur demande une classe, Symfony la lui donne. Ce sujet montre comment **composer** des services sans que celui qui les utilise les connaisse un par un.

Cette première partie ajoute un **export des matériels** qui accepte plusieurs formats. L'idée :

- chaque format est une petite classe qui implémente une interface commune ;
- chaque classe porte une **étiquette** (`app.format_export`), comme une pastille de couleur collée sur un service ;
- un service « exporteur » demande **tous les services qui portent cette étiquette**, sans en nommer aucun. Ajouter un format demain, c'est ajouter **une classe** : rien d'autre ne change.

Trois outils font ce travail :

| Outil | Rôle |
|---|---|
| `#[AutoconfigureTag]` | colle l'étiquette sur la classe (et on peut y joindre des **attributs**, ici le nom du format) |
| `#[TaggedIterator]` | demande, dans un constructeur, la liste des services d'une étiquette |
| `indexAttribute` | range cette liste par la valeur d'un attribut de l'étiquette (ici `format`) |

---

## Étape 1 : l'interface, la neutralisation et le format CSV

Crée `src/Export/FormatExportInterface.php` :

```php
<?php

namespace App\Export;

use App\Entity\Materiel;

/**
 * Un format d'export des matériels. Chaque classe qui l'implémente porte l'étiquette `app.format_export`, avec le
 * nom de son format (`format`), grâce à l'attribut #[AutoconfigureTag] : aucune ligne de configuration à écrire.
 */
interface FormatExportInterface
{
    /** Le type de contenu de la réponse HTTP (par exemple « text/csv »). */
    public function typeMime(): string;

    /** @param iterable<Materiel> $materiels */
    public function exporter(iterable $materiels): string;
}
```

Un fichier CSV s'ouvre dans un tableur, et le nom d'un matériel est saisi par un visiteur. Si ce nom commence par `=`, le tableur le lit comme une **formule** et l'exécute : c'est l'**injection de formule**. Une petite classe, `src/Export/CelluleCsv.php`, met une apostrophe en tête de toute valeur qui commence comme une formule. Elle est écrite **une seule fois** : l'export des matériels l'utilise ici, et celui de l'historique l'utilisera dans l'exercice.

```php
<?php

namespace App\Export;

/**
 * Prépare le texte d'une cellule CSV pour un tableur. Un seul endroit : tous les exports passent par ici,
 * un export de plus ne peut donc pas oublier la protection.
 */
final class CelluleCsv
{
    /** Les caractères qu'un tableur lit comme le début d'une formule (la liste de l'OWASP : =, +, -, @, tabulation, retour chariot). */
    private const DEBUTS_DE_FORMULE = "=+-@\t\r";

    /** Une cellule qui commence comme une formule reçoit une apostrophe en tête : le tableur l'affiche comme du texte. */
    public static function neutraliser(string $valeur): string
    {
        if ('' !== $valeur && str_contains(self::DEBUTS_DE_FORMULE, $valeur[0])) {
            return "'".$valeur;
        }

        return $valeur;
    }
}
```

Puis `src/Export/CsvMaterielExport.php`, qui l'applique à chaque cellule de texte (le numéro n'est pas du texte saisi, on n'y touche pas) :

```php
<?php

namespace App\Export;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.format_export', ['format' => 'csv'])]
class CsvMaterielExport implements FormatExportInterface
{
    public function typeMime(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function exporter(iterable $materiels): string
    {
        $flux = fopen('php://temp', 'w+');
        fputcsv($flux, ['id', 'nom', 'categorie'], ';', '"', '');
        foreach ($materiels as $materiel) {
            // Le nom et la catégorie sont saisis par des visiteurs : chaque cellule de texte passe par la neutralisation.
            fputcsv($flux, [
                $materiel->getId(),
                CelluleCsv::neutraliser((string) $materiel->getNom()),
                CelluleCsv::neutraliser((string) $materiel->getCategorie()),
            ], ';', '"', '');
        }
        rewind($flux);
        $csv = (string) stream_get_contents($flux);
        fclose($flux);

        return $csv;
    }
}
```

Observe :

- `#[AutoconfigureTag('app.format_export', ['format' => 'csv'])]` : le premier argument est le **nom de l'étiquette**, le second la liste de ses **attributs**. Grâce à `autoconfigure: true` (déjà dans `config/services.yaml`), aucune ligne de configuration n'est nécessaire ;
- `fputcsv()` écrit une ligne en CSV **correctement** : elle met entre guillemets ce qui contient le séparateur et double les guillemets. Écrire `implode(';', …)` à la main est une faute classique. Le dernier argument vide (`''`) supprime le caractère d'échappement, source de surprises ;
- le séparateur est le point-virgule, que les tableurs français attendent ;
- `CelluleCsv::neutraliser()` ne touche que le **premier** caractère : « Écran a=b » reste intact, « =1+1 » devient « '=1+1 ». `fputcsv()` règle le problème du **format** (séparateur, guillemets), la neutralisation celui de la **formule** : ce sont deux protections différentes, il faut les deux.

---

## Étape 2 : l'exporteur, qui réunit les formats

Crée trois petites classes. D'abord l'exception et l'interface de l'exporteur :

```php
<?php

namespace App\Export;

class FormatInconnu extends \InvalidArgumentException
{
}
```

```php
<?php

namespace App\Export;

interface ExporteurInterface
{
    /** @return list<string> les formats connus, par exemple ['csv', 'json'] */
    public function formats(): array;

    /** @throws FormatInconnu */
    public function typeMime(string $format): string;

    /**
     * @param iterable<\App\Entity\Materiel> $materiels
     *
     * @throws FormatInconnu
     */
    public function exporter(string $format, iterable $materiels): string;
}
```

Puis l'exporteur lui-même, `src/Export/ExporteurDeMateriels.php` :

```php
<?php

namespace App\Export;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/** Réunit tous les services étiquetés `app.format_export`, rangés par le nom de leur format. */
class ExporteurDeMateriels implements ExporteurInterface
{
    /** @param iterable<string, FormatExportInterface> $formats */
    public function __construct(
        #[TaggedIterator('app.format_export', indexAttribute: 'format')]
        private iterable $formats,
    ) {
    }

    public function formats(): array
    {
        return array_keys(iterator_to_array($this->formats));
    }

    public function typeMime(string $format): string
    {
        return $this->trouver($format)->typeMime();
    }

    public function exporter(string $format, iterable $materiels): string
    {
        return $this->trouver($format)->exporter($materiels);
    }

    private function trouver(string $format): FormatExportInterface
    {
        foreach ($this->formats as $nom => $service) {
            if ($nom === $format) {
                return $service;
            }
        }

        throw new FormatInconnu(sprintf('Le format « %s » n\'existe pas.', $format));
    }
}
```

Le point important est le constructeur :

- `#[TaggedIterator('app.format_export', indexAttribute: 'format')]` demande **tous** les services étiquetés, rangés **par leur attribut `format`** : la clé de chaque service est `'csv'`, puis `'json'` plus tard ;
- la valeur reçue est un **itérable paresseux** : un service n'est construit que quand on le parcourt ;
- l'exporteur ne connaît **aucun** format par son nom de classe. C'est ce qui rend l'ajout d'un format possible sans le toucher.

---

## Étape 3 : le contrôleur et le lien

Crée `src/Controller/ExportController.php` :

```php
<?php

namespace App\Controller;

use App\Export\ExporteurInterface;
use App\Export\FormatInconnu;
use App\Repository\MaterielRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExportController extends AbstractController
{
    #[Route('/materiels/export/{format}', name: 'materiel_export', requirements: ['format' => '[a-z]+'], methods: ['GET'])]
    public function materiels(string $format, ExporteurInterface $exporteur, MaterielRepository $materielRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TECHNICIEN');

        try {
            $contenu = $exporteur->exporter($format, $materielRepository->findBy([], ['nom' => 'ASC']));
            $type = $exporteur->typeMime($format);
        } catch (FormatInconnu $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }

        return new Response($contenu, 200, ['Content-Type' => $type]);
    }
}
```

- le contrôleur demande **l'interface** `ExporteurInterface`, pas la classe : on verra pourquoi à l'étape suivante ;
- un format inconnu devient une **404** : l'exception métier est transformée en réponse HTTP à la frontière ;
- le contrôle des droits est dans le contrôleur (technicien au moins), comme au sujet 11.

Dans `templates/materiel/liste.html.twig`, après le lien « Ajouter un matériel » (et avant le bloc `nav` des filtres), ajoute :

```twig
{% if is_granted('ROLE_TECHNICIEN') %}
    <p>
        Exporter :
        <a href="{{ path('materiel_export', {format: 'csv'}) }}">CSV</a>
        <a href="{{ path('materiel_export', {format: 'json'}) }}">JSON</a>
    </p>
{% endif %}
```

---

## Étape 4 : essayer

1. Connecte-toi en gestionnaire et ouvre `/materiels/export/csv` : un fichier CSV est téléchargé, avec une ligne par matériel.
2. Ouvre `/materiels/export/xml` : **404**. Le lien « JSON » ne marche pas encore : il arrive dans la partie suivante.
3. Pour voir les services étiquetés, sans lire de fichier :

```bash
docker compose exec app php bin/console debug:container --tag=app.format_export
```

---

## À toi

**À toi : reproduis ce code dans ton projet.** Crée l'interface, la classe `CelluleCsv`, le format CSV, l'exception, l'exporteur, le contrôleur et le lien. Fais ensuite les vérifications de l'étape 4. Clique sur « Terminé ».
