-- Script de instalação do banco de dados PostgreSQL com correções
-- Data: 30/04/2025
-- Este script contém correções para os erros encontrados durante a instalação inicial

-- Criar banco de dados (execute isso separadamente se estiver criando um novo banco)
-- CREATE DATABASE sistema_orcamentos_calhas;

-- Conectar ao banco de dados
-- \c sistema_orcamentos_calhas;

-- Limpar tabelas existentes (caso seja uma reinstalação)
DROP TABLE IF EXISTS permissoes CASCADE;
DROP TABLE IF EXISTS caixa CASCADE;
DROP TABLE IF EXISTS caixa_controle CASCADE;
DROP TABLE IF EXISTS estornos_pagamentos CASCADE;
DROP TABLE IF EXISTS pagamentos_contas CASCADE;
DROP TABLE IF EXISTS contas_pagar CASCADE;
DROP TABLE IF EXISTS vendas_itens CASCADE;
DROP TABLE IF EXISTS vendas CASCADE;
DROP TABLE IF EXISTS orcamento_itens CASCADE;
DROP TABLE IF EXISTS orcamentos CASCADE;
DROP TABLE IF EXISTS estoque_movimentacoes CASCADE;
DROP TABLE IF EXISTS produtos CASCADE;
DROP TABLE IF EXISTS categorias CASCADE;
DROP TABLE IF EXISTS clientes CASCADE;
DROP TABLE IF EXISTS configuracoes CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;

-- Criar tabela de usuários
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'admin',
    status BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Criar tabela de configurações
CREATE TABLE configuracoes (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descricao TEXT
);

-- Criar tabela de clientes
CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefone VARCHAR(20),
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    data_cadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacoes TEXT
);

-- Criar tabela de categorias
CREATE TABLE categorias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT
);

-- Criar tabela de produtos
CREATE TABLE produtos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    categoria_id INTEGER REFERENCES categorias(id),
    preco_custo DECIMAL(10,2) NOT NULL,
    preco_venda DECIMAL(10,2) NOT NULL,
    quantidade INTEGER NOT NULL DEFAULT 0,
    unidade VARCHAR(10) NOT NULL DEFAULT 'un',
    estoque_minimo INTEGER NOT NULL DEFAULT 0
);

-- Criar tabela de orçamentos
CREATE TABLE orcamentos (
    id SERIAL PRIMARY KEY,
    numero VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INTEGER REFERENCES clientes(id),
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_validade DATE,
    valor_produtos DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_mao_obra DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_adicional DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),
    condicoes_pagamento TEXT,
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id),
    codigo_acesso VARCHAR(50) UNIQUE,
    data_aprovacao TIMESTAMP,
    endereco_entrega TEXT,
    prazo_entrega VARCHAR(100)
);

-- Criar tabela de itens do orçamento
CREATE TABLE orcamento_itens (
    id SERIAL PRIMARY KEY,
    orcamento_id INTEGER REFERENCES orcamentos(id) ON DELETE CASCADE,
    produto_id INTEGER REFERENCES produtos(id),
    descricao TEXT,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    tipo VARCHAR(20) NOT NULL DEFAULT 'produto' -- produto ou mao_obra
);

-- Criar tabela de vendas
CREATE TABLE vendas (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER REFERENCES clientes(id),
    data_venda TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_pago DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    metodo_pagamento VARCHAR(50),
    usuario_id INTEGER REFERENCES usuarios(id),
    observacoes TEXT
);

-- Criar tabela de itens da venda
CREATE TABLE vendas_itens (
    id SERIAL PRIMARY KEY,
    venda_id INTEGER REFERENCES vendas(id) ON DELETE CASCADE,
    produto_id INTEGER REFERENCES produtos(id),
    descricao TEXT,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0
);

-- Criar tabela de contas a pagar
CREATE TABLE contas_pagar (
    id SERIAL PRIMARY KEY,
    descricao TEXT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id),
    data_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    categoria VARCHAR(50),
    documento VARCHAR(50),
    fornecedor VARCHAR(100),
    recorrente BOOLEAN NOT NULL DEFAULT FALSE,
    periodicidade VARCHAR(20),
    proxima_data DATE,
    conta_pai_id INTEGER REFERENCES contas_pagar(id) ON DELETE SET NULL
);

-- Criar tabela de movimentações de estoque
CREATE TABLE estoque_movimentacoes (
    id SERIAL PRIMARY KEY,
    produto_id INTEGER REFERENCES produtos(id),
    tipo VARCHAR(20) NOT NULL, -- entrada ou saida
    quantidade DECIMAL(10,2) NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    data_movimentacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo VARCHAR(50),
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id),
    referencia VARCHAR(50), -- número de nota fiscal, venda, etc.
    referencia_id INTEGER -- ID da venda ou orçamento relacionado
);

