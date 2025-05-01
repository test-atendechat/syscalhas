<?php
// Configurações do sistema
define('APP_NAME', 'SANDRO CALHAS LTDA');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/');

// Dados da empresa
define('EMPRESA_TELEFONE', '(19) 99262-0970');
define('EMPRESA_EMAIL', 'contato@sandrocalhas.com.br');
define('EMPRESA_ENDERECO', 'Rua Carlos Pulici, 387, Vila Franco, Descalvado - SP');
define('EMPRESA_CNPJ', '48.998.641/0001-03');

// Configurações de Orçamentos
define('TAXA_PADRAO_MAO_OBRA', '100');
define('DESCONTO_PAGAMENTO_VISTA', '10');
define('MAX_PARCELAS', '12');
define('DIAS_VALIDADE_ORCAMENTO', '30');

// Configurações de Horário de Funcionamento
define('HORARIO_INICIO', '07:00');
define('HORARIO_FIM', '17:00');
define('DIAS_FUNCIONAMENTO', '1,2,3,4,5'); // Incluindo sábado (6)

// Configurações de Indisponibilidade Automática
define('APLICAR_INDISPONIBILIDADE_AUTOMATICA', 'sim');
define('HORARIO_INICIO_ALMOCO', '11:00');
define('HORARIO_FIM_ALMOCO', '12:00');
define('TEMPO_INDISPONIVEL_ENTRADA', '30');

// Configurações específicas para cada dia da semana
// Domingo (0)
define('DIA_0_ATIVO', 'sim');
define('DIA_0_HORARIO_INICIO', '07:00:00');
define('DIA_0_HORARIO_FIM', '17:00:00');
define('DIA_0_HORARIO_INICIO_ALMOCO', '11:00:00');
define('DIA_0_HORARIO_FIM_ALMOCO', '12:00:00');
define('DIA_0_TEMPO_INDISPONIVEL_ENTRADA', '30');

// Segunda-feira (1)
define('DIA_1_ATIVO', 'sim');
define('DIA_1_HORARIO_INICIO', '07:00:00');
define('DIA_1_HORARIO_FIM', '17:00:00');
define('DIA_1_HORARIO_INICIO_ALMOCO', '11:00:00');
define('DIA_1_HORARIO_FIM_ALMOCO', '12:00:00');
define('DIA_1_TEMPO_INDISPONIVEL_ENTRADA', '30');

// Terça-feira (2)
define('DIA_2_ATIVO', 'sim');
define('DIA_2_HORARIO_INICIO', '07:00:00');
define('DIA_2_HORARIO_FIM', '17:00:00');
define('DIA_2_HORARIO_INICIO_ALMOCO', '11:00:00');
define('DIA_2_HORARIO_FIM_ALMOCO', '12:00:00');
define('DIA_2_TEMPO_INDISPONIVEL_ENTRADA', '30');

// Quarta-feira (3)
define('DIA_3_ATIVO', 'sim');
define('DIA_3_HORARIO_INICIO', '07:00:00');
define('DIA_3_HORARIO_FIM', '17:00:00');
define('DIA_3_HORARIO_INICIO_ALMOCO', '11:00:00');
define('DIA_3_HORARIO_FIM_ALMOCO', '12:00:00');
define('DIA_3_TEMPO_INDISPONIVEL_ENTRADA', '30');

// Quinta-feira (4)
define('DIA_4_ATIVO', 'sim');
define('DIA_4_HORARIO_INICIO', '07:00:00');
define('DIA_4_HORARIO_FIM', '17:00:00');
define('DIA_4_HORARIO_INICIO_ALMOCO', '11:00:00');
define('DIA_4_HORARIO_FIM_ALMOCO', '12:00:00');
define('DIA_4_TEMPO_INDISPONIVEL_ENTRADA', '30');

// Sexta-feira (5)
define('DIA_5_ATIVO', 'sim');
define('DIA_5_HORARIO_INICIO', '07:00:00');
define('DIA_5_HORARIO_FIM', '17:00:00');
define('DIA_5_HORARIO_INICIO_ALMOCO', '11:00:00');
define('DIA_5_HORARIO_FIM_ALMOCO', '12:00:00');
define('DIA_5_TEMPO_INDISPONIVEL_ENTRADA', '30');

// Sábado (6)
define('DIA_6_ATIVO', 'sim');
define('DIA_6_HORARIO_INICIO', '08:00:00');
define('DIA_6_HORARIO_FIM', '12:00:00');
define('DIA_6_HORARIO_INICIO_ALMOCO', '00:00:00'); // Sem almoço no sábado
define('DIA_6_HORARIO_FIM_ALMOCO', '00:00:00'); // Sem almoço no sábado
define('DIA_6_TEMPO_INDISPONIVEL_ENTRADA', '15');

// Configurações de Estoque
define('ESTOQUE_ALERTA_MINIMO', '50');

// Configurações de Visitas Técnicas
define('TEMPO_VISITA_TECNICA', '30');
define('UNIDADE_TEMPO_VISITA', 'minutos');

// Configurações de Aparência
define('COR_PRINCIPAL', '#0d6efd');
define('COR_SECUNDARIA', '#6c757d');
define('COR_APROVADO', '#198754');
define('COR_PENDENTE', '#ffc107');
define('COR_REJEITADO', '#dc3545');
define('TEMA_SISTEMA', 'light');
define('ESTILO_MENU', 'black');

// Configurações de Integrações
define('API_PREVISAO_TEMPO', 'true');
define('API_PREVISAO_TEMPO_KEY', '');
define('API_PREVISAO_TEMPO_PROVIDER', 'openweathermap');
define('CIDADE_PREVISAO_TEMPO', '');
define('DIAS_REAGENDAMENTO_CHUVA', '2');

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do banco de dados
define('DB_TYPE', 'pgsql'); // mysql ou pgsql

// Verifica se o arquivo de configuração local existe
$config_local = __DIR__ . '/config.local.php';
if (file_exists($config_local)) {
    // Se existir, inclui o arquivo de configuração local
    include($config_local);
} else {
    // Se não existir, usa as variáveis de ambiente ou valores padrão
    define('DB_HOST', getenv('PGHOST') ?: 'localhost');
    define('DB_NAME', getenv('PGDATABASE') ?: 'postgres');
    define('DB_USER', getenv('PGUSER') ?: 'postgres');
    define('DB_PASS', getenv('PGPASSWORD') ?: 'postgres');
    define('DB_PORT', getenv('PGPORT') ?: '5432');
}

// Configurações de data e hora
date_default_timezone_set('America/Sao_Paulo');

// A funcionalidade de atualização automática foi movida para includes/atualizacao_automatica.php

// Configurações de exibição de erros (desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>