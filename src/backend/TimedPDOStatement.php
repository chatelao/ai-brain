<?php

namespace App;

use PDOStatement;

class TimedPDOStatement extends PDOStatement
{
    private static bool $isLogging = false;

    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $start = microtime(true);
        $result = parent::execute($params);
        $duration = microtime(true) - $start;

        if ($duration > 1.0 && !self::$isLogging) {
            self::$isLogging = true;
            try {
                Logger::getInstance()->logPerformance(
                    null,
                    'DB',
                    $this->queryString,
                    $duration,
                    ['params' => $params]
                );
            } finally {
                self::$isLogging = false;
            }
        }

        return $result;
    }
}
