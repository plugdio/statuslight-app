<?php

namespace Services;

class Slack extends \Services\ServiceBase {

	function __construct() {
		parent::__construct();
	}


	public static function getProvider($redirectUri) {
		$f3=\Base::instance();

		//https://github.com/adam-paterson/oauth2-slack
		$slackProvider = new \AdamPaterson\OAuth2\Client\Provider\Slack([
		    'clientId'          => $f3->get('slack_client_id'),
		    'clientSecret'      => $f3->get('slack_client_secret'),
		    'redirectUri'       => $f3->get('baseAppPath') . $redirectUri,
		]);

		return $slackProvider;

	} 

	public static function getLoginUrl($loginType) {

		if ($loginType == 'phone') {
			return self::getProvider('/slack/login')->getAuthorizationUrl([
				    'scope' => 'users:read'
				]);
		} elseif ($loginType == 'device') {
				return self::getProvider('/device/login/slack')->getAuthorizationUrl([
			    	'scope' => 'users:read'
				]);
		} else {
			return null;
		}
	} 

	public static function getTokens($redirectUri) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$token = null;

		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseStaticPath'));
 		// Check given state against previously stored one to mitigate CSRF attack
#		} elseif (empty($f3->get('REQUEST.state')) || ($f3->get('REQUEST.state') !== $f3->get('SESSION.oauth2state'))) {
# 			$f3->set('SESSION.oauth2state', null);		
# 			$f3->reroute($f3->get('baseStaticPath') . '?error=' . urlencode('Invalid state'));
		} else {
			try {
				$token = self::getProvider($redirectUri)->getAccessToken('authorization_code', [
	        		'code' => $f3->get('REQUEST.code')
	    		]);
			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$l->error($tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			}
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

$l->debug($tr . " - " . __METHOD__ . " - request: " . json_encode($request));

				$res = $client->request('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', [
    				'headers' => [
			     		'content-type'     => 'application/json',
			        	'authorization'  => "Bearer " . $token->getToken(),
			    	],
    				'body' => json_encode($request),
    				'http_errors' => false
				]);
				#{  "kind": "calendar#freeBusy",  "timeMin": "2020-05-15T23:15:30.000Z",  "timeMax": "2020-05-15T23:16:30.000Z",  "calendars": {   "x@gmail.com": {    "busy": []   }  } }
				$l->debug($tr . " - " . __METHOD__ . " - body: " . $res->getBody());

				$providerResponse = json_decode($res->getBody());
				if (!empty($providerResponse->error)) {
					$newState = SESSION_STATE_ERROR;
					$closedReason = $providerResponse->message;
					$status = STATUS_ERROR;
					$subStatus = STATUS_ERROR;
				} else {
					$newState = SESSION_STATE_ACTIVE;
					$closedReason = null;
					if (count($providerResponse->calendars->{$primaryCalendareId}->busy) > 1) {
						$status = STATUS_BUSY;
						$subStatus = STATUS_BUSY;
					} elseif (count($providerResponse->calendars->{$primaryCalendareId}->busy) == 0) {
						$status = STATUS_FREE;
						$subStatus = STATUS_FREE;
					}
				}

			} else {
				$newState = SESSION_STATE_ERROR;
				$closedReason = 'error getting calendarList';
				$status = STATUS_ERROR;
				$subStatus = STATUS_ERROR;
			}

		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception1 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$newState = SESSION_STATE_ERROR;
			$closedReason = $e->getMessage();
			$status = STATUS_ERROR;
			$subStatus = STATUS_ERROR;
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception2 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$newState = SESSION_STATE_ERROR;
			$closedReason = $e->getMessage();
			$status = STATUS_ERROR;
			$subStatus = STATUS_ERROR;
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
		$token = self::getTokens('/slack/login');

		$f3->set('SESSION.accessToken', $token->getToken());
		$userId = $this->slackProvider->getAuthorizedUser($token)->getId();
		$f3->set('SESSION.user_id', $userId);

		$this->l->debug($this->tr . " - " . __METHOD__ . " - userId: " . print_r($userId, true));

/*		
		if (!empty($token)) {
			$f3->set('SESSION.accessToken', $token->getToken());
			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());
		}
*/


		$f3->reroute('/slack');
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
			$token = self::getProvider('/slack/login')->getAccessToken($grant, ['refresh_token' => $f3->get('SESSION.refreshToken')]);

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
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('status_slack.html');
		}

	}

}
?>
