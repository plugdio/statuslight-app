<?php

date_default_timezone_set("Europe/Oslo");

require 'vendor/autoload.php';

require_once "lib/helpers.php";
require_once "lib/graphapi.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use \Bramus\Monolog\Formatter\ColoredLineFormatter;

$logStream = new Monolog\Handler\StreamHandler('logs/pres_' . date('Y-m-d', time()) . '.log', Monolog\Logger::DEBUG);
$logStdOut = new Monolog\Handler\StreamHandler('php://stdout', Monolog\Logger::DEBUG);

$logStdOut->setFormatter(new ColoredLineFormatter());

$log = new Monolog\Logger('pres');
$log->pushHandler($logStream);
$log->pushHandler($logStdOut);

$f3 = \Base::instance();

$f3->set('CORS.origin', '*'); 

if (!empty(getenv('STATUSLIGHT_ENV')) && (strtoupper(getenv('PRES_ENV')) != 'PROD') ) {
    $f3->set('ENV', 'DEV');
    $f3->set('baseStaticPath', 'http://localhost:8000');
    $f3->set('baseAppPath', 'http://localhost:8000');
} else {
    $f3->set('ENV', 'PROD');
    $f3->set('baseStaticPath', 'https://statuslight.online');
    $f3->set('baseAppPath', 'https://my.statuslight.online');
}

$f3->set('redirectUriTeams', $f3->get('baseAppPath') . '/teams/login');
$f3->set('scope', 'offline_access user.read Presence.Read');
#$f3->set('scope', 'offline_access Presence.Read');


$path = realpath(dirname(__FILE__)) . "/";
$f3->set('path', $path);
$f3->set('AUTOLOAD', $path . 'app/');
$f3->set('dbdir', $path . 'data/');

$f3->set('log', $log);
$tr = substr(md5(uniqid(rand(), true)),0,6);
$f3->set('tr', $tr);
$f3->config('config.ini');


$f3->set('ONERROR',
    function($f3) {

        global $log, $tr;

        $log->error($tr . ' - OnError: ' . $f3->get('ERROR.code') . " " . $f3->get('ERROR.text') . " " . $f3->get('ERROR.trace'));

#        $smtp = new \SMTP ( $f3->get('SMTP_HOST'), $f3->get('SMTP_PORT'), '', '', '' );
#        $smtp->set('Errors-to', $f3->get('ERRORS_TO'));
#        $smtp->set('To', $f3->get('ERRORS_TO'));
#        $smtp->set('From', $f3->get('ERRORS_FROM'));
#        $smtp->set('Subject', '[' . $f3->get('SYSTEMID') . '] TC error');
#        $smtp->send($tr . ' - OnError: ' . $f3->get('ERROR.code') . " " . $f3->get('ERROR.text') . " " . $f3->get('ERROR.trace'), true);

        echo "<h1>Error</h1><br>";
        echo $f3->get('ERROR.text');
    }
);


$log->debug($tr . " - " . 'init' . " - START  ");
$log->info($tr . ' - New request: ' . $_SERVER["REQUEST_METHOD"] . " " . $_SERVER["REQUEST_URI"] . " - " . $_SERVER["AUTH_USER"]);
/*
foreach (getallheaders() as $name => $value) {
    $log->debug($tr . ' -  ' . $name . ' : ' . $value);
}
*/

if ($f3->get('ENV') == 'DEV') {
    $f3->route('GET /', '\Services\ServiceBase->blank');
} else {
    $f3->route('GET|HEAD /',
        function($f3) {
            $f3->reroute('https://statuslight.online');
        }
    );
    $f3->route('GET /blank', '\Services\ServiceBase->blank');
}

$f3->route('GET /config', '\Services\ServiceBase->getConfig');
$f3->route('GET /logout', '\Services\ServiceBase->logout');

$f3->route('GET /teams/login', '\Services\Teams->login');
$f3->route('GET /teams', '\Services\Teams->status');
$f3->route('GET /teams/token', '\Services\Teams->getToken');


$f3->route('GET /gcal/login', '\Services\GCal->login');
$f3->route('GET /gcal', '\Services\GCal->status');
$f3->route('GET /gcal/token', '\Services\GCal->getToken');


try {
    $f3->run();
} catch (Exception $e) {
    $log->debug($tr . " - " . 'run' . " - Caught exception: " . $e->getMessage());
#    $log->debug($tr . " - " . 'run' . " - Caught exception: " . print_r($e, true));
	$f3->set('error_text', "Caught exception: " . $e->getMessage() . ' - ' . $e->getTraceAsString());
    echo \Template::instance()->render('index.html');
}

?>