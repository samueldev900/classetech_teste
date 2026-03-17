import { useEffect, useState } from 'react';

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/$/, '');

const initialForm = {
  descricao: '',
  idProjeto: '',
};

function buildUrl(path) {
  return `${apiBaseUrl}${path}`;
}

async function request(path, options = {}) {
  const response = await fetch(buildUrl(path), {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });

  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok) {
    const error = new Error(payload.message || 'Falha na requisição.');
    error.payload = payload;
    throw error;
  }

  return payload;
}

function mapFormToPayload(form) {
  return {
    descricao: form.descricao.trim(),
    idProjeto: Number(form.idProjeto),
  };
}

function formatDate(value) {
  if (!value) {
    return '-';
  }

  const clean = value.replace('T', ' ');
  const date = new Date(clean);

  if (isNaN(date.getTime())) {
    return clean;
  }

  return date.toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function App() {
  const [atividades, setAtividades] = useState([]);
  const [form, setForm] = useState(initialForm);
  const [editingId, setEditingId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const [filterActivityId, setFilterActivityId] = useState('');
  const [projetos, setProjetos] = useState([]);

  async function loadProjetos() {
    try {
      const result = await request('/projetos');
      setProjetos(Array.isArray(result.data) ? result.data : []);
    } catch (_) {
      setProjetos([]);
    }
  }

  async function loadAtividades(projectId = '') {
    setLoading(true);

    try {
      const path = projectId ? `/projetos/${projectId}/atividades` : '/atividades';
      const result = await request(path);
      setAtividades(Array.isArray(result.data) ? result.data : []);
    } catch (error) {
      setFeedback({
        type: 'error',
        message: error.payload?.message || 'Não foi possível carregar as atividades.',
      });
      setAtividades([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadProjetos();
    loadAtividades();
  }, []);

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: value,
    }));
  }

  function resetForm() {
    setForm(initialForm);
    setEditingId(null);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);

    try {
      const payload = mapFormToPayload(form);

      if (editingId) {
        await request(`/atividades/${editingId}`, {
          method: 'PUT',
          body: JSON.stringify(payload),
        });
        setFeedback({ type: 'success', message: 'Atividade atualizada com sucesso.' });
      } else {
        await request('/atividades', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        setFeedback({ type: 'success', message: 'Atividade criada com sucesso.' });
      }

      resetForm();
      await loadAtividades();
    } catch (error) {
      const details = error.payload?.errors;
      const errorMessage = Array.isArray(details) && details.length > 0
        ? details.join(' ')
        : error.payload?.message || 'Não foi possível salvar a atividade.';

      setFeedback({ type: 'error', message: errorMessage });
    } finally {
      setSubmitting(false);
    }
  }

  function handleEdit(atividade) {
    setEditingId(atividade.id);
    setForm({
      descricao: atividade.descricao || '',
      idProjeto: atividade.projeto_id ? String(atividade.projeto_id) : '',
    });
    setFeedback(null);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function handleDelete(id) {
    if (!window.confirm('Deseja realmente remover esta atividade?')) {
      return;
    }

    try {
      await request(`/atividades/${id}`, { method: 'DELETE' });
      setFeedback({ type: 'success', message: 'Atividade removida com sucesso.' });

      if (editingId === id) {
        resetForm();
      }

      await loadAtividades();
    } catch (error) {
      setFeedback({
        type: 'error',
        message: error.payload?.message || 'Não foi possível remover a atividade.',
      });
    }
  }

  async function handleFilterSubmit(event) {
    event.preventDefault();
    const id = filterActivityId.trim();

    if (!id) {
      await loadAtividades();
      return;
    }

    setLoading(true);
    try {
      const result = await request(`/atividades/${id}`);
      setAtividades(result.data ? [result.data] : []);
      setFeedback(null);
    } catch (error) {
      setFeedback({
        type: 'error',
        message: error.payload?.message || 'Atividade não encontrada.',
      });
      setAtividades([]);
    } finally {
      setLoading(false);
    }
  }

  async function clearFilter() {
    setFilterActivityId('');
    await loadAtividades();
  }

  return (
    <main className="app-shell">
      {feedback && (
        <section className={`feedback ${feedback.type}`}>
          <span>{feedback.message}</span>
        </section>
      )}

      <section className="content-grid">
        <article className="panel form-panel">
          <div className="panel-header">
            <h2>{editingId ? 'Editar atividade' : 'Nova atividade'}</h2>
            <p>Preencha os dados e envie para a API.</p>
          </div>

          <form onSubmit={handleSubmit} className="activity-form">
            <label>
              <span>Descrição</span>
              <input
                type="text"
                name="descricao"
                value={form.descricao}
                onChange={handleChange}
                placeholder="Ex.: Implementar tela inicial"
                required
              />
            </label>

            <label>
              <span>Projeto</span>
              <select
                name="idProjeto"
                value={form.idProjeto}
                onChange={handleChange}
                required
              >
                <option value="">Selecione um projeto</option>
                {projetos.map((p) => (
                  <option key={p.id} value={p.id}>{p.descricao}</option>
                ))}
              </select>
            </label>

            <div className="form-actions">
              <button type="submit" className="primary-button" disabled={submitting}>
                {submitting ? 'Salvando...' : editingId ? 'Atualizar' : 'Criar atividade'}
              </button>
              <button type="button" className="ghost-button" onClick={resetForm} disabled={submitting}>
                Limpar
              </button>
            </div>
          </form>
        </article>

        <article className="panel list-panel">
          <div className="panel-header list-header">
            <div>
              <h2>Atividades</h2>
              <p>Pesquise pelo ID ou gerencie a lista completa.</p>
            </div>

            <form className="filter-form" onSubmit={handleFilterSubmit}>
              <input
                type="number"
                min="1"
                placeholder="ID da atividade"
                value={filterActivityId}
                onChange={(event) => setFilterActivityId(event.target.value)}
              />
              <button type="submit" className="secondary-button">Buscar</button>
              <button type="button" className="ghost-button" onClick={clearFilter}>Limpar</button>
            </form>
          </div>

          {loading ? (
            <div className="empty-state">Carregando atividades...</div>
          ) : atividades.length === 0 ? (
            <div className="empty-state">Nenhuma atividade encontrada.</div>
          ) : (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Descrição</th>
                    <th>Projeto</th>
                    <th>Data</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {atividades.map((atividade) => (
                    <tr key={atividade.id}>
                      <td><span className="id-badge">{atividade.id}</span></td>
                      <td className="td-descricao">{atividade.descricao}</td>
                      <td>
                        <span className="tag-projeto">
                          {(() => {
                            const p = projetos.find((proj) => proj.id === atividade.projeto_id);
                            return p ? p.descricao : (atividade.projeto_id || '-');
                          })()}
                        </span>
                      </td>
                      <td className="td-data">{formatDate(atividade.data)}</td>
                      <td>
                        <div className="row-actions">
                          <button type="button" className="secondary-button" onClick={() => handleEdit(atividade)}>
                            Editar
                          </button>
                          <button type="button" className="danger-button" onClick={() => handleDelete(atividade.id)}>
                            Excluir
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </article>
      </section>
    </main>
  );
}

export default App;
