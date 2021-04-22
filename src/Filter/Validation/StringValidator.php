<?php

declare(strict_types=1);

namespace Jonquil\Filter\Validation;

use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Jonquil\Type\Text;

/**
 * Class StringValidator
 * @package Jonquil\Filter\Validation
 */
class StringValidator extends Validator
{
    // Regular Expressions
    const REGEX_UUID  = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-'
        . '[a-f0-9]{12}$/i';
    const REGEX_SLUG  = '/^[a-z0-9\+_-]+$/';

    /**
     * {@inheritdoc}
     */
    public function validate($value1, string $constraint, $value2 = null): bool
    {
        if (in_array($constraint, ['=*', '==*', '!=*', '<>*', '>*', '>=*',
            '<*', '<=*', 'after*', 'before*'])) {
            $value1 = mb_strtolower($value1);
            $value2 = mb_strtolower($value2);
        }
        switch ($constraint) {
            case 'length':
                return $this->validateLength($value1, $value2);
            case 'byte_length':
                return $this->validateByteLength($value1, $value2);
            case 'format':
                return $this->validateFormat($value1, $value2);
            case 'pattern':
                return $this->matches($value1, $value2);
            case 'contains':
                return $this->contains($value1, $value2, true);
            case 'contains*':
                return $this->contains($value1, $value2, false);
            case 'starts_with':
                return $this->startsWith($value1, $value2, true);
            case 'starts_with*':
                return $this->startsWith($value1, $value2, false);
            case 'ends_with':
                return $this->endsWith($value1, $value2, true);
            case 'ends_with*':
                return $this->endsWith($value1, $value2, false);
            case 'accepted':
                return $this->isAccepted($value1);
            case 'declined':
                return $this->isDeclined($value1);
            case 'after':
            case 'after*':
            case '>*':
                return $this->isGreaterThan($value1, $value2);
            case 'before':
            case 'before*':
            case '<*':
                return $this->isLessThan($value1, $value2);
            case '=*':
            case '==*':
                return $this->isEqualTo($value1, $value2);
            case '!=*':
            case '<>*':
                return $this->isNotEqualTo($value1, $value2);
            case '>=*':
                return $this->isGreaterThanOrEqualTo($value1, $value2);
            case '<=*':
                return $this->isLessThanOrEqualTo($value1, $value2);
            default:
                return parent::validate($value1, $constraint, $value2);
        }
    }

    /*--------------------------------------------------------------------*/

    /**
     * Validates that a string's length is equal to a given value.
     *
     * @param string $text
     * @param int $length
     * @return bool
     */
    protected function hasLength(string $text, int $length): bool
    {
        return (new Text($text))->getLength() === $length;
    }

    /**
     * Validates that a string's length is greater than or equal to
     * a given value.
     *
     * @param string $text
     * @param int $length
     * @return bool
     */
    protected function hasMinLength(string $text, int $length): bool
    {
        return (new Text($text))->getLength() >= $length;
    }

    /**
     * Validates that a string's length is less than or equal to a given value.
     *
     * @param string $text
     * @param int $length
     * @return bool
     */
    protected function hasMaxLength(string $text, int $length): bool
    {
        return (new Text($text))->getLength() <= $length;
    }

    /**
     * Validates that a string's length is between given minimum
     * and maximum values.
     *
     * @param string $text
     * @param int $minLength
     * @param int $maxLength
     * @return bool
     */
    protected function hasLengthBetween(
        string $text,
        int $minLength,
        int $maxLength
    ): bool {
        return $this->hasMinLength($text, $minLength)
            && $this->hasMaxLength($text, $maxLength);
    }

    /**
     * Validates that a string's byte length is equal to a given value.
     *
     * @param string $text
     * @param int $length
     * @return bool
     */
    protected function hasByteLength(string $text, int $length): bool
    {
        return (new Text($text))->getByteLength() === $length;
    }

    /**
     * Validates that a string's byte length is greater than or equal to
     * a given value.
     *
     * @param string $text
     * @param int $length
     * @return bool
     */
    protected function hasMinByteLength(string $text, int $length): bool
    {
        return (new Text($text))->getByteLength() >= $length;
    }

    /**
     * Validates that a string's byte length is less than or equal to
     * a given value.
     *
     * @param string $text
     * @param int $length
     * @return bool
     */
    protected function hasMaxByteLength(string $text, int $length): bool
    {
        return (new Text($text))->getByteLength() <= $length;
    }

