<?php
namespace Knit\Store\DoctrineDBAL;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

use MD\Foundation\Debug\Timer;

use Knit\Criteria\CriteriaExpression;
use Knit\Exceptions\StoreConnectionFailedException;
use Knit\Exceptions\StoreQueryErrorException;
use Knit\Store\DoctrineDBAL\CriteriaParser;
use Knit\Store\StoreInterface;
use Knit\Knit;

/**
 * Persistent store based on Doctrine DBAL that supports MySQL, PostgreSQL and more.
 *
 * @package    Knit
 * @subpackage Store\DoctrineDBAL
 * @author     Michał Pałys-Dudek <michal@michaldudek.pl>
 * @copyright  2015 Michał Pałys-Dudek
 * @license    https://github.com/michaldudek/Knit/blob/master/LICENSE.md MIT License
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Store implements StoreInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Database connection.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * DoctrineDBAL specific criteria parser.
     *
     * @var CriteriaParser
     */
    protected $criteriaParser;

    /**
     * Are we connected to the store yet?
     *
     * @var boolean
     */
    protected $connected = false;

    /**
     * Constructor.
     *
     * Creates database connection using Doctrine's `Connection` class based on passed `$config`.
     * See {@link: http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html} for
     * details on how to configure a connection.
     *
     * @param array                $config         Connection configuration. Passed directory to `Connection`.
     * @param CriteriaParser       $criteriaParser Knit criteria parser.
     * @param LoggerInterface|null $logger         [optional] Logger.
     * @param Connection|null      $connection     [optional] Existing Doctrine DBAL connection if you already have one.
     */
    public function __construct(
        array $config,
        CriteriaParser $criteriaParser,
        LoggerInterface $logger = null,
        Connection $connection = null
    ) {
        try {
            $this->connection = $connection ? $connection : DriverManager::getConnection($config, new Configuration());
        } catch (\Exception $e) {
            throw new StoreConnectionFailedException('DoctrineDBAL: '. $e->getMessage(), $e->getCode(), $e);
        }

        $this->criteriaParser = $criteriaParser;
        $this->logger = $logger ? $logger : new NullLogger();
    }

    /**
     * Connect to the store.
     *
     * @throws StoreConnectionFailedException When could not connect to the store.
     */
    protected function connect()
    {
        if ($this->connected) {
            return;
        }

        try {
            $this->connection->connect();
        } catch (\Exception $e) {
            throw new StoreConnectionFailedException('DoctrineDBAL: '. $e->getMessage(), $e->getCode(), $e);
        }

        $this->connected = true;
    }

    /**
     * Finds items within a collection.
     *
     * @param string $collection Name of the collection in which to look for.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $params [optional] Other parameters, like `orderBy` or `orderDir`.
     *
     * @return array
     */
    public function find($collection, CriteriaExpression $criteria = null, array $params = [])
    {
        $this->connect();
        
        $timer = new Timer();

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($collection);

        $this->criteriaParser->parse($queryBuilder, $criteria);

        // add offset and limit
        if (isset($params['start'])) {
            $queryBuilder->setFirstResult(intval($params['start']));
        }

        if (isset($params['limit'])) {
            $queryBuilder->setMaxResults(intval($params['limit']));
        }

        // parse order by
        if (isset($params['orderBy'])) {
            $orderBy = $this->parseOrderBy($params);
            foreach ($orderBy as $column => $order) {
                $queryBuilder->addOrderBy($column, $order);
            }
        }

        $sql = $queryBuilder->getSQL();
        $sqlParams = $queryBuilder->getParameters();

        // execute the query
        try {
            $resultStatement = $queryBuilder->execute();

            $results = [];
            while ($row = $resultStatement->fetch()) {
                $results[] = $row;
            }
        } catch (\Exception $e) {
            $this->log($sql, $sqlParams, [], 'error');
            throw new StoreQueryErrorException('DoctrineDBAL: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log($sql, $sqlParams, ['time' => $timer->stop()]);

        return $results;
    }

    /**
     * Counts items within a collection.
     *
     * MUST return either an integer or an array of integers if used `groupBy` param.
     *
     * @param string $collection Name of the collection in which to look for.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $params [optional] Other parameters, like `orderBy` or `orderDir`.
     *
     * @return integer|array
     */
    public function count($collection, CriteriaExpression $criteria = null, array $params = [])
    {
        $this->connect();
        
        $timer = new Timer();

        // first create a normal SELECT query to apply any filters
        $filterQueryBuilder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($collection);

        $this->criteriaParser->parse($filterQueryBuilder, $criteria);

        // add offset and limit
        if (isset($params['start'])) {
            $filterQueryBuilder->setFirstResult(intval($params['start']));
        }

        if (isset($params['limit'])) {
            $filterQueryBuilder->setMaxResults(intval($params['limit']));
        }

        // and then merge it into the parent query
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('('. $filterQueryBuilder->getSQL() .')', 'a');

        $queryBuilder->setParameters($filterQueryBuilder->getParameters());

        $sql = $queryBuilder->getSQL();
        $sqlParams = $queryBuilder->getParameters();

        // execute the query
        try {
            $results = $queryBuilder->execute()->fetch();
            $result = current(array_values($results));
        } catch (\Exception $e) {
            $this->log($sql, $sqlParams, [], 'error');
            throw new StoreQueryErrorException('DoctrineDBAL: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log($sql, $sqlParams, ['time' => $timer->stop()]);

        return $result;
    }

    /**
     * Persists a new item in the collection.
     *
     * MUST return an identifier of the created object.
     *
     * @param string $collection Name of the collection in which to create new object.
     * @param array $properties Properties of the new object.
     *
     * @return integer|string
     */
    public function add($collection, array $properties)
    {
        $this->connect();
        
        $timer = new Timer();

        $queryBuilder = $this->connection->createQueryBuilder()
            ->insert($collection);

        foreach ($properties as $column => $value) {
            // don't include NULL values
            if ($value === null) {
                continue;
            }

            $queryBuilder->setValue($column, ':'. $column);
            $queryBuilder->setParameter($column, $value);
        }

        $sql = $queryBuilder->getSQL();
        $sqlParams = $queryBuilder->getParameters();

        // execute the query
        try {
            $queryBuilder->execute();
            $lastInsertId = $this->connection->lastInsertId();
        } catch (\Exception $e) {
            $this->log($sql, $sqlParams, [], 'error');
            throw new StoreQueryErrorException('DoctrineDBAL: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log($sql, $sqlParams, ['time' => $timer->stop()]);

        return $lastInsertId;
    }

    /**
     * Updates items in the collection based on the given criteria.
     *
     * @param string $collection Name of the collection in which to update.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     * @param array $properties Properties to be updated.
     */
    public function update($collection, CriteriaExpression $criteria = null, array $properties = [])
    {
        $this->connect();
        
        $timer = new Timer();

        $queryBuilder = $this->connection->createQueryBuilder()
            ->update($collection);

        $this->criteriaParser->parse($queryBuilder, $criteria);

        foreach ($properties as $column => $value) {
            $queryBuilder->set($column, ':'. $column);
            $queryBuilder->setParameter($column, $value);
        }

        $sql = $queryBuilder->getSQL();
        $sqlParams = $queryBuilder->getParameters();

        // execute the query
        try {
            $queryBuilder->execute();
        } catch (\Exception $e) {
            $this->log($sql, $sqlParams, [], 'error');
            throw new StoreQueryErrorException('DoctrineDBAL: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log($sql, $sqlParams, ['time' => $timer->stop()]);
    }

    /**
     * Removes items from the collection based on the given criteria.
     *
     * @param string $collection Name of the collection from which to remove items.
     * @param CriteriaExpression $criteria [optional] Lookup criteria.
     */
    public function remove($collection, CriteriaExpression $criteria = null)
    {
        $this->connect();
        
        $timer = new Timer();

        $queryBuilder = $this->connection->createQueryBuilder()
            ->delete($collection);

        $this->criteriaParser->parse($queryBuilder, $criteria);

        $sql = $queryBuilder->getSQL();
        $sqlParams = $queryBuilder->getParameters();

        // execute the query
        try {
            $queryBuilder->execute();
        } catch (\Exception $e) {
            $this->log($sql, $sqlParams, [], 'error');
            throw new StoreQueryErrorException('DoctrineDBAL: '. $e->getMessage(), $e->getCode(), $e);
        }

        // log this query
        $this->log($sql, $sqlParams, ['time' => $timer->stop()]);
    }

    /**
     * Returns the database connection.
     *
     * @return Connection
     */
    public function getConnection()
    {
        $this->connect();
        
        return $this->connection;
    }

    /**
     * Logs a query to the logger.
     *
     * @param string $sql        SQL query.
     * @param array  $parameters Parameters used in the SQL query.
     * @param array  $context    Query context.
     * @param string $level      [optional] Log level. Default: `debug`.
     */
    protected function log($sql, array $parameters, array $context, $level = 'debug')
    {
        $message = sprintf('DoctrineDBAL Query: "%s" with params %s', $sql, json_encode($parameters));
        $this->logger->{$level}($message, $context);
    }

    /**
     * Parse `orderBy` and `orderDir` parameters.
     *
     * @param array  $params Query params.
     *
     * @return array
     */
    private function parseOrderBy(array $params)
    {
        $orderBy = [];

        if (is_array($params['orderBy'])) {
            foreach ($params['orderBy'] as $property => $orderDir) {
                if (is_string($orderDir)) {
                    $property = $orderDir;
                    $orderDir = Knit::ORDER_ASC;
                }

                $orderBy[$property] = $orderDir === Knit::ORDER_DESC ? 'DESC' : 'ASC';
            }
        } else {
            $orderBy[$params['orderBy']] = isset($params['orderDir'])
                && $params['orderDir'] === Knit::ORDER_DESC
                    ? 'DESC'
                    : 'ASC';
        }

        return $orderBy;
    }
}
