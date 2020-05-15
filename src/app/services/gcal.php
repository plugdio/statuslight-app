<?php

namespace Services;

use League\OAuth2\Client\Grant\RefreshToken;

class GCal extends \Services\ServiceBase {

	function __construct() {
		parent::__construct();
	}


	public static function getProvider($redirectUri) {
		$f3=\Base::instance();

		// login link: https://developers.google.com/identity/protocols/oauth2/web-server
		// https://github.com/thephpleague/oauth2-client
		$gcalProvider = new \League\OAuth2\Client\Provider\Google([
		    'clientId'     => $f3->get('gcal_client_id'),
		    'clientSecret' => $f3->get('gcal_client_secret'),
		    'redirectUri'  => $f3->get('baseAppPath') . $redirectUri,
		    'accessType'   => 'offline',
		    'prompt'       => 'consent'
//		    'proxy'                   => 'localhost:8888',
//    		'verify'                  => false
		]);

		return $gcalProvider;

	} 

	public static function getLoginUrl($loginType) {

		if ($loginType == 'phone') {
			return self::getProvider('/gcal/login')->getAuthorizationUrl([
				    'scope' => [
				        'https://www.googleapis.com/auth/calendar.readonly'
				    ],
				]);
		} elseif ($loginType == 'device') {
				return self::getProvider('/device/login/gcal')->getAuthorizationUrl([
			    	'scope' => [
			        	'https://www.googleapis.com/auth/calendar.readonly'
			    	],
				]);
		} else {
			return null;
		}
	} 

	public static function getTokens($redirectUri) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');


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
			$l->debug($tr . " - " . __METHOD__ . " - logged in");
		}

		try {
		    $token = self::getProvider($redirectUri)->getAccessToken('authorization_code', [
		        'code' => $authCode,
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

#			$provider = self::getProvider($redirectUri);
			$client = new \GuzzleHttp\Client();
			$res = $client->request('GET', 'https://www.googleapis.com/calendar/v3/users/me/calendarList?minAccessRole=freeBusyReader', [
			    'headers' => [
			        'Accept'     => 'application/json',
			        'authorization'  => "Bearer " . $token->getToken(),
			    ]
			]);

#			$l->debug($tr . " - " . __METHOD__ . " - body: " . $res->getBody());

			if ($res->getStatusCode() == 200) {
				$calendarList = json_decode($res->getBody() . '');

				$primaryCalendareId = null;
				foreach ($calendarList->items as $calendar) {
					if ($calendar->primary) {
						$primaryCalendareId = $calendar->id;
						break;
					}
				}

				$startTime = date(DATE_RFC3339);
				$endTime = date(DATE_RFC3339, time() + 1 * 60);

				$request = new \stdClass();
				$request->timeMin = date(DATE_RFC3339);
				$request->timeMax = date(DATE_RFC3339, time() + 1 * 60);
				$request->items = [
					"item" => (object) ['id' => primaryCalendareId]
				];


			} else {

			}

$newState = SESSION_STATE_ACTIVE;
$status = STATUS_FREE;
$subStatus = STATUS_FREE;
$closedReason = null;

		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$providerResponse = array(
				'exception' => $e->getMessage()
			);
		}

		$response->result->sessionState = $newState;
		$response->result->status = $status;
		$response->result->subStatus = $subStatus;
		$response->result->closedReason = $closedReason;
		$response->success = true;

		return $response;

	}

	function login() {
		$f3=\Base::instance();
		$token = self::getTokens('/gcal/login');
		
		if (!empty($token)) {
			$f3->set('SESSION.accessToken', $token->getToken());
			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());
		}

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
			$token = self::getProvider('/gcal/login')->getAccessToken($grant, ['refresh_token' => $f3->get('SESSION.refreshToken')]);

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
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('status_gcal.html');
		}

	}

}
?>
