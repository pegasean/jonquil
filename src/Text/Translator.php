<?php

declare(strict_types=1);

namespace Jonquil\Text;

use Jonquil\Cache\CacheInterface;
use Jonquil\Type\Map;
use Exception;
use InvalidArgumentException;

/**
 * Class Translator
 * @package Jonquil\Text
 */
class Translator
{
    const DEFAULT_LANGUAGE = 'en';
    const CACHE_KEY_PREFIX = 'lang.';
    const CACHE_LIFETIME = 7 * 24 * 3600; // seconds
    const CHOICE_SEPARATOR = '|';

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_file'              => 'The file "%s" does not return an array',
        'file_not_accessible'       => 'The file "%s" is not accessible',
        'directory_not_accessible'  => 'The directory "%s" is not accessible',
        'undefined_language'        => 'Undefined language',
        'undefined_directory'       => 'Undefined language settings directory',
    ];

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $language;

    /**
     * @var string
     */
    protected $fallback;

    /**
     * @var bool
     */
    protected $returnKeyOnMiss;

    /**
     * @var Map
     */
    protected $dictionary;

    /**
     * Initializes the class properties.
     *
     * @param string $directory
     * @param string $language
     * @param string $fallback
     * @param CacheInterface $cache
     * @param bool $returnKeyOnMiss
     */
    public function __construct(
        string $directory,
        string $language = self::DEFAULT_LANGUAGE,
        string $fallback = '',
        CacheInterface $cache = null,
        bool $returnKeyOnMiss = true
    ) {
        if (empty($language)) {
            throw new InvalidArgumentException(
                self::$errors['undefined_language']
            );
        } elseif (empty($directory)) {
            throw new InvalidArgumentException(
                self::$errors['undefined_directory']
            );
        } elseif (!is_readable($directory)) {
            throw new InvalidArgumentException(
                sprintf(self::$errors['directory_not_accessible'], $directory)
            );
        }

        $this->directory = rtrim($directory, '/') . '/';
        $this->language = $language;
        $this->fallback = $fallback;
        $this->cache = $cache;
        $this->returnKeyOnMiss = $returnKeyOnMiss;
        $this->dictionary = new Map();

        $this->loadDictionary($language);
    }

    /**
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @return string
     */
    public function getFallbackLanguage(): string
    {
        return $this->fallback;
    }

    /**
     * @param string $language
     */
    public function setFallbackLanguage(string $language)
    {
        $this->fallback = $language;
    }

    /**
     * @param string $language
     */
    public function changeLanguage(string $language)
    {
        $this->language = $language;
        $this->loadDictionary($language);
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param string $key
     * @return string
     */
    public function get(string $key): string
    {
        $value = $this->dictionary->get($key, '');
        return !empty($value) ? $value : ($this->returnKeyOnMiss ? $key : '');
    }

    /**
     * @param $text
     * @param string $language
     * @param bool $useFallback
     * @param string $default
     * @return string
     */
    public function resolve(
        $text,
        string $language = '',
        bool $useFallback = true,
        string $default = ''
    ): string {
        if (empty($language)) {
            $language = $this->language;
        }

        if (is_object($text)) {
            $text = (array) $text;
        }

        if (is_scalar($text)) {
            return (string) $text;
        } elseif (is_array($text)) {
            if (array_key_exists($language, $text)) {
                return $text[$language];
            } elseif ($useFallback && !empty($this->fallback)
                && array_key_exists($this->fallback, $text)) {
                return $text[$this->fallback];
            }
        }

        return $default;
    }

    /**
     * @param string $key
     * @param array $replacements
     * @return string
     */
    public function interpolate(string $key, array $replacements): string
    {
        $template = $this->dictionary->get($key);
        if (is_string($template)) {
            $search = [];
            $replace = [];
            foreach ($replacements as $needle => $replacement) {
                $search[] = '{' . $needle . '}';
                $replace[] = $replacement;
            }
            return str_replace($search, $replace, $template);
        }
        else {
            return '';
        }
    }

    /**
     * @param string $key
     * @param int $number
     * @param bool $prepend
     * @param string $glue
     * @param string $default
     * @return string
     */
    public function pluralize(
        string $key,
        int $number,
        bool $prepend = true,
        string $glue = ' ',
        string $default = ''
    ): string {
        $choice = $this->dictionary->get($key, '');
        if (is_string($choice) && !empty($choice)) {
            $index = $this->getPlural($number);
            $choice = explode(static::CHOICE_SEPARATOR, $choice);
            if (isset($choice[$index])) {
                return $prepend
                    ? $number . $glue . $choice[$index]
                    : sprintf($choice[$index], $number);
            }
        } elseif ($this->returnKeyOnMiss) {
            return $number . $glue . $key;
        } else {
            return (string) $number;
        }

        return $default;
    }

    /**
     * @param string $key
     * @param int $timestamp
     * @param string $defaultFormat
     * @return string
     */
    public function getTime(
        string $key,
        int $timestamp = 0,
        string $defaultFormat = '%G-%m-%d %H:%M:%S'
    ): string {
        if (empty($timestamp)) {
            $timestamp = time();
        }

        $format = $this->dictionary->get($key);
        if (is_string($format) && !empty($format)) {
            return strftime($format, $timestamp);
        }

        return strftime($defaultFormat, $timestamp);
    }

    /**
     * @param int $seconds
     * @param int $maxPeriods
     * @return string
     */
    public function getDuration(int $seconds, int $maxPeriods = 4): string
    {
        $periods = [
            /* #lang */ 'date_time.interval.year'      => 31536000,
            /* #lang */ 'date_time.interval.month'     => 2419200,
            /* #lang */ 'date_time.interval.week'      => 604800,
            /* #lang */ 'date_time.interval.day'       => 86400,
            /* #lang */ 'date_time.interval.hour'      => 3600,
            /* #lang */ 'date_time.interval.minute'    => 60,
            /* #lang */ 'date_time.interval.second'    => 1,
        ];
        $i = 1;
        $duration = [];
        foreach ($periods as $period => $span) {
            $periodDuration = intval(floor($seconds / $span));
            $seconds = $seconds % $span;
            if ($periodDuration === 0) {
                continue;
            }
            $duration[] = $this->pluralize($period, $periodDuration);
            $i++;
            if ($i > $maxPeriods) {
                break;
            }
        }
        if (empty($duration)) {
            return $this->get(/* #lang */ 'date_time.expression.now');
        }
        return implode(', ', $duration);
    }

    /**
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    function getByteCount(int $bytes, int $precision = 2): string
    {
        $units = [
            /* #lang */ 'unit.data_size.symbol.byte',
            /* #lang */ 'unit.data_size.symbol.kilobyte',
            /* #lang */ 'unit.data_size.symbol.megabyte',
            /* #lang */ 'unit.data_size.symbol.gigabyte',
            /* #lang */ 'unit.data_size.symbol.terabyte',
        ];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        $symbol = $this->get($units[$pow]);
        return round($bytes, $precision) . ' ' . $symbol;
    }

    /**
     * Returns the plural definition index.
     *
     * Copyright (c) 2005-2011 Zend Technologies USA Inc.
     * It is subject to the New BSD license. For the full copyright, see:
     * @see http://framework.zend.com/license/new-bsd
     *
     * @param int $number Number for plural selection
     * @return int Plural number to use
     */
    public function getPlural(int $number): int
    {
        switch ($this->language) {
            case 'bo':
            case 'dz':
            case 'id':
            case 'ja':
            case 'jv':
            case 'ka':
            case 'km':
            case 'kn':
            case 'ko':
            case 'ms':
            case 'th':
            case 'tr':
            case 'vi':
            case 'zh':
                return 0;
            case 'af':
            case 'az':
            case 'bn':
            case 'bg':
            case 'ca':
            case 'da':
            case 'de':
            case 'el':
            case 'en':
            case 'eo':
            case 'es':
            case 'et':
            case 'eu':
            case 'fa':
            case 'fi':
            case 'fo':
            case 'fur':
            case 'fy':
            case 'gl':
            case 'gu':
            case 'ha':
            case 'he':
            case 'hu':
            case 'is':
            case 'it':
            case 'ku':
            case 'lb':
            case 'ml':
            case 'mn':
            case 'mr':
            case 'nah':
            case 'nb':
            case 'ne':
            case 'nl':
            case 'nn':
            case 'no':
            case 'om':
            case 'or':
            case 'pa':
            case 'pap':
            case 'ps':
            case 'pt':
            case 'so':
            case 'sq':
            case 'sv':
            case 'sw':
            case 'ta':
            case 'te':
            case 'tk':
            case 'ur':
            case 'zu':
                return ($number == 1) ? 0 : 1;
            case 'am':
            case 'bh':
            case 'fil':
            case 'fr':
            case 'gun':
            case 'hi':
            case 'ln':
            case 'mg':
            case 'nso':
            case 'xbr':
            case 'ti':
            case 'wa':
                return (($number == 0) || ($number == 1)) ? 0 : 1;
            case 'be':
            case 'bs':
            case 'hr':
            case 'ru':
            case 'sr':
            case 'uk':
                return (($number % 10 == 1) && ($number % 100 != 11))
                        ? 0 : ((($number % 10 >= 2) && ($number % 10 <= 4)
                                    && (($number % 100 < 10)
                                    || ($number % 100 >= 20)))
                               ? 1 : 2);
            case 'cs':
            case 'sk':
                return ($number == 1)
                        ? 0 : ((($number >= 2) && ($number <= 4))
                               ? 1 : 2);
            case 'ga':
                return ($number == 1) ? 0 : (($number == 2) ? 1 : 2);
            case 'lt':
                return (($number % 10 == 1) && ($number % 100 != 11))
                        ? 0 : ((($number % 10 >= 2) && (($number % 100 < 10)
                                    || ($number % 100 >= 20)))
                               ? 1 : 2);
            case 'sl':
                return ($number % 100 == 1)
                        ? 0 : (($number % 100 == 2)
                               ? 1 : ((($number % 100 == 3)
                                        || ($number % 100 == 4))
                                      ? 2 : 3));
            case 'mk':
                return ($number % 10 == 1) ? 0 : 1;
            case 'mt':
                return ($number == 1)
                        ? 0 : ((($number == 0) || (($number % 100 > 1)
                                && ($number % 100 < 11)))
                               ? 1 : ((($number % 100 > 10)
                                        && ($number % 100 < 20))
                                      ? 2 : 3));
            case 'lv':
                return ($number == 0)
                        ? 0 : ((($number % 10 == 1) && ($number % 100 != 11))
                               ? 1 : 2);
            case 'pl':
                return ($number == 1)
                        ? 0 : ((($number % 10 >= 2) && ($number % 10 <= 4)
                                    && (($number % 100 < 12)
                                    || ($number % 100 > 14)))
                               ? 1 : 2);
            case 'cy':
                return ($number == 1)
                        ? 0 : (($number == 2)
                               ? 1 : ((($number == 8) || ($number == 11))
                                      ? 2 : 3));
            case 'ro':
                return ($number == 1)
                        ? 0 : ((($number == 0) || (($number % 100 > 0)
                                && ($number % 100 < 20)))
                               ? 1 : 2);
            case 'ar':
                return ($number == 0)
                        ? 0 : (($number == 1)
                               ? 1 : (($number == 2)
                                      ? 2 : ((($number >= 3)
                                                && ($number <= 10))
                                             ? 3 : ((($number >= 11)
                                                        && ($number <= 99))
                                                    ? 4 : 5))));
            default:
                return 0;
        }
    }

    /**
     * @param array $prefixes
     * @return array
     */
    public function export(array $prefixes = []): array
    {
        if (!empty($prefixes)) {
            $dictionary = [];
            $prefixes = array_flip($prefixes);
            foreach ($this->dictionary->toArray() as $prefix => $value) {
                if (array_key_exists($prefix, $prefixes)) {
                    $dictionary[$prefix] = $value;
                }
            }
            return $dictionary;
        }
        else {
            return $this->dictionary->toArray();
        }
    }

    /**
     * @param string $language
     * @return Map
     * @throws Exception
     */
    public function getDictionary(string $language): Map
    {
        $dictionary = [];
        $directory = $this->directory . $language . '/';
        if (!is_readable($directory)) {
            throw new Exception(
                sprintf(self::$errors['directory_not_accessible'], $directory)
            );
        }
        foreach (glob($directory . '*.php') as $file) {
            if (!is_readable($file)) {
                throw new Exception(
                    sprintf(self::$errors['file_not_accessible'], $file)
                );
            }
            $prefix = basename($file, '.php');
            $dictionary[$prefix] = include $file;
            if (!is_array($dictionary[$prefix])) {
                throw new Exception(
                    sprintf(self::$errors['invalid_file'], $file)
                );
            }
        }
        return new Map($dictionary);
    }

    /**
     * @param string $language
     * @return bool
     */
    public function deleteFromCache(string $language): bool
    {
        if (is_null($this->cache)) {
            return false;
        }
        $key = static::CACHE_KEY_PREFIX . $language;
        return $this->cache->delete($key);
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param string $language
     * @throws Exception
     */
    protected function loadDictionary(string $language)
    {
        $key = static::CACHE_KEY_PREFIX . $language;
        if (!is_null($this->cache)) {
            $dictionary = $this->cache->get($key);
            if ($dictionary !== false) {
                $this->dictionary = $dictionary;
                return;
            }
        }
        $this->dictionary = $this->getDictionary($language)
            ->makeImmutable();
        if (!is_null($this->cache)) {
            $this->cache->set($key, $this->dictionary, static::CACHE_LIFETIME);
        }
    }
}

// -- End of file
