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
		$status->supervisorApiVersion = $svApi->getApiVersion();

		header('Content-Type: application/json');
		echo json_encode($status, JSON_PRETTY_PRINT);

	}


}
?>
