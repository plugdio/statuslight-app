<?php

namespace Services;

class GCal {

	function __construct() {
		parent::__construct();
	}


	public static function getProvider($target) {
		$f3=\Base::instance();

		// login link: https://developers.google.com/identity/protocols/oauth2/web-server
		// https://github.com/thephpleague/oauth2-client
		// https://github.com/thephpleague/oauth2-google
		$gcalProvider = new \League\OAuth2\Client\Provider\Google([
		    'clientId'     => $f3->get('gcal_client_id'),
		    'clientSecret' => $f3->get('gcal_client_secret'),
		    'redirectUri'  => $f3->get('baseAppPath') . '/login/' . PROVIDER_GOOGLE . '/' . $target,
		    'accessType'   => 'offline',
		    'prompt'       => 'consent',
//		    'proxy'                   => 'localhost:8888',
//    		'verify'                  => false
		]);

		return $gcalProvider;

	} 

	public static function getLoginUrl($target) {
		$f3=\Base::instance();
		
		$provider = self::getProvider($target);
		$loginUrl = $provider->getAuthorizationUrl([
				    'scope' => [
				        'https://www.googleapis.com/auth/calendar.readonly'
				    ],
				    'state' => $f3->get('tr')
				]);
		$f3->set('SESSION.state', $provider->getState());
		return $loginUrl;

	} 

	public static function getToken($target) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');


		#http://localhost:8000/gcal/login?state=094e7b&code=4/yQHVmBpfPA8eyklVB-PK8rdFCV7i1K9Uv88W00pT3CWP_1dWgqRrJlNF4eisSA6zvAeaG1EwlqKfSN6TPIUc2oQ&scope=https://www.googleapis.com/auth/calendar.readonly

		if ( !empty($f3->get('REQUEST.error')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Error authenticating: " . $f3->get('REQUEST.error'));
#			$f3->error(401, "Authentication error");
			$f3->reroute($f3->get('baseAppPath') . '?error=' . urlencode('Authentication error'));
			return;
		}

		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseAppPath'));
		} elseif ( empty($f3->get('REQUEST.state')) || ($f3->get('REQUEST.state') !== $f3->get('SESSION.state')) ) {
			// State is invalid, possible CSRF attack in progress
    		$l->error($tr . " - " . __METHOD__ . " - Invalid state: " . $f3->get('REQUEST.state') . " vs " . $f3->get('SESSION.state'));
			$f3->reroute($f3->get('baseAppPath') . '?error=' . urlencode('Invalid state'));
			return;
		} elseif ( !empty($f3->get('REQUEST.code')) ) {
			$authCode = $f3->get('REQUEST.code');
			$l->debug($tr . " - " . __METHOD__ . " - logged in");
		}

		try {
		    $token = self::getProvider($target)->getAccessToken('authorization_code', [
		        'code' => $authCode,
		    ]);
		    $refreshToken = $token->getRefreshToken();

	   		$l->debug($tr . " - " . __METHOD__ . " - refreshToken: " . print_r($refreshToken, true));
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

#			$provider = self::getProvider($redirectUri);
			$client = new \GuzzleHttp\Client();
			$res = $client->request('GET', 'https://www.googleapis.com/calendar/v3/users/me/calendarList?minAccessRole=freeBusyReader', [
			    'headers' => [
			        'content-type'     => 'application/json',
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
				$request->timeMin = gmdate("Y-m-d\TH:i:s") . '.000Z';
				$request->timeMax = gmdate("Y-m-d\TH:i:s", time() + 1 * 60) . '.000Z';
				$request->items = [
					(object) ['id' => $primaryCalendareId]
				];


				$res = $client->request('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', [
    				'headers' => [
			     		'content-type'     => 'application/json',
			        	'authorization'  => "Bearer " . $token->getToken(),
			    	],
    				'body' => json_encode($request),
    				'http_errors' => false
				]);
				#{  "kind": "calendar#freeBusy",  "timeMin": "2020-05-15T23:15:30.000Z",  "timeMax": "2020-05-15T23:16:30.000Z",  "calendars": {   "x@gmail.com": {    "busy": []   }  } }
#				$l->debug($tr . " - " . __METHOD__ . " - body: " . $res->getBody());

				$providerResponse = json_decode($res->getBody());
				if (!empty($providerResponse->error)) {
					$newState = SESSION_STATE_ERROR;
					$closedReason = $tr . ' - ' . $providerResponse->message;
					$status = STATUS_ERROR;
					$statusDetail = STATUS_ERROR;
				} else {
					$newState = SESSION_STATE_ACTIVE;
					$closedReason = null;
					if (count($providerResponse->calendars->{$primaryCalendareId}->busy) > 0) {
						$status = STATUS_BUSY;
						$statusDetail = STATUS_BUSY;
					} elseif (count($providerResponse->calendars->{$primaryCalendareId}->busy) == 0) {
						$status = STATUS_FREE;
						$statusDetail = STATUS_FREE;
					}
				}

			} else {
				$newState = SESSION_STATE_ERROR;
				$closedReason = $tr . ' - ' . 'error getting calendarList';
				$status = STATUS_ERROR;
				$statusDetail = STATUS_ERROR;
			}

		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception1 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$newState = SESSION_STATE_ERROR;
			$closedReason = $tr . ' - ' . $e->getMessage();
			$status = STATUS_ERROR;
			$statusDetail = STATUS_ERROR;
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception2 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$newState = SESSION_STATE_ERROR;
			$closedReason = $tr . ' - ' . $e->getMessage();
			$status = STATUS_ERROR;
			$statusDetail = STATUS_ERROR;
		}

		$response->result->sessionState = $newState;
		$response->result->status = $status;
		$response->result->statusDetail = 'Google: ' . $statusDetail;
		$response->result->closedReason = $closedReason;
		$response->success = true;

		return $response;

	}

}
?>
