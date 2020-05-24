<?php

namespace Backend;

require_once "lib/phpMQTT.php";

class MqttComm {

	private $broker = null;

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

		if (!$f3->get('CLI')) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Request is not comming from CLI");
			$f3->error(401);
		}

		$this->mqttMessageModel = new \Models\MqttMessage();

		$mqttHost = $f3->get('mqtt_host');
		$mqttPort = $f3->get('mqtt_port');
		$mqttUser = $f3->get('mqtt_user');
		$mqttPass = $f3->get('mqtt_password');

		$this->l->info($this->tr . " - " . __METHOD__ . " - Connecting to: " . $mqttHost);
		$a = 0;
		while (($this->broker == null) && $a < 100) {
			$this->broker = new \phpMQTT($mqttHost, $mqttPort, 'statuslightapp'); 
			if ($this->broker->connect(true, NULL, $mqttUser, $mqttPass)) {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - Conected to the broker");
				$this->startTime = time();
				$a = 0;
			} else {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Connection to broker failed with user " . $mqttUser);
				$this->broker = null;
				$a++;
				sleep(10);
			}
		}

	}

	public function subscribe() {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if (empty($this->broker)) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - No broker connetion");
			return;
		}

		$topics['SL/#'] = array("qos"=>0, "function"=>'\Backend\MqttComm::procMqttMessage');
		$this->broker->subscribe($topics, 0);

		$i = 0;
		$messagesSent = 0;
		while ($this->broker->proc(true) && ($i < 1500000)) {
			$i++;
			$messageResponse = $this->mqttMessageModel->getFromQueue();
			if ($messageResponse->success) {
				foreach ($messageResponse->result as $message) {
					$messagesSent++;
					$this->l->debug($this->tr . " - " . __METHOD__ . " - message: " . print_r($message, true));
					$this->broker->publish($message['topic'], $message['content'], 0, 1);
//					$this->mqttMessageModel->updateMessage($message['id'], MQTTMSG_SENT);
					$this->mqttMessageModel->deleteMessage($message['id']);
					$i = 0;
				}
			}

			if ($i == 420) {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - PING - messages sent: " . $messagesSent);
				$uptime = time() - $this->startTime;
				$this->broker->publish('SL/APP_' . $f3->get('ENV') . '/$name', $this->tr, 0, 0);
				$this->broker->publish('SL/APP_' . $f3->get('ENV') . '/$stats/uptime', $uptime, 0, 0);
				
				$this->broker->ping();
				$i = 0;
				$messagesSent = 0;
			}

		}

	}

	public static function procMqttMessage($topic, $msg) {
		$f3=\Base::instance();
		$tr = $f3->get('tr');
		$l = $f3->get('log');

#		$l->debug($tr . " - " . __METHOD__ . " - Message received - Topic: " . $topic . ', msg: ' . $msg);
		$mqttClientModel = new \Models\MqttClient();
		if (preg_match('/SL\/([^\/]*)\/\$(.*)/', $topic, $matches)) {
			$mqttClientModel->updateClient($matches[1], $matches[2], $msg, true);
		} elseif (preg_match('/SL\/([^\/]*)\/(statuslight\/.*)/', $topic, $matches)) {
			$mqttClientModel->updateClient($matches[1], $matches[2], $msg, false);
		} else {
			$l->error($tr . " - " . __METHOD__ . " - Unknown message received - Topic: " . $topic . ', msg: ' . $msg);			
		}
		
	}

	function disconnect() {
		$this->broker->close();
	}

}
?>
