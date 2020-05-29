<?php

namespace Services;

class Slack {

	function __construct() {
		parent::__construct();
	}


	public static function getProvider($target) {
		$f3=\Base::instance();

		//https://github.com/adam-paterson/oauth2-slack
		$slackProvider = new \AdamPaterson\OAuth2\Client\Provider\Slack([
		    'clientId'          => $f3->get('slack_client_id'),
		    'clientSecret'      => $f3->get('slack_client_secret'),
		    'redirectUri'       => $f3->get('baseAppPath') . '/login/' . PROVIDER_TEAMS . '/' . $target,
		]);

		return $slackProvider;

	} 

	public static function getLoginUrl($target) {
		$f3=\Base::instance();
		
		$provider = self::getProvider($target);

		$loginUrl = $provider->getAuthorizationUrl([
				    'scope' => 'users:read',
				    'state' => $f3->get('tr')
				]);
		$f3->set('SESSION.state', $provider->getState());
		return $loginUrl;
	} 

	public static function getToken($target) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$token = null;

		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseStaticPath'));
 		// Check given state against previously stored one to mitigate CSRF attack
		} elseif (empty($f3->get('REQUEST.state')) || ($f3->get('REQUEST.state') !== $f3->get('SESSION.state'))) {
		    $l->error($tr . " - " . __METHOD__ . " - Invalid state: " . $f3->get('REQUEST.state') . " vs " . $f3->get('SESSION.state'));
			$f3->reroute($f3->get('baseStaticPath') . '?error=' . urlencode('Invalid state'));
			return;
		} else {

			try {
				$token = self::getProvider($target)->getAccessToken('authorization_code', [
	        		'code' => $f3->get('REQUEST.code')
	    		]);
			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$l->error($tr . " - " . __METHOD__ . " - Caught exception1 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			} catch (\InvalidArgumentException $e) {
				$l->error($tr . " - " . __METHOD__ . " - Caught exception2 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$l->error($tr . " - " . __METHOD__ . " - code " . $f3->get('REQUEST.code'));
			} catch (\RuntimeException $e) {
				$l->error($tr . " - " . __METHOD__ . " - Caught exception3 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$l->error($tr . " - " . __METHOD__ . " - code " . $f3->get('REQUEST.code'));
			}
		}
	    
	    return $token;
		
	}

	public static function getPresenceStatus($target, $token, $userId) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$response = new \Response($tr);

		try {

			$client = new \GuzzleHttp\Client();
			$res = $client->request('POST', 'https://slack.com/api/users.info', [
    				'headers' => [
			     		'content-type'     => 'application/x-www-form-urlencoded'
			    	],
    				'body' => 'token=' . $token . '&user=' . $userId,
    				'http_errors' => false
				]);

#			$l->debug($tr . " - " . __METHOD__ . " - body: " . $res->getBody());

			$providerResponse = json_decode($res->getBody());
			if (empty($providerResponse) || !$providerResponse->ok) {
				$newState = SESSION_STATE_ERROR;
				$closedReason = $tr . ' - ' . $providerResponse->error;
				$status = STATUS_ERROR;
				$statusDetail = STATUS_ERROR;
			} else {
				$newState = SESSION_STATE_ACTIVE;
				$closedReason = null;
				if ($providerResponse->user->profile->status_text == '') {
					$status = STATUS_FREE;
					$statusDetail = STATUS_FREE;
				} elseif ($providerResponse->user->profile->status_text == 'In a meeting') {
					$status = STATUS_BUSY;
					$statusDetail = 'In a meeting';
				} else {
					$status = STATUS_FREE;
					$statusDetail = $providerResponse->user->profile->status_text;
				}
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
		$response->result->statusDetail = 'Slack: ' . $statusDetail;
		$response->result->closedReason = $closedReason;
		$response->success = true;

		return $response;

	}

}
?>
