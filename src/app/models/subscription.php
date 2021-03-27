<?php

namespace Models;

class Subscription {

	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
		
		$db = new \DB\SQL(
		    'mysql:host=' . trim(getenv('MYSQL_HOST')) . ';port=3306;dbname=statuslight',
		    trim(getenv('MYSQL_USER')),
		    trim(getenv('MYSQL_PASSWORD'))
		);

        $this->subscription = new \DB\SQL\Mapper($db, 'subscriptions');
	}

	function saveSubscription($userId, $subscriptionId, $expirationTime, $clientState, $resource) {

		$response = new \Response($this->tr);
		
		$this->subscription->reset();
    	$this->subscription->userId = $userId;
    	$this->subscription->subscriptionId = $subscriptionId;
    	$this->subscription->startTime = date('Y-m-d H:i:s');
		$this->subscription->expirationTime = date('Y-m-d H:i:s', $expirationTime);
    	$this->subscription->state = SUBSCRIPTION_STATE_ACTIVE;
    	$this->subscription->clientState = $clientState;
    	$this->subscription->resource = $resource;
    	$this->subscription->save();

    	$response->result = $this->subscription->cast();
		$response->success = true;
		return $response;
	}

	function getSubscription($subscriptionId) {

		$response = new \Response($this->tr);
		$this->subscription->load(array('subscriptionId=?', $subscriptionId));
		if ($this->subscription->dry()) {
			$response->message = 'Subscription not found';
			return $response;
		}

		$response->result = $this->subscription->cast();
		$response->success = true;
		return $response;

	}

	function getActiveSubscriptionForUser($userId) {

		$response = new \Response($this->tr);
		$this->subscription->load(array('userId=? AND state=?', $userId, SUBSCRIPTION_STATE_ACTIVE));
		if ($this->subscription->dry()) {
			$response->message = 'Subscription not found';
			return $response;
		}

		$response->result = $this->subscription->cast();
		$response->success = true;
		return $response;
	}

	function getActiveSubscriptions() {

		$response = new \Response($this->tr);
		$this->subscription->load(array('state=?', SUBSCRIPTION_STATE_ACTIVE));
		if ($this->subscription->dry()) {
			$response->message = 'Subscription not found';
			return $response;
		}
		$response->result = array();

		while (!$this->subscription->dry()) {
			if (strtotime($this->subscription->expirationTime) < time()) {
				$this->subscription->erase();
			} else {
				$response->result[] = $this->subscription->cast();
			}
			$this->subscription->next();
		}

		$response->success = true;
		return $response;
	}

	function updateSubscriptionExpiry($id, $expirationTime) {
		
		$response = new \Response($this->tr);
		
		$this->subscription->load(array('id=?', $id));
		if ($this->subscription->dry()) {
			$response->message = 'Subscription not found';
			return $response;
		}
		
		$this->subscription->expirationTime = date('Y-m-d H:i:s', $expirationTime);
    	$this->subscription->save();

		$response->success = true;
		return $response;
	}

}

?>
