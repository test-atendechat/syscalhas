<?php
// Inicialização de variáveis
$titulo = "Instalação do Banco de Dados - Versão Simples";
$mensagens = [];
$script_executado = false;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar dados do formulário
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_port = $_POST['db_port'] ?? '5432';
    
    // Validar se todos os campos foram preenchidos
    if (empty($db_name) || empty($db_user) || empty($db_pass)) {
        $mensagens[] = [
            'tipo' => 'erro',
            'texto' => "Todos os campos são obrigatórios."
        ];
    } else {
        // Tentar conectar ao banco de dados
        try {
            $dsn = "pgsql:host={$db_host};dbname={$db_name};port={$db_port}";
            $db = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            $mensagens[] = [
                'tipo' => 'sucesso',
                'texto' => "✅ Conexão com o banco de dados estabelecida com sucesso."
            ];
            
            // Verificar se já existem tabelas
            $tabelas_existentes = [];
            $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tabelas_existentes[] = $row['table_name'];
            }
            
            // Verificar se o usuário confirmou a substituição, se necessário
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
                // Criar sequências em queries separadas para garantir que funcionem
                $sequencias = [
                    "CREATE SEQUENCE IF NOT EXISTS configuracoes_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS usuarios_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS permissoes_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS categorias_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS produtos_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS clientes_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS orcamentos_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS orcamento_itens_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS vendas_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS vendas_itens_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS caixa_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS caixa_controle_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS estoque_movimentacoes_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS contas_pagar_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS pagamentos_contas_id_seq START 1;",
                    "CREATE SEQUENCE IF NOT EXISTS estornos_pagamentos_id_seq START 1;"
                ];
                
                $seq_sucesso = 0;
                foreach ($sequencias as $seq_sql) {
                    try {
                        $db->exec($seq_sql);
                        $seq_sucesso++;
                    } catch (Exception $e) {
                        // Ignorar erros se a sequência já existir
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            $mensagens[] = [
                                'tipo' => 'aviso',
                                'texto' => "Aviso ao criar sequência: " . $e->getMessage()
                            ];
                        }
                    }
                }
                
                $mensagens[] = [
                    'tipo' => 'sucesso',
                    'texto' => "✅ {$seq_sucesso} sequências criadas com sucesso."
                ];
                
                // Array para armazenar os scripts SQL para cada tabela
                $scripts_tabelas = [
                    // Tabela configuracoes
                    "DROP TABLE IF EXISTS configuracoes CASCADE;
                    CREATE TABLE configuracoes (
                        id integer NOT NULL DEFAULT nextval('configuracoes_id_seq'::regclass) PRIMARY KEY,
                        chave character varying(50) NOT NULL,
                        valor text,
                        created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
                        updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
                        cor_principal character varying(20) DEFAULT '#0d6efd'::character varying
                    );
                    
                    INSERT INTO configuracoes (chave, valor) VALUES ('empresa_nome', 'Grupo Sandro Calhas LTDA');
                    INSERT INTO configuracoes (chave, valor) VALUES ('empresa_telefone', '(19) 99262-0970');
                    INSERT INTO configuracoes (chave, valor) VALUES ('empresa_email', 'contato@sandrocalhas.com.br');
                    INSERT INTO configuracoes (chave, valor) VALUES ('empresa_endereco', 'Rua Carlos Pulici, 387, Vila Franco, Descalvado - SP');
                    INSERT INTO configuracoes (chave, valor) VALUES ('empresa_cnpj', '48.998.641/0001-03');
                    INSERT INTO configuracoes (chave, valor) VALUES ('taxa_padrao_mao_obra', '100');
                    INSERT INTO configuracoes (chave, valor) VALUES ('estoque_alerta_minimo', '50');
                    INSERT INTO configuracoes (chave, valor) VALUES ('dias_validade_orcamento', '30');
                    INSERT INTO configuracoes (chave, valor) VALUES ('desconto_pagamento_vista', '10');
                    INSERT INTO configuracoes (chave, valor) VALUES ('max_parcelas', '12');
                    INSERT INTO configuracoes (chave, valor) VALUES ('cor_principal', '#0d6efd');
                    INSERT INTO configuracoes (chave, valor) VALUES ('cor_secundaria', '#6c757d');
                    INSERT INTO configuracoes (chave, valor) VALUES ('cor_aprovado', '#198754');
                    INSERT INTO configuracoes (chave, valor) VALUES ('cor_pendente', '#ffc107');
                    INSERT INTO configuracoes (chave, valor) VALUES ('cor_rejeitado', '#dc3545');",
                    
                    // Tabela usuarios
                    "DROP TABLE IF EXISTS usuarios CASCADE;
                    CREATE TABLE usuarios (
                        id integer NOT NULL DEFAULT nextval('usuarios_id_seq'::regclass) PRIMARY KEY,
                        nome character varying(100) NOT NULL,
                        email character varying(100) NOT NULL,
                        senha character varying(255) NOT NULL,
                        nivel character varying(20) NOT NULL DEFAULT 'usuario'::character varying,
                        ativo boolean NOT NULL DEFAULT true,
                        data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
                    );
                    
                    INSERT INTO usuarios (nome, email, senha, nivel) VALUES 
                    ('Administrador', 'admin@admin.com', '$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK', 'admin');",
                    
                    // Tabela permissoes
                    "DROP TABLE IF EXISTS permissoes CASCADE;
                    CREATE TABLE permissoes (
                        id integer NOT NULL DEFAULT nextval('permissoes_id_seq'::regclass) PRIMARY KEY,
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
                    
                    INSERT INTO permissoes (usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, 
                    gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, 
                    gerenciar_contas, visualizar_relatorios_gerais) 
                    VALUES (1, true, true, true, true, true, true, true, true, true, true);",
                    
                    // Tabela categorias
                    "DROP TABLE IF EXISTS categorias CASCADE;
                    CREATE TABLE categorias (
                        id integer NOT NULL DEFAULT nextval('categorias_id_seq'::regclass) PRIMARY KEY,
                        nome character varying(100) NOT NULL,
                        descricao character varying(255)
                    );
                    
                    INSERT INTO categorias (nome) VALUES ('Cortes');
                    INSERT INTO categorias (nome) VALUES ('Parafusos');",
                    
                    // Tabela produtos
                    "DROP TABLE IF EXISTS produtos CASCADE;
                    CREATE TABLE produtos (
                        id integer NOT NULL DEFAULT nextval('produtos_id_seq'::regclass) PRIMARY KEY,
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
                    
                    INSERT INTO produtos (codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, custo_unitario) 
                    VALUES ('C10', 'Corte 10', 1, 'metro', 8.12, 500, 50, 4.08);",
                    
                    // Tabela clientes
                    "DROP TABLE IF EXISTS clientes CASCADE;
                    CREATE TABLE clientes (
                        id integer NOT NULL DEFAULT nextval('clientes_id_seq'::regclass) PRIMARY KEY,
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
                    
                    INSERT INTO clientes (nome, tipo) VALUES ('Sem Identificação', 'fisica');",
                    
                    // Tabela orcamentos
                    "DROP TABLE IF EXISTS orcamentos CASCADE;
                    CREATE TABLE orcamentos (
                        id integer NOT NULL DEFAULT nextval('orcamentos_id_seq'::regclass) PRIMARY KEY,
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
                    );",
                    
                    // Tabela orcamento_itens
                    "DROP TABLE IF EXISTS orcamento_itens CASCADE;
                    CREATE TABLE orcamento_itens (
                        id integer NOT NULL DEFAULT nextval('orcamento_itens_id_seq'::regclass) PRIMARY KEY,
                        orcamento_id integer NOT NULL,
                        produto_id integer NOT NULL,
                        quantidade numeric NOT NULL DEFAULT 0,
                        valor_unitario numeric NOT NULL DEFAULT 0,
                        valor_total numeric NOT NULL DEFAULT 0,
                        observacoes text
                    );",
                    
                    // Tabela vendas
                    "DROP TABLE IF EXISTS vendas CASCADE;
                    CREATE TABLE vendas (
                        id integer NOT NULL DEFAULT nextval('vendas_id_seq'::regclass) PRIMARY KEY,
                        numero character varying(20) NOT NULL,
                        cliente_id integer NOT NULL,
                        data_venda timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        valor_produtos numeric NOT NULL DEFAULT 0,
                        valor_total numeric NOT NULL DEFAULT 0,
                        forma_pagamento character varying(50),
                        status_pagamento character varying(20) NOT NULL DEFAULT 'pendente'::character varying,
                        status_entrega character varying(20) NOT NULL DEFAULT 'pendente'::character varying,
                        observacoes text,
                        usuario_id integer,
                        orcamento_id integer,
                        data_pagamento date,
                        data_entrega date,
                        nfe_status character varying(50),
                        nfe_chave character varying(50),
                        nfe_numero character varying(20)
                    );",
                    
                    // Tabela vendas_itens
                    "DROP TABLE IF EXISTS vendas_itens CASCADE;
                    CREATE TABLE vendas_itens (
                        id integer NOT NULL DEFAULT nextval('vendas_itens_id_seq'::regclass) PRIMARY KEY,
                        venda_id integer NOT NULL,
                        produto_id integer NOT NULL,
                        quantidade numeric NOT NULL DEFAULT 0,
                        valor_unitario numeric NOT NULL DEFAULT 0,
                        valor_total numeric NOT NULL DEFAULT 0,
                        observacoes text,
                        custo_unitario numeric NOT NULL DEFAULT 0
                    );",
                    
                    // Tabela caixa
                    "DROP TABLE IF EXISTS caixa CASCADE;
                    CREATE TABLE caixa (
                        id integer NOT NULL DEFAULT nextval('caixa_id_seq'::regclass) PRIMARY KEY,
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
                    );",
                    
                    // Tabela caixa_controle
                    "DROP TABLE IF EXISTS caixa_controle CASCADE;
                    CREATE TABLE caixa_controle (
                        id integer NOT NULL DEFAULT nextval('caixa_controle_id_seq'::regclass) PRIMARY KEY,
                        data_abertura timestamp without time zone,
                        saldo_inicial numeric DEFAULT 0,
                        saldo_final numeric,
                        data_fechamento timestamp without time zone,
                        usuario_id_abertura integer,
                        usuario_id_fechamento integer,
                        observacoes text,
                        status character varying(20) NOT NULL DEFAULT 'fechado'::character varying
                    );",
                    
                    // Tabela estoque_movimentacoes
                    "DROP TABLE IF EXISTS estoque_movimentacoes CASCADE;
                    CREATE TABLE estoque_movimentacoes (
                        id integer NOT NULL DEFAULT nextval('estoque_movimentacoes_id_seq'::regclass) PRIMARY KEY,
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
                    );",
                    
                    // Tabela contas_pagar
                    "DROP TABLE IF EXISTS contas_pagar CASCADE;
                    CREATE TABLE contas_pagar (
                        id integer NOT NULL DEFAULT nextval('contas_pagar_id_seq'::regclass) PRIMARY KEY,
                        descricao character varying(255) NOT NULL,
                        valor numeric NOT NULL DEFAULT 0,
                        data_vencimento date NOT NULL,
                        tipo character varying(50) NOT NULL DEFAULT 'normal'::character varying,
                        status character varying(20) NOT NULL DEFAULT 'pendente'::character varying,
                        data_pagamento date,
                        valor_pago numeric,
                        categoria character varying(50),
                        fornecedor character varying(100),
                        recorrente boolean NOT NULL DEFAULT false,
                        intervalo_recorrencia integer,
                        unidade_recorrencia character varying(20) DEFAULT 'meses'::character varying,
                        data_fim_recorrencia date,
                        usuario_id integer,
                        data_cadastro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        observacoes text
                    );",
                    
                    // Tabela pagamentos_contas
                    "DROP TABLE IF EXISTS pagamentos_contas CASCADE;
                    CREATE TABLE pagamentos_contas (
                        id integer NOT NULL DEFAULT nextval('pagamentos_contas_id_seq'::regclass) PRIMARY KEY,
                        conta_id integer NOT NULL,
                        data_pagamento date NOT NULL,
                        valor numeric NOT NULL DEFAULT 0,
                        forma_pagamento character varying(50) NOT NULL,
                        observacoes text,
                        usuario_id integer,
                        data_registro timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
                    );",
                    
                    // Tabela estornos_pagamentos
                    "DROP TABLE IF EXISTS estornos_pagamentos CASCADE;
                    CREATE TABLE estornos_pagamentos (
                        id integer NOT NULL DEFAULT nextval('estornos_pagamentos_id_seq'::regclass) PRIMARY KEY,
                        caixa_id integer,
                        data_estorno timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        valor numeric NOT NULL DEFAULT 0,
                        motivo text,
                        usuario_id integer NOT NULL,
                        venda_id integer,
                        orcamento_id integer,
                        conta_id integer
                    );"
                ];
                
                // Processar cada script de tabela individualmente
                $tabelas_criadas = 0;
                foreach ($scripts_tabelas as $index => $script) {
                    try {
                        $db->exec($script);
                        $tabelas_criadas++;
                    } catch (Exception $e) {
                        $mensagens[] = [
                            'tipo' => 'erro',
                            'texto' => "❌ Erro ao criar tabela #" . ($index + 1) . ": " . $e->getMessage()
                        ];
                    }
                }
                
                if ($tabelas_criadas == count($scripts_tabelas)) {
                    $mensagens[] = [
                        'tipo' => 'sucesso',
                        'texto' => "✅ Todas as {$tabelas_criadas} tabelas foram criadas com sucesso!"
                    ];
                    
                    // Criar arquivo config.local.php
                    $config_dir = __DIR__ . '/includes';
                    if (!is_dir($config_dir)) {
                        mkdir($config_dir, 0755, true);
                    }
                    
                    $config_content = "<?php
// Configurações do banco de dados - Gerado automaticamente em " . date('Y-m-d H:i:s') . "
define('DB_TYPE', 'pgsql');
define('DB_HOST', '{$db_host}');
define('DB_NAME', '{$db_name}');
define('DB_USER', '{$db_user}');
define('DB_PASS', '{$db_pass}');
define('DB_PORT', '{$db_port}');
?>";
                    
                    // Tentar salvar o arquivo de configuração
                    $config_file = $config_dir . '/config.local.php';
                    file_put_contents($config_file, $config_content);
                    
                    $mensagens[] = [
                        'tipo' => 'sucesso',
                        'texto' => "✅ Arquivo de configuração criado com sucesso em {$config_file}"
                    ];
                    
                    $script_executado = true;
                } else {
                    $mensagens[] = [
                        'tipo' => 'erro',
                        'texto' => "❌ Apenas {$tabelas_criadas} de " . count($scripts_tabelas) . " tabelas foram criadas."
                    ];
                }
            }
        } catch (PDOException $e) {
            $mensagens[] = [
                'tipo' => 'erro',
                'texto' => "❌ Erro ao conectar ao banco de dados: " . $e->getMessage()
            ];
            
            // Sugestões comuns para problemas de conexão
            if (strpos($e->getMessage(), 'SQLSTATE[08006]') !== false && strpos($e->getMessage(), 'no password supplied') !== false) {
                $mensagens[] = [
                    'tipo' => 'info',
                    'texto' => "📌 O erro indica problema com a senha do banco de dados. Verifique se você digitou a senha corretamente."
                ];
            } elseif (strpos($e->getMessage(), 'SQLSTATE[08006]') !== false && strpos($e->getMessage(), 'password authentication failed') !== false) {
                $mensagens[] = [
                    'tipo' => 'info',
                    'texto' => "📌 Senha incorreta. Verifique suas credenciais."
                ];
            } elseif (strpos($e->getMessage(), 'could not connect to server') !== false) {
                $mensagens[] = [
                    'tipo' => 'info',
                    'texto' => "📌 Não foi possível conectar ao servidor. Verifique se o PostgreSQL está em execução e se o endereço do host está correto."
                ];
            } elseif (strpos($e->getMessage(), 'database') !== false && strpos($e->getMessage(), 'does not exist') !== false) {
                $mensagens[] = [
                    'tipo' => 'info',
                    'texto' => "📌 O banco de dados informado não existe. Você precisa criar o banco de dados primeiro no HestiaCP."
                ];
                
                // Adicionar instruções para criar banco no HestiaCP
                $mensagens[] = [
                    'tipo' => 'info',
                    'texto' => "Para criar um banco de dados no HestiaCP:<br>
                    1. Acesse o painel de controle do HestiaCP<br>
                    2. Vá para a seção 'Banco de Dados'<br>
                    3. Clique em 'Adicionar'<br>
                    4. Escolha um nome para o banco de dados<br>
                    5. Escolha PostgreSQL como o tipo de banco<br>
                    6. Selecione o usuário ou crie um novo<br>
                    7. Depois de criar, use as mesmas informações neste formulário"
                ];
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
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0d6efd;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            padding: 10px 15px;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
            font-weight: bold;
        }
        .card-body {
            padding: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background-color: #0d6efd;
            color: white;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover {
            background-color: #0b5ed7;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5c636a;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .checkbox {
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .checkbox label {
            display: inline;
            font-weight: normal;
        }
        .text-danger {
            color: #dc3545;
        }
        code {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $titulo; ?></h1>
        
        <?php foreach ($mensagens as $mensagem): ?>
            <div class="alert alert-<?php 
                echo ($mensagem['tipo'] == 'sucesso' ? 'success' : 
                    ($mensagem['tipo'] == 'erro' ? 'danger' : 
                    ($mensagem['tipo'] == 'aviso' ? 'warning' : 'info'))); 
            ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
        <?php endforeach; ?>
        
        <?php if (!$script_executado): ?>
            <div class="card">
                <div class="card-header">Configurações do Banco de Dados PostgreSQL</div>
                <div class="card-body">
                    <p>Por favor, insira as informações de acesso ao banco de dados PostgreSQL do seu HestiaCP:</p>
                    
                    <form method="post">
                        <div class="form-group">
                            <label for="db_host">Host:</label>
                            <input type="text" id="db_host" name="db_host" value="localhost" required>
                            <small>Geralmente "localhost" no HestiaCP</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">Nome do Banco:</label>
                            <input type="text" id="db_name" name="db_name" value="" required>
                            <small>O nome do banco de dados que você criou no HestiaCP</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user">Usuário:</label>
                            <input type="text" id="db_user" name="db_user" value="" required>
                            <small>O nome de usuário que tem acesso ao banco de dados</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_pass">Senha:</label>
                            <input type="password" id="db_pass" name="db_pass" value="" required>
                            <small>A senha do usuário do banco de dados</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_port">Porta:</label>
                            <input type="text" id="db_port" name="db_port" value="5432" required>
                            <small>A porta padrão do PostgreSQL é 5432</small>
                        </div>
                        
                        <?php
                        // Se houver informações sobre tabelas existentes neste banco
                        if (isset($tabelas_existentes) && count($tabelas_existentes) > 0): 
                        ?>
                            <div class="alert alert-warning">
                                <strong>⚠️ Atenção!</strong> Existem <?php echo count($tabelas_existentes); ?> tabelas no banco de dados. 
                                A reinstalação <strong>apagará todos os dados existentes</strong>.
                                
                                <div class="checkbox">
                                    <input type="checkbox" id="forcar_reinstalacao" name="forcar_reinstalacao" value="1">
                                    <label for="forcar_reinstalacao">
                                        <strong class="text-danger">Confirmo que desejo apagar todos os dados existentes e reinstalar o banco</strong>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn">Instalar Banco de Dados</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">Dicas para HestiaCP</div>
                <div class="card-body">
                    <p>Se você está usando o HestiaCP, siga estas orientações:</p>
                    
                    <ol>
                        <li>
                            <strong>Crie o banco de dados pelo painel do HestiaCP</strong><br>
                            Acesse o painel HestiaCP, vá até "Banco de Dados" e crie um banco PostgreSQL
                        </li>
                        <li>
                            <strong>Nome de usuário e nome do banco geralmente são iguais</strong><br>
                            No HestiaCP, o formato comum é: <code>usuário_nomedobanco</code>
                        </li>
                        <li>
                            <strong>A porta padrão 5432 geralmente funciona</strong><br>
                            Só altere se o seu PostgreSQL estiver configurado com outra porta
                        </li>
                        <li>
                            <strong>O host normalmente é "localhost"</strong><br>
                            Mantendo o banco no mesmo servidor da aplicação
                        </li>
                    </ol>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <h3>✅ Instalação Concluída!</h3>
                <p>O banco de dados foi instalado com sucesso e o arquivo de configuração foi criado.</p>
                <p>Agora você pode acessar o sistema usando as credenciais de acesso:</p>
                <ul>
                    <li><strong>Email:</strong> admin@admin.com</li>
                    <li><strong>Senha:</strong> admin123</li>
                </ul>
                <p>Por razões de segurança, mude a senha do administrador assim que fizer o primeiro acesso.</p>
                
                <a href="index.php" class="btn">Ir para a página inicial</a>
            </div>
        <?php endif; ?>
        
        <p><a href="index.php" class="btn btn-secondary">Voltar para a página inicial</a></p>
    </div>
</body>
</html>