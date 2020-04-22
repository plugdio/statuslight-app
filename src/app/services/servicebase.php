<?php

namespace Services;

use League\OAuth2\Client\Provider\Google;
use TheNetworg\OAuth2\Client\Provider\Azure;

class ServiceBase {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

		// https://github.com/thenetworg/oauth2-azure
		$this->teamsProvider = new Azure([
		    'clientId'          => $f3->get('teams_client_id'),
		    'clientSecret'      => $f3->get('teams_client_secret'),
		    'redirectUri'  => $f3->get('baseAppPath') . '/teams/login',
//		    'proxy'                   => 'localhost:8888',
//    		'verify'                  => false
		]);

		$this->teamsProvider->pathAuthorize = "/oauth2/v2.0/authorize";
		$this->teamsProvider->pathToken = "/oauth2/v2.0/token";
		$this->teamsProvider->scope = ["offline_access user.read Presence.Read"];
		$this->teamsProvider->authWithResource = false;

		// login link: https://developers.google.com/identity/protocols/oauth2/web-server
		// https://github.com/thephpleague/oauth2-client
		$this->gcalProvider = new Google([
		    'clientId'     => $f3->get('gcal_client_id'),
		    'clientSecret' => $f3->get('gcal_client_secret'),
		    'redirectUri'  => $f3->get('baseAppPath') . '/gcal/login',
		    'accessType'   => 'offline',
		    'prompt'       => 'consent'
//		    'proxy'                   => 'localhost:8888',
//    		'verify'                  => false
		]);

		//https://github.com/adam-paterson/oauth2-slack
		$this->slackProvider = new \AdamPaterson\OAuth2\Client\Provider\Slack([
		    'clientId'          => $f3->get('slack_client_id'),
		    'clientSecret'      => $f3->get('slack_client_secret'),
		    'redirectUri'       => $f3->get('baseAppPath') . '/slack/login',
		]);

	}

	function getConfig($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$response = new \Response($this->tr);

		$teamsLoginUrl = $this->teamsProvider->getAuthorizationUrl();

		$gcalLoginUrl = $this->gcalProvider->getAuthorizationUrl([
		    'scope' => [
		        'https://www.googleapis.com/auth/calendar.readonly'
		    ],
		]);

		$slackLoginUrl = $this->slackProvider->getAuthorizationUrl([
			'scope' => 'users:read'
		]);

		$response->result->teamsLoginUrl = $teamsLoginUrl;
		$response->result->gcalLoginUrl = $gcalLoginUrl;
		$response->result->slackLoginUrl = $slackLoginUrl;
		$response->success = true;
		$f3->set('page_type', 'AJAX');
		$f3->set('data', $response);
	} 

	function amIAuthenticated($ajax = false) {
		$f3=\Base::instance();

		if ( empty($f3->get('SESSION.accessToken')) ) {
#		if ( empty($f3->get('SESSION.accessToken')) || empty($f3->get('SESSION.refreshToken')) ) {
			$this->l->error($this->tr . " - " . __METHOD__ . " - no tokens - " . print_r($f3->get('SESSION'), true));
			if ($ajax) {
				$f3->set('page_type', 'AJAX');
				$response = new \Response($this->tr);
				$response->message = 'Not authenticated';
				$f3->set('data', $response);
				return false;
			} else {
				$f3->reroute($f3->get('baseStaticPath'));
			}
		}
		return true;
	}


	function beforeroute($f3, $args) {
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
	}


	function afterroute($f3) {
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('blank.html');
		}

	}

	function blank($f3, $args) {

		$f3->set('base_app_path', $f3->get('baseAppPath'));
		$f3->set('current_page', 'REGISTER');

	}

	function logout($f3) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");


		$f3->clear('SESSION');

		$f3->reroute($f3->get('baseStaticPath'));
		

	}


}
?>
