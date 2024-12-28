<?php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ThresholdConstraint extends Constraint
{
    public string $invalidStatusCode = 'The value "{{ value }}" is not a valid HTTP code or range.';
    public string $invalidResponseTime = 'The value "{{ value }}" is not a valid response time.';
    public string $invalidPercent = 'The value "{{ value }}" must be between 1 and 99.';

    public string $type;

    public function __construct(string $type, array $options = [])
    {
        $this->type = $type;
        parent::__construct($options);

        if (!in_array($this->type, ["max_response", "status_code", "percent"], true)) {
            throw new \InvalidArgumentException('Type not allowed.');
        }
    }

    public function validatedBy(): string
    {
        return ThresholdValidator::class;
    }
}