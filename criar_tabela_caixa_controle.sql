-- Script para criar a tabela caixa_controle
CREATE TABLE IF NOT EXISTS caixa_controle (
    id SERIAL PRIMARY KEY,
    data_abertura DATE NOT NULL,
    hora_abertura TIMESTAMP NOT NULL,
    data_fechamento DATE,
    hora_fechamento TIMESTAMP,
    valor_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_final DECIMAL(10,2),
    diferenca DECIMAL(10,2),
    valor_conferido_dinheiro DECIMAL(10,2),
    valor_conferido_credito DECIMAL(10,2),
    valor_conferido_debito DECIMAL(10,2),
    valor_conferido_pix DECIMAL(10,2),
    valor_conferido_outro DECIMAL(10,2),
    usuario_abertura_id INTEGER NOT NULL,
    usuario_fechamento_id INTEGER,
    observacoes_abertura TEXT,
    observacoes_fechamento TEXT
);

-- Verifica se a referência da tabela caixa para caixa_controle está correta
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'caixa' AND column_name = 'caixa_controle_id'
    ) THEN
        ALTER TABLE caixa ADD COLUMN caixa_controle_id INTEGER;
    END IF;
EXCEPTION WHEN OTHERS THEN
    -- Se a tabela caixa não existir, o erro é ignorado
    -- (precisa ser criada em outro script)
END
$$;