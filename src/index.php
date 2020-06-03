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
#$f3->set('UI', $path . 'ui/');
$f3->set('UI', 'ui/');
$f3->set('dbdir', $path . 'data/');

$f3->set('teams_client_id', trim(getenv('TEAMSCLIENTID')));
$f3->set('teams_client_secret', trim(getenv('TEAMSCLIENTSECRET')));
$f3->set('gcal_client_id', trim(getenv('GCALCLIENTID')));
$f3->set('gcal_client_secret', trim(getenv('GCALCLIENTSECRET')));
$f3->set('slack_client_id', trim(getenv('SLACKCLIENTID')));
$f3->set('slack_client_secret', trim(getenv('SLACKCLIENTSECRET')));
$f3->set('mqtt_host', trim(getenv('MQTTHOST')));
$f3->set('mqtt_port', trim(getenv('MQTTPORT')));
$f3->set('mqtt_user', trim(getenv('MQTTADMINUSER')));
$f3->set('mqtt_password', trim(getenv('MQTTADMINPASS')));

#$f3->set('db_host', trim(getenv('DBHOST')));
#$f3->set('db_user', trim(getenv('DBUSER')));
#$f3->set('db_pass', trim(getenv('DBPASS')));

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

$f3->route('GET|HEAD /',
    function($f3) {
        echo \Template::instance()->render('index.html');
    }
);

$f3->route('GET|HEAD /device',
    function($f3) {
        echo \Template::instance()->render('device.html');
    }
);

$f3->route('GET /blank', '\Services\ServiceBase->blank');

$f3->route('GET /config', '\Presenters\Config->getConfig');

$f3->route('GET /login/@service/@target', '\Presenters\Login->login');
$f3->route('GET /logout', '\Presenters\Login->logout');

$f3->route('GET /phone/status', '\Presenters\Phone->main');
$f3->route('GET /phone/status/refresh', '\Presenters\Phone->status');
$f3->route('GET /device/status', '\Presenters\Device->main');
$f3->route('GET /device/add', '\Presenters\Device->addDevice');
$f3->route('GET /device/delete/@deviceId', '\Presenters\Device->deleteDevice');

$f3->route('GET /profile', '\Presenters\Profile->main');
$f3->route('GET /profile/delete', '\Presenters\Profile->delete');

$f3->route('GET /status', '\Presenters\ServiceStatus->main');

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