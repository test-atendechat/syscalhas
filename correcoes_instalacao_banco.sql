-- Script com as correções para instalação do banco de dados
-- Data: 30/04/2025

-- PARTE 1: Correções para valores booleanos nas permissões
-- Essas correções devem ser aplicadas após a instalação inicial

-- Corrigir todos os valores NULL das permissões, definindo-os como false
UPDATE permissoes SET gerenciar_usuarios = false WHERE gerenciar_usuarios IS NULL;
UPDATE permissoes SET visualizar_relatorios_financeiros = false WHERE visualizar_relatorios_financeiros IS NULL;
UPDATE permissoes SET gerenciar_estoque = false WHERE gerenciar_estoque IS NULL;
UPDATE permissoes SET gerenciar_produtos = false WHERE gerenciar_produtos IS NULL;
UPDATE permissoes SET gerenciar_orcamentos = false WHERE gerenciar_orcamentos IS NULL;
UPDATE permissoes SET gerenciar_vendas = false WHERE gerenciar_vendas IS NULL;
UPDATE permissoes SET gerenciar_clientes = false WHERE gerenciar_clientes IS NULL;
UPDATE permissoes SET gerenciar_caixa = false WHERE gerenciar_caixa IS NULL;
UPDATE permissoes SET gerenciar_contas = false WHERE gerenciar_contas IS NULL;
UPDATE permissoes SET visualizar_relatorios_gerais = false WHERE visualizar_relatorios_gerais IS NULL;

-- PARTE 2: Alternativa mais segura - recriar as permissões para os usuários não-admin
-- Se as correções acima não funcionarem, excluir e recriar as permissões adequadamente
DELETE FROM permissoes WHERE id = 2 OR id = 3 OR id = 4; 

-- Recriar a permissão para o usuário Atendente (id = 3)
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
    false, 
    false, 
    true, 
    false, 
    true, 
    true, 
    true, 
    true, 
    false, 
    false
);

-- Recriar a permissão para o usuário Financeiro (id = 4)
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
    4, 
    false, 
    true, 
    true, 
    false, 
    true, 
    true, 
    true, 
    true, 
    true, 
    false
);

-- PARTE 3: Garantir que a tabela caixa_controle exista com a estrutura correta
CREATE TABLE IF NOT EXISTS caixa_controle (
    id SERIAL PRIMARY KEY,
    data_abertura TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_fechamento TIMESTAMP NULL,
    usuario_abertura_id INTEGER REFERENCES usuarios(id),
    usuario_fechamento_id INTEGER REFERENCES usuarios(id),
    valor_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_final DECIMAL(10,2) NULL,
    observacoes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'aberto'
);

-- PARTE 4: Garantir sequências de ID corretas
-- Corrigir a sequência de ID da tabela de produtos
SELECT setval('produtos_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM produtos), false);

-- Corrigir a sequência de ID da tabela de clientes
SELECT setval('clientes_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM clientes), false);

-- Corrigir a sequência de ID da tabela de orçamentos
SELECT setval('orcamentos_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM orcamentos), false);

-- Corrigir a sequência de ID da tabela de vendas
SELECT setval('vendas_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM vendas), false);

-- Corrigir a sequência de ID da tabela de categorias
SELECT setval('categorias_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM categorias), false);

-- PARTE 5: Corrigir possíveis problemas com valores nulos em outras tabelas
-- Garantir que não haja valores NULL para status nas tabelas principais
UPDATE orcamentos SET status = 'pendente' WHERE status IS NULL;
UPDATE vendas SET status = 'pendente' WHERE status IS NULL;
UPDATE contas_pagar SET status = 'pendente' WHERE status IS NULL;

-- Garantir consistência em valores de métodos de pagamento
UPDATE caixa SET metodo_pagamento = 'dinheiro' WHERE metodo_pagamento IS NULL;

-- PARTE 6: Criar índices adicionais para melhorar o desempenho
CREATE INDEX IF NOT EXISTS idx_orcamentos_cliente ON orcamentos(cliente_id);
CREATE INDEX IF NOT EXISTS idx_vendas_cliente ON vendas(cliente_id);
CREATE INDEX IF NOT EXISTS idx_caixa_data ON caixa(data_operacao);
CREATE INDEX IF NOT EXISTS idx_orcamentos_data ON orcamentos(data_criacao);
CREATE INDEX IF NOT EXISTS idx_vendas_data ON vendas(data_venda);

-- PARTE 7: Corrigir senha do administrador (se necessário)
-- Descomente esta linha se precisar resetar a senha do admin para 'admin123'
-- UPDATE usuarios SET senha = '$2y$10$JcWM5zDYY6gCsuEfX9Jhr.mT7kMKmoiIIrKcPsVsHCpYBD2vxQU2C' WHERE email = 'admin@admin.com';