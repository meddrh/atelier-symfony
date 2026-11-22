## À faire

Tu sais joindre une facture à un matériel et préparer un formulaire sur mesure. À toi de faire évoluer les **tickets**, en suivant ta fiche de préparation.

1. **La capture d'écran**, sur `Ticket` et dans `TicketType` :
   - une propriété `capture` (nom du fichier rangé, `null` s'il n'y en a pas), avec `getCapture()` et `setCapture()` ;
   - un champ `capture` de type fichier, **facultatif**, **non lié à une propriété** ;
   - une contrainte `File` : 2 Mo au plus (`maxSize: '2M'`), seulement `image/png` et `image/jpeg` ;
   - le fichier est rangé avec **le service de la démonstration** (`RangeurDeFichiers`) : le nom d'origine n'est **jamais** conservé, l'extension vient du contenu, et rien n'est écrit hors du dossier `app.dossier_televersements`.
2. **Les étiquettes** : une propriété `etiquettes` (une liste, enregistrée en JSON), avec `getEtiquettes()` et `setEtiquettes()`. Dans le formulaire, un seul champ de texte `etiquettes` : `"réseau, urgent"` donne `['réseau', 'urgent']` (espaces autour des étiquettes retirés, étiquettes vides ignorées), et `[]` s'affiche comme un champ vide. Utilise un **transformateur de données**.
3. **Le statut en modification seulement** : un champ `statut` (liste de choix : ouvert, en cours, résolu) qui n'existe **pas** dans le formulaire de création et qui existe dans celui de modification. Utilise un **événement de formulaire**.
4. **La page de modification** : `/tickets/{id}/modifier` (route `ticket_modifier`), mêmes principes que pour le matériel : formulaire prérempli, 404 si le ticket est inconnu, redirection vers la fiche du matériel après un succès avec un message flash. Ajoute un lien « Modifier » dans la liste des tickets de la fiche du matériel.
5. **Le thème** : les champs du formulaire du ticket sont chacun dans un élément de classe `champ`, grâce au thème de la démonstration (`form/champ.html.twig`).
6. **La migration** : génère-la avec `make:migration`, **sans l'exécuter**, et relis le fichier créé. Une colonne de liste (comme `etiquettes`) perd, avec `ALTER TABLE … ADD COLUMN`, le commentaire de type que Doctrine lit sous SQLite : **avant** de lancer `doctrine:migrations:migrate`, recrée donc la table `ticket` dans le fichier, comme à l'étape 7 de la démonstration. Vérifie ensuite avec `doctrine:schema:update --dump-sql --complete` : il doit répondre qu'il n'y a rien à mettre à jour. Le test `MigrationsTest` de ce sujet fait la même vérification sur une base vide, et quand il est rouge il **affiche le SQL qui manque** : lance-le (`docker compose exec app bin/exercice test`) avant de chercher à l'œil. Si tu as déjà exécuté la migration, défais-la avec `doctrine:migrations:migrate prev`, corrige le fichier, puis relance `doctrine:migrations:migrate` (une migration déjà exécutée n'est pas rejouée).

Contraintes :

- le **dossier de rangement est un paramètre** (`app.dossier_televersements`), redirigé vers un dossier jetable en test : tes tests ne doivent jamais écrire dans ton vrai dossier, et les tests refusent de s'exécuter si ce n'est pas le cas ;
- le bouton qui envoie chaque formulaire s'appelle « **Enregistrer** » ;
- un fichier refusé n'est **jamais rangé**, et le formulaire se réaffiche avec le code **422** ;
- un fichier dont le contenu n'est pas une image est refusé **même si le navigateur l'annonce comme une image** ;
- un ticket sans capture reste valide ;
- tout ce que faisait ton application avant doit continuer à fonctionner : plusieurs tests le vérifient, comme la démonstration.

Un conseil sur l'ordre : commence par la capture (c'est le plus risqué), avec le service qui existe déjà, puis les étiquettes, puis le statut et la page de modification. Essaie la capture à la main avant de lancer les tests : renomme une image en `capture.php`, et un fichier texte en `capture.png`.

## Squelette des fichiers

