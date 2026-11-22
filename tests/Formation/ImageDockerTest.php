<?php

namespace App\Tests\Formation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Le poste du junior : l'image ne tourne pas en root (les fichiers créés dans var/ lui appartiennent) et
 * PHP accepte un téléversement de quelques Mio (limite par défaut : 2 Mio).
 */
class ImageDockerTest extends TestCase
{
    private const RACINE = __DIR__.'/../..';

    public function testLImageTourneSansDroitsRootAvecUnUidReglable(): void
    {
        $dockerfile = (string) file_get_contents(self::RACINE.'/docker/Dockerfile');

        self::assertMatchesRegularExpression('/^ARG APP_UID=1000$/m', $dockerfile);
        self::assertMatchesRegularExpression('/^ARG APP_GID=1000$/m', $dockerfile);
        self::assertMatchesRegularExpression('/^USER app$/m', $dockerfile);
    }

    public function testLUidOuLeGidDeLUtilisateurPeutDejaExisterDansLImage(): void
    {
        // Sans --non-unique, un UID ou un GID déjà pris dans l'image de base (33, www-data…) fait échouer la construction.
        $dockerfile = (string) file_get_contents(self::RACINE.'/docker/Dockerfile');

        self::assertMatchesRegularExpression('/groupadd --non-unique /', $dockerfile);
        self::assertMatchesRegularExpression('/useradd --non-unique /', $dockerfile);
    }

    public function testLeReadmeFaitExporterLUidAvantLaConstructionEtLeChown(): void
    {
        $readme = (string) file_get_contents(self::RACINE.'/README.md');
        $section = substr($readme, (int) strpos($readme, '## Fichiers appartenant à root'));
        $export = strpos($section, 'export APP_UID');
        $construction = strpos($section, 'docker compose build');
        $chown = strpos($section, 'chown -R');

        self::assertNotFalse($export);
        self::assertNotFalse($construction);
        self::assertNotFalse($chown);
        self::assertLessThan($construction, $export, 'l\'export APP_UID vient avant la construction de l\'image');
        self::assertLessThan($chown, $export, 'l\'export APP_UID vient avant le chown');
    }

    public function testComposeTransmetLUidAvecUneValeurParDefaut(): void
    {
        $compose = Yaml::parseFile(self::RACINE.'/compose.yaml');

        $args = $compose['services']['app']['build']['args'] ?? [];
        self::assertSame('${APP_UID:-1000}', $args['APP_UID'] ?? null);
        self::assertSame('${APP_GID:-1000}', $args['APP_GID'] ?? null);
        self::assertSame('docker', $compose['services']['app']['build']['context']);
    }

    public function testLaLimiteDeTeleversementPermetHuitMioDeFichier(): void
    {
        $ini = parse_ini_file(self::RACINE.'/docker/php.ini');

        self::assertIsArray($ini);
        self::assertSame('8M', $ini['upload_max_filesize']);
        self::assertSame('10M', $ini['post_max_size']);
        self::assertStringContainsString('COPY php.ini /usr/local/etc/php/conf.d/', (string) file_get_contents(self::RACINE.'/docker/Dockerfile'));
    }

    public function testLesFinsDeLigneSontNormaliseesEtLesBinairesDeclares(): void
    {
        $attributs = (string) file_get_contents(self::RACINE.'/.gitattributes');

        self::assertMatchesRegularExpression('/^\* text=auto eol=lf$/m', $attributs);
        foreach (['png', 'jpg', 'gif', 'ico', 'woff2', 'pdf'] as $extension) {
            self::assertMatchesRegularExpression('/^\*\.'.$extension.' binary$/m', $attributs, $extension);
        }
    }
}
