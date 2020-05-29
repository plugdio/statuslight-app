<?php

namespace Models;

class Device {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
		$db = new \DB\SQL(
		    'mysql:host=' . trim(getenv('MYSQL_HOST')) . ';port=3306;dbname=statuslight',
		    trim(getenv('MYSQL_USER')),
		    trim(getenv('MYSQL_PASSWORD'))
		);

		$this->device = new \DB\SQL\Mapper($db, 'devices');
	}

	function addTempDevice($userId, $pin) {

		$response = new \Response($this->tr);
		$this->device->reset();
		$this->device->userId = $userId;
		$this->device->state = DEVICE_STATE_TEMP;
		$this->device->pin = $pin;
		$this->device->mqttClientId = '';
		$this->device->validity = date('Y-m-d H:i:s', time() + TEMP_DEVICE_VALIDY_MINS * 60);
		$this->device->save();
		
		$response->result = $this->device->cast();
		$response->success = true;
		return $response;

	}

	function getDeviceByUserId($userId) {

		$response = new \Response($this->tr);
		$this->device->load(array('userId=? AND state!=?', $userId, DEVICE_STATE_INACTIVE));
		if ($this->device->dry()) {
			$response->message = 'Device not found';
			return $response;
		}

		$response->result = array();

		while (!$this->device->dry()) {
			if ($this->device->state == DEVICE_STATE_TEMP) {
				if (strtotime($this->device->validity) < time()) {
/*
					$this->device->state = DEVICE_STATE_INACTIVE;
					$this->device->save();
*/
					$this->device->erase();					
				} else {
					$response->result[] = $this->device->cast();
				}
			} else {
				$response->result[] = $this->device->cast();
			}
			$this->device->next();
		}

		$response->success = true;
		return $response;

	}

	function getDeviceByClientIdAndPin($clientId, $pin) {

		$response = new \Response($this->tr);
		$this->device->load(array('mqttClientId=? AND pin=? AND state=?', $clientId, md5($pin), DEVICE_STATE_ACTIVE));
		if ($this->device->dry()) {
			$response->message = 'Device not found';
			return $response;
		}

		$response->result = $this->device->cast();
		$response->success = true;
		return $response;

	}

	function getDeviceByClientPin($clientId, $pin) {

		$response = new \Response($this->tr);
		$this->device->load(array('mqttClientId = ? AND pin=? AND state=?', '', $pin, DEVICE_STATE_TEMP));
		if ($this->device->dry()) {
			$response->message = 'Device not found';
			return $response;
		}

		if (strtotime($this->device->validity) < time()) {
/*
			$this->device->state = DEVICE_STATE_INACTIVE;
			$this->device->save();
*/
			$this->device->erase();
			$response->message = 'Device not found - validity';
			return $response;		
		}

		$this->device->mqttClientId = $clientId;
		$this->device->pin = md5($pin);
		$this->device->state = DEVICE_STATE_ACTIVE;
		$this->device->save();

		$response->result = $this->device->cast();
		$response->success = true;
		return $response;

	}

	function isPinUnique($pin) {
		$this->device->load(array('pin=? AND state=?', $pin, DEVICE_STATE_TEMP));
		if ($this->device->dry()) {
			return true;
		}
		return false;
	}

	function getDeviceByClientId($clientId) {

		$response = new \Response($this->tr);
		$this->device->load(array('mqttClientId=?', $clientId));
		if ($this->device->dry()) {
			$response->message = 'Client not found';
			return $response;
		}

		$response->result = $this->device->cast();
		$response->success = true;
		return $response;

	}

	function updateClient($clientId, $topic, $msg, $updateTime = false) {
		$this->device->load(array('mqttClientId=?', $clientId));
		if ($this->device->dry()) {
			return;
		}
		if ($updateTime) {
			$this->device->mqttUpdated = date('Y-m-d H:i:s', time());
		}

		$clientDetails = json_decode($this->device->clientDetails);
		if (empty($this->device->clientDetails) || empty($clientDetails) || !is_object($clientDetails)) {
			$clientDetails = new \stdClass();
		}

		$clientDetails->{$topic} = $msg;

		$this->device->clientDetails = json_encode($clientDetails);

		$this->device->save();
	}


}

?>
