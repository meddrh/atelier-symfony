<?php

namespace App\Formation\Contenu;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lit contenu/ (YAML + Markdown) et refuse, par ContenuInvalide, tout ce qui est incohérent.
 */
final class ChargeurCatalogue
{
    /** @var array<string, string> */
    private array $contenus = [];
    /** @var array<string, true> */
    private array $idsDeBlocs = [];
    /** @var array<string, true> */
    private array $idsDeBadges = [];
    /** @var array<string, true> */
    private array $idsDeLignes = [];

    public function __construct(
        private readonly string $dossierContenu,
        private readonly string $dossierProjet,
    ) {
    }

    public function charger(): Catalogue
    {
        $this->contenus = [];
        $this->idsDeBlocs = [];
        $this->idsDeBadges = [];
        $this->idsDeLignes = [];

        $programme = $this->chargerProgramme();

        $parcours = $this->lire($this->dossierContenu.'/parcours.yaml');
        $idsNiveaux = $parcours['niveaux'] ?? null;
        if (!\is_array($idsNiveaux) || [] === $idsNiveaux) {
            throw new ContenuInvalide('parcours.yaml : la liste « niveaux » est absente ou vide.');
        }

        $niveaux = [];
        foreach ($idsNiveaux as $idNiveau) {
            if (!\is_string($idNiveau)) {
                throw new ContenuInvalide('parcours.yaml : chaque niveau doit être un identifiant.');
            }
            $niveaux[] = $this->chargerNiveau($idNiveau);
        }

        return new Catalogue($niveaux, $programme, $this->contenus);
    }

    /** @return list<LigneProgramme> */
    private function chargerProgramme(): array
    {
        $donnees = $this->lire($this->dossierContenu.'/programme.yaml');
        $sections = $donnees['sections'] ?? null;
        if (!\is_array($sections) || [] === $sections) {
            throw new ContenuInvalide('programme.yaml : la liste « sections » est absente ou vide.');
        }

        $lignes = [];
        foreach ($sections as $section) {
            $ou = 'programme.yaml, section';
            $sectionId = $this->texte($section, 'id', $ou);
            $ou = sprintf('programme.yaml, section « %s »', $sectionId);
            $titre = $this->texte($section, 'titre', $ou);
            $liste = $section['lignes'] ?? null;
            if (!\is_array($liste) || [] === $liste) {
                throw new ContenuInvalide(sprintf('%s : la liste « lignes » est absente ou vide.', $ou));
            }
            foreach ($liste as $ligne) {
                $id = $this->texte($ligne, 'id', $ou);
                if (isset($this->idsDeLignes[$id])) {
                    throw new ContenuInvalide(sprintf('Ligne de programme en double : « %s ».', $id));
                }
                $this->idsDeLignes[$id] = true;
                $lignes[] = new LigneProgramme(
                    $id,
                    $sectionId,
                    $titre,
                    true === ($section['hors_programme'] ?? false),
                    $this->texte($ligne, 'libelle', sprintf('%s, ligne « %s »', $ou, $id)),
                );
            }
        }

        return $lignes;
    }

    private function chargerNiveau(string $idNiveau): Niveau
    {
        $dossier = $this->dossierContenu.'/'.$idNiveau;
        $d = $this->lire($dossier.'/niveau.yaml');
        $ou = sprintf('niveau « %s »', $idNiveau);

        if (($d['id'] ?? null) !== $idNiveau) {
            throw new ContenuInvalide(sprintf('%s : l\'identifiant du fichier niveau.yaml doit être « %s ».', $ou, $idNiveau));
        }
        $nom = $this->texte($d, 'nom', $ou);
        $sousTitre = $this->texte($d, 'sous_titre', $ou);
        $badge = $this->badge($d['badge'] ?? null, $ou);

        $idsSujets = $d['sujets'] ?? null;
        if (!\is_array($idsSujets) || [] === $idsSujets) {
            throw new ContenuInvalide(sprintf('%s : la liste « sujets » est absente ou vide.', $ou));
        }

        $sujets = [];
        foreach ($idsSujets as $idSujet) {
            if (!\is_string($idSujet)) {
                throw new ContenuInvalide(sprintf('%s : chaque sujet doit être un identifiant.', $ou));
            }
            $sujets[$idSujet] = $this->chargerSujet($dossier, $idSujet);
        }

        $avant = [];
        $apres = [];
        foreach ($d['intercalaires'] ?? [] as $intercalaire) {
            if (!\is_array($intercalaire) || (isset($intercalaire['avant']) === isset($intercalaire['apres']))) {
                throw new ContenuInvalide(sprintf('%s : un intercalaire porte exactement une clé « avant » ou « apres ».', $ou));
            }
            $position = isset($intercalaire['avant']) ? 'avant' : 'apres';
            $sujetId = $intercalaire[$position];
            if (!\is_string($sujetId) || !isset($sujets[$sujetId])) {
                throw new ContenuInvalide(sprintf('%s : intercalaire placé %s un sujet inconnu (« %s »).', $ou, $position, \is_string($sujetId) ? $sujetId : '?'));
            }
            $ids = $intercalaire['blocs'] ?? null;
            if (!\is_array($ids) || [] === $ids) {
                throw new ContenuInvalide(sprintf('%s : l\'intercalaire %s « %s » ne contient aucun bloc.', $ou, $position, $sujetId));
            }
            foreach ($ids as $idBloc) {
                if (!\is_string($idBloc)) {
                    throw new ContenuInvalide(sprintf('%s : identifiant d\'intercalaire invalide.', $ou));
                }
                $bloc = $this->chargerIntercalaire($idBloc);
                if ('avant' === $position) {
                    $avant[$sujetId][] = $bloc;
                } else {
                    $apres[$sujetId][] = $bloc;
                }
            }
        }

        $blocs = [];
        foreach ($sujets as $idSujet => $sujet) {
            array_push($blocs, ...($avant[$idSujet] ?? []), ...$sujet->blocs, ...($apres[$idSujet] ?? []));
        }

        return new Niveau($idNiveau, $nom, $sousTitre, $badge, array_values($sujets), $blocs);
    }

