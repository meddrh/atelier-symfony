<?php

namespace App\Tests\Exercices\TestsFonctionnels\Defauts;

use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Défaut volontaire : la validation ne refuse plus rien (formulaires comme objets). Il remplace le service
 * « validator » dans une copie jetable de l'application (décoration), par TestsFonctionnelsTest.
 */
final class ValidateurIndulgent implements ValidatorInterface
{
    public function __construct(private readonly ValidatorInterface $interne)
    {
    }

    public function getMetadataFor($valeur): MetadataInterface
    {
        return $this->interne->getMetadataFor($valeur);
    }

    public function hasMetadataFor($valeur): bool
    {
        return $this->interne->hasMetadataFor($valeur);
    }

    public function validate($valeur, $contraintes = null, $groupes = null): ConstraintViolationListInterface
    {
        return new ConstraintViolationList();
    }

    public function validateProperty(object $objet, string $nomDePropriete, $groupes = null): ConstraintViolationListInterface
    {
        return new ConstraintViolationList();
    }

    public function validatePropertyValue($objetOuClasse, string $nomDePropriete, $valeur, $groupes = null): ConstraintViolationListInterface
    {
        return new ConstraintViolationList();
    }

    public function startContext(): ContextualValidatorInterface
    {
        return $this->interne->startContext();
    }

    public function inContext(ExecutionContextInterface $contexte): ContextualValidatorInterface
    {
        return $this->interne->inContext($contexte);
    }
}
