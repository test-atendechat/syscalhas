<?php
// Este é um patch para adicionar notificações ao caixa_form.php

// Primeiro bloco:
// Modificar a consulta para obter também o número do orçamento
// Linha 317
$linha317 = "                    \$stmt = \$db->prepare(\"SELECT status_pagamento, numero FROM orcamentos WHERE id = :id\");";

// Adicionar variável de valor formatado após o fetch
// Linha 320
$after_linha320 = "                    
                    // Formatar valor para exibição na notificação
                    \$valor_formatado = 'R$ ' . number_format(\$movimentacao['valor'], 2, ',', '.');";

// Adicionar notificação para pagamento total
// Linha 323
$linha323 = "                    if (\$status_data && \$status_data['status_pagamento'] == 'pago_total') {
                        // Adicionar notificação de pagamento total
                        adicionarNotificacao(
                            \"Pagamento TOTAL de {\$valor_formatado} registrado para o orçamento #{\$status_data['numero']}\",
                            'success',
                            \"orcamento_visualizar.php?id={\$orcamento_id_redirect}\"
                        );
                        header(\"Location: orcamento_visualizar.php?id={\$orcamento_id_redirect}&mensagem=pago\");";

// Adicionar notificação para pagamento parcial
// Linha 326
$linha326 = "                    } else {
                        // Adicionar notificação de pagamento parcial
                        adicionarNotificacao(
                            \"Pagamento PARCIAL de {\$valor_formatado} registrado para o orçamento #{\$status_data['numero']}\",
                            'warning',
                            \"orcamento_visualizar.php?id={\$orcamento_id_redirect}\"
                        );
                        // Agora redirecionamos para a página de orçamento mesmo com pagamento parcial
                        header(\"Location: orcamento_visualizar.php?id={\$orcamento_id_redirect}&mensagem=pagamento_parcial\");";

// Segundo bloco (repetir as mesmas modificações para o segundo caso)
// Linha 332
$linha332 = "                    \$stmt = \$db->prepare(\"SELECT status_pagamento, numero FROM orcamentos WHERE id = :id\");";

// Adicionar variável de valor formatado após o fetch
// Linha 335
$after_linha335 = "                    
                    // Formatar valor para exibição na notificação
                    \$valor_formatado = 'R$ ' . number_format(\$movimentacao['valor'], 2, ',', '.');";

// Adicionar notificação para pagamento total
// Linha 338
$linha338 = "                    if (\$status_data && \$status_data['status_pagamento'] == 'pago_total') {
                        // Adicionar notificação de pagamento total
                        adicionarNotificacao(
                            \"Pagamento TOTAL de {\$valor_formatado} registrado para o orçamento #{\$status_data['numero']}\",
                            'success',
                            \"orcamento_visualizar.php?id={\$orcamento_id_redirect}\"
                        );
                        header(\"Location: orcamento_visualizar.php?id={\$orcamento_id_redirect}&mensagem=pago\");";

// Adicionar notificação para pagamento parcial
// Linha 341
$linha341 = "                    } else {
                        // Adicionar notificação de pagamento parcial
                        adicionarNotificacao(
                            \"Pagamento PARCIAL de {\$valor_formatado} registrado para o orçamento #{\$status_data['numero']}\",
                            'warning',
                            \"orcamento_visualizar.php?id={\$orcamento_id_redirect}\"
                        );
                        // Agora redirecionamos para a página de orçamento mesmo com pagamento parcial
                        header(\"Location: orcamento_visualizar.php?id={\$orcamento_id_redirect}&mensagem=pagamento_parcial\");";

echo "Patch criado com sucesso!\n";
