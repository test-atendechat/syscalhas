-- Script para criar tabelas de colaboradores

-- Tabela de colaboradores (instaladores e auxiliares)
CREATE TABLE IF NOT EXISTS colaboradores (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('instalador', 'auxiliar')),
    cpf VARCHAR(14) UNIQUE,
    telefone VARCHAR(20),
    endereco TEXT,
    data_nascimento DATE,
    data_admissao DATE NOT NULL DEFAULT CURRENT_DATE,
    data_desligamento DATE,
    observacoes TEXT,
    status VARCHAR(10) NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo', 'inativo')),
    usuario_id INTEGER REFERENCES usuarios(id),
    data_cadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_atualizacao TIMESTAMP
);

-- Índices para busca eficiente
CREATE INDEX IF NOT EXISTS idx_colaboradores_nome ON colaboradores(nome);
CREATE INDEX IF NOT EXISTS idx_colaboradores_tipo ON colaboradores(tipo);
CREATE INDEX IF NOT EXISTS idx_colaboradores_status ON colaboradores(status);

-- Tabela para composição de equipes (instalador e seus auxiliares)
CREATE TABLE IF NOT EXISTS colaborador_equipe (
    id SERIAL PRIMARY KEY,
    instalador_id INTEGER NOT NULL REFERENCES colaboradores(id),
    auxiliar_id INTEGER NOT NULL REFERENCES colaboradores(id),
    data_inicio DATE NOT NULL DEFAULT CURRENT_DATE,
    data_fim DATE,
    usuario_id INTEGER REFERENCES usuarios(id),
    data_cadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_diferentes_colaboradores CHECK (instalador_id <> auxiliar_id),
    CONSTRAINT chk_instalador_tipo CHECK (instalador_id IN (SELECT id FROM colaboradores WHERE tipo = 'instalador')),
    CONSTRAINT chk_auxiliar_tipo CHECK (auxiliar_id IN (SELECT id FROM colaboradores WHERE tipo = 'auxiliar'))
);

-- Índices para busca eficiente
CREATE INDEX IF NOT EXISTS idx_colaborador_equipe_instalador ON colaborador_equipe(instalador_id);
CREATE INDEX IF NOT EXISTS idx_colaborador_equipe_auxiliar ON colaborador_equipe(auxiliar_id);

-- Alterar tabela de orçamentos para adicionar colaborador_id
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS colaborador_id INTEGER REFERENCES colaboradores(id);
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS data_finalizacao TIMESTAMP;
ALTER TABLE orcamentos ADD COLUMN IF NOT EXISTS status_execucao VARCHAR(20) DEFAULT 'pendente' CHECK (status_execucao IN ('pendente', 'em_andamento', 'finalizado', 'cancelado'));

-- Índice para busca eficiente de orçamentos por colaborador
CREATE INDEX IF NOT EXISTS idx_orcamentos_colaborador ON orcamentos(colaborador_id);

-- Inserir permissões para gerenciamento de colaboradores
INSERT INTO permissoes (chave, descricao, categoria)
VALUES ('gerenciar_colaboradores', 'Permite gerenciar colaboradores (instaladores e auxiliares)', 'admin')
ON CONFLICT (chave) DO NOTHING;
