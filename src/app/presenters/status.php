<?php

namespace Presenters;

class Status {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}


	function main($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$svApi = new \Supervisor\Api('127.0.0.1', 9001 /* username, password */);

		$status = new \stdClass();
#		$status->supervisorApiVersion = $svApi->getApiVersion();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - " . print_r($svApi->getState(), true));
#		$status->states = array();
		$status->processes = array();
		$status->supervisord = $svApi->getState()["statename"];
		foreach (array('php-fpm:php-fpm_00', 'mqtt-connector', 'sessionmanager') as $name) {
#			$status->states[$name] = $svApi->getProcessInfo($name)["statename"];
			$status->processes[] = $svApi->getProcessInfo($name);
		}

		header('Content-Type: application/json');
		echo json_encode($status, JSON_PRETTY_PRINT);

	}


}
?>
