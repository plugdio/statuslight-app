<?php

namespace Presenters;

class Graph {


	function __construct() {
		$f3=\Base::instance();
		$this->tr = $f3->get('tr');
		$this->l = $f3->get('log');
	}

	function notification($f3, $args) {
		
		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - args: " . print_r($args, true));
#        $this->l->debug($this->tr . " - " . __METHOD__ . " - req: " . print_r($f3->get('REQUEST'), true));
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - body: " . $f3->get('BODY'));

		# POST /graph/notification?validationToken=Validation%3a+Testing+client+application+reachability+for+subscription+Request-Id%3a+77774489-c543-440d-9381-01b1d057502f
		if ( !empty($f3->get('REQUEST.validationToken')) ) {
            header('Content-Type: text/plain');
            echo $f3->get('REQUEST.validationToken');
			return;
        }

		$body = json_decode($f3->get('BODY'));
#		$this->l->debug($this->tr . " - " . __METHOD__ . " - body: " . print_r($body, true));

		$subscriptionModel = new \Models\Subscription();
		$sessionModel = new \Models\Session();
		$sessionManager = new \Backend\SessionManager();

		foreach ($body->value as $value) {

			$subscriptionResponse = $subscriptionModel->getSubscription($value->subscriptionId); 
			if ($subscriptionResponse->success) {
				$subscription = $subscriptionResponse->result;
				// TODO: update based on userId
				$this->l->debug($this->tr . " - " . __METHOD__ . " - Updating session for user - " . $subscription["userId"]);
				$sessionResponse = $sessionModel->updateTeamsSessionStatus($subscription["userId"], $value->resourceData->availability, $value->resourceData->activity);
				if ($sessionResponse->success) {
					$status = \Services\Teams::translateStatus($value->resourceData->availability);
					$this->l->debug($this->tr . " - " . __METHOD__ . " - status - " . $status);
					$statusDetail = 'Teams: ' . $value->resourceData->availability . '/' . $value->resourceData->activity;
					$sessionManager->publishSessionStatus($subscription["userId"], $status, $statusDetail);
				} else {
					$this->l->error($this->tr . " - " . __METHOD__ . " - Error updating session - " . $sessionResponse->message);
				}
			} else {
				$this->l->error($this->tr . " - " . __METHOD__ . " - Subscription not found - " . $value->subscriptionId);
			}
		}


	}

}
?>
