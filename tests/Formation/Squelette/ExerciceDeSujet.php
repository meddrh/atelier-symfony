<?php

namespace App\Tests\Formation\Squelette;

/** Un exercice du contenu réel, avec de quoi retrouver son corrigé et le corrigé du sujet précédent. */
final class ExerciceDeSujet
{
    public function __construct(
        /** Identifiant du sujet, qui est aussi le dossier de son corrigé : `01-premieres-pages`. */
        public readonly string $sujet,
        public readonly string $blocId,
        /** Position parmi tous les exercices du parcours (1 = le premier). */
        public readonly int $rang,
        public readonly string $dossierTests,
        public readonly string $markdown,
        /** Sujet précédent : son corrigé est l'état de départ de celui-ci ; null pour le premier (dépôt nu). */
        public readonly ?string $sujetPrecedent,
    ) {
    }
}
