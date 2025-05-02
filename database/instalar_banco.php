<?php
/**
 * Script de Instalação do Banco de Dados
 * Este arquivo cria todas as tabelas necessárias para o funcionamento do Sistema de Orçamentos para Calhas
 */

// Configurações de conexão com o banco de dados
// ATENÇÃO: Modifique estas configurações conforme seu servidor
$host = "localhost";        // Endereço do servidor de banco de dados
$dbname = "sistema_calhas"; // Nome do banco de dados
$username = "seu_usuario";  // Nome de usuário do banco de dados
$password = "sua_senha";    // Senha do banco de dados
$port = "5432";             // Porta padrão do PostgreSQL

// Mensagens e log
$log = [];
$success = true;

// Função para adicionar mensagem ao log
function addLog($message, $success = true) {
    global $log;
    $log[] = [
        'message' => $message,
        'success' => $success
    ];
}

// Tenta conectar ao banco de dados
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    addLog("Conexão com o banco de dados estabelecida com sucesso!");
} catch (PDOException $e) {
    addLog("Erro ao conectar ao banco de dados: " . $e->getMessage(), false);
    $success = false;
}

// Se a conexão foi bem-sucedida, criar as tabelas
if ($success) {
    // Array com todas as consultas SQL para criar as tabelas
    $queries = [
        // Tabela de usuários
        "CREATE TABLE IF NOT EXISTS usuarios (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            cargo VARCHAR(50),
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            telefone VARCHAR(20),
            ativo BOOLEAN DEFAULT TRUE,
            perfil VARCHAR(50) DEFAULT 'usuario'
        )",
        
        // Tabela de permissões
        "CREATE TABLE IF NOT EXISTS permissoes (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER NOT NULL,
            recurso VARCHAR(100) NOT NULL,
            pode_ver BOOLEAN DEFAULT FALSE,
            pode_cadastrar BOOLEAN DEFAULT FALSE,
            pode_editar BOOLEAN DEFAULT FALSE,
            pode_excluir BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
        )",
        
        // Tabela de clientes
        "CREATE TABLE IF NOT EXISTS clientes (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            telefone VARCHAR(20),
            email VARCHAR(100),
            cpf_cnpj VARCHAR(20),
            endereco TEXT,
            bairro VARCHAR(100),
            cidade VARCHAR(100),
            estado VARCHAR(2),
            cep VARCHAR(10),
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            observacoes TEXT,
            tipo VARCHAR(20) DEFAULT 'cliente'
        )",
        
        // Tabela de produtos
        "CREATE TABLE IF NOT EXISTS produtos (
            id SERIAL PRIMARY KEY,
            codigo VARCHAR(20) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            unidade VARCHAR(10) NOT NULL,
            valor_custo DECIMAL(10, 2) NOT NULL,
            valor_venda DECIMAL(10, 2) NOT NULL,
            estoque_minimo DECIMAL(10, 2) DEFAULT 0,
            estoque_atual DECIMAL(10, 2) DEFAULT 0,
            categoria VARCHAR(50),
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            observacoes TEXT,
            ativo BOOLEAN DEFAULT TRUE
        )",
        
        // Tabela de movimentações de estoque
        "CREATE TABLE IF NOT EXISTS estoque_movimentacoes (
            id SERIAL PRIMARY KEY,
            produto_id INTEGER NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            quantidade DECIMAL(10, 2) NOT NULL,
            data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            observacoes TEXT,
            usuario_id INTEGER,
            orcamento_id INTEGER,
            venda_id INTEGER,
            FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE RESTRICT,
            FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
        )",
        
        // Tabela de orçamentos
        "CREATE TABLE IF NOT EXISTS orcamentos (
            id SERIAL PRIMARY KEY,
            numero VARCHAR(20) NOT NULL,
            cliente_id INTEGER NOT NULL,
            data_orcamento DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'pendente',
            valor_total DECIMAL(10, 2) NOT NULL,
            observacoes TEXT,
            validade DATE,
            desconto DECIMAL(10, 2) DEFAULT 0,
            forma_pagamento VARCHAR(50),
            parcelas INTEGER DEFAULT 1,
            data_aprovacao TIMESTAMP,
            usuario_id INTEGER,
            hash VARCHAR(100),
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            valor_frete DECIMAL(10, 2) DEFAULT 0,
            valor_mao_obra DECIMAL(10, 2) DEFAULT 0,
            FOREIGN KEY (cliente_id) REFERENCES clientes (id) ON DELETE RESTRICT,
            FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
        )",
        
        // Tabela de itens de orçamento
        "CREATE TABLE IF NOT EXISTS orcamento_itens (
            id SERIAL PRIMARY KEY,
            orcamento_id INTEGER NOT NULL,
            produto_id INTEGER NOT NULL,
            quantidade DECIMAL(10, 2) NOT NULL,
            valor_unitario DECIMAL(10, 2) NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            observacoes TEXT,
            FOREIGN KEY (orcamento_id) REFERENCES orcamentos (id) ON DELETE CASCADE,
            FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE RESTRICT
        )",
        
        // Tabela de vendas
        "CREATE TABLE IF NOT EXISTS vendas (
            id SERIAL PRIMARY KEY,
            numero VARCHAR(20) NOT NULL,
            cliente_id INTEGER,
            data_venda DATE NOT NULL,
            valor_total DECIMAL(10, 2) NOT NULL,
            valor_pago DECIMAL(10, 2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'pendente',
            forma_pagamento VARCHAR(50),
            parcelas INTEGER DEFAULT 1,
            observacoes TEXT,
            usuario_id INTEGER,
            orcamento_id INTEGER,
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_pagamento TIMESTAMP,
            desconto DECIMAL(10, 2) DEFAULT 0,
            FOREIGN KEY (cliente_id) REFERENCES clientes (id) ON DELETE SET NULL,
            FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
            FOREIGN KEY (orcamento_id) REFERENCES orcamentos (id) ON DELETE SET NULL
        )",
        
        // Tabela de itens de venda
        "CREATE TABLE IF NOT EXISTS venda_itens (
            id SERIAL PRIMARY KEY,
            venda_id INTEGER NOT NULL,
            produto_id INTEGER NOT NULL,
            quantidade DECIMAL(10, 2) NOT NULL,
            valor_unitario DECIMAL(10, 2) NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            observacoes TEXT,
            FOREIGN KEY (venda_id) REFERENCES vendas (id) ON DELETE CASCADE,
            FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE RESTRICT
        )",
        
        // Tabela de pagamentos
        "CREATE TABLE IF NOT EXISTS pagamentos (
            id SERIAL PRIMARY KEY,
            venda_id INTEGER NOT NULL,
            data_pagamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            valor DECIMAL(10, 2) NOT NULL,
            forma_pagamento VARCHAR(50) NOT NULL,
            observacoes TEXT,
            usuario_id INTEGER,
            estorno BOOLEAN DEFAULT FALSE,
            estorno_id INTEGER,
            FOREIGN KEY (venda_id) REFERENCES vendas (id) ON DELETE CASCADE,
            FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
            FOREIGN KEY (estorno_id) REFERENCES pagamentos (id) ON DELETE SET NULL
        )",
        
        // Tabela de controle de caixa
        "CREATE TABLE IF NOT EXISTS caixa_controle (
            id SERIAL PRIMARY KEY,
            data_abertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            valor_inicial DECIMAL(10, 2) NOT NULL,
            data_fechamento TIMESTAMP,
            valor_final DECIMAL(10, 2),
            status VARCHAR(20) DEFAULT 'aberto',
            usuario_abertura_id INTEGER,
            usuario_fechamento_id INTEGER,
            observacoes TEXT,
            resumo_vendas TEXT,
            FOREIGN KEY (usuario_abertura_id) REFERENCES usuarios (id) ON DELETE SET NULL,
            FOREIGN KEY (usuario_fechamento_id) REFERENCES usuarios (id) ON DELETE SET NULL
        )",
        
        // Tabela de movimentações do caixa
        "CREATE TABLE IF NOT EXISTS caixa (
            id SERIAL PRIMARY KEY,
            data_operacao DATE NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            valor DECIMAL(10, 2) NOT NULL,
            forma_pagamento VARCHAR(50),
            orcamento_id INTEGER,
            usuario_id INTEGER,
            observacoes TEXT,
            data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            venda_id INTEGER,
            cliente_id INTEGER,
            conta_pagar_id INTEGER,
            estorno_id INTEGER,
            caixa_controle_id INTEGER,
            FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
            FOREIGN KEY (orcamento_id) REFERENCES orcamentos (id) ON DELETE SET NULL,
            FOREIGN KEY (venda_id) REFERENCES vendas (id) ON DELETE SET NULL,
            FOREIGN KEY (cliente_id) REFERENCES clientes (id) ON DELETE SET NULL,
            FOREIGN KEY (estorno_id) REFERENCES caixa (id) ON DELETE SET NULL,
            FOREIGN KEY (caixa_controle_id) REFERENCES caixa_controle (id) ON DELETE SET NULL
        )",
        
        // Tabela de contas a pagar
        "CREATE TABLE IF NOT EXISTS contas_pagar (
            id SERIAL PRIMARY KEY,
            descricao VARCHAR(255) NOT NULL,
            valor DECIMAL(10, 2) NOT NULL,
            data_vencimento DATE NOT NULL,
            data_pagamento DATE,
            status VARCHAR(20) DEFAULT 'pendente',
            fornecedor VARCHAR(100),
            categoria VARCHAR(50),
            observacoes TEXT,
            recorrente BOOLEAN DEFAULT FALSE,
            periodicidade VARCHAR(20),
            dia_vencimento INTEGER,
            usuario_id INTEGER,
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
        )",
        
        // Tabela de configurações do sistema
        "CREATE TABLE IF NOT EXISTS configuracoes (
            id SERIAL PRIMARY KEY,
            nome_empresa VARCHAR(100) NOT NULL,
            cnpj VARCHAR(20),
            telefone VARCHAR(20),
            email VARCHAR(100),
            endereco TEXT,
            logo TEXT,
            desconto_padrao DECIMAL(5, 2) DEFAULT 0,
            dias_validade_orcamento INTEGER DEFAULT 15,
            percentual_mao_obra DECIMAL(5, 2) DEFAULT 0,
            mostrar_mao_obra_separado BOOLEAN DEFAULT FALSE,
            juros_padrao DECIMAL(5, 2) DEFAULT 0,
            termos_condicoes TEXT,
            instrucoes_pagamento TEXT,
            assinatura TEXT,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];
    
    // Executa cada consulta SQL
    try {
        $db->beginTransaction();
        
        foreach ($queries as $index => $query) {
            try {
                $db->exec($query);
                addLog("Tabela " . ($index + 1) . " criada com sucesso!");
            } catch (PDOException $e) {
                addLog("Erro ao criar tabela " . ($index + 1) . ": " . $e->getMessage(), false);
                $success = false;
                break;
            }
        }
        
        // Se todas as tabelas foram criadas com sucesso, insere o usuário administrador
        if ($success) {
            // Cria usuário admin padrão (admin@admin.com / admin123)
            $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
            $insertAdmin = "INSERT INTO usuarios (nome, email, senha, cargo, perfil) 
                           VALUES ('Administrador', 'admin@admin.com', :senha, 'Administrador', 'admin')
                           ON CONFLICT (email) DO NOTHING";
            
            $stmt = $db->prepare($insertAdmin);
            $stmt->bindParam(':senha', $senhaHash);
            $stmt->execute();
            
            // Verifica se o usuário foi inserido ou já existia
            $adminId = $db->lastInsertId();
            
            if ($adminId) {
                addLog("Usuário administrador criado com sucesso!");
                
                // Adiciona permissões para o administrador
                $recursos = [
                    'dashboard', 'produtos', 'clientes', 'orcamentos', 'vendas', 'usuarios',
                    'configuracoes', 'estoque', 'relatorios', 'caixa', 'contas_pagar'
                ];
                
                $insertPermissao = "INSERT INTO permissoes (usuario_id, recurso, pode_ver, pode_cadastrar, pode_editar, pode_excluir)
                                   VALUES (:usuario_id, :recurso, TRUE, TRUE, TRUE, TRUE)";
                
                $stmtPerm = $db->prepare($insertPermissao);
                
                foreach ($recursos as $recurso) {
                    $stmtPerm->bindParam(':usuario_id', $adminId);
                    $stmtPerm->bindParam(':recurso', $recurso);
                    $stmtPerm->execute();
                }
                
                addLog("Permissões do administrador configuradas com sucesso!");
            } else {
                addLog("Usuário administrador já existe, pulando criação.");
            }
            
            // Adiciona configurações padrão
            $insertConfig = "INSERT INTO configuracoes (nome_empresa, percentual_mao_obra, dias_validade_orcamento)
                            VALUES ('Minha Empresa de Calhas', 30, 15)
                            ON CONFLICT (id) DO NOTHING";
            
            $db->exec($insertConfig);
            addLog("Configurações iniciais do sistema criadas!");
            
            $db->commit();
            addLog("Instalação concluída com sucesso!");
        } else {
            $db->rollBack();
            addLog("Instalação cancelada devido a erros.", false);
        }
    } catch (PDOException $e) {
        $db->rollBack();
        addLog("Erro durante a transação: " . $e->getMessage(), false);
        $success = false;
    }
}

