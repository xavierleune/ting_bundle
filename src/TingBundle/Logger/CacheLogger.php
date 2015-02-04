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

namespace CCMBenchmark\TingBundle\Logger;

use CCMBenchmark\Ting\Logger\CacheLoggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class CacheLogger implements CacheLoggerInterface
{
    protected $operationIndex = 0;
    protected $operations     = [];
    protected $hits           = 0;
    protected $miss           = 0;
    protected $lastOperation  = '';
    protected $totalTime      = 0;

    /**
     * @var null|LoggerInterface
     */
    protected $logger = null;

    /**
     * @var null|Stopwatch
     */
    protected $stopwatch = null;

    public function __construct(LoggerInterface $logger = null, Stopwatch $stopwatch = null)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * Log an operation
     *
     * @param $operation string one of defined constant starting with OPERATION_
     * @param $keys array|string impacted keys by the operation
     * @return void
     */
    public function startOperation($operation, $keys)
    {
        if ($this->stopwatch !== null) {
            $this->stopwatch->start('cache_operation', 'ting');

            if (is_array($keys) === false) {
                $keys = [$keys];
            }

            $this->operations[++$this->operationIndex] = [
                'type'       => $operation,
                'keys'       => $keys,
                'time'       => 0
            ];
        }
        if ($this->logger !== null) {
            $this->logger->debug($operation, $keys);
        }
    }

    /**
     * Flag the previously operation as stopped. Useful for time logging.
     *
     * @param $miss boolean tells if the last get was a miss if it was a read operation
     * @return void
     */
    public function stopOperation($miss = false)
    {
        if ($this->stopwatch !== null) {
            if (in_array($this->operations[$this->operationIndex]['type'], [
                CacheLoggerInterface::OPERATION_GET,
                CacheLoggerInterface::OPERATION_GET_MULTI
            ])) {
                if ($miss === true) {
                    $this->miss++;
                    $this->operations[$this->operationIndex]['miss'] = true;
                } else {
                    $this->hits++;
                    $this->operations[$this->operationIndex]['miss'] = false;
                }
            }

            $event = $this->stopwatch->stop('cache_operation');
            $periods = $event->getPeriods();
            $period = array_pop($periods);
            $this->operations[$this->operationIndex]['time'] = $period->getDuration();
            $this->totalTime += $period->getDuration();
        }
    }

    public function getOperations()
    {
        return $this->operations;
    }

    public function getTotalTime()
    {
        return $this->totalTime;
    }

    public function getHits()
    {
        return $this->hits;
    }

    public function getMiss()
    {
        return $this->miss;
    }
}
