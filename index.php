<?php
include_once 'vendor/autoload.php';
use STAR\captains\log;

define('HOSTINFO','mysql:dbname=starneto_starNetOnline;host=localhost');
define('PASSWORD','Cartm@n123');
define('USERNAME','starneto_starNet');

$captainslog = new log('Local Test');

class user {
    public int $id = 0;
}

$user = new User();
$user->id = 25;

$captainslog->user = $user;

include 'test1.php';
include 'test2.php';

$captainslog->debug('start of test', ['current user' => $user]);

$fail = new test1();
$fail->fail();

// should never make it here
$captainslog->debug('end of test', ['current user' => $user]);