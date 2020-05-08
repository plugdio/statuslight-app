<?php

namespace Models;

class Device {

	function __construct() {
		$f3=\Base::instance();		
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
        $this->device = new \DB\Jig\Mapper($db, 'devices.json');
	}

	function addTempDevice($userId, $pin) {

		$response = new \Response($this->tr);
		$this->device->reset();
		$this->device->userId = $userId;
		$this->device->clientId = null;
		$this->device->state = DEVICE_STATE_TEMP;
		$this->device->pin = $pin;
		$this->device->validity = time() + TEMP_DEVICE_VALIDY_MINS * 60;
		$this->device->save();
		
		$response->result = $this->device->cast();
		$response->success = true;
		return $response;

	}

	function getDeviceByUserId($userId) {

		$response = new \Response($this->tr);
		$this->device->load(array('@userId=? AND @state!=?', $userId, DEVICE_STATE_INACTIVE));
		if ($this->device->dry()) {
			$response->message = 'Device not found';
			return $response;
		}
		if ($this->device->state == DEVICE_STATE_TEMP) {
			if ($this->device->validity < time()) {
				$this->device->state = DEVICE_STATE_INACTIVE;
				$this->device->save();
				$response->message = 'Device not found';
				return $response;		
			}
		}

		$response->result = $this->device->cast();
		$response->success = true;
		return $response;

	}

	function getDeviceByClientId($clientId, $pin) {

		$response = new \Response($this->tr);
		$this->device->load(array('@clientId=? AND @pin=? AND @state=?', $clientId, $pin, DEVICE_STATE_ACTIVE));
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
		$this->device->load(array('@clientId=? AND @pin=? AND @state=?', null, $pin, DEVICE_STATE_TEMP));
		if ($this->device->dry()) {
			$response->message = 'Device not found';
			return $response;
		}

		if ($this->device->validity < time()) {
			$this->device->state = DEVICE_STATE_INACTIVE;
			$this->device->save();
			$response->message = 'Device not found';
			return $response;		
		}

		$this->device->clientId = $clientId;
		$this->device->state = DEVICE_STATE_ACTIVE;
		$this->device->save();

		$response->result = $this->device->cast();
		$response->success = true;
		return $response;

	}

	function isPinUnique($pin) {
		$this->device->load(array('@pin=? AND @state=?', $pin, DEVICE_STATE_TEMP));
		if ($this->device->dry()) {
			return true;
		}
		return false;
	}

}

?>
