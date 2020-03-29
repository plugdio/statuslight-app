<?php


class Response {

	public $tr;
	public $success;
	public $message;
	public $result;  		// Object, contains the result

	function __construct($tr) {
		if ($tr == NULL) {
			$tr = substr(md5(uniqid(rand(), true)),0,6);
		}
		$this->tr = $tr;
		$this->success = false;
		$this->message = null;
		$this->result = new stdClass();
	}
	
}

function guid(){
#	if (function_exists('com_create_guid')){
#		return com_create_guid();
#	}else{
		mt_srand((double)microtime()*10000);
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = chr(45);// "-"
		$uuid = "" #chr(123)// ""
            .substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);
#                .chr(125);// "}"
		return $uuid;
#	}
}

?>
