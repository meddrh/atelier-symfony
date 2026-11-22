## Penser avant de coder

Jusqu'ici, tes fournisseurs ne pouvaient être ajoutés que par une requête SQL écrite à la main. Dans ce sujet, un utilisateur va pouvoir **les gérer lui-même depuis le navigateur** : en ajouter, en modifier, en supprimer. C'est le premier sujet où quelqu'un d'extérieur envoie des données à ton application, et tout ce qu'il envoie est à considérer comme **non fiable** : une adresse mal écrite, un champ vide, un e-mail déjà utilisé, ou même une requête fabriquée pour supprimer des fournisseurs à son insu.

Voici ce qu'on te demande de réaliser :

- un formulaire pour **ajouter** un fournisseur (nom, adresse e-mail, téléphone facultatif) ;
- un formulaire pour **modifier** un fournisseur existant, déjà rempli avec ses valeurs actuelles ;
- un moyen de **supprimer** un fournisseur depuis la liste ;
- un **refus clair** quand les données sont mauvaises : nom vide, adresse e-mail invalide, adresse e-mail déjà prise par un autre fournisseur ;
- un **message de confirmation** après chaque ajout, modification ou suppression.

Décide maintenant de ce dont tu as besoin, avant de voir comment la démonstration le fait pour le matériel. Le piège de ce sujet n'est pas la syntaxe : c'est l'**ordre**. Si tu écris les pages avant de savoir où vivent les règles de validation, tu les recopieras à plusieurs endroits.

## Comment remplir la fiche

- Écris avec tes mots. Pas besoin de la syntaxe exacte.
- Pour les routes, pense à **deux** choses par page : l'adresse, et la méthode HTTP qui a le droit de l'appeler.
- Pour la suppression, demande-toi : « Qu'est-ce qui empêche un autre site de faire supprimer un fournisseur à mon utilisateur, à son insu ? »
- Chaque partie doit être remplie pour continuer. Ce n'est pas noté : tu compareras ensuite avec la correction.
