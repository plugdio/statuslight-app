<?php

namespace Services;

use League\OAuth2\Client\Provider\Google;

class ServiceBase {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

	}

	function getConfig($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$response = new \Response($this->tr);

		$teamsLoginUrl = \Services\Teams::getLoginUrl('phone');
		$teamsLoginUrlDevice = \Services\Teams::getLoginUrl('device');

		$gcalLoginUrl = \Services\GCal::getLoginUrl('phone');
		$gcalLoginUrlDevice = \Services\GCal::getLoginUrl('device');


		$slackLoginUrl = \Services\Slack::getLoginUrl('phone');
		$slackLoginUrlDevice = \Services\Slack::getLoginUrl('device');

		$response->result->teamsLoginUrl = $teamsLoginUrl;
		$response->result->gcalLoginUrl = $gcalLoginUrl;
		$response->result->slackLoginUrl = $slackLoginUrl;
		
		$response->result->teamsLoginUrlDevice = $teamsLoginUrlDevice;
		$response->result->gcalLoginUrlDevice = $gcalLoginUrlDevice;
		$response->result->slackLoginUrlDevice = $slackLoginUrlDevice;

		$response->success = true;
		$f3->set('page_type', 'AJAX');
		$f3->set('data', $response);
	} 

	function amIAuthenticated($ajax = false) {
		$f3=\Base::instance();

		if ( empty($f3->get('SESSION.accessToken')) ) {
#		if ( empty($f3->get('SESSION.accessToken')) || empty($f3->get('SESSION.refreshToken')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no tokens - " . print_r($f3->get('SESSION'), true));
			if ($ajax) {
				$f3->set('page_type', 'AJAX');
				$response = new \Response($this->tr);
				$response->message = 'Not authenticated';
				$f3->set('data', $response);
				return false;
			} else {
				$f3->reroute($f3->get('baseStaticPath'));
			}
		}
		return true;
	}


	function beforeroute($f3, $args) {
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
	}


	function afterroute($f3) {
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('blank.html');
		}

	}

	function blank($f3, $args) {

		$f3->set('base_app_path', $f3->get('baseAppPath'));
		$f3->set('current_page', 'REGISTER');

	}

	function logout($f3) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");


		$f3->clear('SESSION');

		$f3->reroute($f3->get('baseStaticPath'));
		

	}


}
?>