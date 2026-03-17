<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

class Projeto extends MY_Controller
{
	public $doctrine;

	function __construct()
	{
		parent::__construct();
		if (! isset($this->doctrine)) {
			$this->load->library('doctrine');
		}
	}

	public function index()
	{
		$data = array();
		$projetos = $this->doctrine->em->getRepository('Entity\Projeto')->findAll();

		foreach ($projetos as $projeto) {
			$data[] = array(
				'id' => (int) $projeto->getId(),
				'descricao' => $projeto->getDescricao(),
			);
		}

		$this->response('Projetos retornados com sucesso.', 200, $data);
	}
}
