<?php

declare(strict_types=1);

namespace Jonquil\Type;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use LogicException;

/**
 * Class Map
 * @package Jonquil\Type
 */
class Map implements ArrayAccess, Countable
{
    /**
     * @var array Error messages
     */
    protected static $errors = [
        'changes_not_allowed'   => 'Changes are not allowed (read-only object)',
        'invalid_array_offset'  => 'Invalid array offset'
            . ' (a string or an integer is expected)',
        'invalid_data_type'     => 'Invalid data type "%"',
    ];

    /**
     * @var bool Is it mutable?
     */
    protected $allowChanges;

    /**
     * An instance's array.
     *
     * @var array
     */
    protected $data;

    /**
     * Initializes the class properties.
     *
     * @param array $data
     * @param bool $allowChanges
     */
    public function __construct(array $data = [], bool $allowChanges = true)
    {
        $this->allowChanges = $allowChanges;
        $this->data = $data;
    }

    /**
     * Clones the Map object.
     *
     * @return Map
     */
    public function __clone()
    {
        $map = new self();

        $map->data = $this->data;
        $map->allowChanges = $this->allowChanges;

        return $map;
    }

    /**
     * Checks whether the map is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Checks whether the map is immutable.
     *
     * @return bool
     */
    public function isImmutable(): bool
    {
        return !$this->allowChanges;
    }

