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
			$this->l->debug($this->tr . " - " . __METHOD__ . " - Sleeping");
			sleep(60);
		}

	}

	function refreshSessions() {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $f3->get('GET.env'));
		$env = $f3->get('GET.env');

		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->getActiveSessions();

		if (!$sessionResponse->success) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - No active sessions");
			return;
		}

		foreach ($sessionResponse->result as $session) {

			$this->l->debug($this->tr . " - " . __METHOD__ . " - working with " . $session['id'] . ' - ' . $session['type']);

			if (!in_array($session['type'], array(PROVIDER_AZURE, PROVIDER_GOOGLE, PROVIDER_SLACK))) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Provider not supported " . $session['type']);
				continue;
			}

			$token = unserialize($session['token']);
			try {
				if (($session['type'] == PROVIDER_AZURE) && ($token->hasExpired())) {
					$this->l->debug($this->tr . " - " . __METHOD__ . " - Azure token needs to be refreshed");
					$provider = \Services\Teams::getProvider('/device/login/teams');
	            	$token = $provider->getAccessToken('refresh_token', [
	                	'refresh_token' => $token->getRefreshToken(),
	            	]);
	            }
				if ( ($session['type'] == PROVIDER_GOOGLE) && ( ($token->getExpires() - time()) < 3000 ) ) {
					$this->l->debug($this->tr . " - " . __METHOD__ . " - Google token needs to be refreshed");
					$provider = \Services\GCal::getProvider('/device/login/gcal');
					$grant = new \League\OAuth2\Client\Grant\RefreshToken();
					$token = $provider->getAccessToken($grant, ['refresh_token' => $session['refreshToken']]);
	        	}

				if ($session['type'] == PROVIDER_AZURE) {
					$presenceResponse = \Services\Teams::getPresenceStatus('/device/login/teams', $token);
				} elseif ($session['type'] == PROVIDER_GOOGLE) {
					$presenceResponse = \Services\GCal::getPresenceStatus('/device/login/gcal', $token);
				} elseif ($session['type'] == PROVIDER_SLACK) {
$this->l->debug($this->tr . " - " . __METHOD__ . " - Expires at: " . date('r', $token->getExpires()));
$this->l->debug($this->tr . " - " . __METHOD__ . " - Expires in: " . ($token->getExpires() - time()) );
					$userModel = new \Models\User();
					$userResponse = $userModel->getUser($session['userId']);
					$slackUserId = $userResponse->result['userId'];
					$presenceResponse = \Services\Slack::getPresenceStatus('/device/login/slack', $token, $slackUserId);
				}

				$newSession = $presenceResponse->result;

				$status = $newSession->status;
				$statusDetail = $newSession->statusDetail;
				$sessionState = $newSession->sessionState;
				$closedReason = $newSession->closedReason;

			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception1 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#				$this->l->error($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
				$status = STATUS_ERROR;
				$statusDetail = STATUS_ERROR;
				$sessionState = SESSION_STATE_ERROR;
				$closedReason = $this->tr . ' - ' . $e->getMessage();
			} catch (\BadMethodCallException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception2 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#				$this->l->error($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
				$status = STATUS_ERROR;
				$statusDetail = STATUS_ERROR;
				$sessionState = SESSION_STATE_ERROR;
				$closedReason = $this->tr . ' - ' . $e->getMessage();
			} catch (\RuntimeException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception3 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$status = STATUS_ERROR;
				$statusDetail = STATUS_ERROR;
				$sessionState = SESSION_STATE_ERROR;
				$closedReason = $this->tr . ' - ' . $e->getMessage();
			}

			$sessionModel->updateSession($session['id'], $token, $sessionState, $closedReason, $status, $statusDetail);

			$mqttMessageModel = new \Models\MqttMessage();
			$deviceModel = new \Models\Device();
			$deviceResponse = $deviceModel->getDeviceByUserId($session['userId']);

			if ($deviceResponse->success && count($deviceResponse->result) > 0) {
				foreach ($deviceResponse->result as $device) {
					$mqttMessageModel->putInQueue('SL/' . $device['mqttClientId'] . '/statuslight/status/set', $status);
					$mqttMessageModel->putInQueue('SL/' . $device['mqttClientId'] . '/statuslight/statusdetail/set', $statusDetail);
				}
			}
			
		}
	}

}
?>