// HTML para exibir os resultados
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação do Banco de Dados - Sistema de Orçamentos para Calhas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 2rem;
            padding-bottom: 2rem;
            background-color: #f8f9fa;
        }
        .log-item {
            padding: 8px 15px;
            margin-bottom: 5px;
            border-radius: 4px;
        }
        .log-success {
            background-color: #d4edda;
            border-left: 5px solid #28a745;
            color: #155724;
        }
        .log-error {
            background-color: #f8d7da;
            border-left: 5px solid #dc3545;
            color: #721c24;
        }
        .header-card {
            background-color: #0d6efd;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm mb-4">
            <div class="card-header header-card">
                <h1 class="card-title">Instalação do Banco de Dados</h1>
                <p class="card-subtitle">Sistema de Orçamentos para Calhas</p>
            </div>
            <div class="card-body">
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                    <?php if ($success): ?>
                        <h4><i class="bi bi-check-circle"></i> Instalação concluída com sucesso!</h4>
                        <p>O banco de dados foi configurado e está pronto para uso.</p>
                        <p>Credenciais de acesso inicial:</p>
                        <ul>
                            <li><strong>Email:</strong> admin@admin.com</li>
                            <li><strong>Senha:</strong> admin123</li>
                        </ul>
                        <p>Recomendamos que você altere a senha padrão ao fazer o primeiro login.</p>
                    <?php else: ?>
                        <h4><i class="bi bi-exclamation-triangle"></i> Ocorreram erros durante a instalação</h4>
                        <p>Verifique os detalhes abaixo e corrija as configurações antes de tentar novamente.</p>
                    <?php endif; ?>
                </div>

                <h5 class="mt-4 mb-3">Log de Instalação:</h5>
                <div class="log-container">
                    <?php foreach ($log as $entry): ?>
                        <div class="log-item <?php echo $entry['success'] ? 'log-success' : 'log-error'; ?>">
                            <?php echo $entry['message']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary">Ir para o Sistema</a>
                    <?php if (!$success): ?>
                        <a href="instalar_banco.php" class="btn btn-secondary">Tentar Novamente</a>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 alert alert-warning">
                    <h5>Importante:</h5>
                    <p>Por questões de segurança, recomendamos que você exclua ou renomeie este arquivo após a instalação bem-sucedida.</p>
                </div>
            </div>
            <div class="card-footer text-center text-muted">
                <small>Sistema de Orçamentos para Calhas &copy; <?php echo date('Y'); ?></small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>