    /**
     * Makes the map immutable.
     *
     * @return Map
     */
    public function makeImmutable()
    {
        $this->allowChanges = false;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param string $valueType
     * @return Table
     */
    public function toTable(string $valueType = 'string'): Table
    {
        $data = [];
        foreach ($this->data as $key => $value) {
            switch ($valueType) {
                case 'string':
                    $value = is_scalar($value)
                        ? (string) $value
                        : json_encode($value, JSON_PRETTY_PRINT);
                    break;
                case 'integer':
                    $value = is_scalar($value) ? (int) $value : 0;
                    break;
                case 'double':
                    $value = is_scalar($value) ? (double) $value : 0.0;
                    break;
                case 'boolean':
                    $value = is_scalar($value) ? (boolean) $value : false;
                    break;
                default:
                    throw new InvalidArgumentException(sprintf(
                        static::$errors['invalid_data_type'], $valueType
                    ));
            }
            $data[] = [
                'property' => (string) $key,
                'value' => $value,
            ];
        }

        return new Table($data);
    }

    /**
     * @param bool $addHeader
     * @param bool $flatten
     * @param string $delimiter
     * @param string $enclosure
     * @return string
     */
    public function toCsv(
        bool $addHeader = false,
        bool $flatten = true,
        string $delimiter = ',',
        string $enclosure = '"'
    ): string {
        $handler = fopen('php://temp', 'rw');
        $data = is_array(reset($this->data)) ? $this->data : [$this->data];
        if ($addHeader) {
            $first = reset($data);
            if (is_array($first)) {
                fputcsv($handler, array_keys($first), $delimiter, $enclosure);
            }
        }
        foreach ($data as $row) {
            if ($flatten) {
                $record = [];
                array_walk_recursive(
                    $row,
                    function($value) use (&$record) {
                        $record[] = $value;
                    }
                );
                fputcsv($handler, $record, $delimiter, $enclosure);
            } else {
                fputcsv($handler, $row, $delimiter, $enclosure);
            }
        }
        rewind($handler);
        $csv = stream_get_contents($handler);
        fclose($handler);

        return $csv;
    }

    /**
     * @param array $options
     * @return string
     */
    public function toHtml(array $options = []): string
    {
        if ($this->isEmpty()) {
            return '';
        }
        $options = new self($this->processHtmlExportOptions($options));
        return $this->renderAsHtml($this->data, $options);
    }

    /**
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->data, $options);
    }

    /**
     * @return string
     */
    public function toPrettyJson(): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Returns the length of the map, implementing the Countable interface.
     *
     * @return int
     */
    public function getLength(): int
    {
        return count($this->data);
    }

    /**
     * Returns all keys of the data array.
     *
     * @return array
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Returns all keys of the data array in random order.
     *
     * @return array
     * @throws \Exception
     */
    public function getShuffledKeys(): array
    {
        $keys = $this->getKeys();
        $keysCount = $this->getLength();

        for ($i = 0; $i < $keysCount; $i++) {
            $r = random_int(0, $keysCount - 1);
            if ($r !== $i) {
                $temp = $keys[$r];
                $keys[$r] = $keys[$i];
                $keys[$i] = $temp;
            }
        }

        return array_values($keys);
    }

    /**
     * Returns all values of the data array.
     *
     * @return array
     */
    public function getValues(): array
    {
        return array_values($this->data);
    }

    /**
     * Returns the first $n entries of the map.
     *
     * @param int $n Number of entries to retrieve from the start
     * @return array
     */
    public function getFirst(int $n = 1): array
    {
        if ($n < 1) {
            return [];
        }

        return array_slice($this->data, 0, $n, true);
    }

    /**
     * Returns the last $n entries of the map.
     *
     * @param int $n Number of entries to retrieve from the end
     * @return array
     */
    public function getLast(int $n = 1): array
    {
        if ($n < 1) {
            return [];
        }

        return array_slice($this->data, -$n, null, true);
    }

    /**
     * Returns one or more random entries out of the map
     *
     * @param int $number Specifies how many entries should be picked
     * @return array
     * @throws \Exception
     */
    public function getRandom(int $number = 1): array
    {
        if ($number < 1) {
            return [];
        }

        $data = [];
        $randomKeys = array_slice($this->getShuffledKeys(), 0, $number);
        foreach ($randomKeys as $key) {
            $data[$key] = $this->data[$key];
        }

        return $data;
    }

    /**
     * Returns a formatted JSON value.
     *
     * @param mixed $key
     * @return string
     */
    public function getJsonValue($key): string
    {
        $value = json_decode($this->get($key, ''), true);
        return json_encode($value, JSON_PRETTY_PRINT) ?: '';
    }

    /**
     * Returns a decoded JSON value.
     *
     * @param mixed $key
     * @return array
     */
    public function getDecodedJsonValue($key): array
    {
        return json_decode($this->get($key, ''), true) ?: [];
    }

    /*--------------------------------------------------------------------*/

    /**
     * Checks whether a key exists.
     *
     * @param mixed $key
     * @return bool
     */
    public function has($key): bool
    {
        if (!is_array($key)) {
            if (is_string($key)) {
                $key = explode('.', $key);
            } elseif (is_int($key)) {
                return array_key_exists($key, $this->data);
            } else {
                throw new InvalidArgumentException(
                    static::$errors['invalid_array_offset']
                );
            }
        }

        $data =& $this->data;

        foreach ($key as $k) {
            if (!is_string($k) && !is_int($k)) {
                throw new InvalidArgumentException(
                    static::$errors['invalid_array_offset']
                );
            }
            if (is_array($data) && array_key_exists($k, $data)) {
                $data =& $data[$k];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns an item, associated with a key.
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!is_array($key)) {
            if (is_string($key)) {
                $key = explode('.', $key);
            } elseif (is_int($key)) {
                return $this->data[$key];
            } else {
                throw new InvalidArgumentException(
                    static::$errors['invalid_array_offset']
                );
            }
        }

        if ($this->has($key)) {
            $data =& $this->data;
            foreach ($key as $k) {
                $data =& $data[$k];
            }
            return $data;
        } else {
            return $default;
        }
    }

    /**
     * Adds an item to the data array, if the given key does not already exist.
     *
     * @param mixed $key
     * @param mixed $value
     * @return Map
     */
    public function add($key, $value)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if (is_string($key)) {
            $key = explode('.', $key);
        }

        if ($this->has($key)) {
            throw new LogicException(
                'An item with the same key already exists'
            );
        }

        $this->set($key, $value);

        return $this;
    }

    /**
     * Sets a value, associated with a key.
     *
     * @param mixed $key
     * @param mixed $value
     * @return Map
     */
    public function set($key, $value)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if (!is_array($key)) {
            if (is_string($key)) {
                $key = explode('.', $key);
            } elseif (is_int($key)) {
                $this->data[$key] = $value;
                return $this;
            } else {
                throw new InvalidArgumentException(
                    static::$errors['invalid_array_offset']
                );
            }
        }

        $data =& $this->data;

        foreach($key as $k) {
            if (!(is_string($k) || is_int($k))
                || (is_string($k) && ($k === ''))) {
                throw new InvalidArgumentException(
                    static::$errors['invalid_array_offset']
                );
            }
            if (!is_array($data) || !array_key_exists($k, $data) || !is_array($data[$k])) {
                $data[$k] = [];
            }
            $data =& $data[$k];
        }

        $data = $value;

        return $this;
    }

    /**
     * Removes an entry from the map.
     *
     * @param mixed $key
     * @return Map
     */
    public function remove($key)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if (is_string($key)) {
            $key = explode('.', $key);
        }

        if (!$this->has($key)) {
            return $this;
        }

        if (is_array($key)) {
            $data =& $this->data;
            $i = array_pop($key);
            foreach($key as $k) {
                $data =& $data[$k];
            }
            unset($data[$i]);
        } else {
            unset($this->data[$key]);
        }

