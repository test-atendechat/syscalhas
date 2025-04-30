-- Script de instalação com correções para o relatório financeiro
-- Data: 30/04/2025

-- PARTE 1: Importar o backup completo do banco de dados
\i db_backup_completo_com_correcoes.sql

-- PARTE 2: Instruções para alterações nos arquivos PHP
-- Localizar e modificar o arquivo relatorio_financeiro.php com as seguintes alterações:

-- 1. Alterar o cálculo do custo dos produtos vendidos para usar apenas valores pagos:
/*
// Calcular o custo dos produtos vendidos, com base APENAS nas vendas pagas (total ou parcialmente)
// Excluímos vendas a prazo sem pagamento para não contabilizar custo de algo que não foi recebido
$custo_total_vendas = $valor_pago_vendas * $proporcao_custo;

// A variável custo_total_vendas já contém o custo dos produtos nas vendas PAGAS
$custo_vendas_pagas = $custo_total_vendas;
*/

-- 2. Alterar os títulos nas tabelas para refletir que os valores se referem apenas a vendas pagas:
/*
- Alterar "Valor Total de Vendas" para "Valor Total Recebido"
- Alterar "Custo dos Produtos Vendidos" para "Custo dos Produtos Vendidos (Pagos)"
- Alterar "Valor Total de Vendas" para "Valor Recebido de Vendas" na tabela
- Alterar "Custo dos Materiais (Vendas)" para "Custo dos Materiais (Vendas Pagas)"
*/

-- PARTE 3: Correções adicionais no banco de dados, se necessário
-- 1. Corrigir valores nulos nas permissões (caso existam)
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

-- 2. Garantir que a tabela caixa_controle exista (importante para o controle do caixa)
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

/*
INSTRUÇÕES DE INSTALAÇÃO:

1. Execute este script SQL no seu banco de dados PostgreSQL:
   psql -U seu_usuario -d seu_banco -f instalar_com_correcoes.sql

2. Se estiver usando o servidor web diretamente, copie os arquivos PHP com as correções:
   - relatorio_financeiro.php (com as alterações listadas)

3. Verifique se todas as dependências estão instaladas no servidor.

4. Acesse o sistema e faça login com as credenciais de administrador.

5. Verifique se o relatório financeiro está mostrando corretamente apenas os valores pagos.
*/