<?php

declare(strict_types=1);

namespace Jonquil\Foundation;

use Jonquil\Cache\CacheInterface;
use Jonquil\Database\Database;
use Jonquil\Type\Map;
use Jonquil\Type\Table;
use Jonquil\Type\Text;

use DateTime;
use DateInterval;
use Exception;
use InvalidArgumentException;
use LogicException;
use PDO;

/**
 * Class PersistentModel
 * @package Jonquil\Foundation
 */
class PersistentModel extends Model
{
    const DEFAULT_DATA_TYPE = 'text';
    const CACHE_KEY_PREFIX = 'table_data.';
    const CACHE_KEY = null;
    const CACHE_LIMIT = 1000; // records
    const CACHE_OFFSET = 0; // records
    const CACHE_LIFETIME = 7 * 24 * 3600; // seconds
    const COMPARISON_OPERATORS = ['>', '<', '>=', '<=', '=', '!=', '<>'];
    const REGEX_MATCH_OPERATORS = ['~', '~*', '!~', '!~*'];
    const JSONB_OPERATORS = ['?', '?|', '?&'];

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_condition'         => 'Invalid selection condition',
        'invalid_rhs_type'          => 'Invalid right operand type',
        'invalid_comp_operator'     => '%s is not a valid comparison operator',
        'invalid_regex_operator'    => '%s is not a valid regex match operator',
        'invalid_jsonb_operator'    => '%s is not a valid jsonb operator',
        'invalid_primary_key'       => 'Invalid key (%s expected, %s given)',
        'invalid_parameter_array'   => 'Invalid statement parameter array',
        'column_type_mismatch'      => 'Column "%s" must be of "%s" type',
        'empty_column_list'         => 'No target columns have been given',
        'missing_key_column'        => 'Missing column "%s" in primary key',
        'undefined_column'          => 'Column "%s" has not been defined',
        'undefined_primary_key'     => 'Undefined primary key',
        'unknown_data_type'         => 'Unknown data type "%s"',
        'unknown_operator'          => 'Unknown operator "%s"',
        'unsupported_json_update'   => 'Unsupported JSON operation "%s"',
    ];

    /**
     * @var Table
     */
    protected static $cachedData;

	/**
	 * @var Database
	 */
    protected $db;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var array
     */
    protected $fields;

    /**
     * @var string|array
     */
    protected $key;

    /**
     * Initializes the class properties.
     *
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->table = '';
        $this->fields = [];
        $this->key = '';
    }

    /**
     * Adds data records to the static cache container.
     *
     * @param Database $db
     * @param CacheInterface $cache
     */
    public static function cacheData(
        Database $db,
        CacheInterface $cache = null
    ) {
        $model = new static($db);
        $key = $model->getCacheKey();
        $data = is_null($cache) ? null : $cache->get($key);
        if (empty($data)) {
            $data = $model->getAll(static::CACHE_LIMIT, static::CACHE_OFFSET);
            if (!is_null($cache)) {
                $cache->set($key, $data, static::CACHE_LIFETIME);
            }
        }
        static::$cachedData =& $data;
    }

    /**
     * @return string
     */
    public function getCacheKey(): string
    {
        if (!is_null(static::CACHE_KEY)) {
            return (string) static::CACHE_KEY;
        }
        $className = array_pop(explode('\\', static::class));
        return static::CACHE_KEY_PREFIX . (new Text($className))->underscorize();
    }

    /**
     * Checks whether there are cached data records.
     *
     * @return bool
     */
    public function hasCachedData(): bool
    {
        return !empty(static::$cachedData);
    }

    /**
     * Returns all cached data records.
     *
     * @return Table
     */
    public function getCachedData(): Table
    {
        return static::$cachedData ?? new Table();
    }

    /**
     * @param bool $columnsOnly
     * @return array
     */
    public function getFieldNames(bool $columnsOnly = false): array
    {
        if ($columnsOnly) {
            $columns = [];
            foreach ($this->fields as $alias => $field) {
                if (array_key_exists('column', $field)) {
                    $columns[] = $alias;
                }
            }
            return $columns;
        }
        return array_keys($this->fields);
    }

    /**
     * @return array
     */
    public function getColumnRules()
    {
        $columns = [];
        foreach ($this->fields as $alias => $field) {
            if (array_key_exists('column', $field)) {
                $name = $field['column'];
                unset($field['column']);
                $columns[$name] = $field;
            }
        }
        return $columns;
    }

    /**
     * @return array|string
     */
    public function getPrimaryKey()
    {
        return $this->key;
    }

    /**
     * @param array $criteria
     * @return int
     */
    public function getRecordCount(array $criteria = []): int
    {
        if ($this->hasCachedData() && $this->isValidTableSelection($criteria)) {
            return $this->getCachedData()->getRecordCount($criteria);
        }
        $parameters = [];
        $query = 'SELECT COUNT(*) FROM ' . $this->table . ' '
            . $this->getSelectionClause($this->fields, $criteria, $parameters);
        return (int) $this->db->fetch('scalar', $query, $parameters, true);
    }

    /**
     * @param array $criteria
     * @param array $fields
     * @return Map
     */
    public function find(array $criteria = [], array $fields = []): Map
    {
        if (empty($this->table) || empty($this->fields)) {
            return new Map();
        }
        if (empty($fields)) {
            $fields = $this->getFieldNames();
        }
        if ($this->hasCachedData() && $this->isValidTableSelection($criteria)) {
            return $this->getCachedData()->find($criteria, $fields);
        }
        $parameters = [];
        $query = $this->buildQuery(
            $this->table,
            $this->fields,
            $criteria,
            $parameters,
            $fields
        );
        return new Map(
            $this->db->fetch('row', $query, $parameters, true)
        );
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
        if (empty($this->table) || empty($this->fields)) {
            return new Table();
        }
        if (empty($fields)) {
            $fields = $this->getFieldNames();
        }
        if ($this->hasCachedData() && $this->isValidTableSelection($criteria)) {
            return $this->getCachedData()->findAll(
                $criteria,
                $fields,
                $order,
                $limit,
                $offset
            );
        }
        $parameters = [];
        $query = $this->buildQuery(
            $this->table,
            $this->fields,
            $criteria,
            $parameters,
            $fields,
            $order,
            $limit,
            $offset
        );
        return new Table(
            $this->db->fetch('table', $query, $parameters, true)
        );
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
        if (empty($this->table) || empty($this->fields)) {
            return [];
        }
        if ($this->hasCachedData() && $this->isValidTableSelection($criteria)) {
            return $this->getCachedData()->getList(
                $field,
                $criteria,
                $sortAscending,
                $limit,
                $offset
            );
        }
        $parameters = [];
        $query = $this->buildQuery(
            $this->table,
            $this->fields,
            $criteria,
            $parameters,
            [$field],
            [[$field => ($sortAscending ? 'asc' : 'desc')]],
            $limit,
            $offset
        );
        return $this->db->fetch('list', $query, $parameters, true);
    }

    /**
     * @param mixed $key
     * @param array $fields
     * @return Map
     */
    public function fetch($key, array $fields = []): Map
    {
        $criteria = $this->getPrimaryKeyCriteria($key);
        return $this->find($criteria, $fields);
    }

    /**
     * @param array $fields
     * @param array $order
     * @param int $limit
     * @param int $offset
     * @return Table
     */
    public function fetchAll(
        array $fields = [],
        array $order = [],
        int $limit = 0,
        int $offset = 0
    ): Table {
        return $this->findAll([], $fields, $order, $limit, $offset);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return Table
     */
    public function getAll(int $limit = 0, int $offset = 0): Table
    {
        return $this->findAll([], [], [], $limit, $offset);
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param string $operator
     * @return bool
     */
    public function isComparisonOperator(string $operator): bool
    {
        return in_array($operator, static::COMPARISON_OPERATORS);
    }

    /**
     * @param string $operator
     * @return bool
     */
    public function isRegexMatchOperator(string $operator): bool
    {
        return in_array($operator, static::REGEX_MATCH_OPERATORS);
    }

    /**
     * @param string $operator
     * @return bool
     */
    public function isJsonbOperator(string $operator): bool
    {
        return in_array($operator, static::JSONB_OPERATORS);
    }

    /**
     * @param array $criteria
     * @return bool
     */
    public function isValidTableSelection(array $criteria): bool
    {
        foreach ($criteria as $criterion) {
            if (!is_array($criterion) || count($criterion) !== 3) {
                return false;
            }
            $operator = $criterion[1];
            if (!$this->isComparisonOperator($operator)
                && !$this->isRegexMatchOperator($operator)
                && !in_array($operator, ['in', 'not_in'])) {
                return false;
            }
        }
        return true;
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param array $values
     * @return bool
     */
    protected function insertRecord(array $values): bool
    {
        if (empty($this->table) || empty($this->fields)) {
            return false;
        }
        $parameters = [];
        $statement = $this->buildInsertStatement(
            $this->table,
            $this->getColumnRules(),
            $values,
            $parameters
        );

        return $this->executeStatement($statement, $parameters);
    }

    /**
     * @param mixed $key
     * @param array $values
     * @return bool
     */
    protected function updateRecord($key, array $values): bool
    {
        $criteria = $this->getPrimaryKeyCriteria($key);
        return $this->updateMultipleRecords($criteria, $values);
    }

    /**
     * @param array $values
     * @return bool
     */
    protected function updateAllRecords(array $values): bool
    {
        return $this->updateMultipleRecords([], $values);
    }

    /**
     * @param array $criteria
     * @param array $values
     * @return bool
     */
    protected function updateMultipleRecords(
        array $criteria,
        array $values
    ): bool {
        if (empty($this->table) || empty($this->fields)) {
            return false;
        }
        $parameters = [];
        $statement = $this->buildUpdateStatement(
            $this->table,
            $this->fields,
            $this->getColumnRules(),
            $values,
            $criteria,
            $parameters
        );

        return $this->executeStatement($statement, $parameters);
    }

    /**
     * @param mixed $key
     * @param string $column
     * @param string $action
     * @param string $path
     * @param mixed $value
     * @return bool
     */
    protected function updateJsonField(
        $key,
        string $column,
        string $action,
        string $path,
        $value = null
    ): bool {
        $criteria = $this->getPrimaryKeyCriteria($key);
        return $this->updateMultipleJsonFields(
            $criteria,
            $column,
            $action,
            $path,
            $value
        );
    }

    /**
     * @param array $criteria
     * @param string $column
     * @param string $action
     * @param string $path
     * @param mixed $value
     * @return bool
     */
    protected function updateMultipleJsonFields(
        array $criteria,
        string $column,
        string $action,
        string $path,
        $value = null
    ): bool {
        if (empty($this->table) || empty($this->fields)) {
            return false;
        }
        $columns = $this->getColumnRules();
        if (!array_key_exists($column, $columns)) {
            throw new InvalidArgumentException(sprintf(
                self::$errors['undefined_column'], $column
            ));
        } elseif ($columns[$column]['type'] !== 'jsonb') {
            throw new InvalidArgumentException(sprintf(
                self::$errors['column_type_mismatch'], $column, 'jsonb'
            ));
        }
        $value = $this->db->quote(json_encode($value));
        $path = $this->db->quote('{' . str_replace('.', ',', $path) . '}');
        switch ($action) {
            case 'set':
                $value = 'jsonb_set(' . $column . ',' . $path . ','
                    . $value . ', true)';
                break;
            case 'delete':
                $value = $column . ' #- ' . $path;
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    self::$errors['unsupported_json_update'], $action
                ));
        }
        $parameters = [];
        $statement = implode(' ', [
            'UPDATE ' . $this->table,
            'SET ' . $column . ' = ' . $value,
            $this->getSelectionClause($this->fields, $criteria, $parameters),
        ]);

        return $this->executeStatement($statement, $parameters);
    }

    /**
     * @param mixed $key
     * @return bool
     */
    protected function deleteRecord($key): bool
    {
        $criteria = $this->getPrimaryKeyCriteria($key);
        return $this->deleteMultipleRecords($criteria);
    }

    /**
     * @return bool
     */
    protected function deleteAllRecords(): bool
    {
        return $this->deleteMultipleRecords([]);
    }

    /**
     * @param array $criteria
     * @return bool
     */
    protected function deleteMultipleRecords(array $criteria): bool
    {
        if (empty($this->table) || empty($this->fields)) {
            return false;
        }
        $parameters = [];
        $statement = $this->buildDeleteStatement(
            $this->table,
            $this->fields,
            $criteria,
            $parameters
        );

        return $this->executeStatement($statement, $parameters);
    }

    /**
     * @param string $table
     * @param array $rules
     * @param array $criteria
     * @param array $parameters
     * @param array $fields
     * @param array $order
     * @param int $limit
     * @param int $offset
     * @return string
     */
    protected function buildQuery(
        string $table,
        array $rules,
        array $criteria = [],
        array &$parameters = [],
        array $fields = [],
        array $order = [],
        int $limit = 0,
        int $offset = 0
    ): string {
        return implode(' ', [
            $this->getProjectionClause($rules, $fields),
            'FROM ' . $table,
            $this->getSelectionClause($rules, $criteria, $parameters),
            $this->getSortOrderClause($fields, $order),
            $this->getLimitClause($limit, $offset),
        ]);
    }

    /**
     * @param string $table
     * @param array $columns
     * @param array $values
     * @param array $parameters
     * @return string
     */
    protected function buildInsertStatement(
        string $table,
        array $columns,
        array $values,
        array &$parameters = []
    ): string {
        $columnList = [];
        $valueList = [];
        foreach ($values as $column => $value) {
            if (!array_key_exists($column, $columns)) {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['undefined_column'], $column
                ));
            }
            $symbol = ':' . $column;
            $type = array_key_exists('type', $columns[$column])
                ? $columns[$column]['type'] : static::DEFAULT_DATA_TYPE;
            $columnList[] = $column;
            $valueList[] = $symbol;
            $parameters[] = [$symbol, $value, $this->getPdoDataType($type)];
        }
        if (empty($columnList)) {
            throw new InvalidArgumentException(
                self::$errors['empty_column_list']
            );
        }
        return 'INSERT'
            . ' INTO ' . $table . ' (' . implode(', ', $columnList) . ')'
            . ' VALUES (' . implode(', ', $valueList) . ')';
    }

    /**
     * @param string $table
     * @param array $fieldRules
     * @param array $columnRules
     * @param array $values
     * @param array $criteria
     * @param array $parameters
     * @return string
     */
    protected function buildUpdateStatement(
        string $table,
        array $fieldRules,
        array $columnRules,
        array $values,
        array $criteria = [],
        array &$parameters = []
    ): string {
        return implode(' ', [
            'UPDATE ' . $table,
            $this->getUpdateClause($columnRules, $values, $parameters),
            $this->getSelectionClause($fieldRules, $criteria, $parameters),
        ]);
    }

    /**
     * @param string $table
     * @param array $rules
     * @param array $criteria
     * @param array $parameters
     * @return string
     */
    protected function buildDeleteStatement(
        string $table,
        array $rules,
        array $criteria = [],
        array &$parameters = []
    ): string {
        return implode(' ', [
            'DELETE FROM ' . $table,
            $this->getSelectionClause($rules, $criteria, $parameters),
        ]);
    }

    /**
     * @param array $rules
     * @param array $values
     * @param array $parameters
     * @return string
     */
    protected function getUpdateClause(
        array $rules,
        array $values,
        array &$parameters
    ): string {
        $updates = [];
        foreach ($values as $column => $value) {
            if (!array_key_exists($column, $rules)) {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['undefined_column'], $column
                ));
            }
            $symbol = ':' . $column;
            $type = array_key_exists('type', $rules[$column])
                ? $rules[$column]['type'] : static::DEFAULT_DATA_TYPE;
            $updates[] = $column . ' = ' . $symbol;
            $parameters[] = [$symbol, $value, $this->getPdoDataType($type)];
        }
        $clause = implode(', ', $updates);
        return !empty($clause) ? 'SET ' . $clause  : '';
    }

    /**
     * @param array $rules
     * @param array $criteria
     * @param array $parameters
     * @return string
     */
    protected function getSelectionClause(
        array $rules,
        array $criteria,
        array &$parameters
    ): string {
        $n = 0;
        $conditions = [];
        foreach ($criteria as $criterion) {
            if (!is_array($criterion) || count($criterion) !== 3) {
                throw new InvalidArgumentException(
                    self::$errors['invalid_condition']
                );
            }
            list($field, $operator, $value) = $criterion;
            if (!is_string($field)
                || !array_key_exists($field, $rules)
                || !is_array($rules[$field])) {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['undefined_column'], $field
                ));
            }
            $expression = array_key_exists('column', $rules[$field])
                ? $rules[$field]['column'] : $rules[$field]['expression'];
            $type = array_key_exists('type', $rules[$field])
                ? $rules[$field]['type'] : static::DEFAULT_DATA_TYPE;
            $symbol = ':' . $field . '_' . ++$n;
            $conditions[] = $this->getCondition(
                $expression,
                $operator,
                $value,
                $symbol,
                $type,
                $parameters
            );
        }
        $clause = implode(' AND ', $conditions);
        return !empty($clause) ? 'WHERE ' . $clause  : '';
    }

    /**
     * @param array $rules
     * @param array $fields
     * @return string
     */
    protected function getProjectionClause(
        array $rules,
        array &$fields
    ): string {
        $expressions = [];
        $selectedFields = [];
        foreach ($fields as $field) {
            if (is_string($field) && array_key_exists($field, $rules)) {
                if (!is_array($rules[$field])) {
                    throw new InvalidArgumentException(sprintf(
                        self::$errors['undefined_column'], $field
                    ));
                }
                $expression = array_key_exists('column', $rules[$field])
                    ? $rules[$field]['column'] : $rules[$field]['expression'];
                if ($expression !== $field) {
                    $expression .= ' AS ' . $field;
                }
                $expressions[] = $expression;
                $selectedFields[] = $field;
            }
            else {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['undefined_column'], $field
                ));
            }
        }
        $fields = $selectedFields;
        $clause = !empty($expressions) ? implode(', ', $expressions) : '*';
        return 'SELECT ' . $clause;
    }

    /**
     * @param array $fields
     * @param array $order
     * @return string
     */
    protected function getSortOrderClause(
        array $fields,
        array $order
    ): string {
        $expressions = [];
        foreach ($order as $field) {
            $direction = 'ASC';
            if (is_array($field) && !empty($field)) {
                if (strtoupper(reset($field)) === 'DESC') {
                    $direction = 'DESC';
                }
                $field = key($field);
            }
            if (is_string($field) && in_array($field, $fields)) {
                $expressions[] = $field . ' ' . $direction;
            } else {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['undefined_column'], $field
                ));
            }
        }
        $clause = implode(', ', $expressions);
        return !empty($clause) ? 'ORDER BY ' . $clause : '';
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return string
     */
    protected function getLimitClause(
        int $limit = 0,
        int $offset = 0
    ): string {
        $clause = 'LIMIT ' . ($limit > 0 ? $limit : 'ALL');
        if ($offset > 0) {
            $clause .= ' OFFSET ' . $offset;
        }
        return $clause;
    }

    /**
     * @param string $expression
     * @param string $operator
     * @param $value
     * @param string $symbol
     * @param string $type
     * @param array $parameters
     * @return string
     */
    protected function getCondition(
        string $expression,
        string $operator,
        $value,
        string $symbol,
        string $type,
        array &$parameters
    ): string {
        switch ($operator) {
            // Comparison operators
            case '>':
            case '<':
            case '>=':
            case '<=':
            case '=':
            case '!=':
            case '<>':
                return $this->getComparisonClause(
                    $expression,
                    $operator,
                    $value,
                    $symbol,
                    $type,
                    $parameters
                );
            // JSON operators
            case '?':
            case '?|':
            case '?&':
                return $this->getJsonbClause(
                    $expression,
                    $operator,
                    $value
                );
            // Regular expression match operators
            case '~':
            case '~*':
            case '!~':
            case '!~*':
                return $this->getPatternMatchingClause(
                    $expression,
                    $operator,
                    $value
                );
            // Logical operators
            case 'or':
                return $this->getDisjunctiveClause(
                    $expression,
                    $value,
                    $symbol,
                    $type,
                    $parameters
                );
            // Subquery expressions
            case 'in':
            case 'not_in':
                return $this->getSubqueryExpression(
                    $expression,
                    $operator,
                    $value,
                    $type
                );
            default:
                throw new InvalidArgumentException(sprintf(
                    self::$errors['unknown_operator'], $operator
                ));
        }
    }

    /**
     * @param string $expression
     * @param string $operator
     * @param $value
     * @param string $symbol
     * @param string $type
     * @param array $parameters
     * @return string
     */
    protected function getComparisonClause(
        string $expression,
        string $operator,
        $value,
        string $symbol,
        string $type,
        array &$parameters
    ): string {
        if (!$this->isComparisonOperator($operator)) {
            throw new InvalidArgumentException(sprintf(
                self::$errors['invalid_comp_operator'], $operator
            ));
        }
        if (in_array($operator, ['=', '!=', '<>'])
            && in_array($value, [true, false, null], true)) {
            if ($value === true) {
                $value = 'TRUE';
            } elseif ($value === false) {
                $value = 'FALSE';
            } elseif ($value === null) {
                $value = 'NULL';
            }
            $operator = ($operator === '=' ? 'IS' : 'IS NOT');
            return '(' . $expression . ' ' . $operator . ' ' . $value . ')';
        } else {
            $parameters[] = [$symbol, $value, $this->getPdoDataType($type)];
            return '(' . $expression . ' ' . $operator . ' ' . $symbol . ')';
        }
    }

    /**
     * @param string $expression
     * @param string $operator
     * @param $value
     * @return string
     */
    protected function getJsonbClause(
        string $expression,
        string $operator,
        $value
    ): string {
        if (!$this->isJsonbOperator($operator)) {
            throw new InvalidArgumentException(sprintf(
                self::$errors['invalid_jsonb_operator'], $operator
            ));
        }
        if ($operator === '?') {
            if (!is_scalar($value)) {
                throw new InvalidArgumentException(
                    self::$errors['invalid_rhs_type']
                );
            }
            $value = $this->db->quote($value, PDO::PARAM_STR);
        } else {
            if (!is_array($value)) {
                throw new InvalidArgumentException(
                    self::$errors['invalid_rhs_type']
                );
            }
            $value = array_map(
                function ($value) {
                    return $this->db->quote($value, PDO::PARAM_STR);
                },
                $value
            );
            $value = 'array[' . implode(', ', $value) . ']';
        }

        return '(' . $expression . ' ' . $operator . ' ' . $value . ')';
    }

    /**
     * @param string $expression
     * @param string $operator
     * @param $value
     * @return string
     */
    protected function getPatternMatchingClause(
        string $expression,
        string $operator,
        $value
    ): string {
        if (!$this->isRegexMatchOperator($operator)) {
            throw new InvalidArgumentException(sprintf(
                self::$errors['invalid_regex_operator'], $operator
            ));
        }
        $value = $this->db->quote(strval($value), PDO::PARAM_STR);
        return '((' . $expression . ')::text ' . $operator . ' ' . $value . ')';
    }

    /**
     * @param string $expression
     * @param array $conditions
     * @param string $symbol
     * @param string $type
     * @param array $parameters
     * @return string
     */
    protected function getDisjunctiveClause(
        string $expression,
        array $conditions,
        string $symbol,
        string $type,
        array &$parameters
    ): string {
        $n = 0;
        $parsedConditions = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition) || count($condition) !== 2) {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['invalid_condition']
                ));
            }
            list($operator, $value) = $condition;
            $symbol = $symbol . '_' . ++$n;
            $parsedConditions[] = $this->getCondition(
                $expression,
                $operator,
                $value,
                $symbol,
                $type,
                $parameters
            );
        }
        return '(' . implode(' OR ', $parsedConditions) . ')';
    }

    /**
     * @param string $expression
     * @param string $operator
     * @param $value
     * @param string $type
     * @param bool $useValuesList
     * @return string
     */
    protected function getSubqueryExpression(
        string $expression,
        string $operator,
        $value,
        string $type,
        bool $useValuesList = true
    ): string {
        if (is_array($value)) {
            $value = array_unique($value);
        } else {
            $value = [$value];
        }
        $operator = strtoupper($operator);
        $value = array_map(
            function ($value) use ($type) {
                $value = $this->db->quote(
                    strval($value),
                    $this->getPdoDataType($type)
                );
                return '(' . $value . '::' . $type . ')';
            },
            $value
        );
        $value = '(' . ($useValuesList ? 'VALUES ' : '') . implode(', ', $value) . ')';
        return '(' . $expression . ' ' . $operator . ' ' . $value . ')';
    }

    /**
     * @param mixed $key
     * @return array
     */
    protected function getPrimaryKeyCriteria($key): array
    {
        if (empty($this->key)) {
            throw new LogicException(self::$errors['undefined_primary_key']);
        }
        $criteria = [];
        if (is_array($key)) {
            if (!is_array($this->key)) {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['invalid_primary_key'], 'scalar', 'array'
                ));
            }
            foreach ($this->key as $column) {
                if (array_key_exists($column, $key)) {
                    $criteria[] = [$column, '=', $key[$column]];
                } else {
                    throw new InvalidArgumentException(sprintf(
                        self::$errors['missing_key_column'], $column
                    ));
                }
            }
        } elseif (is_scalar($key)) {
            if (!is_scalar($this->key)) {
                throw new InvalidArgumentException(sprintf(
                    self::$errors['invalid_primary_key'], 'array', 'scalar'
                ));
            }
            $criteria[] = [$this->key, '=', $key];
        } else {
            throw new InvalidArgumentException(sprintf(
                self::$errors['invalid_primary_key'],
                is_array($this->key) ? 'array' : 'scalar',
                gettype($key)
            ));
        }
        return $criteria;
    }

    /**
     * @param string $type
     * @return int
     */
    protected function getPdoDataType(string $type): int
    {
        switch ($type) {
            case 'uuid':
            case 'text':
            case 'jsonb':
            case 'double':
            case 'timestamp':
            case 'inet':
                return PDO::PARAM_STR;
            case 'int':
                return PDO::PARAM_INT;
            case 'boolean':
                return PDO::PARAM_BOOL;
            case 'bytea':
                return PDO::PARAM_LOB;
            case 'null':
                return PDO::PARAM_NULL;
            default:
                throw new InvalidArgumentException(sprintf(
                    self::$errors['unknown_data_type'], $type
                ));
        }
    }

    /**
     * @param string $statement
     * @param array $parameters
     * @return bool
     */
    protected function executeStatement(
        string $statement,
        array $parameters
    ): bool {
        $statement = $this->db->prepareStatement($statement);

        foreach ($parameters as $param) {
            if (!is_array($param) || (count($param) != 3)) {
                throw new InvalidArgumentException(
                    self::$errors['invalid_parameter_array']
                );
            }
            $statement->bindParam($param[0], $param[1], $param[2]);
        }

        return $statement->execute();
    }

    /**
     * @param string $date
     * @param string $offset
     * @param string $format
     * @return string
     */
    protected function parseDate(string $date, string $offset = '', string $format = DATE_ATOM): string
    {
        $date = trim($date);
        if (empty($date)) {
            return '';
        }
        try {
            $date = new DateTime($date);
            if ($offset) {
                if (substr($offset, 0, 1) === '-') {
                    $addInterval = false;
                    $offset = substr($offset, 1);
                } else {
                    $addInterval = true;
                }
                $interval = new DateInterval($offset);
                if ($addInterval) {
                    $date->add($interval);
                } else {
                    $date->sub($interval);
                }
            }
            $date = $date->format($format);
        } catch (Exception $e) {
            return '';
        }

        return $date;
    }
}

// -- End of file
