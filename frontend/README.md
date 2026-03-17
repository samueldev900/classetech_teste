# Frontend React

Frontend minimo em React + Vite para consumir o CRUD de atividades do backend CodeIgniter.

## Requisitos

- Node.js 18+
- Backend PHP rodando em uma URL acessivel

## Configuracao

1. Copie `.env.example` para `.env` se quiser sobrescrever a URL da API.
2. Ajuste os valores conforme seu ambiente local.

Variaveis disponiveis:

- `VITE_API_BASE_URL`: base usada pelo navegador. Em desenvolvimento, o padrao e `/api`.
- `VITE_DEV_API_TARGET`: host de destino do proxy do Vite.
- `VITE_DEV_API_PREFIX`: prefixo reescrito pelo proxy para chegar ao `index.php` do CodeIgniter.

Valores padrao atuais:

- `VITE_API_BASE_URL=/api`
- `VITE_DEV_API_TARGET=http://localhost`
- `VITE_DEV_API_PREFIX=/ideia/modelo/index.php`

## Executar

```bash
npm install
npm run dev
```

## Tela implementada

- Listagem de atividades
- Filtro por projeto
- Criacao de atividade
- Edicao de atividade
- Exclusao de atividade
