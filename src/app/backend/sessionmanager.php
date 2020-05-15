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

			if (!in_array($session['type'], array(PROVIDER_AZURE, PROVIDER_GOOGLE))) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Provider not supported " . $session['type']);
				continue;
			}

			$token = unserialize($session['token']);
			try {
				if ($token->hasExpired()) {
					$this->l->debug($this->tr . " - " . __METHOD__ . " - token needs to be refreshed");
					if ($session['type'] == PROVIDER_AZURE) {
						$provider = \Services\Teams::getProvider('/device/login/teams');
					} elseif ($session['type'] == PROVIDER_GOOGLE) {
						$provider = \Services\GCal::getProvider('/device/login/gcal');
					} elseif ($session['type'] == PROVIDER_SLACK) {
						$provider = \Services\Slack::getProvider('/device/login/slack');
					}
	            	$token = $provider->getAccessToken('refresh_token', [
	                	'refresh_token' => $token->getRefreshToken(),
	            	]);
	        	}

				if ($session['type'] == PROVIDER_AZURE) {
					$presenceResponse = \Services\Teams::getPresenceStatus('/device/login/teams', $token);
				} elseif ($session['type'] == PROVIDER_GOOGLE) {
					$presenceResponse = \Services\GCal::getPresenceStatus('/device/login/gcal', $token);
				}

				$newSession = $presenceResponse->result;

				$status = $newSession->status;
				$subStatus = $newSession->subStatus;
				$sessionState = $newSession->sessionState;
				$closedReason = $newSession->closedReason;

			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#				$this->l->error($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
				$status = STATUS_ERROR;
				$subStatus = STATUS_ERROR;
				$sessionState = SESSION_STATE_ERROR;
				$closedReason = $e->getMessage();
			}

			$sessionModel->updateSession($session['_id'], $token, $sessionState, $closedReason, $status, $subStatus);

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
