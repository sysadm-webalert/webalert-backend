<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ThresholdValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ThresholdConstraint) {
            throw new \LogicException('The constraint must be an instance of ThresholdConstraint.');
        }

        if (null === $value || '' === $value) {
            return; 
        }

        if ($constraint->type === 'status_code') {
            $singleCodePattern = '/^\d{3}$/';
            $rangePattern = '/^\d{3}-\d{3}$/';

            if (preg_match($singleCodePattern, $value)) {
                $number = (int)$value;
                if ($number >= 100 && $number <= 599) {
                    return;
                }
            }

            if (preg_match($rangePattern, $value)) {
                [$start, $end] = explode('-', $value);
                $start = (int)$start;
                $end = (int)$end;

                if ($start >= 100 && $end <= 599 && $start <= $end) {
                    return;
                }
            }

            $this->context->buildViolation($constraint->invalidStatusCode)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }

        if ($constraint->type === 'max_response') {
            if (!is_numeric($value) || (float) $value <= 0) {
                $this->context->buildViolation($constraint->invalidResponseTime)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
            }
        }
        if ($constraint->type === 'percent') {
            if (!is_numeric($value) || $value < 1 || $value > 99) {
                $this->context->buildViolation($constraint->invalidPercent)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
            }
        }
    }
}
