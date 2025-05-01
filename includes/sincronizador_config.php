<?php
/**
 * Sincroniza as configurações do banco de dados com o arquivo config.php
 * 
 * @param PDO $pdo Conexão com o banco de dados
 * @param boolean $banco_para_arquivo Se true (padrão), sincroniza do banco para o arquivo. Se false, sincroniza do arquivo para o banco.
 * @return boolean True se a sincronização foi bem-sucedida, False caso contrário
 */
function sincronizarConfiguracoesComArquivo($pdo = null, $banco_para_arquivo = true) {
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
        
        // Busca as configurações específicas por dia da semana
        $stmt = $pdo->prepare("SELECT to_regclass('configuracoes_dias_semana')");
        $stmt->execute();
        $existe_tabela_dias = $stmt->fetchColumn();
        
        if ($existe_tabela_dias) {
            $stmt = $pdo->query("SELECT * FROM configuracoes_dias_semana ORDER BY dia_semana");
            $config_dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adiciona as configurações por dia ao array de configurações
            foreach ($config_dias as $dia) {
                $dia_semana = $dia['dia_semana'];
                
                // Converte valores booleanos para texto
                $funcionamento_ativo = $dia['funcionamento_ativo'] ? 'sim' : 'nao';
                
                // Adiciona as configurações do dia ao array geral
                $config_db["dia_{$dia_semana}_ativo"] = $funcionamento_ativo;
                $config_db["dia_{$dia_semana}_horario_inicio"] = $dia['horario_inicio'];
                $config_db["dia_{$dia_semana}_horario_fim"] = $dia['horario_fim'];
                $config_db["dia_{$dia_semana}_horario_inicio_almoco"] = $dia['horario_inicio_almoco'];
                $config_db["dia_{$dia_semana}_horario_fim_almoco"] = $dia['horario_fim_almoco'];
                $config_db["dia_{$dia_semana}_tempo_indisponivel_entrada"] = $dia['tempo_indisponivel_entrada'];
            }
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
            
            // Configurações específicas para cada dia da semana (0-6: Domingo-Sábado)
            'dia_0_ativo' => 'DIA_0_ATIVO',
            'dia_0_horario_inicio' => 'DIA_0_HORARIO_INICIO',
            'dia_0_horario_fim' => 'DIA_0_HORARIO_FIM',
            'dia_0_horario_inicio_almoco' => 'DIA_0_HORARIO_INICIO_ALMOCO',
            'dia_0_horario_fim_almoco' => 'DIA_0_HORARIO_FIM_ALMOCO',
            'dia_0_tempo_indisponivel_entrada' => 'DIA_0_TEMPO_INDISPONIVEL_ENTRADA',
            
            'dia_1_ativo' => 'DIA_1_ATIVO',
            'dia_1_horario_inicio' => 'DIA_1_HORARIO_INICIO',
            'dia_1_horario_fim' => 'DIA_1_HORARIO_FIM',
            'dia_1_horario_inicio_almoco' => 'DIA_1_HORARIO_INICIO_ALMOCO',
            'dia_1_horario_fim_almoco' => 'DIA_1_HORARIO_FIM_ALMOCO',
            'dia_1_tempo_indisponivel_entrada' => 'DIA_1_TEMPO_INDISPONIVEL_ENTRADA',
            
            'dia_2_ativo' => 'DIA_2_ATIVO',
            'dia_2_horario_inicio' => 'DIA_2_HORARIO_INICIO',
            'dia_2_horario_fim' => 'DIA_2_HORARIO_FIM',
            'dia_2_horario_inicio_almoco' => 'DIA_2_HORARIO_INICIO_ALMOCO',
            'dia_2_horario_fim_almoco' => 'DIA_2_HORARIO_FIM_ALMOCO',
            'dia_2_tempo_indisponivel_entrada' => 'DIA_2_TEMPO_INDISPONIVEL_ENTRADA',
            
            'dia_3_ativo' => 'DIA_3_ATIVO',
            'dia_3_horario_inicio' => 'DIA_3_HORARIO_INICIO',
            'dia_3_horario_fim' => 'DIA_3_HORARIO_FIM',
            'dia_3_horario_inicio_almoco' => 'DIA_3_HORARIO_INICIO_ALMOCO',
            'dia_3_horario_fim_almoco' => 'DIA_3_HORARIO_FIM_ALMOCO',
            'dia_3_tempo_indisponivel_entrada' => 'DIA_3_TEMPO_INDISPONIVEL_ENTRADA',
            
            'dia_4_ativo' => 'DIA_4_ATIVO',
            'dia_4_horario_inicio' => 'DIA_4_HORARIO_INICIO',
            'dia_4_horario_fim' => 'DIA_4_HORARIO_FIM',
            'dia_4_horario_inicio_almoco' => 'DIA_4_HORARIO_INICIO_ALMOCO',
            'dia_4_horario_fim_almoco' => 'DIA_4_HORARIO_FIM_ALMOCO',
            'dia_4_tempo_indisponivel_entrada' => 'DIA_4_TEMPO_INDISPONIVEL_ENTRADA',
            
            'dia_5_ativo' => 'DIA_5_ATIVO',
            'dia_5_horario_inicio' => 'DIA_5_HORARIO_INICIO',
            'dia_5_horario_fim' => 'DIA_5_HORARIO_FIM',
            'dia_5_horario_inicio_almoco' => 'DIA_5_HORARIO_INICIO_ALMOCO',
            'dia_5_horario_fim_almoco' => 'DIA_5_HORARIO_FIM_ALMOCO',
            'dia_5_tempo_indisponivel_entrada' => 'DIA_5_TEMPO_INDISPONIVEL_ENTRADA',
            
            'dia_6_ativo' => 'DIA_6_ATIVO',
            'dia_6_horario_inicio' => 'DIA_6_HORARIO_INICIO',
            'dia_6_horario_fim' => 'DIA_6_HORARIO_FIM',
            'dia_6_horario_inicio_almoco' => 'DIA_6_HORARIO_INICIO_ALMOCO',
            'dia_6_horario_fim_almoco' => 'DIA_6_HORARIO_FIM_ALMOCO',
            'dia_6_tempo_indisponivel_entrada' => 'DIA_6_TEMPO_INDISPONIVEL_ENTRADA',
            
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
        
        // Inverte o mapeamento para sincronização do arquivo para o banco
        $mapeamento_invertido = array_flip($mapeamento_constantes);
        
        // O arquivo de configuração
        $config_file = __DIR__ . '/config.php';
        
        // Verifica se o arquivo existe e pode ser lido
        if (!file_exists($config_file) || !is_readable($config_file)) {
            error_log("Arquivo de configuração não existe ou não pode ser lido: $config_file");
            return false;
        }
        
        if ($banco_para_arquivo) {
            // SINCRONIZAÇÃO DO BANCO PARA O ARQUIVO
            
            // Verifica se o arquivo pode ser escrito
            if (!is_writable($config_file)) {
                error_log("Arquivo de configuração não tem permissão de escrita: $config_file");
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
            
            error_log("Sincronização do banco para o arquivo concluída: $atualizacoes atualizações realizadas");
        } else {
            // SINCRONIZAÇÃO DO ARQUIVO PARA O BANCO
            
            // O arquivo deve ser incluído para ter acesso às constantes
            require_once($config_file);
            
            // Para cada constante no mapeamento, atualiza no banco
            $atualizacoes = 0;
            
            // Atualizar tabela configuracoes
            foreach ($mapeamento_invertido as $constante => $chave) {
                // Ignora os mapeamentos específicos por dia da semana, que serão tratados separadamente
                if (strpos($chave, 'dia_') === 0) continue;
                
                // Verifica se a constante está definida
                if (defined($constante)) {
                    $valor = constant($constante);
                    
                    // Atualiza no banco
                    $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
                    if ($stmt->execute([$valor, $chave])) {
                        $atualizacoes += $stmt->rowCount();
                    }
                }
            }
            
            // Atualizar configurações específicas por dia da semana
            if ($existe_tabela_dias) {
                for ($dia = 0; $dia <= 6; $dia++) {
                    // Verificar se as constantes para este dia estão definidas
                    $ativo_constante = "DIA_{$dia}_ATIVO";
                    $horario_inicio_constante = "DIA_{$dia}_HORARIO_INICIO";
                    $horario_fim_constante = "DIA_{$dia}_HORARIO_FIM";
                    $horario_inicio_almoco_constante = "DIA_{$dia}_HORARIO_INICIO_ALMOCO";
                    $horario_fim_almoco_constante = "DIA_{$dia}_HORARIO_FIM_ALMOCO";
                    $tempo_indisponivel_entrada_constante = "DIA_{$dia}_TEMPO_INDISPONIVEL_ENTRADA";
                    
                    if (defined($ativo_constante) && defined($horario_inicio_constante) && 
                        defined($horario_fim_constante) && defined($horario_inicio_almoco_constante) &&
                        defined($horario_fim_almoco_constante) && defined($tempo_indisponivel_entrada_constante)) {
                        
                        // Obter valores
                        $ativo = (strtolower(constant($ativo_constante)) === 'sim') ? true : false;
                        $horario_inicio = constant($horario_inicio_constante);
                        $horario_fim = constant($horario_fim_constante);
                        $horario_inicio_almoco = constant($horario_inicio_almoco_constante);
                        $horario_fim_almoco = constant($horario_fim_almoco_constante);
                        $tempo_indisponivel_entrada = intval(constant($tempo_indisponivel_entrada_constante));
                        
                        // Verificar se este dia já existe na tabela
                        $stmt = $pdo->prepare("SELECT id FROM configuracoes_dias_semana WHERE dia_semana = ?");
                        $stmt->execute([$dia]);
                        $existe_dia = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existe_dia) {
                            // Atualizar dia existente
                            $stmt = $pdo->prepare("UPDATE configuracoes_dias_semana SET 
                                horario_inicio = ?, 
                                horario_fim = ?, 
                                horario_inicio_almoco = ?, 
                                horario_fim_almoco = ?, 
                                tempo_indisponivel_entrada = ?, 
                                funcionamento_ativo = ?
                                WHERE dia_semana = ?");
                            if ($stmt->execute([$horario_inicio, $horario_fim, $horario_inicio_almoco, $horario_fim_almoco, $tempo_indisponivel_entrada, $ativo, $dia])) {
                                $atualizacoes += $stmt->rowCount();
                            }
                        } else {
                            // Inserir novo dia
                            $stmt = $pdo->prepare("INSERT INTO configuracoes_dias_semana 
                                (dia_semana, horario_inicio, horario_fim, horario_inicio_almoco, horario_fim_almoco, tempo_indisponivel_entrada, funcionamento_ativo) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
                            if ($stmt->execute([$dia, $horario_inicio, $horario_fim, $horario_inicio_almoco, $horario_fim_almoco, $tempo_indisponivel_entrada, $ativo])) {
                                $atualizacoes++;
                            }
                        }
                    }
                }
            }
            
            error_log("Sincronização do arquivo para o banco concluída: $atualizacoes atualizações realizadas");
        }
        
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