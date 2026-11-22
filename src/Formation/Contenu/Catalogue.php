<?php

namespace App\Formation\Contenu;

final class Catalogue
{
    /** @var array<string, Bloc> */
    private array $blocs = [];
    /** @var list<Bloc> */
    private array $ordre = [];
    /** @var array<string, ?Sujet> */
    private array $sujetParBloc = [];
    /** @var array<string, Niveau> */
    private array $niveauParBloc = [];

    /**
     * @param list<Niveau>          $niveaux
     * @param list<LigneProgramme>  $programme
     * @param array<string, string> $contenus  Markdown brut, par identifiant de bloc
     */
    public function __construct(
        private readonly array $niveaux,
        private readonly array $programme,
        private readonly array $contenus,
    ) {
        foreach ($niveaux as $niveau) {
            foreach ($niveau->blocs as $bloc) {
                $this->blocs[$bloc->id] = $bloc;
                $this->ordre[] = $bloc;
                $this->niveauParBloc[$bloc->id] = $niveau;
                $this->sujetParBloc[$bloc->id] = null;
            }
            foreach ($niveau->sujets as $sujet) {
                foreach ($sujet->blocs as $bloc) {
                    $this->sujetParBloc[$bloc->id] = $sujet;
                }
            }
        }
    }

    /** @return list<Niveau> */
    public function niveaux(): array
    {
        return $this->niveaux;
    }

    /** @return list<Bloc> dans l'ordre du parcours */
    public function blocsOrdonnes(): array
    {
        return $this->ordre;
    }

    public function existe(string $id): bool
    {
        return isset($this->blocs[$id]);
    }

    public function bloc(string $id): Bloc
    {
        return $this->blocs[$id] ?? throw new ContenuInvalide(sprintf('Bloc inconnu : « %s ».', $id));
    }

    /** Le sujet du bloc, ou null pour un intercalaire (rappel, lecture). */
    public function sujetDuBloc(string $blocId): ?Sujet
    {
        $this->bloc($blocId);

        return $this->sujetParBloc[$blocId];
    }

    public function niveauDuBloc(string $blocId): Niveau
    {
        $this->bloc($blocId);

        return $this->niveauParBloc[$blocId];
    }

    /** @return list<LigneProgramme> */
    public function programme(): array
    {
        return $this->programme;
    }

    /** Markdown brut du bloc. */
    public function contenuDuBloc(string $blocId): string
    {
        $this->bloc($blocId);

        return $this->contenus[$blocId];
    }
}
