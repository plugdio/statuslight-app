<?php

namespace Services;

class Slack extends \Services\ServiceBase {


	function __construct() {
		parent::__construct();
	}


	function login() {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . print_r($f3->get('REQUEST'), true));


		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseStaticPath'));
 		// Check given state against previously stored one to mitigate CSRF attack
#		} elseif (empty($f3->get('REQUEST.state')) || ($f3->get('REQUEST.state') !== $f3->get('SESSION.oauth2state'))) {
# 			$f3->set('SESSION.oauth2state', null);		
# 			$f3->reroute($f3->get('baseStaticPath') . '?error=' . urlencode('Invalid state'));
		} else {
			$token = $this->slackProvider->getAccessToken('authorization_code', [
        		'code' => $f3->get('REQUEST.code')
    		]);

	    	try {
    			$this->l->debug($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
    		} catch (Exception $e) {
    			$this->l->error($this->tr . " - " . __METHOD__ . " - Exception: " . print_r($e, true));
    		}
    	

			$f3->set('SESSION.accessToken', $token->getToken());
			$userId = $this->slackProvider->getAuthorizedUser($token)->getId();

			$this->l->debug($this->tr . " - " . __METHOD__ . " - userId: " . print_r($userId, true));

/*
			try {
 
        		// We got an access token, let's now get the user's details
        		$team = $this->slackProvider->getResourceOwner($token);
        		$this->l->debug($this->tr . " - " . __METHOD__ . " - team: " . print_r($team, true));
 
		    } catch (Exception $e) {
 
		        // Failed to get user details
		        $this->l->error($this->tr . " - " . __METHOD__ . " - Exception2: " . print_r($e, true));
		    }
*/
#			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
#			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

		    $f3->set('SESSION.user_id', $userId);
			$f3->reroute('/slack');
		}

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
/*
		if ($f3->get('SESSION.accessTokenExpiresOn') < time() + 600) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - Token needs to be refreshed");


			$grant = new RefreshToken();
			$token = $this->gcalProvider->getAccessToken($grant, ['refresh_token' => $f3->get('SESSION.refreshToken')]);

			$f3->set('SESSION.accessToken', $token->getToken());
			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

		}
*/
		$response->result->accessToken = $f3->get('SESSION.accessToken');
#		$response->result->accessTokenExpiresOn = $f3->get('SESSION.accessTokenExpiresOn');
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
			echo \Template::instance()->render('status_slack.html');
		}

	}

}
?>
