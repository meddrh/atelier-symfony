## Pourquoi cette lecture

Tu as maintenant écrit des pages, des formulaires et des commandes. Une question reste : **sur quoi peux-tu t'appuyer dans la durée ?** Peux-tu utiliser Symfony dans un logiciel d'entreprise ? Ton code de 5.4 fonctionnera-t-il encore après une mise à jour ? Que veut dire un avertissement « déprécié » ? Ici, pas d'exercice : ce sont des notions à connaître, et elles ne se testent pas par du code. Cette fiche ne donne pas de badge.

Une règle pour cette lecture : toute **date précise** est donnée par le site officiel, pas par cette fiche. Quand tu verras « à vérifier sur symfony.com », fais-le : le calendrier peut être révisé.

## La licence : MIT

Symfony est publié sous la licence **MIT**, l'une des plus permissives :

- tu peux l'utiliser, le copier, le modifier et le redistribuer, y compris dans un logiciel **commercial** dont le code reste fermé ;
- à une condition : conserver la mention de droit d'auteur et le texte de la licence ;
- en échange, **aucune garantie** : le logiciel est fourni « tel quel ».

Ce que cela change pour toi : pas de redevance à payer, pas d'obligation de publier le code de ton application. Les règles de la documentation et des marques (le nom « Symfony ») sont distinctes de celles du code : à vérifier sur symfony.com avant de les réutiliser.

## Le numéro de version

Un numéro comme `5.4.12` se lit **majeure.mineure.correctif** :

- le **correctif** (le troisième nombre) corrige des bogues et des failles, sans rien ajouter ;
- la **mineure** (le deuxième) ajoute des fonctionnalités, sans rien casser ;
- la **majeure** (le premier) est la seule qui peut retirer des fonctionnalités.

## Le calendrier des versions

- Une **version mineure** sort environ **tous les six mois** (en principe en mai et en novembre : à vérifier sur symfony.com/releases).
- Une **version majeure** arrive environ **tous les deux ans**. Elle contient exactement les mêmes fonctionnalités que la dernière mineure de la ligne précédente, **moins** tout ce qui était déprécié : passer de 5.4 à 6.0, ce n'est pas découvrir du nouveau, c'est perdre l'ancien.
- Certaines versions sont **LTS** (support à long terme) : elles sont maintenues plus longtemps que les autres, ce qui convient aux projets d'entreprise qu'on ne veut pas migrer tous les six mois. Le projet de cette formation utilise **Symfony 5.4, une version LTS**.
- Chaque version a deux dates de fin : la fin des **corrections de bogues** et la fin des **correctifs de sécurité**. Ces dates précises sont **à vérifier sur symfony.com/releases** (cherche la page de ta version).

Tu peux aussi les lire sur ton poste :

```bash
docker compose exec app php bin/console about
```

La commande affiche la version de Symfony utilisée par ton projet, et les dates de fin de maintenance et de fin de vie qui la concernent.

## La promesse de rétrocompatibilité

Symfony s'engage sur ceci : **à l'intérieur d'une même version majeure, une mise à jour mineure ou corrective ne casse pas ton code**, tant que tu n'utilises que l'interface publique. Passer de 5.4.1 à 5.4.12, ou de 5.3 à 5.4, doit se faire sans réécrire quoi que ce soit.

Quelques précisions :

- La promesse couvre les classes, méthodes et configurations prévues pour que **tu** les utilises. Ce qui est marqué `@internal` dans le code de Symfony est réservé à Symfony : ne t'y appuie pas, cela peut changer à tout moment.
- Une version peut corriger un bogue, et ce correctif peut changer un comportement sur lequel quelqu'un comptait par erreur. La promesse n'interdit pas les corrections.
- Les règles exactes (ce qui est couvert, ce qui ne l'est pas) sont décrites dans la page « Backward Compatibility Promise » de la documentation (à vérifier sur symfony.com).

## Les dépréciations : annoncer avant de retirer

Comment Symfony retire-t-il une fonctionnalité sans casser personne ? En deux temps :

1. Dans une version **mineure** (par exemple 5.3), la fonctionnalité est **dépréciée** : elle fonctionne encore, mais chaque utilisation déclenche un **avertissement** qui indique ce qu'il faut employer à la place et depuis quelle version.
2. Dans la version **majeure** suivante (6.0), elle est **supprimée**.

La méthode de mise à jour qui en découle :

1. Passe à la **dernière version mineure** de ta ligne (ici 5.4).
2. **Corrige tous les avertissements** de dépréciation, qui sont rendus visibles de plusieurs façons : dans la barre de débogage et le profileur de Symfony en environnement de développement, dans les journaux, et à la fin d'une exécution de PHPUnit grâce au pont `symfony/phpunit-bridge` (celui de ton projet : l'option du fichier `.env.test` qui commence par `SYMFONY_DEPRECATIONS_HELPER` en règle le comportement).
3. **Alors seulement**, passe à la version majeure suivante : plus rien n'y est cassé pour toi.

C'est la raison pour laquelle on dit que la dernière mineure d'une ligne est un **pont** vers la majeure suivante.

## Pour vérifier que tu as retenu

1. Que garantit la licence MIT, et que demande-t-elle en échange ?
2. Dans `5.4.12`, quel nombre change quand on ajoute une fonctionnalité ? Quand on corrige un bogue ?
3. Pourquoi passer de 5.4 à 6.0 ne doit-il rien ajouter de nouveau ?
4. Une méthode est « dépréciée » : qu'est-ce que cela veut dire, et que fais-tu ?
5. Où trouves-tu la date de fin de support de ta version ?

Quand tes réponses te paraissent claires, clique sur « Terminé ».
