<?php

namespace Backend;

class SessionManager {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
/*
		if (!$f3->get('CLI')) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - Request is not comming from CLI");
			$f3->error(401);
		}
*/
	}

	function run($f3, $args) {

		while (true) {
			$this->refreshSessions();
			$this->refreshSubscriptions();
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

			if (empty($session['token'])) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - No token");
				continue;
			}

			if (!in_array($session['type'], array(PROVIDER_TEAMS, PROVIDER_GOOGLE, PROVIDER_SLACK))) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Provider not supported " . $session['type']);
				continue;
			}

			$token = unserialize($session['token']);
			try {
				if (($session['type'] == PROVIDER_TEAMS) && ($token->hasExpired())) {
					$this->l->debug($this->tr . " - " . __METHOD__ . " - Azure token needs to be refreshed");
					$provider = \Services\Teams::getProvider($session['target']);
	            	$token = $provider->getAccessToken('refresh_token', [
	                	'refresh_token' => $token->getRefreshToken(),
	            	]);
	            }
				if ( ($session['type'] == PROVIDER_GOOGLE) && ( ($token->getExpires() - time()) < 120 ) ) {
					$this->l->debug($this->tr . " - " . __METHOD__ . " - Google token needs to be refreshed");
					$provider = \Services\GCal::getProvider($session['target']);
					$grant = new \League\OAuth2\Client\Grant\RefreshToken();
					$token = $provider->getAccessToken($grant, ['refresh_token' => $session['refreshToken']]);
	        	}

				if ($session['type'] == PROVIDER_TEAMS) {
					$presenceResponse = \Services\Teams::getPresenceStatus($session['target'], $token);
				} elseif ($session['type'] == PROVIDER_GOOGLE) {
					$presenceResponse = \Services\GCal::getPresenceStatus($session['target'], $token);
				} elseif ($session['type'] == PROVIDER_SLACK) {
					$userModel = new \Models\User();
					$userResponse = $userModel->getUser($session['userId']);
					$slackUserId = $userResponse->result['userId'];
					$presenceResponse = \Services\Slack::getPresenceStatus($session['target'], $token, $slackUserId);
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

			$this->publishSessionStatus($session['userId'], $status, $statusDetail);

			/*
			$dummySessionResponse = $sessionModel->getActiveDummySessionForUser($session['userId']);

			if (!$dummySessionResponse->success) {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - No active dummy sessions");
			} else {
				$status = $dummySessionResponse->result['presenceStatus'];
				$statusDetail = $dummySessionResponse->result['presenceStatusDetail'];
			}

			$mqttMessageModel = new \Models\MqttMessage();
			$deviceModel = new \Models\Device();
			$deviceResponse = $deviceModel->getDeviceByUserId($session['userId']);

			if ($deviceResponse->success && count($deviceResponse->result) > 0) {
				foreach ($deviceResponse->result as $device) {
					$mqttMessageModel->putInQueue('SL/' . $device['mqttClientId'] . '/statuslight/status/set', $status);
					$mqttMessageModel->putInQueue('SL/' . $device['mqttClientId'] . '/statuslight/statusdetail/set', $statusDetail);
				}
			}
*/			
		}
	}

	function publishSessionStatus($userId, $status, $statusDetail) {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $userId);

		$sessionModel = new \Models\Session();
		$dummySessionResponse = $sessionModel->getActiveDummySessionForUser($userId);

		if (!$dummySessionResponse->success) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - No active dummy sessions");
		} else {
			$status = $dummySessionResponse->result['presenceStatus'];
			$statusDetail = $dummySessionResponse->result['presenceStatusDetail'];
		}

		$mqttMessageModel = new \Models\MqttMessage();
		$deviceModel = new \Models\Device();
		$deviceResponse = $deviceModel->getDeviceByUserId($userId);

#		$this->l->debug($this->tr . " - " . __METHOD__ . " - deviceResponse: " . print_r($deviceResponse, true));

		if ($deviceResponse->success && count($deviceResponse->result) > 0) {
			foreach ($deviceResponse->result as $device) {
				$mqttMessageModel->putInQueue('SL/' . $device['mqttClientId'] . '/statuslight/status/set', $status);
				$mqttMessageModel->putInQueue('SL/' . $device['mqttClientId'] . '/statuslight/statusdetail/set', $statusDetail);
			}
		} else {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - no device found");
		}

	}

	function refreshSubscriptions() {
		$f3=\Base::instance();
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $f3->get('GET.env'));
		$env = $f3->get('GET.env');

		$sessionModel = new \Models\Session();
		$subscriptionModel = new \Models\Subscription();
		$subscriptionResponse = $subscriptionModel->getActiveSubscriptions();

		if (!$subscriptionResponse->success) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - No active subscriptions");
			return;
		}

		foreach ($subscriptionResponse->result as $subscription) {

			$this->l->debug($this->tr . " - " . __METHOD__ . " - working with " . $subscription['id'] . ' - '. $subscription["expirationTime"]);

			if (strtotime($subscription["expirationTime"]) > time() + 10 * 60) {
				continue;
			}

			$sessionResponse = $sessionModel->getActiveSessionForUser($subscription["userId"]);
			if (!$sessionResponse->success) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - No session");
				continue;
			}

			$session = $sessionResponse->result;

			if (empty($session['token'])) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - No token");
				continue;
			}

			if (!in_array($session['type'], array(PROVIDER_TEAMS))) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Provider not supported " . $session['type']);
				continue;
			}

			$token = unserialize($session['token']);
			try {
				$providerResponse = \Services\Teams::renewSubscription($token, $subscription["subscriptionId"]);

				if ($providerResponse->success) {

					$subscriptionResponse = $subscriptionModel->updateSubscriptionExpiry($subscription["id"], $providerResponse->result["expirationTime"]);
			
					if (!$subscriptionResponse->success) {
						$this->l->error($this->tr . " - " . __METHOD__ . " - subscription update error - " . $subscriptionResponse->message);
						continue;
					}
	
					$this->l->debug($this->tr . " - " . __METHOD__ . " - Subscription updated");
			
				} else {
					$this->l->error($this->tr . " - " . __METHOD__ . " - Subscription renew error");
				}

			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception1 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#				$this->l->error($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			} catch (\BadMethodCallException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception2 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
#				$this->l->error($this->tr . " - " . __METHOD__ . " - token: " . print_r($token, true));
			} catch (\RuntimeException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception3 " . $e->getMessage() . ' - ' . $e->getTraceAsString());
			}

		}
	}

}
?>
