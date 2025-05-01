<?php
// Criar tabela para configurações específicas por dia da semana
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($pdo)) {
    die("Erro: Conexão com o banco de dados não disponível.");
}

try {
    // Verificar se a tabela já existe
    $stmt = $pdo->prepare("SELECT to_regclass('configuracoes_dias_semana')");
    $stmt->execute();
    $existe_tabela = $stmt->fetchColumn();
    
    if (!$existe_tabela) {
        // Criar a tabela
        $sql = "CREATE TABLE configuracoes_dias_semana (
            id SERIAL PRIMARY KEY,
            dia_semana INTEGER NOT NULL, -- 0 = Domingo, 1 = Segunda, ..., 6 = Sábado
            horario_inicio TIME NOT NULL DEFAULT '07:00',
            horario_fim TIME NOT NULL DEFAULT '17:00',
            horario_inicio_almoco TIME NOT NULL DEFAULT '11:00',
            horario_fim_almoco TIME NOT NULL DEFAULT '12:00',
            tempo_indisponivel_entrada INTEGER NOT NULL DEFAULT 30,
            funcionamento_ativo BOOLEAN NOT NULL DEFAULT FALSE,
            CONSTRAINT uk_dia_semana UNIQUE (dia_semana)
        )";
        
        $pdo->exec($sql);
        
        // Inserir configurações padrão
        // De segunda a sexta (dias 1-5): horário normal
        for ($dia = 1; $dia <= 5; $dia++) {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_dias_semana 
                (dia_semana, horario_inicio, horario_fim, horario_inicio_almoco, horario_fim_almoco, funcionamento_ativo) 
                VALUES (?, '07:00', '17:00', '11:00', '12:00', TRUE)");
            $stmt->execute([$dia]);
        }
        
        // Sábado (dia 6): horário reduzido e sem almoço
        $stmt = $pdo->prepare("INSERT INTO configuracoes_dias_semana 
            (dia_semana, horario_inicio, horario_fim, horario_inicio_almoco, horario_fim_almoco, funcionamento_ativo) 
            VALUES (6, '08:00', '12:00', '00:00', '00:00', TRUE)");
        $stmt->execute();
        
        // Domingo (dia 0): inativo
        $stmt = $pdo->prepare("INSERT INTO configuracoes_dias_semana 
            (dia_semana, horario_inicio, horario_fim, horario_inicio_almoco, horario_fim_almoco, funcionamento_ativo) 
            VALUES (0, '08:00', '18:00', '12:00', '13:00', FALSE)");
        $stmt->execute();
        
        echo "<div class='alert alert-success'>Tabela de configurações por dia da semana criada com sucesso!</div>";
    } else {
        echo "<div class='alert alert-info'>A tabela de configurações por dia da semana já existe.</div>";
    }
    
    // Adicionar as configurações correspondentes também na tabela 'configuracoes'
    // para sincronização com o config.php
    
    // Buscar todas as configurações por dia da semana
    $stmt = $pdo->query("SELECT * FROM configuracoes_dias_semana ORDER BY dia_semana");
    $config_dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($config_dias as $dia) {
        $dia_semana = $dia['dia_semana'];
        $ativo = $dia['funcionamento_ativo'] ? 'sim' : 'nao';
        
        $configs_dia = [
            "dia_{$dia_semana}_ativo" => $ativo,
            "dia_{$dia_semana}_horario_inicio" => $dia['horario_inicio'],
            "dia_{$dia_semana}_horario_fim" => $dia['horario_fim'],
            "dia_{$dia_semana}_horario_inicio_almoco" => $dia['horario_inicio_almoco'],
            "dia_{$dia_semana}_horario_fim_almoco" => $dia['horario_fim_almoco'],
            "dia_{$dia_semana}_tempo_indisponivel_entrada" => $dia['tempo_indisponivel_entrada']
        ];
        
        foreach ($configs_dia as $chave => $valor) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM configuracoes WHERE chave = ?");
            $stmt->execute([$chave]);
            $existe = $stmt->fetchColumn();
            
            if ($existe) {
                $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
                $stmt->execute([$valor, $chave]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)");
                $stmt->execute([$chave, $valor]);
            }
        }
    }
    
    echo "<div class='alert alert-success'>Configurações por dia da semana sincronizadas com a tabela 'configuracoes'.</div>";
    
    // Sincronizar com o arquivo config.php
    require_once('includes/sincronizador_config.php');
    if (function_exists('sincronizarConfiguracoesComArquivo')) {
        sincronizarConfiguracoesComArquivo($pdo, true); // true = banco para arquivo
        echo "<div class='alert alert-success'>Configurações sincronizadas com o arquivo config.php.</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao criar tabela: " . $e->getMessage() . "</div>";
}

echo "<a href='configuracoes.php' class='btn btn-primary mt-3'>Voltar para Configurações</a>";
?>