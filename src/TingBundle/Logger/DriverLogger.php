<?php

namespace CCMBenchmark\TingBundle\Logger;

use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class DriverLogger implements DriverLoggerInterface
{
    /**
     * @var null|LoggerInterface
     */
    protected $logger = null;

    /**
     * @var null|Stopwatch
     */
    protected $stopwatch = null;

    /**
     * @var array
     */
    protected $queries = [];

    /**
     * @var array contains information about statement executions
     */
    protected $execs = [];
    /**
     * @var array
     */
    protected $connections = [];

    /**
     * @var array
     */
    protected $connectionsHashToName = [];

    /**
     * @var int
     */
    protected $queryIndex = 0;

    /**
     * @var int
     */
    protected $execIndex = 0;

    /**
     * @var int
     */
    protected $totalTime = 0;

    public function __construct(LoggerInterface $logger = null, Stopwatch $stopwatch = null)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * Add an opened connection to the list
     *
     * @param $name       string connection name
     * @param $connection string spl_object_hash of the connection
     * @param $connectionConfig array Connection parameters
     */
    public function addConnection($name, $connection, array $connectionConfig)
    {
        if (isset($this->connections[$name]) === false) {
            $this->connections[$name] = [];
        }
        $this->connectionsHashToName[$connection] = $name;
        // We don't want to store passwords
        unset($connectionConfig['password']);
        $this->connections[$name][$connection] = ['name' => $name, 'config' => $connectionConfig];
    }

    /**
     * Logs a SQL Query
     *
     * @param      $sql
     * @param      $params
     * @param      $connection string spl_object_hash of the connection
     * @param      $database   string name of the database
     * @return void
     */
    public function startQuery($sql, $params, $connection, $database)
    {
        if ($this->stopwatch !== null) {
            $this->stopwatch->start('query', 'ting');
            $this->queries[++$this->queryIndex] = [
                'type'       => 'query',
                'connection' => $connection,
                'sql'        => $sql,
                'params'     => $params,
                'database'   => $database,
                'time'       => 0
            ];
        }
        if ($this->logger !== null) {
            $this->logger->debug($sql, $params);
        }
    }

    /**
     * Log the end of a query (for timing purposes mainly)
     *
     * @param $event string event name (query, exec, prepare)
     * @return void
     */
    public function stopQuery($event = 'query')
    {
        if ($this->stopwatch !== null) {
            $event = $this->stopwatch->stop($event);
            $periods = $event->getPeriods();
            $period = array_pop($periods);
            $this->queries[$this->queryIndex]['time'] = $period->getDuration();
            $this->totalTime += $period->getDuration();
        }
    }

    /**
     * Log the preparation of a statement
     *
     * @param $sql string the query
     * @param $connection string spl_object_hash of the connection
     * @param $database string name of the database
     * @return void
     */
    public function startPrepare($sql, $connection, $database)
    {
        if ($this->stopwatch !== null) {
            $this->queries[++$this->queryIndex] = [
                'type'       => 'statement',
                'connection' => $connection,
                'sql'        => $sql,
                'database'   => $database,
                'name'       => '',
                'time'       => 0
            ];
            $this->stopwatch->start('prepare', 'ting');
        }

        if ($this->logger !== null) {
            $this->logger->debug('Preparation of query as statement ' . $sql);
        }
    }

    /**
     * Log the parameters applied to a statement when executed
     *
     * @param $statement string statement name
     * @param $params
     * @return void
     */
    public function startStatementExecute($statement, $params)
    {
        if ($this->stopwatch !== null) {
            if (isset($this->execs[$statement]) === false) {
                $this->execs[$statement] = [];
            }
            $this->execs[$statement][++$this->execIndex] = [
                'params' => $params,
                'time'   => 0
            ];
            $this->stopwatch->start('exec', 'ting');
        }

        if ($this->logger !== null) {
            $this->logger->debug('Execution of statement ' . $statement, $params);
        }
    }

    /**
     * Log the end of the preparation (for timing purposes)
     *
     * @param $statement string statement name
     * @return void
     */
    public function stopPrepare($statement)
    {
        $this->queries[$this->queryIndex]['name'] = $statement;
        $this->stopQuery('prepare');
    }

    /**
     * Log the end of execution of a prepared statement
     *
     * @param $statement string unique identifier for the statement
     * @return void
     */
    public function stopStatementExecute($statement)
    {
        if ($this->stopwatch !== null) {
            $event = $this->stopwatch->stop('exec');
            $periods = $event->getPeriods();
            $period = array_pop($periods);
            $this->execs[$statement][$this->execIndex]['time'] = $period->getDuration();
            $this->totalTime += $period->getDuration();
        }
    }


    /**
     * Give the list of executed queries with execution time
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    public function getTotalTime()
    {
        return $this->totalTime;
    }

    public function getConnections()
    {
        return $this->connections;
    }

    public function getConnectionsHashToName()
    {
        return $this->connectionsHashToName;
    }

    public function getExecs()
    {
        return $this->execs;
    }
}
