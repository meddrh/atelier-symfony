<?php

namespace App\Formation\Progression;

/**
 * Progression du junior, dans une base SQLite à part (hors Doctrine) pour que les
 * migrations de son propre code ne la touchent jamais. Le fichier et le schéma sont
 * créés au premier accès.
 */
final class ProgressionStore
{
    public const DUREES = [15, 30, 45, 60];
    private const DUREE_PAR_DEFAUT = 15;

    private ?\PDO $pdo = null;

    public function __construct(private readonly string $cheminBase)
    {
    }

    /** Chemin du fichier SQLite (source unique : FORMATION_DB). */
    public function chemin(): string
    {
        return $this->cheminBase;
    }

    /** Ouvre la base (la crée au besoin) : lève une exception si elle est illisible. */
    public function ouvrir(): void
    {
        $this->base();
    }

    /**
     * Exécute $operations en une seule transaction : tout est écrit, ou rien. Une exception
     * annule tout ce que la fermeture a écrit, puis remonte. Appelée dans une transaction
     * déjà ouverte, la fermeture s'y joint (la transaction extérieure décide).
     *
     * @template T
     *
     * @param callable(): T $operations
     *
     * @return T
     */
    public function transaction(callable $operations): mixed
    {
        $pdo = $this->base();
        if ($pdo->inTransaction()) {
            return $operations();
        }

        $pdo->beginTransaction();
        try {
            $resultat = $operations();
            $pdo->commit();
        } catch (\Throwable $erreur) {
            $pdo->inTransaction() && $pdo->rollBack();

            throw $erreur;
        }

        return $resultat;
    }

    public function marquerFait(string $blocId, \DateTimeImmutable $quand): void
    {
        // INSERT OR IGNORE : idempotent, la première date est conservée.
        $this->executer(
            'INSERT OR IGNORE INTO bloc_fait (bloc_id, fait_le) VALUES (:id, :quand)',
            ['id' => $blocId, 'quand' => $quand->format(\DATE_ATOM)]
        );
    }

    public function estFait(string $blocId): bool
    {
        return false !== $this->executer('SELECT 1 FROM bloc_fait WHERE bloc_id = :id', ['id' => $blocId])->fetchColumn();
    }

    /** @return array<string, \DateTimeImmutable> */
    public function blocsFaits(): array
    {
        $faits = [];
        foreach ($this->executer('SELECT bloc_id, fait_le FROM bloc_fait ORDER BY fait_le, bloc_id')->fetchAll(\PDO::FETCH_NUM) as [$id, $quand]) {
            $faits[$id] = new \DateTimeImmutable($quand);
        }

        return $faits;
    }

