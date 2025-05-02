<?php
// Inicialização de variáveis
$titulo = "Instalação do Banco de Dados";
$mensagens = [];
$script_executado = false;

// Função para verificar se o arquivo de configuração local existe
function verificarConfigLocal() {
    $config_local = __DIR__ . '/includes/config.local.php';
    if (!file_exists($config_local)) {
        return false;
    }
    return true;
}

// Função para conectar ao banco de dados
function conectarBancoDados() {
    try {
        require_once('includes/config.php');
        
        // Verificar se as constantes foram definidas
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            throw new Exception("Configurações de banco de dados incompletas");
        }
        
        // Construir DSN
        $dsn = (DB_TYPE == 'pgsql') ? 
            "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT :
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
        
        // Conectar
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return [true, $db, null];
    } catch (PDOException $e) {
        return [false, null, $e->getMessage()];
    } catch (Exception $e) {
        return [false, null, $e->getMessage()];
    }
}

// Função para executar um script SQL
function executarScriptSQL($db, $scriptSQL) {
    $consultas = 0;
    $sucesso = 0;
    $erros = [];
    
    // Remover comentários e dividir o script em consultas individuais
    $scriptSQL = preg_replace('/--.*$/m', '', $scriptSQL);
    $consultas_array = explode(';', $scriptSQL);
    
    // Iniciar transação
    $db->beginTransaction();
    
    try {
        foreach ($consultas_array as $consulta) {
            $consulta = trim($consulta);
            if (empty($consulta)) continue;
            
            $consultas++;
            try {
                $db->exec($consulta);
                $sucesso++;
            } catch (PDOException $e) {
                $erros[] = [
                    'consulta' => $consulta,
                    'erro' => $e->getMessage()
                ];
            }
        }
        
        // Se não houve erros, confirmar transação
        if (count($erros) == 0) {
            $db->commit();
            return [
                'sucesso' => true,
                'consultas' => $consultas,
                'executadas' => $sucesso,
                'erros' => $erros
            ];
        } else {
            // Se houve erros, reverter transação
            $db->rollback();
            return [
                'sucesso' => false,
                'consultas' => $consultas,
                'executadas' => $sucesso,
                'erros' => $erros
            ];
        }
    } catch (Exception $e) {
        // Em caso de erro geral, reverter transação
        if ($db->inTransaction()) {
            $db->rollback();
        }
        return [
            'sucesso' => false,
            'consultas' => $consultas,
            'executadas' => $sucesso,
            'erros' => [['consulta' => 'Erro geral', 'erro' => $e->getMessage()]]
        ];
    }
}

// Função para verificar se o banco já tem tabelas
function verificarTabelasExistentes($db) {
    try {
        $tabelas = [];
        $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tabelas[] = $row['table_name'];
        }
        return $tabelas;
    } catch (Exception $e) {
        return [];
    }
}

