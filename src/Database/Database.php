<?php

declare(strict_types=1);

namespace Jonquil\Database;

/**
 * Class Database
 * @package Jonquil\Database
 */
class Database
{
    /**
     * @var array Error messages
     */
    protected static $errors = [
        'extension_not_loaded'      => 'The PDO extension is not loaded',
        'undefined_dsn'             => 'Undefined data source name',
        'undefined_username'        => 'Undefined username',
        'undefined_password'        => 'Undefined password',
        'unknown_fetch_type'        => 'Unknown fetch type',
        'invalid_parameter_array'   => 'Invalid statement parameter array',
    ];

    /**
     * @var array
     */
	protected $config;

    /**
     * @var null|\PDO
     */
    protected $connection;

    /**
     * Initializes the class properties.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param bool $persistent
     */
	public function __construct(
        string $dsn,
        string $username,
        string $password,
        bool $persistent = false
    ) {
        if (!extension_loaded('pdo')) {
            throw new \RuntimeException(
                self::$errors['extension_not_loaded']
            );
        } elseif (empty($dsn)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_dsn']
            );
        } elseif (empty($username)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_username']
            );
        } elseif (empty($password)) {
            throw new \InvalidArgumentException(
                self::$errors['undefined_password']
            );
        }

        $this->config = [
            'dsn'       => $dsn,
            'username'  => $username,
            'password'  => $password,
            'options'   => [
                \PDO::ATTR_PERSISTENT => $persistent,
            ],
        ];
        $this->connection = null;
        $this->connect();
	}

    /**
     * @return null|\PDO
     */
	public function getDataObject()
	{
		return $this->connection;
	}

    /**
     * Initiates a transaction
     */
    public function startTransaction()
    {
        $this->connect();
        $this->connection->beginTransaction();
    }

    /**
     * Commits a transaction
     */
    public function commitTransaction()
    {
        $this->connect();
        $this->connection->commit();
    }

    /**
     * Rolls back a transaction
     */
    public function abortTransaction()
    {
        $this->connect();
        $this->connection->rollBack();
    }

    /**
     * Executes an SQL statement and returns a result set
     * as a PDOStatement object.
     *
     * @param string $queryString
     * @return \PDOStatement
     */
	public function executeQuery(string $queryString): \PDOStatement
	{
		return $this->connection->query($queryString);
	}

    /**
     * Executes an SQL statement and returns the number of affected rows.
     *
     * @param string $queryString
     * @return int
     */
	public function executeUpdate(string $queryString): int
	{
		return $this->connection->exec($queryString);
	}

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param string $queryString
     * @param array $options
     * @return \PDOStatement
     */
	public function prepareStatement(
        string $queryString,
        array $options = []
    ): \PDOStatement {
		return $this->connection->prepare($queryString, $options);
	}

    /**
     * Fetches data from the database.
     *
     * @param string $type
     * @param string $queryString
     * @param array $parameters
     * @param bool $bind
     * @return mixed
     */
    public function fetch(
        string $type,
        string $queryString,
        array $parameters = [],
        bool $bind = false
    ) {
        $statement = $this->prepareStatement($queryString);

        if ($bind) {
            foreach ($parameters as $param) {
                if (!is_array($param) || (count($param) != 3)) {
                    throw new \InvalidArgumentException(
                        self::$errors['invalid_parameter_array']
                    );
                }
                $statement->bindParam($param[0], $param[1], $param[2]);
            }
            $statement->execute();
        } else {
            if (!is_array($parameters)) {
                $parameters = array($parameters);
            }
            $statement->execute($parameters);
        }

        switch ($type) {
            case 'table':
                return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            case 'list':
                return $statement->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            case 'row':
                return $statement->fetch(\PDO::FETCH_ASSOC) ?: [];
            case 'object':
                return $statement->fetch(\PDO::FETCH_OBJ) ?: (object) [];
            case 'scalar':
                return $statement->fetchColumn() ?: null;
            default:
                throw new \InvalidArgumentException(
                    self::$errors['unknown_fetch_type']
                );
        }
    }

    /**
     * Quotes a string for use in a query.
     *
     * @param string $string
     * @param int $parameterType
     * @return string
     */
	public function quote(
        string $string,
        int $parameterType = \PDO::PARAM_STR
    ): string {
		return $this->connection->quote($string, $parameterType);
	}

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string $name
     * @return mixed
     */
	public function getLastInsertId(string $name = null)
	{
		return $this->connection->lastInsertId($name);
	}

    /**
     * Fetches the SQLSTATE associated with
     * the last operation on the statement handle.
     *
     * @return string
     */
	public function getErrorCode(): string
	{
		return $this->connection->errorCode();
	}

    /**
     * Fetches extended error information associated with
     * the last operation on the statement handle.
     *
     * @return array
     */
	public function getErrorDetails(): array
	{
		return $this->connection->errorInfo();
	}

    /**
     * Returns an array of available PDO drivers.
     *
     * @return array
     */
	public function getAvailableDrivers(): array
	{
		return $this->connection->getAvailableDrivers();
	}

    /**
     * Retrieves a statement attribute.
     *
     * @param int $attribute
     * @return mixed
     */
    public function getAttribute(int $attribute)
	{
		return $this->connection->getAttribute($attribute);
	}

    /**
     * Sets a statement attribute.
     *
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
	public function setAttribute(int $attribute, $value): bool
	{
		return $this->connection->setAttribute($attribute, $value);
	}

    /**
     * Checks if a database connection has been established.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connection instanceof \PDO;
    }

    /**
     * Deletes a PDO instance representing a connection to a database.
     */
    public function closeConnection()
    {
        $this->connection = null;
    }

    /**
     * Creates a PDO instance representing a connection to a database.
     */
    protected function connect()
    {
        if ($this->isConnected()) {
            return;
        }
        try {
            $this->connection = new \PDO(
                $this->config['dsn'],
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
            $this->connection->setAttribute(
                \PDO::ATTR_ERRMODE,
                \PDO::ERRMODE_EXCEPTION
            );
        }
        catch (\PDOException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}

// -- End of file
