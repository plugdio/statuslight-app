<?php

namespace Presenters;

class Device {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}

	function main($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $f3->get('SESSION.userId'));

		$this->amIAuthenticated();

		$userId = $f3->get('SESSION.userId');

		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->getActiveSessionForUser($userId);

		if (!$sessionResponse->success) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no session - " . $sessionResponse->message);
			$f3->reroute($f3->get('baseAppPath'));
		}

		$mySession = $sessionResponse->result;

		$f3->set('service', 'UNKNOWN PROVIDER');

		if ($mySession['type'] == PROVIDER_TEAMS) {
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
				$myDevice["deviceId"] = $device['id'];
				if ($device["state"] == DEVICE_STATE_TEMP) {
					$myDevice["id"] = '-';
					$myDevice["state"] = 'Pending activation';
					$myDevice["validity"] = $device['validity'];
					$myDevice["pin"] = $device['pin'];
					$myPendingDevices[] = $myDevice;
				} elseif ($device["state"] == DEVICE_STATE_ACTIVE) {
					$myDevice["id"] = $device['mqttClientId'];
					$myDevice["lastSeen"] = $device["mqttUpdated"];
					$clientDetails = json_decode($device['clientDetails']);
					if (is_object($clientDetails)) {
						if (!empty($clientDetails->state)) {
							$myDevice["type"] = 'homie';
							$myDevice["clientState"] = $clientDetails->state;
							if ($clientDetails->state == 'ready') {
								if (!empty($clientDetails->{'statuslight/color'})) {
									$myDevice["color"] = $clientDetails->{'statuslight/color'};
								} else {
									$myDevice["color"] = 'white';
								}
							} else {
								$myDevice["color"] = 'grey';
							}
						} else {
							$myDevice["type"] = 'non-homie';
							$myDevice["clientState"] = '-';
							$myDevice["color"] = 'white';
						}
						$config = json_decode($clientDetails->{'implementation/config'});
						if (($config != null) && !empty($config->wifi->ssid)) {
							$myDevice["network"] = $config->wifi->ssid;
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

		$f3->reroute('/device/status');

	}

	function deleteDevice($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		$deviceId = $f3->get('PARAMS.deviceId');

		$this->amIAuthenticated();

		$deviceModel = new \Models\Device();
		$deviceModel->deleteDevice($deviceId);

		$f3->reroute('/device/status');

	}

	function updateStatus($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$response = new \Response($this->tr);

		$f3->set('popup', true);
		$f3->set('SESSION.popup', true);

		$this->amIAuthenticated();

		$userId = $f3->get('SESSION.userId');
		$status = $args["status"];
		$period = $args["period"];

		//TODO: input validation

		if (empty($userId) || empty($status) || empty($period)) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - wrong input", $f3->get('POST'));
			$response->message = 'wrong input';
			header('Content-Type: application/json');
			echo json_encode($response, JSON_PRETTY_PRINT);
			return;
		}

		$sessionModel = new \Models\Session();
			
		if ($period == -1) {
			# reset the manual status
			$sessionResponse = $sessionModel->deleteSessionsForUser($userId, true);
			$sessionResponse = $sessionModel->getActiveSessionForUser($userId);
			if ($sessionResponse->success) {
				$status = $sessionResponse->result['presenceStatus'];
				$statusDetail = $sessionResponse->result['presenceStatusDetail'];
			} else {
				$status = 'error';
				$statusDetail = 'error';
			}
		} else {
			$statusDetail = 'Manual status';
			$sessionResponse = $sessionModel->saveSession(PROVIDER_DUMMY, 'device', $userId, '-', null, SESSION_STATE_ACTIVE, null, $status, $statusDetail, $period);			
		}

		$sessionManager = new \Backend\SessionManager();
		$sessionManager->publishSessionStatus($userId, $status, $statusDetail);

		$f3->reroute('/popup/status');

	}

	function amIAuthenticated($ajax = false) {
		$f3=\Base::instance();

		if ( empty($f3->get('SESSION.accessToken')) ) {
#		if ( empty($f3->get('SESSION.accessToken')) || empty($f3->get('SESSION.refreshToken')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no tokens - " . print_r($f3->get('SESSION'), true));
			if ($f3->get('popup')) {
				$f3->reroute($f3->get('baseAppPath') . '/popup/login');
			} else {
				$f3->reroute($f3->get('baseAppPath'));
			}
		}
		return true;
	}


	function afterroute($f3) {
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		echo \Template::instance()->render('device_status.html');

	}

}
?>
