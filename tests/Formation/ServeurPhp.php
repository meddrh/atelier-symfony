<?php

namespace App\Tests\Formation;

/**
 * Un vrai serveur `php -S` (script routeur compris) sur un projet, pour les tests lents.
 *
 * Le port est réservé à ces tests (8767, boucle locale) : un seul serveur à la fois.
 */
final class ServeurPhp
{
    public const PORT = 8767;

    /** @var resource|null */
    private $processus;

    /**
     * @param array<string, string> $env variables d'environnement du serveur (PATH ajouté)
     */
    public function __construct(private readonly string $projet, private readonly array $env = [])
    {
    }

    public function demarrer(): void
    {
        $journal = $this->projet.'/var/serveur-php.log';
        $commande = [\PHP_BINARY, '-S', '127.0.0.1:'.self::PORT, '-t', 'public'];
        // Le routeur est celui du projet : sans lui, `php -S` sert tout par public/index.php (l'ancienne architecture).
        if (is_file($this->projet.'/public/routeur.php')) {
            $commande[] = 'public/routeur.php';
        }
        $this->processus = proc_open(
            $commande,
            [1 => ['file', $journal, 'a'], 2 => ['file', $journal, 'a']],
            $tuyaux,
            $this->projet,
            ['PATH' => (string) getenv('PATH')] + $this->env,
        );
        if (!\is_resource($this->processus)) {
            throw new \RuntimeException('Le serveur PHP n\'a pas pu être lancé.');
        }
        for ($i = 0; $i < 100; ++$i) {
            $connexion = @fsockopen('127.0.0.1', self::PORT, $codeErreur, $messageErreur, 0.2);
            if (false !== $connexion) {
                fclose($connexion);

                return;
            }
            usleep(100_000);
        }
        $this->arreter();
        throw new \RuntimeException('Le serveur PHP ne répond pas sur le port '.self::PORT.'.');
    }

    /** @return array{int, string} code HTTP (0 si aucune réponse), corps */
    public function obtenir(string $chemin): array
    {
        $corps = @file_get_contents('http://127.0.0.1:'.self::PORT.$chemin, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 60]]));
        $code = 0;
        foreach ($http_response_header ?? [] as $ligne) {
            if (1 === preg_match('#^HTTP/\S+ (\d{3})#', $ligne, $m)) {
                $code = (int) $m[1];
            }
        }

        return [$code, false === $corps ? '' : $corps];
    }

    public function arreter(): void
    {
        if (\is_resource($this->processus)) {
            proc_terminate($this->processus);
            proc_close($this->processus);
        }
        $this->processus = null;
    }
}
