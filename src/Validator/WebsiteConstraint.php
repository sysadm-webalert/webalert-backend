<?php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class WebsiteConstraint extends Constraint
{
    public $invalidSitenameMessage = 'The value "{{ value }}" is not a valid site name. It contains invalid characters.';
    public $invalidUrlMessage = 'The value "{{ value }}" is not a valid URL. It cannot contain paths.';

    public string $type;

    public function __construct(string $type, array $options = [])
    {
        $this->type = $type;
        parent::__construct($options);

        if (!in_array($this->type, ['sitename', 'url'], true)) {
            throw new \InvalidArgumentException('Type not allowed.');
        }
    }

    public function validatedBy(): string
    {
        return WebsiteValidator::class;
    }
}
