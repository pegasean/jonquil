<?php

declare(strict_types=1);

namespace Jonquil\Filter\Validation;

use Jonquil\Text\Translator;
use Countable;
use Exception;
use InvalidArgumentException;

/**
 * Class Validator
 * @package Jonquil\Filter\Validation
 */
class Validator
{
    const VALIDATION_MODIFIERS = ['any', 'all', 'none'];

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_regex'         => 'Invalid regular expression',
        'invalid_criterion'     => 'Invalid validation criterion',
        'invalid_parameter'     => 'Invalid validation criterion parameter',
        'undefined_constraint'  => 'Undefined validation constraint "%s"',
        'undefined_modifier'    => 'Undefined validation modifier "%s"',
    ];

    /**
     * @var array
     */
    protected $criteria = [];

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $validationErrors = [];

    /**
     * Initializes the class properties.
     *
     * @param array $criteria
     */
    public function __construct(array $criteria = [])
    {
        foreach ($criteria as $criterion) {
            $this->addCriterion($criterion);
        }
    }

    /**
     * @param string|null $attribute
     * @return array
     */
    public function getCriteria(string $attribute = null): array
    {
        if (is_null($attribute)) {
            return $this->criteria;
        }
        $criteria = [];
        foreach ($this->criteria as $criterion) {
            if ($criterion[0] === $attribute) {
                $criteria[] = $criterion;
            }
        }
        return $criteria;
    }

    /**
     * @param array $criterion
     */
    public function addCriterion(array $criterion)
    {
        $elementCount = count($criterion);
        if (($elementCount < 2) || ($elementCount > 4)) {
            throw new InvalidArgumentException(
                self::$errors['invalid_criterion']
            );
        }

        list($attribute, $constraint) = $criterion;

        if (is_array($attribute)) {
            foreach ($attribute as $attr) {
                $subcriterion = array_values($criterion);
                $subcriterion[0] = $attr;
                $this->addCriterion($subcriterion);
            }
        } else {
            if (!is_string($attribute) || !is_string($constraint)
                || empty($attribute) || empty($constraint)) {
                throw new InvalidArgumentException(
                    self::$errors['invalid_criterion']
                );
            }
            if (!isset($this->attributes[$attribute])) {
                $this->attributes[$attribute] = 'undefined';
            }
            if ($constraint === 'type') {
                $this->attributes[$attribute] = $criterion[2];
            }
            $this->insertCriterion($criterion);
        }
    }

    /**
     * @param Translator $text
     * @param array $attributeLabels
     * @param bool $highlight
     * @return array
     */
    public function getValidationErrors(
        Translator $text = null,
        array $attributeLabels = [],
        bool $highlight = false
    ): array {
        if (is_null($text)) {
            return $this->validationErrors;
        }
        $validationErrors = [];
        foreach ($this->validationErrors as $attribute => $errors) {
            $validationErrors[$attribute] = [];
            $attributeLabel = $attributeLabels[$attribute] ?? $attribute;
            foreach ($errors as $error) {
                $errorKey = array_shift($error);
                $replacements = array_shift($error);
                $replacements['attribute'] = $attributeLabel;
                if ($highlight) {
                    $replacements = array_map(
                        function ($value) {
                            return '<b>' . $value . '</b>';
                        },
                        $replacements
                    );
                }
                $validationErrors[$attribute][] = $text->interpolate(
                    $errorKey,
                    $replacements
                );
            }
        }
        return $validationErrors;
    }

    /**
     * Validates a value.
     *
     * @param $value1
     * @param string $constraint
     * @param $value2
     * @return bool
     */
    public function validate($value1, string $constraint, $value2 = null): bool
    {
        switch ($constraint) {
            // Basic Constraints
            case 'type':
                return $this->isOfType($value1, $value2);
            case 'null':
                return $this->isNull($value1);
            case 'not_null':
                return $this->isNotNull($value1);
            case 'empty':
                return $this->isEmpty($value1);
            case 'not_empty':
                return $this->isNotEmpty($value1);
            case 'true':
                return $this->isTrue($value1);
            case 'false':
                return $this->isFalse($value1);

            // Comparison Constraints
            case '=':
            case '==':
                return $this->isEqualTo($value1, $value2);
            case '!=':
            case '<>':
                return $this->isNotEqualTo($value1, $value2);
            case '===':
                return $this->isIdenticalTo($value1, $value2);
            case '!==':
                return $this->isNotIdenticalTo($value1, $value2);
            case '>':
                return $this->isGreaterThan($value1, $value2);
            case '>=':
                return $this->isGreaterThanOrEqualTo($value1, $value2);
            case '<':
                return $this->isLessThan($value1, $value2);
            case '<=':
                return $this->isLessThanOrEqualTo($value1, $value2);

            // Pattern Matching Constraints
            case '~':
                return $this->isLike($value1, $value2, true);
            case '~*':
                return $this->isLike($value1, $value2, false);
            case '!~':
                return $this->isNotLike($value1, $value2, true);
            case '!~*':
                return $this->isNotLike($value1, $value2, false);

            // Complex Constraints
            case 'in':
                return $this->isInList($value1, $value2);
            case 'not_in':
                return $this->isNotInList($value1, $value2);
            case 'any':
                return $this->validateMultiple($value1, $value2, 'any');
            case 'all':
                return $this->validateMultiple($value1, $value2, 'all');
            case 'none':
                return $this->validateMultiple($value1, $value2, 'none');

            // Other Constraints
            case 'range':
                return $this->validateRange($value1, $value2);
            case 'callback':
                return $this->validateCallback($value1, $value2);

            // Undefined Constraint
            default:
                throw new InvalidArgumentException(sprintf(
                    static::$errors['undefined_constraint'], $constraint
                ));
        }
    }

    /**
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function test(array $data): bool
    {
        $isValid = true;
        $this->validationErrors = [];
        $skipAttributes = [];
        foreach ($this->criteria as $criterion) {
            list($key, $constraint) = $criterion;
            $parameters = $criterion[2] ?? null;
            $options = $criterion[3] ?? [];
            if (!is_array($options)) {
                $options = [];
            }

            if (in_array($key, $skipAttributes)) {
                continue;
            } elseif (!array_key_exists($key, $data)) {
                if ($constraint === 'required') {
                    $this->addValidationError($key, $constraint);
                    $isValid = false;
                    $skipAttributes[] = $key;
                }
                continue;
            }

            if ($constraint === 'required') {
                $constraint = 'not_empty';
            }

            if (!$this->validate($data[$key], $constraint, $parameters)) {
                $this->addValidationError(
                    $key,
                    $constraint,
                    $parameters,
                    $options
                );
                $isValid = false;
                if (in_array($constraint, ['type', 'not_empty'])) {
                    $skipAttributes[] = $key;
                }
            }
        }
        return $isValid;
    }

    /*--------------------------------------------------------------------
       Basic Constraints
      --------------------------------------------------------------------*/

    /**
     * Validates that a value is of a specific data type.
     *
     * @param mixed $value
     * @param string $type
     * @return bool
     */
    protected function isOfType($value, string $type): bool
    {
        switch ($type) {
            case 'array':
                return is_array($value);
            case 'boolean':
                return is_bool($value);
            case 'callable':
                return is_callable($value);
            case 'double':
                return is_double($value);
            case 'integer':
                return is_integer($value);
            case 'null':
                return is_null($value);
            case 'numeric':
                return is_numeric($value);
            case 'object':
                return is_object($value);
            case 'resource':
                return is_resource($value);
            case 'scalar':
                return is_scalar($value);
            case 'string':
                return is_string($value);
            default:
                return false;
        }
    }

    /**
     * Validates that a value is exactly equal to null.
     *
     * @param mixed $value
     * @return bool
     */
    protected function isNull($value): bool
    {
        return is_null($value);
    }

    /**
     * Validates that a value is not strictly equal to null.
     *
     * @param mixed $value
     * @return bool
     */
    protected function isNotNull($value): bool
    {
        return !is_null($value);
    }

    /**
     * Validates that a value is empty, defined as equal to an empty string,
     * an empty array (or Countable object), or equal to null.
     *
     * @param mixed $value
     * @return bool
     */
    protected function isEmpty($value): bool
    {
        if (is_null($value)) {
            return true;
        } elseif (is_string($value) && ($value === '')) {
            return true;
        } elseif ((is_array($value) || $value instanceof Countable)
            && count($value) < 1) {
            return true;
        }

        return false;
    }

    /**
     * Validates that a value is not empty, defined as not equal to an empty
     * string, an empty array (or Countable object), and not equal to null.
     *
     * @param mixed $value
     * @return bool
     */
    protected function isNotEmpty($value): bool
    {
        return !$this->isEmpty($value);
    }

    /**
     * Validates that a value is true. Specifically, this checks to see if the
     * value is exactly true, exactly the integer 1, or exactly the string "1".
     *
     * @param mixed $value
     * @return bool
     */
    protected function isTrue($value): bool
    {
        if (is_bool($value)) {
            return $value === true;
        } elseif (is_int($value)) {
            return $value === 1;
        } elseif (is_string($value)) {
            return $value === '1';
        }

        return false;
    }

    /**
     * Validates that a value is false. Specifically, this checks to see if the
     * value is exactly false, exactly the integer 0, or exactly the string "0".
     *
     * @param mixed $value
     * @return bool
     */
    protected function isFalse($value): bool
    {
        if (is_bool($value)) {
            return $value === false;
        } elseif (is_int($value)) {
            return $value === 0;
        } elseif (is_string($value)) {
            return $value === '0';
        }

        return false;
    }

    /*--------------------------------------------------------------------
       Comparison Constraints
      --------------------------------------------------------------------*/

    /**
     * Validates that a value is equal to another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isEqualTo($comparedValue, $referenceValue): bool
    {
        return $comparedValue == $referenceValue;
    }

    /**
     * Validates that a value is not equal to another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isNotEqualTo($comparedValue, $referenceValue): bool
    {
        return $comparedValue != $referenceValue;
    }

    /**
     * Validates that a value is identical to another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isIdenticalTo($comparedValue, $referenceValue): bool
    {
        return $comparedValue === $referenceValue;
    }

    /**
     * Validates that a value is not identical to another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isNotIdenticalTo($comparedValue, $referenceValue): bool
    {
        return $comparedValue !== $referenceValue;
    }

    /**
     * Validates that a value is less than another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isLessThan($comparedValue, $referenceValue): bool
    {
        return $comparedValue < $referenceValue;
    }

    /**
     * Validates that a value is less than or equal to another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isLessThanOrEqualTo(
        $comparedValue,
        $referenceValue
    ): bool {
        return $comparedValue <= $referenceValue;
    }

    /**
     * Validates that a value is greater than another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isGreaterThan($comparedValue, $referenceValue): bool
    {
        return $comparedValue > $referenceValue;
    }

    /**
     * Validates that a value is greater than or equal to another value.
     *
     * @param mixed $comparedValue
     * @param mixed $referenceValue
     * @return bool
     */
    protected function isGreaterThanOrEqualTo(
        $comparedValue,
        $referenceValue
    ): bool {
        return $comparedValue >= $referenceValue;
    }

    /*--------------------------------------------------------------------
       Pattern Matching Constraints
      --------------------------------------------------------------------*/

    /**
     * Validates that a value matches a given POSIX regular expression.
     *
     * @param mixed $value
     * @param string $pattern
     * @param bool $caseSensitive
     * @return bool
     */
    protected function isLike(
        $value,
        string $pattern,
        bool $caseSensitive = true
    ): bool {
        $value = (string) $value;
        $pattern = '`' . $pattern . '`u';
        if (!$caseSensitive) {
            $pattern .= 'i';
        }
        $matchStatus = @preg_match($pattern, $value);
        if ($matchStatus === false) {
            throw new InvalidArgumentException(
                self::$errors['invalid_regex']
            );
        }
        return (boolean) $matchStatus;
    }

    /**
     * Validates that a value does not match a given POSIX regular expression.
     *
     * @param mixed $value
     * @param string $pattern
     * @param bool $caseSensitive
     * @return bool
     */
    protected function isNotLike(
        $value,
        string $pattern,
        bool $caseSensitive = true
    ): bool {
        return !$this->isLike($value, $pattern, $caseSensitive);
    }

    /*--------------------------------------------------------------------
       Complex Constraints
      --------------------------------------------------------------------*/

    /**
     * Validates that a value is one of a given set of valid choices.
     *
     * @param mixed $value
     * @param array $list
     * @return bool
     */
    protected function isInList($value, array $list): bool
    {
        return in_array($value, $list, true);
    }

    /**
     * Validates that a value is not any of a given set of invalid choices.
     *
     * @param mixed $value
     * @param array $list
     * @return bool
     */
    protected function isNotInList($value, array $list): bool
    {
        return !$this->isInList($value, $list);
    }

    /**
     * Validates that a value is between two other values.
     *
     * @param mixed $comparedValue
     * @param mixed $minValue
     * @param mixed $maxValue
     * @return bool
     */
    protected function isBetween($comparedValue, $minValue, $maxValue): bool
    {
        return $this->isGreaterThanOrEqualTo($comparedValue, $minValue)
            && $this->isLessThanOrEqualTo($comparedValue, $maxValue);
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param string|null $type
     * @return array
     */
    protected function getCriteriaAttributes(string $type = null): array
    {
        if (is_null($type)) {
            return array_keys($this->attributes);
        }
        $attributes = [];
        foreach ($this->attributes as $attribute => $attrType) {
            if ($attrType === $type) {
                $attributes[] = $attribute;
            }
        }
        return $attributes;
    }

    /**
     * @param array $criterion
     */
    protected function insertCriterion(array $criterion)
    {
        $constraint = $criterion[1];
        $index = null;

        if (in_array($constraint, ['type', 'required'])) {
            $index = -1;
            foreach ($this->criteria as $i => $c) {
                if ($c[1] === 'required') {
                    $index = $i;
                } elseif (($c[1] === 'type')
                    && ($constraint === 'type')) {
                    $index = $i;
                } else {
                    break;
                }
            }
            $index++;
        }
        if (is_null($index)) {
            $this->criteria[] = $criterion;
        } else {
            array_splice($this->criteria, $index, 0, [$criterion]);
        }
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param $comparedValue
     * @param array $conditions
     * @param string $modifier
     * @return bool
     */
    protected function validateMultiple(
        $comparedValue,
        array $conditions,
        string $modifier
    ): bool
    {
        if (!in_array($modifier, self::VALIDATION_MODIFIERS)) {
            throw new InvalidArgumentException(sprintf(
                static::$errors['undefined_modifier'], $modifier
            ));
        }
        if (isset($conditions['constraint']) && isset($conditions['values'])) {
            $constraint = $conditions['constraint'];
            $values = $conditions['values'];
            if (is_string($constraint) && is_array($values)) {
                $conditions = [];
                foreach ($values as $value) {
                    $conditions[] = [$constraint, $value];
                }
            }
        }
        if (empty($conditions)) {
            throw new InvalidArgumentException(
                static::$errors['invalid_parameter']
            );
        }
        foreach ($conditions as $condition) {
            if (!is_array($condition) || count($condition) !== 2) {
                throw new InvalidArgumentException(
                    static::$errors['invalid_parameter']
                );
            }
            list($constraint, $referenceValue) = $condition;
            if (!is_string($constraint)) {
                throw new InvalidArgumentException(
                    static::$errors['invalid_parameter']
                );
            }
            if ($this->validate($comparedValue, $constraint, $referenceValue)) {
                if ($modifier === 'any') {
                    return true;
                } elseif ($modifier === 'none') {
                    return false;
                }
            } else {
                if ($modifier === 'all') {
                    return false;
                }
            }
        }
        if ($modifier === 'any') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $comparedValue
     * @param array $range
     * @return bool
     */
    protected function validateRange($comparedValue, array $range): bool
    {
        if (!isset($range['min']) || !isset($range['max'])) {
            throw new InvalidArgumentException(
                static::$errors['invalid_parameter']
            );
        }
        return $this->isBetween($comparedValue, $range['min'], $range['max']);
    }

    /**
     * @param $value
     * @param callable $callback
     * @return bool
     */
    protected function validateCallback($value, callable $callback): bool
    {
        return (bool) call_user_func($callback, $value);
    }

    /**
     * @param string $attribute
     * @param string $constraint
     * @param null $value
     * @param array $options
     */
    protected function addValidationError(
        string $attribute,
        string $constraint,
        $value = null,
        array $options = []
    ) {
        $error = [/* #lang */ 'validation.default'];
        switch ($constraint) {
            // Basic Constraints
            case 'type':
                $error = $this->buildTypeValidationError($value);
                break;
            case 'null':
                $error = [/* #lang */ 'validation.null'];
                break;
            case 'not_null':
                $error = [/* #lang */ 'validation.not_null'];
                break;
            case 'empty':
                $error = [/* #lang */ 'validation.empty'];
                break;
            case 'not_empty':
                $error = [/* #lang */ 'validation.not_empty'];
                break;
            case 'true':
                $error = [/* #lang */ 'validation.true'];
                break;
            case 'false':
                $error = [/* #lang */ 'validation.false'];
                break;
            case 'required':
                $error = [/* #lang */ 'validation.required'];
                break;

            // Comparison Constraints
            case '=':
            case '==':
                $error = [
                    /* #lang */ 'validation.equal_to',
                    ['value' => $value],
                ];
                break;
            case '!=':
            case '<>':
                $error = [
                    /* #lang */ 'validation.not_equal_to',
                    ['value' => $value],
                ];
                break;
            case '===':
                $error = [
                    /* #lang */ 'validation.identical_to',
                    ['value' => $value],
                ];
                break;
            case '!==':
                $error = [
                    /* #lang */ 'validation.not_identical_to',
                    ['value' => $value],
                ];
                break;
            case '>':
                $error = [
                    /* #lang */ 'validation.greater_than',
                    ['value' => $value],
                ];
                break;
            case '>=':
                $error = [
                    /* #lang */ 'validation.greater_than_or_equal_to',
                    ['value' => $value],
                ];
                break;
            case '<':
                $error = [
                    /* #lang */ 'validation.less_than',
                    ['value' => $value],
                ];
                break;
            case '<=':
                $error = [
                    /* #lang */ 'validation.less_than_or_equal_to',
                    ['value' => $value],
                ];
                break;

            // Pattern Matching Constraints
            case '~':
            case '~*':
            case '!~':
            case '!~*':
                $error = [/* #lang */ 'validation.regex'];
                break;

            // Complex Constraints
            case 'range':
                $error = [
                    /* #lang */ 'validation.range',
                    ['min' => $value['min'], 'max' => $value['max']],
                ];
                break;
        }
        if (isset($options['message'])) {
            $error[0] = $options['message'];
        }
        $this->validationErrors[$attribute][] = $error;
    }

    /**
     * @param string $type
     * @return array
     */
    protected function buildTypeValidationError(string $type): array
    {
        switch ($type) {
            case 'array':
                return [/* #lang */ 'validation.type.array'];
            case 'boolean':
                return [/* #lang */ 'validation.type.boolean'];
            case 'callable':
                return [/* #lang */ 'validation.type.callable'];
            case 'double':
                return [/* #lang */ 'validation.type.double'];
            case 'integer':
                return [/* #lang */ 'validation.type.integer'];
            case 'null':
                return [/* #lang */ 'validation.type.null'];
            case 'numeric':
                return [/* #lang */ 'validation.type.numeric'];
            case 'object':
                return [/* #lang */ 'validation.type.object'];
            case 'resource':
                return [/* #lang */ 'validation.type.resource'];
            case 'scalar':
                return [/* #lang */ 'validation.type.scalar'];
            case 'string':
                return [/* #lang */ 'validation.type.string'];
            default:
                return [/* #lang */ 'validation.type.default'];
        }
    }
}

// -- End of file
