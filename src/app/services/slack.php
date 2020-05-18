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

	public static function getPresenceStatus($redirectUri, $token, $userId) {
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
		$response->result->statusDetail = $statusDetail;
		$response->result->closedReason = $closedReason;
		$response->success = true;

		return $response;

	}

	function login() {
		$f3=\Base::instance();
		$token = self::getTokens('/slack/login');

		if (empty($token)) {
			$f3->reroute($f3->get('baseStaticPath'));
		}

		$f3->set('SESSION.accessToken', $token->getToken());
		$provider = self::getProvider('/slack/login');
		$userId = $provider->getAuthorizedUser($token)->getId();
		$f3->set('SESSION.user_id', $userId);

		$this->l->debug($this->tr . " - " . __METHOD__ . " - userId: " . print_r($userId, true));

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
