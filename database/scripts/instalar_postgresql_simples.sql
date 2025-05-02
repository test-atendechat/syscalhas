-- --------------------------------------------------------
-- Sistema de Orçamentos e Vendas
-- Versão simplificada para importação no PostgreSQL
-- --------------------------------------------------------

-- Criar sequências primeiro
CREATE SEQUENCE IF NOT EXISTS configuracoes_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS usuarios_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS permissoes_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS categorias_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS produtos_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS clientes_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS orcamentos_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS orcamento_itens_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS vendas_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS vendas_itens_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS caixa_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS caixa_controle_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS estoque_movimentacoes_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS contas_pagar_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS pagamentos_contas_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS estornos_pagamentos_id_seq START 1;

-- Iniciar transação
BEGIN;

-- Estrutura da tabela configuracoes
DROP TABLE IF EXISTS configuracoes CASCADE;
CREATE TABLE configuracoes (
    id integer NOT NULL DEFAULT nextval('configuracoes_id_seq') PRIMARY KEY,
    chave character varying(50) NOT NULL,
    valor text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cor_principal character varying(20) DEFAULT '#0d6efd'
);

-- Dados da tabela configuracoes
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (12, 'cor_secundaria', '#6c757d', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (13, 'cor_aprovado', '#198754', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (14, 'cor_pendente', '#ffc107', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (15, 'cor_rejeitado', '#dc3545', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (16, 'cor_primaria', '#0d6efd', '2025-04-29 20:00:17.935304', '2025-04-29 20:00:17.935304', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (7, 'estoque_alerta_minimo', '50', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (1, 'empresa_nome', 'Grupo Sandro Calhas LTDA', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (2, 'empresa_telefone', '(19) 99262-0970', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (3, 'empresa_email', 'contato@sandrocalhas.com.br', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (4, 'empresa_endereco', 'Rua Carlos Pulici, 387, Vila Franco, Descalvado - SP', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (5, 'empresa_cnpj', '48.998.641/0001-03', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (6, 'taxa_padrao_mao_obra', '100', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (9, 'desconto_pagamento_vista', '10', '2025-04-29 15:47:38.438476', '2025-04-29 15:47:38.438476', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (10, 'max_parcelas', '12', '2025-04-29 15:47:38.438476', '2025-04-29 15:47:38.438476', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (8, 'dias_validade_orcamento', '30', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (11, 'cor_principal', '#0d6efd', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');

-- Resetar sequência
SELECT setval('configuracoes_id_seq', (SELECT MAX(id) FROM configuracoes));

-- Estrutura da tabela usuarios
DROP TABLE IF EXISTS usuarios CASCADE;
CREATE TABLE usuarios (
    id integer NOT NULL DEFAULT nextval('usuarios_id_seq') PRIMARY KEY,
    nome character varying(100) NOT NULL,
    email character varying(100) NOT NULL,
    senha character varying(255) NOT NULL,
    nivel character varying(20) NOT NULL DEFAULT 'usuario',
    ativo boolean NOT NULL DEFAULT true,
    data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Dados da tabela usuarios
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES 
(1, 'Administrador', 'admin@admin.com', '$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK', 'admin', true, '2025-04-29 01:02:32.26967');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES 
(4, 'Financeiro', 'financeiro@exemplo.com', '$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK', 'usuario', true, '2025-04-29 22:50:41.853126');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES 
(3, 'Atendente', 'atendente@atendente.com', '$2y$10$WjRfcwHvPfbWWpyDM/KZ9.kRrDOdrRDcmMrw2iudnAlhli5zI6V8S', 'usuario', true, '2025-04-29 22:50:41.853126');

-- Resetar sequência
SELECT setval('usuarios_id_seq', (SELECT MAX(id) FROM usuarios));

-- Estrutura da tabela permissoes
DROP TABLE IF EXISTS permissoes CASCADE;
CREATE TABLE permissoes (
    id integer NOT NULL DEFAULT nextval('permissoes_id_seq') PRIMARY KEY,
    usuario_id integer NOT NULL,
    gerenciar_usuarios boolean DEFAULT false,
    visualizar_relatorios_financeiros boolean DEFAULT false,
    gerenciar_estoque boolean DEFAULT false,
    gerenciar_produtos boolean DEFAULT false,
    gerenciar_orcamentos boolean DEFAULT false,
    gerenciar_vendas boolean DEFAULT false,
    gerenciar_clientes boolean DEFAULT false,
    gerenciar_caixa boolean DEFAULT false,
    gerenciar_contas boolean DEFAULT false,
    visualizar_relatorios_gerais boolean DEFAULT false,
    data_atualizacao timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- Dados da tabela permissoes
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES 
(1, 1, true, true, true, true, true, true, true, true, true, true, '2025-04-29 22:35:17.976187');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES 
(2, 3, false, false, true, false, true, true, true, true, false, false, '2025-04-29 22:51:00.378936');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES 
(3, 4, false, true, true, false, true, true, true, true, true, false, '2025-04-29 22:51:18.493191');

-- Resetar sequência
SELECT setval('permissoes_id_seq', (SELECT MAX(id) FROM permissoes));

-- Estrutura da tabela categorias
DROP TABLE IF EXISTS categorias CASCADE;
CREATE TABLE categorias (
    id integer NOT NULL DEFAULT nextval('categorias_id_seq') PRIMARY KEY,
    nome character varying(100) NOT NULL,
    descricao character varying(255)
);

-- Dados da tabela categorias
INSERT INTO categorias (id, nome, descricao) VALUES (1, 'Cortes', NULL);
INSERT INTO categorias (id, nome, descricao) VALUES (2, 'Parafusos', NULL);

-- Resetar sequência
SELECT setval('categorias_id_seq', (SELECT MAX(id) FROM categorias));

-- Estrutura da tabela produtos
DROP TABLE IF EXISTS produtos CASCADE;
CREATE TABLE produtos (
    id integer NOT NULL DEFAULT nextval('produtos_id_seq') PRIMARY KEY,
    codigo character varying(50),
    descricao character varying(255) NOT NULL,
    categoria_id integer,
    unidade character varying(10) NOT NULL,
    valor_unitario numeric NOT NULL,
    estoque_atual numeric NOT NULL DEFAULT 0,
    estoque_minimo numeric NOT NULL DEFAULT 0,
    observacoes text,
    data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    custo_unitario numeric NOT NULL DEFAULT 0
);

-- Dados da tabela produtos
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES 
(2, 'C12', 'Corte 12', 1, 'metro', 8.50, 45.16, 50.00, '', '2025-04-29 20:08:26.091834', 4.50);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES 
(3, 'B1', 'Parafuso Brocante Panela Phillips', 2, 'unidade', 0.20, 9945.00, 500.00, '', '2025-04-29 20:18:27.357121', 0.10);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES 
(1, 'C10', 'Corte 10', 1, 'metro', 8.12, 478.55, 50.00, '', '2025-04-29 20:04:55.577645', 4.08);

-- Resetar sequência
SELECT setval('produtos_id_seq', (SELECT MAX(id) FROM produtos));

-- Estrutura da tabela clientes
DROP TABLE IF EXISTS clientes CASCADE;
CREATE TABLE clientes (
    id integer NOT NULL DEFAULT nextval('clientes_id_seq') PRIMARY KEY,
    nome character varying(100) NOT NULL,
    tipo character varying(10) NOT NULL DEFAULT 'fisica',
    cpf_cnpj character varying(20),
    email character varying(100),
    telefone character varying(20),
    endereco character varying(255),
    cidade character varying(100),
    estado character varying(2),
    cep character varying(10),
    observacoes text,
    data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Dados da tabela clientes
INSERT INTO clientes (id, nome, tipo, cpf_cnpj, email, telefone, endereco, cidade, estado, cep, observacoes, data_cadastro) VALUES 
(1, 'Sem Identificação', 'fisica', '', '', '', '', '', '', '', '', '2025-04-29 20:01:03.918571');

-- Resetar sequência
SELECT setval('clientes_id_seq', (SELECT MAX(id) FROM clientes));

-- Estrutura da tabela orcamentos
DROP TABLE IF EXISTS orcamentos CASCADE;
CREATE TABLE orcamentos (
    id integer NOT NULL DEFAULT nextval('orcamentos_id_seq') PRIMARY KEY,
    numero character varying(20) NOT NULL,
    cliente_id integer NOT NULL,
    data_criacao timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_validade date,
    status character varying(20) NOT NULL DEFAULT 'pendente',
    taxa_mao_obra numeric NOT NULL DEFAULT 0,
    valor_produtos numeric NOT NULL DEFAULT 0,
    valor_mao_obra numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    observacoes text,
    codigo_acesso character varying(32) NOT NULL,
    usuario_id integer,
    forma_pagamento character varying(50),
    status_pagamento character varying(20) NOT NULL DEFAULT 'pendente',
    status_execucao character varying(20) NOT NULL DEFAULT 'pendente',
    data_pagamento date,
    data_finalizacao date
);

-- Resetar sequência
SELECT setval('orcamentos_id_seq', COALESCE((SELECT MAX(id) FROM orcamentos), 0));

-- Estrutura da tabela orcamento_itens
DROP TABLE IF EXISTS orcamento_itens CASCADE;
CREATE TABLE orcamento_itens (
    id integer NOT NULL DEFAULT nextval('orcamento_itens_id_seq') PRIMARY KEY,
    orcamento_id integer NOT NULL,
    produto_id integer NOT NULL,
    quantidade numeric NOT NULL DEFAULT 0,
    valor_unitario numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    observacoes text
);

-- Resetar sequência
SELECT setval('orcamento_itens_id_seq', COALESCE((SELECT MAX(id) FROM orcamento_itens), 0));

-- Estrutura da tabela vendas
DROP TABLE IF EXISTS vendas CASCADE;
CREATE TABLE vendas (
    id integer NOT NULL DEFAULT nextval('vendas_id_seq') PRIMARY KEY,
    numero character varying(20) NOT NULL,
    cliente_id integer NOT NULL,
    data_venda timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_produtos numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    forma_pagamento character varying(50),
    status_pagamento character varying(20) NOT NULL DEFAULT 'pendente',
    status_entrega character varying(20) NOT NULL DEFAULT 'pendente',
    observacoes text,
    usuario_id integer,
    orcamento_id integer,
    data_pagamento date,
    data_entrega date,
    nfe_status character varying(50),
    nfe_chave character varying(50),
    nfe_numero character varying(20)
);

-- Resetar sequência
SELECT setval('vendas_id_seq', COALESCE((SELECT MAX(id) FROM vendas), 0));

-- Estrutura da tabela vendas_itens
DROP TABLE IF EXISTS vendas_itens CASCADE;
CREATE TABLE vendas_itens (
    id integer NOT NULL DEFAULT nextval('vendas_itens_id_seq') PRIMARY KEY,
    venda_id integer NOT NULL,
    produto_id integer NOT NULL,
    quantidade numeric NOT NULL DEFAULT 0,
    valor_unitario numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    observacoes text,
    custo_unitario numeric NOT NULL DEFAULT 0
);

-- Resetar sequência
SELECT setval('vendas_itens_id_seq', COALESCE((SELECT MAX(id) FROM vendas_itens), 0));

-- Estrutura da tabela caixa
DROP TABLE IF EXISTS caixa CASCADE;
CREATE TABLE caixa (
    id integer NOT NULL DEFAULT nextval('caixa_id_seq') PRIMARY KEY,
    data_operacao date NOT NULL DEFAULT CURRENT_DATE,
    tipo character varying(20) NOT NULL,
    descricao character varying(255) NOT NULL,
    valor numeric NOT NULL DEFAULT 0,
    forma_pagamento character varying(50),
    observacoes text,
    usuario_id integer,
    venda_id integer,
    orcamento_id integer,
    conta_id integer,
    cliente_id integer,
    data_registro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Resetar sequência
SELECT setval('caixa_id_seq', COALESCE((SELECT MAX(id) FROM caixa), 0));

-- Estrutura da tabela caixa_controle
DROP TABLE IF EXISTS caixa_controle CASCADE;
CREATE TABLE caixa_controle (
    id integer NOT NULL DEFAULT nextval('caixa_controle_id_seq') PRIMARY KEY,
    data_abertura timestamp without time zone,
    saldo_inicial numeric DEFAULT 0,
    saldo_final numeric,
    data_fechamento timestamp without time zone,
    usuario_id_abertura integer,
    usuario_id_fechamento integer,
    observacoes text,
    status character varying(20) NOT NULL DEFAULT 'fechado'
);

-- Resetar sequência
SELECT setval('caixa_controle_id_seq', COALESCE((SELECT MAX(id) FROM caixa_controle), 0));

-- Estrutura da tabela estoque_movimentacoes
DROP TABLE IF EXISTS estoque_movimentacoes CASCADE;
CREATE TABLE estoque_movimentacoes (
    id integer NOT NULL DEFAULT nextval('estoque_movimentacoes_id_seq') PRIMARY KEY,
    produto_id integer NOT NULL,
    data_movimentacao timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo character varying(20) NOT NULL,
    quantidade numeric NOT NULL DEFAULT 0,
    saldo_anterior numeric NOT NULL DEFAULT 0,
    saldo_atual numeric NOT NULL DEFAULT 0,
    observacoes text,
    usuario_id integer,
    venda_id integer,
    orcamento_id integer,
    valor_unitario numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0
);

-- Resetar sequência
SELECT setval('estoque_movimentacoes_id_seq', COALESCE((SELECT MAX(id) FROM estoque_movimentacoes), 0));

-- Estrutura da tabela contas_pagar
DROP TABLE IF EXISTS contas_pagar CASCADE;
CREATE TABLE contas_pagar (
    id integer NOT NULL DEFAULT nextval('contas_pagar_id_seq') PRIMARY KEY,
    descricao character varying(255) NOT NULL,
    valor numeric NOT NULL DEFAULT 0,
    data_vencimento date NOT NULL,
    tipo character varying(50) NOT NULL DEFAULT 'normal',
    status character varying(20) NOT NULL DEFAULT 'pendente',
    data_pagamento date,
    valor_pago numeric,
    categoria character varying(50),
    fornecedor character varying(100),
    recorrente boolean NOT NULL DEFAULT false,
    intervalo_recorrencia integer,
    unidade_recorrencia character varying(20) DEFAULT 'meses',
    data_fim_recorrencia date,
    usuario_id integer,
    data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacoes text
);

-- Resetar sequência
SELECT setval('contas_pagar_id_seq', COALESCE((SELECT MAX(id) FROM contas_pagar), 0));

-- Estrutura da tabela pagamentos_contas
DROP TABLE IF EXISTS pagamentos_contas CASCADE;
CREATE TABLE pagamentos_contas (
    id integer NOT NULL DEFAULT nextval('pagamentos_contas_id_seq') PRIMARY KEY,
    conta_id integer NOT NULL,
    data_pagamento date NOT NULL,
    valor numeric NOT NULL DEFAULT 0,
    forma_pagamento character varying(50) NOT NULL,
    observacoes text,
    usuario_id integer,
    data_registro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Resetar sequência
SELECT setval('pagamentos_contas_id_seq', COALESCE((SELECT MAX(id) FROM pagamentos_contas), 0));

-- Estrutura da tabela estornos_pagamentos
DROP TABLE IF EXISTS estornos_pagamentos CASCADE;
CREATE TABLE estornos_pagamentos (
    id integer NOT NULL DEFAULT nextval('estornos_pagamentos_id_seq') PRIMARY KEY,
    caixa_id integer,
    data_estorno timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor numeric NOT NULL DEFAULT 0,
    motivo text,
    usuario_id integer NOT NULL,
    venda_id integer,
    orcamento_id integer,
    conta_id integer
);

-- Resetar sequência
SELECT setval('estornos_pagamentos_id_seq', COALESCE((SELECT MAX(id) FROM estornos_pagamentos), 0));

-- Adicionar chaves estrangeiras
ALTER TABLE permissoes ADD CONSTRAINT fk_permissoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE produtos ADD CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL;
ALTER TABLE orcamentos ADD CONSTRAINT fk_orcamentos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id);
ALTER TABLE orcamentos ADD CONSTRAINT fk_orcamentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;
ALTER TABLE orcamento_itens ADD CONSTRAINT fk_orcamento_itens_orcamento FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE;
ALTER TABLE orcamento_itens ADD CONSTRAINT fk_orcamento_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id);

-- Configurar codificação UTF8 para o banco
SET client_encoding = 'UTF8';

COMMIT;