// Processar a instalação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se há configuração de banco
    if (!verificarConfigLocal()) {
        $mensagens[] = [
            'tipo' => 'erro',
            'texto' => "Arquivo de configuração local não encontrado. Por favor, crie o arquivo includes/config.local.php seguindo o modelo config.local.php.exemplo."
        ];
    } else {
        // Tentar conectar ao banco
        list($conectado, $db, $erro_conexao) = conectarBancoDados();
        
        if (!$conectado) {
            $mensagens[] = [
                'tipo' => 'erro',
                'texto' => "Erro ao conectar ao banco de dados: " . $erro_conexao
            ];
        } else {
            // Verificar se já existem tabelas
            $tabelas_existentes = verificarTabelasExistentes($db);
            $forcar_reinstalacao = isset($_POST['forcar_reinstalacao']) && $_POST['forcar_reinstalacao'] == 1;
            
            if (count($tabelas_existentes) > 0 && !$forcar_reinstalacao) {
                $mensagens[] = [
                    'tipo' => 'aviso',
                    'texto' => "⚠️ O banco de dados já contém " . count($tabelas_existentes) . " tabelas. A reinstalação apagará todos os dados existentes."
                ];
                
                // Listar as tabelas existentes
                $tabelas_texto = implode(', ', $tabelas_existentes);
                $mensagens[] = [
                    'tipo' => 'info',
                    'texto' => "Tabelas encontradas: " . $tabelas_texto
                ];
            } else {
                // Capturar dados do formulário
                $script_sql = isset($_POST['script_sql']) ? $_POST['script_sql'] : '';
                $arquivo_sql = isset($_FILES['arquivo_sql']) ? $_FILES['arquivo_sql'] : null;
                $usar_padrao = isset($_POST['usar_padrao']) && $_POST['usar_padrao'] == 1;
                
                // Definir o SQL que será usado
                $script_para_executar = '';
                
                // Verificar qual método foi utilizado (texto, arquivo ou embutido)
                if (!empty($script_sql)) {
                    // Usar o script fornecido diretamente
                    $script_para_executar = $script_sql;
                    $origem = "texto fornecido";
                } elseif ($arquivo_sql && $arquivo_sql['error'] == 0) {
                    // Ler o arquivo enviado
                    $script_para_executar = file_get_contents($arquivo_sql['tmp_name']);
                    $origem = "arquivo enviado";
                } elseif ($usar_padrao) {
                    // Usar o script SQL embutido diretamente no código
                    $script_para_executar = '-- --------------------------------------------------------
-- Sistema de Orçamentos e Vendas
-- Data de Exportação: 2025-04-29 21:42:18
-- --------------------------------------------------------

BEGIN;

-- Estrutura da tabela `configuracoes`
DROP TABLE IF EXISTS configuracoes CASCADE;
CREATE TABLE configuracoes (
    id integer NOT NULL DEFAULT nextval(\'configuracoes_id_seq\'::regclass) PRIMARY KEY,
    chave character varying(50) NOT NULL,
    valor text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cor_principal character varying(20) DEFAULT \'#0d6efd\'::character varying
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'configuracoes\', \'id\'), COALESCE((SELECT MAX(id) FROM configuracoes), 1), false);

-- Dados da tabela `configuracoes`
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (12, \'cor_secundaria\', \'#6c757d\', \'2025-04-29 19:15:31.577241\', \'2025-04-29 19:15:31.577241\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (13, \'cor_aprovado\', \'#198754\', \'2025-04-29 19:15:31.577241\', \'2025-04-29 19:15:31.577241\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (14, \'cor_pendente\', \'#ffc107\', \'2025-04-29 19:15:31.577241\', \'2025-04-29 19:15:31.577241\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (15, \'cor_rejeitado\', \'#dc3545\', \'2025-04-29 19:15:31.577241\', \'2025-04-29 19:15:31.577241\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (16, \'cor_primaria\', \'#0d6efd\', \'2025-04-29 20:00:17.935304\', \'2025-04-29 20:00:17.935304\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (7, \'estoque_alerta_minimo\', 50, \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (1, \'empresa_nome\', \'Grupo Sandro Calhas LTDA\', \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (2, \'empresa_telefone\', \'(19) 99262-0970\', \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (3, \'empresa_email\', \'contato@sandrocalhas.com.br\', \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (4, \'empresa_endereco\', \'Rua Carlos Pulici, 387, Vila Franco, Descalvado - SP\', \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (5, \'empresa_cnpj\', \'48.998.641/0001-03\', \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (6, \'taxa_padrao_mao_obra\', 100, \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (9, \'desconto_pagamento_vista\', 10, \'2025-04-29 15:47:38.438476\', \'2025-04-29 15:47:38.438476\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (10, \'max_parcelas\', 12, \'2025-04-29 15:47:38.438476\', \'2025-04-29 15:47:38.438476\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (8, \'dias_validade_orcamento\', 30, \'2025-04-29 13:03:15.615062\', \'2025-04-29 13:03:15.615062\', \'#0d6efd\');
INSERT INTO configuracoes (id, chave, valor, created_at, updated_at, cor_principal) VALUES (11, \'cor_principal\', \'#0d6efd\', \'2025-04-29 19:15:31.577241\', \'2025-04-29 19:15:31.577241\', \'#0d6efd\');

-- --------------------------------------------------------

-- Estrutura da tabela `usuarios`
DROP TABLE IF EXISTS usuarios CASCADE;
CREATE TABLE usuarios (
    id integer NOT NULL DEFAULT nextval(\'usuarios_id_seq\'::regclass) PRIMARY KEY,
    nome character varying(100) NOT NULL,
    email character varying(100) NOT NULL,
    senha character varying(255) NOT NULL,
    nivel character varying(20) NOT NULL DEFAULT \'usuario\'::character varying,
    ativo boolean NOT NULL DEFAULT true,
    data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'usuarios\', \'id\'), COALESCE((SELECT MAX(id) FROM usuarios), 1), false);

-- Dados da tabela `usuarios`
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES (1, \'Administrador\', \'admin@admin.com\', \'$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK\', \'admin\', \'1\', \'2025-04-29 01:02:32.26967\');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES (4, \'Financeiro\', \'financeiro@exemplo.com\', \'$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK\', \'usuario\', \'1\', \'2025-04-29 22:50:41.853126\');
INSERT INTO usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) VALUES (3, \'Atendente\', \'atendente@atendente.com\', \'$2y$10$WjRfcwHvPfbWWpyDM/KZ9.kRrDOdrRDcmMrw2iudnAlhli5zI6V8S\', \'usuario\', \'1\', \'2025-04-29 22:50:41.853126\');

-- --------------------------------------------------------

-- Estrutura da tabela `permissoes`
DROP TABLE IF EXISTS permissoes CASCADE;
CREATE TABLE permissoes (
    id integer NOT NULL DEFAULT nextval(\'permissoes_id_seq\'::regclass) PRIMARY KEY,
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

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'permissoes\', \'id\'), COALESCE((SELECT MAX(id) FROM permissoes), 1), false);

-- Dados da tabela `permissoes`
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES (1, 1, \'1\', \'1\', \'1\', \'1\', \'1\', \'1\', \'1\', \'1\', \'1\', \'1\', \'2025-04-29 22:35:17.976187\');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES (2, 3, \'\', \'\', \'1\', \'\', \'1\', \'1\', \'1\', \'1\', \'\', \'\', \'2025-04-29 22:51:00.378936\');
INSERT INTO permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) VALUES (3, 4, \'\', \'1\', \'1\', \'\', \'1\', \'1\', \'1\', \'1\', \'1\', \'\', \'2025-04-29 22:51:18.493191\');

-- --------------------------------------------------------

-- Estrutura da tabela `categorias`
DROP TABLE IF EXISTS categorias CASCADE;
CREATE TABLE categorias (
    id integer NOT NULL DEFAULT nextval(\'categorias_id_seq\'::regclass) PRIMARY KEY,
    nome character varying(100) NOT NULL,
    descricao character varying(255)
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'categorias\', \'id\'), COALESCE((SELECT MAX(id) FROM categorias), 1), false);

-- Dados da tabela `categorias`
INSERT INTO categorias (id, nome, descricao) VALUES (1, \'Cortes\', NULL);
INSERT INTO categorias (id, nome, descricao) VALUES (2, \'Parafusos\', NULL);

-- --------------------------------------------------------

-- Estrutura da tabela `produtos`
DROP TABLE IF EXISTS produtos CASCADE;
CREATE TABLE produtos (
    id integer NOT NULL DEFAULT nextval(\'produtos_id_seq\'::regclass) PRIMARY KEY,
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

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'produtos\', \'id\'), COALESCE((SELECT MAX(id) FROM produtos), 1), false);

-- Dados da tabela `produtos`
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES (2, \'C12\', \'Corte 12\', 1, \'metro\', 8.50, 45.16, 50.00, \'\', \'2025-04-29 20:08:26.091834\', 4.50);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES (3, \'B1\', \'Parafuso Brocante Panela Phillips\', 2, \'unidade\', 0.20, 9945.00, 500.00, \'\', \'2025-04-29 20:18:27.357121\', 0.10);
INSERT INTO produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) VALUES (1, \'C10\', \'Corte 10\', 1, \'metro\', 8.12, 478.55, 50.00, \'\', \'2025-04-29 20:04:55.577645\', 4.08);

-- --------------------------------------------------------

-- Estrutura da tabela `clientes`
DROP TABLE IF EXISTS clientes CASCADE;
CREATE TABLE clientes (
    id integer NOT NULL DEFAULT nextval(\'clientes_id_seq\'::regclass) PRIMARY KEY,
    nome character varying(100) NOT NULL,
    tipo character varying(10) NOT NULL DEFAULT \'fisica\'::character varying,
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

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'clientes\', \'id\'), COALESCE((SELECT MAX(id) FROM clientes), 1), false);

-- Dados da tabela `clientes`
INSERT INTO clientes (id, nome, tipo, cpf_cnpj, email, telefone, endereco, cidade, estado, cep, observacoes, data_cadastro) VALUES (1, \'Sem Identificação\', \'fisica\', \'\', \'\', \'\', \'\', \'\', \'\', \'\', \'\', \'2025-04-29 20:01:03.918571\');

-- --------------------------------------------------------

-- Estrutura da tabela `orcamentos`
DROP TABLE IF EXISTS orcamentos CASCADE;
CREATE TABLE orcamentos (
    id integer NOT NULL DEFAULT nextval(\'orcamentos_id_seq\'::regclass) PRIMARY KEY,
    numero character varying(20) NOT NULL,
    cliente_id integer NOT NULL,
    data_criacao timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_validade date,
    status character varying(20) NOT NULL DEFAULT \'pendente\'::character varying,
    taxa_mao_obra numeric NOT NULL DEFAULT 0,
    valor_produtos numeric NOT NULL DEFAULT 0,
    valor_mao_obra numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    observacoes text,
    codigo_acesso character varying(32) NOT NULL,
    usuario_id integer,
    forma_pagamento character varying(50),
    status_pagamento character varying(20) NOT NULL DEFAULT \'pendente\'::character varying,
    status_execucao character varying(20) NOT NULL DEFAULT \'pendente\'::character varying,
    data_pagamento date,
    data_finalizacao date
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'orcamentos\', \'id\'), COALESCE((SELECT MAX(id) FROM orcamentos), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `orcamento_itens`
DROP TABLE IF EXISTS orcamento_itens CASCADE;
CREATE TABLE orcamento_itens (
    id integer NOT NULL DEFAULT nextval(\'orcamento_itens_id_seq\'::regclass) PRIMARY KEY,
    orcamento_id integer NOT NULL,
    produto_id integer NOT NULL,
    quantidade numeric NOT NULL DEFAULT 0,
    valor_unitario numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    observacoes text
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'orcamento_itens\', \'id\'), COALESCE((SELECT MAX(id) FROM orcamento_itens), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `vendas`
DROP TABLE IF EXISTS vendas CASCADE;
CREATE TABLE vendas (
    id integer NOT NULL DEFAULT nextval(\'vendas_id_seq\'::regclass) PRIMARY KEY,
    numero character varying(20) NOT NULL,
    cliente_id integer NOT NULL,
    data_venda timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_produtos numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    forma_pagamento character varying(50),
    status_pagamento character varying(20) NOT NULL DEFAULT \'pendente\'::character varying,
    status_entrega character varying(20) NOT NULL DEFAULT \'pendente\'::character varying,
    observacoes text,
    usuario_id integer,
    orcamento_id integer,
    data_pagamento date,
    data_entrega date,
    nfe_status character varying(50),
    nfe_chave character varying(50),
    nfe_numero character varying(20)
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'vendas\', \'id\'), COALESCE((SELECT MAX(id) FROM vendas), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `vendas_itens`
DROP TABLE IF EXISTS vendas_itens CASCADE;
CREATE TABLE vendas_itens (
    id integer NOT NULL DEFAULT nextval(\'vendas_itens_id_seq\'::regclass) PRIMARY KEY,
    venda_id integer NOT NULL,
    produto_id integer NOT NULL,
    quantidade numeric NOT NULL DEFAULT 0,
    valor_unitario numeric NOT NULL DEFAULT 0,
    valor_total numeric NOT NULL DEFAULT 0,
    observacoes text,
    custo_unitario numeric NOT NULL DEFAULT 0
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'vendas_itens\', \'id\'), COALESCE((SELECT MAX(id) FROM vendas_itens), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `caixa`
DROP TABLE IF EXISTS caixa CASCADE;
CREATE TABLE caixa (
    id integer NOT NULL DEFAULT nextval(\'caixa_id_seq\'::regclass) PRIMARY KEY,
    data_operacao date NOT NULL DEFAULT CURRENT_DATE,
    hora_operacao time without time zone NOT NULL DEFAULT CURRENT_TIME,
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

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'caixa\', \'id\'), COALESCE((SELECT MAX(id) FROM caixa), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `caixa_controle`
DROP TABLE IF EXISTS caixa_controle CASCADE;
CREATE TABLE caixa_controle (
    id integer NOT NULL DEFAULT nextval(\'caixa_controle_id_seq\'::regclass) PRIMARY KEY,
    data_abertura timestamp without time zone,
    saldo_inicial numeric DEFAULT 0,
    saldo_final numeric,
    data_fechamento timestamp without time zone,
    usuario_id_abertura integer,
    usuario_id_fechamento integer,
    observacoes text,
    status character varying(20) NOT NULL DEFAULT \'fechado\'::character varying
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'caixa_controle\', \'id\'), COALESCE((SELECT MAX(id) FROM caixa_controle), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `estoque_movimentacoes`
DROP TABLE IF EXISTS estoque_movimentacoes CASCADE;
CREATE TABLE estoque_movimentacoes (
    id integer NOT NULL DEFAULT nextval(\'estoque_movimentacoes_id_seq\'::regclass) PRIMARY KEY,
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

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'estoque_movimentacoes\', \'id\'), COALESCE((SELECT MAX(id) FROM estoque_movimentacoes), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `contas_pagar`
DROP TABLE IF EXISTS contas_pagar CASCADE;
CREATE TABLE contas_pagar (
    id integer NOT NULL DEFAULT nextval(\'contas_pagar_id_seq\'::regclass) PRIMARY KEY,
    descricao character varying(255) NOT NULL,
    valor numeric NOT NULL DEFAULT 0,
    data_vencimento date NOT NULL,
    tipo character varying(50) NOT NULL DEFAULT \'normal\'::character varying,
    status character varying(20) NOT NULL DEFAULT \'pendente\'::character varying,
    data_pagamento date,
    valor_pago numeric,
    categoria character varying(50),
    fornecedor character varying(100),
    recorrente boolean NOT NULL DEFAULT false,
    intervalo_recorrencia integer,
    unidade_recorrencia character varying(20) DEFAULT \'meses\'::character varying,
    data_fim_recorrencia date,
    usuario_id integer,
    data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacoes text
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'contas_pagar\', \'id\'), COALESCE((SELECT MAX(id) FROM contas_pagar), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `pagamentos_contas`
DROP TABLE IF EXISTS pagamentos_contas CASCADE;
CREATE TABLE pagamentos_contas (
    id integer NOT NULL DEFAULT nextval(\'pagamentos_contas_id_seq\'::regclass) PRIMARY KEY,
    conta_id integer NOT NULL,
    data_pagamento date NOT NULL,
    valor numeric NOT NULL DEFAULT 0,
    forma_pagamento character varying(50) NOT NULL,
    observacoes text,
    usuario_id integer,
    data_registro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'pagamentos_contas\', \'id\'), COALESCE((SELECT MAX(id) FROM pagamentos_contas), 1), false);

-- --------------------------------------------------------

-- Estrutura da tabela `estornos_pagamentos`
DROP TABLE IF EXISTS estornos_pagamentos CASCADE;
CREATE TABLE estornos_pagamentos (
    id integer NOT NULL DEFAULT nextval(\'estornos_pagamentos_id_seq\'::regclass) PRIMARY KEY,
    caixa_id integer,
    data_estorno timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor numeric NOT NULL DEFAULT 0,
    motivo text,
    usuario_id integer NOT NULL,
    venda_id integer,
    orcamento_id integer,
    conta_id integer
);

-- Resetar a sequência para a coluna id
SELECT setval(pg_get_serial_sequence(\'estornos_pagamentos\', \'id\'), COALESCE((SELECT MAX(id) FROM estornos_pagamentos), 1), false);

-- --------------------------------------------------------

COMMIT;';
                    
                    $origem = "script embutido no sistema";
                } else {
                    $mensagens[] = [
                        'tipo' => 'erro',
                        'texto' => "Nenhum script SQL fornecido para instalação."
                    ];
                    $script_para_executar = '';
                }
                
                // Executar o script se tiver conteúdo
                if (!empty($script_para_executar)) {
                    // Criar as sequências primeiro se não existirem
                    $criar_sequencias_sql = "
                    DO $$
                    BEGIN
                        -- Verificar e criar sequências se não existirem
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'configuracoes_id_seq') THEN
                            CREATE SEQUENCE configuracoes_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'usuarios_id_seq') THEN
                            CREATE SEQUENCE usuarios_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'permissoes_id_seq') THEN
                            CREATE SEQUENCE permissoes_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'categorias_id_seq') THEN
                            CREATE SEQUENCE categorias_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'produtos_id_seq') THEN
                            CREATE SEQUENCE produtos_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'clientes_id_seq') THEN
                            CREATE SEQUENCE clientes_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'orcamentos_id_seq') THEN
                            CREATE SEQUENCE orcamentos_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'orcamento_itens_id_seq') THEN
                            CREATE SEQUENCE orcamento_itens_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'vendas_id_seq') THEN
                            CREATE SEQUENCE vendas_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'vendas_itens_id_seq') THEN
                            CREATE SEQUENCE vendas_itens_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'caixa_id_seq') THEN
                            CREATE SEQUENCE caixa_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'caixa_controle_id_seq') THEN
                            CREATE SEQUENCE caixa_controle_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'estoque_movimentacoes_id_seq') THEN
                            CREATE SEQUENCE estoque_movimentacoes_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'contas_pagar_id_seq') THEN
                            CREATE SEQUENCE contas_pagar_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'pagamentos_contas_id_seq') THEN
                            CREATE SEQUENCE pagamentos_contas_id_seq START 1;
                        END IF;
                        
                        IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = 'estornos_pagamentos_id_seq') THEN
                            CREATE SEQUENCE estornos_pagamentos_id_seq START 1;
                        END IF;
                    END;
                    $$;";

                    // Executar a criação de sequências primeiro
                    try {
                        $db->exec($criar_sequencias_sql);
                    } catch (Exception $e) {
                        // Ignorar erros na criação de sequências
                        $mensagens[] = [
                            'tipo' => 'aviso',
                            'texto' => "Aviso ao criar sequências: " . $e->getMessage()
                        ];
                    }

                    // Agora executar o script principal
                    $resultado = executarScriptSQL($db, $script_para_executar);
                    $script_executado = true;
                    
                    if ($resultado['sucesso']) {
                        $mensagens[] = [
                            'tipo' => 'sucesso',
                            'texto' => "✅ Banco de dados instalado com sucesso! Foram executadas {$resultado['executadas']} de {$resultado['consultas']} consultas usando o {$origem}."
                        ];
                    } else {
                        $mensagens[] = [
                            'tipo' => 'erro',
                            'texto' => "❌ Erro ao instalar banco de dados. Foram executadas {$resultado['executadas']} de {$resultado['consultas']} consultas."
                        ];
                        
                        // Listar os erros
                        foreach ($resultado['erros'] as $erro) {
                            $mensagens[] = [
                                'tipo' => 'erro',
                                'texto' => "<strong>Erro:</strong> " . htmlspecialchars($erro['erro']) . 
                                          "<br><strong>Consulta:</strong> <code>" . htmlspecialchars(substr($erro['consulta'], 0, 150)) . 
                                          (strlen($erro['consulta']) > 150 ? '...' : '') . "</code>"
                            ];
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link href="css/styles.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .card {
            margin-bottom: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card-header {
            padding: 10px 15px;
            font-weight: bold;
        }
        .card-body {
            padding: 15px;
        }
        .mensagem {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 4px;
        }
        .mensagem-sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensagem-erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .mensagem-aviso {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .mensagem-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .codigo {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
            max-height: 300px;
        }
        textarea {
            width: 100%;
            height: 200px;
            font-family: monospace;
        }
        .passos {
            counter-reset: passo;
            margin-left: 0;
            padding-left: 0;
        }
        .passos li {
            list-style-type: none;
            counter-increment: passo;
            margin-bottom: 15px;
            padding-left: 30px;
            position: relative;
        }
        .passos li::before {
            content: counter(passo);
            position: absolute;
            left: 0;
            top: 0;
            width: 22px;
            height: 22px;
            background-color: #0d6efd;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 22px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h1 class="h4"><?php echo $titulo; ?></h1>
            </div>
            <div class="card-body">
                <!-- Mensagens -->
                <?php foreach ($mensagens as $mensagem): ?>
                    <div class="mensagem mensagem-<?php echo $mensagem['tipo']; ?>">
                        <?php echo $mensagem['texto']; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!verificarConfigLocal()): ?>
                    <!-- Instruções para criar arquivo de configuração -->
                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white">
                            Configuração do Banco de Dados
                        </div>
                        <div class="card-body">
                            <p>É necessário criar um arquivo de configuração para o banco de dados.</p>
                            
                            <ol class="passos">
                                <li>
                                    <strong>Crie o arquivo de configuração local</strong><br>
                                    Crie um arquivo chamado <code>config.local.php</code> na pasta <code>includes</code> com o seguinte conteúdo:
                                    <div class="codigo">
&lt;?php
// Configurações do banco de dados para servidor externo
define('DB_HOST', 'localhost');     // Nome do host ou IP do servidor
define('DB_NAME', 'nome_do_banco'); // Nome do banco de dados
define('DB_USER', 'usuario_db');    // Nome de usuário do banco
define('DB_PASS', 'senha_db');      // Senha do banco de dados
define('DB_PORT', '5432');          // Porta PostgreSQL (normalmente 5432)
?&gt;</div>
                                </li>
                                <li>
                                    <strong>Modifique os valores do arquivo</strong><br>
                                    Substitua os valores com as informações corretas do seu banco de dados:
                                    <ul>
                                        <li><code>DB_HOST</code>: geralmente 'localhost' se o banco está no mesmo servidor</li>
                                        <li><code>DB_NAME</code>: nome do banco de dados que você criou</li>
                                        <li><code>DB_USER</code>: nome do usuário com permissão no banco</li>
                                        <li><code>DB_PASS</code>: senha do usuário</li>
                                        <li><code>DB_PORT</code>: porta do PostgreSQL (padrão: 5432)</li>
                                    </ul>
                                </li>
                                <li>
                                    <strong>Recarregue esta página</strong><br>
                                    Após criar o arquivo de configuração, recarregue esta página para continuar.
                                </li>
                            </ol>
                        </div>
                    </div>
                <?php elseif (!$script_executado): ?>
                    <!-- Formulário de instalação -->
                    <form method="post" enctype="multipart/form-data">
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                Instalar Banco de Dados
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h5>Escolha uma das opções abaixo para instalar o banco de dados:</h5>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="usar_padrao" name="usar_padrao" value="1" checked>
                                    <label class="form-check-label" for="usar_padrao">
                                        <strong>Usar script padrão incorporado</strong> (recomendado)
                                    </label>
                                    <div class="text-muted small">Esta opção instalará todas as tabelas necessárias com dados iniciais padrão.</div>
                                </div>
                                
                                <hr>
                                
                                <div class="form-group mb-3">
                                    <label for="arquivo_sql">Ou enviar um arquivo SQL:</label>
                                    <input type="file" class="form-control" id="arquivo_sql" name="arquivo_sql" disabled>
                                </div>
                                
                                <div class="form-group mb-4">
                                    <label for="script_sql">Ou colar script SQL manualmente:</label>
                                    <textarea class="form-control" id="script_sql" name="script_sql" rows="6" placeholder="Cole aqui o conteúdo SQL..." disabled></textarea>
                                </div>
                                
                                <?php
                                // Verificar se há tabelas
                                list($conectado, $db, $erro_conexao) = conectarBancoDados();
                                $tabelas_existentes = $conectado ? verificarTabelasExistentes($db) : [];
                                
                                if (count($tabelas_existentes) > 0): 
                                ?>
                                    <div class="alert alert-warning">
                                        <h5 class="alert-heading">⚠️ Atenção!</h5>
                                        <p>Existem <?php echo count($tabelas_existentes); ?> tabelas no banco de dados. A reinstalação <strong>apagará todos os dados existentes</strong>.</p>
                                        <hr>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="forcar_reinstalacao" name="forcar_reinstalacao" value="1">
                                            <label class="form-check-label" for="forcar_reinstalacao">
                                                <strong class="text-danger">Confirmo que desejo apagar todos os dados existentes e reinstalar o banco</strong>
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-primary" id="btn_instalar">Instalar Banco de Dados</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Instalação concluída -->
                    <div class="alert alert-success mb-4">
                        <h4 class="alert-heading">✅ Instalação Concluída</h4>
                        <p>O banco de dados foi instalado com sucesso.</p>
                        <hr>
                        <p class="mb-0">Agora você pode acessar o sistema usando as credenciais:</p>
                        <ul>
                            <li><strong>Email:</strong> admin@admin.com</li>
                            <li><strong>Senha:</strong> admin123</li>
                        </ul>
                        <p class="mt-3 mb-0">Por segurança, lembre-se de alterar sua senha após o primeiro acesso.</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="index.php" class="btn btn-primary">Voltar para a página inicial</a>
                    <a href="teste_conexao.php" class="btn btn-secondary">Testar conexão com o banco</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const usarPadrao = document.getElementById('usar_padrao');
        const scriptSql = document.getElementById('script_sql');
        const arquivoSql = document.getElementById('arquivo_sql');
        
        if (usarPadrao && scriptSql && arquivoSql) {
            usarPadrao.addEventListener('change', function() {
                if (this.checked) {
                    scriptSql.disabled = true;
                    arquivoSql.disabled = true;
                } else {
                    scriptSql.disabled = false;
                    arquivoSql.disabled = false;
                }
            });
        }
        
        // Validar o formulário antes do envio
        const form = document.querySelector('form');
        const btnInstalar = document.getElementById('btn_instalar');
        const forcarReinstalacao = document.getElementById('forcar_reinstalacao');
        
        if (form && btnInstalar) {
            form.addEventListener('submit', function(e) {
                <?php if (count($tabelas_existentes) > 0): ?>
                if (forcarReinstalacao && !forcarReinstalacao.checked) {
                    e.preventDefault();
                    alert('Você precisa confirmar que deseja apagar todos os dados existentes marcando a caixa de confirmação.');
                    return false;
                }
                <?php endif; ?>
                
                // Se tudo estiver ok, mostrar feedback
                btnInstalar.disabled = true;
                btnInstalar.innerHTML = 'Instalando... Aguarde';
            });
        }
    });
    </script>
</body>
</html>