<?php

namespace Models;

class MqttMessage {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
        $db = new \DB\Jig($f3->get('dbdir'), \DB\Jig::FORMAT_JSON);
        $this->mqttMessage = new \DB\Jig\Mapper($db, 'mqttmessages.json');
	}

	function putInQueue($topic, $content) {

		$this->mqttMessage->reset();
    	$this->mqttMessage->topic = $topic;
    	$this->mqttMessage->content = $content;
    	$this->mqttMessage->queue_in = time();
    	$this->mqttMessage->queue_out = null;
    	$this->mqttMessage->state = MQTTMSG_NOT_SENT;
    	$this->mqttMessage->save();
	}

	function getFromQueue() {
		$response = new \Response($this->tr);
		$this->mqttMessage->load(array('@state=?', MQTTMSG_NOT_SENT));
		if ($this->mqttMessage->dry()) {
			$response->message = 'mqttMessage not found';
			return $response;
		}
		$response->result = array();

		while (!$this->mqttMessage->dry()) {
			if ($this->mqttMessage->queue_in < time() - MQTTMSG_VALIDY_MINS * 60) {
				$this->mqttMessage->state = MQTTMSG_EXPIRED;
				$this->mqttMessage->save();
			} else {
				$response->result[] = $this->mqttMessage->cast();
			}
			$this->mqttMessage->next();
		}

		$response->success = true;
		return $response;
	}

	function updateMessage($messageId, $newState) {
		$response = new \Response($this->tr);
		
		$this->mqttMessage->load(array('@_id=?', $messageId));
		if ($this->mqttMessage->dry()) {
			$response->message = 'mqttMessage not found';
			return $response;
		}
		
    	if ($newState == MQTTMSG_SENT) {
    		$this->mqttMessage->queue_out = time();
    	}
    	$this->mqttMessage->state = $newState;
    	$this->mqttMessage->save();
	}

}

?>