    private function chargerSujet(string $dossierNiveau, string $idSujet): Sujet
    {
        $dossier = $dossierNiveau.'/'.$idSujet;
        $d = $this->lire($dossier.'/sujet.yaml');
        $ou = sprintf('sujet « %s »', $idSujet);

        if (($d['id'] ?? null) !== $idSujet) {
            throw new ContenuInvalide(sprintf('%s : l\'identifiant du fichier sujet.yaml doit être « %s ».', $ou, $idSujet));
        }
        if (1 !== preg_match('/^(\d+)-/', $idSujet, $m)) {
            throw new ContenuInvalide(sprintf('%s : l\'identifiant doit commencer par un numéro (ex. « 01-… »).', $ou));
        }
        $titre = $this->texte($d, 'titre', $ou);

        $liste = $d['blocs'] ?? null;
        if (!\is_array($liste) || [] === $liste) {
            throw new ContenuInvalide(sprintf('%s : la liste « blocs » est absente ou vide.', $ou));
        }
        $blocs = [];
        $nbExercices = 0;
        foreach ($liste as $donneesBloc) {
            $bloc = $this->construireBloc($donneesBloc, $dossier, $ou);
            if (TypeBloc::Exercice === $bloc->type) {
                ++$nbExercices;
            }
            $blocs[] = $bloc;
        }

        if ($nbExercices > 1) {
            throw new ContenuInvalide(sprintf('%s : au plus un exercice par sujet (%d trouvés).', $ou, $nbExercices));
        }
        $badge = isset($d['badge']) ? $this->badge($d['badge'], $ou) : null;
        if (1 === $nbExercices && null === $badge) {
            throw new ContenuInvalide(sprintf('%s : le sujet a un exercice mais pas de badge.', $ou));
        }
        if (0 === $nbExercices && null !== $badge) {
            throw new ContenuInvalide(sprintf('%s : le sujet a un badge mais pas d\'exercice.', $ou));
        }

        return new Sujet($idSujet, (int) $m[1], $titre, $badge, $blocs);
    }

    private function chargerIntercalaire(string $idBloc): Bloc
    {
        $dossier = $this->dossierContenu.'/rappels';
        $d = $this->lire($dossier.'/'.$idBloc.'.yaml');
        $bloc = $this->construireBloc($d, $dossier, 'rappels');
        if ($bloc->id !== $idBloc) {
            throw new ContenuInvalide(sprintf('rappels/%s.yaml : l\'identifiant du bloc doit être « %s » (trouvé « %s »).', $idBloc, $idBloc, $bloc->id));
        }
        if (!\in_array($bloc->type, [TypeBloc::Rappel, TypeBloc::Lecture], true)) {
            throw new ContenuInvalide(sprintf('Bloc « %s » : un intercalaire est un rappel ou une lecture, pas « %s ».', $bloc->id, $bloc->type->value));
        }

        return $bloc;
    }

    private function construireBloc(mixed $d, string $dossierFichiers, string $ou): Bloc
    {
        $id = $this->texte($d, 'id', $ou.', bloc');
        $ou = sprintf('bloc « %s »', $id);
        // Même contrainte que la route formation_lecon ; « D » : pas de saut de ligne final toléré.
        if (1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $id)) {
            throw new ContenuInvalide(sprintf('Identifiant de bloc « %s » invalide : seuls les lettres non accentuées, les chiffres, « - » et « _ » sont permis.', $id));
        }

        if (isset($this->idsDeBlocs[$id])) {
            throw new ContenuInvalide(sprintf('Identifiant de bloc en double : « %s ».', $id));
        }
        $this->idsDeBlocs[$id] = true;

