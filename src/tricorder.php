<?php
namespace STAR\captains;

use donatj\UserAgent\UserAgentParser;

class tricorder {
    private \PDO $pdo;
    private UserAgentParser $parser;
    private \PDOStatement $statement;

    // LOG_VISITORS table
    private int $visitorId;
    private int $userId;
    private string $userIp;
    private string $identifyUser;
    
    // LOG_PLATFORMS table
    // $visitorId - foreign key
    private string $browser;
    private string $browserVersion;
    private string $platform;
    private \DateTime $visitTimestamp;

    // LOG_VISITS
    private string $theCurrentUrl;
    private string $referrerMedium;
    private string $referrerSource;
    private string $referrerContent;
    private string $referrerKeyword;
    private string $firstVisit;
    private string $previousVisit;
    private string $currentVisitStarted;
    private string $timesVisited;
    private string $pagesViewed;

    function __construct() {
		$this->pdo = new \PDO(HOSTINFO,USERNAME,PASSWORD);
        $this->parser = new UserAgentParser;

        date_default_timezone_set('America/Los_Angeles');
        
        $this->processDefaults();
		$this->parseCookies();
        $this->logTraffic();
    }
    
    private function processDefaults(): void {
        if(isset($GLOBALS['user'])) $this->userId = $GLOBALS['user']->id ?: 0;
		$this->theCurrentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http").'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$this->visitTimestamp = (new \DateTime('now'))->format('U');
        $this->userIp = $_SERVER['REMOTE_ADDR'];
        
        $ua = $this->parser->parse();
        $this->browser = $ua->browser();
        $this->browserVersion = $ua->browserVersion();
        $this->platform = $ua->platform();
    }

    private function parseCookies(): void {
        if(isset($_COOKIE['__utma']) && isset($_COOKIE['__utmz'])) {
            // Parse the __utmz data
            $utmzArray = explode('.', $_COOKIE["__utmz"],5);
            $campaignData = $utmzArray[4];
            parse_str(strtr($campaignData, '|', '&'), $crumbs);
            
            $this->referrerMedium = $crumbs['utmcmd'];	// medium (organic, referral, direct, etc)
            $this->referrerSource = $crumbs['utmcsr'];	// source (google, facebook.com, etc)
            $this->referrerContent = $crumbs['utmcct'];	// content (index.html, etc)
            $this->referrerKeyword = $crumbs['utmctr'];	// term (search term)
            
            // Parse the __utma Cookie
            list($domainHash,$uniqueId,$timestampFirstVisit,$timestampPreviousVisit,$timestampStartCurrentVisit,$numSessionsStarted) = explode('.', $_COOKIE["__utma"]);
            
            $this->identifyUser	= $uniqueId;                                      // Get Google Analytics unique user ID.
            $this->firstVisit = date('U',$timestampFirstVisit);                   // Get timestamp of first visit.
            $this->previousVisit = date('U',$timestampPreviousVisit);             // Get timestamp of previous visit.
            $this->currentVisitStarted = date('U',$timestampStartCurrentVisit);   // Get timestamp of current visit.
            $this->timesVisited = $numSessionsStarted;                            // Get number of times visited.
            
            // Parse the __utmb Cookie
            list($domainHash,$pageViews,$garbage,$timestampStartCurrentVisit) = explode('.', $_COOKIE['__utmb']);
            $this->pagesViewed = $pageViews; // Get the total number of page views.
        }
    }

    private function logTraffic(): void {
        $this->initializeVisitor();
        $this->statement->execute([
            'ipAddress' => $this->userIp,
            'userId' => $this->userId,
            'identity' => $this->identifyUser
        ]);
        $this->visitorId = $this->pdo->lastInsertId();
        
        $this->initializePlatform();
        $this->statement->execute([
            'visitorId' => $this->visitorId,
            'browser' => $this->browser,
            'browserVersion' => $this->browserVersion,
            'os' => $this->platform,
            'timeLastVisited' => $this->visitTimestamp
        ]);

        $this->initializeVisit();
        $this->statement->execute([
            'visitorId' => $this->visitorId,
            'currentUrl' => $this->theCurrentUrl,
            'medium' => $this->referrerMedium,
            'source' => $this->referrerSource,
            'content' => $this->referrerContent,
            'keywords' => $this->referrerKeyword,
            'firstVisit' => $this->firstVisit,
            'previousVisit' => $this->previousVisit,
            'currentVisit' => $this->currentVisitStarted,
            'timesVisited' => $this->timesVisited,
            'pagesViewed' => $this->pagesViewed,
            'timestamp' => $this->visitTimestamp
        ]);
    }

    private function initializeVisitor(): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `LOG_VISITORS` 
            ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ipAddress` VARBINARY(16) NOT NULL DEFAULT 0x0,
            `userId` INT NOT NULL DEFAULT "0",
            `identity` VARCHAR(65) NOT NULL DEFAULT "0",
            PRIMARY KEY (`id`),
            UNIQUE INDEX `person`(`ipAddress`, `userId`, `identity`)) 
            ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;'
        );

        $this->statement = $this->pdo->prepare(
            'INSERT INTO `LOG_VISITORS`
            (`ipAddress`, `userId`, `identity`)
            VALUES (:ipAddress, :userId, :identity)'
        );
    }

    private function initializePlatform(): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `LOG_PLATFORMS` 
            ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `visitorId` INT UNSIGNED NOT NULL,
            `browser` VARCHAR(20) NOT NULL DEFAULT "",
            `browserVersion` VARCHAR(20) NOT NULL DEFAULT "",
            `os` VARCHAR(20) NOT NULL DEFAULT "",
            `timeLastVisited` INT UNSIGNED,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `entry`(`visitorId`, `browser`, `browserVersion`, `os`)) 
            ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;'
        );

        $this->statement = $this->pdo->prepare(
            'INSERT INTO `LOG_PLATFORMS`
            (`visitorId`, `browser`, `browserVersion`, `os`, `timeLastVisited`)
            VALUES (:visitorId, :browser, :browserVersion, :os, :timeLastVisited)
            ON DUPLICATE KEY UPDATE `timeLastVisited` = :timeLastVisited'
        );
    }

    private function initializeVisit(): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `LOG_VISITS` 
            ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `visitorId` INT UNSIGNED NOT NULL,
            `currentUrl` VARCHAR(350) NOT NULL,
            `medium` VARCHAR(50) NOT NULL DEFAULT "",
            `source` VARCHAR(60) NOT NULL DEFAULT "",
            `content` VARCHAR(150) NOT NULL DEFAULT "",
            `keywords` VARCHAR(100) NOT NULL DEFAULT "",
            `firstVisit` INT UNSIGNED NOT NULL DEFAULT 0 ,
            `previousVisit` INT UNSIGNED NOT NULL DEFAULT 0,
            `currentVisit` INT UNSIGNED NOT NULL DEFAULT 0,
            `timesVisited` INT NOT NULL DEFAULT 0,
            `pagesViewed` INT NOT NULL DEFAULT 0,
            `timestamp` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`id`)) 
            ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_520_ci;'
        );
    }
}