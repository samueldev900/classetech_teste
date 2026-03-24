# Guia Técnico — classetech_teste

> **Objetivo:** Guia completo para entender, explicar e defender cada decisão técnica desta solução em uma entrevista. Cobre arquitetura, backend PHP + Doctrine ORM, frontend React, banco de dados, API REST, testes e melhorias possíveis.

---

## Sumário

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Banco de Dados e Schema](#2-banco-de-dados-e-schema)
3. [Backend PHP — CodeIgniter 3](#3-backend-php--codeigniter-3)
4. [Doctrine ORM — Integração e Uso](#4-doctrine-orm--integração-e-uso)
5. [Entidades Doctrine](#5-entidades-doctrine)
6. [Controllers e Lógica de Negócio](#6-controllers-e-lógica-de-negócio)
7. [API REST — Endpoints e Respostas](#7-api-rest--endpoints-e-respostas)
8. [Frontend React + Vite](#8-frontend-react--vite)
9. [Gerenciamento de Estado e Hooks](#9-gerenciamento-de-estado-e-hooks)
10. [Comunicação Frontend → Backend](#10-comunicação-frontend--backend)
11. [Considerações de Segurança](#11-considerações-de-segurança)
12. [Configuração e Variáveis de Ambiente](#12-configuração-e-variáveis-de-ambiente)
13. [Como Executar o Projeto](#13-como-executar-o-projeto)
14. [Testes](#14-testes)
15. [Melhorias e Débitos Técnicos](#15-melhorias-e-débitos-técnicos)
16. [Perguntas Frequentes de Entrevista](#16-perguntas-frequentes-de-entrevista)

---

## 1. Visão Geral da Arquitetura

```
┌─────────────────────────────────────────────────────────┐
│                    NAVEGADOR                            │
│  React 18 SPA (Vite 6)  ← porta 5173 (dev)             │
│       ↕ fetch('/api/...')                               │
│  Proxy Vite → http://localhost:8080                     │
└─────────────────────────────────────────────────────────┘
              ↕ HTTP/REST JSON
┌─────────────────────────────────────────────────────────┐
│              PHP 7+ (CodeIgniter 3)                     │
│   Controllers → Doctrine EntityManager → MySQL          │
│              porta 8080 (dev)                           │
└─────────────────────────────────────────────────────────┘
              ↕ SQL (PDO/mysqli)
┌─────────────────────────────────────────────────────────┐
│              MySQL 5.5+                                 │
│   Tabelas: projeto, atividade                           │
└─────────────────────────────────────────────────────────┘
```

**Stack tecnológica:**

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| Frontend | React | 18.3.x |
| Bundler | Vite | 6.2.x |
| Backend | PHP + CodeIgniter 3 | PHP ≥ 5.6 |
| ORM | Doctrine ORM | 2.x |
| Banco de dados | MySQL | 5.5+ |
| Estilização | CSS puro (sem framework) | — |

**Por que essa stack?**
- **CodeIgniter 3** é um framework PHP leve, sem magia excessiva, fácil de configurar e muito usado em projetos legados — ideal para demonstrar domínio de fundamentos PHP.
- **Doctrine 2** é o ORM mais popular e robusto do ecossistema PHP, usado em Symfony, e demonstra conhecimento de padrões como *Data Mapper* e *Unit of Work*.
- **React** com hooks é o padrão atual do mercado para interfaces reativas, sem a complexidade desnecessária de um framework maior.
- **Vite** é o bundler moderno mais rápido para desenvolvimento React, com suporte nativo a ESModules.

---

## 2. Banco de Dados e Schema

### Schema SQL (`banco.sql`)

```sql
CREATE TABLE `projeto` (
  `id`        BIGINT(20) NOT NULL AUTO_INCREMENT,
  `descricao` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `atividade` (
  `id`           BIGINT(20)  NOT NULL AUTO_INCREMENT,
  `dataCadastro` DATETIME    DEFAULT CURRENT_TIMESTAMP,
  `descricao`    VARCHAR(255) DEFAULT NULL,
  `idProjeto`    BIGINT(20)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idxprojeto` (`idProjeto`),
  CONSTRAINT `idxprojeto`
    FOREIGN KEY (`idProjeto`) REFERENCES `projeto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

### Diagrama de Relacionamento (ER)

```
┌──────────┐        ┌──────────────┐
│  projeto │        │   atividade  │
├──────────┤        ├──────────────┤
│ id  (PK) │◄───┐   │ id  (PK)     │
│ descricao│    └── │ idProjeto(FK)│
└──────────┘        │ descricao    │
                    │ dataCadastro │
                    └──────────────┘
```

**Cardinalidade:** Um projeto pode ter **muitas** atividades. Uma atividade pertence a **um único** projeto (N:1 / ManyToOne).

**Pontos importantes:**
- `BIGINT AUTO_INCREMENT` — escala para grandes volumes de registros.
- `FOREIGN KEY` com índice `idxprojeto` — garante integridade referencial no banco e melhora performance em JOINs/queries por projeto.
- `dataCadastro DEFAULT CURRENT_TIMESTAMP` — o banco define a data automaticamente se o backend não informar.
- `ENGINE=InnoDB` — suporte a transações ACID e foreign keys (MyISAM não suporta FK).
- **Atenção:** o charset `latin1` no SQL original pode causar problemas com caracteres especiais. Em produção, usar `utf8mb4`.

---

## 3. Backend PHP — CodeIgniter 3

### Estrutura de diretórios relevante

```
application/
├── config/
│   ├── database.php   ← credenciais do banco (lê do .env se configurado)
│   ├── routes.php     ← mapeamento HTTP verb + URI → controller/método
│   └── config.php     ← base_url e outras configurações globais
├── controllers/
│   ├── MY_Controller.php  ← base com helpers JSON
│   ├── Atividades.php     ← CRUD completo de atividades
│   ├── Projeto.php        ← listagem de projetos
│   └── Principal.php      ← seed de dados de exemplo
├── core/
│   └── MY_Controller.php  ← sobreposição do CI_Controller base
├── models/Entity/
│   ├── Atividade.php      ← Entidade Doctrine
│   └── Projeto.php        ← Entidade Doctrine
└── libraries/
    └── Doctrine.php       ← integração do Doctrine no CI
```

### Ciclo de vida de uma requisição

```
HTTP Request
   ↓
index.php (entry point)
   ↓
CodeIgniter Router (routes.php)
   ↓
Controller::método($params)
   ↓
Doctrine EntityManager (CRUD)
   ↓
MySQL
   ↓
Serialização → json_encode
   ↓
HTTP Response (JSON)
```

### Roteamento (`routes.php`)

O CI3 suporta rotas com verbo HTTP explícito:

```php
// Sintaxe: $route['URI']['VERBO_HTTP'] = 'controller/metodo';
$route['atividades']['get']            = 'atividades/index';
$route['atividades']['post']           = 'atividades/store';
$route['atividades/(:num)']['get']     = 'atividades/show/$1';
$route['atividades/(:num)']['put']     = 'atividades/update/$1';
$route['atividades/(:num)']['patch']   = 'atividades/update/$1';
$route['atividades/(:num)']['delete']  = 'atividades/delete/$1';
$route['projetos']['get']              = 'projeto/index';
$route['projetos/(:num)/atividades']['get'] = 'atividades/projeto/$1';
```

- `(:num)` é um wildcard que aceita somente números, capturado como `$1`.
- O `.htaccess` redireciona todas as requisições para `index.php`, permitindo URLs limpas.

---

## 4. Doctrine ORM — Integração e Uso

### O que é Doctrine ORM?

Doctrine é um ORM (*Object-Relational Mapper*) para PHP baseado no padrão **Data Mapper** (diferente do *Active Record* do Eloquent/Laravel). Ele separa completamente a lógica de negócio das entidades da lógica de persistência.

### Padrões implementados

| Padrão | Descrição |
|--------|-----------|
| **Data Mapper** | Entidades não conhecem o banco. O `EntityManager` é responsável por salvar/carregar. |
| **Unit of Work** | O EM rastreia todas as mudanças nas entidades e envia ao banco em uma única transação no `flush()`. |
| **Identity Map** | O EM garante que cada entidade com o mesmo ID seja representada por um único objeto em memória. |
| **Repository** | Abstração de consultas; `getRepository('Entity\Atividade')` retorna um repositório para queries. |

### Inicialização no CodeIgniter (`libraries/Doctrine.php`)

```php
// 1. Setup de configuração (modo dev = sem cache de metadata)
$config = Setup::createAnnotationMetadataConfiguration(
    $metadata_paths,  // onde ficam as entidades
    $dev_mode,        // true = desabilita cache
    $proxies_dir      // onde gerar proxies
);

// 2. Criação do EntityManager — ponto central do Doctrine
$this->em = EntityManager::create($connection_options, $config);

// 3. Registro do autoloader para namespace Entity\
$loader = new ClassLoader('Entity', APPPATH . 'models');
$loader->register();
```

### Uso do EntityManager nos controllers

```php
// Buscar por PK
$atividade = $this->doctrine->em->find('Entity\Atividade', $id);

// Buscar com critérios
$atividades = $this->doctrine->em->getRepository('Entity\Atividade')
    ->findBy(['idProjeto' => $projeto], ['dataCadastro' => 'asc']);

// Buscar todos
$atividades = $this->doctrine->em->getRepository('Entity\Atividade')->findAll();

// Criar / persistir
$atividade = new Entity\Atividade;
$atividade->setDescricao('Nova tarefa');
$atividade->setIdProjeto($projeto);
$atividade->setDataCadastro(date('Y-m-d H:i:s'));
$this->doctrine->em->persist($atividade); // registra para inserção
$this->doctrine->em->flush();             // executa o INSERT

// Atualizar (entity já rastreada)
$atividade->setDescricao('Tarefa atualizada');
$this->doctrine->em->flush(); // executa o UPDATE (sem persist!)

// Remover
$this->doctrine->em->remove($atividade);
$this->doctrine->em->flush(); // executa o DELETE
```

**Por que `flush()` e não `persist()` para update?**
> O Doctrine rastreia automaticamente qualquer entidade carregada via `find()` ou `findBy()`. Quando você altera uma propriedade, ele detecta a mudança (*dirty checking*) e inclui um UPDATE no próximo `flush()`. O `persist()` só é necessário para objetos **novos** que ainda não existem no banco.

### Proxies

O Doctrine gera classes Proxy em `application/models/Proxies/` para implementar **lazy loading**. Isso permite que relações não sejam carregadas do banco até o momento em que são acessadas (`$atividade->getIdProjeto()`).

---

## 5. Entidades Doctrine

### `Entity\Projeto`

```php
/**
 * @Entity
 * @Table(name="projeto")
 */
class Projeto {
    /** @Id @Column(type="integer") @GeneratedValue(strategy="IDENTITY") */
    public $id;

    /** @Column(name="descricao", type="string", length=255) */
    public $descricao;

    // getters/setters...
}
```

### `Entity\Atividade`

```php
/**
 * @Entity
 * @Table(name="atividade")
 */
class Atividade {
    /** @Id @Column(type="integer") @GeneratedValue(strategy="IDENTITY") */
    public $id;

    /** @Column(name="dataCadastro", type="string") */
    public $dataCadastro;

    /**
     * @ManyToOne(targetEntity="Projeto")
     * @JoinColumn(name="idProjeto", referencedColumnName="id")
     */
    public $idProjeto;

    /** @Column(name="descricao", type="string", length=255) */
    public $descricao;

    // getters/setters...
}
```

**Anotações importantes:**

| Anotação | Significado |
|----------|-------------|
| `@Entity` | Marca a classe como entidade Doctrine |
| `@Table(name="...")` | Mapeia para a tabela especificada |
| `@Id` | Define a chave primária |
| `@GeneratedValue(strategy="IDENTITY")` | Auto-increment no banco (BIGINT AUTO_INCREMENT) |
| `@Column(type="string", length=255)` | Mapeia para coluna VARCHAR(255) |
| `@ManyToOne(targetEntity="Projeto")` | Relacionamento N:1 com Projeto |
| `@JoinColumn(name="idProjeto", ...)` | Define a coluna FK na tabela atividade |

**Nota sobre `@Column(type="string")` para data:**
O campo `dataCadastro` usa `type="string"` (não `datetime`). Isso significa que o Doctrine armazena/recupera o valor como string bruta, sem conversão de tipo. Isso é intencional para evitar problemas de fuso horário e formatos de data entre PHP e MySQL.

---

## 6. Controllers e Lógica de Negócio

### `MY_Controller` — Base

```php
class MY_Controller extends CI_Controller {
    function __construct() {
        parent::__construct();
        header('Content-Type: application/json'); // toda resposta é JSON
    }

    // Resposta de sucesso
    protected function response($message, $status, $data) {
        set_status_header($status);
        echo json_encode(['message' => $message, 'status' => $status, 'data' => $data]);
    }

    // Resposta de erro
    protected function error($message, $status, $errors, $data) {
        set_status_header($status);
        echo json_encode(['message' => $message, 'status' => $status, 'errors' => $errors, 'data' => $data]);
    }
}
```

### `Atividades` — Métodos privados de suporte

#### `getPayload()` — Leitura do corpo da requisição

```php
private function getPayload() {
    // 1. Tenta form-data (application/x-www-form-urlencoded)
    $payload = $this->input->post(NULL, TRUE);
    if (is_array($payload) && !empty($payload)) return $payload;

    // 2. Tenta JSON bruto (application/json)
    $raw_input = trim($this->input->raw_input_stream);
    if ($raw_input === '') return [];
    $decoded = json_decode($raw_input, TRUE);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;

    // 3. Fallback: query string format
    parse_str($raw_input, $parsed);
    return is_array($parsed) ? $parsed : [];
}
```

> **Por que três tentativas?** O PHP não lê automaticamente o corpo de requisições PUT/PATCH via `$_POST`. É necessário ler `php://input` manualmente. Suportar múltiplos formatos (JSON e form-data) torna a API mais flexível para diferentes clientes.

#### `normalizePayload()` — Normalização de campos

```php
private function normalizePayload($payload) {
    // Aceita 'data' como alias de 'dataCadastro'
    if (isset($payload['data']) && !isset($payload['dataCadastro'])) {
        $payload['dataCadastro'] = $payload['data'];
    }
    return $payload;
}
```

#### `validatePayload()` — Validação

```php
private function validatePayload($payload, $is_update) {
    $errors = [];

    // Para update: ao menos um campo deve ser enviado
    if ($is_update && empty(array_intersect(array_keys($payload), ['descricao', 'idProjeto', 'dataCadastro', 'data']))) {
        $errors[] = 'Informe ao menos um campo...';
    }

    // Para criação: campos obrigatórios
    if (!$is_update) {
        if (!array_key_exists('descricao', $payload)) $errors[] = '...';
        if (!array_key_exists('idProjeto', $payload)) $errors[] = '...';
    }

    // Validações de tipo e formato
    if (array_key_exists('descricao', $payload) && trim($payload['descricao']) === '')
        $errors[] = 'descricao não pode ser vazio.';
    if (array_key_exists('idProjeto', $payload) && (!is_numeric($payload['idProjeto']) || $payload['idProjeto'] <= 0))
        $errors[] = 'idProjeto deve ser inteiro positivo.';

    return $errors;
}
```

#### `serializeAtividade()` — Serialização para JSON

```php
private function serializeAtividade($atividade) {
    $projeto = $atividade->getIdProjeto(); // lazy-loaded proxy
    return [
        'id'         => (int) $atividade->getId(),
        'projeto_id' => $projeto ? (int) $projeto->getId() : NULL,
        'data'       => $atividade->getDataCadastro(),
        'descricao'  => $atividade->getDescricao(),
        'created_at' => $atividade->getDataCadastro(), // alias
    ];
}
```

> **Por que serializar manualmente e não usar `json_encode` direto?** Entidades Doctrine têm propriedades privadas, métodos de proxy e referências circulares que não são serializáveis diretamente. A serialização manual dá controle total sobre o formato de saída.

### Fluxo de CRUD — `store()` (criação)

```
POST /atividades  { "descricao": "...", "idProjeto": 1 }
  ↓
getPayload()         → lê JSON do corpo
normalizePayload()   → normaliza campos
validatePayload()    → valida campos obrigatórios
findProjeto($id)     → verifica se projeto existe (404 se não)
new Entity\Atividade → instancia entidade
setters              → preenche campos
em->persist()        → registra para inserção
em->flush()          → INSERT no banco (try/catch 500)
serializeAtividade() → converte para array
response(201, data)  → retorna JSON criado
```

### Fluxo de `update()` (atualização parcial)

```
PUT /atividades/1  { "descricao": "Nova desc" }
  ↓
findAtividade($id)   → 404 se não encontrar
getPayload()         → lê corpo
validatePayload(is_update=true) → valida
// Aplica apenas campos enviados (PATCH parcial)
if (isset descricao)  → setDescricao()
if (isset dataCadastro) → setDataCadastro()
if (isset idProjeto)  → findProjeto() + setIdProjeto()
em->flush()          → UPDATE no banco
response(200, data)  → retorna JSON atualizado
```

---

## 7. API REST — Endpoints e Respostas

### Tabela de Endpoints

| Método | URI | Controller::Método | Descrição |
|--------|-----|-------------------|-----------|
| GET | `/atividades` | `Atividades::index` | Lista todas as atividades |
| GET | `/atividades/{id}` | `Atividades::show` | Busca atividade por ID |
| POST | `/atividades` | `Atividades::store` | Cria nova atividade |
| PUT/PATCH | `/atividades/{id}` | `Atividades::update` | Atualiza atividade |
| DELETE | `/atividades/{id}` | `Atividades::delete` | Remove atividade |
| GET | `/projetos` | `Projeto::index` | Lista todos os projetos |
| GET | `/projetos/{id}/atividades` | `Atividades::projeto` | Atividades de um projeto |
| GET | `/principal/povoar` | `Principal::povoar` | Seed de dados (dev only) |

### Formato de Resposta (Sucesso)

```json
{
  "message": "Atividade criada com sucesso.",
  "status": 201,
  "data": {
    "id": 1,
    "projeto_id": 2,
    "data": "2024-01-15 10:30:00",
    "descricao": "Implementar tela inicial",
    "created_at": "2024-01-15 10:30:00"
  }
}
```

### Formato de Resposta (Erro)

```json
{
  "message": "Dados inválidos.",
  "status": 400,
  "errors": [
    "O campo descricao é obrigatório.",
    "O campo idProjeto é obrigatório."
  ],
  "data": []
}
```

### Códigos HTTP utilizados

| Código | Situação |
|--------|----------|
| 200 | Sucesso geral (GET, PUT, DELETE) |
| 201 | Recurso criado (POST) |
| 400 | Dados inválidos (validação falhou) |
| 404 | Recurso não encontrado |
| 500 | Erro interno (falha de persistência) |

### Exemplos de uso com cURL

```bash
# Listar atividades
curl http://localhost:8080/atividades

# Buscar atividade por ID
curl http://localhost:8080/atividades/1

# Criar atividade
curl -X POST http://localhost:8080/atividades \
  -H "Content-Type: application/json" \
  -d '{"descricao": "Nova tarefa", "idProjeto": 1}'

# Atualizar atividade
curl -X PUT http://localhost:8080/atividades/1 \
  -H "Content-Type: application/json" \
  -d '{"descricao": "Tarefa atualizada"}'

# Deletar atividade
curl -X DELETE http://localhost:8080/atividades/1

# Atividades de um projeto
curl http://localhost:8080/projetos/1/atividades

# Seed de dados
curl http://localhost:8080/principal/povoar
```

---

## 8. Frontend React + Vite

### Estrutura de arquivos

```
frontend/
├── src/
│   ├── App.jsx       ← componente principal (toda a lógica)
│   ├── main.jsx      ← ponto de entrada React
│   └── styles.css    ← estilos globais (CSS puro)
├── index.html        ← template HTML do Vite
├── vite.config.js    ← configuração Vite + proxy de dev
├── package.json      ← dependências npm
└── .env.example      ← variáveis de ambiente
```

### Dependências

```json
{
  "dependencies": {
    "react": "^18.3.1",     // biblioteca principal
    "react-dom": "^18.3.1"  // renderização no DOM
  },
  "devDependencies": {
    "@vitejs/plugin-react": "^4.4.1",  // suporte a JSX/Fast Refresh
    "vite": "^6.2.2"                   // bundler/dev server
  }
}
```

> **Por que tão poucas dependências?** A aplicação é pequena e focada. Sem React Router (SPA de uma página), sem Redux (estado local suficiente), sem bibliotecas de UI (design próprio simples). Menos dependências = menos vulnerabilidades e manutenção.

### Configuração Vite (`vite.config.js`)

```js
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.VITE_DEV_API_TARGET || 'http://localhost:8080',
        rewrite: (path) => path.replace(/^\/api/, VITE_DEV_API_PREFIX || ''),
        changeOrigin: true,
      }
    }
  }
});
```

**Como o proxy funciona:**
- Requisições para `/api/atividades` no frontend → `http://localhost:8080/atividades` no backend
- O prefixo `/api` é removido no rewrite
- `changeOrigin: true` altera o header `Host` para o target, evitando problemas de CORS em dev

---

## 9. Gerenciamento de Estado e Hooks

O componente `App` usa exclusivamente o hook `useState` para gerenciar 7 estados:

```jsx
const [atividades, setAtividades]         = useState([]);    // lista de atividades
const [form, setForm]                     = useState(initialForm); // dados do formulário
const [editingId, setEditingId]           = useState(null);  // ID sendo editado
const [loading, setLoading]               = useState(true);  // carregamento da lista
const [submitting, setSubmitting]         = useState(false); // submit do formulário
const [feedback, setFeedback]             = useState(null);  // mensagem sucesso/erro
const [filterActivityId, setFilterActivityId] = useState(''); // filtro por ID
const [projetos, setProjetos]             = useState([]);    // opções do select
```

### `useEffect` — Carregamento inicial

```jsx
useEffect(() => {
  loadProjetos();   // carrega projetos para o <select>
  loadAtividades(); // carrega lista inicial
}, []); // [] = executa apenas uma vez ao montar
```

### Padrão de edição (formulário reutilizável)

O mesmo formulário é usado para criar e editar:
- `editingId === null` → criação (POST `/atividades`)
- `editingId !== null` → edição (PUT `/atividades/{editingId}`)

```jsx
function handleEdit(atividade) {
  setEditingId(atividade.id); // ativa modo edição
  setForm({
    descricao: atividade.descricao || '',
    idProjeto: atividade.projeto_id ? String(atividade.projeto_id) : '',
  });
}
```

### `handleSubmit` — lógica unificada

```jsx
async function handleSubmit(event) {
  event.preventDefault();
  setSubmitting(true);
  try {
    const payload = mapFormToPayload(form);
    if (editingId) {
      await request(`/atividades/${editingId}`, { method: 'PUT', body: JSON.stringify(payload) });
    } else {
      await request('/atividades', { method: 'POST', body: JSON.stringify(payload) });
    }
    resetForm();
    await loadAtividades(); // recarrega lista após salvar
  } catch (error) {
    // Exibe erros de validação ou mensagem genérica
    const details = error.payload?.errors;
    setFeedback({ type: 'error', message: details?.join(' ') || error.message });
  } finally {
    setSubmitting(false);
  }
}
```

### Filtro por ID de atividade

```jsx
async function handleFilterSubmit(event) {
  event.preventDefault();
  const id = filterActivityId.trim();
  if (!id) { await loadAtividades(); return; } // sem ID = recarrega tudo
  // Busca atividade específica e exibe como lista de um elemento
  const result = await request(`/atividades/${id}`);
  setAtividades(result.data ? [result.data] : []);
}
```

---

## 10. Comunicação Frontend → Backend

### Função `request()` — cliente HTTP centralizado

```jsx
const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '');

async function request(path, options = {}) {
  const response = await fetch(buildUrl(path), {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });

  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok) {
    const error = new Error(payload.message || 'Falha na requisição.');
    error.payload = payload; // inclui body completo no erro
    throw error;
  }

  return payload;
}
```

**Decisões de design:**
1. **`response.text()` antes de `JSON.parse()`** — evita erros quando a resposta está vazia (ex.: DELETE sem corpo).
2. **`error.payload = payload`** — permite que o `catch` acesse detalhes do erro (ex.: lista de erros de validação).
3. **Headers explícitos** — `Content-Type: application/json` garante que o PHP faça parsing correto do corpo.

### `formatDate()` — exibição de datas

```jsx
function formatDate(value) {
  if (!value) return '-';
  const clean = value.replace('T', ' '); // normaliza ISO 8601
  const date = new Date(clean);
  if (isNaN(date.getTime())) return clean; // fallback: exibe string bruta
  return date.toLocaleDateString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}
```

### Resolução do nome do projeto na tabela

```jsx
{(() => {
  const p = projetos.find((proj) => proj.id === atividade.projeto_id);
  return p ? p.descricao : (atividade.projeto_id || '-');
})()}
```

> A API retorna apenas `projeto_id` (número). O nome é resolvido localmente fazendo lookup no array `projetos` já carregado em memória — evita requisição extra por linha da tabela.

---

## 11. Considerações de Segurança

### Vulnerabilidades identificadas

#### 1. Credenciais hardcoded (CORRIGIDO neste PR)

**Problema:** A senha do banco estava hardcoded em `application/config/database.php`.
```php
// ❌ Antes
'password' => '86375297',
```

**Solução:** Usar variáveis de ambiente via `$_ENV` e arquivo `.env`.
```php
// ✅ Depois
'password' => $_ENV['DB_PASSWORD'] ?? '',
```

**Como configurar:** Copiar `.env.example` para `.env` e preencher os valores.

#### 2. Ausência de autenticação

**Problema:** Todos os endpoints são públicos. Qualquer um pode criar/editar/deletar dados.
**Solução ideal:** Implementar JWT (JSON Web Tokens) ou sessões PHP, com middleware de autenticação no `MY_Controller`.

#### 3. Sem proteção CSRF

**Problema:** Formulários enviados via fetch não têm proteção CSRF.
**Solução:** Em APIs stateless com JWT, CSRF não é necessário. Para sessões, usar token CSRF em header.

#### 4. CORS não configurado para produção

**Problema:** O proxy Vite só funciona em desenvolvimento. Em produção, o backend precisa definir headers CORS.
**Solução:** Adicionar no `MY_Controller`:
```php
header('Access-Control-Allow-Origin: https://seu-dominio.com');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
```

#### 5. Sem rate limiting

**Problema:** Endpoint `/principal/povoar` pode ser chamado repetidamente, criando dados desnecessários.
**Solução:** Proteger por autenticação ou remover em produção.

#### 6. `db_debug` habilitado em produção (potencial)

**Problema:** Se `ENVIRONMENT` não for `production`, erros de banco são exibidos ao cliente.
**Solução:** Garantir que `ENVIRONMENT=production` no servidor de produção.

### Aspectos positivos de segurança

- ✅ Validação de tipo nos IDs (`is_numeric` e `> 0`)
- ✅ `trim()` em campos de texto antes de salvar
- ✅ HTTP method routing (não aceita verbo errado)
- ✅ Doctrine usa Prepared Statements nativamente (sem SQL injection)
- ✅ Frontend usa `window.confirm()` antes de deletar

---

## 12. Configuração e Variáveis de Ambiente

### Backend (PHP) — `.env` e `database.php`

Copie `.env.example` para `.env` e configure:

```env
DB_HOSTNAME=localhost
DB_USERNAME=root
DB_PASSWORD=sua_senha_aqui
DB_DATABASE=classetech_teste
DB_CHARSET=utf8
```

### Frontend (React/Vite) — `frontend/.env`

```env
VITE_API_BASE_URL=/api
VITE_DEV_API_TARGET=http://localhost:8080
VITE_DEV_API_PREFIX=
```

**Como o Vite expõe variáveis:** Apenas variáveis com prefixo `VITE_` são expostas ao código do navegador via `import.meta.env.VITE_NOME`. Sem esse prefixo, a variável não fica visível no bundle.

---

## 13. Como Executar o Projeto

### Pré-requisitos

- PHP ≥ 5.6 com extensões `mysqli` e `json`
- MySQL ≥ 5.5
- Apache com `mod_rewrite` habilitado (ou `php -S` para desenvolvimento)
- Node.js ≥ 18
- npm ≥ 9

### 1. Banco de Dados

```bash
# Criar banco
mysql -u root -p -e "CREATE DATABASE classetech_teste CHARACTER SET utf8 COLLATE utf8_general_ci;"

# Importar schema
mysql -u root -p classetech_teste < banco.sql
```

### 2. Backend

```bash
# Configurar credenciais
cp .env.example .env
# editar .env com suas credenciais

# Iniciar servidor (porta 8080)
php -S localhost:8080

# Seed de dados de exemplo
curl http://localhost:8080/principal/povoar
```

### 3. Frontend

```bash
cd frontend

# Instalar dependências
npm install

# Iniciar dev server (porta 5173)
npm run dev

# Build de produção
npm run build  # gera frontend/dist/
```

Acesse: **http://localhost:5173**

---

## 14. Testes

### Infraestrutura existente

O projeto inclui infraestrutura PHPUnit em `tests/` baseada no framework de testes do CodeIgniter:

```
tests/
├── Bootstrap.php        ← configura ambiente de teste
├── mocks/               ← mocks de database, libraries, CI components
└── README.md            ← guia completo de como escrever testes
```

### Como executar

```bash
cd tests
phpunit
```

### Exemplo de teste unitário para `Atividades`

```php
class AtividadesTest extends CI_TestCase {
    public function setUp() {
        parent::setUp();
        $this->ci->load->library('doctrine');
    }

    public function test_serializeAtividade_retorna_campos_corretos() {
        $projeto = new Entity\Projeto();
        $projeto->id = 1;

        $atividade = new Entity\Atividade();
        $atividade->id = 10;
        $atividade->descricao = 'Teste';
        $atividade->dataCadastro = '2024-01-01 12:00:00';
        $atividade->idProjeto = $projeto;

        // ... assert campos serializados
    }
}
```

### Cobertura atual

- Infraestrutura de testes: ✅ configurada
- Testes unitários de controllers: ❌ não implementados
- Testes de integração/API: ❌ não implementados

---

## 15. Melhorias e Débitos Técnicos

### Prioridade Alta

| Item | Justificativa |
|------|--------------|
| Autenticação (JWT) | Endpoints públicos em produção são um risco |
| Testes automatizados | Nenhum teste de controller ou integração |
| CORS para produção | Frontend e backend em domínios diferentes |
| Charset `utf8mb4` no banco | `latin1` causa problemas com emojis e acentos |

### Prioridade Média

| Item | Justificativa |
|------|--------------|
| Paginação na listagem | Com muitas atividades, `findAll()` pode ser lento |
| Cache de metadata Doctrine | Em produção, `$dev_mode = false` com APCu/Redis |
| Mensagens de erro sem acentos no backend | `"nao encontrada"` em vez de `"não encontrada"` |
| React Query / SWR | Melhor gestão de cache e loading states |

### Prioridade Baixa

| Item | Justificativa |
|------|--------------|
| TypeScript no frontend | Maior segurança de tipos em escala |
| Paginação no frontend | UX melhor com muitos registros |
| Log de erros estruturado | Facilita debugging em produção |
| Atualização de dependências | CodeIgniter 3 e PHP 5.3 estão em EOL |

---

## 16. Perguntas Frequentes de Entrevista

### Sobre Doctrine ORM

**Q: O que é o padrão Data Mapper e como o Doctrine o implementa?**
> O Data Mapper separa a lógica de negócio das entidades da lógica de persistência. As entidades são POPOs (Plain Old PHP Objects) que não conhecem o banco. O `EntityManager` é responsável por traduzir operações nas entidades para SQL. Isso facilita testes (entidades podem ser testadas sem banco) e mantém o código mais limpo.

**Q: Qual a diferença entre `persist()` e `flush()`?**
> `persist()` apenas registra uma entidade nova no contexto do `EntityManager` (não vai ao banco). `flush()` sincroniza todas as mudanças pendentes (inserts, updates, deletes) com o banco em uma única transação. Para entidades já rastreadas (carregadas via `find()`), `persist()` não é necessário — só `flush()`.

**Q: O que é a "Unit of Work" no Doctrine?**
> É o mecanismo que rastreia todas as entidades carregadas e suas mudanças. Ao chamar `flush()`, o Doctrine calcula o diff entre o estado original e o atual e gera apenas os SQLs necessários.

**Q: O que são Proxies no Doctrine e para que servem?**
> São classes geradas automaticamente que estendem suas entidades. Permitem lazy loading: quando você acessa `$atividade->getIdProjeto()`, se o projeto não foi carregado junto, o Proxy dispara uma query `SELECT` automaticamente naquele momento.

**Q: Por que o campo `dataCadastro` é `type="string"` e não `type="datetime"`?**
> Para evitar conversões automáticas de fuso horário e problemas de formato. Com `type="string"`, o valor é armazenado e recuperado exatamente como está, sem que o Doctrine converta para objeto `DateTime` PHP.

### Sobre CodeIgniter

**Q: Como funciona o roteamento com verbos HTTP no CI3?**
> O CI3 suporta routing com verbos HTTP através de arrays na chave da rota: `$route['uri']['get'] = 'controller/metodo'`. O framework verifica o método HTTP da requisição e roteia para o método correto. Wildcards como `(:num)` capturam segmentos numéricos como parâmetros.

**Q: Por que usar `MY_Controller` em vez de `CI_Controller` diretamente?**
> É o padrão recomendado pelo CI para sobrescrever a classe base. Qualquer controller que herda de `MY_Controller` herda automaticamente o header `Content-Type: application/json` e os métodos `response()` e `error()`, evitando repetição de código.

**Q: Por que ler o corpo da requisição manualmente com `raw_input_stream`?**
> O PHP popula `$_POST` apenas para requisições `application/x-www-form-urlencoded` com método POST. Para PUT, PATCH, e para `application/json`, é necessário ler `php://input` (ou `$this->input->raw_input_stream` no CI) e parsear manualmente.

### Sobre React

**Q: Por que usar apenas `useState` e não `useReducer` ou Redux?**
> Para uma aplicação com um único componente e lógica simples de CRUD, `useState` é suficiente. `useReducer` seria mais adequado se houvesse transições de estado complexas ou interdependências entre múltiplos estados. Redux adicionaria boilerplate desnecessário.

**Q: Por que o formulário de criação e edição são o mesmo componente?**
> Reduz duplicação de código. A lógica de validação, feedback e reset é compartilhada. A variável `editingId` controla se a submissão chama POST (criação) ou PUT (atualização).

**Q: Por que `window.scrollTo({ top: 0, behavior: 'smooth' })` no `handleEdit`?**
> Em mobile e telas pequenas, o formulário fica no topo da página. Ao clicar em "Editar" na tabela (abaixo), o usuário precisa ver o formulário preenchido. O scroll suave melhora a UX.

**Q: Como o nome do projeto é exibido na tabela sem fazer uma requisição adicional?**
> Os projetos são carregados uma vez no `useEffect` inicial e armazenados em `projetos`. Na renderização da tabela, usa-se `projetos.find(p => p.id === atividade.projeto_id)` para resolver o nome localmente, sem chamada de rede extra por linha.

### Sobre Arquitetura Geral

**Q: Por que separar frontend e backend em vez de usar um framework full-stack?**
> Permite escalar e deployar independentemente. O frontend pode ser servido via CDN enquanto o backend roda em servidor PHP. Também facilita a substituição de qualquer camada sem afetar a outra.

**Q: Como você resolveria o problema de CORS em produção?**
> Adicionaria headers CORS no `MY_Controller` (ou via Apache/Nginx), limitando `Allow-Origin` ao domínio do frontend. Para preflight OPTIONS, responderia com 200 sem processar a lógica de negócio.

**Q: Como você implementaria paginação nessa API?**
> Adicionaria parâmetros de query `?page=1&per_page=10` no endpoint `GET /atividades`. No Doctrine, usaria `setFirstResult((page-1)*per_page)->setMaxResults(per_page)` no QueryBuilder. A resposta incluiria metadados `{total, page, per_page, data}`.

**Q: O que mudaria para tornar esse projeto production-ready?**
> 1. Autenticação JWT; 2. HTTPS; 3. CORS configurado; 4. Rate limiting; 5. Paginação; 6. Logs estruturados; 7. Charset `utf8mb4`; 8. Variáveis de ambiente (já feito); 9. Disable `dev_mode` no Doctrine; 10. Testes automatizados.

---

*Guia gerado com base na análise completa do código-fonte do repositório `classetech_teste`.*
