<?php

namespace Models;

use TheNetworg\OAuth2\Client\Provider\Azure;

class Session {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
        $this->session = new \DB\Jig\Mapper($db, 'sessions.json');
	}

	function saveSession($sessionType, $userId, $token) {
    	$this->session->type = $sessionType;
    	$this->session->userId = $userId;
    	$this->session->token = serialize($token);
    	$this->session->startTime = time();
    	$this->session->lastUpdatedTime = time();
    	$this->session->state = SESSION_STATE_ACTIVE;
    	$this->session->save();
	}

	function getActiveSessionForUser($userId) {
		$response = new \Response($this->tr);
		$this->session->load(array('@userId=? AND @state=?', $userId, SESSION_STATE_ACTIVE));
		if ($this->session->dry()) {
			$response->message = 'Session not found';
			return $response;
		}

		$response->result = $this->session->cast();
		$response->success = true;
		return $response;
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

			$token = unserialize($this->session->token);

			try {

				if ($token->hasExpired()) {
					$this->l->debug($this->tr . " - " . __METHOD__ . " - token needs to be refreshed");
	            	$token = $provider->getAccessToken('refresh_token', [
	                	'refresh_token' => $token->getRefreshToken(),
	            	]);
	            	$this->session->token = serialize($token);
	        	}

				$response = $provider->request('get', $ref, $token, []);

				$this->l->debug($this->tr . " - " . __METHOD__ . " - response: " . print_r($response, true));

			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$response = array(
					'exception' => $e->getMessage()
				);
			}


			if (array_key_exists('availability', $response)) {

			} elseif (array_key_exists('exception', $response)) {
				$this->session->state = SESSION_STATE_ERROR;
				$this->session->error = $response["exception"];
			} else {
				$this->session->state = SESSION_STATE_INACTIVE;

			}
			
			$this->session->lastUpdatedTime = time();
			$this->session->save();
			$this->session->next();
		}
	}

}

?>
