<?php
namespace STAR\captains;

use Monolog\Logger;
use Monolog\ErrorHandler;
use STAR\captains\databaseHandler;

class log {
    private String $channelPrefix;
    private Logger $debug;
    private Logger $info;
    private Logger $notice;
    private Logger $warning;
    private Logger $error;
    private Logger $critical;
    private Logger $alert;
    private Logger $emergency;

    public function __construct(String $channelPrefix = 'Default') {
        $this->channelPrefix = $channelPrefix;
        $logger = new Logger($channelPrefix.' System Errors ');
        ErrorHandler::register($logger);
        $handler = new ErrorHandler($logger);

        $handler->registerErrorHandler([], false);
        // $handler->registerExceptionHandler();
        $handler->registerFatalHandler();

        $logger->pushHandler(new databaseHandler(true));
    }

    public function debug(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->debug)) {
            $this->debug = new Logger($this->channelPrefix.' Debug');
            $this->debug->pushHandler(new databaseHandler(false, LOGGER::DEBUG));
        }
        $this->debug->debug($message, $optionalData);
    }

    public function info(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->info)) {
            $this->info = new Logger($this->channelPrefix.' Info');
            $this->info->pushHandler(new databaseHandler(false, LOGGER::INFO));
        }
        $this->info->info($message, $optionalData);
    }

    public function notice(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->notice)) {
            $this->notice = new Logger($this->channelPrefix.' Notice');
            $this->notice->pushHandler(new databaseHandler(false, LOGGER::NOTICE));
        }
        $this->notice->notice($message, $optionalData);
    }

    public function warning(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->warning)) {
            $this->warning = new Logger($this->channelPrefix.' Warning');
            $this->warning->pushHandler(new databaseHandler(false, LOGGER::WARNING));
        }
        $this->warning->warning($message, $optionalData);
    }

    public function error(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->error)) {
            $this->error = new Logger($this->channelPrefix.' Error');
            $this->error->pushHandler(new databaseHandler(false, LOGGER::ERROR));
        }
        $this->error->error($message, $optionalData);
    }

    public function critical(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->critical)) {
            $this->critical = new Logger($this->channelPrefix.' Critical');
            $this->critical->pushHandler(new databaseHandler(false, LOGGER::CRITICAL));
        }
        $this->critical->critical($message, $optionalData);
    }

    public function alert(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->alert)) {
            $this->alert = new Logger($this->channelPrefix.' alert');
            $this->alert->pushHandler(new databaseHandler(false, LOGGER::ALERT));
        }
        $this->alert->alert($message, $optionalData);
    }

    public function emergency(String $message = 'No message given', Array $optionalData = []): void {
        if(empty($this->emergency)) {
            $this->emergency = new Logger($this->channelPrefix.' Emergency');
            $this->emergency->pushHandler(new databaseHandler(false, LOGGER::EMERGENCY));
        }
        $this->emergency->emergency($message, $optionalData);
    }
}