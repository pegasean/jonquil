<?php

declare(strict_types=1);

namespace Jonquil\Filter\Validation;

use InvalidArgumentException;

/**
 * Class IntegerValidator
 * @package Jonquil\Filter\Validation
 */
class IntegerValidator extends Validator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value1, string $constraint, $value2 = null): bool
    {
        switch ($constraint) {
            case 'digit_count':
                return $this->validateDigitCount($value1, $value2);
            default:
                return parent::validate($value1, $constraint, $value2);
        }
    }

    /*--------------------------------------------------------------------*/

    /**
     * Validates that an integer's digit count is equal to a given value.
     *
     * @param int $number
     * @param int $count
     * @return bool
     */
    protected function hasDigitCount(int $number, int $count): bool
    {
        return strlen((string) abs($number)) === $count;
    }

    /**
     * Validates that an integer's digit count is greater than or equal to
     * a given value.
     *
     * @param int $number
     * @param int $count
     * @return bool
     */
    protected function hasMinDigitCount(int $number, int $count): bool
    {
        return strlen((string) abs($number)) >= $count;
    }

    /**
     * Validates that an integer's digit count is less than or equal to
     * a given value.
     *
     * @param int $number
     * @param int $count
     * @return bool
     */
    protected function hasMaxDigitCount(int $number, int $count): bool
    {
        return strlen((string) abs($number)) <= $count;
    }

    /**
     * Validates that an integer's digit count is between given minimum
     * and maximum values.
     *
     * @param int $number
     * @param int $minCount
     * @param int $maxCount
     * @return bool
     */
    protected function hasDigitCountBetween(
        int $number,
        int $minCount,
        int $maxCount
    ): bool {
        $count = strlen((string) abs($number));
        return ($count >= $minCount) && ($count <= $maxCount);
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param int $number
     * @param int|array $count
     * @return bool
     */
    protected function validateDigitCount(int $number, $count): bool
    {
        if (is_int($count)) {
            return $this->hasDigitCount($number, $count);
        } elseif (is_array($count)) {
            if (isset($count['min']) && isset($count['max'])) {
                return $this->hasDigitCountBetween(
                    $number, $count['min'], $count['max']
                );
            } elseif(isset($count['min'])) {
                return $this->hasMinDigitCount($number, $count['min']);
            } elseif(isset($count['max'])) {
                return $this->hasMaxDigitCount($number, $count['max']);
            }
        }
        throw new InvalidArgumentException(
            static::$errors['invalid_parameter']
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function addValidationError(
        string $attribute,
        string $constraint,
        $value = null,
        array $options = []
    ) {
        switch ($constraint) {
            case 'digit_count':
                $error = $this->buildDigitCountValidationError($value);
                break;
            default:
                parent::addValidationError(
                    $attribute,
                    $constraint,
                    $value,
                    $options
                );
                return;
        }
        if (isset($options['message'])) {
            $error[0] = $options['message'];
        }
        $this->validationErrors[$attribute][] = $error;
    }

    /**
     * @param int|array $count
     * @return array
     */
    protected function buildDigitCountValidationError($count): array
    {
        if (is_int($count)) {
            return [
                /* #lang */ 'validation.integer.digit_count.equal_to',
                ['digits' => $count],
            ];
        } elseif (is_array($count)) {
            if (isset($count['min']) && isset($count['max'])) {
                return [
                    /* #lang */ 'validation.integer.digit_count.between',
                    ['min' => $count['min'], 'max' => $count['max']],
                ];
            } elseif(isset($count['min'])) {
                return [
                    /* #lang */ 'validation.integer.digit_count.min',
                    ['min' => $count['min']],
                ];
            } elseif(isset($count['max'])) {
                return [
                    /* #lang */ 'validation.integer.digit_count.max',
                    ['max' => $count['max']],
                ];
            }
        }
        return [/* #lang */ 'validation.default'];
    }
}

// -- End of file
