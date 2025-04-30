<?php
// Inicialização de variáveis
$titulo = "Corrigir Senha de Administrador";
$mensagens = [];
$senha_corrigida = false;

// Função para conectar ao banco de dados
function conectarBancoDados() {
    try {
        // Procurar arquivo de configuração
        $config_path = __DIR__ . '/includes/config.local.php';
        if (file_exists($config_path)) {
            include($config_path);
        } else {
            // Tentar arquivo de configuração padrão
            include(__DIR__ . '/includes/config.php');
        }
        
        // Verificar se constantes foram definidas
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            throw new Exception("Arquivo de configuração não encontrado ou incompleto");
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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se é para resetar a senha
    if (isset($_POST['resetar_senha'])) {
        // Conectar ao banco
        list($conectado, $db, $erro_conexao) = conectarBancoDados();
        
        if (!$conectado) {
            $mensagens[] = [
                'tipo' => 'erro',
                'texto' => "❌ Erro ao conectar ao banco de dados: " . $erro_conexao
            ];
        } else {
            try {
                // Verificar se tabela de usuários existe
                $stmt = $db->query("SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public'
                    AND table_name = 'usuarios'
                )");
                
                if (!$stmt->fetchColumn()) {
                    $mensagens[] = [
                        'tipo' => 'erro',
                        'texto' => "❌ A tabela 'usuarios' não existe no banco de dados."
                    ];
                } else {
                    // Verificar se existe o usuário admin
                    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email");
                    $email = 'admin@admin.com';
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() == 0) {
                        // Criar o usuário admin
                        $mensagens[] = [
                            'tipo' => 'info',
                            'texto' => "Usuário admin não encontrado. Criando novo usuário administrador..."
                        ];
                        
                        $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, nivel, ativo) 
                                           VALUES (:nome, :email, :senha, :nivel, :ativo)");
                        
                        $nome = 'Administrador';
                        $email = 'admin@admin.com';
                        // Hash da senha 'admin123'
                        $senha = password_hash('admin123', PASSWORD_DEFAULT);
                        $nivel = 'admin';
                        $ativo = true;
                        
                        $stmt->bindParam(':nome', $nome);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':senha', $senha);
                        $stmt->bindParam(':nivel', $nivel);
                        $stmt->bindParam(':ativo', $ativo);
                        
                        $stmt->execute();
                        $admin_id = $db->lastInsertId();
                        
                        $mensagens[] = [
                            'tipo' => 'sucesso',
                            'texto' => "✅ Usuário administrador criado com sucesso (ID: {$admin_id})"
                        ];
                        
                        // Verificar permissões
                        $stmt = $db->prepare("SELECT * FROM permissoes WHERE usuario_id = :usuario_id");
                        $stmt->bindParam(':usuario_id', $admin_id);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() == 0) {
                            // Criar permissões
                            $stmt = $db->prepare("INSERT INTO permissoes (
                                usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, 
                                gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, 
                                gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, 
                                gerenciar_contas, visualizar_relatorios_gerais
                            ) VALUES (
                                :usuario_id, :gerenciar_usuarios, :visualizar_relatorios_financeiros,
                                :gerenciar_estoque, :gerenciar_produtos, :gerenciar_orcamentos,
                                :gerenciar_vendas, :gerenciar_clientes, :gerenciar_caixa,
                                :gerenciar_contas, :visualizar_relatorios_gerais
                            )");
                            
                            $true_val = true;
                            
                            $stmt->bindParam(':usuario_id', $admin_id);
                            $stmt->bindParam(':gerenciar_usuarios', $true_val);
                            $stmt->bindParam(':visualizar_relatorios_financeiros', $true_val);
                            $stmt->bindParam(':gerenciar_estoque', $true_val);
                            $stmt->bindParam(':gerenciar_produtos', $true_val);
                            $stmt->bindParam(':gerenciar_orcamentos', $true_val);
                            $stmt->bindParam(':gerenciar_vendas', $true_val);
                            $stmt->bindParam(':gerenciar_clientes', $true_val);
                            $stmt->bindParam(':gerenciar_caixa', $true_val);
                            $stmt->bindParam(':gerenciar_contas', $true_val);
                            $stmt->bindParam(':visualizar_relatorios_gerais', $true_val);
                            
                            $stmt->execute();
                            
                            $mensagens[] = [
                                'tipo' => 'sucesso',
                                'texto' => "✅ Permissões do administrador configuradas com sucesso"
                            ];
                        }
                    } else {
                        // Atualizar a senha do usuário admin
                        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $mensagens[] = [
                            'tipo' => 'info',
                            'texto' => "Usuário admin encontrado (ID: {$usuario['id']}). Atualizando senha..."
                        ];
                        
                        $stmt = $db->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
                        
                        // Hash da senha 'admin123'
                        $senha = password_hash('admin123', PASSWORD_DEFAULT);
                        
                        $stmt->bindParam(':senha', $senha);
                        $stmt->bindParam(':id', $usuario['id']);
                        
                        $stmt->execute();
                        
                        $mensagens[] = [
                            'tipo' => 'sucesso',
                            'texto' => "✅ Senha do usuário administrador atualizada com sucesso"
                        ];
                        
                        // Verificar permissões
                        $stmt = $db->prepare("SELECT * FROM permissoes WHERE usuario_id = :usuario_id");
                        $stmt->bindParam(':usuario_id', $usuario['id']);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() == 0) {
                            // Criar permissões
                            $stmt = $db->prepare("INSERT INTO permissoes (
                                usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, 
                                gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, 
                                gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, 
                                gerenciar_contas, visualizar_relatorios_gerais
                            ) VALUES (
                                :usuario_id, :gerenciar_usuarios, :visualizar_relatorios_financeiros,
                                :gerenciar_estoque, :gerenciar_produtos, :gerenciar_orcamentos,
                                :gerenciar_vendas, :gerenciar_clientes, :gerenciar_caixa,
                                :gerenciar_contas, :visualizar_relatorios_gerais
                            )");
                            
                            $true_val = true;
                            
                            $stmt->bindParam(':usuario_id', $usuario['id']);
                            $stmt->bindParam(':gerenciar_usuarios', $true_val);
                            $stmt->bindParam(':visualizar_relatorios_financeiros', $true_val);
                            $stmt->bindParam(':gerenciar_estoque', $true_val);
                            $stmt->bindParam(':gerenciar_produtos', $true_val);
                            $stmt->bindParam(':gerenciar_orcamentos', $true_val);
                            $stmt->bindParam(':gerenciar_vendas', $true_val);
                            $stmt->bindParam(':gerenciar_clientes', $true_val);
                            $stmt->bindParam(':gerenciar_caixa', $true_val);
                            $stmt->bindParam(':gerenciar_contas', $true_val);
                            $stmt->bindParam(':visualizar_relatorios_gerais', $true_val);
                            
                            $stmt->execute();
                            
                            $mensagens[] = [
                                'tipo' => 'sucesso',
                                'texto' => "✅ Permissões do administrador configuradas com sucesso"
                            ];
                        } else {
                            $mensagens[] = [
                                'tipo' => 'info',
                                'texto' => "As permissões do administrador já estão configuradas"
                            ];
                        }
                    }
                    
                    $senha_corrigida = true;
                }
            } catch (Exception $e) {
                $mensagens[] = [
                    'tipo' => 'erro',
                    'texto' => "❌ Erro ao redefinir senha: " . $e->getMessage()
                ];
            }
        }
    }
    
    // Se for configurar conexão
    if (isset($_POST['configurar_conexao'])) {
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
            } catch (Exception $e) {
                $mensagens[] = [
                    'tipo' => 'erro',
                    'texto' => "❌ Erro ao conectar ao banco de dados: " . $e->getMessage()
                ];
            }
        }
    }
}

