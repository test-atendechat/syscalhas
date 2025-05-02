-- --------------------------------------------------------
-- Sistema de Orçamentos e Vendas - Backup Completo
-- Data de Exportação: 2025-04-30
-- --------------------------------------------------------

-- Primeiro, limpar as tabelas existentes, respeitando as dependências
DO $$
BEGIN
    -- Desativar temporariamente verificação de chaves estrangeiras
    SET session_replication_role = 'replica';
    
    -- Limpar todas as tabelas
    TRUNCATE TABLE estornos_pagamentos CASCADE;
    TRUNCATE TABLE pagamentos_contas CASCADE;
    TRUNCATE TABLE contas_pagar CASCADE;
    TRUNCATE TABLE estoque_movimentacoes CASCADE;
    TRUNCATE TABLE caixa_controle CASCADE;
    TRUNCATE TABLE caixa CASCADE;
    TRUNCATE TABLE vendas_itens CASCADE;
    TRUNCATE TABLE vendas CASCADE;
    TRUNCATE TABLE orcamento_itens CASCADE;
    TRUNCATE TABLE orcamentos CASCADE;
    TRUNCATE TABLE clientes CASCADE;
    TRUNCATE TABLE produtos CASCADE;
    TRUNCATE TABLE categorias CASCADE;
    TRUNCATE TABLE permissoes CASCADE;
    TRUNCATE TABLE usuarios CASCADE;
    TRUNCATE TABLE configuracoes CASCADE;
    
    -- Reativar verificação de chaves estrangeiras
    SET session_replication_role = 'origin';
END
$$;

-- Inserir dados da tabela configuracoes
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

-- Inserir dados da tabela usuarios
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES 
(1, 'Administrador', 'admin@admin.com', '$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK', 'admin', true, '2025-04-29 01:02:32.26967');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES 
(4, 'Financeiro', 'financeiro@exemplo.com', '$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK', 'usuario', true, '2025-04-29 22:50:41.853126');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES 
(3, 'Atendente', 'atendente@atendente.com', '$2y$10$WjRfcwHvPfbWWpyDM/KZ9.kRrDOdrRDcmMrw2iudnAlhli5zI6V8S', 'usuario', true, '2025-04-29 22:50:41.853126');

-- Inserir dados da tabela permissoes
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES 
(1, 1, true, true, true, true, true, true, true, true, true, true, '2025-04-29 22:35:17.976187');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES 
(2, 3, false, false, true, false, true, true, true, true, false, false, '2025-04-29 22:51:00.378936');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES 
(3, 4, false, true, true, false, true, true, true, true, true, false, '2025-04-29 22:51:18.493191');

-- Inserir dados da tabela categorias
INSERT INTO categorias (id, nome, descricao) VALUES (1, 'Cortes', NULL);
INSERT INTO categorias (id, nome, descricao) VALUES (2, 'Parafusos', NULL);

-- Inserir dados da tabela produtos
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES 
(2, 'C12', 'Corte 12', 1, 'metro', 8.50, 45.16, 50.00, '', '2025-04-29 20:08:26.091834', 4.50);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES 
(3, 'B1', 'Parafuso Brocante Panela Phillips', 2, 'unidade', 0.20, 9945.00, 500.00, '', '2025-04-29 20:18:27.357121', 0.10);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES 
(1, 'C10', 'Corte 10', 1, 'metro', 8.12, 478.55, 50.00, '', '2025-04-29 20:04:55.577645', 4.08);

-- Inserir dados da tabela clientes
INSERT INTO clientes (id, nome, tipo, cpf_cnpj, email, telefone, endereco, cidade, estado, cep, observacoes, data_cadastro) VALUES 
(1, 'Sem Identificação', 'fisica', '', '', '', '', '', '', '', '', '2025-04-29 20:01:03.918571');

-- Dados da tabela caixa
INSERT INTO caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, usuario_id, observacoes) VALUES 
(1, '2025-04-29', 'entrada', 'Abertura de caixa', 100.00, 'dinheiro', 1, 'Saldo inicial');

-- Dados da tabela caixa_controle
INSERT INTO caixa_controle (id, data_abertura, saldo_inicial, status, usuario_id_abertura) VALUES 
(1, '2025-04-29 20:00:00', 100.00, 'aberto', 1);

-- Dados das movimentações de estoque
INSERT INTO estoque_movimentacoes (id, produto_id, data_movimentacao, tipo, quantidade, saldo_anterior, saldo_atual, usuario_id, observacoes) VALUES
(1, 1, '2025-04-29 20:04:55', 'entrada', 500.00, 0.00, 500.00, 1, 'Estoque inicial'),
(2, 2, '2025-04-29 20:08:26', 'entrada', 50.00, 0.00, 50.00, 1, 'Estoque inicial'),
(3, 3, '2025-04-29 20:18:27', 'entrada', 10000.00, 0.00, 10000.00, 1, 'Estoque inicial');

-- Atualizar sequências
SELECT setval('configuracoes_id_seq', (SELECT MAX(id) FROM configuracoes));
SELECT setval('usuarios_id_seq', (SELECT MAX(id) FROM usuarios));
SELECT setval('permissoes_id_seq', (SELECT MAX(id) FROM permissoes));
SELECT setval('categorias_id_seq', (SELECT MAX(id) FROM categorias));
SELECT setval('produtos_id_seq', (SELECT MAX(id) FROM produtos));
SELECT setval('clientes_id_seq', (SELECT MAX(id) FROM clientes));
SELECT setval('caixa_id_seq', (SELECT MAX(id) FROM caixa));
SELECT setval('caixa_controle_id_seq', (SELECT MAX(id) FROM caixa_controle));
SELECT setval('estoque_movimentacoes_id_seq', (SELECT MAX(id) FROM estoque_movimentacoes));
SELECT setval('orcamentos_id_seq', COALESCE((SELECT MAX(id) FROM orcamentos), 0));
SELECT setval('orcamento_itens_id_seq', COALESCE((SELECT MAX(id) FROM orcamento_itens), 0));
SELECT setval('vendas_id_seq', COALESCE((SELECT MAX(id) FROM vendas), 0));
SELECT setval('vendas_itens_id_seq', COALESCE((SELECT MAX(id) FROM vendas_itens), 0));
SELECT setval('contas_pagar_id_seq', COALESCE((SELECT MAX(id) FROM contas_pagar), 0));
SELECT setval('pagamentos_contas_id_seq', COALESCE((SELECT MAX(id) FROM pagamentos_contas), 0));
SELECT setval('estornos_pagamentos_id_seq', COALESCE((SELECT MAX(id) FROM estornos_pagamentos), 0));