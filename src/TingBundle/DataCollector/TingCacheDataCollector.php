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

use CCMBenchmark\Ting\Logger\CacheLoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

class TingCacheDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var CacheLoggerInterface|null
     */
    protected $cacheLogger  = null;

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
        if ($this->cacheLogger !== null) {
            $this->data['cache']['operations'] = $this->cacheLogger->getOperations();
            $this->data['cache']['operationsCount'] = count($this->data['cache']['operations']);
            $this->data['cache']['time'] = $this->cacheLogger->getTotalTime();
            $this->data['cache']['hits'] = $this->cacheLogger->getHits();
            $this->data['cache']['miss'] = $this->cacheLogger->getMiss();
        }
    }

    public function lateCollect()
    {
        if ($this->cacheLogger !== null) {
            $this->data['cache']['operations'] = $this->cacheLogger->getOperations();
            $this->data['cache']['operationsCount'] = count($this->data['cache']['operations']);
            $this->data['cache']['time'] = $this->cacheLogger->getTotalTime();
            $this->data['cache']['hits'] = $this->cacheLogger->getHits();
            $this->data['cache']['miss'] = $this->cacheLogger->getMiss();
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
        return 'ting.cache';
    }

    public function setCacheLogger(CacheLoggerInterface $cacheLogger = null)
    {
        $this->cacheLogger = $cacheLogger;
    }

    public function getOperations()
    {
        return $this->data['cache']['operations'];
    }

    public function getCacheOperationsCount()
    {
        return $this->data['cache']['operationsCount'];
    }

    public function getCacheTotalTime()
    {
        return $this->data['cache']['time'];
    }

    public function getHits()
    {
        return $this->data['cache']['hits'];
    }

    public function getMiss()
    {
        return $this->data['cache']['miss'];
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
