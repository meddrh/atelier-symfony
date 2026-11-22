## Ce que nous construisons

Un matériel a une **facture**. Nous allons permettre de la **joindre** au matériel sous forme de fichier PDF, depuis le formulaire. C'est la première fois qu'un visiteur nous envoie **un fichier** : et un fichier, c'est tout ce qu'il envoie qui n'est pas fiable, à commencer par son **nom**, son **extension** et même ce qu'il **prétend être**.

Dans cette partie : le dossier de rangement, un petit service qui range le fichier, le champ du formulaire avec ses vérifications, et la migration. La partie 2 ajoute d'autres champs au même formulaire.

---

## Étape 1 : ce qu'il ne faut jamais croire

Quand un navigateur envoie un fichier, il annonce :

- un **nom** (`facture-juin.pdf`) : le visiteur peut l'écrire comme il veut, y compris `../../x.php` pour tenter de sortir du dossier prévu ;
- une **extension** : rien ne l'empêche d'envoyer un programme PHP nommé `facture.pdf`, ou une vraie facture nommée `facture.php` ;
- un **type** (« c'est un PDF ») : c'est lui qui le dit, nous n'avons aucune raison de le croire ;
- une **taille**.

Les règles de ce sujet découlent de cette méfiance :

1. le fichier est rangé dans **un dossier prévu**, hors du dossier public `public/` : le serveur web ne le sert jamais tel quel ;
2. il est rangé sous un **nom que nous générons**, jamais celui du visiteur ;
3. son extension vient de son **contenu réel**, pas de son nom ;
4. on vérifie son **type réel** (lu dans le contenu) et sa **taille** avant de le garder.

---

## Étape 2 : le dossier de rangement est un paramètre

Un **paramètre** est une valeur nommée, déclarée une fois, que les services peuvent recevoir. Le chemin du dossier n'a pas à être écrit en dur dans le code : on le déclare dans `config/services.yaml`, et on peut le changer sans toucher une ligne de PHP. C'est aussi ce qui permet aux tests d'utiliser **un autre dossier**, jetable.

Voici le fichier **complet** : remplace tout son contenu par ce texte. Il contient, tout en haut de `parameters:`, deux valeurs par défaut (`env(MAILER_DSN)` et `env(LOCK_DSN)`) que **ton fichier a déjà** : elles ne servent pas encore, mais des sujets plus loin en auront besoin. Ne les supprime pas en recopiant.

```yaml
# This file is the entry point to configure your own services.
# Files in the packages/ subdirectory configure your dependencies.

# Put parameters here that don't need to change on each machine where the app is deployed
# https://symfony.com/doc/current/best_practices.html#use-parameters-for-application-configuration
parameters:
    # Servira au sujet 14 (envoi d'e-mails) : valeur par défaut (aucun envoi), sinon le démarrage échouerait sans la variable. .env.test fixe la même valeur. Ne la supprime pas.
    env(MAILER_DSN): 'null://null'
    # Servira au sujet 15 (verrous) : un fichier du système, valeur par défaut pour la même raison. .env.test fixe la même valeur. Ne la supprime pas.
    env(LOCK_DSN): 'flock'
    # Dossier où sont rangés les fichiers envoyés par les visiteurs. Hors de public/ : le serveur ne les sert jamais tels quels.
    app.dossier_televersements: '%kernel.project_dir%/var/televersements'

services:
    # default configuration for services in *this* file
    _defaults:
        autowire: true      # Automatically injects dependencies in your services.
        autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.

    # makes classes in src/ available to be used as services
    # this creates a service per class whose id is the fully-qualified class name
    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'
            # La plateforme a son propre noyau (src/Formation/FormationKernel.php) : ne pas la déclarer ici.
            - '../src/Formation/'

    # add more service definitions when explicit configuration is needed
    # please note that last definitions always *replace* previous ones

    App\Service\RangeurDeFichiers:
        arguments:
            $dossier: '%app.dossier_televersements%'

when@test:
    parameters:
        # En test, les fichiers vont dans le cache du noyau : un dossier jetable, jamais les vrais fichiers.
        app.dossier_televersements: '%kernel.cache_dir%/televersements'
```

- les deux lignes `env(...)` du haut sont déjà là et **servent plus tard** (e-mails au sujet 14, verrous au sujet 15) : une valeur par défaut évite que l'application refuse de démarrer quand la variable d'environnement correspondante n'existe pas. Tu n'as rien à en faire pour l'instant ;
- `parameters:` déclare aussi `app.dossier_televersements`. `%kernel.project_dir%` est un paramètre prédéfini : la racine du projet. Le dossier est sous `var/`, que le serveur web ne publie pas.
- Sous `services:`, `App\Service\RangeurDeFichiers` reçoit ce paramètre dans son argument `$dossier` : on écrit le paramètre entre `%…%`.
- `when@test:` redéfinit le paramètre **seulement** dans l'environnement de test : les fichiers vont alors dans le cache du noyau, un endroit qu'on peut vider sans regret. Sans cela, tes tests écriraient (et effaceraient !) dans ton vrai dossier.

---

## Étape 3 : le service qui range le fichier

Crée `src/Service/RangeurDeFichiers.php` :

```php
<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Range un fichier envoyé par un visiteur dans le dossier prévu, sous un nom que nous générons.
 *
 * Rien de ce que le visiteur a écrit (nom du fichier, extension) n'est utilisé : le nom est aléatoire,
 * l'extension vient du CONTENU réel du fichier. Un nom comme « ../../x.php » ne sort donc jamais du dossier.
 */
class RangeurDeFichiers
{
    public function __construct(private readonly string $dossier)
    {
    }

    /** Renvoie le nom sous lequel le fichier est rangé (à enregistrer sur l'entité). */
    public function ranger(UploadedFile $fichier): string
    {
        $extension = $fichier->guessExtension() ?? 'bin';
        $nom = bin2hex(random_bytes(16)).'.'.$extension;

        $fichier->move($this->dossier, $nom);

        return $nom;
    }
}
```

Lis `ranger()` dans l'ordre :

- `guessExtension()` **devine l'extension à partir du contenu** du fichier (c'est le composant `symfony/mime`, installé pour ce sujet). Un PDF donne `pdf`, même s'il s'appelle `facture.php`. Si rien n'est reconnu, on garde `bin`.
- `bin2hex(random_bytes(16))` fabrique un nom de 32 caractères hexadécimaux, imprévisible. Il ne contient que des lettres et des chiffres : ni `/`, ni `..`.
- `move($this->dossier, $nom)` déplace le fichier envoyé vers le dossier, sous ce nom. Symfony crée le dossier s'il n'existe pas.
- la méthode **renvoie le nom** : c'est lui qu'on gardera en base, pas le fichier lui-même.

