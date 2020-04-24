<?php

namespace Models;

use TheNetworg\OAuth2\Client\Provider\Azure;

class Session {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig('data/', \DB\Jig::FORMAT_JSON);
        $this->session = new \DB\Jig\Mapper($db, 'sessions.json');
	}

	function saveSession($sessionType = null, $token = null) {
    	$this->session->type = $sessionType;
    	$this->session->token = serialize($token);
    	$this->session->startTime = time();
    	$this->session->lastUpdatedTime = time();
    	$this->session->state = SESSION_STATE_ACTIVE;
    	$this->session->save();
	}

	function refreshSessions() {
		$f3=\Base::instance();

		$this->session->load(array('@state=?', SESSION_STATE_ACTIVE));
		while(!$this->session->dry()) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - working with " . $this->session->_id);

			$provider = new Azure([
		    	'clientId'          => $f3->get('teams_client_id'),
		    	'clientSecret'      => $f3->get('teams_client_secret'),
		    	'redirectUri'		=> $f3->get('baseAppPath') . '/teams/login',
		    	'authWithResource' 	=> false,
//			    'proxy'                   => 'localhost:8888',
//	  	  		'verify'                  => false
			]);

			$provider->urlAPI = 'https://graph.microsoft.com/beta/';
			$ref = 'me/presence';

			$accessToken = unserialize($this->session->token);

			if ($accessToken->hasExpired()) {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - token needs to be refreshed");
            	$accessToken = $provider->getAccessToken('refresh_token', [
                	'refresh_token' => $accessToken->getRefreshToken(),
            	]);
            	$this->session->token = serialize($accessToken);
        	}

			$response = $provider->request('get', $ref, $accessToken, []);

			$this->l->debug($this->tr . " - " . __METHOD__ . " - response: " . print_r($response, true));

			if (array_key_exists('availability', $response)) {
				$this->session->lastUpdatedTime = time();
				$this->session->save();
			} else {
				$this->session->SESSION_STATE_INACTIVE;
				$this->session->lastUpdatedTime = time();
				$this->session->save();
			}

			$this->session->next();
		}
	}

/*
	function saveUser($teamsProfile, $accessToken, $refreshToken) {

		$response = new \Response($this->tr);
		$this->user->load(array('@teamsId=?', $teamsProfile->id));
		if ($this->user->dry()) {
			$this->user->id = guid();
			$this->user->teamsId = $teamsProfile->id;
			$this->user->teamsProfile = $teamsProfile;
			$this->user->save();
		}
		$teamsTokens = new \stdClass();
		$teamsTokens->accessToken = $accessToken;
		$teamsTokens->refreshToken = $refreshToken;
		$this->user->teamsTokens = $teamsTokens;
		$this->user->save();

		$response->result = $this->user->cast();
		$response->success = true;
		return $response;

	}

	function getUser($userId) {

		$response = new \Response($this->tr);
		$this->user->load(array('@id=?', $userId));
		if ($this->user->dry()) {
			$response->message = 'User not found';
			return $response;
		}

		$response->result = $this->user->cast();
		$response->success = true;
		return $response;

	}
*/
}

?>