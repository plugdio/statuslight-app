<?php

namespace Models;

class MqttClient {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
		$this->client = new \DB\SQL\Mapper($f3->get('db'), 'devices');
	}

	function getClientById($clientId) {

		$response = new \Response($this->tr);
		$this->client->load(array('mqttClientId=?', $clientId));
		if ($this->client->dry()) {
			$response->message = 'Client not found';
			return $response;
		}

		$response->result = $this->client->cast();
		$response->success = true;
		return $response;

	}

	function updateClient($clientId, $topic, $msg, $updateTime = false) {
		$this->client->load(array('mqttClientId=?', $clientId));
		if ($this->client->dry()) {
			$this->client->reset();
			$this->client->mqttClientId = $clientId;
		}
		if ($updateTime) {
			$this->client->mqttUpdated = date('Y-m-d H:i:s', time());
		}
		$this->client->mqttContent = $msg;
		$this->client->save();
	}
}

?>
