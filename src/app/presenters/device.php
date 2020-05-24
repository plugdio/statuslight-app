<?php

namespace Presenters;

class Device {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}


	function loginWithTeams($f3, $args) {
		
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$token = \Services\Teams::getTokens('/device/login/teams');
		if (!empty($token)) {
			try {
				$f3->set('SESSION.accessToken', $token->getToken());
				$f3->set('SESSION.refreshToken', $token->getRefreshToken());
				$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

				$provider = \Services\Teams::getProvider('/device/login/teams');
				$provider->urlAPI = 'https://graph.microsoft.com/beta/';
				$me = $provider->get("me", $token);
#				$this->l->debug($this->tr . " - " . __METHOD__ . " - me: " . print_r($me, true));

				$teamsUserId = $me['id'];
				$name = $me['displayName'];
				$email = $me['mail'];

				$userModel = new \Models\User();
				$userResult = $userModel->saveUser($teamsUserId, PROVIDER_AZURE, $name, $email);

				if ($userResult->success) {
					$userId = $userResult->result['id'];
					$f3->set('SESSION.userId', $userId);
					$f3->set('SESSION.name', $name);
				}

				$sessionModel = new \Models\Session();
				$sessionModel->saveSession(PROVIDER_AZURE, $userId, $token);
			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$f3->set('SESSION.userId', null);
				$f3->set('SESSION.name', null);
				$f3->set('SESSION.accessToken', null);
				$f3->set('SESSION.refreshToken', null);
				$f3->set('SESSION.accessTokenExpiresOn', null);
			}
		}

		$f3->reroute('/device');
	}

	function loginWithGoogle($f3, $args) {
		
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$token = \Services\GCal::getTokens('/device/login/gcal');
		if (!empty($token)) {
			try {
				$f3->set('SESSION.accessToken', $token->getToken());
				$f3->set('SESSION.refreshToken', $token->getRefreshToken());
				$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

				$provider = \Services\GCal::getProvider('/device/login/gcal');

		        // We got an access token, let's now get the owner details
        		$ownerDetails = $provider->getResourceOwner($token);

#				$this->l->debug($this->tr . " - " . __METHOD__ . " - ownerDetails: " . print_r($ownerDetails, true));

				$googleUserId = $ownerDetails->getId();
				$name = $ownerDetails->getName();
				$email = $ownerDetails->getEmail();

				$userModel = new \Models\User();
				$userResult = $userModel->saveUser($googleUserId, PROVIDER_GOOGLE, $name, $email);

				if ($userResult->success) {
					$userId = $userResult->result['id'];
					$f3->set('SESSION.userId', $userResult->result['id']);
					$f3->set('SESSION.name', $name);
				}

				$sessionModel = new \Models\Session();
				$sessionModel->saveSession(PROVIDER_GOOGLE, $userId, $token);
			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$f3->set('SESSION.userId', null);
				$f3->set('SESSION.name', null);
				$f3->set('SESSION.accessToken', null);
				$f3->set('SESSION.refreshToken', null);
				$f3->set('SESSION.accessTokenExpiresOn', null);
			}
		}

		$f3->reroute('/device');
	}

	function loginWithSlack($f3, $args) {
		
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$token = \Services\Slack::getTokens('/device/login/slack');
		if (!empty($token)) {
			try {
				$f3->set('SESSION.accessToken', $token->getToken());
				$f3->set('SESSION.refreshToken', $token->getRefreshToken());
				$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

				$provider = \Services\Slack::getProvider('/device/login/slack');

        		$slackUserId = $provider->getAuthorizedUser($token)->getId();
        		$team = $provider->getResourceOwner($token);
				
#				$this->l->debug($this->tr . " - " . __METHOD__ . " - userId: " . print_r($userId, true));
#				$this->l->debug($this->tr . " - " . __METHOD__ . " - team: " . print_r($team, true));

				$name = $team->getRealName();
				$email = $team->getEmail();

				$userModel = new \Models\User();
				$userResult = $userModel->saveUser($slackUserId, PROVIDER_SLACK, $name, $email);

				if ($userResult->success) {
					$userId = $userResult->result['id'];
					$f3->set('SESSION.userId', $userResult->result['id']);
					$f3->set('SESSION.name', $name);
				}

				$f3->set('SESSION.userId', $userId);
				$f3->set('SESSION.name', $name);

				$sessionModel = new \Models\Session();
				$sessionModel->saveSession(PROVIDER_SLACK, $userId, $token);

			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$f3->set('SESSION.userId', null);
				$f3->set('SESSION.name', null);
				$f3->set('SESSION.accessToken', null);
				$f3->set('SESSION.refreshToken', null);
				$f3->set('SESSION.accessTokenExpiresOn', null);
			}
		}

		$f3->reroute('/device');
	}


	function main($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $f3->get('SESSION.userId'));

		$this->amIAuthenticated();

		$userId = $f3->get('SESSION.userId');

		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->getActiveSessionForUser($userId);

		if (!$sessionResponse->success) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no session - " . $sessionResponse->message);
			$f3->reroute($f3->get('baseStaticPath'));
		}

		$mySession = $sessionResponse->result;

		$f3->set('service', 'UNKNOWN PROVIDER');

		if ($mySession['type'] == PROVIDER_AZURE) {
			$f3->set('service', 'Teams');
			$f3->set('status', $mySession['presenceStatus']);
		} elseif ($mySession['type'] == PROVIDER_GOOGLE) {
			$f3->set('service', 'Google Calendar');
			$f3->set('status', $mySession['presenceStatus']);
		} elseif ($mySession['type'] == PROVIDER_SLACK) {
			$f3->set('service', 'Slack');
			$f3->set('status', $mySession['presenceStatus']);
		}

		$deviceModel = new \Models\Device();
		$deviceResponse = $deviceModel->getDeviceByUserId($userId);

		if (!$deviceResponse->success) {
			$f3->set('no_devices_yet', true);
		} else {
			$f3->set('no_devices_yet', false);
		}

		$myActiveDevices = array();
		$myPendingDevices = array();

		if ($deviceResponse->success && count($deviceResponse->result) > 0) {
			foreach ($deviceResponse->result as $device) {
				$myDevice = array();
				if ($device["state"] == DEVICE_STATE_TEMP) {
					$myDevice["id"] = '-';
					$myDevice["state"] = 'Pending activation';
					$myDevice["validity"] = $device['validity'];
					$myDevice["pin"] = $device['pin'];
					$myPendingDevices[] = $myDevice;
				} elseif ($device["state"] == DEVICE_STATE_ACTIVE) {
					$myDevice["id"] = $device['clientId'];
					$clientResponse = $deviceModel->getDeviceByClientId($device["clientId"]);
					if ($clientResponse->success) {
						$myClient = $clientResponse->result;
						$myDevice["clientState"] = $myClient["state"];
						$myDevice["lastSeen"] = date('Y-m-d H:i:s', $myClient["updated"]);
						$config = json_decode($myClient['implementation/config']);
						if (($config != null) && !empty($config->wifi->ssid)) {
							$myDevice["network"] = $config->wifi->ssid;
						}
						if (!empty($myClient["statuslight/color"])) {
							$myDevice["color"] = $myClient["statuslight/color"];
						} else {
							$myDevice["color"] = 'white';
						}
					} else {
						$myDevice["state"] = '-';
						$myDevice["updated"] = '-';
					}
					$myActiveDevices[] = $myDevice;
				}
			}
		}

		$f3->set('active_devices', $myActiveDevices);
		$f3->set('pending_devices', $myPendingDevices);

	}

	function addDevice($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$this->amIAuthenticated();

		$userId = $f3->get('SESSION.userId');

		$deviceModel = new \Models\Device();

		$pin = mt_rand(100000, 999999);
		while (!$deviceModel->isPinUnique($pin)) {
			$pin = mt_rand(100000, 999999);
		}

		$deviceResponse = $deviceModel->addTempDevice($userId, $pin);

		if (!$deviceResponse->success) {
			$f3->set('SESSION.error_text', $response->message);
		} else {
			$f3->set('SESSION.info_text', "Device has been added");
		}

		$f3->reroute('/device');

	}


	function amIAuthenticated($ajax = false) {
		$f3=\Base::instance();

		if ( empty($f3->get('SESSION.accessToken')) ) {
#		if ( empty($f3->get('SESSION.accessToken')) || empty($f3->get('SESSION.refreshToken')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no tokens - " . print_r($f3->get('SESSION'), true));
			$f3->reroute($f3->get('baseStaticPath'));
		}
		return true;
	}


	function afterroute($f3) {
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		echo \Template::instance()->render('device.html');

	}

}
?>
