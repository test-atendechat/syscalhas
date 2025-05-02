<?php
// Script para atualizar como os agendamentos são consultados
// Executar diretamente no servidor para modificar o arquivo orcamento_visualizar.php

$arquivo = 'orcamento_visualizar.php';
$conteudo = file_get_contents($arquivo);

// 1. Atualizar a função buscarAgendamentoAtivo para incluir status 'pendente'
$conteudo = str_replace(
    "WHERE a.orcamento_id = :orcamento_id \n                              AND (a.status = 'orcamento_agendado' OR a.status = 'instalacao_agendada')",
    "WHERE a.orcamento_id = :orcamento_id \n                              AND (a.status = 'orcamento_agendado' OR a.status = 'instalacao_agendada' OR a.status = 'pendente')",
    $conteudo
);

// 2. Remover a condição de status para buscar agendamentos (2 ocorrências)
$padrao_if = "/        \/\/ Buscar dados de agendamento se o status de execução for 'agendado'\n        \\\$agendamento = null;\n        if \(\\\$orcamento\['status_execucao'\] == 'agendado' \|\| \\\$orcamento\['status_execucao'\] == 'orcamento_agendado'\) {\n            \/\/ Usar a nova função para buscar agendamento ativo\n            \\\$agendamento = buscarAgendamentoAtivo\(\\\$id\);\n        }/";

$substitui_if = "        // Buscar dados de agendamento independente do status\n        \$agendamento = null;\n        // Sempre buscar agendamento para verificar se há pendentes\n        \$agendamento = buscarAgendamentoAtivo(\$id);";

$conteudo = preg_replace($padrao_if, $substitui_if, $conteudo);

// 3. Modificar a condição para mostrar os botões de aprovação/rejeição
$conteudo = str_replace(
    '<?php if (isset($agendamento) && $agendamento && $agendamento[\'status\'] == \'pendente\'): ?>',
    '<?php if (isset($agendamento) && $agendamento): ?>',
    $conteudo
);

// Salvar as alterações
file_put_contents($arquivo, $conteudo);

echo "Script executado com sucesso!\n";
