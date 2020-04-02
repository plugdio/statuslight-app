<?php

namespace Services;

use League\OAuth2\Client\Provider\Google;

class ServiceBase {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

		// login link: https://developers.google.com/identity/protocols/oauth2/web-server
		// https://github.com/thephpleague/oauth2-client
		$this->gcalProvider = new Google([
		    'clientId'     => $f3->get('gcal_client_id'),
		    'clientSecret' => $f3->get('gcal_client_secret'),
		    'redirectUri'  => $f3->get('redirectUriGCal'),
		    'accessType'   => 'offline',
//		    'proxy'                   => 'localhost:8888',
//    		'verify'                  => false
		]);

	}

	function getConfig($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$response = new \Response($this->tr);

		$teamsLoginUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=' . $f3->get('client_id') . '&response_type=code&redirect_uri=' . urlencode($f3->get('redirectUriTeams')) . '&response_mode=query&scope=' . urlencode($f3->get('scope')) . '&state=' . $this->tr;

		$gcalLoginUrl = $this->gcalProvider->getAuthorizationUrl([
		    'scope' => [
		        'https://www.googleapis.com/auth/calendar.readonly'
		    ],
		]);

		$response->result->teamsLoginUrl = $teamsLoginUrl;
		$response->result->gcalLoginUrl = $gcalLoginUrl;
		$response->success = true;
		$f3->set('page_type', 'AJAX');
		$f3->set('data', $response);
	} 

	function amIAuthenticated($ajax = false) {
		$f3=\Base::instance();

#		if ( empty($f3->get('SESSION.accessToken')) ) {
		if ( empty($f3->get('SESSION.accessToken')) || empty($f3->get('SESSION.refreshToken')) ) {
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
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
	}


	function afterroute($f3) {
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		if ($f3->get('page_type') == 'AJAX') {
			header('Content-Type: application/json');
			echo json_encode($f3->get('data'), JSON_PRETTY_PRINT);
		} else {
			echo \Template::instance()->render('blank.html');
		}

	}

	function blank($f3, $args) {
/*
		$f3->set('login_link_teams', 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=' . $f3->get('client_id') . '&response_type=code&redirect_uri=' . urlencode($f3->get('redirectUriTeams')) . '&response_mode=query&scope=' . urlencode($f3->get('scope')) . '&state=' . $this->tr);

		$login_link_gcal = 'https://accounts.google.com/o/oauth2/v2/auth?client_id=' . $f3->get('gcal_client_id') . '&redirect_uri=' . urlencode('http://localhost:8000/gcal/login') . '&response_type=code&scope=' . urlencode('https://www.googleapis.com/auth/calendar.readonly') . '&access_type=offline&state=' . $this->tr;

		$gcalProvider = new Google([
		    'clientId'     => $f3->get('gcal_client_id'),
		    'clientSecret' => $f3->get('gcal_client_secret'),
		    'redirectUri'  => 'http://localhost:8000/gcal/login',
		    'proxy'                   => 'localhost:8888',
    		'verify'                  => false
		]);

		$login_link_gcal = $gcalProvider->getAuthorizationUrl([
		    'scope' => [
		        'https://www.googleapis.com/auth/calendar.readonly'
		    ],
		]);

		$this->l->debug($this->tr . " - " . __METHOD__ . " - authUrl: " . $login_link_gcal);

		$f3->set('login_link_gcal', $login_link_gcal);
*/
		$f3->set('base_app_path', $f3->get('baseAppPath'));
		$f3->set('current_page', 'REGISTER');

	}



}
?>
