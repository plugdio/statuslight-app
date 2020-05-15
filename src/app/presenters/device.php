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

				$userId = $me['id'];
				$provider = PROVIDER_AZURE;
				$name = $me['displayName'];
				$email = $me['mail'];

				$userModel = new \Models\User();
				$userModel->saveUser($userId, $provider, $name, $email);

				$f3->set('SESSION.userId', $me['id']);
				$f3->set('SESSION.name', $me['displayName']);

				$sessionModel = new \Models\Session();
				$sessionModel->saveSession(PROVIDER_AZURE, $userId, $token);
			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			}
		}

		$f3->reroute('/device');
	}

	function main($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$this->amIAuthenticated();

		$userId = $f3->get('SESSION.userId');

		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->getActiveSessionForUser($userId);

		if (!$sessionResponse->success) {
			//TODO
		}

		$mySession = $sessionResponse->result;

		$f3->set('service', 'UNKNOWN PROVIDER');

		if ($mySession['type'] == PROVIDER_AZURE) {
			$f3->set('service', 'Teams');
			$f3->set('status', $mySession['status'] . ' (' . $mySession['subStatus'] . ')');
		} elseif ($mySession['type'] == PROVIDER_GOOGLE) {
			$f3->set('service', 'Google Calendar');
			$f3->set('status', $mySession['status']);
		} elseif ($mySession['type'] == PROVIDER_SLACK) {
			$f3->set('service', 'Slack');
			$f3->set('status', $mySession['status']);
		}

		$deviceModel = new \Models\Device();
		$mqttClientModel = new \Models\MqttClient();
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
					$myDevice["validity"] = date('Y-m-d H:i:s', $device['validity']);
					$myDevice["pin"] = $device['pin'];
					$myPendingDevices[] = $myDevice;
				} elseif ($device["state"] == DEVICE_STATE_ACTIVE) {
					$myDevice["id"] = $device['clientId'];
					$clientResponse = $mqttClientModel->getClientById($device["clientId"]);
					if ($clientResponse->success) {
						$myDevice["clientState"] = $clientResponse->result["state"];
						$myDevice["lastSeen"] = date('Y-m-d H:i:s', $clientResponse->result["updated"]);
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
		$deviceResponse = $deviceModel->getDeviceByUserId($userId);

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
