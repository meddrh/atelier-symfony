<?php

namespace App\Formation\Contenu;

enum TypeBloc: string
{
    case Preparation = 'preparation';
    case Demonstration = 'demonstration';
    case Rappel = 'rappel';
    case Lecture = 'lecture';
    case Exercice = 'exercice';
}
