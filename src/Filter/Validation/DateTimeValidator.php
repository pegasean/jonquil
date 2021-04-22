<?php

declare(strict_types=1);

namespace Jonquil\Filter\Validation;

/**
 * Class DateTimeValidator
 * @package Jonquil\Filter\Validation
 */
class DateTimeValidator extends Validator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value1, string $constraint, $value2 = null): bool
    {
        switch ($constraint) {
            case 'after':
                return $this->isGreaterThan($value1, $value2);
            case 'before':
                return $this->isLessThan($value1, $value2);
            case 'format':
                return $this->validateFormat($value1, $value2);
            default:
                return parent::validate($value1, $constraint, $value2);
        }
    }

    /*--------------------------------------------------------------------*/

    /**
     * {@inheritdoc}
     */
    protected function isOfType($value, string $type): bool
    {
        switch ($type) {
            case 'date':
                $validator = new StringValidator();
                return $validator->validate($value, 'format', 'date');
            default:
                return parent::isOfType($value, $type);
        }
    }

    /**
     * Validates that a date is equal to another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isEqualTo($comparedDate, $referenceDate): bool
    {
        return $this->compare($comparedDate, $referenceDate) === 0;
    }

    /**
     * Validates that a date is not equal to another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isNotEqualTo($comparedDate, $referenceDate): bool
    {
        return $this->compare($comparedDate, $referenceDate) !== 0;
    }

    /**
     * Validates that a date is identical to another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isIdenticalTo($comparedDate, $referenceDate): bool
    {
        return $this->isEqualTo($comparedDate, $referenceDate);
    }

    /**
     * Validates that a date is not identical to another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isNotIdenticalTo($comparedDate, $referenceDate): bool
    {
        return $this->isNotEqualTo($comparedDate, $referenceDate);
    }

    /**
     * Validates that a date is strictly before another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isLessThan($comparedDate, $referenceDate): bool
    {
        return $this->compare($comparedDate, $referenceDate) < 0;
    }

    /**
     * Validates that a date is before or equal to another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isLessThanOrEqualTo(
        $comparedDate,
        $referenceDate
    ): bool {
        return $this->compare($comparedDate, $referenceDate) <= 0;
    }

    /**
     * Validates that a date is strictly after another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isGreaterThan($comparedDate, $referenceDate): bool
    {
        return $this->compare($comparedDate, $referenceDate) > 0;
    }

    /**
     * Validates that a date is after or equal to another given date.
     *
     * @param mixed $comparedDate
     * @param mixed $referenceDate
     * @return bool
     */
    protected function isGreaterThanOrEqualTo(
        $comparedDate,
        $referenceDate
    ): bool {
        return $this->compare($comparedDate, $referenceDate) >= 0;
    }

    /**
     * Validates that a date is one of a given set of valid choices.
     *
     * @param mixed $date
     * @param array $list
     * @return bool
     */
    protected function isInList($date, array $list): bool
    {
        foreach ($list as $listDate) {
            if ($this->isEqualTo($date, $listDate)) {
                return true;
            }
        }
        return false;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Compares two dates.
     *
     * @param string $date1
     * @param string $date2
     * @return mixed Returns -1 if date1 is less than date2; 1 if date1
     * is greater than date2; 0 if the two dates are equal; or false if either
     * of the dates is invalid.
     */
    protected function compare(string $date1, string $date2)
    {
        $date1 = strtotime($date1);
        $date2 = strtotime($date2);
        if (($date1 === false) || ($date2 === false)) {
            return false;
        } elseif ($date1 < $date2) {
            return -1;
        } elseif ($date1 > $date2) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * @param string $date
     * @return string
     */
    protected function normalize(string $date): string
    {
        $dateFormat = 'Y-m-d';
        $timeFormat = 'H:i:s';
        $format = $dateFormat . ' ' . $timeFormat;
        $time = strtotime($date);
        if (date($timeFormat, $time) === '00:00:00') {
            $format = $dateFormat;
        }
        return date($format, $time);
    }

    /**
     * Validates that a date matches a format.
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    protected function validateFormat(string $date, string $format): bool
    {
        $parsed = date_parse_from_format($format, $date);
        return ($parsed['error_count'] === 0)
            && ($parsed['warning_count'] === 0);
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
            case 'format':
                $error = [
                    /* #lang */ 'validation.date.format',
                    ['format' => $value],
                ];
                break;
            case '=':
            case '==':
            case '===':
                $error = [
                    /* #lang */ 'validation.date.equal_to',
                    ['date' => $this->normalize($value)],
                ];
                break;
            case '!=':
            case '!==':
            case '<>':
                $error = [
                    /* #lang */ 'validation.date.not_equal_to',
                    ['date' => $this->normalize($value)],
                ];
                break;
            case 'after':
            case '>':
                $error = [
                    /* #lang */ 'validation.date.after',
                    ['date' => $this->normalize($value)],
                ];
                break;
            case 'before':
            case '<':
                $error = [
                    /* #lang */ 'validation.date.before',
                    ['date' => $this->normalize($value)],
                ];
                break;
            case '>=':
                $error = [
                    /* #lang */ 'validation.date.after_or_equal_to',
                    ['date' => $this->normalize($value)],
                ];
                break;
            case '<=':
                $error = [
                    /* #lang */ 'validation.date.before_or_equal_to',
                    ['date' => $this->normalize($value)],
                ];
                break;
            case 'range':
                $error = [
                    /* #lang */ 'validation.range',
                    [
                        'min' => $this->normalize($value['min']),
                        'max' => $this->normalize($value['max']),
                    ],
                ];
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
     * {@inheritdoc}
     */
    protected function buildTypeValidationError(string $type): array
    {
        switch ($type) {
            case 'date':
                return [/* #lang */ 'validation.type.date'];
            default:
                return parent::buildTypeValidationError($type);
        }
    }
}

// -- End of file