        $valeurType = $this->texte($d, 'type', $ou);
        $type = TypeBloc::tryFrom($valeurType)
            ?? throw new ContenuInvalide(sprintf('%s : type inconnu « %s ».', $ou, $valeurType));

        $titre = $this->texte($d, 'titre', $ou);

        $duree = $d['duree'] ?? null;
        if (!\is_int($duree) || $duree <= 0) {
            throw new ContenuInvalide(sprintf('%s : la durée doit être un entier positif de minutes (reçu : %s).', $ou, json_encode($duree, JSON_UNESCAPED_UNICODE)));
        }

        $fichier = $this->texte($d, 'fichier', $ou);
        $chemin = $dossierFichiers.'/'.$fichier;
        if (!is_file($chemin)) {
            throw new ContenuInvalide(sprintf('%s : fichier Markdown absent « %s ».', $ou, $fichier));
        }
        $this->contenus[$id] = (string) file_get_contents($chemin);

        $programme = $d['programme'] ?? [];
        if (!\is_array($programme)) {
            throw new ContenuInvalide(sprintf('%s : « programme » doit être une liste.', $ou));
        }
        foreach ($programme as $ligne) {
            if (!\is_string($ligne) || !isset($this->idsDeLignes[$ligne])) {
                throw new ContenuInvalide(sprintf('%s : ligne de programme inconnue « %s ».', $ou, \is_string($ligne) ? $ligne : json_encode($ligne)));
            }
        }

        $dossierTests = null;
        if (TypeBloc::Exercice === $type) {
            $dossierTests = $this->texte($d, 'tests', $ou);
            if (!is_dir($this->dossierProjet.'/'.$dossierTests)) {
                throw new ContenuInvalide(sprintf('%s : dossier de tests absent « %s ».', $ou, $dossierTests));
            }
        }

        $champs = [];
        if (TypeBloc::Preparation === $type) {
            $champs = $this->champsFiche($d['champs'] ?? null, $ou);
        }

        return new Bloc($id, $type, $titre, $duree, $fichier, array_values($programme), $dossierTests, $champs);
    }

    /** @return list<array{cle: string, libelle: string, aide: string, correction: string}> */
    private function champsFiche(mixed $champs, string $ou): array
    {
        if (!\is_array($champs) || [] === $champs) {
            throw new ContenuInvalide(sprintf('%s : une fiche de préparation doit avoir au moins un champ.', $ou));
        }
        $resultat = [];
        $cles = [];
        foreach ($champs as $champ) {
            $cle = $this->texte($champ, 'cle', $ou.', champ de fiche');
            if (isset($cles[$cle])) {
                throw new ContenuInvalide(sprintf('%s : champ de fiche en double « %s ».', $ou, $cle));
            }
            $cles[$cle] = true;
            $ouChamp = sprintf('%s, champ « %s »', $ou, $cle);
            $resultat[] = [
                'cle' => $cle,
                'libelle' => $this->texte($champ, 'libelle', $ouChamp),
                'aide' => $this->texte($champ, 'aide', $ouChamp),
                'correction' => $this->texte($champ, 'correction', $ouChamp),
            ];
        }

        return $resultat;
    }

    private function badge(mixed $d, string $ou): Badge
    {
        $ou .= ', badge';
        $badge = new Badge(
            $this->texte($d, 'id', $ou),
            $this->texte($d, 'nom', $ou),
            $this->texte($d, 'description', $ou),
        );
        if (isset($this->idsDeBadges[$badge->id])) {
            throw new ContenuInvalide(sprintf('Identifiant de badge en double : « %s ».', $badge->id));
        }
        $this->idsDeBadges[$badge->id] = true;

        return $badge;
    }

    /** Valeur texte non vide de la clé $cle. */
    private function texte(mixed $donnees, string $cle, string $ou): string
    {
        $valeur = \is_array($donnees) ? ($donnees[$cle] ?? null) : null;
        if (!\is_string($valeur) || '' === trim($valeur)) {
            throw new ContenuInvalide(sprintf('%s : la clé « %s » est absente ou vide.', $ou, $cle));
        }

        return $valeur;
    }

    /** @return array<mixed> */
    private function lire(string $chemin): array
    {
        $affiche = str_starts_with($chemin, $this->dossierContenu.'/')
            ? substr($chemin, \strlen($this->dossierContenu) + 1)
            : $chemin;
        if (!is_file($chemin)) {
            throw new ContenuInvalide(sprintf('Fichier absent : %s.', $affiche));
        }
        try {
            $donnees = Yaml::parseFile($chemin);
        } catch (ParseException $e) {
            throw new ContenuInvalide(sprintf('%s : YAML illisible (%s).', $affiche, $e->getMessage()), 0, $e);
        }
        if (!\is_array($donnees)) {
            throw new ContenuInvalide(sprintf('%s : le fichier doit contenir une table YAML.', $affiche));
        }

        return $donnees;
    }
}
