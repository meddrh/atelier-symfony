## À faire

Tu sais composer des services par étiquette, les décorer et valider leurs étiquettes avec une passe de compilation. À toi de construire l'**export CSV de l'historique**, en suivant ta fiche de préparation. Tes tests créent leurs propres utilisateurs : rien n'est à préparer en base. Il n'y a **pas de migration** : aucune entité ne change.

1. **Les colonnes.**
   - Une interface `App\Export\ColonneHistorique` avec deux méthodes : `getEntete(): string` et `getValeur(HistoriqueAffectation $ligne): string`.
   - Cinq services qui l'implémentent, chacun étiqueté `app.colonne_historique` avec un attribut `ordre` (un nombre, par l'attribut `#[AutoconfigureTag]`). Dans l'ordre du fichier :

     | En-tête | Valeur |
     |---|---|
     | `Date` | la date du changement, au format `2026-01-02 10:00` (`Y-m-d H:i`) |
     | `Matériel` | le nom du matériel |
     | `Ancien utilisateur` | son nom, **cellule vide** s'il n'y en avait pas |
     | `Nouvel utilisateur` | son nom, cellule vide s'il n'y en a plus |
     | `Auteur` | le nom de l'auteur, cellule vide s'il est inconnu |

   - Donne des rangs **espacés** (10, 20, 30…) : on pourra insérer une colonne entre deux autres sans tout renuméroter.
2. **La passe de compilation** `App\DependencyInjection\Compiler\ColonnesHistoriquePass`, enregistrée dans `Kernel::build()` (à la suite de celle de la démonstration).
   - Elle **trie** les services de l'étiquette par `ordre`, **numériquement** (100 vient après 30), puis donne la liste triée, sous forme de références, à l'argument `$colonnes` de la définition du service `App\Export\ExportHistorique`.
   - Elle **refuse** une étiquette sans `ordre`, ou avec un `ordre` qui n'est pas un nombre, en levant une `InvalidArgumentException` (du composant d'injection de dépendances) dont le message **nomme le service fautif** et l'attribut `ordre`. L'application ne démarre alors pas.
