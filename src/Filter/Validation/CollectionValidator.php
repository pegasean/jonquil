<?php

declare(strict_types=1);

namespace Jonquil\Filter\Validation;

/**
 * Class CollectionValidator
 * @package Jonquil\Filter\Validation
 */
class CollectionValidator extends Validator
{
    /**
     * @param array $data
     * @return bool
     */
    public function test(array $data): bool
    {
        $isValid = true;
        $this->validationErrors = [];
        $validators = [];
        foreach ($this->attributes as $attribute => $type) {
            if (isset($validators[$type])) {
                continue;
            }
            switch ($type) {
                case 'string':
                    $validator = new StringValidator();
                    break;
                case 'date':
                    $validator = new DateTimeValidator();
                    break;
                case 'integer':
                    $validator = new IntegerValidator();
                    break;
                case 'double':
                    $validator = new DoubleValidator();
                    break;
                default:
                    $type = 'default';
                    $validator = new Validator();
            }
            $validators[$type] = $validator;
        }
        foreach ($this->criteria as $criterion) {
            $type = $this->attributes[$criterion[0]];
            $validator = $validators[$type] ?? $validators['default'];
            $validator->addCriterion($criterion);
        }
        foreach ($validators as $validator) {
            if (!$validator->test($data)) {
                $this->validationErrors = array_merge(
                    $this->validationErrors,
                    $validator->getValidationErrors()
                );
                $isValid = false;
            }
        }
        return $isValid;
    }
}

// -- End of file
