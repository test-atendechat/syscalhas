        </div> <!-- fecha o container do header -->
        
        <!-- Modal de confirmação para exclusão -->
        <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p id="mensagemConfirmacao">Tem certeza que deseja excluir este registro?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <form id="formExclusao" method="post">
                            <input type="hidden" id="id_exclusao" name="id">
                            <input type="hidden" name="excluir" value="1">
                            <button type="submit" class="btn btn-danger">Excluir</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="mt-5 py-3 bg-light text-center text-muted">
            <div class="container">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></p>
            </div>
        </footer>
        
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- Bootstrap Bundle com Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Scripts customizados -->
        <script>
            // Script para confirmação de exclusão genérica
            function confirmarExclusao(id, nome, formulario = '') {
                document.getElementById('id_exclusao').value = id;
                document.getElementById('mensagemConfirmacao').innerText = 'Tem certeza que deseja excluir "' + nome + '"?';
                
                if (formulario) {
                    document.getElementById('formExclusao').setAttribute('action', formulario);
                }
                
                var modal = new bootstrap.Modal(document.getElementById('modalConfirmacao'));
                modal.show();
            }
            
            // Formatação de valores monetários para inputs
            document.addEventListener('DOMContentLoaded', function() {
                const monetaryInputs = document.querySelectorAll('.monetary-input');
                
                monetaryInputs.forEach(function(input) {
                    input.addEventListener('input', function(e) {
                        let valor = e.target.value.replace(/\D/g, '');
                        
                        if (valor.length === 0) {
                            e.target.value = '';
                            return;
                        }
                        
                        // Converter para formato de moeda
                        valor = (parseInt(valor) / 100).toFixed(2);
                        e.target.value = valor.replace('.', ',');
                    });
                });
            });
        </script>
    </body>
</html>