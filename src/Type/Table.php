<?php

declare(strict_types=1);

namespace Jonquil\Type;

use Jonquil\Filter\Validation\Validator;
use Jonquil\Type\Table\Schema;
use Exception;
use InvalidArgumentException;
use LogicException;

/**
 * Class Table
 * @package Jonquil\Type
 */
class Table extends Map
{
    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var array
     */
    protected $indexes;

    /**
     * @var string
     */
    protected $keyColumn;

    /**
     * Initializes the class properties.
     *
     * @param array $data
     * @param array $columns
     * @param bool $allowChanges
     */
    public function __construct(
        array $data = [],
        array $columns = [],
        bool $allowChanges = true
    ) {
        parent::__construct([], $allowChanges);

        $dataInfo = new Map($this->analyze($data));
        $dataErrors = new Map($dataInfo->get('errors'));

        $this->checkDataErrors($dataErrors);
        $this->mergeColumnDefinitions($columns, $dataInfo->get('columns'));

        $this->schema = new Schema($columns);
        $this->indexes = [];
        $this->keyColumn = '';

        $this->import($data);
    }

    /**
     * Clones the Table object.
     *
     * @return Table
     */
    public function __clone()
    {
        $table = new self();

        $table->data = $this->data;
        $table->allowChanges = $this->allowChanges;

        $table->schema = clone $this->schema;
        $table->indexes = $this->indexes;
        $table->keyColumn = $this->keyColumn;

        return $table;
    }

    /**
     * {@inheritdoc}
     */
    public function toCsv(
        bool $addHeader = true,
        bool $flatten = false,
        string $delimiter = ',',
        string $enclosure = '"'
    ): string {
        return parent::toCsv($addHeader, $flatten, $delimiter, $enclosure);
    }

    /**
     * {@inheritdoc}
     */
    public function toHtml(array $options = []): string
    {
        if ($this->isEmpty()) {
            return '';
        }
        $options = new Map($this->processHtmlExportOptions($options));

        $content = '<table' . $options->get('table_attributes') . '>';
        if ($options->get('include_table_header')) {
            $columnNames = $options->get('column_names');
            if ($options->get('column_name_format')) {
                foreach ($columnNames as &$columnName) {
                    $text = new Text($columnName);
                    switch ($options->get('column_name_format')) {
                        case 'title_case':
                            $text->spacify()->toTitleCase();
                            break;
                        default:
                            $text->trim();
                    }
                    $columnName = $text->toString();
                }
            }
            $columnNames = implode('</th><th>', $columnNames);
            $content .= sprintf('<thead><tr><th>%s</th></tr></thead>', $columnNames);
        }
        $content .= '<tbody>';
        foreach ($this->data as $row) {
            $content .= '<tr>';
            foreach ($row as $value) {
                $content .= '<td>' . $this->renderAsHtml($value, $options) . '</td>';
            }
            $content .= '</tr>';
        }
        $content .= '</tbody>';
        $content .= '</table>';

        return $content;
    }

    /**
     * Returns the key data type.
     *
     * @return string
     */
    public function getKeyType(): string
    {
        reset($this->data);
        return strtolower(gettype(key($this->data)));
    }

    /*--------------------------------------------------------------------*/

    /**
     * Checks whether a column exists.
     *
     * @param string $id
     * @return bool
     */
    public function hasColumn(string $id): bool
    {
        return $this->schema->hasColumn($id);
    }

    /**
     * Returns the number of columns.
     *
     * @return int
     */
    public function getColumnCount(): int
    {
        return $this->schema->getColumnCount();
    }

    /**
     * Returns an array with constraints for all columns or for a given column.
     *
     * @param string $id
     * @return array
     */
    public function getColumnRules(string $id = ''): array
    {
        return $this->schema->getColumnRules($id);
    }

    /**
     * Adds a new column.
     *
     * @param string $columnId
     * @param array $rules
     * @return Table
     */
    public function addColumn(string $columnId, array $rules): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $this->schema->addColumn($columnId, $rules);