Ce sujet ne crée aucune classe : il fait évoluer `Ticket`, `TicketType` et `TicketController`, qui existent déjà. Le squelette ne donne donc que les méthodes **à ajouter** dans ces classes ; leurs corps, les propriétés, les règles de validation, le transformateur, l'événement du formulaire, les gabarits et la migration sont à toi. Les méthodes existantes qui changent (`TicketController::nouveau()`, `TicketType::buildForm()`) n'ont pas de squelette : les modifier est le travail de l'exercice.

Dans `Ticket`, à côté de tes propriétés `capture` et `etiquettes` (à déclarer toi-même, avec leur colonne) :

```php
// à ajouter dans src/Entity/Ticket.php

    public function getCapture(): ?string
    {
        // À toi : renvoyer le nom du fichier de la capture, ou null s'il n'y en a pas.
    }

    public function setCapture(?string $capture): self
    {
        // À toi : enregistrer le nom du fichier et renvoyer le ticket lui-même.
    }

    /** @return list<string> */
    public function getEtiquettes(): array
    {
        // À toi : renvoyer la liste des étiquettes.
    }

    /** @param list<string> $etiquettes */
    public function setEtiquettes(array $etiquettes): self
    {
        // À toi : enregistrer la liste et renvoyer le ticket lui-même.
    }
```

Dans `TicketController`, la page de modification :

```php
// à ajouter dans src/Controller/TicketController.php

    #[Route('/tickets/{id}/modifier', name: 'ticket_modifier', requirements: ['id' => '\d+'])]
    public function modifier(int $id, Request $request, TicketRepository $ticketRepository, EntityManagerInterface $entityManager, RangeurDeFichiers $rangeur): Response
    {
        // À toi : retrouver le ticket (page introuvable s'il est inconnu), afficher le formulaire prérempli ; s'il est valide,
        // ranger la capture s'il y en a une, enregistrer, annoncer le succès par un message flash et rediriger vers la fiche
        // du matériel ; sinon le réafficher (code 422).
    }
```

N'oublie pas les `use` en haut du fichier : `App\Repository\TicketRepository` et `App\Service\RangeurDeFichiers`.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Le dossier de rangement est le paramètre `app.dossier_televersements`, redirigé en test.
- Le formulaire du matériel propose un champ « facture » de type fichier, et est envoyé en multipart.
- Une facture PDF est rangée sous un nom généré, jamais celui du visiteur.
- Un matériel sans facture reste valide.
- Un nom de fichier envoyé avec des `../` ne fait rien sortir du dossier de rangement.
- Une extension trompeuse (`facture.php`) n'est jamais conservée : l'extension vient du contenu.
- Un fichier qui n'est pas un PDF est refusé (422) et n'est pas rangé, même si le navigateur l'annonce comme tel.
- Une facture de plus de 2 Mo est refusée (422) et n'est pas rangée.
- Une facture de moins de 2 Mo est acceptée.
- Le numéro de série est enregistré en majuscules et sans espaces.
- Un numéro de série fait d'espaces est enregistré comme absent.
- La date de retour n'apparaît qu'en modification.
- La date de retour est enregistrée en modification.
- Le thème entoure chaque champ du matériel d'un élément de classe `champ`.
- Le formulaire du ticket propose un champ « capture » de type fichier, et est envoyé en multipart.
- Une capture PNG est rangée sous un nom généré, jamais celui du visiteur.
- Un nom de capture avec des `../` ne fait rien sortir du dossier de rangement.
- Une image nommée avec une extension trompeuse n'est jamais rangée en `.php`.
- Un fichier qui n'est pas une image est refusé (422) et n'est pas rangé, même si le navigateur l'annonce comme tel.
- Une capture de plus de 2 Mo est refusée (422) et n'est pas rangée.
- Un ticket sans capture reste valide.
- Des étiquettes séparées par des virgules deviennent une liste.
- Des étiquettes vides donnent une liste vide.
- Le formulaire de modification réécrit les étiquettes séparées par des virgules.
- Le statut n'apparaît pas à la création.
- Le statut ne peut pas être imposé à la création.
- Le statut apparaît en modification et se change.
- Modifier un ticket inconnu répond 404.
- Le thème entoure chaque champ du ticket d'un élément de classe `champ`.
- Tes migrations, jouées sur une base vide, construisent la base que décrivent tes entités (`MigrationsTest`).
