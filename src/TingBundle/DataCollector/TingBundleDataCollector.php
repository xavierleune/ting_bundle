<?php


namespace CCMBenchmark\TingBundle\DataCollector;


use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class TingBundleDataCollector extends DataCollector
{
    /**
     * @var DriverLoggerInterface|null
     */
    protected $driverLogger = null;

    /**
     * Collects data for the given Request and Response.
     *
     * @param Request    $request A Request instance
     * @param Response   $response A Response instance
     * @param \Exception $exception An Exception instance
     *
     * @api
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if ($this->driverLogger !== null) {
            $this->data['queries'] = $this->driverLogger->getQueries();
            $this->data['execs'] = $this->driverLogger->getExecs();
            $this->data['queryCount'] = count($this->data['queries']);
            $this->data['time'] = $this->driverLogger->getTotalTime();
            $this->data['connections'] = $this->driverLogger->getConnections();
            $this->data['connectionsHashToName'] = $this->driverLogger->getConnectionsHashToName();
        }
    }

    /**
     * Returns the name of the collector.
     *
     * @return string The collector name
     *
     * @api
     */
    public function getName()
    {
        return 'ting_bundle';
    }

    public function setLogger(DriverLoggerInterface $driverLogger = null)
    {
        $this->driverLogger = $driverLogger;
    }

    public function getQueryCount()
    {
        return $this->data['queryCount'];
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getExecs()
    {
        return $this->data['execs'];
    }

    public function getTime()
    {
        return $this->data['time'];
    }

    public function getConnections()
    {
        return $this->data['connections'];
    }

    public function getConnectionsHashToName()
    {
        return $this->data['connectionsHashToName'];
    }
}
