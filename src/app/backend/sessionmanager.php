<?php

namespace Backend;

class SessionManager {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

		if (!$f3->get('CLI')) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Request is not comming from CLI");
			$f3->error(401);
		}
	}

	function run($f3, $args) {

		while (true) {
			$this->refreshSessions();
			sleep(60);
		}

	}

	function refreshSessions() { #($f3, $args) {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $f3->get('GET.env'));
		$env = $f3->get('GET.env');
		if (strtoupper($env) != $f3->get('ENV')) {
			$f3->set('ENV', $env);
			if ($env != 'DEV') {
				$f3->set('DEBUG', 0);
			}
		}

		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->getActiveSessions();

		if (!$sessionResponse->success) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - No active sessions");
			return;
		}

		foreach ($sessionResponse->result as $session) {

			$this->l->debug($this->tr . " - " . __METHOD__ . " - working with " . $session['_id']);

			if ($session['type'] == PROVIDER_AZURE) {
				$provider = \Services\Teams::getProvider('/device/login/teams');
				$provider->urlAPI = 'https://graph.microsoft.com/beta/';
				$ref = 'me/presence';
			} else {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Provider not supported " . $session['type']);
				continue;
			}

			$token = unserialize($session['token']);
			try {
				if ($token->hasExpired()) {
					$this->l->debug($this->tr . " - " . __METHOD__ . " - token needs to be refreshed");
	            	$token = $provider->getAccessToken('refresh_token', [
	                	'refresh_token' => $token->getRefreshToken(),
	            	]);
	        	}

				$providerResponse = $provider->request('get', $ref, $token, []);

				$this->l->debug($this->tr . " - " . __METHOD__ . " - providerResponse: " . print_r($providerResponse, true));

			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$this->l->error($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
				$providerResponse = array(
					'exception' => $e->getMessage()
				);
			}


			if (array_key_exists('availability', $providerResponse)) {
				$newState = SESSION_STATE_ACTIVE;
				if (array_key_exists('activity', $providerResponse)) {
					$subStatus = $providerResponse["availability"] . ':' . $providerResponse["activity"];
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
				$closedReason = $providerResponse["exception"];
				$status = STATUS_ERROR;
			} else {
				$newState = SESSION_STATE_INACTIVE;
				$closedReason = "Presence coudn't be retreived";
				$status = STATUS_ERROR;
			}
	        
			$sessionModel->updateSession($session['_id'], $token, $newState, $closedReason, $status, $subStatus);

			$mqttMessageModel = new \Models\MqttMessage();
			$deviceModel = new \Models\Device();
			$deviceResponse = $deviceModel->getDeviceByUserId($session['userId']);

			if ($deviceResponse->success && count($deviceResponse->result) > 0) {
				foreach ($deviceResponse->result as $device) {
					$mqttMessageModel->putInQueue('SL/' . $device['clientId'] . '/statuslight/status/set', $status);
				}
			}
			
		}
	}

}
?>
