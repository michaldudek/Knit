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

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Debug\Timer;
use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Utils\StringUtils;

use Knit\Criteria\CriteriaExpression;
use Knit\Criteria\FieldValue;
use Knit\Entity\Repository;
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Exceptions\StoreQueryErrorException;
use Knit\Store\StoreInterface;
use Knit\KnitOptions;
use Knit\Knit;

class MySQLStore implements StoreInterface
{

    /**
     * MySQL server host name.
     * 
     * @var string
     */
    protected $hostname;

    /**
     * MySQL server port number.
     * 
     * @var int
     */
    protected $port;

    /**
     * MySQL server user name.
     * 
     * @var string
     */
    protected $username;

    /**
     * MySQL server user password.
     * 
     * @var string
     */
    protected $password;

    /**
     * MySQL database name.
     * 
     * @var string
     */
    protected $database;

    /**
     * PDO connection to the database.
     * 
     * @var PDO
     */
    protected $connection;

    /**
     * The store logger.
     * 
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Map of MySQL types to Knit types.
     * 
     * @var array
     */
    protected static $mysqlToKnitType = array(
        'tinyint' => KnitOptions::TYPE_INT,
        'smallint' => KnitOptions::TYPE_INT,
        'mediumint' => KnitOptions::TYPE_INT,
        'bigint' => KnitOptions::TYPE_INT,
        'int' => KnitOptions::TYPE_INT,
        'float' => KnitOptions::TYPE_FLOAT,
        'varchar' => KnitOptions::TYPE_STRING,
        'mediumtext' => KnitOptions::TYPE_STRING,
        'tinytext' => KnitOptions::TYPE_STRING,
        'text' => KnitOptions::TYPE_STRING,
        'enum' => KnitOptions::TYPE_ENUM
    );

    /**
     * Constructor.
     * 
     * @param array $config Array of all information required to connect to the store (e.g. host, user, pass, database name, port, etc.)
     */
    public function __construct(array $config, LoggerInterface $logger = null) {
        // check for all required info
        if (!ArrayUtils::checkValues($config, array('hostname', 'username', 'password', 'database'))) {
            throw new \InvalidArgumentException('"'. get_called_class() .'::__construct()"  expects 1st argument to be an array containing non-empty keys: "hostname", "username", "password" and "database", "'. implode('", "', array_keys($config)) .'" given.');
        }

        $this->hostname = $config['hostname'];
        $this->port = (isset($config['port'])) ? intval($config['port']) : null;
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->database = $config['database'];

        $this->logger = $logger ? $logger : new NullLogger();
    }

    /*****************************************************
     * STORE INTERFACE IMPLEMENTATION
     *****************************************************/
    /** void */
    public function didBindToRepository(Repository $repository) {}

    /**
     * Performs a SELECT query on the given table.
     * 
     * @param string $table Name of the table to select from.
     * @param CriteriaExpression $criteria Criteria on which to select.
     * @param array $params [optional] Paramaters of the select (like order, start, limit, groupby, etc.)
     * @return array
     */
    public function find($table, CriteriaExpression $criteria = null, array $params = array()) {
        $criteria = $this->parseCriteria($criteria, $parameters);
        
        // parse the params
        $orderBy        = isset($params['orderBy'])     ? $params['orderBy']                    : false;
        $orderDir       = ((isset($params['orderDir'])) AND in_array(strtolower($params['orderDir']), array('asc', 'desc')))
            ? $params['orderDir'] : 'asc';
        $start          = isset($params['start'])       ? intval($params['start'])              : 0;
        $limit          = isset($params['limit'])       ? intval($params['limit'])              : false;
        
        // and finally write the SQL query
        $query = 'SELECT * FROM `'. $table .'` '
            . ($criteria           ? ' WHERE '. $criteria                                   : null)
            . ($orderBy            ? ' ORDER BY `'. $orderBy .'` '. $orderDir               : null)
            . ($limit              ? ' LIMIT '. $start .', '. $limit                        : null);

        return $this->query($query, $parameters, true);
    }

