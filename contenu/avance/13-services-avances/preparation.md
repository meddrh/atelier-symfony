## Penser avant de coder

Après la démonstration, tu sais composer des services par étiquette, les décorer et vérifier les étiquettes au démarrage. À toi de construire l'**export CSV de l'historique des affectations** avec les mêmes outils.

Voici ce qu'on te demande de réaliser :

- une adresse `/historique/export.csv`, réservée aux gestionnaires, qui **télécharge** tout l'historique en CSV ;
- chaque **colonne** du fichier est un **service** étiqueté, avec un rang (`ordre`) ; une **passe de compilation** trie les colonnes et refuse une étiquette sans rang ;
- un **décorateur** ajoute en tête du fichier l'indicateur d'ordre des octets UTF-8 (le « BOM »), pour que le tableur lise bien les accents ;
- aucune cellule ne doit pouvoir **s'exécuter comme une formule** dans le tableur.

Décide maintenant de ce dont tu as besoin, avant de lire la correction. Le piège de ce sujet : **un CSV est un fichier qu'un autre programme va interpréter**. Un titre de matériel saisi par un visiteur et commençant par `=` n'est plus une simple donnée dès qu'il arrive dans une cellule.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour les colonnes, relis la démonstration : qui connaît la **liste** des colonnes, et qui connaît **chaque** colonne ?
- Pour la passe, demande-toi **à quel moment** elle tourne : à chaque requête, ou une seule fois ?
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
