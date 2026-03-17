<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		header('Content-Type: application/json');
	}

	protected function response($message, $status, $data)
	{
		set_status_header($status);
		echo json_encode(array(
			'message' => $message,
			'status' => (int) $status,
			'data' => $data,
		));
	}

	protected function error($message, $status, $errors, $data)
	{
		set_status_header($status);
		echo json_encode(array(
			'message' => $message,
			'status' => (int) $status,
			'errors' => $errors,
			'data' => $data,
		));
	}
}
