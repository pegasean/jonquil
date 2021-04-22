<?php

declare(strict_types=1);

namespace Jonquil\Type\Table;

use LogicException;
use InvalidArgumentException;

/**
 * Class Schema
 * @package Jonquil\Type\Table
 */
class Schema
{
    const DATA_TYPES = ['string', 'integer', 'double', 'boolean'];

    /**
     * @var array
     */
    protected $columns;

    /**
     * @var string
     */
    protected $lastError;

    /**
     * Initializes the class properties.
     *
     * @param array $columns
     */
    public function __construct(
        array $columns = []
    ) {
        $this->lastError = '';
        $this->columns = [];
        foreach ($columns as $id => $rules) {
            $this->addColumn($id, $rules);
        }
    }

    /**
     * Clones the Schema object.
     *
     * @return Schema
     */
    public function __clone()
    {
        $schema = new self();

        $schema->columns = $this->columns;
        $schema->lastError = '';

        return $schema;
    }

    /**
     * Returns the last validation error.
     *
     * @return string
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Returns an array with constraints for all columns or for a given column.
     *
     * @param string $id
     * @return array
     */
    public function getColumnRules(string $id = ''): array
    {
        if (!empty($id)) {
            if ($this->hasColumn($id)) {
                return $this->columns[$id];
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Column "%s" has not been defined', $id
                ));
            }
        }

        return $this->columns;
    }

    /**
     * Returns the names of all columns.
     *
     * @return array
     */
    public function getColumnNames(): array
    {
        return array_keys($this->columns);
    }

    /**
     * Returns the number of columns.
     *
     * @return int
     */
    public function getColumnCount(): int
    {
        return count($this->columns);
    }

    /**
     * Checks whether a column exists.
     *
     * @param string $id
     * @return bool
     */
    public function hasColumn(string $id): bool
    {
        return array_key_exists($id, $this->columns);
    }

    /**
     * Adds a new column.
     *
     * @param string $id
     * @param array $rules
     * @throws LogicException
     */
    public function addColumn(string $id, array $rules)
    {
        if ($this->hasColumn($id)) {
            throw new InvalidArgumentException(sprintf(
                'A column with name "%s" already exists', $id
            ));
        } elseif (!isset($rules['type']) || !is_string($rules['type'])) {
            throw new InvalidArgumentException(sprintf(
                'Undefined data type for column "%s"', $id
            ));
        } elseif (!$this->isValidDataType($rules['type'])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid data type "%s" for column "%s"',
                $rules['type'],
                $id
            ));
        } elseif (isset($rules['not_null']) && !is_bool($rules['not_null'])) {
            throw new InvalidArgumentException(
                'The not-null constraint should have a boolean value'
            );
        } elseif (isset($rules['default'])
            && (gettype($rules['default']) !== $rules['type'])) {
            throw new InvalidArgumentException(
                'The default value does not match the column data type'
            );
        }

        $this->columns[$id] = [
            'type' => $rules['type'],
            'not_null' => isset($rules['not_null'])
                ? $rules['not_null'] : true,
        ];

        if (isset($rules['default'])) {
            $this->columns[$id]['default'] = $rules['default'];
        }
    }

    /**
     * Deletes a column.
     *
     * @param string $id
     */
    public function deleteColumn(string $id)
    {
        if ($this->hasColumn($id)) {
            unset($this->columns[$id]);
        }
    }

    /**
     * Validates a table row against the schema.
     *
     * @param array $record
     * @return bool
     */
    public function isValidRow(array $record): bool
    {
        $this->lastError = '';
        if (count($record) !== $this->getColumnCount()) {
            $this->lastError = 'The number of columns in the record does'
                . ' not match the number of defined columns';
            return false;
        }

        foreach ($this->columns as $id => $rules) {
            if (!array_key_exists($id, $record)) {
                $this->lastError = sprintf(
                    'No value for column "%s" is given', $id
                );
                return false;
            }
            if (!$this->isValidFieldValue($id, $record[$id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates a column value.
     *
     * @param string $id
     * @param mixed $value
     * @return bool
     */
    public function isValidFieldValue(string $id, $value): bool
    {
        $this->lastError = '';
        $valueType = strtolower(gettype($value));
        $rules = $this->getColumnRules($id);

        if ($valueType === 'null') {
            if ($rules['not_null'] === true) {
                $this->lastError = sprintf(
                    'Null value in column "%s" violates'
                    . ' not-null constraint', $id
                );
                return false;
            }
        } elseif ($valueType !== $rules['type']) {
            $this->lastError = sprintf(
                'Data type mismatch for column "%s" (%s expected, %s given)',
                $id, $rules['type'], $valueType
            );
            return false;
        }

        return true;
    }

    /**
     * Checks whether a given data type is supported by the schema.
     *
     * @param string $type
     * @return bool
     */
    public function isValidDataType(string $type): bool
    {
        return in_array($type, static::DATA_TYPES);
    }
}

// -- End of file