3. **Le service d'écriture.**
   - Une interface `App\Export\ExportHistoriqueInterface` avec une méthode `ecrire($flux, iterable $lignes): void`.
   - `App\Export\ExportHistorique` l'implémente : elle reçoit les colonnes dans son constructeur (`array $colonnes`), écrit l'**en-tête**, puis une ligne par ligne d'historique. Séparateur : le **point-virgule**. Écris chaque ligne avec `fputcsv()`, **sans caractère d'échappement** (cinquième argument `''`) : un point-virgule, un guillemet, une virgule, une barre oblique inverse ou un retour à la ligne dans un titre ne doivent **jamais** abîmer la ligne.
   - **Neutralisation de l'injection de formule.** Un tableur lit comme une **formule** toute cellule qui commence par `=`, `+`, `-` ou `@` (et par une tabulation ou un retour chariot). Une telle cellule reçoit une **apostrophe en tête** : `=1+1` est écrit `'=1+1`. Une valeur sans danger reste **inchangée** (`Écran a=b` n'est pas touché : seul le premier caractère compte). Cette protection existe déjà : c'est `CelluleCsv::neutraliser()`, écrite dans la démonstration pour l'export des matériels. **Réutilise-la** pour toutes les colonnes, une seule fois, dans `ExportHistorique` : ne la recopie pas.
4. **Le décorateur du BOM.**
   - `App\Export\ExportHistoriqueAvecBom` décore `ExportHistoriqueInterface` : il écrit `"\xEF\xBB\xBF"` **une seule fois**, puis laisse le service enveloppé écrire le reste. Déclare-le dans `config/services.yaml` par `decorates:` (n'oublie pas l'**alias** de l'interface vers `ExportHistorique`).
   - Le BOM n'est pas écrit par le contrôleur, ni par `ExportHistorique` : c'est le décorateur.
5. **Le dépôt et le contrôleur.**
   - Une méthode de dépôt `findTous()` donne tout l'historique, **du plus ancien au plus récent** (à date égale, la ligne la plus ancienne **écrite** d'abord), avec le matériel et les utilisateurs chargés en une seule requête.
   - Une route `/historique/export.csv` (nom `historique_export`, `GET` seulement) renvoie une `StreamedResponse` : `Content-Type: text/csv; charset=UTF-8` et un nom de fichier **daté du jour** dans `Content-Disposition` (`attachment; filename=historique-2026-11-22.csv`).
   - Elle est réservée à `ROLE_GESTIONNAIRE` : une règle dans `access_control` **et** un contrôle dans le contrôleur. Sans connexion : redirection vers la page de connexion ; technicien ou utilisateur simple : 403.
   - Un lien « Exporter tout l'historique (CSV) » sur la page d'historique d'un matériel, visible des seuls gestionnaires.

Contraintes :

- la liste des colonnes est **dans les services étiquetés**, jamais écrite dans le contrôleur ni dans `ExportHistorique` : ajouter une sixième colonne ne doit demander **qu'une classe** ;
- l'ordre des colonnes vient de la passe : tes tests vérifient aussi ta passe **sur un conteneur minimal**, et un noyau qui contient une étiquette sans `ordre` doit **refuser de démarrer** ;
- un conseil : lance aussi tes tests des démonstrations (les formats, la fabrique, le décorateur du journal, la passe des formats) et ceux des sujets précédents (`tests/Junior` compris), pour voir tout de suite si un changement les casse ;
- le sujet 11 reste en place : seul un gestionnaire (ou un administrateur) exporte.

Deux conseils :

- **Écris d'abord la passe**, avec son test sur un conteneur minimal : c'est elle qui fait échouer le démarrage, et c'est la plus facile à vérifier seule. Une passe de compilation s'essaie sans noyau : `$conteneur = new ContainerBuilder(); $conteneur->register('colonne_a', \stdClass::class)->addTag('app.colonne_historique', ['ordre' => 10]); $conteneur->addCompilerPass(new ColonnesHistoriquePass()); $conteneur->compile();`.
- **Une réponse en flux n'est pas lue comme les autres dans un test.** Elle est envoyée dès l'événement « réponse » : le client de test ne la garde pas. Pour lire le fichier, enveloppe l'appel : `ob_start(); $client->request('GET', '/historique/export.csv'); $corps = ob_get_clean();`.

## Squelette des fichiers

Ce sujet crée sept classes et deux interfaces : les contrats des colonnes et de l'export, un exemple de colonne, le service d'écriture, son décorateur, la passe de compilation et le contrôleur. Il ajoute aussi une méthode à `HistoriqueAffectationRepository`. Le squelette ne te donne pas la solution : le tri des colonnes, la vérification de leur rang, l'écriture du CSV, la neutralisation des formules et le flux de la réponse restent à écrire. Il n'y a rien pour `config/services.yaml` (l'alias et `decorates:`), pour l'appel de ta passe dans `Kernel::build()` (le noyau existe déjà, seule la méthode `build()` change de corps) ni pour le gabarit de l'historique : ce ne sont pas des classes PHP à créer.

Les deux contrats, tels que la consigne les décrit :

```php
<?php
// src/Export/ColonneHistorique.php

namespace App\Export;

use App\Entity\HistoriqueAffectation;

interface ColonneHistorique
{
    public function getEntete(): string;

    public function getValeur(HistoriqueAffectation $ligne): string;
}
```

```php
<?php
// src/Export/ExportHistoriqueInterface.php

namespace App\Export;

interface ExportHistoriqueInterface
{
    public function ecrire($flux, iterable $lignes): void;
}
```

Une colonne, la première, pour montrer la forme : l'attribut `#[AutoconfigureTag]` étiquette le service avec son rang. Les quatre autres colonnes sont à toi, sur le même modèle : leurs noms de classe sont libres (les tests ne les nomment pas), leurs rangs sont espacés comme la consigne le demande.

```php
<?php
// src/Export/ColonneDate.php

namespace App\Export;

use App\Entity\HistoriqueAffectation;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.colonne_historique', ['ordre' => 10])]
class ColonneDate implements ColonneHistorique
{
    public function getEntete(): string
    {
        // À toi : l'en-tête de la colonne.
    }

    public function getValeur(HistoriqueAffectation $ligne): string
    {
        // À toi : la valeur de la colonne pour cette ligne, au format de la consigne.
    }
}
```

Le service d'écriture. Son constructeur reçoit la liste des colonnes, comme la consigne le dit :

```php
<?php
// src/Export/ExportHistorique.php

namespace App\Export;

class ExportHistorique implements ExportHistoriqueInterface
{
    public function __construct(array $colonnes = [])
    {
        // À toi : garder les colonnes que la passe de compilation donnera.
    }

    public function ecrire($flux, iterable $lignes): void
    {
        // À toi : écrire l'en-tête, puis une ligne par ligne d'historique, comme la consigne le demande.
    }
}
```