-- Criar tabela para controle de caixa (registra entradas e saídas)
CREATE TABLE caixa_controle (
    id SERIAL PRIMARY KEY,
    data_abertura TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_fechamento TIMESTAMP,
    usuario_abertura_id INTEGER REFERENCES usuarios(id),
    usuario_fechamento_id INTEGER REFERENCES usuarios(id),
    valor_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_final DECIMAL(10,2),
    observacoes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'aberto'
);

-- Criar tabela de movimentação do caixa
CREATE TABLE caixa (
    id SERIAL PRIMARY KEY,
    data_operacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descricao TEXT NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- entrada ou saida
    valor DECIMAL(10,2) NOT NULL,
    metodo_pagamento VARCHAR(50),
    usuario_id INTEGER REFERENCES usuarios(id),
    referencia VARCHAR(50), -- texto livre para referência
    orcamento_id INTEGER REFERENCES orcamentos(id) ON DELETE SET NULL,
    venda_id INTEGER REFERENCES vendas(id) ON DELETE SET NULL,
    conta_pagar_id INTEGER REFERENCES contas_pagar(id) ON DELETE SET NULL,
    cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
    estorno_id INTEGER REFERENCES estornos_pagamentos(id) ON DELETE SET NULL
);

-- Criar tabela de permissões de usuários
CREATE TABLE permissoes (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
    gerenciar_usuarios BOOLEAN NOT NULL DEFAULT FALSE,
    visualizar_relatorios_financeiros BOOLEAN NOT NULL DEFAULT FALSE,
    gerenciar_estoque BOOLEAN NOT NULL DEFAULT FALSE,
    gerenciar_produtos BOOLEAN NOT NULL DEFAULT FALSE,
    gerenciar_orcamentos BOOLEAN NOT NULL DEFAULT FALSE,
    gerenciar_vendas BOOLEAN NOT NULL DEFAULT FALSE,
    gerenciar_clientes BOOLEAN NOT NULL DEFAULT FALSE,
    gerenciar_caixa BOOLEAN NOT NULL DEFAULT FALSE,
    gerenciar_contas BOOLEAN NOT NULL DEFAULT FALSE,
    visualizar_relatorios_gerais BOOLEAN NOT NULL DEFAULT FALSE
);

-- Criar tabela de estornos de pagamentos
CREATE TABLE estornos_pagamentos (
    id SERIAL PRIMARY KEY,
    data_estorno TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor DECIMAL(10,2) NOT NULL,
    motivo TEXT NOT NULL,
    usuario_id INTEGER REFERENCES usuarios(id),
    conta_id INTEGER, -- id do registro original (pode ser de vendas, orçamentos, etc)
    tipo_conta VARCHAR(20) NOT NULL, -- venda, orcamento, conta_pagar, etc.
    observacoes TEXT
);

-- Criar tabela de pagamentos de contas
CREATE TABLE pagamentos_contas (
    id SERIAL PRIMARY KEY,
    conta_id INTEGER REFERENCES contas_pagar(id) ON DELETE CASCADE,
    data_pagamento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor DECIMAL(10,2) NOT NULL,
    metodo_pagamento VARCHAR(50) NOT NULL,
    observacoes TEXT,
    usuario_id INTEGER REFERENCES usuarios(id),
    caixa_id INTEGER REFERENCES caixa(id)
);

-- Inserir usuário administrador padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha, tipo) 
VALUES ('Administrador', 'admin@admin.com', '$2y$10$JcWM5zDYY6gCsuEfX9Jhr.mT7kMKmoiIIrKcPsVsHCpYBD2vxQU2C', 'admin');

-- Inserir usuário Atendente/Caixa (senha: caixa123)
INSERT INTO usuarios (nome, email, senha, tipo) 
VALUES ('Atendente', 'atendente@exemplo.com', '$2y$10$H1UYrkQoH7HPSFD8QBNV/OqVK4mBXGWgj44aK3UXkAE5njnvpjIou', 'atendente');

-- Inserir usuário Financeiro (senha: financeiro123)
INSERT INTO usuarios (nome, email, senha, tipo) 
VALUES ('Financeiro', 'financeiro@exemplo.com', '$2y$10$sW1ghhjjfQrJl7TrKffJn.4g/r7ZF5jBYlW9KQQ5o.IEY3ZAQ7xC.', 'financeiro');

-- Inserir permissões para administrador (tudo permitido)
INSERT INTO permissoes (
    usuario_id, 
    gerenciar_usuarios, 
    visualizar_relatorios_financeiros, 
    gerenciar_estoque, 
    gerenciar_produtos, 
    gerenciar_orcamentos, 
    gerenciar_vendas, 
    gerenciar_clientes, 
    gerenciar_caixa, 
    gerenciar_contas, 
    visualizar_relatorios_gerais
) VALUES (
    1, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE
);

