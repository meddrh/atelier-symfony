## À faire

Tu sais ajouter, modifier et supprimer un matériel depuis le navigateur. À toi de faire la même **gestion complète** pour les **fournisseurs**, en suivant ta fiche de préparation.

1. **Les règles de validation**, sur l'entité `Fournisseur` :
   - le nom est obligatoire ;
   - l'adresse e-mail est obligatoire et doit **être une adresse e-mail valide** ;
   - l'adresse e-mail doit être **unique** : une adresse déjà utilisée par un autre fournisseur est refusée avec un message lisible, et non par une erreur technique de la base ;
   - le téléphone reste facultatif.
2. **Le type de formulaire** `App\Form\FournisseurType`, avec les champs `nom`, `email` et `telephone`.
3. **Trois nouvelles pages** dans `FournisseurController` :
   - `/fournisseurs/nouveau` : le formulaire d'ajout ;
   - `/fournisseurs/{id}/modifier` : le formulaire de modification, prérempli ;
   - `/fournisseurs/{id}/supprimer` : la suppression, qui n'accepte que la méthode `POST` et exige un **jeton CSRF** valide.
4. **Les gabarits** : les deux formulaires, des liens « Ajouter » et « Modifier » dans la liste, et dans la liste un formulaire avec un bouton « Supprimer » pour chaque fournisseur.

Contraintes :

- le bouton qui envoie chaque formulaire s'appelle « **Enregistrer** » ;
- un formulaire invalide se réaffiche avec le code **422** ;
- un formulaire valide **redirige** (code 302) et affiche un **message flash**, dans un élément de classe `flash` ;
- l'adresse e-mail d'un fournisseur qu'on modifie ne doit pas être refusée comme « déjà prise » **par lui-même** ;
- un fournisseur inconnu donne une page introuvable (404) ;
- tes pages du matériel doivent continuer à fonctionner : plusieurs tests les vérifient, comme la démonstration.

Un conseil sur l'ordre : commence par les règles de validation, puis le type de formulaire, puis une seule page (l'ajout) que tu essaies dans le navigateur. Les deux autres ressemblent beaucoup à celle-là.

## Squelette des fichiers

Ce sujet crée une classe, le type de formulaire, et ajoute trois méthodes à `FournisseurController`. Le squelette ne te donne pas la solution : les champs du formulaire, le traitement de la requête, l'enregistrement et les messages restent à écrire. Les règles de validation de l'entité et les gabarits ne sont pas dans ce squelette (ce ne sont pas des méthodes à ajouter, ou pas des classes PHP) : tu les écris toi-même.

Le type de formulaire, dans son propre fichier :

```php
<?php
// src/Form/FournisseurType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FournisseurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // À toi : ajouter les champs nom, email et telephone.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // À toi : dire à quelle classe ce formulaire est lié.
    }
}
```

Les trois pages sont des méthodes **à ajouter** dans ton `FournisseurController`, à la suite de celles que tu as déjà. Ce bloc ne s'écrit pas tel quel dans un nouveau fichier :

```php
// à ajouter dans src/Controller/FournisseurController.php

    #[Route('/fournisseurs/nouveau', name: 'fournisseur_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $entityManager): Response
    {
        // À toi : afficher le formulaire d'ajout ; s'il est valide, enregistrer le fournisseur,
        // annoncer le succès par un message flash et rediriger ; sinon le réafficher (code 422).
    }

    #[Route('/fournisseurs/{id}/modifier', name: 'fournisseur_modifier', requirements: ['id' => '\d+'])]
    public function modifier(int $id, Request $request, FournisseurRepository $fournisseurRepository, EntityManagerInterface $entityManager): Response
    {
        // À toi : la même chose pour un fournisseur existant, formulaire prérempli ; page introuvable si l'identifiant est inconnu.
    }

    #[Route('/fournisseurs/{id}/supprimer', name: 'fournisseur_supprimer', requirements: ['id' => '\d+'])]
    public function supprimer(int $id, Request $request, FournisseurRepository $fournisseurRepository, EntityManagerInterface $entityManager): Response
    {
        // À toi : n'accepter que la méthode POST, vérifier le jeton CSRF, supprimer, annoncer le résultat
        // par un message flash et revenir à la liste ; page introuvable si l'identifiant est inconnu.
    }
```

N'oublie pas les `use` en haut du fichier : `App\Entity\Fournisseur`, `App\Form\FournisseurType`, `Doctrine\ORM\EntityManagerInterface` et `Symfony\Component\HttpFoundation\Request`. Le dossier `src/Form/` donne l'espace de noms `App\Form` : c'est la règle PSR-4.

## Pour lancer les tests

```bash
docker compose exec app bin/exercice test
```

Le badge se débloque quand **tous** les tests sont verts. Lis le message du premier test rouge : il dit en général ce qui manque.

## Tests attendus

- Le formulaire d'ajout d'un matériel s'affiche, avec ses champs `materiel[nom]` et `materiel[categorie]`.
- Un matériel valide est enregistré et le visiteur est redirigé (302).
- Après cette redirection, un message flash est affiché.
- Un matériel sans nom est refusé avec le code 422, et rien n'est enregistré.
- Un matériel peut être modifié.
- Le bouton « Supprimer » de la liste des matériels supprime le matériel.
- Une suppression de matériel envoyée sans jeton ne supprime rien.
- Le formulaire d'ajout d'un fournisseur s'affiche, avec ses champs `fournisseur[nom]`, `fournisseur[email]` et `fournisseur[telephone]`.
- Un fournisseur valide est enregistré et le visiteur est redirigé (302).
- Après cette redirection, un message flash est affiché.
- Le téléphone reste facultatif.
- Une adresse e-mail invalide est refusée avec le code 422, et rien n'est enregistré.
- Un nom vide est refusé avec le code 422, et rien n'est enregistré.
- Une adresse e-mail déjà prise par un autre fournisseur est refusée avec le code 422.
- Le formulaire de modification est prérempli avec le nom et l'adresse e-mail du fournisseur.
- Un fournisseur peut être modifié en gardant son adresse e-mail.
- Un fournisseur ne peut pas prendre l'adresse e-mail d'un autre (422, adresse inchangée).
- Modifier un fournisseur inconnu répond 404.
- Le bouton « Supprimer » de la liste des fournisseurs supprime le fournisseur et redirige.
- Une suppression envoyée sans jeton ne supprime rien.
- Une suppression envoyée avec un faux jeton ne supprime rien.
- Une suppression par simple lien (méthode `GET`) répond 405 et ne supprime rien.
