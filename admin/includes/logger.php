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
            $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                return [];
            }
            return array_reverse($lines);
        } else {
            return [];
        }
    }

    public function clearLogs() {
        if (file_exists($this->logFile)) {
            // Create a backup before clearing
            $backupFile = $this->logFile . '.backup.' . date('Y-m-d_His');
            copy($this->logFile, $backupFile);
            
            // Clear the log file
            file_put_contents($this->logFile, '');
            
            // Log the clearing action
            $this->write('All logs cleared by user: ' . $_SESSION['alogin'], 'ADMIN');
            
            return true;
        }
        return false;
    }
}
?>