    /** @param array<string, string> $reponses */
    public function enregistrerFiche(string $blocId, array $reponses): void
    {
        $this->executer(
            'INSERT OR REPLACE INTO fiche (bloc_id, reponses_json, maj_le) VALUES (:id, :json, :quand)',
            [
                'id' => $blocId,
                'json' => json_encode($reponses, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'quand' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ]
        );
    }

    /** @return array<string, string>|null */
    public function fiche(string $blocId): ?array
    {
        $json = $this->executer('SELECT reponses_json FROM fiche WHERE bloc_id = :id', ['id' => $blocId])->fetchColumn();

        return false === $json ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function enregistrerLancement(Lancement $l): void
    {
        $this->executer(
            'INSERT INTO lancement (bloc_id, vert, nb_tests, nb_reussis, resume, date, echecs_json)
             VALUES (:bloc, :vert, :tests, :reussis, :resume, :date, :echecs)',
            [
                'bloc' => $l->blocId,
                'vert' => $l->vert ? 1 : 0,
                'tests' => $l->nbTests,
                'reussis' => $l->nbReussis,
                'resume' => $l->resume,
                'date' => $l->date->format(\DATE_ATOM),
                'echecs' => json_encode($l->echecs, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /** @return list<Lancement> le plus récent d'abord */
    public function lancements(string $blocId): array
    {
        $lignes = $this->executer(
            'SELECT bloc_id, vert, nb_tests, nb_reussis, resume, date, echecs_json FROM lancement WHERE bloc_id = :id ORDER BY id DESC',
            ['id' => $blocId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $l) => new Lancement(
            $l['bloc_id'],
            1 === (int) $l['vert'],
            (int) $l['nb_tests'],
            (int) $l['nb_reussis'],
            $l['resume'],
            new \DateTimeImmutable($l['date']),
            null === $l['echecs_json'] ? [] : json_decode($l['echecs_json'], true, 512, \JSON_THROW_ON_ERROR),
        ), $lignes);
    }

    public function nombreDeRouges(string $blocId): int
    {
        return (int) $this->executer(
            'SELECT COUNT(*) FROM lancement WHERE bloc_id = :id AND vert = 0',
            ['id' => $blocId]
        )->fetchColumn();
    }

    /** Étape de démonstration atteinte en dernier : revenir sur le bloc la rouvre. */
    public function memoriserEtape(string $blocId, int $etape): void
    {
        $this->executer('INSERT OR REPLACE INTO etape_demo (bloc_id, etape) VALUES (:id, :etape)', ['id' => $blocId, 'etape' => $etape]);
    }

    public function etapeMemorisee(string $blocId): ?int
    {
        $etape = $this->executer('SELECT etape FROM etape_demo WHERE bloc_id = :id', ['id' => $blocId])->fetchColumn();

        return false === $etape ? null : (int) $etape;
    }

    public function duree(): int
    {
        $valeur = $this->executer("SELECT valeur FROM reglage WHERE cle = 'duree'")->fetchColumn();

        return false === $valeur ? self::DUREE_PAR_DEFAUT : (int) $valeur;
    }

    public function changerDuree(int $minutes): void
    {
        if (!\in_array($minutes, self::DUREES, true)) {
            throw new \InvalidArgumentException(sprintf('Durée de séance invalide : %d (valeurs permises : %s).', $minutes, implode(', ', self::DUREES)));
        }
        $this->executer("INSERT OR REPLACE INTO reglage (cle, valeur) VALUES ('duree', :v)", ['v' => (string) $minutes]);
    }

    /** @param array<string, string|int> $parametres */
    private function executer(string $sql, array $parametres = []): \PDOStatement
    {
        $requete = $this->base()->prepare($sql);
        $requete->execute($parametres);

        return $requete;
    }

    private function base(): \PDO
    {
        if (null !== $this->pdo) {
            return $this->pdo;
        }

        $dossier = \dirname($this->cheminBase);
        if (!is_dir($dossier) && !mkdir($dossier, 0777, true) && !is_dir($dossier)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier « %s ».', $dossier));
        }

        $pdo = new \PDO('sqlite:'.$this->cheminBase, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 5, // la page web et la commande peuvent écrire en même temps
        ]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS bloc_fait (bloc_id TEXT PRIMARY KEY, fait_le TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS fiche (bloc_id TEXT PRIMARY KEY, reponses_json TEXT NOT NULL, maj_le TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS lancement (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bloc_id TEXT NOT NULL,
            vert INTEGER NOT NULL,
            nb_tests INTEGER NOT NULL,
            nb_reussis INTEGER NOT NULL,
            resume TEXT NOT NULL,
            date TEXT NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS lancement_bloc ON lancement (bloc_id)');
        // Colonne ajoutée après les autres : voir la méthode, qui la crée sur une base déjà en service.
        $this->ajouterLaListeDesTestsRouges($pdo);
        // Table ajoutée après les autres : CREATE IF NOT EXISTS migre une base déjà en service sans toucher à ses données.
        $pdo->exec('CREATE TABLE IF NOT EXISTS etape_demo (bloc_id TEXT PRIMARY KEY, etape INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS reglage (cle TEXT PRIMARY KEY, valeur TEXT NOT NULL)');

        return $this->pdo = $pdo;
    }

    /**
     * Colonne ajoutée après les autres : une base déjà en service la reçoit sans rien perdre (ses anciens lancements
     * gardent un NULL, lu comme « pas de liste »). La page web et la commande peuvent ouvrir la base en même temps :
     * si l'autre a ajouté la colonne entre-temps, c'est fait, et tout autre échec remonte.
     */
    private function ajouterLaListeDesTestsRouges(\PDO $pdo): void
    {
        $colonnes = array_column($pdo->query('PRAGMA table_info(lancement)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
        if (\in_array('echecs_json', $colonnes, true)) {
            return;
        }
        try {
            $pdo->exec('ALTER TABLE lancement ADD COLUMN echecs_json TEXT');
        } catch (\PDOException $e) {
            $colonnes = array_column($pdo->query('PRAGMA table_info(lancement)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
            if (!\in_array('echecs_json', $colonnes, true)) {
                throw $e;
            }
        }
    }
}
