<?php

namespace CCMBenchmark\TingBundle\Logger;

use CCMBenchmark\Ting\Logger\Driver\DriverLoggerInterface;

class DriverLogger implements DriverLoggerInterface
{

    /**
     * Logs a SQL Query
     *
     * @param      $sql
     * @param      $params
     * @param bool $prepared
     * @return void
     */
    public function startQuery($sql, $params, $prepared = false)
    {
        // TODO: Implement startQuery() method.
    }

    /**
     * Log the end of a query (for timing purposes mainly)
     * @return void
     */
    public function stopQuery()
    {
        // TODO: Implement stopQuery() method.
    }

}
