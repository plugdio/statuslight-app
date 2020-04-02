<?php

namespace Services;

#use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Grant\RefreshToken;

class GCal extends \Services\ServiceBase {

	function __construct() {
		parent::__construct();
	}

	function login() {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . print_r($f3->get('REQUEST'), true));

#http://localhost:8000/gcal/login?state=094e7b&code=4/yQHVmBpfPA8eyklVB-PK8rdFCV7i1K9Uv88W00pT3CWP_1dWgqRrJlNF4eisSA6zvAeaG1EwlqKfSN6TPIUc2oQ&scope=https://www.googleapis.com/auth/calendar.readonly



		if ( !empty($f3->get('REQUEST.error')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Error authenticating: " . $f3->get('REQUEST.error'));
#			$f3->error(401, "Authentication error");
			$f3->reroute($f3->get('baseStaticPath') . '?error=' . urlencode('Authentication error'));
			return;
		}

		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseStaticPath'));
#		} elseif ( empty($f3->get('REQUEST.state')) || ($f3->get('REQUEST.state') !== $f3->get('SESSION.oauth2state')) ) {
#			// State is invalid, possible CSRF attack in progress
#    		unset($_SESSION['oauth2state']);
#    		exit('Invalid state');
		} elseif ( !empty($f3->get('REQUEST.code')) ) {
			$authCode = $f3->get('REQUEST.code');
			$this->l->debug($this->tr . " - " . __METHOD__ . " - logged in");
		}

		// Try to get an access token (using the authorization code grant)
    	$token = $this->gcalProvider->getAccessToken('authorization_code', [
        	'code' => $authCode
    	]);

    	try {
    		$this->l->debug($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
    	} catch (Exception $e) {
    		$this->l->error($this->tr . " - " . __METHOD__ . " - Exception: " . print_r($e, true));
    	}
    	

		$f3->set('SESSION.accessToken', $token->getToken());
		$f3->set('SESSION.refreshToken', $token->getRefreshToken());
		$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

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

		$f3->reroute('/gcal');

	}

	function status($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		$this->amIAuthenticated();

	}


	function getToken($f3, $args) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		if (!$this->amIAuthenticated(true)) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Not authenticated");
			return;
		}
		$response = new \Response($this->tr);
		$f3->set('page_type', 'AJAX');

		if ($f3->get('SESSION.accessTokenExpiresOn') < time() + 600) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - Token needs to be refreshed");


			$grant = new RefreshToken();
			$token = $this->gcalProvider->getAccessToken($grant, ['refresh_token' => $f3->get('SESSION.refreshToken')]);

			$f3->set('SESSION.accessToken', $token->getToken());
			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

		}

		$response->result->accessToken = $f3->get('SESSION.accessToken');
		$response->result->accessTokenExpiresOn = $f3->get('SESSION.accessTokenExpiresOn');
#		$response->result->refreshToken = $f3->get('SESSION.refreshToken');
		$response->success = true;
		$f3->set('data', $response);
	} 

	function afterroute($f3) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('status_gcal.html');
		}

	}

	function logout($f3) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		session_destroy();

		$f3->reroute('/');
		

	}


}
?>
