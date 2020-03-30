<?php

namespace Presenters;

class Teams extends \Presenters\MainPresenter {


	function __construct() {
		parent::__construct();
	}


	function login() {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . print_r($f3->get('REQUEST'), true));

		if ( !empty($f3->get('REQUEST.error')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Error authenticating: " . $f3->get('REQUEST.error') . ", " . $f3->get('REQUEST.error_description'));
			$f3->error(401, "Authentication error");
			return;
		}
		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseStaticPath'));
		} elseif ( !empty($f3->get('REQUEST.code')) ) {
			$authCode = $f3->get('REQUEST.code');
			$this->l->debug($this->tr . " - " . __METHOD__ . " - logged in");
		}

		$this->graph = new \GraphAPI($f3->get('scope'), $f3->get('redirectUriTeams'), $this->tr, $this->l);
		$this->graph->setAuthParams($f3->get('client_id'), $f3->get('client_secret'), $authCode);

		$authResponse = $this->graph->getToken('authorization_code');

		if (!$authResponse->success) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Error authenticating: " . $authResponse->message); 
			$f3->error(401, "Error authenticating: " . $authResponse->message);
		}

		$f3->set('SESSION.accessToken', $authResponse->accessToken);
		$f3->set('SESSION.refreshToken', $authResponse->refreshToken);
/*
		$userProfileResponse = $this->graph->getSignedInUser();

		if (!$userProfileResponse->success) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - error getting the user: " . print_r($userProfileResponse, true)); 
#			$f3->set('SESSION.accessToken', null);
#			$f3->set('SESSION.refreshToken', null);
			$f3->error(401, "Error getting user details");
		}

		$userModel = new \Models\UserModel();
		$userResponse = $userModel->saveUser($userProfileResponse->result, $authResponse->accessToken, $authResponse->refreshToken);

		$this->l->error($this->tr . " - " . __METHOD__ . " - user: " . print_r($userResponse->result['id'], true)); 

		$f3->set('SESSION.userId', $userResponse->result['id']);
		$f3->set('SESSION.signed_in_user', $userProfileResponse->result->displayName);
*/
		if ($f3->get('ENV') == 'DEV') {
			$f3->reroute('/teams/status');
		} else {
			$f3->reroute('/teams');
		}


	}

	function status($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$this->amIAuthenticated();
/*
		$userId = $f3->get('SESSION.userId');
		$userModel = new \Models\UserModel();

		$userResponse = $userModel->getUser($userId);
		if (!$userResponse->success) {
			$f3->set('SESSION.userId', null);
			$f3->reroute('/');
		}

		$accessToken = $userResponse->result['teamsTokens']['accessToken'];
		$refreshToken = $userResponse->result['teamsTokens']['refreshToken'];


		$this->graph = new \GraphAPI($f3->get('scope'), $f3->get('redirectUriTeams'), $this->tr, $this->l);
		$this->graph->setTokens($accessToken, $refreshToken);

		$presenceResponse = $this->graph->getMyPresence();

		if ($presenceResponse->result->availability == 'Available') {
			$f3->set('bg_class', 'bg-success');
		} else {
			$f3->set('bg_class', 'bg-danger');
		}
		
		$f3->set('current_page', 'STATUS');
		$f3->set('presence', json_encode($presenceResponse->result, JSON_PRETTY_PRINT));
*/
	}


	function getToken($f3, $args) {
		if (!$this->amIAuthenticated()) {
			return;
		}
		$response = new \Response($this->tr);
		$response->result->accessToken = $f3->get('SESSION.accessToken');
#		$response->result->refreshToken = $f3->get('SESSION.refreshToken');
		$response->success = true;
		$f3->set('page_type', 'AJAX');
		$f3->set('data', $response);
	} 

	function getConfig($f3, $args) {

		$response = new \Response($this->tr);
		$response->result->teamsLoginUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=' . $f3->get('client_id') . '&response_type=code&redirect_uri=' . urlencode($f3->get('redirectUriTeams')) . '&response_mode=query&scope=' . urlencode($f3->get('scope')) . '&state=' . $this->tr;
		$response->success = true;
		$f3->set('page_type', 'AJAX');
		$f3->set('data', $response);
	} 

	function amIAuthenticated($ajax = false) {
		$f3=\Base::instance();

#		if ( empty($f3->get('SESSION.userId')) ) {
		if ( empty($f3->get('SESSION.accessToken')) || empty($f3->get('SESSION.refreshToken')) ) {
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

	function afterroute($f3) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('status_teams.html');
		}

	}

	function logout($f3) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		session_destroy();

		$f3->reroute('/');
		

	}


}
?>
