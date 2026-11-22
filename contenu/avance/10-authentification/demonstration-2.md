## Ce que nous construisons

Ton `Utilisateur` est prêt. Cette partie met en place **la porte** :

- la configuration de la sécurité : un **fournisseur d'utilisateurs**, un **pare-feu**, le hachage des mots de passe ;
- une page `/connexion` avec un formulaire protégé contre la falsification de requête (**CSRF**), et une case « Se souvenir de moi » ;
- une adresse `/deconnexion` ;
- les pages des matériels, réservées aux utilisateurs connectés.

---

## Étape 1 : la configuration

Le composant a créé `config/packages/security.yaml` à son installation. Ouvre-le et **remplace tout son contenu** par ceci :

```yaml
security:
    enable_authenticator_manager: true
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    providers:
        utilisateurs:
            entity:
                class: App\Entity\Utilisateur
                property: email
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            lazy: true
            provider: utilisateurs
            form_login:
                login_path: app_connexion
                check_path: app_connexion
                enable_csrf: true
                default_target_path: /materiels
            logout:
                path: app_deconnexion
                target: app_connexion
            remember_me:
                secret: '%kernel.secret%'
                lifetime: 604800

    # Seule la PREMIÈRE règle qui correspond s'applique.
    access_control:
        - { path: ^/(?:connexion|deconnexion), roles: PUBLIC_ACCESS }
        - { path: ^/materiels, roles: ROLE_USER }

when@test:
    security:
        password_hashers:
            # En test, un hachage rapide suffit : les mots de passe sont fictifs.
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4 # Lowest possible value for bcrypt
                time_cost: 3 # Lowest possible value for argon
                memory_cost: 10 # Lowest possible value for argon
```

Lis-le par blocs :

- `enable_authenticator_manager: true` active le **système d'authentification** moderne. En 5.4 il n'est pas activé par défaut ; il le devient en 6.0 ;
- `password_hashers` dit comment hacher : tout utilisateur qui a un mot de passe (`PasswordAuthenticatedUserInterface`) est haché avec le meilleur algorithme disponible. Le bloc `when@test` n'agit qu'en test : un hachage volontairement rapide, pour que les tests ne perdent pas de temps ;
- `providers` : le **fournisseur d'utilisateurs** dit **où retrouver** un utilisateur. Ici, dans la table de l'entité `Utilisateur`, par sa propriété `email` : c'est ce que `getUserIdentifier()` renvoie ;
- `firewalls` : un **pare-feu** décrit **comment** on s'authentifie sur une partie du site. Le pare-feu `dev` désactive la sécurité sur les outils de développement. Le pare-feu `main` couvre **tout le reste** :
  - `lazy: true` : la session n'est lue que si quelqu'un a besoin de savoir qui est connecté ;
  - `form_login` : l'authentification par formulaire. `login_path` est la page qui l'affiche, `check_path` l'adresse qui **reçoit** l'envoi (la même, ici). `enable_csrf: true` exige le jeton CSRF ;
  - `logout` : l'adresse de déconnexion, et où aller ensuite ;
  - `remember_me` : un cookie signé, valable 604 800 secondes (7 jours), qui reconnecte le visiteur quand sa session a expiré. La clé qui le signe est le secret de l'application ;
- `access_control` : la **liste des règles d'accès**, lue de haut en bas. **Seule la première règle qui correspond** s'applique. Ici : les pages de connexion et de déconnexion sont ouvertes explicitement (`PUBLIC_ACCESS`), et tout ce qui commence par `/materiels` exige `ROLE_USER`.

**Refuser par défaut, ouvrir explicitement** : une page que personne n'a pensé à lister reste ouverte à tout le monde tant qu'aucune règle ne la couvre. La bonne habitude est de terminer la liste par une règle qui exige une connexion partout (`^/`), et de n'ouvrir au public que les pages qui doivent l'être. C'est l'ordre qui compte : la règle générale va **en dernier**.

Un visiteur anonyme qui demande une page protégée est **renvoyé vers `/connexion`**. Un utilisateur connecté mais sans le rôle demandé reçoit une erreur **403** (« interdit »). Les deux cas sont différents : « je ne sais pas qui tu es » et « je sais qui tu es, et non ».

---

## Étape 2 : la page de connexion et la déconnexion

Crée `src/Controller/SecurityController.php` :

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    // Le même chemin sert à AFFICHER le formulaire (GET) et à le RECEVOIR (POST) : le pare-feu intercepte le POST.
    #[Route('/connexion', name: 'app_connexion')]
    public function connexion(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/connexion.html.twig', [
            'dernier_email' => $authenticationUtils->getLastUsername(),
            'erreur' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    // Jamais exécutée : le pare-feu intercepte cette adresse (clé « logout » de security.yaml) avant le contrôleur.
    #[Route('/deconnexion', name: 'app_deconnexion')]
    public function deconnexion(): never
    {
        throw new \LogicException("Cette méthode n'est jamais appelée : le pare-feu gère la déconnexion.");
    }
}
```

- `AuthenticationUtils` donne la **dernière erreur** d'authentification et le **dernier identifiant saisi** : le formulaire peut ainsi se réafficher avec l'e-mail déjà rempli ;
- la même route sert deux fois : un `GET` **affiche** le formulaire, un `POST` est **intercepté** par le pare-feu (`check_path`) avant d'arriver au contrôleur ;
- `deconnexion()` ne s'exécute **jamais** : le pare-feu intercepte l'adresse. La méthode n'existe que pour que la route existe.

Crée `templates/security/connexion.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}Connexion{% endblock %}

