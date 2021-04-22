<?php

declare(strict_types=1);

namespace Jonquil\Filter;

use Jonquil\Type\Text;
use InvalidArgumentException;

/**
 * Class Formatter
 * @package Jonquil\Filter
 */
class Formatter extends Filter
{

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_rule'              => 'Invalid formatting rule',
        'undefined_data_type'       => 'Undefined data type "%s"',
        'undefined_filter'          => 'Undefined formatting filter "%s"',
        'undefined_round_mode'      => 'Undefined rounding mode "%s"',
        'different_number_signs'    => 'Different number signs',
    ];

    /**
     * @var array
     */
    protected static $textFilters = [
        'upper_case',
        'lower_case',
        'title_case',
        'upper_case_first',
        'lower_case_first',
        'swap_case',
        'prepend',
        'append',
        'surround',
        'ensure_prefix',
        'ensure_suffix',
        'remove_prefix',
        'remove_suffix',
        'truncate',
        'trim',
        'trim_left',
        'trim_right',
        'tidy',
        'strip_punctuation',
        'compact',
        'transliterate',
        'segment',
        'spacify',
        'dasherize',
        'underscorize',
        'camelize',
        'pascalize',
        'titleize',
        'humanize',
        'slugify',
        'pad',
        'pad_left',
        'pad_right',
    ];

    /**
     * Formats a value.
     *
     * @param $value
     * @param string $filter
     * @param array $parameters
     * @return mixed
     */
    public function format($value, string $filter, array $parameters = [])
    {
        switch ($filter) {
            case 'type':
                $method = 'convertType';
                break;
            case 'round':
                $method = 'round';
                break;
            case 'mround':
                $method = 'roundToMultiple';
                break;
            case 'ceiling':
                $method = 'roundCeiling';
                break;
            case 'floor':
                $method = 'roundFloor';
                break;
            case 'number':
                $method = 'formatNumber';
                break;
            default:
                $method = 'formatString';
                $parameters = [$filter, $parameters];
        }
        if (in_array($filter, ['number', 'round', 'mround',
            'ceiling', 'floor'])) {
            $value = (float) $value;
        }
        array_unshift($parameters, $value);
        return call_user_func_array([$this, $method], $parameters);
    }

    /*--------------------------------------------------------------------*/

    /**
     * Converts a value to a given type.
     *
     * @param $value
     * @param string $type
     * @return mixed
     */
    protected function convertType($value, string $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return (integer) $value;
            case 'bool':
            case 'boolean':
                return (boolean) $value;
            case 'real':
            case 'float':
            case 'double':
                return (double) $value;
            case 'string':
                return (string) $value;
            case 'array':
                return (array) $value;
            case 'object':
                return (object) $value;
            default:
                throw new InvalidArgumentException(sprintf(
                    static::$errors['undefined_data_type'], $type
                ));
        }
    }

    /**
     * Rounds a floating-point number.
     *
     * @param float $number
     * @param int $precision
     * @param string $mode
     * @return float
     */
    protected function round(
        float $number,
        int $precision = 0,
        string $mode = 'half_up'
    ): float {
        switch ($mode) {
            case 'half_up':
                return round($number, $precision, PHP_ROUND_HALF_UP);
            case 'half_down':
                return round($number, $precision, PHP_ROUND_HALF_DOWN);
            case 'half_even':
                return round($number, $precision, PHP_ROUND_HALF_EVEN);
            case 'half_odd':
                return round($number, $precision, PHP_ROUND_HALF_ODD);
            case 'up':
                return $this->roundUp($number, $precision);
            case 'down':
                return $this->roundDown($number, $precision);
            default:
                throw new InvalidArgumentException(sprintf(
                    static::$errors['undefined_round_mode'], $mode
                ));
        }
    }

    /**
     * Rounds a number up to a specified number of decimal places.
     *
     * @param float $number
     * @param int $precision
     * @return float
     */
    protected function roundUp(float $number, int $precision = 0): float
    {
        $significance = pow(10, $precision);
        if ($number < 0.0) {
            return floor($number * $significance) / $significance;
        } else {
            return ceil($number * $significance) / $significance;
        }
    }

    /**
     * Rounds a number down to a specified number of decimal places.
     *
     * @param float $number
     * @param int $precision
     * @return float
     */
    protected function roundDown(float $number, int $precision = 0): float
    {
        $significance = pow(10, $precision);
        if ($number < 0.0) {
            return ceil($number * $significance) / $significance;
        } else {
            return floor($number * $significance) / $significance;
        }
    }

    /**
     * Rounds a number to the nearest multiple of a specified value.
     *
     * @param float $number
     * @param float $multiple
     * @return float
     */
    protected function roundToMultiple(float $number, float $multiple): float
    {
        if ($multiple === 0.0) {
            return 0.0;
        }
        if ($this->testSign($number) != $this->testSign($multiple)) {
            throw new InvalidArgumentException(
                static::$errors['different_number_signs']
            );
        }
        $multiplier = 1 / $multiple;
        return round($number * $multiplier) / $multiplier;
    }

    /**
     * Rounds a number up, away from zero, to the nearest multiple
     * of significance.
     *
     * @param float $number
     * @param float $significance
     * @return float
     */
    protected function roundCeiling(
        float $number,
        float $significance = null
    ): float {
        if (($number === 0.0) || ($significance === 0.0)) {
            return 0.0;
        }
        if (is_null($significance)) {
            $significance = $number / abs($number);
        }

        if ($this->testSign($number) != $this->testSign($significance)) {
            throw new InvalidArgumentException(
                static::$errors['different_number_signs']
            );
        }
        return ceil($number / $significance) * $significance;
    }

    /**
     * Rounds a number down, toward zero, to the nearest multiple
     * of significance.
     *
     * @param float $number
     * @param float $significance
     * @return float
     */
    protected function roundFloor(
        float $number,
        float $significance = null
    ): float {
        if (($number === 0.0) || ($significance === 0.0)) {
            return 0.0;
        }
        if (is_null($significance)) {
            $significance = $number / abs($number);
        }

        if ($this->testSign($number) != $this->testSign($significance)) {
            throw new InvalidArgumentException(
                static::$errors['different_number_signs']
            );
        }
        return floor($number / $significance) * $significance;
    }

    /**
     * Determines the sign of a number. Returns 1 if the number is positive,
     * zero (0) if the number is 0, and -1 if the number is negative.
     *
     * @param float $number
     * @return int
     */
    protected function testSign(float $number): int
    {
        if ($number === 0.0) {
            return 0;
        }
        return (int) ($number / abs($number));
    }

    /**
     * Formats a number with grouped thousands.
     *
     * @param float $number
     * @param int $decimals
     * @param string $decimalPoint
     * @param string $thousandsSeparator
     * @return string
     */
    protected function formatNumber(
        float $number,
        int $decimals = 0,
        string $decimalPoint = '.',
        string $thousandsSeparator = ','
    ): string
    {
        return number_format(
            $number,
            $decimals,
            $decimalPoint,
            $thousandsSeparator
        );
    }

    /**
     * Formats a string.
     *
     * @param $value
     * @param string $filter
     * @param array $parameters
     * @return string
     */
    protected function formatString(
        $value,
        string $filter,
        array $parameters = []
    ): string {
        if (!in_array($filter, static::$textFilters)) {
            throw new InvalidArgumentException(sprintf(
                static::$errors['undefined_filter'], $filter
            ));
        }
        if (in_array($filter, ['upper_case', 'lower_case', 'title_case'])) {
            $filter = 'to_' . $filter;
        }
        $text = new Text($value);
        $method = (new Text($filter))->pascalize()->toString();
        return (string) call_user_func_array([$text, $method], $parameters);
    }

    /*--------------------------------------------------------------------*/

    /**
     * {@inheritdoc}
     */
    protected function filter($value, string $filter, array $parameters = [])
    {
        return $this->format($value, $filter, $parameters);
    }
}
