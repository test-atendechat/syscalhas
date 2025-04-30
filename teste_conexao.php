<?php
// Inicialização
$titulo = "Teste de Conexão com o Banco de Dados";
$mensagens = [];
$exibir_instrucoes = true;

// Tentar estabelecer conexão com o banco
try {
    // Incluir configurações
    require_once('includes/config.php');
    
    // Verificar se as constantes foram definidas
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        throw new Exception("Constantes de conexão não definidas corretamente");
    }
    
    // Construir DSN
    $dsn = (DB_TYPE == 'pgsql') ? 
        "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT :
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
    
    // Tentar conexão
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Testar query simples
    $stmt = $db->query("SELECT current_timestamp as timestamp");
    $resultado = $stmt->fetch();
    
    // Se chegou até aqui, a conexão foi estabelecida com sucesso
    $mensagens[] = [
        'tipo' => 'sucesso',
        'texto' => "✅ Conexão com o banco de dados estabelecida com sucesso!"
    ];
    
    $mensagens[] = [
        'tipo' => 'info',
        'texto' => "📌 Informações da conexão:<br>" .
                  "• Tipo de banco: " . DB_TYPE . "<br>" .
                  "• Host: " . DB_HOST . "<br>" .
                  "• Nome do banco: " . DB_NAME . "<br>" .
                  "• Porta: " . DB_PORT . "<br>" .
                  "• Timestamp do servidor: " . $resultado['timestamp']
    ];
    
    // Verificar se existem tabelas no banco
    $tabelas = [];
    if (DB_TYPE == 'pgsql') {
        $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    } else {
        $stmt = $db->query("SHOW TABLES");
    }
    
    while ($row = $stmt->fetch()) {
        $tabelas[] = is_array($row) ? reset($row) : $row;
    }
    
    if (count($tabelas) > 0) {
        $mensagens[] = [
            'tipo' => 'sucesso',
            'texto' => "✅ Banco de dados contém " . count($tabelas) . " tabelas."
        ];
        // Se banco já tem tabelas, não exibir instruções de instalação
        $exibir_instrucoes = false;
    } else {
        $mensagens[] = [
            'tipo' => 'aviso',
            'texto' => "⚠️ O banco de dados está vazio. Execute instalar_banco.php para criar as tabelas."
        ];
    }
    
} catch (Exception $e) {
    $mensagens[] = [
        'tipo' => 'erro',
        'texto' => "❌ Erro de conexão: " . $e->getMessage()
    ];
    
    // Verificar especificamente o erro de senha
    if (strpos($e->getMessage(), 'no password supplied') !== false) {
        $mensagens[] = [
            'tipo' => 'info',
            'texto' => "📌 O erro indica problema com a senha do banco de dados. Verifique se você configurou corretamente o arquivo config.local.php."
        ];
    } elseif (strpos($e->getMessage(), 'password authentication failed') !== false) {
        $mensagens[] = [
            'tipo' => 'info',
            'texto' => "📌 Senha incorreta. Verifique suas credenciais no arquivo config.local.php."
        ];
    } elseif (strpos($e->getMessage(), 'connection refused') !== false) {
        $mensagens[] = [
            'tipo' => 'info',
            'texto' => "📌 Conexão recusada. Verifique se o servidor PostgreSQL está em execução e se o endereço do host está correto."
        ];
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
            white-space: pre-wrap;
            margin: 10px 0;
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
                
                <?php if ($exibir_instrucoes): ?>
                    <!-- Instruções de instalação -->
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            Instruções para instalação em servidor externo
                        </div>
                        <div class="card-body">
                            <ol class="passos">
                                <li>
                                    <strong>Configure o acesso ao banco de dados</strong><br>
                                    Crie um arquivo chamado <code>config.local.php</code> na pasta <code>includes</code> com base no modelo <code>config.local.php.exemplo</code>
                                    <div class="codigo">
// Exemplo de configuração (includes/config.local.php)
&lt;?php
define('DB_HOST', 'localhost');     // Nome do host
define('DB_NAME', 'nome_do_banco'); // Nome do banco 
define('DB_USER', 'usuario_db');    // Nome de usuário
define('DB_PASS', 'senha_db');      // Senha
define('DB_PORT', '5432');          // Porta PostgreSQL
?&gt;</div>
                                </li>
                                <li>
                                    <strong>Verifique a conexão com o banco</strong><br>
                                    Acesse esta página (<code>teste_conexao.php</code>) novamente para confirmar que a conexão foi estabelecida.
                                </li>
                                <li>
                                    <strong>Instale o banco de dados</strong><br>
                                    Execute o arquivo <code>instalar_banco.php</code> para criar as tabelas necessárias.
                                </li>
                                <li>
                                    <strong>Acesse o sistema</strong><br>
                                    Volte para a <a href="index.php">página inicial</a> e faça login com as credenciais padrão:
                                    <ul>
                                        <li>Email: admin@admin.com</li>
                                        <li>Senha: admin123</li>
                                    </ul>
                                </li>
                                <li>
                                    <strong>Troque a senha do administrador</strong><br>
                                    Por segurança, altere imediatamente a senha do administrador em <a href="perfil.php">Meu Perfil</a>.
                                </li>
                            </ol>
                        </div>
                    </div>
                <?php endif; ?>
                
                <p>
                    <a href="index.php" class="btn btn-primary">Voltar para a página inicial</a>
                    <?php if ($exibir_instrucoes): ?>
                        <a href="instalar_banco.php" class="btn btn-success">Instalar banco de dados</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</body>
</html>