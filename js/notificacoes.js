/**
 * Sistema de Notificações
 */
document.addEventListener('DOMContentLoaded', function() {
    // Função vazia para compatibilidade (sons removidos)
    function reproduzirSom(tipo) {
        // Sons foram removidos conforme solicitado
        return;
    }
    // Elementos do DOM
    const notificacoesToggle = document.getElementById('notificacoes-toggle');
    const notificacoesDropdown = document.getElementById('notificacoes-dropdown');
    const notificacoesContador = document.getElementById('notificacoes-contador');
    const notificacoesLista = document.getElementById('notificacoes-lista');
    const notificacoesVazia = document.getElementById('notificacoes-vazia');
    const marcarTodasBtn = document.getElementById('marcar-todas-lidas');
    
    // Variáveis do sistema
    let notificacoes = [];
    let quantidadeNaoLidas = 0;
    let intervaloAtualizacao = null;
    
    // Função para formatar data relativa (ex: "há 5 minutos")
    function formatarDataRelativa(dataString) {
        const data = new Date(dataString);
        const agora = new Date();
        const diff = Math.floor((agora - data) / 1000); // Diferença em segundos
        
        if (diff < 60) {
            return 'Agora mesmo';
        } else if (diff < 3600) {
            const minutos = Math.floor(diff / 60);
            return `há ${minutos} ${minutos === 1 ? 'minuto' : 'minutos'}`;
        } else if (diff < 86400) {
            const horas = Math.floor(diff / 3600);
            return `há ${horas} ${horas === 1 ? 'hora' : 'horas'}`;
        } else {
            const dias = Math.floor(diff / 86400);
            return `há ${dias} ${dias === 1 ? 'dia' : 'dias'}`;
        }
    }
    
    // Obter ícone baseado no tipo da notificação
    function obterIcone(tipo) {
        switch (tipo) {
            case 'success':
                return '<i class="fas fa-check-circle text-success"></i>';
            case 'warning':
                return '<i class="fas fa-exclamation-triangle text-warning"></i>';
            case 'danger':
                return '<i class="fas fa-times-circle text-danger"></i>';
            case 'info':
            default:
                return '<i class="fas fa-info-circle text-info"></i>';
        }
    }
    
    // Renderizar notificações na lista
    function renderizarNotificacoes() {
        // Limpar lista
        notificacoesLista.innerHTML = '';
        
        // Mostrar mensagem se não há notificações
        if (notificacoes.length === 0) {
            notificacoesVazia.style.display = 'block';
            return;
        }
        
        notificacoesVazia.style.display = 'none';
        
        // Adicionar cada notificação à lista
        notificacoes.forEach(notificacao => {
            const li = document.createElement('li');
            li.className = `notificacao-item ${notificacao.lida ? 'lida' : ''}`;
            li.dataset.id = notificacao.id;
            
            li.innerHTML = `
                <div class="notificacao-conteudo">
                    <div class="notificacao-icone">
                        ${obterIcone(notificacao.tipo)}
                    </div>
                    <div class="notificacao-info">
                        <div class="notificacao-mensagem">${notificacao.mensagem}</div>
                        <div class="notificacao-tempo">${formatarDataRelativa(notificacao.data_criacao)}</div>
                    </div>
                </div>
            `;
            
            // Adicionar evento de clique
            li.addEventListener('click', function() {
                marcarComoLida(notificacao.id);
                if (notificacao.link) {
                    window.location.href = notificacao.link;
                }
            });
            
            notificacoesLista.appendChild(li);
        });
    }
    
    // Atualizar contador de notificações
    function atualizarContador() {
        if (quantidadeNaoLidas > 0) {
            notificacoesContador.textContent = quantidadeNaoLidas > 9 ? '9+' : quantidadeNaoLidas;
            notificacoesContador.style.display = 'flex';
        } else {
            notificacoesContador.style.display = 'none';
        }
    }
    
    // Buscar notificações do servidor
    function buscarNotificacoes() {
        let ultimaQuantidade = quantidadeNaoLidas;
        
        fetch('ajax/obter_notificacoes.php?acao=listar')
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    notificacoes = data.notificacoes;
                    quantidadeNaoLidas = data.quantidade;
                    renderizarNotificacoes();
                    atualizarContador();
                    
                    // As notificações sonoras foram removidas conforme solicitado
                    // Mantemos apenas a atualização visual
                }
            })
            .catch(error => console.error('Erro ao buscar notificações:', error));
    }
    
    // Marcar notificação como lida
    function marcarComoLida(id) {
        fetch(`ajax/obter_notificacoes.php?acao=marcar_lida&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    // Atualizar localmente
                    const notificacao = notificacoes.find(n => n.id == id);
                    if (notificacao) notificacao.lida = 1;
                    
                    quantidadeNaoLidas = data.quantidade;
                    renderizarNotificacoes();
                    atualizarContador();
                }
            })
            .catch(error => console.error('Erro ao marcar notificação como lida:', error));
    }
    
    // Marcar todas notificações como lidas
    function marcarTodasComoLidas() {
        fetch('ajax/obter_notificacoes.php?acao=marcar_todas_lidas')
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    // Atualizar localmente
                    notificacoes.forEach(n => n.lida = 1);
                    quantidadeNaoLidas = 0;
                    renderizarNotificacoes();
                    atualizarContador();
                }
            })
            .catch(error => console.error('Erro ao marcar todas notificações como lidas:', error));
    }
    
    // Iniciar sistema de notificações
    function iniciarSistemaNotificacoes() {
        // Buscar notificações iniciais
        buscarNotificacoes();
        
        // Configurar atualização periódica (a cada 30 segundos)
        intervaloAtualizacao = setInterval(buscarNotificacoes, 30000);
        
        // Configurar toggle do dropdown
        if (notificacoesToggle) {
            notificacoesToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                notificacoesDropdown.style.display = notificacoesDropdown.style.display === 'block' ? 'none' : 'block';
            });
        }
        
        // Configurar botão de marcar todas como lidas
        if (marcarTodasBtn) {
            marcarTodasBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                marcarTodasComoLidas();
            });
        }
        
        // Fechar dropdown quando clicar fora
        document.addEventListener('click', function(e) {
            if (notificacoesDropdown && notificacoesDropdown.style.display === 'block') {
                if (!notificacoesDropdown.contains(e.target) && e.target !== notificacoesToggle) {
                    notificacoesDropdown.style.display = 'none';
                }
            }
        });
    }
    
    // Iniciar o sistema de notificações se os elementos existirem
    if (notificacoesToggle && notificacoesDropdown && notificacoesLista) {
        iniciarSistemaNotificacoes();
    }
});