    /**
     * Validates that a string's byte length is between given minimum
     * and maximum values.
     *
     * @param string $text
     * @param int $minLength
     * @param int $maxLength
     * @return bool
     */
    protected function hasByteLengthBetween(
        string $text,
        int $minLength,
        int $maxLength
    ): bool {
        return $this->hasMinByteLength($text, $minLength)
            && $this->hasMaxByteLength($text, $maxLength);
    }

    /**
     * Validates that a string's logical value evaluates to true. Specifically,
     * this checks to see if the value is exactly 'true', '1', 'on', or 'yes'
     * (the letter case is ignored), or if it represents a positive integer.
     *
     * @param string $text
     * @return bool
     */
    protected function isAccepted(string $text): bool
    {
        return (new Text($text))->toBoolean() === true;
    }

    /**
     * Validates that a string's logical value evaluates to false. Specifically,
     * this checks to see if the value is exactly 'false', '0', 'off', or 'no'
     * (the letter case is ignored), or if it represents a negative integer.
     *
     * @param string $text
     * @return bool
     */
    protected function isDeclined(string $text): bool
    {
        return !$this->isAccepted($text);
    }

    /**
     * Validates that a string is a valid date.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidDate(string $text): bool
    {
        if (strtotime($text) === false) {
            return false;
        }
        $date = date_parse($text);
        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /**
     * Validates that a string is a valid timezone.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidTimezone(string $text): bool
    {
        try {
            new DateTimeZone($text);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Validates that a string is a valid IP address.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidIpAddress(string $text): bool
    {
        return filter_var($text, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validates that a string is a valid MAC address.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidMacAddress(string $text): bool
    {
        return filter_var($text, FILTER_VALIDATE_MAC) !== false;
    }

    /**
     * Validates that a string is a valid e-mail address.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidEmail(string $text): bool
    {
        return filter_var($text, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates that a string is a valid URL.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidUrl(string $text): bool
    {
        return filter_var($text, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validates that a string contains only alphabetic characters.
     *
     * @param string $text
     * @return bool
     */
    protected function isAlphabetic(string $text): bool
    {
        return (new Text($text))->isAlphabetic();
    }

    /**
     * Validates that a string contains only alphanumeric characters.
     *
     * @param string $text
     * @return bool
     */
    protected function isAlphanumeric(string $text): bool
    {
        return (new Text($text))->isAlphanumeric();
    }

    /**
     * Validates that a string contains only ASCII characters.
     *
     * @param string $text
     * @return bool
     */
    protected function isAscii(string $text): bool
    {
        return (new Text($text))->isAscii();
    }

    /**
     * Validates that a string contains only hexadecimal characters.
     *
     * @param string $text
     * @return bool
     */
    protected function isHexadecimal(string $text): bool
    {
        return (new Text($text))->isHexadecimal();
    }

    /**
     * Validates that a string is base64-encoded.
     *
     * @param string $text
     * @return bool
     */
    protected function isBase64(string $text): bool
    {
        return (new Text($text))->isBase64();
    }

    /**
     * Validates that a string is a valid JSON object.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidJson(string $text): bool
    {
        return (new Text($text))->isValidJson();
    }

    /**
     * Validates that a string is a valid UUID.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidUuid(string $text): bool
    {
        return $this->matches($text, static::REGEX_UUID);
    }

    /**
     * Validates that a string is a valid slug.
     *
     * @param string $text
     * @return bool
     */
    protected function isValidSlug(string $text): bool
    {
        return $this->matches($text, static::REGEX_SLUG);
    }

    /**
     * Validates that a string passes a regular expression check.
     *
     * @param string $text
     * @param string $pattern
     * @return bool
     */
    protected function matches(string $text, string $pattern): bool
    {
        return preg_match($pattern, $text);
    }

    /**
     * Validates that a string contains a substring.
     *
     * @param string $text
     * @param string $needle
     * @param bool $caseSensitive
     * @return bool
     */
    protected function contains(
        string $text,
        string $needle,
        bool $caseSensitive = true
    ): bool {
        return (new Text($text))->contains($needle, $caseSensitive);
    }

    /**
     * Validates that a string starts with a prefix (substring).
     *
     * @param string $text
     * @param string $prefix
     * @param bool $caseSensitive
     * @return bool
     */
    protected function startsWith(
        string $text,
        string $prefix,
        bool $caseSensitive = true
    ): bool {
        return (new Text($text))->startsWith($prefix, $caseSensitive);
    }

    /**
     * Validates that a string ends with a suffix (substring).
     *
     * @param string $text
     * @param string $suffix
     * @param bool $caseSensitive
     * @return bool
     */
    protected function endsWith(
        string $text,
        string $suffix,
        bool $caseSensitive = true
    ): bool {
        return (new Text($text))->endsWith($suffix, $caseSensitive);
    }

