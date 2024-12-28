<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class WebsiteValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof WebsiteConstraint) {
            throw new \LogicException('The constraint must be an instance of WebsiteConstraint.');
        }

        if (null === $value || '' === $value) {
            return; 
        }

        if ($constraint->type === 'sitename') {
            $sitenamePattern = '/^[a-zA-Z0-9_-]+$/';
            if (!preg_match($sitenamePattern, $value)) {
                $this->context->buildViolation($constraint->invalidSitenameMessage)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
                return;
            }
        }

        if ($constraint->type === 'url') {
            $urlPattern = '/^(https?:\/\/[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})$/';
            if (!preg_match($urlPattern, $value)) {
                $this->context->buildViolation($constraint->invalidUrlMessage)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
                return;
            }
        }
    }
}
