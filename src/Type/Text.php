<?php

declare(strict_types=1);

namespace Jonquil\Type;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Transliterator;

/**
 * A string manipulation library with multibyte support, based on Stringy:
 * https://github.com/danielstjules/Stringy
 *
 * Copyright (C) 2013 Daniel St. Jules
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * @package Jonquil\Type
 * @author Daniel St. Jules
 */
class Text implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * An instance's string.
     *
     * @var string
     */
    protected $content;

    /**
     * The string's encoding, which should be one of the mbstring module's
     * supported encodings.
     *
     * @var string
     */
    protected $encoding;

    /**
     * Initializes an object and assigns both text and encoding properties
     * the supplied values. $text is cast to a string prior to assignment; if
     * $encoding is not specified, it defaults to mb_internal_encoding(). Throws
     * an InvalidArgumentException if the first argument is an array or object
     * without a __toString method.
     *
     * @param mixed $text Value to modify, after being cast to string
     * @param string $encoding The character encoding
     * @throws InvalidArgumentException if an array or object without a
     *         __toString method is passed as the first argument
     */
    public function __construct($text = '', string $encoding = null)
    {
        if (is_array($text)) {
            throw new InvalidArgumentException(
                'Passed value cannot be an array'
            );
        } elseif (is_object($text) && !method_exists($text, '__toString')) {
            throw new InvalidArgumentException(
                'Passed object must have a __toString method'
            );
        }
        $this->content = (string) $text;
        $this->encoding = empty($encoding) ? mb_internal_encoding() : $encoding;
    }

    /**
     * Returns the length of the string.
     *
     * @return int
     */
    public function getLength(): int
    {
        return mb_strlen($this->content, $this->encoding);
    }

    /**
     * Returns the byte length of the string.
     *
     * @return int
     */
    public function getByteLength(): int
    {
        return mb_strlen($this->content, '8bit');
    }

    /**
     * Returns the encoding used by the Text object.
     *
     * @return string
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Returns the current string value.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->content;
    }

    /**
     * Returns a boolean representation of the given logical string value.
     * For example, 'true', '1', 'on' and 'yes' will return true. 'false', '0',
     * 'off', and 'no' will return false. In all instances, case is ignored.
     * For other numeric strings, their sign will determine the return value.
     * In addition, blank strings consisting of only whitespace will return
     * false. For all other strings, the return value is a result of a
     * boolean cast.
     *
     * @return bool
     */
    public function toBoolean(): bool
    {
        $this->toLowerCase();
        $key = $this->content;
        $map = [
            'true'  => true,
            '1'     => true,
            'on'    => true,
            'yes'   => true,
            'false' => false,
            '0'     => false,
            'off'   => false,
            'no'    => false
        ];
        if (array_key_exists($key, $map)) {
            return $map[$key];
        } elseif (is_numeric($this->content)) {
            return (intval($this->content) > 0);
        }
        $this->replace('[[:space:]]', '');
        return (bool) $this->content;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns true if the string contains only whitespace characters, false
     * otherwise.
     *
     * @return bool
     */
    public function isBlank(): bool
    {
        return $this->matchesPattern('^[[:space:]]*$');
    }

    /**
     * Returns true if the string contains only alphabetic characters, false
     * otherwise.
     *
     * @return bool
     */
    public function isAlphabetic(): bool
    {
        return $this->matchesPattern('^[[:alpha:]]+$');
    }

    /**
     * Returns true if the string contains only alphanumeric characters,
     * false otherwise.
     *
     * @return bool
     */
    public function isAlphanumeric(): bool
    {
        return $this->matchesPattern('^[[:alnum:]]+$');
    }

    /**
     * Returns true if the string contains only ASCII characters,
     * false otherwise.
     *
     * @return bool
     */
    public function isAscii(): bool
    {
        return $this->matchesPattern('^[[:ascii:]]+$');
    }

    /**
     * Returns true if the string contains only hexadecimal characters, false
     * otherwise.
     *
     * @return bool
     */
    public function isHexadecimal(): bool
    {
        return $this->matchesPattern('^[[:xdigit:]]+$');
    }

    /**
     * Returns true if the string is base64-encoded, false otherwise.
     *
     * @return bool
     */
    public function isBase64(): bool
    {
        $decodedString = (string) base64_decode($this->content, true);
        return (base64_encode($decodedString) === $this->content);
    }

    /**
     * Returns true if the string is JSON, false otherwise. Unlike json_decode
     * in PHP 5.x, this method is consistent with PHP 7 and other JSON parsers,
     * in that an empty string is not considered valid JSON.
     *
     * @return bool
     */
    public function isValidJson(): bool
    {
        if (!$this->getLength()) {
            return false;
        }
        json_decode($this->content);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns true if the string contains $needle, false otherwise. By default
     * the comparison is case-sensitive, but can be made insensitive by setting
     * $caseSensitive to false.
     *
     * @param string $needle Substring to look for
     * @param bool $caseSensitive Whether or not to enforce case-sensitivity
     * @return bool
     */
    public function contains(string $needle, bool $caseSensitive = true): bool
    {
        $encoding = $this->encoding;
        if ($caseSensitive) {
            return (mb_strpos($this->content, $needle, 0, $encoding) !== false);
        }
        return (mb_stripos($this->content, $needle, 0, $encoding) !== false);
    }

    /**
     * Returns true if the string contains all $needles, false otherwise. By
     * default the comparison is case-sensitive, but can be made insensitive by
     * setting $caseSensitive to false.
     *
     * @param array $needles Substrings to look for
     * @param bool $caseSensitive Whether or not to enforce case-sensitivity
     * @return bool
     */
    public function containsAll(
        array $needles,
        bool $caseSensitive = true
    ): bool {
        if (empty($needles)) {
            return false;
        }
        foreach ($needles as $needle) {
            if (!$this->contains($needle, $caseSensitive)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if the string contains any $needles, false otherwise. By
     * default the comparison is case-sensitive, but can be made insensitive by
     * setting $caseSensitive to false.
     *
     * @param array $needles Substrings to look for
     * @param bool $caseSensitive Whether or not to enforce case-sensitivity
     * @return bool
     */
    public function containsAny(
        array $needles,
        bool $caseSensitive = true
    ): bool {
        if (empty($needles)) {
            return false;
        }
        foreach ($needles as $needle) {
            if ($this->contains($needle, $caseSensitive)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the number of occurrences of $needle in the given string.
     * By default, the comparison is case-sensitive, but can be made insensitive
     * by setting $caseSensitive to false.
     *
     * @param string $needle The substring to search for
     * @param bool $caseSensitive Whether or not to enforce case-sensitivity
     * @return int
     */
    public function countOccurences(
        string $needle,
        bool $caseSensitive = true
    ): int {
        if ($caseSensitive) {
            return mb_substr_count($this->content, $needle, $this->encoding);
        }
        return mb_substr_count(
            mb_strtoupper($this->content, $this->encoding),
            mb_strtoupper($needle, $this->encoding),
            $this->encoding
        );
    }

    /**
     * Returns the index of the first occurrence of $needle in the string,
     * and false if not found. Accepts an optional offset from which to begin
     * the search.
     *
     * @param string $needle Substring to look for
     * @param int $offset Offset from which to search
     * @return int|bool
     */
    public function getIndexOf(string $needle, int $offset = 0)
    {
        return mb_strpos($this->content, $needle, $offset, $this->encoding);
    }

    /**
     * Returns the index of the last occurrence of $needle in the string,
     * and false if not found. Accepts an optional offset from which to begin
     * the search. Offsets may be negative to count from the last character
     * in the string.
     *
     * @param string $needle Substring to look for
     * @param int $offset Offset from which to search
     * @return int|bool
     */
    public function getIndexOfLast(string $needle, int $offset = 0)
    {
        return mb_strrpos($this->content, $needle, $offset, $this->encoding);
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns a part of the string.
     *
     * @param int $start Position of the first character to use
     * @param int $length Maximum number of characters returned
     * @return string
     */
    public function getSubstring(int $start, int $length = null): string
    {
        return mb_substr($this->content, $start, $length, $this->encoding);
    }

    /**
     * Returns the first $n characters of the string.
     *
     * @param int $n Number of characters to retrieve from the start
     * @return string
     */
    public function getFirst(int $n = 1): string
    {
        if ($n < 1) {
            return '';
        }
        return $this->getSubstring(0, $n);
    }

    /**
     * Returns the last $n characters of the string.
     *
     * @param int $n Number of characters to retrieve from the end
     * @return string
     */
    public function getLast(int $n = 1): string
    {
        if ($n < 1) {
            return '';
        }
        return $this->getSubstring(-$n);
    }

    /**
     * Returns the substring between $start and $end, if found, or an empty
     * string. An optional offset may be supplied from which to begin the
     * search for the start string.
     *
     * @param string $start Delimiter marking the start of the substring
     * @param string $end Delimiter marketing the end of the substring
     * @param int $offset Index from which to begin the search
     * @return string
     */
    public function getBetween(
        string $start,
        string $end,
        int $offset = 0
    ): string {
        $startIndex = $this->getIndexOf($start, $offset);
        if ($startIndex === false) {
            return '';
        }
        $startIndex += mb_strlen($start, $this->encoding);
        $endIndex = $this->getIndexOf($end, $startIndex);
        if ($endIndex === false) {
            return '';
        }
        $length = $endIndex - $startIndex;
        return $this->getSubstring($startIndex, $length);
    }

    /**
     * Returns the character at $index, with indices starting at 0.
     *
     * @param int $index Position of the character
     * @return string
     */
    public function getCharacter(int $index): string
    {
        return $this->getSubstring($index, 1);
    }

    /**
     * Returns an array consisting of all characters in the string.
     *
     * @return array
     */
    public function getCharacters(): array
    {
        $characters = [];
        for ($i = 0, $l = $this->getLength(); $i < $l; $i++) {
            $characters[] = $this->getCharacter($i);
        }
        return $characters;
    }

    /**
     * Returns an array consisting of all unique characters in the string.
     *
     * @return array
     */
    public function getUniqueCharacters(): array
    {
        return array_unique($this->getCharacters());
    }

    /**
     * Splits the string on newlines and carriage returns, returning an
     * array of substrings corresponding to separate lines.
     *
     * @return array
     */
    public function getLines(): array
    {
        return mb_split('[\r\n]{1,2}', $this->content);
    }

    /**
     * Splits the string with the provided regular expression, returning an
     * array of substrings. An optional integer $limit will truncate the
     * results.
     *
     * @param string $pattern The regex with which to split the string
     * @param int $limit Optional maximum number of results to return
     * @return array
     */
    public function split(string $pattern, int $limit = null): array
    {
        if ($limit === 0) {
            return [];
        } elseif ($pattern === '') {
            return [$this->content];
        }
        if ($limit > 0) {
            $limit++;
        } else {
            $limit = -1;
        }
        $regexEncoding = mb_regex_encoding();
        mb_regex_encoding($this->encoding);
        $array = mb_split($pattern, $this->content, $limit);
        mb_regex_encoding($regexEncoding);
        if (($limit > 0) && (count($array) === $limit)) {
            array_pop($array);
        }
        return $array;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns the longest common prefix between the string and $other.
     *
     * @param string $other Second string for comparison
     * @return string
     */
    public function getLongestCommonPrefix(string $other): string
    {
        $encoding = $this->encoding;
        $maxLength = min($this->getLength(), mb_strlen($other, $encoding));
        $longestCommonPrefix = '';
        for ($i = 0; $i < $maxLength; $i++) {
            $char = mb_substr($this->content, $i, 1, $encoding);
            if ($char == mb_substr($other, $i, 1, $encoding)) {
                $longestCommonPrefix .= $char;
            } else {
                break;
            }
        }
        return $longestCommonPrefix;
    }

    /**
     * Returns the longest common suffix between the string and $other.
     *
     * @param string $other Second string for comparison
     * @return string
     */
    public function getLongestCommonSuffix(string $other): string
    {
        $encoding = $this->encoding;
        $maxLength = min($this->getLength(), mb_strlen($other, $encoding));
        $longestCommonSuffix = '';
        for ($i = 1; $i <= $maxLength; $i++) {
            $char = mb_substr($this->content, -$i, 1, $encoding);
            if ($char == mb_substr($other, -$i, 1, $encoding)) {
                $longestCommonSuffix = $char . $longestCommonSuffix;
            } else {
                break;
            }
        }
        return $longestCommonSuffix;
    }

    /**
     * Returns the longest common substring between the string and $other.
     * In the case of ties, it returns that which occurs first.
     *
     * @param string $other Second string for comparison
     * @return string
     */
    public function getLongestCommonSubstring(string $other): string
    {
        // Uses dynamic programming to solve
        // http://en.wikipedia.org/wiki/Longest_common_substring_problem
        $encoding = $this->encoding;
        $text = new self($this->content, $encoding);
        $length = $text->getLength();
        $otherLength = mb_strlen($other, $encoding);
        // Return if either string is empty
        if ($length == 0 || $otherLength == 0) {
            return '';
        }
        $len = 0;
        $end = 0;
        $table = array_fill(0, $length + 1, array_fill(0, $otherLength + 1, 0));
        for ($i = 1; $i <= $length; $i++) {
            for ($j = 1; $j <= $otherLength; $j++) {
                $strChar = mb_substr($text->content, $i - 1, 1, $encoding);
                $otherChar = mb_substr($other, $j - 1, 1, $encoding);
                if ($strChar == $otherChar) {
                    $table[$i][$j] = $table[$i - 1][$j - 1] + 1;
                    if ($table[$i][$j] > $len) {
                        $len = $table[$i][$j];
                        $end = $i;
                    }
                } else {
                    $table[$i][$j] = 0;
                }
            }
        }
        $text->content = mb_substr(
            $text->content,
            $end - $len,
            $len,
            $encoding
        );
        return $text->toString();
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns true if the string begins with $prefix, false otherwise. By
     * default, the comparison is case-sensitive, but can be made insensitive
     * by setting $caseSensitive to false.
     *
     * @param string $prefix The substring to look for
     * @param bool $caseSensitive Whether or not to enforce case-sensitivity
     * @return bool
     */
    public function startsWith(
        string $prefix,
        bool $caseSensitive = true
    ): bool {
        $prefixLength = mb_strlen($prefix, $this->encoding);
        $firstChars = mb_substr(
            $this->content,
            0,
            $prefixLength,
            $this->encoding
        );
        if (!$caseSensitive) {
            $firstChars = mb_strtolower($firstChars, $this->encoding);
            $prefix = mb_strtolower($prefix, $this->encoding);
        }
        return $firstChars === $prefix;
    }

    /**
     * Returns true if the string ends with $suffix, false otherwise. By
     * default, the comparison is case-sensitive, but can be made insensitive
     * by setting $caseSensitive to false.
     *
     * @param string $suffix The substring to look for
     * @param bool $caseSensitive Whether or not to enforce case-sensitivity
     * @return bool
     */
    public function endsWith(
        string $suffix,
        bool $caseSensitive = true
    ): bool {
        $suffixLength = mb_strlen($suffix, $this->encoding);
        $lastChars = mb_substr(
            $this->content,
            $this->getLength() - $suffixLength,
            $suffixLength,
            $this->encoding
        );
        if (!$caseSensitive) {
            $lastChars = mb_strtolower($lastChars, $this->encoding);
            $suffix = mb_strtolower($suffix, $this->encoding);
        }
        return $lastChars === $suffix;
    }

    /**
     * Prepends the string with $prefix.
     *
     * @param string $prefix The text to prepend
     * @return Text
     */
    public function prepend(string $prefix): Text
    {
        $this->content = $prefix . $this->content;
        return $this;
    }

    /**
     * Appends the string with $suffix.
     *
     * @param string $suffix The text to append
     * @return Text
     */
    public function append(string $suffix): Text
    {
        $this->content .= $suffix;
        return $this;
    }

    /**
     * Surrounds the string with the given text.
     *
     * @param string $text The text to add to both sides
     * @return Text
     */
    public function surround(string $text): Text
    {
        $this->prepend($text)->append($text);
        return $this;
    }

    /**
     * Checks whether the string begins with $prefix.
     * If it does not, $prefix is prepended to it.
     *
     * @param string $prefix The text to prepend if not present
     * @return Text
     */
    public function ensurePrefix(string $prefix): Text
    {
        if (!$this->startsWith($prefix)) {
            $this->prepend($prefix);
        }
        return $this;
    }

    /**
     * Checks whether the string ends with $suffix.
     * If it does not, $suffix is appended to it.
     *
     * @param string $suffix The text to append if not present
     * @return Text
     */
    public function ensureSuffix(string $suffix): Text
    {
        if (!$this->endsWith($suffix)) {
            $this->append($suffix);
        }
        return $this;
    }

    /**
     * Removes $prefix from the string, if the string starts with it.
     *
     * @param string $prefix The text to remove if present
     * @return Text
     */
    public function removePrefix(string $prefix): Text
    {
        if ($this->startsWith($prefix)) {
            $prefixLength = mb_strlen($prefix, $this->encoding);
            $this->content = $this->getSubstring($prefixLength);
        }
        return $this;
    }

    /**
     * Removes $suffix from the string, if the string ends with it.
     *
     * @param string $suffix The text to remove if present
     * @return Text
     */
    public function removeSuffix(string $suffix): Text
    {
        if ($this->endsWith($suffix)) {
            $suffixLength = mb_strlen($suffix, $this->encoding);
            $this->content = $this->getSubstring(
                0,
                $this->getLength() - $suffixLength
            );
        }
        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Truncates the string to a given length. If $suffix is provided, and
     * truncating occurs, the string is further truncated so that the suffix
     * may be appended without exceeding the desired length.
     *
     * @param int $length Desired length of the truncated string
     * @param bool $safe Truncate without splitting words (default: false)
     * @param string $suffix The text to append if it can fit
     * @return Text
     */
    public function truncate(
        int $length,
        bool $safe = false,
        string $suffix = ''
    ): Text {
        if ($length >= $this->getLength()) {
            return $this;
        }
        $suffixLength = mb_strlen($suffix, $this->encoding);
        $length = $length - $suffixLength;
        $text = mb_substr($this->content, 0, $length, $this->encoding);
        if ($safe) {
            if (mb_strpos($this->content, ' ', $length - 1, $this->encoding)
                    != $length) {
                // Find pos of the last occurrence of a space, get up to that
                $lastPos = mb_strrpos($text, ' ', 0, $this->encoding);
                $text = mb_substr($text, 0, $lastPos, $this->encoding);
            }
        }
        $this->content = $text . $suffix;
        return $this;
    }

    /**
     * Returns the substring beginning at $start, and up to, but not including
     * the index specified by $end. If $end is omitted, the function extracts
     * the remaining string. If $end is negative, it is computed from the end
     * of the string.
     *
     * @param int $start Initial index from which to begin extraction
     * @param int $end Optional index at which to end extraction
     * @return Text
     */
    public function slice(int $start, int $end = null): Text
    {
        if ($end === null) {
            $length = $this->getLength();
        } elseif ($end >= 0 && $end <= $start) {
            $this->content = '';
            return $this;
        } elseif ($end < 0) {
            $length = $this->getLength() + $end - $start;
        } else {
            $length = $end - $start;
        }
        $this->content = mb_substr(
            $this->content,
            $start,
            $length,
            $this->encoding
        );
        return $this;
    }

    /**
     * Inserts $text into the string at the $index provided.
     *
     * @param string $text String to be inserted
     * @param int $index The index at which to insert the substring
     * @return Text
     */
    public function insert(string $text, int $index): Text
    {
        if ($index > $this->getLength()) {
            return $this;
        }
        $firstChars = mb_substr(
            $this->content,
            0,
            $index,
            $this->encoding
        );
        $lastChars = mb_substr(
            $this->content,
            $index,
            $this->getLength(),
            $this->encoding
        );
        $this->content = $firstChars . $text . $lastChars;
        return $this;
    }

    /**
     * Divides the string into groups of characters separated by
     * the given delimiter.
     *
     * @param int $length The length of each segment
     * @param string $delimiter Sequence used to separate the segments
     * @return Text
     */
    public function segment(int $length, string $delimiter = ' '): Text
    {
        if ($length < 1) {
            return $this;
        }
        $text = '';
        foreach ($this->getCharacters() as $index => $char) {
            if (($index !== 0) && ($index % $length === 0)) {
                $text .= $delimiter;
            }
            $text .= $char;
        }
        $this->content = $text;
        return $this;
    }

    /**
     * Replaces all occurrences of $search in the string by $replacement.
     *
     * @param string $search A string or a regular expression pattern
     * @param string $replacement The string to replace with
     * @param bool $regex If $search is a regular expression (default: true)
     * @param string $options Matching conditions to be used
     * @return Text
     */
    public function replace(
        string $search,
        string $replacement,
        bool $regex = true,
        string $options = 'msr'
    ): Text {
        if (!$regex) {
            $search = preg_quote($search);
        }
        $regexEncoding = mb_regex_encoding();
        mb_regex_encoding($this->encoding);
        $this->content = mb_ereg_replace(
            $search,
            $replacement,
            $this->content,
            $options
        );
        mb_regex_encoding($regexEncoding);
        return $this;
    }

    /**
     * Repeats the string given $n number of times.
     *
     * @param int $n The number of times to repeat the string
     * @return Text
     */
    public function repeat(int $n): Text
    {
        $this->content = str_repeat($this->content, $n);
        return $this;
    }

    /**
     * Reverses the string.
     *
     * @return Text
     */
    public function reverse(): Text
    {
        $text = '';
        for ($i = $this->getLength() - 1; $i >= 0; $i--) {
            $text .= mb_substr($this->content, $i, 1, $this->encoding);
        }
        $this->content = $text;
        return $this;
    }

    /**
     * Shuffles the characters in the string.
     *
     * @return Text
     */
    public function shuffle(): Text
    {
        $indexes = range(0, $this->getLength() - 1);
        shuffle($indexes);
        $text = '';
        foreach ($indexes as $i) {
            $text .= mb_substr($this->content, $i, 1, $this->encoding);
        }
        $this->content = $text;
        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns true if the string contains only upper case characters, false
     * otherwise.
     *
     * @return bool
     */
    public function isUpperCase(): bool
    {
        return $this->matchesPattern('^[[:upper:]]+$');
    }

    /**
     * Returns true if the string contains only lower case characters, false
     * otherwise.
     *
     * @return bool
     */
    public function isLowerCase(): bool
    {
        return $this->matchesPattern('^[[:lower:]]+$');
    }

    /**
     * Returns true if the string contains an upper case character, false
     * otherwise.
     *
     * @return bool
     */
    public function hasUpperCase(): bool
    {
        return $this->matchesPattern('.*[[:upper:]]');
    }

    /**
     * Returns true if the string contains a lower case character, false
     * otherwise.
     *
     * @return bool
     */
    public function hasLowerCase(): bool
    {
        return $this->matchesPattern('.*[[:lower:]]');
    }

    /**
     * Converts all characters in the string to upper case.
     *
     * @return Text
     */
    public function toUpperCase(): Text
    {
        $this->content = mb_strtoupper($this->content, $this->encoding);
        return $this;
    }

    /**
     * Converts all characters in the string to lower case.
     *
     * @return Text
     */
    public function toLowerCase(): Text
    {
        $this->content = mb_strtolower($this->content, $this->encoding);
        return $this;
    }

    /**
     * Converts the first character of each word in the string to upper case.
     *
     * @return Text
     */
    public function toTitleCase(): Text
    {
        $this->content = mb_convert_case(
            $this->content,
            MB_CASE_TITLE,
            $this->encoding
        );
        return $this;
    }

    /**
     * Converts the first character of the string to upper case.
     *
     * @return Text
     */
    public function upperCaseFirst(): Text
    {
        $this->content = mb_strtoupper($this->getFirst(), $this->encoding)
            . $this->getSubstring(1);
        return $this;
    }

    /**
     * Converts the first character of the string to lower case.
     *
     * @return Text
     */
    public function lowerCaseFirst(): Text
    {
        $this->content = mb_strtolower($this->getFirst(), $this->encoding)
            . $this->getSubstring(1);
        return $this;
    }

    /**
     * Converts all upper case characters in the string to lower case, and all
     * lower case characters to upper case.
     *
     * @return Text
     */
    public function swapCase(): Text
    {
        $encoding = $this->encoding;
        $this->content = preg_replace_callback(
            '/[\S]/u',
            function ($match) use ($encoding) {
                if ($match[0] == mb_strtoupper($match[0], $encoding)) {
                    return mb_strtolower($match[0], $encoding);
                }
                return mb_strtoupper($match[0], $encoding);
            },
            $this->content
        );
        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Removes whitespace from the start and end of the string. Supports
     * the removal of unicode whitespace. Accepts an optional string of
     * characters to strip instead of the defaults.
     *
     * @param string $chars Optional string of characters to strip
     * @return Text
     */
    public function trim(string $chars = ''): Text
    {
        $chars = !empty($chars) ? preg_quote($chars) : '[:space:]';
        $this->replace("^[$chars]+|[$chars]+\$", '');
        return $this;
    }

    /**
     * Removes whitespace from the start of the string. Supports the removal
     * of unicode whitespace. Accepts an optional string of characters to strip
     * instead of the defaults.
     *
     * @param string $chars Optional string of characters to strip
     * @return Text
     */
    public function trimLeft(string $chars = ''): Text
    {
        $chars = !empty($chars) ? preg_quote($chars) : '[:space:]';
        $this->replace("^[$chars]+", '');
        return $this;
    }

    /**
     * Removes whitespace from the end of the string. Supports the removal
     * of unicode whitespace. Accepts an optional string of characters to strip
     * instead of the defaults.
     *
     * @param string $chars Optional string of characters to strip
     * @return Text
     */
    public function trimRight(string $chars = ''): Text
    {
        $chars = !empty($chars) ? preg_quote($chars) : '[:space:]';
        $this->replace("[$chars]+\$", '');
        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Replaces smart quotes, ellipsis characters, and dashes from Windows-1252
     * (commonly used in Word documents) with their ASCII equivalents.
     *
     * @return Text
     */
    public function tidy(): Text
    {
        $this->content = preg_replace([
            '/\x{2026}/u',
            '/[\x{201C}\x{201D}]/u',
            '/[\x{2018}\x{2019}]/u',
            '/[\x{2013}\x{2014}]/u',
        ], [
            '...',
            '"',
            "'",
            '-',
        ], $this->content);
        return $this;
    }

    /**
     * Strips all punctuation characters and symbols from the string.
     *
     * @return Text
     */
    public function stripPunctuation()
    {
        $this->replace('[[:punct:]]+', '');
        return $this;
    }

    /**
     * Trims the string and replaces consecutive whitespace characters with a
     * single space. This includes tabs and newline characters, as well as
     * multibyte whitespace such as the thin space and ideographic space.
     *
     * @return Text
     */
    public function compact(): Text
    {
        $this->replace('[[:space:]]+', ' ')->trim();
        return $this;
    }

    /**
     * Converts each tab in the string to some number of spaces, as defined by
     * $tabLength. By default, each tab is converted to 4 consecutive spaces.
     *
     * @param int $tabLength Number of spaces to replace each tab with
     * @return Text
     */
    public function toSpaces(int $tabLength = 4): Text
    {
        $spaces = str_repeat(' ', $tabLength);
        $this->content = str_replace("\t", $spaces, $this->content);
        return $this;
    }

    /**
     * Converts each occurrence of some consecutive number of spaces, as
     * defined by $tabLength, to a tab. By default, each 4 consecutive spaces
     * are converted to a tab.
     *
     * @param int $tabLength Number of spaces to replace with a tab
     * @return Text
     */
    public function toTabs(int $tabLength = 4): Text
    {
        $spaces = str_repeat(' ', $tabLength);
        $this->content = str_replace($spaces, "\t", $this->content);
        return $this;
    }

    /**
     * Converts the string's characters from one script to another. An array
     * with the identifiers of all supported transliterators can be obtained by
     * invoking the Transliterator::listIDs() method.
     *
     * @param string $id The transliterator identifier
     * @return Text
     */
    public function transliterate(string $id): Text
    {
        $translit = Transliterator::create($id);
        if (is_null($translit)) {
            throw new InvalidArgumentException(
                'The transliterator identifier is invalid or unsupported'
            );
        }
        $this->content = $translit->transliterate($this->content);
        return $this;
    }

    /**
     * Converts all applicable characters to HTML entities. An alias of
     * htmlentities. Refer to http://php.net/manual/en/function.htmlentities.php
     * for a list of flags.
     *
     * @param int|null $flags Optional flags
     * @return Text
     */
    public function htmlEncode($flags = ENT_COMPAT): Text
    {
        $this->content = htmlentities(
            $this->content,
            $flags,
            $this->encoding
        );
        return $this;
    }

    /**
     * Converts all HTML entities to their applicable characters. An alias of
     * html_entity_decode. For a list of flags, refer to
     * http://php.net/manual/en/function.html-entity-decode.php
     *
     * @param int|null $flags Optional flags
     * @return Text
     */
    public function htmlDecode($flags = ENT_COMPAT): Text
    {
        $this->content = html_entity_decode(
            $this->content,
            $flags,
            $this->encoding
        );
        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns a lowercase and trimmed string separated by the given delimiter.
     * Delimiters are inserted before uppercase characters (with the exception
     * of the first character of the string), and in place of spaces, dashes,
     * and underscores. Alphabetic delimiters are not converted to lowercase.
     *
     * @param string $delimiter Sequence used to separate parts of the string
     * @return Text
     */
    public function delimit(string $delimiter): Text
    {
        $regexEncoding = mb_regex_encoding();
        mb_regex_encoding($this->encoding);
        $this->trim();
        $this->content = mb_ereg_replace('\B([A-Z])', '-\1', $this->content);
        $this->toLowerCase();
        $this->content = mb_ereg_replace('[-_\s]+', $delimiter, $this->content);
        $this->trim($delimiter);
        mb_regex_encoding($regexEncoding);
        return $this;
    }

    /**
     * Returns a lowercase and trimmed string separated by spaces. Spaces are
     * inserted before uppercase characters (with the exception of the first
     * character of the string), and in place of dashes as well as underscores.
     *
     * @return Text
     */
    public function spacify(): Text
    {
        return $this->delimit(' ');
    }

    /**
     * Returns a lowercase and trimmed string separated by dashes. Dashes are
     * inserted before uppercase characters (with the exception of the first
     * character of the string), and in place of spaces as well as underscores.
     *
     * @return Text
     */
    public function dasherize(): Text
    {
        return $this->delimit('-');
    }

    /**
     * Returns a lowercase and trimmed string separated by underscores.
     * Underscores are inserted before uppercase characters (with the exception
     * of the first character of the string), and in place of spaces as well as
     * dashes.
     *
     * @return Text
     */
    public function underscorize(): Text
    {
        return $this->delimit('_');
    }

    /**
     * Returns a camelCase version of the string. Trims surrounding spaces,
     * capitalizes letters following digits, spaces, dashes and underscores,
     * and removes spaces, dashes, as well as underscores.
     *
     * @return Text
     */
    public function camelize(): Text
    {
        $encoding = $this->encoding;
        $this->trim()->lowerCaseFirst();
        $this->content = preg_replace('/^[-_]+/', '', $this->content);
        $this->content = preg_replace_callback(
            '/[-_\s]+(.)?/u',
            function ($match) use ($encoding) {
                if (isset($match[1])) {
                    return mb_strtoupper($match[1], $encoding);
                }
                return '';
            },
            $this->content
        );
        $this->content = preg_replace_callback(
            '/[\d]+(.)?/u',
            function ($match) use ($encoding) {
                return mb_strtoupper($match[0], $encoding);
            },
            $this->content
        );
        return $this;
    }

    /**
     * Returns an UpperCamelCase version of the supplied string. It trims
     * surrounding spaces, capitalizes letters following digits, spaces, dashes
     * and underscores, and removes spaces, dashes, underscores.
     *
     * @return Text Object with $text in UpperCamelCase
     */
    public function pascalize(): Text
    {
        $this->camelize()->upperCaseFirst();
        return $this;
    }

    /**
     * Returns a trimmed string with the first letter of each word capitalized.
     * Also accepts an array, $ignore, allowing you to list words not to be
     * capitalized.
     *
     * @param array $ignore An array of words not to capitalize
     * @return Text
     */
    public function titleize(array $ignore = []): Text
    {
        $encoding = $this->encoding;
        $this->content = preg_replace_callback(
            '/([\S]+)/u',
            function ($match) use ($encoding, $ignore) {
                if (!empty($ignore) && in_array($match[0], $ignore)) {
                    return $match[0];
                }
                $text = new self($match[0], $encoding);
                return (string) $text->toLowerCase()->upperCaseFirst();
            },
            $this->content
        );
        return $this;
    }

    /**
     * Capitalizes the first word of the string, replaces underscores with
     * spaces, and strips a trailing '_id' if any.
     *
     * @return Text
     */
    public function humanize(): Text
    {
        $this->removeSuffix('_id');
        $this->content = str_replace('_', ' ', $this->content);
        $this->trim()->upperCaseFirst();
        return $this;
    }

    /**
     * Converts the string into an URL slug. This includes transliterating all
     * characters to ASCII, lowercasing them, and replacing whitespace
     * with $delimiter. The replacement defaults to a single dash.
     *
     * @param string $delimiter The string used to replace whitespace
     * @param string $transliteratorId The transliterator identifier
     * @return Text
     */
    public function slugify(
        string $delimiter = '-',
        string $transliteratorId = 'Any-Latin'
    ): Text {
        $transliteratorId .= ';Latin-ASCII';
        $this->transliterate($transliteratorId);
        $pattern = '/[^a-zA-Z\d\s-_' . preg_quote($delimiter) . ']/u';
        $this->content = preg_replace($pattern, '', $this->content);
        $this->delimit($delimiter);
        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Pads both sides of the string to a given length with $padText.
     * If the length is less than or equal to the length of the string,
     * no padding takes places.
     *
     * @param int $length Desired string length after padding
     * @param string $padText String used to pad, defaults to space
     * @return Text
     */
    public function pad(int $length, string $padText = ' '): Text
    {
        $padding = $length - $this->getLength();
        $this->applyPadding(
            (int) floor($padding / 2),
            (int) ceil($padding / 2),
            $padText
        );
        return $this;
    }

    /**
     * Pads the beginning of the string to a given length with $padText.
     * If the length is less than or equal to the length of the string,
     * no padding takes places.
     *
     * @param int $length Desired string length after padding
     * @param string $padText String used to pad, defaults to space
     * @return Text
     */
    public function padLeft(int $length, string $padText = ' '): Text
    {
        $this->applyPadding($length - $this->getLength(), 0, $padText);
        return $this;
    }

    /**
     * Pads the end of the string to a given length with $padText.
     * If the length is less than or equal to the length of the string,
     * no padding takes places.
     *
     * @param int $length Desired string length after padding
     * @param string $padText String used to pad, defaults to space
     * @return Text
     */
    public function padRight(int $length, string $padText = ' '): Text
    {
        $this->applyPadding(0, $length - $this->getLength(), $padText);
        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns the length of the string.
     * Implements the Countable interface.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->getLength();
    }

    /**
     * Returns a new ArrayIterator.
     * Implements the IteratorAggregate interface.
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->getCharacters());
    }

    /**
     * Returns whether or not a character exists at a given index.
     * Implements part of the ArrayAccess interface.
     *
     * @param int $offset The index to check
     * @return bool
     * @throws InvalidArgumentException
     */
    public function offsetExists($offset): bool
    {
        if (!is_int($offset) || ($offset < 0)) {
            throw new InvalidArgumentException(
                'Offset must be a non-negative integer'
            );
        }
        return $this->getLength() > $offset;
    }

    /**
     * Returns the character at the given index.
     * Implements part of the ArrayAccess interface.
     *
     * @param int $offset The index from which to retrieve the character
     * @return string
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new OutOfBoundsException('No character exists at the index');
        }
        return $this->getCharacter($offset);
    }

    /**
     * Replaces a character at the given index.
     * Implements part of the ArrayAccess interface.
     *
     * @param int $offset The index of the character
     * @param string $value A new character to replace it
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     */
    public function offsetSet($offset, $value)
    {
        if (!$this->offsetExists($offset)) {
            throw new OutOfBoundsException('No character exists at the index');
        }
        $value = (string) $value;
        if (mb_strlen($value, $this->encoding) !== 1) {
            throw new InvalidArgumentException(
                'Value must be a single character'
            );
        }
        $this->content = $this->getSubstring(0, $offset) . $value
            . $this->getSubstring($offset + 1);
    }

    /**
     * Deletes a character at the given index.
     * Implements part of the ArrayAccess interface.
     *
     * @param int $offset The index of the character
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     */
    public function offsetUnset($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new OutOfBoundsException('No character exists at the index');
        }
        $this->content = $this->getSubstring(0, $offset)
            . $this->getSubstring($offset + 1);
    }

    /*--------------------------------------------------------------------*/

    /**
     * Returns the current string value.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /*--------------------------------------------------------------------*/

    /**
     * Adds the specified amount of left and right padding to the string.
     * The default character used is a space.
     *
     * @param int $left Length of left padding
     * @param int $right Length of right padding
     * @param string $padText String used to pad
     */
    protected function applyPadding(
        int $left = 0,
        int $right = 0,
        string $padText = ' '
    ) {
        $padTextLength = mb_strlen($padText, $this->encoding);
        $currentLength = $this->getLength();
        $newLength = $currentLength + $left + $right;
        if (!$padTextLength || $newLength <= $currentLength) {
            return;
        }
        $leftPadding = mb_substr(
            str_repeat($padText, (int) ceil($left / $padTextLength)),
            0,
            $left,
            $this->encoding
        );
        $rightPadding = mb_substr(
            str_repeat($padText, (int) ceil($right / $padTextLength)),
            0,
            $right,
            $this->encoding
        );
        $this->content = $leftPadding . $this->content . $rightPadding;
    }

    /**
     * Returns true if the string matches the supplied pattern, false otherwise.
     *
     * @param string $pattern A regular expression pattern to match against
     * @return bool
     */
    protected function matchesPattern(string $pattern): bool
    {
        $regexEncoding = mb_regex_encoding();
        mb_regex_encoding($this->encoding);
        $match = mb_ereg_match($pattern, $this->content);
        mb_regex_encoding($regexEncoding);
        return $match;
    }
}

// -- End of file
