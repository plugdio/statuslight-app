<?php

namespace Presenters;

class Login {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}

	function login($f3, $args) {
		
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		$this->l->debug($this->tr . " - " . __METHOD__ . " - args: " . print_r($args, true));

		$service = $f3->get('PARAMS.service');
		$target = $f3->get('PARAMS.target');

		if ($service == PROVIDER_TEAMS) {
			$token = \Services\Teams::getToken($target);
		} elseif ($service == PROVIDER_GOOGLE) {
			$token = \Services\GCal::getToken($target);
		} elseif ($service == PROVIDER_SLACK) {
			$token = \Services\Slack::getToken($target);
		} else {
			$this->l->error($this->tr . " - " . __METHOD__ . " - unknown service: " . $servcice);
			return;
		}

		if (!empty($token)) {
			$f3->set('SESSION.accessToken', $token->getToken());
			$f3->set('SESSION.refreshToken', $token->getRefreshToken());
			$f3->set('SESSION.accessTokenExpiresOn', $token->getExpires());

			try {
				if ($service == PROVIDER_TEAMS) {
					$provider = \Services\Teams::getProvider($target);
					$provider->urlAPI = 'https://graph.microsoft.com/beta/';
					$me = $provider->get("me", $token);
#					$this->l->debug($this->tr . " - " . __METHOD__ . " - me: " . print_r($me, true));

					$providerUserId = $me['id'];
					$name = $me['displayName'];
					$email = $me['mail'];
					$refreshToken = null;

					$presenceResponse = \Services\Teams::getPresenceStatus($target, $token);
				}

				if ($service == PROVIDER_GOOGLE) {
					$provider = \Services\GCal::getProvider($target);
			        // We got an access token, let's now get the owner details
	        		$ownerDetails = $provider->getResourceOwner($token);
#					$this->l->debug($this->tr . " - " . __METHOD__ . " - ownerDetails: " . print_r($ownerDetails, true));

					$providerUserId = $ownerDetails->getId();
					$name = $ownerDetails->getName();
					$email = $ownerDetails->getEmail();
					$refreshToken = $token->getRefreshToken();

					$presenceResponse = \Services\GCal::getPresenceStatus($target, $token);

				}

				if ($service == PROVIDER_SLACK) {
					$provider = \Services\Slack::getProvider($target);

	        		$providerUserId = $provider->getAuthorizedUser($token)->getId();
	        		$team = $provider->getResourceOwner($token);
					$name = $team->getRealName();
					$email = $team->getEmail();

					$presenceResponse = \Services\SLack::getPresenceStatus($target, $token, $providerUserId);

				}

				$userModel = new \Models\User();
				$sessionModel = new \Models\Session();

				$userResult = $userModel->saveUser($providerUserId, $service, $name, $email);
				if ($userResult->success) {
					$userId = $userResult->result['id'];
					$f3->set('SESSION.userId', $userId);
					$f3->set('SESSION.name', $name);
				}

				$sessionDetails = $presenceResponse->result;

				$status = $sessionDetails->status;
				$statusDetail = $sessionDetails->statusDetail;
				$sessionState = $sessionDetails->sessionState;
				$closedReason = $sessionDetails->closedReason;

				$sessionResponse = $sessionModel->saveSession($service, $target, $userId, $token, $refreshToken, $sessionState, $closedReason, $status, $statusDetail);


			} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Caught exception " . $e->getMessage() . ' - ' . $e->getTraceAsString());
				$f3->clear('SESSION');
			}

		} else {
			$this->l->error($this->tr . " - " . __METHOD__ . " - empty token");
		}

		$f3->reroute('/' . $target . '/status');
	}

	function logout($f3, $args) {
		$f3->clear('SESSION');
		//TODO: remove session from DB
		$f3->reroute('/');
	}

}
?>