Le service est enregistré dans `services.yaml` (étape 2), et le contrôleur le recevra par **câblage automatique** : il suffit de l'écrire dans les arguments de la méthode.

---

## Étape 4 : l'entité

Le matériel retient le **nom généré** de sa facture. Ajoute ces trois propriétés dans `src/Entity/Materiel.php`, sous `$categorie` (la partie 2 utilisera les deux autres ; les ajouter maintenant évite d'écrire deux migrations) :

```php
#[ORM\Column(length: 40, nullable: true)]
#[Assert\Length(max: 40, maxMessage: 'Le numéro de série ne doit pas dépasser {{ limit }} caractères.')]
private ?string $numeroSerie = null;

// Nom généré du fichier de la facture, rangé par RangeurDeFichiers (jamais le nom donné par le visiteur).
#[ORM\Column(length: 255, nullable: true)]
private ?string $facture = null;

#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $dateRetour = null;
```

Et leurs méthodes d'accès, à la fin de la classe :

```php
public function getNumeroSerie(): ?string
{
    return $this->numeroSerie;
}

public function setNumeroSerie(?string $numeroSerie): self
{
    $this->numeroSerie = $numeroSerie;

    return $this;
}

public function getFacture(): ?string
{
    return $this->facture;
}

public function setFacture(?string $facture): self
{
    $this->facture = $facture;

    return $this;
}

public function getDateRetour(): ?\DateTimeImmutable
{
    return $this->dateRetour;
}

public function setDateRetour(?\DateTimeImmutable $dateRetour): self
{
    $this->dateRetour = $dateRetour;

    return $this;
}
```

Aucune règle de validation sur `$facture` : ce n'est pas le visiteur qui la remplit. Il choisit un **fichier** ; c'est nous qui calculons le nom.

---

## Étape 5 : le champ du formulaire

Dans `src/Form/MaterielType.php`, ajoute ces deux lignes `use` :

```php
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
```

Puis le champ, à la suite des autres :

```php
->add('facture', FileType::class, [
    'label' => 'Facture (PDF, 2 Mo au plus)',
    // Le champ ne correspond à aucune propriété : le contrôleur range le fichier et enregistre son nom.
    'mapped' => false,
    'required' => false,
    'constraints' => [
        new File(
            maxSize: '2M',
            mimeTypes: ['application/pdf'],
            maxSizeMessage: 'La facture ne doit pas dépasser {{ limit }} {{ suffix }}.',
            mimeTypesMessage: 'La facture doit être un fichier PDF.',
        ),
    ],
])
```

- `FileType` dessine un `<input type="file">` ; Symfony ajoute tout seul `enctype="multipart/form-data"` au formulaire, sans lequel le navigateur n'enverrait pas le fichier.
- `'mapped' => false` : ce champ ne correspond à **aucune propriété** de `Materiel` (la propriété `facture` contient un **nom**, pas un fichier). Le formulaire garde le fichier de côté, et le contrôleur le récupère.
- `new File(…)` est une **contrainte de validation**. `maxSize: '2M'` refuse les fichiers de plus de 2 000 000 d'octets (le `M` compte en millions ; `'2Mi'` compterait en 1 048 576). `mimeTypes` ne garde que le type **réellement détecté** dans le contenu, ce qui demande `symfony/mime`.
- Les deux messages remplacent ceux par défaut, en anglais ou trop techniques.

---

## Étape 6 : le contrôleur

Le contrôleur reçoit le service, le fichier, et enregistre le nom. Voici `src/Controller/MaterielController.php` en entier (les nouveautés : `RangeurDeFichiers`, `UploadedFile`, `FormInterface` et la méthode `rangerLaFacture()`) :

```php
<?php

namespace App\Controller;

use App\Entity\Materiel;
use App\Form\MaterielType;
use App\Repository\MaterielRepository;
use App\Service\RangeurDeFichiers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MaterielController extends AbstractController
{
    #[Route('/materiels', name: 'materiel_liste')]
    public function liste(MaterielRepository $materielRepository): Response
    {
        return $this->render('materiel/liste.html.twig', [
            'materiels' => $materielRepository->findAll(),
        ]);
    }

    #[Route('/materiels/nouveau', name: 'materiel_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request, EntityManagerInterface $entityManager, RangeurDeFichiers $rangeur): Response
    {
        $materiel = new Materiel();
        $formulaire = $this->createForm(MaterielType::class, $materiel);
        $formulaire->handleRequest($request);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $this->rangerLaFacture($formulaire, $materiel, $rangeur);
            $entityManager->persist($materiel);
            $entityManager->flush();

            $this->addFlash('success', 'Le matériel a été ajouté.');

            return $this->redirectToRoute('materiel_fiche', ['id' => $materiel->getId()]);
        }

        return $this->renderForm('materiel/nouveau.html.twig', [
            'formulaire' => $formulaire,
        ]);
    }

    #[Route('/materiels/{id}', name: 'materiel_fiche', requirements: ['id' => '\d+'])]
    public function fiche(int $id, MaterielRepository $materielRepository): Response
    {
        $materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');

        return $this->render('materiel/fiche.html.twig', [
            'materiel' => $materiel,
        ]);
    }

    #[Route('/materiels/{id}/modifier', name: 'materiel_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function modifier(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager, RangeurDeFichiers $rangeur): Response
    {
        $materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');

        $formulaire = $this->createForm(MaterielType::class, $materiel);
        $formulaire->handleRequest($request);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $this->rangerLaFacture($formulaire, $materiel, $rangeur);
            // L'objet vient de la base : Doctrine le suit déjà, pas besoin de persist().
            $entityManager->flush();

            $this->addFlash('success', 'Le matériel a été modifié.');

            return $this->redirectToRoute('materiel_fiche', ['id' => $materiel->getId()]);
        }

        return $this->renderForm('materiel/modifier.html.twig', [
            'materiel' => $materiel,
            'formulaire' => $formulaire,
        ]);
    }

    #[Route('/materiels/{id}/supprimer', name: 'materiel_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimer(int $id, Request $request, MaterielRepository $materielRepository, EntityManagerInterface $entityManager): Response
    {
        $materiel = $materielRepository->find($id) ?? throw $this->createNotFoundException('Matériel introuvable.');

        if (!$this->isCsrfTokenValid('supprimer_materiel'.$materiel->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La suppression a été refusée : le jeton de sécurité est invalide.');

            return $this->redirectToRoute('materiel_liste');
        }

        if (!$materiel->getTickets()->isEmpty()) {
            $this->addFlash('error', 'Ce matériel a des tickets : il ne peut pas être supprimé.');

            return $this->redirectToRoute('materiel_liste');
        }

        $entityManager->remove($materiel);
        $entityManager->flush();

        $this->addFlash('success', 'Le matériel a été supprimé.');

        return $this->redirectToRoute('materiel_liste');
    }

    /** Range le fichier de la facture, s'il y en a un, et garde seulement son nom généré sur le matériel. */
    private function rangerLaFacture(FormInterface $formulaire, Materiel $materiel, RangeurDeFichiers $rangeur): void
    {
        $facture = $formulaire->get('facture')->getData();
        if ($facture instanceof UploadedFile) {
            $materiel->setFacture($rangeur->ranger($facture));
        }
    }
}
```

`$formulaire->get('facture')->getData()` renvoie un objet `UploadedFile` si un fichier a été envoyé, `null` sinon. On range le fichier **seulement après** que `isValid()` a répondu oui : un fichier refusé n'est jamais rangé.

---

## Étape 7 : la migration

Trois nouvelles colonnes : une migration. L'**ordre** des commandes compte ici, parce que `make:migration` ne produit pas la bonne migration sous SQLite.

`ALTER TABLE … ADD COLUMN` ne conserve pas le commentaire `(DC2Type:datetime_immutable)` dont Doctrine se sert pour reconnaître le type de la colonne de date : après un `migrate` sur la migration générée, `doctrine:schema:update --dump-sql --complete` dirait encore qu'il y a des choses à faire. La solution propre est de **recréer la table** dans la migration, comme pour la clé étrangère du sujet précédent. Et une migration déjà exécutée n'est **jamais rejouée** : il faut donc la corriger **avant** de lancer `migrate`.

1. Génère la migration, **sans l'exécuter** :

```bash
docker compose exec app php bin/console make:migration
```

2. Ouvre le fichier créé dans `migrations/` (son nom contient la date et l'heure de ta génération : il diffère de celui du code ci-dessous) et **remplace tout son contenu** par la migration qui suit. Garde le nom de classe de **ton** fichier : `Version…` doit toujours porter le même nom que le fichier.

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Numéro de série, facture et date de retour d'un matériel.
 *
 * Sous SQLite, « ALTER TABLE … ADD COLUMN » ne conserve pas le commentaire « (DC2Type:…) » que Doctrine
 * lit pour connaître le type exact d'une colonne : la colonne de date serait ensuite jugée « différente »
 * à chaque comparaison. On recrée donc la table, comme Doctrine le fait pour une clé étrangère.
 */
final class Version20261121160631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Numéro de série, facture et date de retour d'un matériel";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__materiel AS SELECT id, utilisateur_id, nom, categorie FROM materiel');
        $this->addSql('DROP TABLE materiel');
        $this->addSql("CREATE TABLE materiel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, utilisateur_id INTEGER DEFAULT NULL, nom VARCHAR(120) NOT NULL, categorie VARCHAR(60) NOT NULL, numero_serie VARCHAR(40) DEFAULT NULL, facture VARCHAR(255) DEFAULT NULL, date_retour DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)\n, CONSTRAINT FK_18D2B091FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE)");
        $this->addSql('INSERT INTO materiel (id, utilisateur_id, nom, categorie) SELECT id, utilisateur_id, nom, categorie FROM __temp__materiel');
        $this->addSql('DROP TABLE __temp__materiel');
        $this->addSql('CREATE INDEX IDX_18D2B091FB88E14F ON materiel (utilisateur_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__materiel AS SELECT id, utilisateur_id, nom, categorie FROM materiel');
        $this->addSql('DROP TABLE materiel');
        $this->addSql('CREATE TABLE materiel (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, utilisateur_id INTEGER DEFAULT NULL, nom VARCHAR(120) NOT NULL, categorie VARCHAR(60) NOT NULL, CONSTRAINT FK_18D2B091FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO materiel (id, utilisateur_id, nom, categorie) SELECT id, utilisateur_id, nom, categorie FROM __temp__materiel');
        $this->addSql('DROP TABLE __temp__materiel');
        $this->addSql('CREATE INDEX IDX_18D2B091FB88E14F ON materiel (utilisateur_id)');
    }
}
```

3. Exécute-la, puis vérifie que la base et les entités sont d'accord :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console doctrine:schema:update --dump-sql --complete
```

