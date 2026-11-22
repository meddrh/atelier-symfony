<?php

namespace App\Formation\Parcours;

enum StatutBloc: string
{
    case Fait = 'fait';
    case EnCours = 'en_cours';
    case Verrouille = 'verrouille';
}