        return $this;
    }

    /**
     * Returns a value, associated with a key, and also removes the entry
     * from the map.
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if (is_string($key)) {
            $key = explode('.', $key);
        }

        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Shuffles the map.
     *
     * @return Map
     * @throws \Exception
     */
    public function shuffle()
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $data = [];
        $resetIndices = true;
        foreach ($this->getShuffledKeys() as $key) {
            if (!is_int($key)) {
                $resetIndices = false;
            }
            $data[$key] = $this->data[$key];
        }
        $this->data = $resetIndices ? array_values($data) : $data;

        return $this;
    }

    /**
     * Sorts the map recursively by keys.
     *
     * @return Map
     */
    public function sortByKeys()
    {
        return $this->flatten(true)->expand();
    }

    /**
     * Flattens the map with dot-separated keys.
     *
     * @param bool $sort
     * @return Map
     */
    public function flatten(bool $sort = false)
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $data = $this->flattenArray($this->data);
        if ($sort) {
            ksort($data);
        }
        $this->data =& $data;

        return $this;
    }

    /**
     * Expands the map on dot-separated keys.
     *
     * @return Map
     */
    public function expand()
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $map = new static();
        foreach ($this->data as $key => $value) {
            $map->set($key, $value);
        }
        $this->data = $map->toArray();

        return $this;
    }

    /**
     * Decodes a JSON entry.
     *
     * @param mixed $key
     * @return Map
     */
    public function decodeJsonValue($key)
    {
        $this->set($key, $this->getDecodedJsonValue($key));

        return $this;
    }

    /*--------------------------------------------------------------------*/

    public function isSequentiallyIndexed()
    {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->getKeys() === range(0, $this->getLength() - 1);
    }

    public function hasStringKeys()
    {
        return count(array_filter($this->getKeys(), 'is_string')) > 0;
    }

    /*--------------------------------------------------------------------*/


    /**
     * Returns the length of the map, implementing the Countable interface.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->getLength();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $this->data[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        unset($this->data[$offset]);
    }

    /*--------------------------------------------------------------------*/

    /**
     * @return string
     */
    public function __toString(): string
    {
        return var_export($this->data, true);
    }

    /*--------------------------------------------------------------------*/

    /**
     * Flattens a multi-dimensional associative array.
     *
     * @param  array $array
     * @param  string $prefix
     * @return array
     */
    protected function flattenArray(array $array, string $prefix = '')
    {
        $newArray = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $newArray = array_merge(
                    $newArray,
                    $this->flattenArray($value, $prefix . $key . '.')
                );
            } else {
                $newArray[$prefix . $key] = $value;
            }
        }
        return $newArray;
    }

    protected function renderAsHtml($value, Map $options): string
    {
        switch (gettype($value)) {
            case 'array':
                $content = '';
                if ((new self($value))->isSequentiallyIndexed()) {
                    foreach ($value as $subvalue) {
                        $content .= '<li>' . $this->renderAsHtml($subvalue, $options) . '</li>';
                    }
                    if ($content) {
                        $content = '<ul' . $options->get('list_attributes') . '>' . $content . '</ul>';
                    }
                } else {
                    foreach ($value as $propertyName => $subvalue) {
                        $content .= '<tr><td>' . $propertyName . '</td><td>'
                            . $this->renderAsHtml($subvalue, $options) . '</td></tr>';
                    }
                    if ($content) {
                        $content = '<table' . $options->get('table_attributes') . '>'
                            . '<tbody>' . $content . '</tbody></table>';
                    }
                }
                $value = $content;
                break;
            case 'string':
                if ($options->get('escape_special_chars')) {
                    $value = htmlspecialchars($value);
                }
                break;
            case 'boolean':
                $booleanFormat = $options->get('boolean_format');
                $value = empty($booleanFormat) ? var_export($value, true) : $booleanFormat[intval($value)];
                break;
            case 'integer':
            case 'double':
                break;
            default:
                $value = '';
        }
        return strval($value);
    }

    protected function processHtmlExportOptions(array $options): array
    {
        $listAttributes = '';
        $tableAttributes = '';
        $booleanFormat = ['no', 'yes'];

        if (isset($options['list_attributes']) && is_array($options['list_attributes'])) {
            $listAttributes = $this->concatenateHtmlAttributes($options['list_attributes']);
        }
        if (isset($options['table_attributes']) && is_array($options['table_attributes'])) {
            $tableAttributes = $this->concatenateHtmlAttributes($options['table_attributes']);
        }
        if (isset($options['boolean_format'])) {
            if (is_array($options['boolean_format'])) {
                $options['boolean_format'] = array_filter($options['boolean_format'], 'is_string');
                if (count($options['boolean_format']) === 2) {
                    $booleanFormat = array_values($options['boolean_format']);
                }
            } elseif ($options['boolean_format'] === false) {
                $booleanFormat = [];
            }
        }

        return [
            'list_attributes' => $listAttributes,
            'table_attributes' => $tableAttributes,
            'boolean_format' => $booleanFormat,
            'escape_special_chars' => boolval($options['escape_special_chars'] ?? true),
        ];
    }

    protected function concatenateHtmlAttributes(array $attributes, bool $addLeadingSpace = true): string
    {
        $content = '';
        $attributeList = [];
        foreach ($attributes as $property => $value) {
            $attributeList[] = htmlspecialchars($property) . '="' . htmlspecialchars($value) . '"';
        }
        if ($attributeList) {
            $content = implode(' ', $attributeList);
            if ($addLeadingSpace) {
                $content = ' ' . $content;
            }
        }
        return $content;
    }
}

// -- End of file