    /**
     * Performs a SELECT COUNT(*) query on the given table.
     * 
     * @param string $table Name of the table to select from.
     * @param CriteriaExpression $criteria Criteria on which to select.
     * @param array $params [optional] Paramaters of the select (like order, start, limit, groupby, etc.)
     * @return array
     */
    public function count($table, CriteriaExpression $criteria = null, array $params = array()) {
        $criteria = $this->parseCriteria($criteria, $parameters);
        
        // parse the params
        $groupBy        = isset($params['groupBy'])     ? $params['groupBy']                    : false;
        $orderBy        = isset($params['orderBy'])     ? $params['orderBy']                    : false;
        $orderDir       = ((isset($params['orderDir'])) AND in_array(strtolower($params['orderDir']), array('asc', 'desc')))
            ? $params['orderDir'] : 'asc';
        $start          = isset($params['start'])       ? intval($params['start'])              : 0;
        $limit          = isset($params['limit'])       ? intval($params['limit'])              : false;
        
        // and finally write the SQL query
        $query = 'SELECT COUNT(*) AS `count` FROM `'. $table .'` '
            . ($criteria           ? ' WHERE '. $criteria                                   : null)
            . ($groupBy            ? ' GROUP BY `'. $groupBy .'` '                          : null)
            . ($orderBy            ? ' ORDER BY `'. $orderBy .'` '. $orderDir               : null)
            . ($limit              ? ' LIMIT '. $start .', '. $limit                        : null);

        $result = $this->query($query, $parameters, true);
        return ($groupBy) ? $result : intval($result[0]['count']);
    }

    /**
     * Performs an INSERT query with the given data to the given table.
     * 
     * @param string $table Name of the table to insert into.
     * @param array $data Data that should be inserted.
     * @return int ID of the inserted row.
     */
    public function add($table, array $data) {
        $parameters = array();
        foreach($data as $field => $value) {
            $parameters[] = ':'. $field;
        }

        $query = 'INSERT INTO `'. $table .'` (`'. implode('`,`', array_keys($data)) .'`) VALUES ('. implode(', ', $parameters) .')';

        $this->query($query, $data);

        return intval($this->connection->lastInsertId());
    }

    /**
     * Performs an UPDATE query on the given table with the given criteria.
     * 
     * @param string $table Name of the table which to update.
     * @param CriteriaExpression $criteria Criteria on which to update.
     * @param array $data Data that should be updated.
     */
    public function update($table, CriteriaExpression $criteria = null, array $data) {
        $criteria = $this->parseCriteria($criteria, $parameters);
        $fields = array();

        foreach($data as $field => $value) {
            $fields[] = '`'. $field .'` = :set__'. $field;
            $parameters['set__'. $field] = $value;
        }

        $query = 'UPDATE `'. $table .'` SET '. implode(', ', $fields) .' WHERE '. $criteria;

        $this->query($query, $parameters);
    }

