<?php

namespace Services;

class Teams {


	function __construct() {
		parent::__construct();
	}

	public static function getProvider($target) {
		$f3=\Base::instance();
		$tr = $f3->get('tr');
		// https://github.com/thenetworg/oauth2-azure
		$teamsProvider = new \TheNetworg\OAuth2\Client\Provider\Azure([
		    'clientId'          => $f3->get('teams_client_id'),
		    'clientSecret'      => $f3->get('teams_client_secret'),
		    'redirectUri'		=> $f3->get('baseAppPath') . '/login/' . PROVIDER_TEAMS . '/' . $target,
		    'authWithResource' 	=> false,
		    'state'				=> $tr,
//		    'proxy'                   => 'localhost:8888',
//    		'verify'                  => false
		]);

		$teamsProvider->pathAuthorize = "/oauth2/v2.0/authorize";
		$teamsProvider->pathToken = "/oauth2/v2.0/token";
		$teamsProvider->scope = ["offline_access user.read Presence.Read"];

		return $teamsProvider;
	} 

	public static function getLoginUrl($target) {
		$f3=\Base::instance();

		$provider = self::getProvider($target);
		$loginUrl = $provider->getAuthorizationUrl(['state' => $f3->get('tr')]);
		$f3->set('SESSION.state', $provider->getState());
		return $loginUrl;
	} 

	public static function getToken($target) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		if ( !empty($f3->get('REQUEST.error')) ) {
			$l->error($tr . " - " . __METHOD__ . " - Error authenticating: " . $f3->get('REQUEST.error') . ", " . $f3->get('REQUEST.error_description'));
#			$f3->error(401, "Authentication error");
			$f3->reroute($f3->get('baseStaticPath') . '?error=' . urlencode('Authentication error'));
			return;
		}
		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseStaticPath'));
		} elseif ( !empty($f3->get('REQUEST.code')) ) {
			$authCode = $f3->get('REQUEST.code');

			// Check given state against previously stored one to mitigate CSRF attack
			if (empty($f3->get('REQUEST.state')) || ($f3->get('REQUEST.state') !== $f3->get('SESSION.state'))) {
		    	$l->error($tr . " - " . __METHOD__ . " - Invalid state: " . $f3->get('REQUEST.state') . " vs " . $f3->get('SESSION.state'));
				$f3->reroute($f3->get('baseStaticPath') . '?error=' . urlencode('Invalid state'));
				return;
			}

			$l->debug($tr . " - " . __METHOD__ . " - logged in");
		}


		try {
		    $token = self::getProvider($target)->getAccessToken('authorization_code', [
		        'code' => $authCode,
	//	        'resource' => 'https://graph.microsoft.com/',
		    ]);

#	   		$l->debug($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			return null;
		}

	    return $token;
		
	}

	public static function getPresenceStatus($target, $token) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$response = new \Response($tr);

		try {

			$provider = self::getProvider($target);
			$provider->urlAPI = 'https://graph.microsoft.com/beta/';
			$ref = 'me/presence';

			$providerResponse = $provider->request('get', $ref, $token, []);

#			$l->debug($tr . " - " . __METHOD__ . " - providerResponse: " . print_r($providerResponse, true));

		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$providerResponse = array(
				'exception' => $e->getMessage()
			);
		}

		if (array_key_exists('availability', $providerResponse)) {
			$newState = SESSION_STATE_ACTIVE;
			if (array_key_exists('activity', $providerResponse)) {
				$statusDetail = $providerResponse["availability"] . '/' . $providerResponse["activity"];
			}
			if (in_array($providerResponse["availability"], array('Available', 'AvailableIdle'))) {
				$status = STATUS_FREE;
			} elseif (in_array($providerResponse["availability"], array('Busy', 'BusyIdle', 'DoNotDisturb'))) {
				$status = STATUS_BUSY;
			} elseif (in_array($providerResponse["availability"], array('Away', 'BeRightBack'))) {
				$status = STATUS_AWAY;
			} elseif (in_array($providerResponse["availability"], array('Offline'))) {
				$status = STATUS_OFFLINE;
			} elseif (in_array($providerResponse["availability"], array('PresenceUnknown'))) {
				$status = STATUS_UNKNOWN;
			} else {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Unknown availability: " . $providerResponse["availability"]);
				$status = STATUS_ERROR;
			}
		} elseif (array_key_exists('exception', $providerResponse)) {
			$newState = SESSION_STATE_ERROR;
			$closedReason = $tr . ' - ' . $providerResponse["exception"];
			$status = STATUS_ERROR;
		} else {
			$newState = SESSION_STATE_INACTIVE;
			$closedReason = $tr . ' - ' . "Presence coudn't be retreived";
			$status = STATUS_ERROR;
		}

		$response->result->sessionState = $newState;
		$response->result->status = $status;
		$response->result->statusDetail = 'Teams: ' . $statusDetail;
		$response->result->closedReason = $closedReason;
		$response->success = true;

		return $response;

	}

}
?>
