<?php

declare(strict_types=1);

namespace Jonquil\Session;

/**
 * PDO Session Handler
 * @package Jonquil\Session
 */
class PdoSessionHandler implements \SessionHandlerInterface
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_pdo_object'    => 'Invalid PDO object',
        'pdo_exception'         => 'PDOException: %s',
        'missing_option'        => '"%s" has not been provided',
    ];

    /**
     * @var \PDO A PDO instance
     */
    protected $pdo;

    /**
     * @var string Sessions table name
     */
    protected $table;

    /**
     * @var string Session ID column name
     */
    protected $id;

    /**
     * @var string Session data column name
     */
    protected $data;

    /**
     * @var string Start timestamp column name
     */
    protected $startTime;

    /**
     * @var string Last accessed timestamp column name
     */
    protected $lastAccessTime;

    /**
     * @var string Read request counter column name
     */
    protected $requests;

    /**
     * Initializes the class properties.
     *
     * List of required options:
     *  * table: Sessions table name
     *  * id_column: Session ID column name
     *  * data_column: Session data column name
     *  * start_column: Start timestamp column name
     *  * last_access_column: Last accessed timestamp column name
     *  * requests_column: Request counter column name
     *
     * @param \PDO $pdo A PDO instance
     * @param array $options An associative array of database options
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(\PDO $pdo, array $options = [])
    {
        if (!($pdo instanceof \PDO)) {
            throw new \InvalidArgumentException(
                self::$errors['invalid_pdo_object']
            );
        }

        $requiredOptions = ['table', 'id_column', 'data_column',
            'start_column', 'last_access_column', 'requests_column'];

        foreach ($requiredOptions as $requiredOption) {
            if (!array_key_exists($requiredOption, $options)) {
                throw new \InvalidArgumentException(
                    sprintf(self::$errors['missing_option'], $requiredOption)
                );
            }
        }

        $this->pdo = $pdo;

        $this->table = $options['table'];
        $this->id = $options['id_column'];
        $this->data = $options['data_column'];
        $this->startTime = $options['start_column'];
        $this->lastAccessTime = $options['last_access_column'];
        $this->requests = $options['requests_column'];
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionId): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId): string
    {
        $currentTimestamp = date(self::DATE_FORMAT);
        try {
            $statement = $this->pdo->prepare("
                SELECT {$this->data}
                FROM {$this->table}
                WHERE {$this->id} = :id
            ");
            $statement->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $statement->execute();

            $encodedSessionData = $statement->fetchColumn();

            if ($encodedSessionData === false) {
                // Create a new Session
                $this->createNewSession($sessionId);
                return '';
            }

            // Increment the request counter for the Session
            $statement = $this->pdo->prepare("
                UPDATE {$this->table}
                SET
                    {$this->lastAccessTime} = :last_access_timestamp,
                    {$this->requests} = {$this->requests} + 1
                WHERE {$this->id} = :id
            ");
            $statement->bindParam(
                ':id',
                $sessionId,
                \PDO::PARAM_STR
            );
            $statement->bindParam(
                ':last_access_timestamp',
                $currentTimestamp,
                \PDO::PARAM_STR
            );
            $statement->execute();

            return base64_decode($encodedSessionData);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                sprintf(self::$errors['pdo_exception'], $e->getMessage()),
                0, $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $sessionData): bool
    {
        $encodedSessionData = base64_encode($sessionData);
        $currentTimestamp = date(self::DATE_FORMAT);

        try {
            $statement = $this->pdo->prepare("
                UPDATE {$this->table}
                SET
                    {$this->data} = :data,
                    {$this->lastAccessTime} = :last_access_timestamp
                WHERE {$this->id} = :id
            ");
            $statement->bindParam(
                ':id',
                $sessionId,
                \PDO::PARAM_STR
            );
            $statement->bindParam(
                ':data',
                $encodedSessionData,
                \PDO::PARAM_STR
            );
            $statement->bindParam(
                ':last_access_timestamp',
                $currentTimestamp,
                \PDO::PARAM_STR
            );
            $statement->execute();

            if (!$statement->rowCount()) {
                $this->createNewSession($sessionId, $sessionData);
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                sprintf(self::$errors['pdo_exception'], $e->getMessage()),
                0, $e
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId): bool
    {
        try {
            $statement = $this->pdo->prepare("
                DELETE FROM {$this->table}
                WHERE {$this->id} = :id
            ");
            $statement->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $statement->execute();
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                sprintf(self::$errors['pdo_exception'], $e->getMessage()),
                0, $e
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function gc($maxLifetime): bool
    {
        $timeThreshold = date(self::DATE_FORMAT, time() - $maxLifetime);

        try {
            $statement = $this->pdo->prepare("
                DELETE FROM {$this->table}
                WHERE {$this->lastAccessTime} < :last_access_timestamp
            ");
            $statement->bindValue(
                ':last_access_timestamp',
                $timeThreshold,
                \PDO::PARAM_STR
            );
            $statement->execute();
        }
        catch (\PDOException $e) {
            throw new \RuntimeException(
                sprintf(self::$errors['pdo_exception'], $e->getMessage()),
                0, $e
            );
        }

        return true;
    }

    /**
     * Creates a new session.
     *
     * @param string $sessionId Session ID
     * @param string $sessionData Session data
     *
     * @return bool
     */
    protected function createNewSession(
        string $sessionId,
        string $sessionData = ''
    ): bool {
        $encodedSessionData = base64_encode($sessionData);
        $currentTimestamp = date(self::DATE_FORMAT);
        $requests = 0;

        $statement = $this->pdo->prepare("
            INSERT INTO {$this->table}
                ({$this->id}, {$this->data}, {$this->startTime},
                {$this->lastAccessTime}, {$this->requests})
            VALUES
                (:id, :data, :start_timestamp,
                :last_access_timestamp, :requests)
        ");

        $statement->bindParam(
            ':id',
            $sessionId,
            \PDO::PARAM_STR
        );
        $statement->bindParam(
            ':data',
            $encodedSessionData,
            \PDO::PARAM_STR
        );
        $statement->bindParam(
            ':start_timestamp',
            $currentTimestamp,
            \PDO::PARAM_STR
        );
        $statement->bindParam(
            ':last_access_timestamp',
            $currentTimestamp,
            \PDO::PARAM_STR
        );
        $statement->bindParam(
            ':requests',
            $requests,
            \PDO::PARAM_INT
        );

        $statement->execute();

        return true;
    }
}

// -- End of file
