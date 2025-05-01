-- Criar tabela de agendamento_instaladores para relacionamento many-to-many
CREATE TABLE IF NOT EXISTS agendamento_instaladores (
    id SERIAL PRIMARY KEY,
    agendamento_id INTEGER NOT NULL,
    instalador_id INTEGER NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agendamento FOREIGN KEY (agendamento_id) REFERENCES agendamentos (id) ON DELETE CASCADE,
    CONSTRAINT fk_instalador FOREIGN KEY (instalador_id) REFERENCES instaladores (id) ON DELETE CASCADE,
    CONSTRAINT uk_agendamento_instalador UNIQUE (agendamento_id, instalador_id)
);

-- Criar tabela de agendamento_auxiliares para registrar auxiliares de cada instalador no agendamento
CREATE TABLE IF NOT EXISTS agendamento_auxiliares (
    id SERIAL PRIMARY KEY,
    agendamento_id INTEGER NOT NULL,
    instalador_id INTEGER NOT NULL,
    auxiliar_id INTEGER NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agendamento_aux FOREIGN KEY (agendamento_id) REFERENCES agendamentos (id) ON DELETE CASCADE,
    CONSTRAINT fk_instalador_aux FOREIGN KEY (instalador_id) REFERENCES instaladores (id) ON DELETE CASCADE,
    CONSTRAINT fk_auxiliar FOREIGN KEY (auxiliar_id) REFERENCES instaladores (id) ON DELETE CASCADE,
    CONSTRAINT uk_agendamento_instalador_auxiliar UNIQUE (agendamento_id, instalador_id, auxiliar_id)
);

-- Verificar se a tabela agendamentos já tem as colunas para instalador_id e auxiliar_id
-- Se tiver, vamos mantê-las para compatibilidade com código existente
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT FROM information_schema.columns 
        WHERE table_name = 'agendamentos' AND column_name = 'instalador_id'
    ) THEN
        ALTER TABLE agendamentos ADD COLUMN instalador_id INTEGER;
    END IF;
    
    IF NOT EXISTS (
        SELECT FROM information_schema.columns 
        WHERE table_name = 'agendamentos' AND column_name = 'auxiliar_id'
    ) THEN
        ALTER TABLE agendamentos ADD COLUMN auxiliar_id INTEGER;
    END IF;
    
    -- Migrar dados existentes para o novo modelo, se necessário
    IF EXISTS (
        SELECT FROM agendamentos 
        WHERE instalador_id IS NOT NULL
    ) THEN
        INSERT INTO agendamento_instaladores (agendamento_id, instalador_id)
        SELECT id, instalador_id FROM agendamentos 
        WHERE instalador_id IS NOT NULL
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Criar índices para melhorar performance
CREATE INDEX IF NOT EXISTS idx_agendamento_instaladores_agendamento_id ON agendamento_instaladores (agendamento_id);
CREATE INDEX IF NOT EXISTS idx_agendamento_instaladores_instalador_id ON agendamento_instaladores (instalador_id);
CREATE INDEX IF NOT EXISTS idx_agendamento_auxiliares_agendamento_id ON agendamento_auxiliares (agendamento_id);
CREATE INDEX IF NOT EXISTS idx_agendamento_auxiliares_instalador_id ON agendamento_auxiliares (instalador_id);
CREATE INDEX IF NOT EXISTS idx_agendamento_auxiliares_auxiliar_id ON agendamento_auxiliares (auxiliar_id);
