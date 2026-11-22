# Installer l'Atelier Symfony

Ce guide t'accompagne de zéro jusqu'à ta première séance. Compte 20 à 30 minutes la première fois,
surtout pour le téléchargement de l'image Docker.

Tu n'installes ni PHP, ni Composer, ni Symfony sur ton ordinateur : tout tourne dans un conteneur Docker.
Il te faut seulement **git** et **Docker**.

## 1. Installer les outils

### Windows

1. Installe **WSL 2** (le sous-système Linux de Windows) : ouvre PowerShell en administrateur et lance
   `wsl --install`, puis redémarre. Une distribution Ubuntu est installée par défaut.
2. Installe **Docker Desktop** (<https://www.docker.com/products/docker-desktop/>). Dans ses réglages,
   vérifie que « Use the WSL 2 based engine » est coché, et que l'intégration avec ta distribution Ubuntu
   est activée (Settings → Resources → WSL integration).
3. Ouvre le terminal **Ubuntu** (pas PowerShell) : toutes les commandes de ce guide se tapent là.
   git y est déjà installé (`git --version` pour vérifier ; sinon `sudo apt install git`).

> Range le projet **dans Linux** (par exemple `~/atelier`), jamais dans `/mnt/c/…` : depuis le disque
> Windows, les tests sont plusieurs fois plus lents et peuvent dépasser le délai de `bin/exercice`.

### macOS

1. Installe **Docker Desktop** (<https://www.docker.com/products/docker-desktop/>), puis lance-le une fois.
2. git : tape `git --version` dans le Terminal ; s'il manque, macOS propose d'installer les outils en ligne
   de commande.

### Linux

1. Installe **Docker Engine** et le greffon **Compose** en suivant la documentation de ta distribution
   (<https://docs.docker.com/engine/install/>), puis autorise ton compte à utiliser Docker sans `sudo` :
   `sudo usermod -aG docker $USER`, et ferme puis rouvre ta session.
2. Installe git (`sudo apt install git` sur Debian ou Ubuntu).

### Vérifier

```sh
git --version
docker --version
docker compose version        # « Docker Compose version v2… » : c'est bon
docker run --rm hello-world   # « Hello from Docker! » : Docker fonctionne
```

## 2. Récupérer le projet

Mohammed te transmet soit l'adresse d'un dépôt privé, soit un fichier `livraison.bundle`.

```sh
cd ~
# avec un dépôt privé :
git clone --branch livraison <adresse du dépôt> atelier
# ou avec le fichier reçu, copié d'abord dans ton dossier personnel
# (sous Windows, depuis le terminal Ubuntu : cp /mnt/c/Users/<ton nom Windows>/Downloads/livraison.bundle ~/) :
git clone --branch livraison ~/livraison.bundle atelier

cd atelier
git switch -c travail
```

Tu codes toujours sur ta branche **`travail`**, jamais sur `livraison` : c'est ce qui te permettra de recevoir
les mises à jour sans perdre ton travail.

Dis aussi à git qui tu es, si ce n'est pas déjà fait :

```sh
git config --global user.name "Ton Nom"
git config --global user.email "ton.adresse@exemple.fr"
```

## 3. Linux et Windows (WSL) : ton identifiant

Le conteneur crée les fichiers avec ton identifiant Linux, pour qu'ils t'appartiennent. Vérifie-le :

```sh
id -u    # identifiant de ton compte
id -g    # identifiant de ton groupe
```

S'ils valent tous les deux `1000`, passe à l'étape suivante. Sinon, avant toute construction :

```sh
echo 'export APP_UID=$(id -u) APP_GID=$(id -g)' >> ~/.bashrc
source ~/.bashrc
```

Sous Windows, fais cette vérification dans le terminal Ubuntu : le premier compte créé dans WSL vaut
`1000` en général, mais vérifie-le quand même. Sous macOS, rien à faire.

## 4. Construire et démarrer

Depuis le dossier `atelier` :

```sh
docker compose build                          # construit l'image (quelques minutes la première fois)
docker compose run --rm app composer install  # installe les bibliothèques PHP dans vendor/
docker compose up -d                          # démarre le serveur en arrière-plan
```

Ouvre <http://localhost:8000/formation> : la page « Séance du jour » s'affiche. L'installation est faite.

## 5. Vérifier que tout marche

```sh
docker compose exec app bin/exercice test
```

Sur une installation neuve, tu dois voir :

```
Pas d'exercice en cours. Ta séance du jour : « … ».
```

C'est **le signe que tout fonctionne** : la plateforme répond, et ta première séance est une lecture
(un rappel), pas encore un exercice. Les tests se lanceront quand tu arriveras au premier exercice (ils seront
rouges tant que tu ne l'auras pas fait : c'est normal). Si tu vois une erreur à la place, va à « En cas de problème ».

## Au quotidien

| Pour… | Commande |
|---|---|
| démarrer le serveur (après un redémarrage de l'ordinateur) | `docker compose up -d` |
| arrêter le serveur | `docker compose down` |
| lancer les tests de l'exercice en cours | `docker compose exec app bin/exercice test` |
| lancer une commande Symfony | `docker compose exec app php bin/console <commande>` |
| ouvrir un terminal dans le conteneur | `docker compose exec app bash` |
| voir les messages du serveur | `docker compose logs -f app` |

Sous Windows et macOS, Docker Desktop doit être lancé pour que ces commandes marchent.

**Ouvrir le projet dans ton éditeur sous Windows** : installe VS Code et son extension « WSL », puis, dans le
terminal Ubuntu, depuis `~/atelier`, tape `code .`. VS Code ouvre le projet rangé dans Linux ; n'ouvre pas le
dossier par l'explorateur Windows.

Pour le déroulé d'une séance et la réception des mises à jour, lis le [README](README.md).

## En cas de problème

| Ce que tu vois | Ce qu'il faut faire |
|---|---|
| `Cannot connect to the Docker daemon` | Docker n'est pas lancé : ouvre Docker Desktop (Windows, macOS) ou `sudo systemctl start docker` (Linux). |
| `permission denied` en lançant `docker` (Linux) | Ton compte n'est pas dans le groupe `docker` : `sudo usermod -aG docker $USER`, puis ferme et rouvre ta session. |
| `port is already allocated` ou `address already in use` sur 8000 | Un autre programme utilise le port 8000 : arrête-le, ou arrête un ancien conteneur avec `docker compose down`. |
| `Permission denied` sur `var/` ou `vendor/` (Linux) | Les fichiers appartiennent à un autre compte : vérifie l'étape 3, puis suis « Fichiers appartenant à root » dans le README. |
| `groupadd` ou `useradd` échoue pendant `docker compose build` | Préviens Mohammed avec le message complet. |
| `composer install` échoue sur le réseau | Vérifie ta connexion, puis relance la même commande : elle reprend où elle s'était arrêtée. |
| `bin/exercice test` affiche « Délai dépassé » | Le projet est sans doute sur le disque Windows (`/mnt/c/…`) : déplace-le dans Linux (étape 1). |
| `/bin/sh^M` ou `bad interpreter` | Fins de ligne Windows : reclone le projet **depuis le terminal Ubuntu**, pas depuis Windows. |
| La page `/formation` affiche une erreur | Ce n'est pas ton code : préviens Mohammed. |

Si rien de tout cela ne correspond, copie le message d'erreur **en entier** et envoie-le à Mohammed.

## Repartir de zéro

- Recommencer le parcours depuis le début : supprime `var/formation.sqlite`. Ton code reste en place.
- Tout supprimer : `docker compose down --rmi local`, puis supprime le dossier `atelier`.
