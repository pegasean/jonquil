<?php

declare(strict_types=1);

namespace Jonquil\Text;

use Jonquil\Type\Text;
use InvalidArgumentException;
use LogicException;

/**
 * Class TextGenerator
 * @package Jonquil\Text
 */
class TextGenerator
{
    /**
     * Default string length
     */
    const DEFAULT_LENGTH = 32;

    /**
     * ASCII punctuation marks and symbols
     */
    const PUNCTUATION_CHARSET = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

    /**
     * ASCII numeric characters
     */
    const NUMERIC_CHARSET = '0123456789';

    /**
     * ASCII uppercase alphabetic characters
     */
    const UC_ALPHA_CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * ASCII lowercase alphabetic characters
     */
    const LC_ALPHA_CHARSET = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * ASCII alphabetic characters
     */
    const ALPHA_CHARSET = self::UC_ALPHA_CHARSET . self::LC_ALPHA_CHARSET;

    /**
     * ASCII alphanumeric characters
     */
    const ALPHANUMERIC_CHARSET = self::ALPHA_CHARSET . self::NUMERIC_CHARSET;

    /**
     * ASCII printable characters
     */
    const ASCII_PRINTABLE_CHARSET = self::ALPHANUMERIC_CHARSET
        . self::PUNCTUATION_CHARSET;

    /**
     * Generates a random string
     *
     * @param int $length The length of the random string
     * @param string $charset A set of all possible characters
     * @return string
     */
    public function generate(
        int $length = self::DEFAULT_LENGTH,
        string $charset = self::ALPHANUMERIC_CHARSET
    ): string {
        if ($length < 1) {
            return '';
        }

        $chars = (new Text($charset))->getUniqueCharacters();
        $charset = new Text(implode('', $chars));

        if ($charset->count() < 2) {
            throw new LogicException(
                'The charset should contain at least 2 distinct characters'
            );
        }

        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $charset->count() - 1);
            $string .= $charset->getCharacter($index);
        }

        return $string;
    }

    /**
     * Generates a random string comprised of printable ASCII characters
     *
     * @param int $length The length of the random string
     * @return string
     */
    public function getAscii(int $length = self::DEFAULT_LENGTH): string
    {
        return $this->generate($length, static::ASCII_PRINTABLE_CHARSET);
    }

    /**
     * Generates a random string comprised of alphabetic ASCII characters
     *
     * @param int $length The length of the random string
     * @param string $case The case of the alphabetic characters
     * @return string
     */
    public function getAsciiAlphabetic(
        int $length = self::DEFAULT_LENGTH,
        string $case = 'mixed'
    ): string {
        return $this->generate($length, $this->getAsciiAlphaCharset($case));
    }

    /**
     * Generates a random string comprised of alphanumeric ASCII characters
     *
     * @param int $length The length of the random string
     * @param string $case The case of the alphabetic characters
     * @return string
     */
    public function getAsciiAlphanumeric(
        int $length = self::DEFAULT_LENGTH,
        string $case = 'mixed'
    ): string {
        return $this->generate(
            $length,
            $this->getAsciiAlphaCharset($case) . static::NUMERIC_CHARSET
        );
    }

    /**
     * Generates a random string comprised of hexadecimal characters
     *
     * @param int $length The length of the random string
     * @param bool $uppercase Whether to convert the string to uppercase
     * @return string
     */
    public function getHex(
        int $length = self::DEFAULT_LENGTH,
        bool $uppercase = false
    ): string {
        if ($length < 1) {
            return '';
        }

        $byteLength = (int) ceil($length / 2);
        $string = bin2hex(random_bytes($byteLength));
        $string = substr($string, 0, $length);

        return $uppercase ? strtoupper($string) : $string;
    }

    /**
     * Generates a Verison 4 UUID, in compliance with RFC 4122
     *
     * @return string
     */
    public function getUuid4(): string
    {
        return implode('-', [
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(chr((ord(random_bytes(1)) & 0x0F) | 0x40))
                . bin2hex(random_bytes(1)),
            bin2hex(chr((ord(random_bytes(1)) & 0x3F) | 0x80))
                . bin2hex(random_bytes(1)),
            bin2hex(random_bytes(6))
        ]);
    }

    /**
     * Returns an alphabetic ASCII character set for a letter case
     *
     * @param string $case The case of the alphabetic characters:
     *                          upper, lower, or mixed
     * @return string
     */
    protected function getAsciiAlphaCharset(string $case): string
    {
        if (!in_array($case, ['upper', 'lower', 'mixed'])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid letter case "%s". '
                . 'Valid options are: "upper", "lower", and "mixed".',
                $case
            ));
        }
        switch ($case) {
            case 'upper':
                return static::UC_ALPHA_CHARSET;
            case 'lower':
                return static::LC_ALPHA_CHARSET;
            default:
                return static::ALPHA_CHARSET;
        }
    }
}

// -- End of file
