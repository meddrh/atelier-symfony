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
