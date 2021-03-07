<?php

namespace Presenters;

class Phone {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}


	function main($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ( empty($f3->get('SESSION.accessToken')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no token - " . print_r($f3->get('SESSION'), true));
			$f3->clear('SESSION');
			$f3->reroute('/');
		}

		echo \Template::instance()->render('phone_status.html');

	}

	function status($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ( empty($f3->get('SESSION.accessToken')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no token - " . print_r($f3->get('SESSION'), true));
			$f3->clear('SESSION');
			$f3->error(401);
		}

		$userId = $f3->get('SESSION.userId');
		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->getActiveSessionForUser($userId);

		if (!$sessionResponse->success) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - session error - " . $sessionResponse->message);
			$f3->clear('SESSION');
			$f3->error(500);
		}

		$response = new \Response($this->tr);
		$response->result->status = $sessionResponse->result['presenceStatus'];
		$response->result->statusDetail = $sessionResponse->result['presenceStatusDetail'];

		$dummySessionResponse = $sessionModel->getActiveDummySessionForUser($userId);

		if (!$dummySessionResponse->success) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - No active dummy sessions");
		} else {
			$response->result->status = $dummySessionResponse->result['presenceStatus'];
			$response->result->statusDetail = $dummySessionResponse->result['presenceStatusDetail'];
		}

		$response->success = true;

		header('Content-Type: application/json');
		echo json_encode($response, JSON_PRETTY_PRINT);

	}

}
?>
