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
 * 
 * @todo rewrite to PDO/mysqli
 */
namespace Knit\Store;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Utils\ArrayUtils;

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
        $query = "SHOW COLUMNS FROM `". $this->makeSafe($table) ."`";
        $columns = $this->query($query, true);
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
     * @throws NotFoundException When couldn't find the database.
     */
    protected function connect() {
        if ($this->connection) {
            throw new \RuntimeException("Already connected to MySQL database. Cannot connect twice. If you want to connect to a different database you need to instantiate a new MySQLStore.");
        }

        $connection = mysql_connect($this->hostname, $this->username, $this->password);
        // if failed to establish connection then trigger an error
        if (!$connection) {
            throw new StoreConnectionFailedException('Cannot connect to MySQL database "'. $this->username .' @ '. $this->hostname .'".');
        }

        // store reference to the connection in the class
        $this->connection = $connection;
        
        // set utf8 character set for all requests
        $this->forceUtf8();
        
        // select a database
        $selected = mysql_select_db($this->database, $this->connection);
        if (!$selected) {
            throw new NotFoundException('Cannot connect to database "'. $this->database .' @ '. $this->hostname .'". No such database exists.');
        }
    }

    /**
     * Executes the given SQL query.
     * 
     * @param string $sql SQL query to be executed.
     * @param bool $forceMulti [optional] Should the result be always a collection (array of results)? Default: false.
     * @return array Data returned from the database as an array.
     */
    public function query($sql, $forceMulti = false) {
        // lazy connection, only when needed
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        // execute the query
        $sql = trim($sql);
        $result = mysql_query($sql, $this->connection);
        
        // handle the query's error
        if ($result === false) {
            throw new StoreQueryErrorException('MySQL: '. mysql_error($this->connection), mysql_errno($this->connection));
        }
        
        // initial parsing of the result
        // only SELECT and SHOW queries can return multiple rows
        $data = array();
        if (stripos($sql, 'SELECT') === 0 OR stripos($sql, 'SHOW') === 0) {
            if ($forceMulti OR (@mysql_num_rows($result) > 1)) {
                while($row = @mysql_fetch_array($result, MYSQL_ASSOC)) {
                    $data[] = $row;
                }
            } elseif (@mysql_num_rows($result) === 1) {
                $data = @mysql_fetch_array($result, MYSQL_ASSOC);
            }
        }
        
        // log the query
        // @todo
        //$affectedRows = mysql_affected_rows($this->connection);
        //$this->logQuery($sql, $time, $affectedRows);

        // free the result once we have it
        if (is_resource($result)) {
            @mysql_free_result($result);
        }
        
        return $data;
    }

    /**
     * Sets UTF8 character set for all queries.
     */
    public function forceUtf8() {
        mysql_query("SET CHARACTER SET utf8", $this->connection);
        mysql_query("SET NAMES utf8", $this->connection);
    }

    /*****************************************************
     * HELPERS
     *****************************************************/
    /**
     * Makes the value safe to be used inside an SQL Query
     *
     * @param mixed $value Can be an array.
     * @return mixed
     */
    public function makeSafe($value) {
        // MySQL connection is required otherwise the mysql_real_escape_string() will fail..
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        // go recursively through array
        if (is_array($value)) {
            foreach($value as &$val) {
                $val = $this->makeSafe($val);
            }
            return $value;
        }
        
        // don't do this for ints which are safe anyway, but this makes them string which may break stuff down the line
        if (!is_int($value)) {
            $value = mysql_real_escape_string($value, $this->connection);
        }

        return $value;
    }

    /**
     * Translates MySQL column type to Knit type returning the type name, maxlength and allowed values (for enum fields) in an array.
     * 
     * @param string $type MySQL column type.
     * @return array
     * 
     * @throws \RuntimeException When could not resolve MySQL type to Knit type.
     */
    public static function mysqlToKnitType($type) {
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