<?php

namespace Ajax;

class Status extends \Ajax\MainAjax {

	function __construct() {
		parent::__construct();
	}


	function getStatus($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		$response = new \Response($this->tr);

		$userId = $f3->get('SESSION.userId');
		$userModel = new \Models\UserModel();

		$userResponse = $userModel->getUser($userId);
		if (!$userResponse->success) {
			$f3->set('SESSION.userId', null);
			$f3->set('data', $userResponse);
			return;
		}

		$accessToken = $userResponse->result['teamsTokens']['accessToken'];
		$refreshToken = $userResponse->result['teamsTokens']['refreshToken'];

		$this->graph = new \GraphAPI($f3->get('scope'), $f3->get('redirectUri'), $this->tr, $this->l);
		$this->graph->setTokens($accessToken, $refreshToken);

		$presenceResponse = $this->graph->getMyPresence();

		if ($presenceResponse->result->availability == 'Available') {
			$f3->set('bg_class', 'bg-success');
		} else {
			$f3->set('bg_class', 'bg-danger');
		}
		
		$f3->set('data', $presenceResponse);
	}

}
?>
