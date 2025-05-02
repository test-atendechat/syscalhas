<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Inicialização
$titulo = "Instalação do Banco de Dados";
$mensagens = [];
$script_executado = false;

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

// Verificar se o banco já tem tabelas
$tabelas_existentes = [];
try {
    $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tabelas_existentes[] = $row['table_name'];
    }
} catch (Exception $e) {
    $mensagens[] = [
        'tipo' => 'erro',
        'texto' => "Erro ao verificar tabelas existentes: " . $e->getMessage()
    ];
}

// Verificar se é para forçar reinstalação
$forcar_reinstalacao = isset($_POST['forcar_reinstalacao']) && $_POST['forcar_reinstalacao'] == 1;

// Exibir aviso se já existirem tabelas
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
}

// Processar a instalação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (count($tabelas_existentes) == 0 || $forcar_reinstalacao)) {
    
    // Capturar dados do formulário
    $script_sql = isset($_POST['script_sql']) ? $_POST['script_sql'] : '';
    $arquivo_sql = isset($_FILES['arquivo_sql']) ? $_FILES['arquivo_sql'] : null;
    
    // Verificar qual método foi utilizado (texto ou arquivo)
    if (!empty($script_sql)) {
        // Usar o script fornecido diretamente
        $script_para_executar = $script_sql;
    } elseif ($arquivo_sql && $arquivo_sql['error'] == 0) {
        // Ler o arquivo enviado
        $script_para_executar = file_get_contents($arquivo_sql['tmp_name']);
    } else {
        // Tentar usar o script gerado por exportar_banco_completo.php
        $url_script_padrao = 'exportar_banco_completo.php?download=1';
        
        // Tentar fazer um request interno para obter o script
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: PHP/InstallScript\r\n"
            ]
        ]);
        
        $script_para_executar = @file_get_contents($url_script_padrao, false, $context);
        
        if ($script_para_executar === false) {
            $mensagens[] = [
                'tipo' => 'erro',
                'texto' => "Não foi possível obter o script SQL padrão. Por favor, forneça um script manualmente."
            ];
            $script_para_executar = '';
        } else {
            $mensagens[] = [
                'tipo' => 'info',
                'texto' => "Script SQL padrão obtido com sucesso do exportador automático."
            ];
        }
    }
    
    // Executar o script se tiver conteúdo
    if (!empty($script_para_executar)) {
        $resultado = executarScriptSQL($db, $script_para_executar);
        $script_executado = true;
        
        if ($resultado['sucesso']) {
            $mensagens[] = [
                'tipo' => 'sucesso',
                'texto' => "✅ Banco de dados instalado com sucesso! Foram executadas {$resultado['executadas']} de {$resultado['consultas']} consultas."
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
    } else {
        $mensagens[] = [
            'tipo' => 'erro',
            'texto' => "Nenhum script SQL fornecido para instalação."
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
            overflow-x: auto;
            max-height: 300px;
        }
        textarea {
            width: 100%;
            height: 200px;
            font-family: monospace;
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
                
                <?php if (!$script_executado): ?>
                    <?php if (count($tabelas_existentes) > 0): ?>
                        <div class="alert alert-warning">
                            <h4 class="alert-heading">Atenção!</h4>
                            <p>Existem tabelas no banco de dados. A reinstalação apagará todos os dados existentes.</p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">
                                Instalar Banco de Dados
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label for="arquivo_sql">Enviar arquivo SQL:</label>
                                    <input type="file" class="form-control" id="arquivo_sql" name="arquivo_sql">
                                    <small class="form-text text-muted">Selecione um arquivo SQL para importar</small>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="script_sql">Ou cole o script SQL abaixo:</label>
                                    <textarea class="form-control" id="script_sql" name="script_sql" rows="10" placeholder="Cole aqui o conteúdo SQL exportado..."></textarea>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="usar_script_padrao" checked>
                                    <label class="form-check-label" for="usar_script_padrao">
                                        Usar script padrão gerado pelo sistema (recomendado)
                                    </label>
                                </div>
                                
                                <?php if (count($tabelas_existentes) > 0): ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="forcar_reinstalacao" name="forcar_reinstalacao" value="1">
                                        <label class="form-check-label" for="forcar_reinstalacao">
                                            <strong class="text-danger">Confirmo que desejo apagar todos os dados existentes e reinstalar o banco</strong>
                                        </label>
                                    </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-primary" id="btn_instalar">Instalar Banco de Dados</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success mb-4">
                        <h4 class="alert-heading">Instalação Concluída</h4>
                        <p>O banco de dados foi instalado com sucesso.</p>
                        <hr>
                        <p class="mb-0">Você pode agora acessar o sistema e começar a utilizá-lo.</p>
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
        const usarScriptPadrao = document.getElementById('usar_script_padrao');
        const scriptSql = document.getElementById('script_sql');
        const arquivoSql = document.getElementById('arquivo_sql');
        
        if (usarScriptPadrao && scriptSql && arquivoSql) {
            usarScriptPadrao.addEventListener('change', function() {
                if (this.checked) {
                    scriptSql.disabled = true;
                    arquivoSql.disabled = true;
                } else {
                    scriptSql.disabled = false;
                    arquivoSql.disabled = false;
                }
            });
            
            // Executar no carregamento
            usarScriptPadrao.dispatchEvent(new Event('change'));
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
                
                btnInstalar.disabled = true;
                btnInstalar.innerHTML = 'Instalando... Aguarde';
            });
        }
    });
    </script>
</body>
</html>