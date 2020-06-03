<?php

namespace Presenters;

class Profile {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}

	function main($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $f3->get('SESSION.userId'));

		$this->amIAuthenticated();

		$userId = $f3->get('SESSION.userId');

		$userModel = new \Models\User();
		$userResponse = $userModel->getUser($userId);
		$myUser = $userResponse->result;
		$f3->set('user', $myUser);

		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->getActiveSessionForUser($userId);
		$mySession = $sessionResponse->result;
		$mySession['token'] = null;
		$mySession['refreshToken'] = null;
		$f3->set('session', $mySession);

		$deviceModel = new \Models\Device();
		$deviceResponse = $deviceModel->getDeviceByUserId($userId);
		$myDevices = array();
		foreach ($deviceResponse->result as $device) {
			$device['clientDetails'] = json_encode(json_decode($device['clientDetails']), JSON_PRETTY_PRINT);
			$myDevices[] = $device;
		}
		$f3->set('devices', $myDevices);

		echo \Template::instance()->render('profile.html');

	}

	function delete($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START - " . $f3->get('SESSION.userId'));

		$this->amIAuthenticated();

		$userId = $f3->get('SESSION.userId');

		$deviceModel = new \Models\Device();
		$deviceResponse = $deviceModel->deleteDevicesForUser($userId);

		$sessionModel = new \Models\Session();
		$sessionResponse = $sessionModel->deleteSessionsForUser($userId);

		$userModel = new \Models\User();
		$userResponse = $userModel->deleteUser($userId);

		$f3->reroute($f3->get('baseStaticPath'));

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

}
?>