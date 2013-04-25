<?php
/**
 * MySQL store driver.
 * 
 * @package Knit
 * @subpackage Store
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Knit\Store;

use PDO;
use PDOException;
use PDOStatement;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Utils\StringUtils;

use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Exceptions\StoreQueryErrorException;
use Knit\Store\StoreInterface;
use Knit\Knit;

class MySQLStore implements StoreInterface
{

    protected $hostname;
    protected $username;
    protected $password;
    protected $database;

    protected $connection;

    protected $logger;

    protected static $mysqlToKnitType = array(
        'tinyint' => Knit::TYPE_INT,
        'smallint' => Knit::TYPE_INT,
        'mediumint' => Knit::TYPE_INT,
        'bigint' => Knit::TYPE_INT,
        'int' => Knit::TYPE_INT,
        'float' => Knit::TYPE_FLOAT,
        'varchar' => Knit::TYPE_STRING,
        'mediumtext' => Knit::TYPE_STRING,
        'tinytext' => Knit::TYPE_STRING,
        'text' => Knit::TYPE_STRING,
        'enum' => Knit::TYPE_ENUM
    );

    public function __construct($hostname, $username, $password, $database) {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;

        $this->logger = new NullLogger();
    }

    /*****************************************************
     * STORE INTERFACE IMPLEMENTATION
     *****************************************************/
    public function find($table, array $criteria = array(), array $params = array()) {
        $criteria = $this->parseCriteria($criteria);

        throw new \RuntimeException('@TODO: MySQLStore::find()');

        /*
        
        // parse any optional params
        $orderBy        = isset($params['orderBy'])     ? $this->makeSafe($params['orderBy'])   : false;
        $orderDir       = ((isset($params['orderDir'])) AND in_array($params['orderDir'], array(MDOrderAscending, MDOrderDescending))) ? $params['orderDir'] : MDOrderAscending;
        $start          = isset($params['start'])       ? intval($params['start'])              : 0;
        $limit          = isset($params['limit'])       ? intval($params['limit'])              : false;
        $forceMulti     = ($limit == 1)                 ? false                                 : true;
        
        $query = "
            SELECT
                *
            FROM `". $this->makeSafe($table) ."`
            ". ($criteria           ? "WHERE ". $criteria                                   : null) ."
            ". ($orderBy            ? "ORDER BY `". $orderBy ."` ". $orderDir               : null) ."
            ". ($limit              ? "LIMIT ". $start .", ". $limit                        : null) ."
        ";

        return $this->query($query, $forceMulti);
        */
        return array();
    }

    public function count($table, array $criteria = array(), array $params = array()) {
        return 0;
    }

    public function add($table, array $properties) {
        return 0;
    }

    public function update($table, array $criteria, array $values) {
    }

    public function delete($table, array $criteria) {
    }

    /**
     * Returns the table structure that conforms to the entity structure definition array.
     * 
     * @param string $table Name of the table to get structure for.
     * @return array
     */
    public function structure($table) {
        $columns = $this->query('SHOW COLUMNS FROM '. $table, array(), true);

        $structure = array();
        foreach($columns as $column) {
            // resolve the MySQL type to Knit type
            $type = self::mysqlToKnitType($column['Type']);

            $columnInfo = array(
                'type' => $type['name'],
                'length' => $type['length'],
                'null' => ($column['Null'] === 'YES'),
                'default' => $column['Default'],
                'allowed' => $type['allowed']
            );

            $structure[$column['Field']] = $columnInfo;
        }

        return $structure;
    }

    /*****************************************************
     * DATABASE CONNECTION HANDLERS
     *****************************************************/
    /**
     * Connects to a database with the credentials passed to this store.
     * 
     * @throws \RuntimeException When already connected and trying to connect for 2nd time.
     * @throws StoreConnectionFailedException When failled to connect to the database.
     */
    protected function connect() {
        if ($this->connection) {
            throw new \RuntimeException("Already connected to MySQL database. Cannot connect twice. If you want to connect to a different database you need to instantiate a new MySQLStore.");
        }

        try {
            $this->connection = new PDO('mysql:host='. $this->hostname .';dbname='. $this->database, $this->username, $this->password);
            // make it throw exceptions on errors
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new StoreConnectionFailedException($e->getMessage(), 0, $e);
        }
        
        // set utf8 character set for all requests
        $this->connection->exec("SET CHARACTER SET utf8");
    }

    /**
     * Executes the given PDO statement (SQL query).
     * 
     * @param string $sql SQL query to be executed.
     * @param array $params [optional] Array of parameters that should be bound to the SQL query.
     * @param bool $forceMulti [optional] Should the result be always a collection (array of results)? Default: false.
     * @return array Data returned from the database as an array.
     * 
     * @throws StoreQueryErrorException When there was an error executing the query.
     */
    protected function query($sql, array $params = array(), $forceMulti = false) {
        // lazy connection, only when needed
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        try {
            $statement = $this->connection->prepare(trim($sql));
            $statement->execute($params);
        } catch (PDOException $e) {
            $this->logQuery($statement, array(
                'params' => $params
            ), true);
            throw new StoreQueryErrorException('MySQL: '. $e->getMessage(), 0, $e);
        }

        $type = $this->determineQueryType($statement->queryString);
        $rowCount = $statement->rowCount();

        // initial parsing of the result
        // only SELECT and SHOW queries can return multiple rows
        $data = array();
        if ($type == 'select' || $type == 'show') {
            if ($forceMulti || $rowCount > 1) {
                while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $data[] = $row;
                }
            } else {
                $data = $statement->fetch(PDO::FETCH_ASSOC);
            }
        }

        // finally log the query
        $this->logQuery($statement, array(
            'type' => $type,
            'rows' => $rowCount
        ));

        return $data;
    }

    /*****************************************************
     * HELPERS
     *****************************************************/
    /**
     * Logs the given PDO statement (database query) to the available logger.
     * 
     * @param PDOStatement $statement Query to be logged.
     * @param array $context [optional] Context of the query (like execution time) that can't be read from the PDOStatement.
     * @param bool $error [optional] Has an error occurred on this query? Default: false.
     * 
     * @todo Improve logging of the queries by:
     *       - adding tags,
     *       - adding execution time,
     *       - adding trace or trying to figure out where it was executed.
     */
    public function logQuery(PDOStatement $statement, array $context = array(), $error = false) {
        $message = $statement->queryString;
        $context = array_merge($context, array(
            'query' => $statement->queryString
        ));

        if ($error) {
            $this->logger->error($message, $context);
        } else {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Determines query type based on the SQL string.
     * 
     * @param string $sql SQL query.
     * @return string
     */
    public function determineQueryType($sql) {
        $type = StringUtils::getFirstWord($sql);
        return strtolower($type);
    }

    /**
     * Translates MySQL column type to Knit type returning the type name, maxlength and allowed values (for enum fields) in an array.
     * 
     * @param string $type MySQL column type.
     * @return array
     * 
     * @throws \RuntimeException When could not resolve MySQL type to Knit type.
     */
    protected static function mysqlToKnitType($type) {
        $type = trim($type);
        $name = null;
        $length = 0;
        $allowed = array();
        $foundMysqlType = null;

        foreach(static::$mysqlToKnitType as $mysqlType => $knitType) {
            if (stripos($type, $mysqlType) === 0) {
                $name = $knitType;
                $foundMysqlType = $mysqlType;
                break;
            }
        }

        if ($name === null) {
            throw new \RuntimeException('Could not resolve MySQL type "'. $type .'" to Knit type.');
        }

        if ($name == Knit::TYPE_ENUM) {
            /*
             @todo match allowed values
            dump('enum');
            $matched = preg_match('/^'. $foundMysqlType .'\(([^\)]+)\)$/i', $type, $matches);
            dump($matched);
            if ($matched) {
                dump($matches);
                $matchedAllowed = preg_match('/^([(\'|\")([^\'\"]+)(\'|\")]+)$/i', $matched[1], $matchesAllowed);
                dump($matchedAllowed);
                if ($matchedAllowed) {
                    dump($matchesAllowed);
                }
            }
            */
        } else {
            $matched = preg_match('/^'. $foundMysqlType .'\((\d+)\)$/i', $type, $matches);
            if ($matched && isset($matches[1])) {
                $length = intval($matches[1]);
            }
        }

        return array(
            'name' => $name,
            'length' => $length,
            'allowed' => $allowed
        );
    }

    protected function parseCriteria(array $criteria) {
        throw new \RuntimeException('@TODO: MySQLStore::parseCriteria()');
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Sets the logger.
     * 
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Is connnected to database?
     * 
     * @return bool
     */
    public function isConnected() {
        return isset($this->connection);
    }

}