La seconde commande doit répondre qu'il n'y a **rien à mettre à jour**.

Si tu as lancé `migrate` **avant** de remplacer le contenu du fichier : défais d'abord la migration avec `docker compose exec app php bin/console doctrine:migrations:migrate prev`, remplace le contenu du fichier, puis relance `doctrine:migrations:migrate`.

---

## Étape 8 : essayer, et essayer de tricher

1. Ouvre `/materiels/nouveau`, remplis le nom et la catégorie, choisis un vrai PDF et enregistre. La fiche affiche « Facture : jointe ».
2. Regarde le dossier : `ls var/televersements`. Tu y trouves un fichier au nom illisible, du genre `3f9c…e1.pdf`. Le nom de ton fichier d'origine n'apparaît **nulle part**.
3. Renomme ton PDF en `facture.php` et envoie-le : il est accepté (son contenu est bien un PDF), mais il est rangé sous un nom en `.pdf`. L'extension que tu as choisie est ignorée.
4. Crée un fichier texte qui contient `<?php echo 'bonjour';`, nomme-le `facture.pdf` et envoie-le : le formulaire le refuse (« La facture doit être un fichier PDF. »). Son nom dit « PDF », son contenu dit le contraire : **c'est le contenu qui décide**.
5. Même un nom comme `../../x.php` ne change rien : le nom du visiteur n'est jamais utilisé pour écrire un fichier.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Déclare le paramètre et le service, crée `RangeurDeFichiers`, ajoute les trois propriétés et leurs méthodes, le champ `facture`, la méthode du contrôleur et la migration. Fais les cinq essais de l'étape 8, puis clique sur « Terminé ».
