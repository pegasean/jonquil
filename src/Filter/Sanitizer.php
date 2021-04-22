<?php

declare(strict_types=1);

namespace Jonquil\Filter;

use InvalidArgumentException;

/**
 * Class Sanitizer
 * @package Jonquil\Filter
 */
class Sanitizer extends Filter
{

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_rule'      => 'Invalid sanitization rule',
        'undefined_filter'  => 'Undefined sanitization filter "%s"',
    ];

    /**
     * Sanitizes a value.
     *
     * @param $value
     * @param string $filter
     * @param array $parameters
     * @return mixed
     */
    public function sanitize($value, string $filter, array $parameters = [])
    {
        $value = (string) $value;
        switch ($filter) {
            case 'integer':
                return $this->sanitizeInteger($value);
            case 'double':
                return $this->sanitizeDouble($value, $parameters);
            case 'string':
                return $this->sanitizeString($value, $parameters);
            case 'email':
                return $this->sanitizeEmail($value);
            case 'url':
                return $this->sanitizeUrl($value);
            case 'url_encoded':
                return $this->urlEncode($value, $parameters);
            case 'special_chars':
                return $this->escapeSpecialChars($value, $parameters);
            default:
                throw new InvalidArgumentException(sprintf(
                    static::$errors['undefined_filter'], $filter
                ));
        }
    }

    /*--------------------------------------------------------------------*/

    /**
     * Removes all characters except digits, plus and minus signs.
     *
     * @param string $value
     * @return string
     */
    protected function sanitizeInteger(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Removes all characters except digits, +- and optionally .,eE.
     *
     * @param string $value
     * @param array $flags
     * @return string
     */
    protected function sanitizeDouble(string $value, array $flags = []): string
    {
        return filter_var(
            $value,
            FILTER_SANITIZE_NUMBER_FLOAT,
            $this->joinFlags($flags)
        );
    }

    /**
     * Strips tags, optionally strips or encodes special characters.
     *
     * @param string $value
     * @param array $flags
     * @return string
     */
    protected function sanitizeString(string $value, array $flags = []): string
    {
        return filter_var(
            $value,
            FILTER_SANITIZE_STRING,
            $this->joinFlags($flags)
        );
    }

    /**
     * Removes all characters except letters, digits
     * and !#$%&'*+-=?^_`{|}~@.[].
     *
     * @param string $value
     * @return string
     */
    protected function sanitizeEmail(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Removes all characters except letters, digits
     * and $-_.+!*'(),{}|\\^~[]`<>#%";/?:@&=.
     *
     * @param string $value
     * @return string
     */
    protected function sanitizeUrl(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_URL);
    }

    /**
     * URL-encodes a string, optionally strips or encodes special characters.
     *
     * @param string $value
     * @param array $flags
     * @return string
     */
    protected function urlEncode(string $value, array $flags = []): string
    {
        return filter_var(
            $value,
            FILTER_SANITIZE_ENCODED,
            $this->joinFlags($flags)
        );
    }

    /**
     * HTML-escapes '"<>& and characters with ASCII value less than 32,
     * optionally strips or encodes other special characters.
     *
     * @param string $value
     * @param array $flags
     * @return string
     */
    protected function escapeSpecialChars(
        string $value,
        array $flags = []
    ): string {
        return filter_var(
            $value,
            FILTER_SANITIZE_SPECIAL_CHARS,
            $this->joinFlags($flags)
        );
    }

    /*--------------------------------------------------------------------*/

    /**
     * {@inheritdoc}
     */
    protected function filter($value, string $filter, array $parameters = [])
    {
        return $this->sanitize($value, $filter, $parameters);
    }

    /**
     * @param array $flags
     * @return mixed
     */
    protected function joinFlags(array $flags): int
    {
        return array_reduce(
            $flags,
            function ($a, $b) {
                return $a | $b;
            },
            0
        );
    }
}
