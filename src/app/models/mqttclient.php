<?php

namespace Models;

class MqttClient {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
        $this->client = new \DB\Jig\Mapper($db, 'mqttclients.json');
	}

	function getClientById($clientId) {

		$response = new \Response($this->tr);
		$this->client->load(array('@id=?', $clientId));
		if ($this->client->dry()) {
			$response->message = 'Client not found';
			return $response;
		}

		$response->result = $this->client->cast();
		$response->success = true;
		return $response;

	}


}

?>
