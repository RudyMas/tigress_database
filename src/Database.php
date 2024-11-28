<?php

namespace Tigress;

use Exception;
use PDO;

/**
 * Class Database (PHP version 8.4)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2024.11.28.0
 * @package Tigress
 */
class Database extends PDO
{
    /**
     * @var int
     */
    private int $rows;
    /**
     * @var int
     */
    private int $iterator = 0;

    /**
     * @var array
     */
    private array $internalData;

    /**
     * Get the version of the Database
     *
     * @return string
     */
    public static function version(): string
    {
        return '2024.11.28';
    }

    /**
     * Database constructor.
     *
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string $dbname
     * @param string $charset
     * @param string $dbType
     * @param string $timezone
     * @throws Exception
     */
    public function __construct(
        string $host = 'localhost',
        int    $port = 3306,
        string $username = 'username',
        string $password = 'password',
        string $dbname = 'dbname',
        string $charset = 'utf8',
        string $dbType = 'mysql',
        string $timezone = 'Europe/Brussels'
    )
    {
        switch (strtolower($dbType)) {
            case 'mysql':
                parent::__construct("mysql:host={$host};port={$port};charset={$charset};dbname={$dbname}", $username, $password, [PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '{$timezone}'"]);
                parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                parent::setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
                break;
            case 'mssql':
            case 'sybase':
                parent::__construct("sqlsrv:server = tcp:{$host},{$port}; Database = {$dbname}", $username, $password);
                parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                parent::setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
                break;
            case 'odbc_mssql':
            case 'odbc_sybase':
                parent::__construct("odbc:Driver={ODBC Driver 17 for SQL Server};Server={$host},{$port};Database={$dbname}", $username, $password);
                parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                parent::setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
                break;
            default:
                throw new Exception("$dbType isn't implemented yet!", 500);
        }
    }

    /**
     * Query the database
     *
     * @param string $query
     * @param int|null $fetchMode
     * @param ...$fetch_mode_args
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): bool
    {
        $result = parent::query($query, $fetchMode, $fetch_mode_args);
        if ($result === false) {
            $this->internalData = [];
            $this->rows = 0;
            return false;
        }
        $this->internalData = $result->fetchAll(PDO::FETCH_OBJ);
        $this->rows = count($this->internalData);
        $this->iterator = 0;
        return true;
    }

    /**
     * Get a single row by query
     *
     * @param string $query
     * @return object|false
     */
    public function fetchRowByQuery(string $query): object|false
    {
        $this->query($query);
        if ($this->rows === 0) {
            return false;
        }
        return $this->fetch(0);
    }

    /**
     * Get a single item by query
     *
     * @param string $query
     * @param string $field
     * @return mixed
     */
    public function fetchItemByQuery(string $query, string $field): mixed
    {
        $this->query($query);
        if ($this->rows === 0) {
            return false;
        }
        $data = $this->fetch(0);
        return $data->$field;
    }

    /**
     * Insert record(s)
     *
     * @param string $query
     * @return void
     */
    public function insert(string $query): void
    {
        $this->execQuery($query);
    }

    /**
     * Update record(s)
     *
     * @param string $query
     * @return void
     */
    public function update(string $query): void
    {
        $this->execQuery($query);
    }

    /**
     * Delete record(s)
     *
     * @param string $query
     * @return void
     */
    public function delete(string $query): void
    {
        $this->execQuery($query);
    }

    /**
     * Fetch a row from the internal data
     *
     * @param int $row
     * @return object
     */
    public function fetch(int $row): object
    {
        return $this->internalData[$row];
    }

    /**
     * Copy the internal data to the data property
     *
     * @return array
     */
    public function fetchAll(): array
    {
        return $this->internalData;
    }

    /**
     * Fetch the current row
     *
     * @return object
     */
    public function fetchCurrent(): object
    {
        return $this->internalData[$this->iterator];
    }

    /**
     * Fetch the next row
     *
     * @return object
     */
    public function fetchNext(): object
    {
        $this->iterator++;
        if ($this->iterator >= $this->rows) {
            $this->iterator = $this->rows - 1;
        }
        return $this->internalData[$this->iterator];
    }

    /**
     * Fetch the previous row
     *
     * @return object
     */
    public function fetchPrevious(): object
    {
        $this->iterator--;
        if ($this->iterator < 0) {
            $this->iterator = 0;
        }
        return $this->internalData[$this->iterator];
    }

    /**
     * Run a select query with key bindings
     *
     * @param string $sql
     * @param array $keyBindings
     * @return bool
     */
    public function selectQuery(string $sql, array $keyBindings = []): bool
    {
        $stmt = $this->prepare($sql);
        foreach ($keyBindings as $key => $value) {
            $stmt->bindValue($key, $value, $this->getDataType($value));
        }
        $result = $stmt->execute();
        $this->internalData = $stmt->fetchAll(PDO::FETCH_OBJ);
        $this->rows = count($this->internalData);
        $this->iterator = 0;
        return $result;
    }

    /**
     * Run a insert query with key bindings
     *
     * @param string $sql
     * @param array $keyBindings
     * @return bool
     */
    public function insertQuery(string $sql, array $keyBindings = []): bool
    {
        return $this->runQuery($sql, $keyBindings);
    }

    /**
     * Run a update query with key bindings
     *
     * @param string $sql
     * @param array $keyBindings
     * @return bool
     */
    public function updateQuery(string $sql, array $keyBindings = []): bool
    {
        return $this->runQuery($sql, $keyBindings);
    }

    /**
     * Run a delete query with key bindings
     *
     * @param string $sql
     * @param array $keyBindings
     * @return bool
     */
    public function deleteQuery(string $sql, array $keyBindings = []): bool
    {
        return $this->runQuery($sql, $keyBindings);
    }

    /**
     * Quote a string
     *
     * @param string|null $string
     * @return string
     */
    public function cleanSQL(?string $string = null): string
    {
        return ($string === null) ? parent::quote(null) : parent::quote($string);
    }

    /**
     * Execute a query
     *
     * @param string $query
     * @return void
     */
    private function execQuery(string $query): void
    {
        $this->rows = parent::exec($query);
    }

    /**
     * Run a query with key bindings
     *
     * @param string $sql
     * @param array $keyBindings
     * @return bool
     */
    private function runQuery(string $sql, array $keyBindings = []): bool
    {
        $stmt = $this->prepare($sql);
        foreach ($keyBindings as $key => $value) {
            $stmt->bindValue($key, $value, $this->getDataType($value));
        }
        return $stmt->execute();
    }

    /**
     * Get the data type of the value
     *
     * @param mixed $value
     * @return int
     */
    private function getDataType(mixed $value): int
    {
        if (is_int($value)) return PDO::PARAM_INT;
        if (is_bool($value)) return PDO::PARAM_BOOL;
        if (is_null($value)) return PDO::PARAM_NULL;
        return PDO::PARAM_STR;
    }

    public function getRows(): int
    {
        return $this->rows;
    }
}