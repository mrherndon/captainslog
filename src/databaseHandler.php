<?php
namespace STAR\captains;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class databaseHandler extends AbstractProcessingHandler {
    private \PDO $pdo;
    private \PDOStatement $statement;
    private bool $errorLogger;
    private string $logType;
    private array $trace;
    private bool $hasData;

    public function __construct(bool $errorLogger = false, int $level = Logger::DEBUG, string $customTableName = null) {
        $this->pdo = new \PDO(HOSTINFO,USERNAME,PASSWORD);
        $this->errorLogger = $errorLogger;
        $this->logType = 'LOG_'.LOGGER::getLevelName($level);
        if($errorLogger) $this->logType = 'LOG_SYSTEM_ERRORS';
        if(!empty($customTableName)) $this->logType = 'LOG_'.$customTableName;
        $this->{$this->logType.'Initialized'} = false;
        $this->hasData = false;

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        parent::__construct($level, true);      
    }

    protected function write(array $record): void {
        if(isset($record['context'])) {
            if(isset($record['context']['exception'])) {
                $this->trace = $record['context']['exception']->getTrace();
            } else {
                $this->hasData = true;
            }
        }
        
        if(!$this->{$this->logType.'Initialized'}) $this->initialize();

        $userId = 0;
        if(isset($GLOBALS['user'])) $userId = $GLOBALS['user']->id ?: 0;

        $this->statement->execute([
            'channel' => $record['channel'],
            'level' => $record['level'],
            'message' => $record['message'],
            'time' => $record['datetime']->format('U'),
            'formatted' => $record['formatted'],
            'userId' => $userId
        ]);

        if($this->errorLogger) $this->backtrace($this->pdo->lastInsertId());
        if($this->hasData) $this->logData($this->pdo->lastInsertId(), $record['context']);
    }

    private function initialize(): void {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS $this->logType
            (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `channel` VARCHAR(255), `level` INT, `message` LONGTEXT, 
            `time` INT UNSIGNED, `formatted` TEXT,
            `userId` INT UNSIGNED, PRIMARY KEY (`id`)) 
            ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;"
        );
        $this->statement = $this->pdo->prepare(
            "INSERT INTO $this->logType
            (`channel`, `level`, `message`, `time`, `formatted`, `userId`) 
            VALUES (:channel, :level, :message, :time, :formatted, :userId)"
        );
        $this->{$this->logType.'Initialized'} = true;
    }

    private function backtrace(int $id): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `LOG_BACKTRACE` 
            (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `systemErrorsId` INT NOT NULL, `stepNumber` INT NULL,
            `file` VARCHAR(255) NULL, `line` VARCHAR(255) NULL,
            `class` VARCHAR(225) NULL, 
            `function` VARCHAR(255) NULL, `args` LONGTEXT NULL, 
            PRIMARY KEY (`id`)) 
            ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;'
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO `LOG_BACKTRACE`
            (`systemErrorsId`, `stepNumber`, `file`, `line`, `class`, `function`, `args`)
            VALUES (:systemErrorsId, :stepNumber, :file, :line, :class, :function, :args)'
        );

        $backtrace = array_reverse($this->trace);
        $i = 1;
        foreach($backtrace as $trace) {
            $statement->execute([
                'systemErrorsId' => $id,
                'stepNumber' => $i++,
                'file' => array_key_exists('file', $trace) ? $trace['file'] : '',
                'line' => array_key_exists('line', $trace) ? $trace['line'] : '',
                'class' => array_key_exists('class', $trace) ? $trace['class'] : '',
                'function' => array_key_exists('function', $trace) ? $trace['function'] : '',
                'args' => array_key_exists('args', $trace) ? json_encode($trace['args']) : ''
            ]);
        }
    }

    private function logData(int $id, array $context): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `LOG_DATA` 
            (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `relativeTable` VARCHAR(255), `relativeId` INT NOT NULL,
            `key` VARCHAR(255) NULL, `value` VARCHAR(255) NULL,
            PRIMARY KEY (`id`)) 
            ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;'
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO `LOG_DATA`
            (`relativeTable`, `relativeId`, `key`, `value`)
            VALUES (:relativeTable, :relativeId, :key, :value)'
        );

        foreach($context as $key => $value) {
            $statement->execute([
                'relativeTable' => $this->logType,
                'relativeId' => $id,
                'key' => $key,
                'value' => json_encode($value)
            ]);
        }
    }
}