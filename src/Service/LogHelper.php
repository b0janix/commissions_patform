<?php

declare(strict_types=1);

namespace App\Service;

class LogHelper
{
    public const ERRORS_LOG_PAT = 'var/logs/errors.log';
    public static function logError(string $message, int $msgType = 3)
    {
        $errDt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $errDt .= " - $message \n";

        error_log(message: $errDt, message_type: $msgType, destination: self::ERRORS_LOG_PAT);
    }
}