    /**
     * Performs a DELETE query on the given table with the given criteria.
     * 
     * @param string $table Name of the table from which to delete.
     * @param CriteriaExpression $criteria Criteria on which to delete.
     */
    public function delete($table, CriteriaExpression $criteria = null) {
        $criteria = $this->parseCriteria($criteria, $parameters);

        $query = 'DELETE FROM `'. $table .'` WHERE '. $criteria;
        $this->query($query, $parameters);
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
            $type = static::mysqlToKnitType($column['Type']);

            $columnInfo = array(
                'type' => $type['name'],
                'maxLength' => $type['length'],
                'required' => ($column['Null'] === 'NO'),
                'default' => $column['Default']
            );

            if (isset($column['allowed']) && is_array($column['allowed']) && !empty($column['allowed'])) {
                $columnInfo['allowedValues'] = $column['allowed'];
            }

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
            $this->connection = new PDO('mysql:host='. $this->hostname . ($this->port ? ';port='. $this->port : '') .';dbname='. $this->database, $this->username, $this->password);
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
     * @param array $parameters [optional] Array of parameters that should be bound to the SQL query.
     * @param bool $forceMulti [optional] Should the result be always a collection (array of results)? Default: false.
     * @return array Data returned from the database as an array.
     * 
     * @throws StoreQueryErrorException When there was an error executing the query.
     */
    protected function query($sql, array $parameters = array(), $forceMulti = false) {
        // lazy connection, only when needed
        if (!$this->isConnected()) {
            $this->connect();
        }

        $timer = new Timer();
        
        try {
            $statement = $this->connection->prepare(trim($sql));
            $statement->execute($parameters);
        } catch (PDOException $e) {
            $this->logQuery($statement, array(
                'parameters' => $parameters
            ), true);
            throw new StoreQueryErrorException('MySQL: '. $e->getMessage(), intval($e->getCode()), $e);
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
            'executionTime' => $timer->stop(),
            'affected' => $rowCount,
            'parameters' => $parameters
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
     */
    public function logQuery(PDOStatement $statement, array $context = array(), $error = false) {
        $message = $statement->queryString;
        $context = array_merge($context, array(
            'query' => $statement->queryString,
            '_tags' => array(
                'mysql'
            )
        ));

        if (isset($context['type'])) {
            $context['_tags'][] = $context['type'];
        }

        // if error occurred then log as error
        if ($error) {
            $this->logger->error($message, $context);

        // otherwise just log normally
        } else {
            $this->logger->debug($message, $context);
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

        if ($name == KnitOptions::TYPE_ENUM) {
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

    /**
     * Parses a CriteriaExpression into SQL format that uses parameters for prepared statements.
     * 
     * @param CriteriaExpression $criteria [optional] Criteria to be parsed.
     * @param array $parameters [optional] An array of parameters in which the parameters and their values will be stored.
     * @return string
     * 
     * @throws InvalidOperatorException When couldn't handle an operator either because of lack of implementation or MySQL not supporting that operator.
     */
    protected function parseCriteria(CriteriaExpression $criteria = null, &$parameters = array()) {
        if (!is_array($parameters)) {
            $parameters = array();
        }
        
        if (is_null($criteria)) {
            return '(1)';
        }

        $items = array();

        foreach($criteria->getCriteria() as $criterium) {
            if ($criterium instanceof CriteriaExpression) {
                $items[] = $this->parseCriteria($criterium, $parameters);
                continue;
            }

            $sql = '`'. $criterium->getField() .'`';

            // add value to parameters array
            // but first figure out a unique name
            $i = 0;
            $parameter = 'where__'. $criterium->getField();
            while(isset($parameters[$parameter])) {
                $parameter = $parameter . $i++;
            }

            $parameters[$parameter] = $criterium->getValue();

            // now figure out an operator
            switch($criterium->getOperator()) {
                case FieldValue::OPERATOR_EQUALS:
                    // if checking against NULL value then syntax is a bit different
                    if (is_null($parameters[$parameter])) {
                        $sql .= ' IS NULL';
                        unset($parameters[$parameter]);
                        break;
                    }

                    $sql .= ' = :'. $parameter;
                    break;

                case FieldValue::OPERATOR_NOT:
                    // if checking against NULL value then syntax is a bit different
                    if (is_null($parameters[$parameter])) {
                        $sql .= ' IS NOT NULL';
                        unset($parameters[$parameter]);
                        break;
                    }

                    $sql .= ' != :'. $parameter;
                    break;

                case FieldValue::OPERATOR_IN:
                    // PDO doesn't handle joining the values for IN clause...
                    // so copy the parameter values and unset the parameter
                    $values = ArrayUtils::resetKeys($parameters[$parameter]); // btw reset keys so they're numeric
                    unset($parameters[$parameter]);

                    // if the value is empty or is not an array then replace whole statement with a 0 (false) so it never matches
                    if (!is_array($values) || empty($values)) {
                        $sql = '0';
                        break;
                    }

                    // we have to build that clause ourselves
                    // but are we handling int's or strings?
                    if (is_int($values[0])) {
                        $sql .= ' IN ('. implode(',', $values) .')';
                    } else {
                        $sql .= " IN ('". implode("', '", $values) ."')";
                    }

                    break;

                case FieldValue::OPERATOR_GREATER_THAN:
                    $sql .= ' > :'. $parameter;
                    break;

                case FieldValue::OPERATOR_GREATER_THAN_EQUAL:
                    $sql .= ' >= :'. $parameter;
                    break;

                case FieldValue::OPERATOR_LOWER_THAN:
                    $sql .= ' < :'. $parameter;
                    break;

                case FieldValue::OPERATOR_LOWER_THAN_EQUAL:
                    $sql .= ' <= :'. $parameter;
                    break;

                // if it hasn't been handled by the above then throw an exception
                default:
                    throw new InvalidOperatorException('MySQLStore cannot handle operator "'. trim($criterium->getOperator(), '_') .'" used for "'. $criterium->getField() .'" column. Either because this operator is not supported by MySQL DBMS or it has not been implemented in Knit yet.');
            }

            // and finally add the generated SQL to the items list
            $items[] = $sql;
        }

        if (empty($items)) {
            return '(1)';
        }

        // finally join all the items and return them
        $logic = $criteria->getLogic() === KnitOptions::LOGIC_AND ? ' AND ' : ' OR ';
        return '( '. implode($logic, $items) .' )';
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