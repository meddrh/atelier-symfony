<?php

namespace App\Tests\Formation\Controller;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Lecture d'une ligne de bloc (gabarit _blocs.html.twig, macro « ligne ») : l'état se vérifie
 * dans la ligne du bloc, pas dans le texte de toute la page, où « Fait » ou « Verrouillé »
 * peuvent venir d'un autre bloc.
 */
trait AvecLignesDeBloc
{
    /**
     * Étiquette d'état de la ligne du bloc de ce titre et de ce type (« Rappel », « Exercice », …) dans $zone.
     * La classe de la ligne doit dire la même chose que l'étiquette.
     */
    private function etatDeLaLigne(Crawler $zone, string $titre, string $libelleType): string
    {
        $etats = [];
        foreach ($zone->filter('li.bloc') as $noeud) {
            $ligne = new Crawler($noeud);
            if ($titre.' '.$libelleType === $ligne->filter('.bloc__titre')->text()) {
                $etats[] = [$ligne->filter('.etat')->text(), (string) $ligne->attr('class')];
            }
        }
        self::assertCount(1, $etats, sprintf('une seule ligne « %s » (%s) attendue', $titre, $libelleType));

        [$etat, $classe] = $etats[0];
        $attendu = ['Fait' => 'bloc--fait', 'En cours' => 'bloc--en-cours', 'Verrouillé' => 'bloc--verrouille'][$etat] ?? null;
        self::assertNotNull($attendu, sprintf('état inconnu « %s » pour « %s »', $etat, $titre));
        self::assertStringContainsString($attendu, $classe, sprintf('la classe de la ligne « %s » dit autre chose que « %s »', $titre, $etat));

        return $etat;
    }
}
