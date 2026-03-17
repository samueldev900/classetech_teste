<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

class Atividade extends MY_Controller
{
	public $doctrine;

	function __construct()
	{
		parent::__construct();
		if (! isset($this->doctrine)) {
			$this->load->library('doctrine');
		}
	}

	private function serializeAtividade($atividade)
	{
		$projeto = $atividade->getIdProjeto();

		return array(
			'id' => (int) $atividade->getId(),
			'projeto_id' => $projeto ? (int) $projeto->getId() : NULL,
			'data' => $atividade->getDataCadastro(),
			'descricao' => $atividade->getDescricao(),
			'created_at' => $atividade->getDataCadastro(),
		);
	}

	private function getPayload()
	{
		$payload = $this->input->post(NULL, TRUE);

		if (is_array($payload) && ! empty($payload)) {
			return $payload;
		}

		$raw_input = trim($this->input->raw_input_stream);

		if ($raw_input === '') {
			return array();
		}

		$decoded = json_decode($raw_input, TRUE);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
			return $decoded;
		}

		parse_str($raw_input, $parsed);

		return is_array($parsed) ? $parsed : array();
	}

	private function normalizePayload($payload)
	{
		if (isset($payload['data']) && ! isset($payload['dataCadastro'])) {
			$payload['dataCadastro'] = $payload['data'];
		}

		return $payload;
	}

	private function validatePayload($payload, $is_update)
	{
		$errors = array();
		$allowed_fields = array('descricao', 'idProjeto', 'dataCadastro', 'data');
		$provided_fields = array_intersect(array_keys($payload), $allowed_fields);

		if ($is_update && empty($provided_fields)) {
			$errors[] = 'Informe ao menos um dos campos: descricao, idProjeto ou dataCadastro.';
		}

		if (! $is_update) {
			if (! array_key_exists('descricao', $payload)) {
				$errors[] = 'O campo descricao e obrigatorio.';
			}

			if (! array_key_exists('idProjeto', $payload)) {
				$errors[] = 'O campo idProjeto e obrigatorio.';
			}
		}

		if (array_key_exists('descricao', $payload) && trim($payload['descricao']) === '') {
			$errors[] = 'O campo descricao nao pode ser vazio.';
		}

		if (array_key_exists('idProjeto', $payload)) {
			if (! is_numeric($payload['idProjeto']) || (int) $payload['idProjeto'] <= 0) {
				$errors[] = 'O campo idProjeto deve ser um numero inteiro valido.';
			}
		}

		if (array_key_exists('dataCadastro', $payload) && trim($payload['dataCadastro']) === '') {
			$errors[] = 'O campo dataCadastro nao pode ser vazio.';
		}

		return $errors;
	}

	private function findProjeto($id)
	{
		return $this->doctrine->em->find('Entity\Projeto', (int) $id);
	}

	private function findAtividade($id)
	{
		return $this->doctrine->em->find('Entity\Atividade', (int) $id);
	}

	public function projeto($id)
	{
		$projeto = $this->findProjeto($id);

		if (! $projeto) {
			return $this->error('Projeto nao encontrado.', 404, array(), array());
		}

		$data = array();
		$atividades = $this->doctrine->em->getRepository('Entity\Atividade')
			->findBy(array('idProjeto' => $projeto), array('dataCadastro' => 'asc'));

		foreach ($atividades as $atividade) {
			$data[] = $this->serializeAtividade($atividade);
		}

		$this->response('Atividades do projeto retornadas com sucesso.', 200, $data);
	}

	public function get($id)
	{
		return $this->show($id);
	}

	public function index()
	{
		$data = array();
		$atividades = $this->doctrine->em->getRepository('Entity\Atividade')->findAll();

		foreach ($atividades as $atividade) {
			$data[] = $this->serializeAtividade($atividade);
		}

		$this->response('Atividades retornadas com sucesso.', 200, $data);
	}

	public function show($id)
	{
		$atividade = $this->findAtividade($id);

		if (! $atividade) {
			return $this->error('Atividade nao encontrada.', 404, array(), array());
		}

		$this->response('Atividade retornada com sucesso.', 200, $this->serializeAtividade($atividade));
	}

	public function store()
	{
		$payload = $this->normalizePayload($this->getPayload());
		$errors = $this->validatePayload($payload, FALSE);

		if (! empty($errors)) {
			return $this->error('Dados invalidos.', 400, $errors, array());
		}

		$projeto = $this->findProjeto($payload['idProjeto']);
		if (! $projeto) {
			return $this->error('Projeto nao encontrado.', 404, array(), array());
		}

		$atividade = new Entity\Atividade;
		$atividade->setDescricao(trim($payload['descricao']));
		$atividade->setIdProjeto($projeto);
		$atividade->setDataCadastro(
			isset($payload['dataCadastro']) ? trim($payload['dataCadastro']) : date('Y-m-d H:i:s')
		);

		try {
			$this->doctrine->em->persist($atividade);
			$this->doctrine->em->flush();
		} catch (Exception $exception) {
			return $this->error('Nao foi possivel criar a atividade.', 500, array(), array());
		}

		$this->response('Atividade criada com sucesso.', 201, $this->serializeAtividade($atividade));
	}

	public function update($id)
	{
		$atividade = $this->findAtividade($id);

		if (! $atividade) {
			return $this->error('Atividade nao encontrada.', 404, array(), array());
		}

		$payload = $this->normalizePayload($this->getPayload());
		$errors = $this->validatePayload($payload, TRUE);

		if (! empty($errors)) {
			return $this->error('Dados invalidos.', 400, $errors, array());
		}

		if (array_key_exists('descricao', $payload)) {
			$atividade->setDescricao(trim($payload['descricao']));
		}

		if (array_key_exists('dataCadastro', $payload)) {
			$atividade->setDataCadastro(trim($payload['dataCadastro']));
		}

		if (array_key_exists('idProjeto', $payload)) {
			$projeto = $this->findProjeto($payload['idProjeto']);
			if (! $projeto) {
				return $this->error('Projeto nao encontrado.', 404, array(), array());
			}

			$atividade->setIdProjeto($projeto);
		}

		try {
			$this->doctrine->em->flush();
		} catch (Exception $exception) {
			return $this->error('Nao foi possivel atualizar a atividade.', 500, array(), array());
		}

		$this->response('Atividade atualizada com sucesso.', 200, $this->serializeAtividade($atividade));
	}

	public function delete($id)
	{
		$atividade = $this->findAtividade($id);

		if (! $atividade) {
			return $this->error('Atividade nao encontrada.', 404, array(), array());
		}

		try {
			$this->doctrine->em->remove($atividade);
			$this->doctrine->em->flush();
		} catch (Exception $exception) {
			return $this->error('Nao foi possivel remover a atividade.', 500, array(), array());
		}

		$this->response('Atividade removida com sucesso.', 200, array('id' => (int) $id));
	}
}