        foreach ($this->data as $rowId => $row) {
            $this->resetField($rowId, $columnId);
        }

        return $this;
    }

    /**
     * Deletes a column.
     *
     * @param string $columnId
     * @return Table
     */
    public function deleteColumn(string $columnId): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if ($columnId === $this->keyColumn) {
            throw new LogicException(sprintf(
                'Column "%s" is a key column and cannot be deleted', $columnId
            ));
        }

        if ($this->hasIndex($columnId)) {
            $this->deleteIndex($columnId);
        }

        $this->schema->deleteColumn($columnId);

        foreach ($this->data as $rowId => $row) {
            unset($this->data[$rowId][$columnId]);
        }

        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Checks whether a row exists.
     *
     * @param string|int $id
     * @return bool
     */
    public function hasRow($id): bool
    {
        return array_key_exists($id, $this->data);
    }

    /**
     * Returns the number of rows.
     *
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->getLength();
    }

    /**
     * Imports data from a two-dimensional array.
     *
     * @param array $data
     * @return Table
     */
    public function import(array $data): Table
    {
        foreach ($data as $id => $record) {
            $this->addRow($id, $record);
        }

        return $this;
    }

    /**
     * Returns a row, associated with a key.
     *
     * @param string|int $id
     * @return array
     */
    public function getRow($id): array
    {
        if ($this->hasRow($id)) {
            return $this->data[$id];
        }

        return [];
    }

    /**
     * Adds a new row to the table or replaces an existing one.
     *
     * @param string|int $id
     * @param array $data
     * @param bool $replaceExisting
     * @return Table
     */
    public function addRow(
        $id,
        array $data,
        bool $replaceExisting = true
    ): Table {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if ($this->hasRow($id) && ($replaceExisting === false)) {
            throw new InvalidArgumentException(sprintf(
                'A record with key "%s" already exists', $id
            ));
        }

        if (count($data) < $this->schema->getColumnCount()) {
            foreach ($this->schema->getColumnNames() as $columnId) {
                if (!array_key_exists($columnId, $data)) {
                    $data[$columnId] = null;
                }
            }
        }

        $keyType = $this->getKeyType();
        if (($keyType !== 'null') && (gettype($id) !== $keyType)) {
            throw new InvalidArgumentException(sprintf(
                'Data type mismatch for the key value (%s expected, %s given)',
                $keyType, gettype($id)
            ));
        }

        if ($this->schema->isValidRow($data)) {
            $this->data[$id] = $data;
        } else {
            throw new InvalidArgumentException($this->schema->getLastError());
        }

        $this->rebuildIndexes();

        return $this;
    }

    /**
     * Removes a row from the table.
     *
     * @param string|int $id
     * @return Table
     */
    public function deleteRow($id): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if ($this->hasRow($id)) {
            unset($this->data[$id]);
        }

        $this->rebuildIndexes();

        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Updates a field in the table.
     *
     * @param string|int $rowId
     * @param string $columnId
     * @param mixed $value
     * @return Table
     */
    public function updateField($rowId, string $columnId, $value): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        if (!$this->hasRow($rowId)) {
            throw new InvalidArgumentException(sprintf(
                'A record with key "%s" does not exist', $rowId
            ));
        } elseif (!$this->hasColumn($columnId)) {
            throw new InvalidArgumentException(sprintf(
                'Undefined column "%s"', $columnId
            ));
        }

        if ($this->schema->isValidFieldValue($columnId, $value)) {
            $this->data[$rowId][$columnId] = $value;
        } else {
            throw new InvalidArgumentException($this->schema->getLastError());
        }

        $this->rebuildIndex($columnId);

        return $this;
    }

    /**
     * Sets a field to null.
     *
     * @param string|int $rowId
     * @param string $columnId
     * @return Table
     */
    public function nullifyField($rowId, string $columnId): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $this->updateField($rowId, $columnId, null);

        return $this;
    }

    /**
     * Sets a field to the column's default value or null.
     *
     * @param string|int $rowId
     * @param string $columnId
     * @return Table
     */
    public function resetField($rowId, string $columnId): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        } elseif (!$this->hasColumn($columnId)) {
            return $this;
        }

        $value = null;
        $rules = $this->schema->getColumnRules($columnId);
        if (isset($rules['default'])) {
            $value = $rules['default'];
        }

        $this->updateField($rowId, $columnId, $value);

        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Re-indexes the data array using numeric keys (starting from 0).
     *
     * @return Table
     */
    public function resetKeys(): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $data = $this->data;
        $this->data = [];
        $this->keyColumn = '';

        foreach($data as $row) {
            $this->data[] = $row;
        }

        $this->rebuildIndexes();

        return $this;
    }

    /**
     * Re-indexes the data array using the given column's values.
     *
     * @param string $columnId
     * @return Table
     */
    public function setKeyColumn(string $columnId): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $index = $this->buildIndex($columnId, true);

        if (count($index) !== $this->getRowCount()) {
            throw new InvalidArgumentException(
                'The number of index rows does not match'
                    . ' the number of table rows'
            );
        }

        $data = $this->data;
        $this->data = [];
        $this->keyColumn = $columnId;

        foreach($index as $newRowId => $oldRowId) {
            $this->data[$newRowId] = $data[$oldRowId];
        }

        $this->rebuildIndexes();

        return $this;
    }

    /**
     * Returns a list with the identifiers of all indexes.
     *
     * @return array
     */
    public function getIndexList(): array
    {
        return array_keys($this->indexes);
    }

    /**
     * Checks whether an index exists.
     *
     * @param string $columnId
     * @return bool
     */
    public function hasIndex(string $columnId): bool
    {
        return array_key_exists($columnId, $this->indexes);
    }

    /**
     * Adds an index.
     *
     * @param string $columnId
     * @param bool $unique
     * @return Table
     */
    public function addIndex(string $columnId, bool $unique = true): Table
    {
        $index = $this->buildIndex($columnId, $unique);
        $this->indexes[$columnId] = [
            'unique' => $unique,
            'map' => $index,
        ];

        return $this;
    }

    /**
     * Builds an index.
     *
     * @param string $columnId
     * @param bool $unique
     * @return array
     */
    public function buildIndex(string $columnId, bool $unique = true): array
    {
        if (!$this->hasColumn($columnId)) {
            throw new InvalidArgumentException(sprintf(
                'Column "%s" has not been defined', $columnId
            ));
        }

        $rules = $this->schema->getColumnRules($columnId);

        if (!in_array($rules['type'], ['string', 'integer'])) {
            throw new InvalidArgumentException(sprintf(
                'Column "%s" holds %s values', $columnId, $rules['type']
            ));
        } elseif ($rules['not_null'] === false) {
            throw new InvalidArgumentException(sprintf(
                'Column "%s" may contain null values', $columnId
            ));
        }

        $index = [];

        foreach ($this->data as $rowId => $row) {
            if ($unique) {
                if (array_key_exists($row[$columnId], $index)) {
                    throw new LogicException(sprintf(
                        'Column "%s" contains duplicate values', $columnId
                    ));
                }
                $index[$row[$columnId]] = $rowId;
            } else {
                $index[$row[$columnId]][] = $rowId;
            }
        }

        return $index;
    }

    /**
     * Rebuilds an index.
     *
     * @param string $columnId
     * @return Table
     */
    public function rebuildIndex(string $columnId): Table
    {
        if ($this->hasIndex($columnId)) {
            $this->indexes[$columnId]['map'] = $this->buildIndex(
                $columnId,
                $this->indexes[$columnId]['unique']
            );
        }

        return $this;
    }

    /**
     * Rebuilds all indexes.
     *
     * @return Table
     */
    public function rebuildIndexes(): Table
    {
        foreach ($this->getIndexList() as $columnId) {
            $this->rebuildIndex($columnId);
        }

        return $this;
    }

    /**
     * Deletes an index.
     *
     * @param string $columnId
     * @return Table
     */
    public function deleteIndex(string $columnId): Table
    {
        if (array_key_exists($columnId, $this->indexes)) {
            unset($this->indexes[$columnId]);
        }

        return $this;
    }

    /**
     * Deletes all indexes.
     *
     * @return Table
     */
    public function deleteIndexes(): Table
    {
        foreach ($this->getIndexList() as $columnId) {
            $this->deleteIndex($columnId);
        }

        return $this;
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param array $criteria
     * @return int
     */
    public function getRecordCount(array $criteria = []): int
    {
        return $this->query($criteria)->getLength();
    }

    /**
     * @param array $criteria
     * @param array $fields
     * @return Map
     */
    public function find(
        array $criteria = [],
        array $fields = []
    ): Map {
        if (count($criteria) === 1) {
            list($field, $operator, $value) = reset($criteria);
            if ($operator === '=') {
                $data = $this->fetchRow($field, $value);
                if (!empty($data)) {
                    $data = (new self([$data]))->project($fields)->toArray();
                }
                return new Map(reset($data) ?: []);
            }
        }
        $data = $this->query($criteria, $fields)->toArray();
        return new Map(reset($data) ?: []);
    }

    /**
     * @param array $criteria
     * @param array $fields
     * @param array $order
     * @param int $limit
     * @param int $offset
     * @return Table
     */
    public function findAll(
        array $criteria = [],
        array $fields = [],
        array $order = [],
        int $limit = 0,
        int $offset = 0
    ): Table {
        if (count($criteria) === 1) {
            list($field, $operator, $value) = reset($criteria);
            if ($operator === '=') {
                $table = new self($this->fetchRows($field, $value));
                $table = $table->project($fields)->sort($order, false);
                if (!empty($limit) || !empty($offset)) {
                    if ($limit < 0) {
                        $limit = 0;
                    }
                    if ($offset < 0) {
                        $offset = 0;
                    }
                    $table = new self(
                        array_slice($table->toArray(), $offset, $limit)
                    );
                }
                return $table;
            }
        }
        return $this->query($criteria, $fields, $order, $limit, $offset);
    }

    /**
     * @param string $field
     * @param array $criteria
     * @param bool $sortAscending
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getList(
        string $field,
        array $criteria = [],
        bool $sortAscending = true,
        int $limit = 0,
        int $offset = 0
    ): array {
        $table = $this->query(
            $criteria,
            [$field],
            [[$field => ($sortAscending ? 'asc' : 'desc')]],
            $limit,
            $offset
        );
        $list = [];
        foreach ($table->toArray() as $row) {
            $list[] = $row[$field];
        }
        return $list;
    }

    /**
     * Returns a row matching a unique field value.
     *
     * @param string $columnId
     * @param mixed $value
     * @return array
     */
    public function fetchRow(string $columnId, $value): array
    {
        if (!$this->hasColumn($columnId)) {
            return [];
        } elseif ($columnId === $this->keyColumn) {
            return $this->getRow($value);
        } elseif ($this->hasIndex($columnId)
            && array_key_exists($value, $this->indexes[$columnId]['map'])) {
            $rowId = $this->indexes[$columnId]['map'][$value];
            return $this->getRow(is_array($rowId) ? reset($rowId) : $rowId);
        } else {
            $row = $this->query([[$columnId, '===', $value]])->toArray();
            return reset($row) ?: [];
        }
    }

    /**
     * Returns an array of all rows matching a field value.
     *
     * @param string $columnId
     * @param mixed $value
     * @return array
     */
    public function fetchRows(string $columnId, $value): array
    {
        if (!$this->hasColumn($columnId)) {
            return [];
        } elseif ($columnId === $this->keyColumn) {
            return [$this->getRow($value)];
        } elseif ($this->hasIndex($columnId)
            && array_key_exists($value, $this->indexes[$columnId]['map'])) {
            $rows = [];
            $rowIds = $this->indexes[$columnId]['map'][$value];
            if (!is_array($rowIds)) {
                $rowIds = [$rowIds];
            }
            foreach ($rowIds as $rowId) {
                $rows[] = $this->getRow($rowId);
            }
            return $rows;
        } else {
            return $this->query([[$columnId, '===', $value]])->getValues();
        }
    }

    /**
     * @param array $criteria
     * @param array $columnIds
     * @param array $order
     * @param int $limit
     * @param int $offset
     * @return Table
     */
    public function query(
        array $criteria,
        array $columnIds = [],
        array $order = [],
        int $limit = 0,
        int $offset = 0
    ): Table {
        $view = new self();
        $view->data = $this->data;
        $view->schema = clone $this->schema;

        if (!empty($criteria)) {
            $view = $view->select($criteria, false);
        }
        if (!empty($columnIds)) {
            $view = $view->project($columnIds);
        }
        if (!empty($order)) {
            $view->sort($order, false);
        }
        if (!empty($limit) || !empty($offset)) {
            $view = $view->limit($limit, $offset);
        }

        return $view;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return Table
     */
    public function limit(int $limit = 0, int $offset = 0): Table
    {
        $columns = $this->getColumnRules();
        $view = new self([], $columns);

        $selectionCount = 0;
        $lastInsertId = 0;
        foreach ($this->data as $rowId => $row) {
            $selectionCount++;
            if (($offset > 0) && ($offset >= $selectionCount)) {
                continue;
            }
            $view->addRow($lastInsertId, $row);
            $lastInsertId++;

            if (($limit > 0) && ($limit === $lastInsertId)) {
                break;
            }
        }

        return $view;
    }

    /**
     * @param array $criteria
     * @param bool $preserveKeys
     * @return Table
     */
    public function select(
        array $criteria,
        bool $preserveKeys = true
    ): Table {
        $columns = $this->getColumnRules();
        $view = new self([], $columns);

        $validator = new Validator($criteria);
        $lastInsertId = 0;
        foreach ($this->data as $rowId => $row) {
            if (!$validator->test($row)) {
                continue;
            }
            if ($preserveKeys) {
                $view->addRow($rowId, $row);
            } else {
                $view->addRow($lastInsertId, $row);
                $lastInsertId++;
            }
        }

        return $view;
    }

    /**
     * @param array $columnIds
     * @return Table
     * @throws InvalidArgumentException
     */
    public function project(array $columnIds): Table
    {
        $columns = [];
        foreach ($columnIds as $columnId) {
            if (!$this->hasColumn($columnId)) {
                throw new InvalidArgumentException(sprintf(
                    'Column "%s" has not been defined', $columnId
                ));
            }
            $columns[$columnId] = $this->getColumnRules($columnId);
        }

        $view = new self([], $columns);

        foreach ($this->data as $rowId => $row) {
            $projectedRow = [];
            foreach ($columnIds as $columnId) {
                $projectedRow[$columnId] = $row[$columnId];
            }
            $view->addRow($rowId, $projectedRow);
        }

        return $view;
    }

    /**
     * @param array $order
     * @param bool $preserveNumericKeys
     * @return Table
     * @throws LogicException
     */
    public function sort(array $order, bool $preserveNumericKeys = true): Table
    {
        if ($this->isImmutable()) {
            throw new LogicException(static::$errors['changes_not_allowed']);
        }

        $keyType = $this->getKeyType();
        $indexColumnId = 'INDEX_' . uniqid();

        $args = [];
        foreach ($order as $columnId) {
            $direction = SORT_ASC;
            if (is_array($columnId) && !empty($columnId)) {
                if (strtoupper(reset($columnId)) === 'DESC') {
                    $direction = SORT_DESC;
                }
                $columnId = key($columnId);
            }
            if (!$this->hasColumn((string) $columnId)) {
                continue;
            }
            $tmp = [];
            foreach ($this->data as $rowId => $row) {
                if (($keyType === 'integer') && $preserveNumericKeys) {
                    $this->data[$rowId][$indexColumnId] = $rowId;
                }
                $tmp[$rowId] = $row[$columnId];
            }
            $args[] = $tmp;
            $args[] = $direction;
        }
        $args[] =& $this->data;
        call_user_func_array('array_multisort', $args);

        if (($keyType === 'integer') && $preserveNumericKeys) {
            $data = array_pop($args);
            $this->data = [];
            foreach ($data as $rowId => $row) {
                $rowId = $row[$indexColumnId];
                unset($row[$indexColumnId]);
                $this->data[$rowId] = $row;
            }
        }

        return $this;
    }

    /**
     * Scans all fields of a data array and returns summarized information
     * about the array (including any validation errors).
     *
     * @param array $table
     * @return array
     */
    public function analyze(array $table = []): array
    {
        if (empty($table)) {
            $table =& $this->data;
        }

        $t = [
            'key' => [
                'type' => null,
                'values' => [],
            ],
            'columns' => [],
            'rows' => [
                'count' => count($table),
            ],
            'errors' => [],
            'is_valid' => false,
        ];

        foreach ($table as $rowId => $row) {
            $keyType = strtolower(gettype($rowId));
            if (!isset($t['key']['values'][$keyType])) {
                $t['key']['values'][$keyType] = 0;
            }
            $t['key']['values'][$keyType]++;
            if (!is_array($row)) {
                $t['errors']['invalid_rows'][] = $rowId;
                continue;
            }
            foreach ($row as $columnId => $value) {
                if (!is_string($columnId)) {
                    $t['errors']['invalid_column_ids'][] = $columnId;
                    continue;
                }
                if (!isset($t['columns'][$columnId])) {
                    $t['columns'][$columnId] = [
                        'type' => null,
                        'not_null' => false,
                        'values' => [
                            'all' => 0,
                        ],
                    ];
                }
                $t['columns'][$columnId]['values']['all']++;
                $valueType = strtolower(gettype($value));
                if (!is_scalar($value) && !is_null($value)) {
                    $t['errors']['nonscalar_values'][$rowId][] = $columnId;
                    continue;
                }
                if (!isset($t['columns'][$columnId]['values'][$valueType])) {
                    $t['columns'][$columnId]['values'][$valueType] = 0;
                }
                $t['columns'][$columnId]['values'][$valueType]++;
            }
        }

        if (!empty($t['key']['values'])) {
            if (count($t['key']['values']) > 1) {
                $t['errors']['mixed_keys'] = true;
            } else {
                reset($t['key']['values']);
                $t['key']['type'] = key($t['key']['values']);
            }
        }

        foreach ($t['columns'] as $id => $column) {
            $types = array_diff(
                array_keys($column['values']),
                ['all', 'null']
            );
            if (count($types) === 1) {
                $t['columns'][$id]['type'] = array_shift($types);
            } elseif (count($types) === 0) {
                $t['columns'][$id]['type'] = 'string';
            } else {
                $t['errors']['inconsistent_value_types'][] = $id;
            }
            $t['columns'][$id]['not_null'] = !isset($column['values']['null'])
                && ($t['rows']['count'] === $column['values']['all']);
        }

        $t['is_valid'] = empty($t['errors']);

        return $t;
    }

    /*--------------------------------------------------------------------*/

    /**
     * Checks whether a row exists.
     *
     * @param string|int $key
     * @return bool
     */
    public function has($key): bool
    {
        return $this->hasRow($key);
    }

    /**
     * Returns a row, associated with a key.
     *
     * @param string|int $key
     * @param mixed $default
     * @return array
     */
    public function get($key, $default = [])
    {
        $value = $this->getRow($key);
        return !empty($value) ? $value : $default;
    }

    /**
     * Adds a new row to the table, if the given key does not already exist.
     *
     * @param string|int $key
     * @param array $value
     * @return Table
     */
    public function add($key, $value)
    {
        return $this->addRow($key, $value, false);
    }

    /**
     * Adds a new row to the table or replaces an existing one.
     *
     * @param string|int $key
     * @param array $value
     * @return Table
     */
    public function set($key, $value)
    {
        return $this->addRow($key, $value, true);
    }

    /**
     * Removes a row from the table.
     *
     * @param string|int $key
     * @return Table
     */
    public function remove($key)
    {
        return $this->deleteRow($key);
    }

    /**
     * Returns a row, associated with a key, and also removes the row
     * from the table.
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public function pull($key, $default = [])
    {
        $value = $this->getRow($key);
        $this->deleteRow($key);

        return !empty($value) ? $value : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function decodeJsonValue($key)
    {
        throw new Exception('JSON fields cannot be expanded');
    }

    /*--------------------------------------------------------------------*/

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->addRow($offset, $value, true);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->deleteRow($offset);
    }

    /*--------------------------------------------------------------------*/

    /**
     * Examines the error log generated by Table.analyze() and throws an
     * InvalidArgumentException if any problems were encountered.
     *
     * @param Map $errors
     * @throws InvalidArgumentException
     */
    protected function checkDataErrors(Map $errors)
    {
        $msg = [];

        if ($errors->has('mixed_keys')) {
            $msg[] = ' - Mixed row keys were detected. The row keys should be'
                . ' either integers or strings, but not a mix of the two.';
        }
        if ($errors->has('invalid_rows')) {
            $msg[] = ' - Invalid rows were detected. All rows should be valid'
                . ' associative arrays.';
        }
        if ($errors->has('invalid_column_ids')) {
            $msg[] = ' - Invalid column IDs were found. All column identifiers'
                . ' should be strings.';
        }
        if ($errors->has('nonscalar_values')) {
            $msg[] = ' - Non-scalar values were found. Column values can only'
                . ' be scalar types (boolean, integer, float, string) or null.';
        }
        if ($errors->has('inconsistent_value_types')) {
            $msg[] = ' - Inconsistent value types were detected. The values for'
                . ' each column should be of the same type or equal to null.';
        }
        if (!empty($msg)) {
            throw new InvalidArgumentException(
                'The given data array is not a valid table:' . PHP_EOL
                . implode(PHP_EOL, $msg)
            );
        }
    }

    /**
     * Consolidates the column constraints passed to the object constructor
     * and the constraints inferred from the data array.
     *
     * @param array $definedColumns
     * @param array $inferredColumns
     */
    protected function mergeColumnDefinitions(
        array &$definedColumns,
        array $inferredColumns
    ) {
        if (empty($definedColumns)) {
            foreach ($inferredColumns as $columnId => $inferredColumn) {
                $inferredColumn = new Map($inferredColumn);
                $definedColumns[$columnId] = [
                    'type' => $inferredColumn->get('type'),
                    'not_null' => $inferredColumn->get('not_null'),
                ];
            }
        } else {
            foreach ($definedColumns as $columnId => $definedColumn) {
                if (!is_array($definedColumn)) {
                    $definedColumn = [];
                }
                if (!isset($inferredColumns[$columnId])
                    || !is_array($inferredColumns[$columnId])) {
                    $inferredColumns[$columnId] = [];
                }
                $definedColumn = new Map($definedColumn);
                $inferredColumn = new Map($inferredColumns[$columnId]);
                $definedColumns[$columnId] = [
                    'type' => $definedColumn->has('type')
                        ? $definedColumn->get('type')
                        : $inferredColumn->get('type'),
                    'not_null' => $definedColumn->has('not_null')
                        ? $definedColumn->get('not_null')
                        : $inferredColumn->get('not_null'),
                ];
            }
        }
    }

    protected function processHtmlExportOptions(array $options): array
    {
        $columnNames = $this->schema->getColumnNames();
        if (isset($options['column_names'])) {
            if (is_array($options['column_names'])) {
                $options['column_names'] = array_filter($options['column_names'], 'is_string');
                if (count($options['column_names']) === count($columnNames)) {
                    $columnNames = array_values($options['column_names']);
                }
            }
        }

        return array_merge(parent::processHtmlExportOptions($options), [
            'include_table_header' => boolval($options['include_table_header'] ?? true),
            'column_names' => $columnNames,
            'column_name_format' => strval($options['column_name_format'] ?? ''),
        ]);
    }
}

// -- End of file
