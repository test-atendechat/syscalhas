-- Criar tabela de notificações se não existir
CREATE TABLE IF NOT EXISTS notificacoes (
    id SERIAL PRIMARY KEY,
    mensagem TEXT NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'info',
    link TEXT,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    autor_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    destinatario_id INTEGER REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Criar tabela de notificações lidas se não existir
CREATE TABLE IF NOT EXISTS notificacoes_lidas (
    id SERIAL PRIMARY KEY,
    notificacao_id INTEGER NOT NULL REFERENCES notificacoes(id) ON DELETE CASCADE,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    data_leitura TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(notificacao_id, usuario_id)
);