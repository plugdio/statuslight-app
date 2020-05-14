<?php

namespace Backend;

require_once "lib/phpMQTT.php";

class MqttComm {

	private static $dbInstance = null;
	private $broker = null;

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

		if (!$f3->get('CLI')) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Request is not comming from CLI");
			$f3->error(401);
		}

		if ($f3->get('ENV') == 'DEV') {
			$mqttHost = 'test.statuslight.online';
		} else {
			$mqttHost = 'sl_mosquitto.statuslight-app_backend';
		}
		$mqttPort = 1883;
		$mqttUser = 'adm_app_' . $f3->get('ENV');
		$mqttPass = 'sladmin123';

		$this->l->info($this->tr . " - " . __METHOD__ . " - Connecting to: " . $mqttHost);
		$a = 0;
		while (($this->broker == null) && $a < 100) {
			$this->broker = new \phpMQTT($mqttHost, $mqttPort, 'statuslightapp'); 
			if ($this->broker->connect(true, NULL, $mqttUser, $mqttPass)) {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - Conected to the broker");
				$a = 0;
			} else {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Connection to broker failed with user " . $mqttUser);
				$this->broker = null;
				$a++;
				sleep(10);
			}
		}

	}

	public static function getDbInstance() {

		$f3=\Base::instance();
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$l->debug($tr . " - " . __METHOD__ . " - Start");

		if (self::$dbInstance == null) {
			$l->info($tr . " - " . __METHOD__ . " - New dbInstance instance created");
			$db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
			self::$dbInstance = new \DB\Jig\Mapper($db, 'mqttclients.json');
		}
		return self::$dbInstance;
	}


	public function subscribe() {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if (empty($this->broker)) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - No broker connetion");
			return;
		}

		$topics['SL/#'] = array("qos"=>0, "function"=>'\Backend\MqttComm::procMqttMessage');
		$this->broker->subscribe($topics, 0);

		$mqttMessageModel = new \Models\MqttMessage();

		$i = 0;
		while ($this->broker->proc(true) && ($i < 1500000)) {
			$i++;
			$messageResponse = $mqttMessageModel->getFromQueue();
			if ($messageResponse->success) {
				foreach ($messageResponse->result as $message) {
					$this->l->trace($this->tr . " - " . __METHOD__ . " - message: " . print_r($message, true));
					$this->broker->publish($message['topic'], $message['content']);
					$mqttMessageModel->updateMessage($message['_id'], MQTTMSG_SENT);
					$i = 0;
				}
			}

			if ($i == 120) {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - PING");
				$this->broker->ping();
				$i = 0;
			}

		}

	}

	public static function procMqttMessage($topic, $msg) {
		$f3=\Base::instance();
		$tr = $f3->get('tr');
		$l = $f3->get('log');

#		$l->debug($tr . " - " . __METHOD__ . " - Message received - Topic: " . $topic . ', msg: ' . $msg);

		if (preg_match('/SL\/([^\/]*)\/\$(.*)/', $topic, $matches)) {
			$mqttClient = \Backend\MqttComm::getDbInstance();
			$mqttClient->load(array('@id=?', $matches[1]));
			if ($mqttClient->dry()) {
				$mqttClient->reset();
				$mqttClient->id = $matches[1];
			}
			$mqttClient->updated = time();
			$mqttClient->{$matches[2]} = $msg;
			$mqttClient->save();
		} elseif (preg_match('/SL\/[^\/]*\/statuslight\/.*/', $topic)) {
			# code...
		} else {
			$l->error($tr . " - " . __METHOD__ . " - Unknown message received - Topic: " . $topic . ', msg: ' . $msg);			
		}
		
	}

	function disconnect() {
		$this->broker->close();
	}

}
?>