// Verificar conexão atual
$conexao_atual = "";
list($conectado, $db, $erro_conexao) = conectarBancoDados();
if ($conectado) {
    $conexao_atual = "✅ Conectado ao banco de dados: " . DB_TYPE . " em " . DB_HOST . ":" . DB_PORT . ", banco " . DB_NAME;
} else {
    $conexao_atual = "❌ Não foi possível conectar ao banco de dados: " . $erro_conexao;
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
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #bb2d3b;
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
        .text-muted {
            color: #6c757d;
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
        
        <div class="card mb-4">
            <div class="card-header">Status da Conexão</div>
            <div class="card-body">
                <p><?php echo $conexao_atual; ?></p>
            </div>
        </div>
        
        <?php if (!$senha_corrigida): ?>
        <div class="card mb-4">
            <div class="card-header">Redefinir Senha de Administrador</div>
            <div class="card-body">
                <p>Este utilitário irá redefinir a senha do usuário administrador para <strong>admin123</strong>.</p>
                <p class="text-muted">Útil quando você está tendo problemas para acessar o sistema com as credenciais padrão.</p>
                
                <form method="post">
                    <input type="hidden" name="resetar_senha" value="1">
                    <button type="submit" class="btn btn-warning">Redefinir Senha de Administrador</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">Configurar Conexão com o Banco</div>
            <div class="card-body">
                <p>Se estiver tendo problemas para conectar ao banco de dados, você pode configurar novamente a conexão:</p>
                
                <form method="post">
                    <input type="hidden" name="configurar_conexao" value="1">
                    
                    <div class="form-group">
                        <label for="db_host">Host:</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Nome do Banco:</label>
                        <input type="text" id="db_name" name="db_name" value="" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Usuário:</label>
                        <input type="text" id="db_user" name="db_user" value="" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Senha:</label>
                        <input type="password" id="db_pass" name="db_pass" value="" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_port">Porta:</label>
                        <input type="text" id="db_port" name="db_port" value="5432" required>
                    </div>
                    
                    <button type="submit" class="btn">Salvar Configurações</button>
                </form>
            </div>
        </div>
        
        <p>
            <a href="index.php" class="btn btn-secondary">Voltar para a página inicial</a>
            <?php if ($senha_corrigida): ?>
                <a href="login.php" class="btn">Ir para a página de login</a>
            <?php endif; ?>
        </p>
    </div>
</body>
</html>