<?php

namespace App\Service;

use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidateEntityService
{
    public function validateEntity(object $entity, ValidatorInterface $validator): array
    {
        $violations = $validator->validate($entity);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
            return $errors;
        }

        return [];
    }
}
