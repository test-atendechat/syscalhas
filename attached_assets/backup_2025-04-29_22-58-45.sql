-- Backup do banco de dados
-- Data: 2025-04-29 22:58:45
-- --------------------------------------------------------

-- Estrutura da tabela configuracoes
DROP TABLE IF EXISTS configuracoes CASCADE;
CREATE TABLE configuracoes (
    id integer NOT NULL DEFAULT nextval('configuracoes_id_seq'::regclass),
    chave character varying(50) NOT NULL,
    valor text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cor_principal character varying(20) DEFAULT '#0d6efd'::character varying
);

-- Dados da tabela configuracoes
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (12, 'cor_secundaria', '#6c757d', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (13, 'cor_aprovado', '#198754', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (14, 'cor_pendente', '#ffc107', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (15, 'cor_rejeitado', '#dc3545', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (16, 'cor_primaria', '#0d6efd', '2025-04-29 20:00:17.935304', '2025-04-29 20:00:17.935304', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (7, 'estoque_alerta_minimo', 50, '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (1, 'empresa_nome', 'Grupo Sandro Calhas LTDA', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (2, 'empresa_telefone', '(19) 99262-0970', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (3, 'empresa_email', 'contato@sandrocalhas.com.br', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (4, 'empresa_endereco', 'Rua Carlos Pulici, 387, Vila Franco, Descalvado - SP', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (5, 'empresa_cnpj', '48.998.641/0001-03', '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (6, 'taxa_padrao_mao_obra', 100, '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (9, 'desconto_pagamento_vista', 10, '2025-04-29 15:47:38.438476', '2025-04-29 15:47:38.438476', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (10, 'max_parcelas', 12, '2025-04-29 15:47:38.438476', '2025-04-29 15:47:38.438476', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (8, 'dias_validade_orcamento', 30, '2025-04-29 13:03:15.615062', '2025-04-29 13:03:15.615062', '#0d6efd');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (11, 'cor_principal', '#0d6efd', '2025-04-29 19:15:31.577241', '2025-04-29 19:15:31.577241', '#0d6efd');

-- --------------------------------------------------------

-- Estrutura da tabela usuarios
DROP TABLE IF EXISTS usuarios CASCADE;
CREATE TABLE usuarios (
    id integer NOT NULL DEFAULT nextval('usuarios_id_seq'::regclass),
    nome character varying(100) NOT NULL,
    email character varying(100) NOT NULL,
    senha character varying(255) NOT NULL,
    nivel character varying(20) NOT NULL DEFAULT 'usuario'::character varying,
    ativo boolean NOT NULL DEFAULT true,
    data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Dados da tabela usuarios
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES (1, 'Administrador', 'admin@admin.com', '$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK', 'admin', '1', '2025-04-29 01:02:32.26967');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES (4, 'Financeiro', 'financeiro@exemplo.com', '$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK', 'usuario', '1', '2025-04-29 22:50:41.853126');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES (3, 'Atendente', 'atendente@atendente.com', '$2y$10$WjRfcwHvPfbWWpyDM/KZ9.kRrDOdrRDcmMrw2iudnAlhli5zI6V8S', 'usuario', '1', '2025-04-29 22:50:41.853126');

-- --------------------------------------------------------

-- Estrutura da tabela permissoes
DROP TABLE IF EXISTS permissoes CASCADE;
CREATE TABLE permissoes (
    id integer NOT NULL DEFAULT nextval('permissoes_id_seq'::regclass),
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
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES (1, 1, '1', '1', '1', '1', '1', '1', '1', '1', '1', '1', '2025-04-29 22:35:17.976187');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES (2, 3, '', '', '1', '', '1', '1', '1', '1', '', '', '2025-04-29 22:51:00.378936');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES (3, 4, '', '1', '1', '', '1', '1', '1', '1', '1', '', '2025-04-29 22:51:18.493191');

-- --------------------------------------------------------

-- Estrutura da tabela categorias
DROP TABLE IF EXISTS categorias CASCADE;
CREATE TABLE categorias (
    id integer NOT NULL DEFAULT nextval('categorias_id_seq'::regclass),
    nome character varying(100) NOT NULL,
    descricao character varying(255)
);

-- Dados da tabela categorias
INSERT INTO categorias (id, nome, descricao) VALUES (1, 'Cortes', NULL);
INSERT INTO categorias (id, nome, descricao) VALUES (2, 'Parafusos', NULL);

-- --------------------------------------------------------

-- Estrutura da tabela produtos
DROP TABLE IF EXISTS produtos CASCADE;
CREATE TABLE produtos (
    id integer NOT NULL DEFAULT nextval('produtos_id_seq'::regclass),
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
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES (2, 'C12', 'Corte 12', 1, 'metro', 8.50, 45.16, 50.00, '', '2025-04-29 20:08:26.091834', 4.50);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES (3, 'B1', 'Parafuso Brocante Panela Phillips', 2, 'unidade', 0.20, 9945.00, 500.00, '', '2025-04-29 20:18:27.357121', 0.10);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES (1, 'C10', 'Corte 10', 1, 'metro', 8.12, 478.55, 50.00, '', '2025-04-29 20:04:55.577645', 4.08);

-- --------------------------------------------------------

-- Estrutura da tabela clientes
DROP TABLE IF EXISTS clientes CASCADE;
CREATE TABLE clientes (
    id integer NOT NULL DEFAULT nextval('clientes_id_seq'::regclass),
    nome character varying(100) NOT NULL,
    tipo character varying(10) NOT NULL DEFAULT 'fisica'::character varying,
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
INSERT INTO clientes (id, nome, tipo, cpf_cnpj, email, telefone, endereco, cidade, estado, cep, observacoes, data_cadastro) VALUES (1, 'Sem Identificação', 'fisica', '', '', '', '', '', '', '', '', '2025-04-29 20:01:03.918571');

-- --------------------------------------------------------

-- Estrutura da tabela orcamentos
DROP TABLE IF EXISTS orcamentos CASCADE;
CREATE TABLE orcamentos (
    id integer NOT NULL DEFAULT nextval('orcamentos_id_seq'::regclass),
    numero character varying(20) NOT NULL,
    cliente_id integer NOT NULL,
    data_criacao timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_validade date,
    status character varying(20) NOT NULL DEFAULT 'pendente'::character varying,
    taxa_mao_obra numeric NOT NULL DEFAULT 0,
    valor_produtos numeric NOT NULL DEFAULT 0,
    valor_mao_obra numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    observacoes text,
    codigo_acesso character varying(32) NOT NULL,
    usuario_id integer,
    forma_pagamento character varying(50),
    status_pagamento character varying(20) NOT NULL DEFAULT 'pendente'::character varying,
    status_execucao character varying(20) NOT NULL DEFAULT 'pendente'::character varying,
    data_pagamento date,
    data_finalizacao date
);

-- Dados da tabela orcamentos
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (8, '2025/04/0008', 1, '2025-04-29 19:02:53', '2025-05-29', 'aprovado', 100.00, 121.80, 121.80, 219.24, '', '0c8afa9555e13152892081cd5ba73493', 1, 'vista', 'pago_total', 'finalizado', '2025-04-29', '2025-04-29');
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (1, '2025/04/0001', 1, '2025-04-29 17:50:41', '2025-05-29', 'aprovado', 100.00, 8.12, 8.12, 16.24, '', '7e7761ad8bd65ac6004a35a9de2bfa10', 1, 'prazo', 'pago_total', 'pendente', '2025-04-29', NULL);
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (2, '2025/04/0002', 1, '2025-04-29 17:54:38', '2025-05-29', 'aprovado', 100.00, 8.12, 8.12, 16.24, '', '2329674cfa11e569804e09ac40575121', 1, 'prazo', 'pago_total', 'finalizado', '2025-04-29', '2025-04-29');
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (3, '2025/04/0003', 1, '2025-04-29 18:44:17', '2025-05-29', 'aprovado', 100.00, 81.20, 81.20, 162.40, '', '6d7b121ab849e4f1880cc890c603083e', 1, 'prazo', 'pago_total', 'pendente', '2025-04-29', NULL);
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (4, '2025/04/0004', 1, '2025-04-29 18:48:21', '2025-05-29', 'aprovado', 100.00, 449.14, 449.14, 898.28, '', '429e3c50dcfb0c77d8be0b4cbf0b7684', 1, 'prazo', 'pago_total', 'finalizado', '2025-04-29', '2025-04-29');
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (5, '2025/04/0005', 1, '2025-04-29 18:53:25', '2025-05-29', 'aprovado', 100.00, 81.20, 81.20, 146.16, '', 'cc67be8b26036b24a4f624560d703547', 1, 'vista', 'pago_total', 'finalizado', '2025-04-29', '2025-04-29');
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (6, '2025/04/0006', 1, '2025-04-29 18:54:26', '2025-05-29', 'aprovado', 100.00, 8.50, 8.50, 15.30, '', '70e0e9cf50a5fff64f016e7da2aba90f', 1, 'vista', 'pago_total', 'pendente', '2025-04-29', NULL);
INSERT INTO orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) VALUES (7, '2025/04/0007', 1, '2025-04-29 18:56:07', '2025-05-29', 'aprovado', 100.00, 109.00, 109.00, 218.00, '', 'aa638b003a5d90c9be73c2b25470ad59', 1, 'prazo', 'pago_total', 'finalizado', '2025-04-29', '2025-04-29');

-- --------------------------------------------------------

-- Estrutura da tabela orcamento_itens
DROP TABLE IF EXISTS orcamento_itens CASCADE;
CREATE TABLE orcamento_itens (
    id integer NOT NULL DEFAULT nextval('orcamento_itens_id_seq'::regclass),
    orcamento_id integer NOT NULL,
    produto_id integer NOT NULL,
    descricao character varying(255) NOT NULL,
    unidade character varying(10) NOT NULL,
    quantidade numeric NOT NULL,
    valor_unitario numeric NOT NULL,
    valor_total numeric NOT NULL
);

-- Dados da tabela orcamento_itens
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (1, 1, 1, 'Corte 10', 'metro', 1.00, 8.12, 8.12);
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (2, 2, 1, 'Corte 10', 'metro', 1.00, 8.12, 8.12);
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (3, 3, 1, 'Corte 10', 'metro', 10.00, 8.12, 81.20);
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (4, 4, 2, 'Corte 12', 'metro', 52.84, 8.50, 449.14);
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (5, 5, 1, 'Corte 10', 'metro', 10.00, 8.12, 81.20);
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (6, 6, 2, 'Corte 12', 'metro', 1.00, 8.50, 8.50);
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (7, 7, 3, 'Parafuso Brocante Panela Phillips', 'unidade', 545.00, 0.20, 109.00);
INSERT INTO orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (8, 8, 1, 'Corte 10', 'metro', 15.00, 8.12, 121.80);

-- --------------------------------------------------------

-- Estrutura da tabela vendas
DROP TABLE IF EXISTS vendas CASCADE;
CREATE TABLE vendas (
    id integer NOT NULL DEFAULT nextval('vendas_id_seq'::regclass),
    numero character varying(20) NOT NULL,
    data_venda date NOT NULL DEFAULT CURRENT_DATE,
    cliente_id integer,
    valor_total numeric NOT NULL DEFAULT 0,
    forma_pagamento character varying(20) NOT NULL DEFAULT 'dinheiro'::character varying,
    status character varying(20) NOT NULL DEFAULT 'finalizada'::character varying,
    usuario_id integer NOT NULL,
    observacoes text,
    status_pagamento character varying(20) NOT NULL DEFAULT 'pendente'::character varying,
    data_pagamento date,
    valor_desconto numeric DEFAULT 0
);

-- Dados da tabela vendas
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (1, 'V2025040001', '2025-04-29', 1, 166.05, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.00);
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (2, 'V2025040002', '2025-04-29', 1, 100.00, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.00);
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (3, 'V2025040003', '2025-04-29', 1, 7.31, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.81);
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (4, 'V2025040004', '2025-04-29', 1, 8.12, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.00);
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (5, 'V2025040005', '2025-04-29', 1, 8.12, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.00);
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (6, 'V2025040006', '2025-04-29', 1, 8.50, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.00);
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (7, 'V2025040007', '2025-04-29', 1, 8.12, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.00);
INSERT INTO vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) VALUES (8, 'V2025040008', '2025-04-29', 1, 406.00, 'dinheiro', 'finalizada', 1, '', 'pago_total', '2025-04-29', 0.00);

-- --------------------------------------------------------

-- Estrutura da tabela vendas_itens
DROP TABLE IF EXISTS vendas_itens CASCADE;
CREATE TABLE vendas_itens (
    id integer NOT NULL DEFAULT nextval('vendas_itens_id_seq'::regclass),
    venda_id integer NOT NULL,
    produto_id integer NOT NULL,
    descricao character varying(255) NOT NULL,
    unidade character varying(50) NOT NULL,
    quantidade numeric NOT NULL,
    valor_unitario numeric NOT NULL,
    valor_total numeric NOT NULL
);

-- Dados da tabela vendas_itens
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (1, 1, 1, 'Corte 10', 'metro', 20.45, 8.12, 166.05);
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (2, 2, 3, 'Parafuso Brocante Panela Phillips', 'unidade', 500.00, 0.20, 100.00);
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (3, 3, 1, 'Corte 10', 'metro', 1.00, 8.12, 8.12);
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (4, 4, 1, 'Corte 10', 'metro', 1.00, 8.12, 8.12);
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (5, 5, 1, 'Corte 10', 'metro', 1.00, 8.12, 8.12);
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (6, 6, 2, 'Corte 12', 'metro', 1.00, 8.50, 8.50);
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (7, 7, 1, 'Corte 10', 'metro', 1.00, 8.12, 8.12);
INSERT INTO vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) VALUES (8, 8, 1, 'Corte 10', 'metro', 50.00, 8.12, 406.00);

-- --------------------------------------------------------

-- Estrutura da tabela caixa
DROP TABLE IF EXISTS caixa CASCADE;
CREATE TABLE caixa (
    id integer NOT NULL DEFAULT nextval('caixa_id_seq'::regclass),
    data_operacao date NOT NULL DEFAULT CURRENT_DATE,
    tipo character varying(20) NOT NULL,
    descricao text NOT NULL,
    valor numeric NOT NULL,
    forma_pagamento character varying(20) NOT NULL DEFAULT 'dinheiro'::character varying,
    orcamento_id integer,
    usuario_id integer NOT NULL,
    observacoes text,
    data_registro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    venda_id integer,
    cliente_id integer,
    conta_pagar_id integer,
    estorno_id integer,
    caixa_controle_id integer
);

-- Dados da tabela caixa
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (1, '2025-04-29', 'entrada', 'TROCO', 100.00, 'dinheiro', NULL, 1, '', '2025-04-29 20:21:00.364376', NULL, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (2, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040001', 50.00, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 50,00, Valor restante: R$ 16.555,00', '2025-04-29 20:27:38.359994', 1, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (3, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040001', 116.05, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 50,00, Pagamento atual: R$ 116,05, Valor restante: R$ 16.438,95', '2025-04-29 20:27:50.156743', 1, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (4, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040002', 56.00, 'pix', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 56,00, Valor restante: R$ 9.944,00', '2025-04-29 20:32:17.363567', 2, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (5, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040002', 44.00, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 56,00, Pagamento atual: R$ 44,00, Valor restante: R$ 9.900,00', '2025-04-29 20:32:33.400948', 2, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (6, '2025-04-29', 'entrada', 'Pagamento do orçamento #2025/04/0002', 5.00, 'dinheiro', NULL, 1, '', '2025-04-29 20:59:03.609301', NULL, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (7, '2025-04-29', 'entrada', 'teste', 11.11, 'dinheiro', NULL, 1, '', '2025-04-29 21:01:13.344767', NULL, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (8, '2025-04-29', 'entrada', 'Venda direta #V2025040003', 7.31, 'dinheiro', NULL, 1, 'Venda direta', '2025-04-29 21:02:53.380127', 3, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (9, '2025-04-29', 'entrada', 'testeee', 11.11, 'dinheiro', NULL, 1, '', '2025-04-29 21:03:39.093188', NULL, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (10, '2025-04-29', 'entrada', 'teste', 122.22, 'dinheiro', NULL, 1, '', '2025-04-29 21:05:02.322778', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (11, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040004', 4.00, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 4,00, Valor restante: R$ 808,00', '2025-04-29 21:06:24.114945', 4, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (12, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040004', 4.12, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 4,00, Pagamento atual: R$ 4,12, Valor restante: R$ 803,88', '2025-04-29 21:06:32.634646', 4, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (13, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040005', 8.12, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 8,12, Valor restante: R$ 803,88', '2025-04-29 21:12:35.107612', 5, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (14, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040006', 2.50, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 2,50, Valor restante: R$ 847,50', '2025-04-29 21:17:04.371807', 6, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (15, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040006', 6.00, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 2,50, Pagamento atual: R$ 6,00, Valor restante: R$ 841,50', '2025-04-29 21:17:21.641922', 6, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (16, '2025-04-29', 'entrada', 'teste', 10.00, 'dinheiro', 2, 1, '', '2025-04-29 21:28:14.334835', NULL, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (17, '2025-04-29', 'entrada', 'Pagamento do Orçamento #2', 10.00, 'dinheiro', NULL, 1, '', '2025-04-29 21:28:33.967178', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (18, '2025-04-29', 'entrada', 'teste', 11.11, 'dinheiro', NULL, 1, '', '2025-04-29 21:29:04.578835', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (19, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040007', 5.00, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 5,00, Valor restante: R$ 807,00', '2025-04-29 21:30:20.767336', 7, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (20, '2025-04-29', 'entrada', 'Pagamento da venda #V2025040007', 3.12, 'dinheiro', NULL, 1, 'Pagamento parcial - Valor pago anteriormente: R$ 5,00, Pagamento atual: R$ 3,12, Valor restante: R$ 803,88', '2025-04-29 21:30:35.695908', 7, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (21, '2025-04-29', 'entrada', 'Venda direta #V2025040008', 406.00, 'dinheiro', NULL, 1, 'Venda direta', '2025-04-29 21:31:28.059619', 8, NULL, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (22, '2025-04-29', 'entrada', 'Pagamento do Orçamento #2', 6.23, 'dinheiro', 2, 1, '', '2025-04-29 21:38:27.918819', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (23, '2025-04-29', 'entrada', 'Pagamento do Orçamento #1', 16.24, 'dinheiro', 1, 1, '', '2025-04-29 21:41:39.075267', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (24, '2025-04-29', 'entrada', 'Pagamento do Orçamento #2', 0.01, 'dinheiro', 2, 1, '', '2025-04-29 21:42:16.238185', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (25, '2025-04-29', 'entrada', 'Pagamento do Orçamento #3', 50.00, 'dinheiro', 3, 1, '', '2025-04-29 21:45:12.886621', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (26, '2025-04-29', 'entrada', 'Pagamento do Orçamento #3', 50.00, 'dinheiro', 3, 1, '', '2025-04-29 21:46:08.824351', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (27, '2025-04-29', 'entrada', 'Pagamento do Orçamento #3', 62.40, 'dinheiro', 3, 1, '', '2025-04-29 21:46:39.840452', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (28, '2025-04-29', 'entrada', 'Pagamento do Orçamento #4', 500.00, 'dinheiro', 4, 1, '', '2025-04-29 21:49:50.622448', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (29, '2025-04-29', 'entrada', 'Pagamento do Orçamento #4', 398.28, 'dinheiro', 4, 1, '', '2025-04-29 21:52:32.881925', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (30, '2025-04-29', 'entrada', 'Pagamento do Orçamento #5', 146.16, 'dinheiro', 5, 1, '', '2025-04-29 21:54:02.206779', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (31, '2025-04-29', 'entrada', 'Pagamento do Orçamento #6', 15.30, 'dinheiro', 6, 1, '', '2025-04-29 21:54:53.759824', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (32, '2025-04-29', 'entrada', 'Pagamento do Orçamento #7', 150.00, 'dinheiro', 7, 1, '', '2025-04-29 21:57:54.078949', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (33, '2025-04-29', 'entrada', 'Pagamento do Orçamento #7', 68.00, 'pix', 7, 1, '', '2025-04-29 21:58:13.928803', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (34, '2025-04-29', 'entrada', 'Pagamento do Orçamento #8', 200.00, 'dinheiro', 8, 1, '', '2025-04-29 22:03:25.190274', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (35, '2025-04-29', 'entrada', 'Pagamento do Orçamento #8', 19.24, 'dinheiro', 8, 1, '', '2025-04-29 22:04:09.821338', NULL, 1, NULL, NULL, NULL);
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) VALUES (36, '2025-04-29', 'saida', 'Gasolina Uno', 50.00, 'dinheiro', NULL, 1, '', '2025-04-30 00:06:57.604561', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

-- Estrutura da tabela caixa_controle
DROP TABLE IF EXISTS caixa_controle CASCADE;
CREATE TABLE caixa_controle (
    id integer NOT NULL DEFAULT nextval('caixa_controle_id_seq'::regclass),
    data_abertura date NOT NULL,
    hora_abertura timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_inicial numeric NOT NULL DEFAULT 0,
    data_fechamento date,
    hora_fechamento timestamp without time zone,
    valor_final numeric,
    valor_informado numeric,
    diferenca numeric,
    observacoes text,
    usuario_abertura_id integer,
    usuario_fechamento_id integer,
    observacoes_abertura text,
    valor_conferido_dinheiro numeric,
    valor_conferido_credito numeric,
    valor_conferido_debito numeric,
    valor_conferido_pix numeric,
    valor_conferido_outro numeric,
    observacoes_fechamento text
);

-- Dados da tabela caixa_controle

-- --------------------------------------------------------

-- Estrutura da tabela estoque_movimentacoes
DROP TABLE IF EXISTS estoque_movimentacoes CASCADE;
CREATE TABLE estoque_movimentacoes (
    id integer NOT NULL DEFAULT nextval('estoque_movimentacoes_id_seq'::regclass),
    produto_id integer NOT NULL,
    tipo character varying(20) NOT NULL,
    quantidade numeric NOT NULL,
    valor_unitario numeric NOT NULL,
    valor_total numeric NOT NULL,
    observacao text,
    orcamento_id integer,
    data_movimentacao timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id integer
);

-- Dados da tabela estoque_movimentacoes
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (1, 1, 'entrada', 100.00, 8.12, 812.00, '', NULL, '2025-04-29 20:06:04.868105', 1);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (2, 2, 'entrada', 100.00, 8.50, 850.00, '', NULL, '2025-04-29 20:08:38.849156', 1);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (3, 3, 'entrada', 1000.00, 0.20, 200.00, '', NULL, '2025-04-29 20:18:36.150551', 1);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (4, 3, 'saida', 10.00, 0.20, 2.00, 'Motivo: Venda', NULL, '2025-04-29 20:19:42.0609', 1);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (5, 1, 'saida', 20.45, 8.12, 166.05, 'Saída automática da venda #V2025040001', NULL, '2025-04-29 20:24:05.116676', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (6, 3, 'saida', 500.00, 0.20, 100.00, 'Saída automática da venda #V2025040002', NULL, '2025-04-29 20:31:52.030015', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (7, 3, 'entrada', 10000.00, 0.20, 2000.00, '', NULL, '2025-04-29 20:38:55.176676', 1);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (8, 1, 'saida', 1.00, 8.12, 8.12, 'Saída automática do orçamento #2025/04/0001', 1, '2025-04-29 20:50:54.942978', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (9, 1, 'saida', 1.00, 8.12, 8.12, 'Saída automática do orçamento #2025/04/0002', 2, '2025-04-29 20:55:03.244293', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (10, 1, 'saida', 1.00, 8.12, 8.12, 'Saída automática da venda #V2025040003', NULL, '2025-04-29 21:02:53.380127', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (11, 1, 'saida', 1.00, 8.12, 8.12, 'Saída automática da venda #V2025040004', NULL, '2025-04-29 21:06:09.165008', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (12, 1, 'saida', 1.00, 8.12, 8.12, 'Saída automática da venda #V2025040005', NULL, '2025-04-29 21:12:20.743311', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (13, 2, 'saida', 1.00, 8.50, 8.50, 'Saída automática da venda #V2025040006', NULL, '2025-04-29 21:16:49.443672', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (14, 1, 'saida', 1.00, 8.12, 8.12, 'Saída automática da venda #V2025040007', NULL, '2025-04-29 21:29:59.947848', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (15, 1, 'saida', 50.00, 8.12, 406.00, 'Saída automática da venda #V2025040008', NULL, '2025-04-29 21:31:28.059619', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (16, 1, 'saida', 10.00, 8.12, 81.20, 'Saída automática do orçamento #2025/04/0003', 3, '2025-04-29 21:44:39.730739', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (17, 1, 'saida', 10.00, 8.12, 81.20, 'Saída automática do orçamento #2025/04/0003', 3, '2025-04-29 21:45:37.252261', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (18, 2, 'saida', 52.84, 8.50, 449.14, 'Saída automática do orçamento #2025/04/0004', 4, '2025-04-29 21:48:59.397482', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (19, 1, 'saida', 10.00, 8.12, 81.20, 'Saída automática do orçamento #2025/04/0005', 5, '2025-04-29 21:53:35.919675', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (20, 2, 'saida', 1.00, 8.50, 8.50, 'Saída automática do orçamento #2025/04/0006', 6, '2025-04-29 21:54:35.031404', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (21, 3, 'saida', 545.00, 0.20, 109.00, 'Saída automática do orçamento #2025/04/0007', 7, '2025-04-29 21:56:14.641605', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (22, 1, 'saida', 15.00, 8.12, 121.80, 'Saída automática do orçamento #2025/04/0008', 8, '2025-04-29 22:03:00.41642', NULL);
INSERT INTO estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) VALUES (23, 1, 'entrada', 500.00, 8.12, 4060.00, '', NULL, '2025-04-29 22:22:26.903414', 1);

-- --------------------------------------------------------

-- Estrutura da tabela contas_pagar
DROP TABLE IF EXISTS contas_pagar CASCADE;
CREATE TABLE contas_pagar (
    id integer NOT NULL DEFAULT nextval('contas_pagar_id_seq'::regclass),
    descricao character varying(255) NOT NULL,
    fornecedor character varying(255),
    data_emissao date NOT NULL,
    data_vencimento date NOT NULL,
    valor numeric NOT NULL,
    status character varying(30) NOT NULL DEFAULT 'pendente'::character varying,
    observacoes text,
    documento character varying(100),
    categoria character varying(100),
    data_pagamento date,
    valor_pago numeric,
    forma_pagamento character varying(50),
    usuario_id integer,
    created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone,
    recorrente boolean DEFAULT false,
    intervalo_dias integer DEFAULT 30,
    proxima_data date,
    conta_pai_id integer
);

-- Dados da tabela contas_pagar

-- --------------------------------------------------------

-- Estrutura da tabela pagamentos_contas
DROP TABLE IF EXISTS pagamentos_contas CASCADE;
CREATE TABLE pagamentos_contas (
    id integer NOT NULL DEFAULT nextval('pagamentos_contas_id_seq'::regclass),
    conta_id integer,
    data_pagamento date NOT NULL,
    valor numeric NOT NULL,
    forma_pagamento character varying(50) NOT NULL,
    observacoes text,
    caixa_id integer,
    usuario_id integer,
    created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Dados da tabela pagamentos_contas

-- --------------------------------------------------------

-- Estrutura da tabela estornos_pagamentos
DROP TABLE IF EXISTS estornos_pagamentos CASCADE;
CREATE TABLE estornos_pagamentos (
    id integer NOT NULL DEFAULT nextval('estornos_pagamentos_id_seq'::regclass),
    conta_id integer NOT NULL,
    data_estorno date NOT NULL,
    valor numeric NOT NULL,
    observacoes text,
    usuario_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- Dados da tabela estornos_pagamentos

-- --------------------------------------------------------

-- Sequências
DROP SEQUENCE IF EXISTS usuarios_id_seq CASCADE;
CREATE SEQUENCE usuarios_id_seq START 1;
<br />
<b>Fatal error</b>:  Uncaught PDOException: SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation &quot;sql_features&quot; does not exist in /home/runner/workspace/backup_sql_simples.php:122
Stack trace:
#0 /home/runner/workspace/backup_sql_simples.php(122): PDO-&gt;query('SELECT pg_get_s...')
#1 {main}
  thrown in <b>/home/runner/workspace/backup_sql_simples.php</b> on line <b>122</b><br />
