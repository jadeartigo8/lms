<?php
date_default_timezone_set('Asia/Manila');
class Logger {
    private $logFile;

    public function __construct($filename = 'system.log') {
        $this->logFile = __DIR__ . '/../logs/' . $filename;

        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    public function write($message, $type = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp][$type]: $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function getLogs() {
        if (file_exists($this->logFile)) {
            $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
            $lines_reversed = array_reverse($lines);

            return $lines_reversed;

        } else {
            return ["Log file not found."];
        }
    }
}
?>
