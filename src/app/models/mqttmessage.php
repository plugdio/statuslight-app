<?php

namespace Models;

class MqttMessage {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');

        $this->mqttMessage = new \DB\SQL\Mapper($f3->get('db'), 'mqttmessages');
	}

	function putInQueue($topic, $content) {

		$this->mqttMessage->reset();
    	$this->mqttMessage->topic = $topic;
    	$this->mqttMessage->content = $content;
		$this->mqttMessage->queueIn = date('Y-m-d H:i:s');
    	$this->mqttMessage->state = MQTTMSG_NOT_SENT;
    	$this->mqttMessage->save();
	}

	function getFromQueue() {
		$response = new \Response($this->tr);
		$this->mqttMessage->load(array('state=?', MQTTMSG_NOT_SENT));
		if ($this->mqttMessage->dry()) {
			$response->message = 'mqttMessage not found';
			return $response;
		}
		$response->result = array();

		while (!$this->mqttMessage->dry()) {
			if (strtotime($this->mqttMessage->queueIn) < time() - MQTTMSG_VALIDY_MINS * 60) {
/*
				$this->mqttMessage->state = MQTTMSG_EXPIRED;
				$this->mqttMessage->save();
*/

$this->l->debug("deleting: " . print_r($this->mqttMessage->cast(), true));
				$this->mqttMessage->erase();				
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
		
		$this->mqttMessage->load(array('id=?', $messageId));
		if ($this->mqttMessage->dry()) {
			$response->message = 'mqttMessage not found';
			return $response;
		}
		
    	if ($newState == MQTTMSG_SENT) {
    		$this->mqttMessage->queueOut = date('Y-m-d H:i:s');
    	}
    	$this->mqttMessage->state = $newState;
    	$this->mqttMessage->save();
	}

	function deleteMessage($messageId) {
		$response = new \Response($this->tr);
		
		$this->mqttMessage->load(array('id=?', $messageId));
		if ($this->mqttMessage->dry()) {
			$response->message = 'mqttMessage not found';
			return $response;
		}
		
    	$this->mqttMessage->erase();
    	$response->success = true;
		return $response;
	}

}

?>
