<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
 *
 ***********************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 **********************************************************************/

namespace CCMBenchmark\TingBundle\DataCollector;

use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class TingDriverDataCollector extends DataCollector
{
    /**
     * @var DriverLoggerInterface|null
     */
    protected $driverLogger = null;

    protected $data = [];

    public function __construct()
    {
        $this->init();
    }

    /**
     * Collects data for the given Request and Response.
     *
     * @param Request    $request A Request instance
     * @param Response   $response A Response instance
     * @param \Throwable $exception An Exception instance
     *
     * @api
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        if ($this->driverLogger !== null) {
            $this->data['driver']['queries'] = $this->driverLogger->getQueries();
            $this->data['driver']['execs'] = $this->driverLogger->getExecs();
            $this->data['driver']['queryCount'] = count($this->data['driver']['queries']);
            $this->data['driver']['time'] = $this->driverLogger->getTotalTime();
            $this->data['driver']['connections'] = $this->driverLogger->getConnections();
            $this->data['driver']['connectionsHashToName'] = $this->driverLogger->getConnectionsHashToName();

            // HttpKernel < 3.2 compatibility layer
            // For >= 3.2 cloneVar is always present and MUST be used.
            if (method_exists($this, 'cloneVar')) {
                foreach ($this->data['driver']['queries'] as &$query) {
                    if (isset($query['params']) === true) {
                        $query['params'] = $this->cloneVar($query['params']);
                    }
                }
            }
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
        return 'ting.driver';
    }

    public function setDriverLogger(DriverLoggerInterface $driverLogger = null)
    {
        $this->driverLogger = $driverLogger;
    }

    public function getQueryCount()
    {
        return $this->data['driver']['queryCount'];
    }

    public function getQueries()
    {
        return $this->data['driver']['queries'];
    }

    public function getExecs()
    {
        return $this->data['driver']['execs'];
    }

    public function getTime()
    {
        return $this->data['driver']['time'];
    }

    public function getConnections()
    {
        return $this->data['driver']['connections'];
    }

    public function getConnectionsHashToName()
    {
        return $this->data['driver']['connectionsHashToName'];
    }

    public function reset()
    {
        $this->init();
    }

    private function init()
    {
        $this->data = [
            'driver' => [
                'queries'               => [],
                'execs'                 => [],
                'queryCount'            => 0,
                'time'                  => 0,
                'connections'           => [],
                'connectionsHashToName' => []
            ]
        ];
    }
}
