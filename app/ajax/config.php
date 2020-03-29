<?php

namespace Ajax;

class Config extends \Ajax\MainAjax {

	function __construct() {
		parent::__construct();
	}


	function getConfig($f3, $args) {

		$this->l->debug($this->tr . " - " . __METHOD__ . " - START");
		$response = new \Response($this->tr);

		$teamsLoginUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=' . $f3->get('client_id') . '&response_type=code&redirect_uri=' . urlencode($f3->get('redirectUri')) . '&response_mode=query&scope=' . urlencode($f3->get('scope')) . '&state=' . $this->tr;
		$config = new \stdClass();
		$config->teamsLoginUrl = $teamsLoginUrl;
		$response->result = $config;
		$response->success = true;
		$f3->set('data', $response);
	}

	function amIAuthenticated() {}

}
?>
