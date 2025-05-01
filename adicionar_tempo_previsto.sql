-- Adicionar campo de tempo previsto à tabela de orçamentos
ALTER TABLE orcamentos ADD COLUMN tempo_previsto_horas INTEGER DEFAULT 2;

-- Comentário sobre o campo
COMMENT ON COLUMN orcamentos.tempo_previsto_horas IS 'Tempo previsto em horas para conclusão do serviço';