Le décorateur du BOM. Il implémente la même interface que le service qu'il enveloppe, et son constructeur reçoit ce service enveloppé (c'est ce que `decorates:` lui fournira).

```php
<?php
// src/Export/ExportHistoriqueAvecBom.php

namespace App\Export;

class ExportHistoriqueAvecBom implements ExportHistoriqueInterface
{
    public function __construct(ExportHistoriqueInterface $interieur)
    {
        // À toi : garder le service enveloppé.
    }

    public function ecrire($flux, iterable $lignes): void
    {
        // À toi : écrire le BOM une seule fois, puis laisser le service enveloppé écrire le reste.
    }
}
```

La passe de compilation. `CompilerPassInterface` impose `process()`, avec cette signature :

```php
<?php
// src/DependencyInjection/Compiler/ColonnesHistoriquePass.php

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ColonnesHistoriquePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // À toi : trier les services étiquetés, refuser une étiquette sans rang numérique, donner la liste à l'export.
    }
}
```

Le contrôleur de l'export :

```php
<?php
// src/Controller/HistoriqueController.php

namespace App\Controller;

use App\Export\ExportHistoriqueInterface;
use App\Repository\HistoriqueAffectationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HistoriqueController extends AbstractController
{
    #[Route('/historique/export.csv', name: 'historique_export', methods: ['GET'])]
    public function export(ExportHistoriqueInterface $export, HistoriqueAffectationRepository $historiqueRepository): Response
    {
        // À toi : contrôler le droit, puis répondre par un flux CSV avec les bons en-têtes.
    }
}
```

`HistoriqueAffectationRepository` existe déjà : la méthode ci-dessous est à **ajouter** dans ta classe, à la suite des autres. Ce bloc ne s'écrit pas tel quel dans un nouveau fichier.

```php
// à ajouter dans src/Repository/HistoriqueAffectationRepository.php

    /**
     * @return iterable<HistoriqueAffectation>
     */
    public function findTous(): iterable
    {
        // À toi : tout l'historique, du plus ancien au plus récent, avec ce qu'il faut charger en une seule requête.
    }
```

Chaque fichier se range selon PSR-4 : `src/Export/` donne `App\Export`, `src/DependencyInjection/Compiler/` donne `App\DependencyInjection\Compiler`, et le nom du fichier donne le nom de la classe.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- L'export des matériels en CSV : un type CSV, une ligne d'en-tête et une ligne par matériel.
- L'export des matériels en JSON est indenté en développement (la fabrique règle l'option selon le mode de débogage).
- Un format inconnu donne 404, et l'export des matériels est réservé aux techniciens (403 sinon).
- Le décorateur journalise chaque export, avec son format.
- La passe des formats refuse un format en double et un format sans nom.
- L'export de l'historique est réservé au gestionnaire : redirection sans connexion, 403 pour un utilisateur simple et pour un technicien, succès pour le gestionnaire.
- Les en-têtes : `text/csv; charset=UTF-8`, un téléchargement au nom daté du jour, une réponse en flux.
- Le corps commence par le BOM, **une seule fois**.
- Le BOM vient du décorateur : le service obtenu par l'interface l'écrit, et `services.yaml` déclare deux décorations.
- L'en-tête contient les cinq colonnes dans l'ordre ; sans historique, il est seul.
- Une ligne par changement, du plus ancien au plus récent, avec les cellules vides pour « personne » et l'auteur inconnu.
- Une cellule qui commence par `=`, `+`, `-`, `@`, une tabulation ou un retour chariot est neutralisée (titre du matériel, ancien utilisateur, nouvel utilisateur, auteur) : six cas.
- Dans l'export des matériels aussi : le nom et la catégorie qui commencent par l'un de ces six caractères sont neutralisés.
- Une valeur sans danger reste telle quelle.
- Un point-virgule, des guillemets, un retour à la ligne, une virgule et une barre oblique inverse dans un titre n'abîment pas la ligne.
- La passe trie les colonnes par rang numérique (100 après 30).
- La passe refuse une étiquette sans `ordre`, et un `ordre` qui n'est pas un nombre.
- Le conteneur de l'application donne les colonnes à `ExportHistorique` dans l'ordre des rangs.
- Un noyau qui contient une colonne sans `ordre` refuse de démarrer.
