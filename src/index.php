<?php

date_default_timezone_set("Europe/Oslo");

require 'vendor/autoload.php';

require_once "lib/helpers.php";
require_once "lib/constants.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use \Bramus\Monolog\Formatter\ColoredLineFormatter;

$path = realpath(dirname(__FILE__)) . "/";

$logStream = new Monolog\Handler\StreamHandler($path . 'logs/pres_' . date('Y-m-d', time()) . '.log', Monolog\Logger::DEBUG);
$logStdOut = new Monolog\Handler\StreamHandler('php://stdout', Monolog\Logger::DEBUG);

$logStdOut->setFormatter(new ColoredLineFormatter());

$log = new Monolog\Logger('pres');
$log->pushHandler($logStream);
$log->pushHandler($logStdOut);

$f3 = \Base::instance();

$f3->set('CORS.origin', '*'); 

if (rtrim(strtoupper(getenv('STATUSLIGHT_ENV'))) == 'DEV') {
    $f3->set('DEBUG',3);
    $f3->set('ENV', 'DEV');
    $f3->set('baseStaticPath', 'http://' . trim(getenv('STATICURL')));
    $f3->set('baseAppPath', 'http://' . trim(getenv('DOMAIN')));
} else {
    $f3->set('ENV', strtoupper(getenv('STATUSLIGHT_ENV')));
    $f3->set('baseStaticPath', 'https://' . trim(getenv('STATICURL')));
    $f3->set('baseAppPath', 'https://' . trim(getenv('DOMAIN')));
}

$f3->set('path', $path);
$f3->set('AUTOLOAD', $path . 'app/');
$f3->set('UI', $path . 'ui/');
$f3->set('dbdir', $path . 'data/');

$f3->set('teams_client_id', trim(getenv('TEAMSCLIENTID')));
$f3->set('teams_client_secret', trim(getenv('TEAMSCLIENTSECRET')));
$f3->set('gcal_client_id', trim(getenv('GCALCLIENTID')));
$f3->set('gcal_client_secret', trim(getenv('gcal_client_secret')));
$f3->set('slack_client_id', trim(getenv('SLACKCLIENTID')));
$f3->set('slack_client_secret', trim(getenv('SLACKCLIENTSECRET')));
$f3->set('mqtt_host', trim(getenv('MQTTHOST')));
$f3->set('mqtt_port', trim(getenv('MQTTPORT')));
$f3->set('mqtt_user', trim(getenv('MQTTADMINUSER')));
$f3->set('mqtt_password', trim(getenv('MQTTADMINPASS')));

#$f3->set('db_host', trim(getenv('DBHOST')));
#$f3->set('db_user', trim(getenv('DBUSER')));
#$f3->set('db_pass', trim(getenv('DBPASS')));

$db = new \DB\SQL(
    'mysql:host=' . trim(getenv('DBHOST')) . ';port=3306;dbname=statuslight',
    trim(getenv('DBUSER')),
    trim(getenv('DBPASS'))
);
$f3->set('db', $db);

$f3->set('log', $log);
$tr = substr(md5(uniqid(rand(), true)),0,6);
$f3->set('tr', $tr);

/*
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
*/

$log->debug($tr . " - " . 'init' . " - START - ENV: " . getenv('STATUSLIGHT_ENV'));
$log->info($tr . ' - New request: ' . $_SERVER["REQUEST_METHOD"] . " " . $_SERVER["REQUEST_URI"] . " - " . $_SERVER["AUTH_USER"]);

if ( ($f3->get('ENV') == 'DEV') || ($f3->get('ENV') == 'TEST') ) {
    $f3->route('GET /', '\Services\ServiceBase->blank');
} else {
    $f3->route('GET|HEAD /',
        function($f3) {
            $f3->reroute('https://statuslight.online');
        }
    );
}

$f3->route('GET /blank', '\Services\ServiceBase->blank');

$f3->route('GET /config', '\Services\ServiceBase->getConfig');
$f3->route('GET /logout', '\Services\ServiceBase->logout');

$f3->route('GET /teams/login', '\Services\Teams->login');
$f3->route('GET /teams', '\Services\Teams->status');
$f3->route('GET /teams/token', '\Services\Teams->getToken');


$f3->route('GET /gcal/login', '\Services\GCal->login');
$f3->route('GET /gcal', '\Services\GCal->status');
$f3->route('GET /gcal/token', '\Services\GCal->getToken');


$f3->route('GET /slack/login', '\Services\Slack->login');
$f3->route('GET /slack', '\Services\Slack->status');
$f3->route('GET /slack/token', '\Services\Slack->getToken');

$f3->route('GET /device', '\Presenters\Device->main');
$f3->route('GET /device/login/teams', '\Presenters\Device->loginWithTeams');
$f3->route('GET /device/login/gcal', '\Presenters\Device->loginWithGoogle');
$f3->route('GET /device/login/slack', '\Presenters\Device->loginWithSlack');
$f3->route('GET /device/add', '\Presenters\Device->addDevice');

$f3->route('GET /status', '\Presenters\Status->main');

$f3->route('GET /backend/mqttconnector', '\Backend\MqttComm->subscribe');
$f3->route('GET /backend/sessionmanager', '\Backend\SessionManager->run');

$f3->route('POST /backend/mqtt/auth', '\Backend\MqttAuth->auth');
$f3->route('POST /backend/mqtt/superuser', '\Backend\MqttAuth->superuser');
$f3->route('POST /backend/mqtt/acl', '\Backend\MqttAuth->acl');

#$f3->route('GET /backend/jobs/getstatus', '\Backend\SessionManager->refreshSessions');


try {
    $f3->run();
} catch (Exception $e) {
    $log->debug($tr . " - " . 'run' . " - Caught exception: " . $e->getMessage());
    $log->debug($tr . " - " . 'run' . " - Caught exception: " . print_r($e, true));
	$f3->set('error_text', "Caught exception: " . $e->getMessage() . ' - ' . $e->getTraceAsString());
    echo \Template::instance()->render('index.html');
}

?>