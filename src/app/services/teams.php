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
#		$teamsProvider->scope = ["offline_access user.read Presence.Read.All"];

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
			$f3->reroute($f3->get('baseAppPath') . '?error=' . urlencode('Authentication error'));
			return;
		}
		if ( empty($f3->get('REQUEST.code')) ) {
			$f3->reroute($f3->get('baseAppPath'));
		} elseif ( !empty($f3->get('REQUEST.code')) ) {
			$authCode = $f3->get('REQUEST.code');

			// Check given state against previously stored one to mitigate CSRF attack
			if (empty($f3->get('REQUEST.state')) || ($f3->get('REQUEST.state') !== $f3->get('SESSION.state'))) {
		    	$l->error($tr . " - " . __METHOD__ . " - Invalid state: " . $f3->get('REQUEST.state') . " vs " . $f3->get('SESSION.state'));
				$f3->reroute($f3->get('baseAppPath') . '?error=' . urlencode('Invalid state'));
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
			
			$status = self::translateStatus($providerResponse["availability"]);
			
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

	public static function subscribeToPresenceChanges($target, $token, $providerUserId) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$l->debug($tr . " - " . __METHOD__ . " - START");

		$response = new \Response($tr);

		try {

			$provider = self::getProvider($target);
			$provider->urlAPI = 'https://graph.microsoft.com/beta/';

			#https://docs.microsoft.com/en-us/graph/api/subscription-post-subscriptions?view=graph-rest-beta&tabs=http
			#https://www.c-sharpcorner.com/blogs/get-notification-when-microsoft-teams-presence-changes-using-graph-api
			#https://gotoguy.blog/2020/07/12/subscribing-to-teams-presence-with-graph-api-using-power-platform/
			#https://stackoverflow.com/questions/64702174/subscribing-to-presence-in-ms-graph-api-for-multiple-users
			$ref = 'subscriptions';
			$body = new \stdClass();
			$body->changeType = 'updated';
			$body->notificationUrl = $f3->get('baseAppPath') . '/graph/notification';
			$body->resource = '/communications/presences/' . $providerUserId;
			$expirationTime = time() + 59 * 60;
			$body->expirationDateTime = date(DATE_RFC3339, $expirationTime);
			$body->clientState = $tr;

			$providerResponse = $provider->post($ref, json_encode($body), $token, []);
#			$providerResponse = $provider->delete("subscriptions/a8145707-3019-49a5-91fa-d7811437705c", $token);
			/*
			Array (
				[@odata.context] => https://graph.microsoft.com/beta/$metadata#subscriptions/$entity
				[id] => 20982c99-8e5e-4d57-bcbf-6f36ef71e572
				[resource] => /communications/presences/23460553-2421-4ac9-b5ef-37ffb4ca07d4
				[applicationId] => 824ac24a-e853-4d80-89df-e5de49b6f502
				[changeType] => updated
				[clientState] => secretClientValue
				[notificationUrl] => https://test.statuslight.online/graph/notification
				[notificationQueryOptions] =>      
				[notificationContentType] =>      
				[lifecycleNotificationUrl] =>      
				[expirationDateTime] => 2021-03-23T16:26:28Z     
				[creatorId] => 23460553-2421-4ac9-b5ef-37ffb4ca07d4     
				[includeResourceData] =>      
				[latestSupportedTlsVersion] => v1_2     
				[encryptionCertificate] =>      
				[encryptionCertificateId] =>  
			)
			*/
			
			$l->debug($tr . " - " . __METHOD__ . " - providerResponse: " . print_r($providerResponse, true));

		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$providerResponse = array(
				'exception' => $e->getMessage()
			);
			$response->result = $providerResponse;
			return $response;
		}
		if (!empty($providerResponse["id"])) {
			$response->success = true;
			$providerResponse["expirationTime"] = $expirationTime;
		} else {
			$l->error($tr . " - " . __METHOD__ . " - Subscription error - ");
		}

		$response->result = $providerResponse;
		return $response;

	}

	public static function renewSubscription($token, $subscriptionId) {
		$f3=\Base::instance();
		
		$tr = $f3->get('tr');
		$l = $f3->get('log');

		$l->debug($tr . " - " . __METHOD__ . " - START");

		$response = new \Response($tr);

		try {

			$provider = self::getProvider('device');
			$provider->urlAPI = 'https://graph.microsoft.com/beta/';

			$ref = 'subscriptions/' . $subscriptionId;
			$body = new \stdClass();
			$expirationTime = time() + 59 * 60;
			$body->expirationDateTime = date(DATE_RFC3339, $expirationTime);

			$providerResponse = $provider->patch($ref, json_encode($body), $token, []);

			/*
			{
			"id":"7f105c7d-2dc5-4530-97cd-4e7ae6534c07",
			"resource":"me/messages",
			"applicationId": "24d3b144-21ae-4080-943f-7067b395b913",
			"changeType":"created,updated",
			"clientState":"secretClientValue",
			"notificationUrl":"https://webhook.azurewebsites.net/api/send/myNotifyClient",
			"lifecycleNotificationUrl":"https://webhook.azurewebsites.net/api/send/lifecycleNotifications",
			"expirationDateTime":"2016-11-22T18:23:45.9356913Z",
			"creatorId": "8ee44408-0679-472c-bc2a-692812af3437",
			"latestSupportedTlsVersion": "v1_2",
			"encryptionCertificate": "",
			"encryptionCertificateId": "",
			"includeResourceData": false,
			"notificationContentType": "application/json"
			}
			*/
			
			$l->debug($tr . " - " . __METHOD__ . " - providerResponse: " . print_r($providerResponse, true));

		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			$l->error($tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			$l->error($tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			$providerResponse = array(
				'exception' => $e->getMessage()
			);
			$response->result = $providerResponse;
			return $response;
		}
		if (!empty($providerResponse["id"])) {
			$response->success = true;
			$providerResponse["expirationTime"] = $expirationTime;
		} else {
			$l->error($tr . " - " . __METHOD__ . " - Subscription error - ");
		}

		$response->result = $providerResponse;
		return $response;

	}

	public static function translateStatus($status) {
		if (in_array($status, array('Available', 'AvailableIdle'))) {
			return STATUS_FREE;
		} elseif (in_array($status, array('Busy', 'BusyIdle', 'DoNotDisturb'))) {
			return STATUS_BUSY;
		} elseif (in_array($status, array('Away', 'BeRightBack'))) {
			return STATUS_AWAY;
		} elseif (in_array($status, array('Offline'))) {
			return STATUS_OFFLINE;
		} elseif (in_array($status, array('PresenceUnknown'))) {
			return STATUS_UNKNOWN;
		} else {
			return STATUS_UNKNOWN;
		}
	}

}
?>