{% block body %}
    <h1>Connexion</h1>

    {% if erreur %}
        <p class="erreur">{{ erreur.messageKey|trans(erreur.messageData, 'security') }}</p>
    {% endif %}

    <form method="post">
        <p>
            <label for="adresse">Adresse e-mail</label>
            <input type="email" id="adresse" name="_username" value="{{ dernier_email }}" autocomplete="email" required autofocus>
        </p>
        <p>
            <label for="mot-de-passe">Mot de passe</label>
            <input type="password" id="mot-de-passe" name="_password" autocomplete="current-password" required>
        </p>
        <input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
        <p>
            <label><input type="checkbox" name="_remember_me"> Se souvenir de moi</label>
        </p>
        <button type="submit">Se connecter</button>
    </form>
{% endblock %}
```

Les noms des champs ne sont **pas** libres : `_username`, `_password`, `_csrf_token` et `_remember_me` sont ceux que le pare-feu lit.

- `csrf_token('authenticate')` fabrique un **jeton CSRF** : une valeur liée à la session, qu'une page malveillante ne peut pas deviner. Sans lui, un autre site pourrait faire envoyer ce formulaire à ton visiteur à son insu. Un jeton faux ou absent : la connexion est **refusée**, même avec le bon mot de passe ;
- `erreur.messageKey|trans(erreur.messageData, 'security')` affiche l'erreur dans la langue de la page ; le domaine de traduction `security` est celui des messages du composant ;
- la case « Se souvenir de moi » envoie `_remember_me` : le pare-feu pose alors le cookie `REMEMBERME`.

---

## Étape 3 : les liens du menu

Dans `templates/base.html.twig`, ajoute ce bloc à la fin de l'en-tête (juste avant `</header>`) :

```twig
{% if app.user %}
    <a href="{{ path('app_deconnexion') }}">{{ 'nav.deconnexion'|trans }}</a>
{% else %}
    <a href="{{ path('app_connexion') }}">{{ 'nav.connexion'|trans }}</a>
{% endif %}
```

`app.user` est l'utilisateur connecté, ou `null` : le menu propose « Déconnexion » à un utilisateur connecté, « Connexion » sinon. L'exercice y ajoutera le lien « Mon compte ».

Ajoute aussi deux clés dans `translations/messages+intl-icu.fr.yaml` et `translations/messages+intl-icu.en.yaml`, sous `nav`, avec les mêmes clés dans les deux langues :

```yaml
# fr
    deconnexion: Déconnexion
    connexion: Connexion
# en
    deconnexion: Log out
    connexion: Sign in
```

---

## Étape 4 : essayer

1. Ouvre `/materiels` sans être connecté : tu es **renvoyé vers `/connexion`**.
2. Saisis `alice@exemple.test` et un **mauvais** mot de passe : le formulaire se réaffiche avec une erreur.
3. Saisis le bon mot de passe (celui de la partie 1) : tu arrives sur `/materiels`.
4. Dans les outils du navigateur, onglet des cookies : repère le cookie de session. Déconnecte-toi (`/deconnexion`), reconnecte-toi **en cochant** « Se souvenir de moi » : un second cookie, `REMEMBERME`, apparaît.
5. Dans les outils du navigateur, **modifie** la valeur du champ caché `_csrf_token` avant d'envoyer le formulaire : la connexion est refusée.

---

## Étape 5 : tes tests du sujet 9 doivent se connecter

Les pages des matériels exigent maintenant une connexion : tes tests de `tests/Junior/` reçoivent une redirection au lieu de la page. Un test ne passe pas par le formulaire à chaque fois : le client de test a `loginUser()`, qui connecte un utilisateur **sans mot de passe**.

Dans `tests/Junior/AvecBaseVierge.php`, ajoute les deux `use` :

```php
use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
```

et cette méthode à la fin du trait :

```php
    /**
     * Les matériels et les tickets exigent une connexion : le test se connecte, sans passer par le formulaire,
     * avec un utilisateur créé pour lui (loginUser).
     */
    private function seConnecter(KernelBrowser $client, EntityManagerInterface $entityManager): void
    {
        $technicien = (new Utilisateur())
            ->setNom('Technicien de test')
            ->setEmail('technicien@exemple.test')
            ->setRoles(['ROLE_TECHNICIEN']);
        $entityManager->persist($technicien);
        $entityManager->flush();

        $client->loginUser($technicien);
    }
```

Dans `setUp()` de `MaterielTest` et de `TicketTest`, ajoute cette ligne après la création de la base vierge :

```php
$this->seConnecter($this->client, $this->entityManager);
```

Relance `php bin/phpunit tests/Junior` : tes tests repassent au vert.

---

## À toi

**À toi : reproduis ce code dans ton projet.** Remplace `security.yaml`, crée le contrôleur et le gabarit de connexion, ajoute les liens et les traductions, puis fais les cinq vérifications de l'étape 4. Remets tes tests du sujet 9 au vert, puis clique sur « Terminé ».