-- Inserir permissões para atendente
INSERT INTO permissoes (
    usuario_id, 
    gerenciar_usuarios, 
    visualizar_relatorios_financeiros, 
    gerenciar_estoque, 
    gerenciar_produtos, 
    gerenciar_orcamentos, 
    gerenciar_vendas, 
    gerenciar_clientes, 
    gerenciar_caixa, 
    gerenciar_contas, 
    visualizar_relatorios_gerais
) VALUES (
    2, 
    FALSE, 
    FALSE, 
    TRUE, 
    FALSE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    FALSE, 
    FALSE
);

-- Inserir permissões para financeiro
INSERT INTO permissoes (
    usuario_id, 
    gerenciar_usuarios, 
    visualizar_relatorios_financeiros, 
    gerenciar_estoque, 
    gerenciar_produtos, 
    gerenciar_orcamentos, 
    gerenciar_vendas, 
    gerenciar_clientes, 
    gerenciar_caixa, 
    gerenciar_contas, 
    visualizar_relatorios_gerais
) VALUES (
    3, 
    FALSE, 
    TRUE, 
    TRUE, 
    FALSE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    TRUE, 
    FALSE
);

-- Inserir configurações iniciais
INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('nome_empresa', 'Sistema de Orçamentos e Vendas', 'Nome da empresa');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('email_contato', 'contato@empresa.com', 'Email de contato');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('telefone_contato', '(11) 9999-9999', 'Telefone de contato');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('endereco_empresa', 'Rua Exemplo, 123', 'Endereço da empresa');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('cidade_empresa', 'São Paulo', 'Cidade da empresa');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('estado_empresa', 'SP', 'Estado da empresa');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('cep_empresa', '01234-567', 'CEP da empresa');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('percentual_mao_obra', '30', 'Percentual padrão de mão de obra');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('dias_validade_orcamento', '15', 'Dias de validade padrão para orçamentos');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('prazo_entrega_padrao', '7 dias úteis', 'Prazo de entrega padrão');

INSERT INTO configuracoes (chave, valor, descricao) 
VALUES ('desconto_pagamento_vista', '5', 'Percentual de desconto para pagamento à vista');

-- Criar categoria padrão
INSERT INTO categorias (nome, descricao) 
VALUES ('Geral', 'Categoria geral para produtos');

-- Criar produto de exemplo
INSERT INTO produtos (codigo, nome, descricao, categoria_id, preco_custo, preco_venda, quantidade, unidade, estoque_minimo) 
VALUES ('CAL001', 'Calha Galvanizada 3m', 'Calha galvanizada de 3 metros', 1, 50.00, 100.00, 10, 'un', 5);

-- Criar cliente de exemplo
INSERT INTO clientes (nome, email, telefone, endereco, cidade, estado, cep) 
VALUES ('Cliente Exemplo', 'cliente@exemplo.com', '(11) 98765-4321', 'Rua Cliente, 456', 'São Paulo', 'SP', '04567-890');

-- Criar índices para melhor desempenho
CREATE INDEX idx_produtos_categoria ON produtos(categoria_id);
CREATE INDEX idx_orcamentos_cliente ON orcamentos(cliente_id);
CREATE INDEX idx_orcamentos_status ON orcamentos(status);
CREATE INDEX idx_vendas_cliente ON vendas(cliente_id);
CREATE INDEX idx_vendas_status ON vendas(status);
CREATE INDEX idx_contas_pagar_status ON contas_pagar(status);
CREATE INDEX idx_contas_pagar_vencimento ON contas_pagar(data_vencimento);
CREATE INDEX idx_caixa_data ON caixa(data_operacao);
CREATE INDEX idx_caixa_tipo ON caixa(tipo);
CREATE INDEX idx_movimentacoes_produto ON estoque_movimentacoes(produto_id);
CREATE INDEX idx_movimentacoes_data ON estoque_movimentacoes(data_movimentacao);

-- Configurar sequências
SELECT setval('usuarios_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM usuarios), false);
SELECT setval('configuracoes_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM configuracoes), false);
SELECT setval('clientes_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM clientes), false);
SELECT setval('categorias_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM categorias), false);
SELECT setval('produtos_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM produtos), false);
SELECT setval('orcamentos_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM orcamentos), false);
SELECT setval('orcamento_itens_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM orcamento_itens), false);
SELECT setval('vendas_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM vendas), false);
SELECT setval('vendas_itens_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM vendas_itens), false);
SELECT setval('contas_pagar_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM contas_pagar), false);
SELECT setval('estoque_movimentacoes_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM estoque_movimentacoes), false);
SELECT setval('caixa_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM caixa), false);
SELECT setval('caixa_controle_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM caixa_controle), false);
SELECT setval('permissoes_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM permissoes), false);
SELECT setval('estornos_pagamentos_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM estornos_pagamentos), false);
SELECT setval('pagamentos_contas_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM pagamentos_contas), false);