    /*--------------------------------------------------------------------*/

    /**
     * {@inheritdoc}
     */
    protected function isEqualTo($comparedValue, $referenceValue): bool
    {
        return strcmp($comparedValue, $referenceValue) === 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function isNotEqualTo($comparedValue, $referenceValue): bool
    {
        return strcmp($comparedValue, $referenceValue) !== 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function isIdenticalTo($comparedValue, $referenceValue): bool
    {
        return $this->isEqualTo($comparedValue, $referenceValue);
    }

    /**
     * {@inheritdoc}
     */
    protected function isNotIdenticalTo($comparedValue, $referenceValue): bool
    {
        return $this->isNotEqualTo($comparedValue, $referenceValue);
    }

    /**
     * {@inheritdoc}
     */
    protected function isLessThan($comparedValue, $referenceValue): bool
    {
        return strcmp($comparedValue, $referenceValue) < 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function isLessThanOrEqualTo(
        $comparedValue,
        $referenceValue
    ): bool {
        return strcmp($comparedValue, $referenceValue) <= 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function isGreaterThan($comparedValue, $referenceValue): bool
    {
        return strcmp($comparedValue, $referenceValue) > 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function isGreaterThanOrEqualTo(
        $comparedValue,
        $referenceValue
    ): bool {
        return strcmp($comparedValue, $referenceValue) >= 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function isInList($value, array $list): bool
    {
        foreach ($list as $listValue) {
            if ($this->isEqualTo($value, $listValue)) {
                return true;
            }
        }
        return false;
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param string $text
     * @param int|array $length
     * @return bool
     */
    protected function validateLength(string $text, int $length): bool
    {
        if (is_int($length)) {
            return $this->hasLength($text, $length);
        } elseif (is_array($length)) {
            if (isset($length['min']) && isset($length['max'])) {
                return $this->hasLengthBetween(
                    $text, $length['min'], $length['max']
                );
            } elseif(isset($length['min'])) {
                return $this->hasMinLength($text, $length['min']);
            } elseif(isset($length['max'])) {
                return $this->hasMaxLength($text, $length['max']);
            }
        }
        throw new InvalidArgumentException(
            static::$errors['invalid_parameter']
        );
    }

    /**
     * @param string $text
     * @param int|array $byteLength
     * @return bool
     */
    protected function validateByteLength(string $text, int $byteLength): bool
    {
        if (is_int($byteLength)) {
            return $this->hasByteLength($text, $byteLength);
        } elseif (is_array($byteLength)) {
            if (isset($byteLength['min']) && isset($byteLength['max'])) {
                return $this->hasByteLengthBetween(
                    $text, $byteLength['min'], $byteLength['max']
                );
            } elseif(isset($byteLength['min'])) {
                return $this->hasMinByteLength($text, $byteLength['min']);
            } elseif(isset($byteLength['max'])) {
                return $this->hasMaxByteLength($text, $byteLength['max']);
            }
        }
        throw new InvalidArgumentException(
            static::$errors['invalid_parameter']
        );
    }

    /**
     * @param string $text
     * @param string $format
     * @return bool
     */
    protected function validateFormat(string $text, string $format): bool
    {
        switch ($format) {
            case 'json':
                return $this->isValidJson($text);
            case 'uuid':
                return $this->isValidUuid($text);
            case 'date':
                return $this->isValidDate($text);
            case 'timezone':
                return $this->isValidTimezone($text);
            case 'email':
                return $this->isValidEmail($text);
            case 'url':
                return $this->isValidUrl($text);
            case 'slug':
                return $this->isValidSlug($text);
            case 'ip_address':
                return $this->isValidIpAddress($text);
            case 'mac_address':
                return $this->isValidMacAddress($text);
            case 'alpha':
                return $this->isAlphabetic($text);
            case 'alpha_numeric':
                return $this->isAlphanumeric($text);
            case 'ascii':
                return $this->isAscii($text);
            case 'hex':
                return $this->isHexadecimal($text);
            case 'base64':
                return $this->isBase64($text);
            default:
                throw new InvalidArgumentException(
                    static::$errors['invalid_parameter']
                );
        }
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
        $constraint = rtrim($constraint, '*');
        switch ($constraint) {
            case 'length':
                $error = $this->buildLengthValidationError($value);
                break;
            case 'byte_length':
                $error = $this->buildByteLengthValidationError($value);
                break;
            case 'format':
                $error = $this->buildFormatValidationError($value);
                break;
            case 'pattern':
                $error = [/* #lang */ 'validation.regex'];
                break;
            case 'contains':
                $error = [
                    /* #lang */ 'validation.string.contains',
                    ['text' => $value],
                ];
                break;
            case 'starts_with':
                $error = [
                    /* #lang */ 'validation.string.starts_with',
                    ['text' => $value],
                ];
                break;
            case 'ends_with':
                $error = [
                    /* #lang */ 'validation.string.ends_with',
                    ['text' => $value],
                ];
                break;
            case 'accepted':
                $error = [/* #lang */ 'validation.string.accepted'];
                break;
            case 'declined':
                $error = [/* #lang */ 'validation.string.declined'];
                break;
            case '=':
            case '==':
            case '===':
                $error = [
                    /* #lang */ 'validation.string.equal_to',
                    ['text' => $value],
                ];
                break;
            case '!=':
            case '!==':
            case '<>':
                $error = [
                    /* #lang */ 'validation.string.not_equal_to',
                    ['text' => $value],
                ];
                break;
            case 'after':
            case '>':
                $error = [
                    /* #lang */ 'validation.string.after',
                    ['text' => $value],
                ];
                break;
            case 'before':
            case '<':
                $error = [
                    /* #lang */ 'validation.string.before',
                    ['text' => $value],
                ];
                break;
            case '>=':
                $error = [
                    /* #lang */ 'validation.string.after_or_equal_to',
                    ['text' => $value],
                ];
                break;
            case '<=':
                $error = [
                    /* #lang */ 'validation.string.before_or_equal_to',
                    ['text' => $value],
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
     * @param int|array $length
     * @return array
     */
    protected function buildLengthValidationError($length): array
    {
        if (is_int($length)) {
            return [
                /* #lang */ 'validation.string.length.equal_to',
                ['chars' => $length],
            ];
        } elseif (is_array($length)) {
            if (isset($length['min']) && isset($length['max'])) {
                return [
                    /* #lang */ 'validation.string.length.between',
                    ['min' => $length['min'], 'max' => $length['max']],
                ];
            } elseif(isset($length['min'])) {
                return [
                    /* #lang */ 'validation.string.length.min',
                    ['min' => $length['min']],
                ];
            } elseif(isset($length['max'])) {
                return [
                    /* #lang */ 'validation.string.length.max',
                    ['max' => $length['max']],
                ];
            }
        }
        return [/* #lang */ 'validation.default'];
    }

    /**
     * @param int|array $byteLength
     * @return array
     */
    protected function buildByteLengthValidationError($byteLength): array
    {
        if (is_int($byteLength)) {
            return [
                /* #lang */ 'validation.string.byte_length.equal_to',
                ['bytes' => $byteLength],
            ];
        } elseif (is_array($byteLength)) {
            if (isset($byteLength['min']) && isset($byteLength['max'])) {
                return [
                    /* #lang */ 'validation.string.byte_length.between',
                    ['min' => $byteLength['min'], 'max' => $byteLength['max']],
                ];
            } elseif(isset($byteLength['min'])) {
                return [
                    /* #lang */ 'validation.string.byte_length.min',
                    ['min' => $byteLength['min']],
                ];
            } elseif(isset($byteLength['max'])) {
                return [
                    /* #lang */ 'validation.string.byte_length.max',
                    ['max' => $byteLength['max']],
                ];
            }
        }
        return [/* #lang */ 'validation.default'];
    }

    /**
     * @param string $format
     * @return array
     */
    protected function buildFormatValidationError(string $format): array
    {
        switch ($format) {
            case 'json':
                return [/* #lang */ 'validation.string.format.json'];
            case 'uuid':
                return [/* #lang */ 'validation.string.format.uuid'];
            case 'date':
                return [/* #lang */ 'validation.string.format.date'];
            case 'timezone':
                return [/* #lang */ 'validation.string.format.timezone'];
            case 'email':
                return [/* #lang */ 'validation.string.format.email'];
            case 'url':
                return [/* #lang */ 'validation.string.format.url'];
            case 'slug':
                return [/* #lang */ 'validation.string.format.slug'];
            case 'ip_address':
                return [/* #lang */ 'validation.string.format.ip_address'];
            case 'mac_address':
                return [/* #lang */ 'validation.string.format.mac_address'];
            case 'alpha':
                return [/* #lang */ 'validation.string.format.alpha'];
            case 'alpha_numeric':
                return [/* #lang */ 'validation.string.format.alpha_numeric'];
            case 'ascii':
                return [/* #lang */ 'validation.string.format.ascii'];
            case 'hex':
                return [/* #lang */ 'validation.string.format.hex'];
            case 'base64':
                return [/* #lang */ 'validation.string.format.base64'];
            default:
                return [/* #lang */ 'validation.default'];
        }
    }
}

// -- End of file
