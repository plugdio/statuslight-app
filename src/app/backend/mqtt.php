<?php

namespace Backend;

class Mqtt {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}


	function auth($f3, $args) {
		# username=jane%40mens.de&password=jolie&topic=&acc=-1
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$clientId = $f3->get('POST.username');
		$password = $f3->get('POST.password');

		if (empty($clientId) || empty($password)) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - MQTT auth failed: missing username or password");
			$f3->error(403);
		}

		if (preg_match('/^adm_.*/', $clientId)) {
			$adminModel = new \Models\Admin();
			$adminResult = $adminModel->getAdmin($clientId, $password);
			if (!$adminResult->success) {
				$this->l->debug($this->tr . " - " . __METHOD__ . " - MQTT auth failed: admin credentials don't match");
				$f3->error(403);
			}
		}

		$deviceModel = new \Models\Device();
		$deviceResult = $deviceModel->getDeviceByClientId($clientId, $password);
		if ($deviceResult->success) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - Device authenticated with clientId: " . $clientId);
			echo "ok";
			return;
		}
		
		$this->l->debug($this->tr . " - " . __METHOD__ . " - Active device not found with clientId: " . $clientId . ' and PIN: ' . $pin );

		}

		$deviceResult = $deviceModel->getDeviceByPin($clientId, $password);
		if ($deviceResult->success) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - Temp device has been activated with clientId: " . $clientId);
			echo "ok";
			return;
		}

		$this->l->debug($this->tr . " - " . __METHOD__ . " - Temp device not found with clientId: " . $clientId . ' and PIN: ' . $pin );

		$f3->error(403);

	}

	function superuser($f3, $args) {
		$f3->error(403);
	}

	function acl($f3, $args) {
		# username=jane%40mens.de&password=&topic=t%2F1&acc=2&clientid=JANESUB
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");

		$clientId = $f3->get('POST.username');
		$topic = urldecode($f3->get('POST.topic'));
		$acc = $f3->get('POST.acc');
		$deviceId = $f3->get('POST.clientid');

		if (empty($clientId) || empty($topic) || empty($acc)) {
			$this->l->debug($this->tr . " - " . __METHOD__ . " - MQTT acl failed: missing params");
			$f3->error(403);
		}

	}

}
?>