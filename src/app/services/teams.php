<?php

namespace Services;

class Teams extends \Services\ServiceBase {


	function __construct() {
		parent::__construct();
	}

	public static function getProvider($redirectUri) {
		$f3=\Base::instance();
		// https://github.com/thenetworg/oauth2-azure
		$teamsProvider = new \TheNetworg\OAuth2\Client\Provider\Azure([
		    'clientId'          => $f3->get('teams_client_id'),
		    'clientSecret'      => $f3->get('teams_client_secret'),
		    'redirectUri'		=> $f3->get('baseAppPath') . $redirectUri,
		    'authWithResource' 	=> false,
//		    'proxy'                   => 'localhost:8888',
//    		'verify'                  => false
		]);

		$teamsProvider->pathAuthorize = "/oauth2/v2.0/authorize";
		$teamsProvider->pathToken = "/oauth2/v2.0/token";
		$teamsProvider->scope = ["offline_access user.read Presence.Read"];

		return $teamsProvider;
	} 

	public static function getLoginUrl($loginType) {

		if ($loginType == 'phone') {
			return self::getProvider('/teams/login')->getAuthorizationUrl();
		} elseif ($loginType == 'device') {
			return self::getProvider('/device/login/teams')->getAuthorizationUrl();
		} else {
			return null;
		}
	} 

	public static function getTokens($redirectUri) {
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
			$l->debug($tr . " - " . __METHOD__ . " - logged in");
		}

		// Check given state against previously stored one to mitigate CSRF attack
#		} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
#		    unset($_SESSION['oauth2state']);
#		    exit('Invalid state');
#		}

		try {
		    $token = self::getProvider($redirectUri)->getAccessToken('authorization_code', [
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

	public static function getPresenceStatus($redirectUri, $token) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$response = new \Response($tr);

		try {

			$provider = self::getProvider($redirectUri);
			$provider->urlAPI = 'https://graph.microsoft.com/beta/';
			$ref = 'me/presence';

			$providerResponse = $provider->request('get', $ref, $token, []);

			$l->debug($tr . " - " . __METHOD__ . " - providerResponse: " . print_r($providerResponse, true));

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
				$statusDetail = $providerResponse["availability"] . ':' . $providerResponse["activity"];
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
		$response->result->statusDetail = $statusDetail;
		$response->result->closedReason = $closedReason;
		$response->success = true;

		return $response;

	}

	function login() {
		$f3=\Base::instance();
		$token = self::getTokens('/teams/login');
		
		if (!empty($token)) {
			$f3->set('SESSION.accessToken', $token->getToken());
			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());
		}

		$f3->reroute('/teams');
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

/*
			$this->graph = new \GraphAPI($f3->get('scope'), $f3->get('redirectUriTeams'), $this->tr, $this->l);
			$this->graph->setAuthParams($f3->get('client_id'), $f3->get('client_secret'), $authCode);
			$this->graph->setTokens($f3->get('SESSION.accessToken'), $f3->get('SESSION.refreshToken'));

			$tokenResponse = $this->graph->getToken('refresh_token');

			if (!$tokenResponse->success) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Error geting new token: " . $authResponse->message); 
				$f3->error(401, "Error authenticating: " . $authResponse->message);
			}
*/

			$token = self::getProvider('/teams/login')->getAccessToken('refresh_token', [
	                	'refresh_token' => $f3->get('SESSION.refreshToken'),
	            	]);

			$f3->set('SESSION.accessToken', $token->getToken());
			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

		}
		$this->l->debug($this->tr . " - " . __METHOD__ . " - Using current token");

		$response->result->accessToken = $f3->get('SESSION.accessToken');
		$response->result->accessTokenExpiresOn = $f3->get('SESSION.accessTokenExpiresOn');
		$response->success = true;
		$f3->set('data', $response);
	} 

	function afterroute($f3) {
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('status_teams.html');
		}

	}

}
?>
