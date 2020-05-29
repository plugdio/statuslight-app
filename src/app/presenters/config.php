<?php

namespace Presenters;

class Config {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}


	function getConfig($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$response = new \Response($this->tr);

		$response->result->teamsLoginUrlPhone = \Services\Teams::getLoginUrl('phone');
		$response->result->googleLoginUrlPhone = \Services\GCal::getLoginUrl('phone');
		$response->result->slackLoginUrlPhone = \Services\Slack::getLoginUrl('phone');
		
		$response->result->teamsLoginUrlDevice = \Services\Teams::getLoginUrl('device');
		$response->result->googleLoginUrlDevice = \Services\GCal::getLoginUrl('device');
		$response->result->slackLoginUrlDevice = \Services\Slack::getLoginUrl('device');

		$response->success = true;

		header('Content-Type: application/json');
		echo json_encode($response, JSON_PRETTY_PRINT);

	} 


}
?>
