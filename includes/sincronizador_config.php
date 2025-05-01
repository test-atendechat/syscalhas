<?php
/**
 * Sincroniza as configurações do banco de dados com o arquivo config.php
 * 
 * @param PDO $pdo Conexão com o banco de dados
 * @return boolean True se a sincronização foi bem-sucedida, False caso contrário
 */
function sincronizarConfiguracoesComArquivo($pdo = null) {
    // Se $pdo não foi fornecido, tenta usar a variável global
    if ($pdo === null) {
        global $pdo;
        if (!$pdo) {
            error_log('Erro ao sincronizar configurações: Conexão PDO não disponível');
            return false;
        }
    }
    
    try {
        // Verifica se a tabela configurações existe
        $stmt = $pdo->prepare("SELECT to_regclass('configuracoes')");
        $stmt->execute();
        $existe_tabela = $stmt->fetchColumn();
        
        if (!$existe_tabela) {
            error_log('Tabela configuracoes não existe no banco de dados');
            return false;
        }
        
        // Busca todas as configurações do banco de dados
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
        $config_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (empty($config_db)) {
            error_log('Nenhuma configuração encontrada no banco de dados');
            return false;
        }
        
        // Mapeamento de chaves de configuração para constantes no config.php
        $mapeamento_constantes = [
            // Dados da empresa
            'empresa_nome' => 'APP_NAME',
            'empresa_telefone' => 'EMPRESA_TELEFONE',
            'empresa_email' => 'EMPRESA_EMAIL',
            'empresa_endereco' => 'EMPRESA_ENDERECO',
            'empresa_cnpj' => 'EMPRESA_CNPJ',
            
            // Configurações de Orçamentos
            'taxa_padrao_mao_obra' => 'TAXA_PADRAO_MAO_OBRA',
            'desconto_pagamento_vista' => 'DESCONTO_PAGAMENTO_VISTA',
            'max_parcelas' => 'MAX_PARCELAS',
            'dias_validade_orcamento' => 'DIAS_VALIDADE_ORCAMENTO',
            
            // Configurações de Horário de Funcionamento
            'horario_inicio' => 'HORARIO_INICIO',
            'horario_fim' => 'HORARIO_FIM',
            'dias_funcionamento' => 'DIAS_FUNCIONAMENTO',
            
            // Configurações de Indisponibilidade Automática
            'aplicar_indisponibilidade_automatica' => 'APLICAR_INDISPONIBILIDADE_AUTOMATICA',
            'horario_inicio_almoco' => 'HORARIO_INICIO_ALMOCO',
            'horario_fim_almoco' => 'HORARIO_FIM_ALMOCO',
            'tempo_indisponivel_entrada' => 'TEMPO_INDISPONIVEL_ENTRADA',
            
            // Configurações de Estoque
            'estoque_alerta_minimo' => 'ESTOQUE_ALERTA_MINIMO',
            
            // Configurações de Visitas Técnicas
            'tempo_visita_tecnica' => 'TEMPO_VISITA_TECNICA',
            'unidade_tempo_visita' => 'UNIDADE_TEMPO_VISITA',
            
            // Configurações de Aparência
            'cor_principal' => 'COR_PRINCIPAL',
            'cor_secundaria' => 'COR_SECUNDARIA',
            'cor_aprovado' => 'COR_APROVADO',
            'cor_pendente' => 'COR_PENDENTE',
            'cor_rejeitado' => 'COR_REJEITADO',
            'tema_sistema' => 'TEMA_SISTEMA',
            'estilo_menu' => 'ESTILO_MENU',
            
            // Configurações de Integrações
            'api_previsao_tempo' => 'API_PREVISAO_TEMPO',
            'api_previsao_tempo_key' => 'API_PREVISAO_TEMPO_KEY',
            'api_previsao_tempo_provider' => 'API_PREVISAO_TEMPO_PROVIDER',
            'cidade_previsao_tempo' => 'CIDADE_PREVISAO_TEMPO',
            'dias_reagendamento_chuva' => 'DIAS_REAGENDAMENTO_CHUVA'
        ];
        
        // O arquivo de configuração
        $config_file = __DIR__ . '/config.php';
        
        // Verifica se o arquivo existe e pode ser escrito
        if (!file_exists($config_file) || !is_writable($config_file)) {
            error_log("Arquivo de configuração não existe ou não tem permissão de escrita: $config_file");
            return false;
        }
        
        // Lê o conteúdo atual do arquivo
        $content = file_get_contents($config_file);
        
        // Para cada configuração no banco, atualiza no arquivo
        $atualizacoes = 0;
        foreach ($config_db as $chave => $valor) {
            // Verifica se existe um mapeamento para esta configuração
            if (isset($mapeamento_constantes[$chave])) {
                $constante = $mapeamento_constantes[$chave];
                
                // Escapar caracteres especiais no valor para evitar problemas com aspas
                $valor_escapado = str_replace("'", "\'", $valor);
                
                // Padrão para encontrar a constante no arquivo
                $pattern = "/define\('$constante', '.*?'\);/";
                
                // Substituição com o novo valor
                $replacement = "define('$constante', '{$valor_escapado}');";
                
                // Verifica se a constante existe no arquivo
                if (preg_match($pattern, $content)) {
                    // Atualiza o valor da constante
                    $content = preg_replace($pattern, $replacement, $content);
                    $atualizacoes++;
                }
            }
        }
        
        // Escreve o conteúdo atualizado no arquivo
        file_put_contents($config_file, $content);
        
        error_log("Sincronização de configurações concluída: $atualizacoes atualizações realizadas");
        return true;
    } catch (Exception $e) {
        error_log('Erro ao sincronizar configurações: ' . $e->getMessage());
        return false;
    }
}


// Sincronizar configurações do banco com o arquivo config.php
// Essa função será executada uma vez por sessão
function executarSincronizacaoConfiguracoes($pdo) {
    // Como isso é importante mas não crítico, executamos em um bloco try-catch
    try {
        // Verifica se as configurações precisam ser sincronizadas (uma vez por sessão, ou a cada 24 horas)
        if (!isset($_SESSION['configuracoes_sincronizadas']) || 
            $_SESSION['configuracoes_sincronizadas'] < (time() - 86400)) {
                
            // Realiza a sincronização
            $resultado = sincronizarConfiguracoesComArquivo($pdo);
            
            // Registra o momento da sincronização na sessão para evitar sincronizar a cada página
            if ($resultado) {
                $_SESSION['configuracoes_sincronizadas'] = time();
                error_log("Configurações sincronizadas com sucesso!");
            }
        }
    } catch (Exception $e) {
        error_log('Erro ao sincronizar configurações: ' . $e->getMessage());
        // Silencia erros para não atrapalhar o uso normal do sistema
    }
}
?>