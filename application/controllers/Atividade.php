<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

class Atividade extends CI_Controller
{
	public $doctrine;

	function __construct()
	{
		parent::__construct();
		if (! isset($this->doctrine)) {
			$this->load->library('doctrine');
		}
		header('Content-Type: application/json');
	}

	public function projeto($id)
	{
		$data = [];
		$atividades = $this->doctrine->em->getRepository("Entity\Atividade")
			->findBy(array("idProjeto" => $id), array("dataCadastro" => "asc"));
		foreach ($atividades as $ativadade) {
			$data[] = [
				"id" => $ativadade->getId(),
				"data" => $ativadade->getDataCadastro(),
				"descricao" => $ativadade->getDescricao()
			];
		}
		echo json_encode($data);
	}

	public function get($id)
	{
		$data = [];
		$atividade = $this->doctrine->em->find("Entity\Atividade", $id);
		$data[] = [
			"id" => $atividade->getId(),
			"data" => $atividade->getDataCadastro(),
			"descricao" => $atividade->getDescricao()
		];
		echo json_encode($data);
	}

	public function index()
	{
		$data = [];
		$atividades = $this->doctrine->em->getRepository('Entity\Atividade')->findAll();
		foreach ($atividades as $atividade) {
			$data[] = [
				'id' => $atividade->getId(),
				'data' => $atividade->getDataCadastro(),
				'descricao' => $atividade->getDescricao()
			];
		}
		echo json_encode($data);
	}

	public function show($id) {}

	public function store() {}

	public function update($id) {}

	public function delete($id) {}
}
