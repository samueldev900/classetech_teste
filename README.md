# CRUD de Atividades — CodeIgniter 3 + Doctrine + React

Aplicação completa de gerenciamento de atividades com backend em **PHP (CodeIgniter 3 + Doctrine ORM)** e frontend em **React (Vite)**.

---

## Pré-requisitos

- **PHP** >= 5.6 com extensões `mysqli` e `json`
- **MySQL** >= 5.5
- **Apache** com `mod_rewrite` habilitado (ou servidor embutido do PHP)
- **Composer** (dependências PHP já estão em `vendor/`, mas pode rodar `composer install` se necessário)
- **Node.js** >= 18 e **npm** (para o frontend)

---

## 1. Banco de dados

Crie o banco e importe o schema:

```sql
CREATE DATABASE appteste CHARACTER SET utf8 COLLATE utf8_general_ci;
```

```bash
mysql -u root -p appteste < banco.sql
```

Configure suas credenciais em `application/config/database.php`:

```php
'hostname' => 'localhost',
'username' => 'root',
'password' => 'SUA_SENHA',
'database' => 'appteste',
```

---

## 2. Backend (CodeIgniter 3)

### Configuração

Em `application/config/config.php`, ajuste a `base_url` conforme seu ambiente:

```php
$config['base_url'] = 'http://localhost:8080/';
```

### Subindo o servidor

**Opção A — Servidor embutido do PHP:**

```bash
php -S localhost:8080
```

### Populando o banco (seed)

Acesse a rota abaixo para criar 1 projeto e 10 atividades de exemplo:

```
GET http://localhost:8080/principal/povoar
```

---

## 3. Frontend (React + Vite)

```bash
cd frontend
npm install
npm run dev
```

O frontend sobe em `http://localhost:5173` e faz proxy das chamadas `/api` para o backend.

### Variáveis de ambiente (opcional)

Crie um arquivo `frontend/.env` se o backend estiver em porta diferente de 8080:

```env
VITE_DEV_API_TARGET=http://localhost:8080
```

### Build de produção

```bash
npm run build
```

Os arquivos estáticos serão gerados em `frontend/dist/`.

---

## 4. Endpoints da API

Todos os endpoints retornam JSON no formato:

```json
{
  "message": "...",
  "status": 200,
  "data": { }
}
```

| Método   | Rota                            | Ação                          |
|----------|---------------------------------|-------------------------------|
| `GET`    | `/atividades`                   | Listar todas as atividades    |
| `GET`    | `/atividades/{id}`              | Buscar atividade por ID       |
| `POST`   | `/atividades`                   | Criar nova atividade          |
| `PUT`    | `/atividades/{id}`              | Atualizar atividade           |
| `DELETE` | `/atividades/{id}`              | Excluir atividade             |
| `GET`    | `/projetos`                     | Listar todos os projetos      |
| `GET`    | `/projetos/{id}/atividades`     | Atividades de um projeto      |
| `GET`    | `/principal/povoar`             | Popular banco com dados teste |

### Payload para criar/atualizar

```json
{
  "descricao": "Implementar tela inicial",
  "idProjeto": 1
}
```

---

## Estrutura do projeto

```
├── application/
│   ├── config/          # Configurações (database, routes, config)
│   ├── controllers/
│   │   ├── Atividades.php   # CRUD de atividades (API REST)
│   │   ├── Projeto.php      # Listagem de projetos
│   │   └── Principal.php    # Seed do banco
│   ├── core/
│   │   └── MY_Controller.php  # Base controller (respostas JSON)
│   ├── libraries/
│   │   └── Doctrine.php     # Integração Doctrine ORM
│   └── models/Entity/
│       ├── Atividade.php    # Entidade Atividade
│       └── Projeto.php      # Entidade Projeto
├── frontend/
│   ├── src/
│   │   ├── App.jsx          # Componente único do CRUD
│   │   └── styles.css       # Estilos da aplicação
│   ├── vite.config.js       # Proxy API + configuração Vite
│   └── package.json
├── banco.sql                # Schema do banco de dados
└── README.md
```

---

## Tecnologias

- **Backend:** PHP, CodeIgniter 3, Doctrine ORM, MySQL
- **Frontend:** React 18, Vite 6, CSS puro
