--
-- PostgreSQL database dump
--

-- Dumped from database version 16.8
-- Dumped by pg_dump version 16.5

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE IF EXISTS ONLY public.produtos DROP CONSTRAINT IF EXISTS produtos_categoria_id_fkey;
ALTER TABLE IF EXISTS ONLY public.permissoes DROP CONSTRAINT IF EXISTS permissoes_usuario_id_fkey;
ALTER TABLE IF EXISTS ONLY public.pagamentos_contas DROP CONSTRAINT IF EXISTS pagamentos_contas_usuario_id_fkey;
ALTER TABLE IF EXISTS ONLY public.pagamentos_contas DROP CONSTRAINT IF EXISTS pagamentos_contas_conta_id_fkey;
ALTER TABLE IF EXISTS ONLY public.pagamentos_contas DROP CONSTRAINT IF EXISTS pagamentos_contas_caixa_id_fkey;
ALTER TABLE IF EXISTS ONLY public.orcamentos DROP CONSTRAINT IF EXISTS orcamentos_usuario_id_fkey;
ALTER TABLE IF EXISTS ONLY public.orcamentos DROP CONSTRAINT IF EXISTS orcamentos_cliente_id_fkey;
ALTER TABLE IF EXISTS ONLY public.orcamento_itens DROP CONSTRAINT IF EXISTS orcamento_itens_produto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.orcamento_itens DROP CONSTRAINT IF EXISTS orcamento_itens_orcamento_id_fkey;
ALTER TABLE IF EXISTS ONLY public.vendas DROP CONSTRAINT IF EXISTS fk_vendas_usuario;
ALTER TABLE IF EXISTS ONLY public.vendas_itens DROP CONSTRAINT IF EXISTS fk_vendas_itens_venda;
ALTER TABLE IF EXISTS ONLY public.vendas_itens DROP CONSTRAINT IF EXISTS fk_vendas_itens_produto;
ALTER TABLE IF EXISTS ONLY public.vendas DROP CONSTRAINT IF EXISTS fk_vendas_cliente;
ALTER TABLE IF EXISTS ONLY public.caixa DROP CONSTRAINT IF EXISTS fk_caixa_usuario;
ALTER TABLE IF EXISTS ONLY public.caixa DROP CONSTRAINT IF EXISTS fk_caixa_orcamento;
ALTER TABLE IF EXISTS ONLY public.caixa DROP CONSTRAINT IF EXISTS fk_caixa_estorno;
ALTER TABLE IF EXISTS ONLY public.caixa DROP CONSTRAINT IF EXISTS fk_caixa_cliente;
ALTER TABLE IF EXISTS ONLY public.estornos_pagamentos DROP CONSTRAINT IF EXISTS estornos_pagamentos_usuario_id_fkey;
ALTER TABLE IF EXISTS ONLY public.estornos_pagamentos DROP CONSTRAINT IF EXISTS estornos_pagamentos_conta_id_fkey;
ALTER TABLE IF EXISTS ONLY public.estoque_movimentacoes DROP CONSTRAINT IF EXISTS estoque_movimentacoes_usuario_id_fkey;
ALTER TABLE IF EXISTS ONLY public.estoque_movimentacoes DROP CONSTRAINT IF EXISTS estoque_movimentacoes_produto_id_fkey;
ALTER TABLE IF EXISTS ONLY public.contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_usuario_id_fkey;
ALTER TABLE IF EXISTS ONLY public.contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_conta_pai_id_fkey;
ALTER TABLE IF EXISTS ONLY public.caixa DROP CONSTRAINT IF EXISTS caixa_venda_id_fkey;
ALTER TABLE IF EXISTS ONLY public.caixa_controle DROP CONSTRAINT IF EXISTS caixa_controle_usuario_fechamento_id_fkey;
ALTER TABLE IF EXISTS ONLY public.caixa_controle DROP CONSTRAINT IF EXISTS caixa_controle_usuario_abertura_id_fkey;
ALTER TABLE IF EXISTS ONLY public.caixa DROP CONSTRAINT IF EXISTS caixa_conta_pagar_id_fkey;
ALTER TABLE IF EXISTS ONLY public.vendas DROP CONSTRAINT IF EXISTS vendas_pkey;
ALTER TABLE IF EXISTS ONLY public.vendas_itens DROP CONSTRAINT IF EXISTS vendas_itens_pkey;
ALTER TABLE IF EXISTS ONLY public.usuarios DROP CONSTRAINT IF EXISTS usuarios_pkey;
ALTER TABLE IF EXISTS ONLY public.usuarios DROP CONSTRAINT IF EXISTS usuarios_email_key;
ALTER TABLE IF EXISTS ONLY public.produtos DROP CONSTRAINT IF EXISTS produtos_pkey;
ALTER TABLE IF EXISTS ONLY public.permissoes DROP CONSTRAINT IF EXISTS permissoes_usuario_id_key;
ALTER TABLE IF EXISTS ONLY public.permissoes DROP CONSTRAINT IF EXISTS permissoes_pkey;
ALTER TABLE IF EXISTS ONLY public.pagamentos_contas DROP CONSTRAINT IF EXISTS pagamentos_contas_pkey;
ALTER TABLE IF EXISTS ONLY public.orcamentos DROP CONSTRAINT IF EXISTS orcamentos_pkey;
ALTER TABLE IF EXISTS ONLY public.orcamentos DROP CONSTRAINT IF EXISTS orcamentos_numero_key;
ALTER TABLE IF EXISTS ONLY public.orcamentos DROP CONSTRAINT IF EXISTS orcamentos_codigo_acesso_key;
ALTER TABLE IF EXISTS ONLY public.orcamento_itens DROP CONSTRAINT IF EXISTS orcamento_itens_pkey;
ALTER TABLE IF EXISTS ONLY public.estornos_pagamentos DROP CONSTRAINT IF EXISTS estornos_pagamentos_pkey;
ALTER TABLE IF EXISTS ONLY public.estoque_movimentacoes DROP CONSTRAINT IF EXISTS estoque_movimentacoes_pkey;
ALTER TABLE IF EXISTS ONLY public.contas_pagar DROP CONSTRAINT IF EXISTS contas_pagar_pkey;
ALTER TABLE IF EXISTS ONLY public.configuracoes DROP CONSTRAINT IF EXISTS configuracoes_pkey;
ALTER TABLE IF EXISTS ONLY public.configuracoes DROP CONSTRAINT IF EXISTS configuracoes_chave_key;
ALTER TABLE IF EXISTS ONLY public.clientes DROP CONSTRAINT IF EXISTS clientes_pkey;
ALTER TABLE IF EXISTS ONLY public.categorias DROP CONSTRAINT IF EXISTS categorias_pkey;
ALTER TABLE IF EXISTS ONLY public.caixa DROP CONSTRAINT IF EXISTS caixa_pkey;
ALTER TABLE IF EXISTS ONLY public.caixa_controle DROP CONSTRAINT IF EXISTS caixa_controle_pkey;
ALTER TABLE IF EXISTS public.vendas_itens ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.vendas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.usuarios ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.produtos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.permissoes ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.pagamentos_contas ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.orcamentos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.orcamento_itens ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.estornos_pagamentos ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.estoque_movimentacoes ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.contas_pagar ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.configuracoes ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.clientes ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.categorias ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.caixa_controle ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.caixa ALTER COLUMN id DROP DEFAULT;
DROP SEQUENCE IF EXISTS public.vendas_itens_id_seq;
DROP TABLE IF EXISTS public.vendas_itens;
DROP SEQUENCE IF EXISTS public.vendas_id_seq;
DROP TABLE IF EXISTS public.vendas;
DROP SEQUENCE IF EXISTS public.usuarios_id_seq;
DROP TABLE IF EXISTS public.usuarios;
DROP SEQUENCE IF EXISTS public.produtos_id_seq;
DROP TABLE IF EXISTS public.produtos;
DROP SEQUENCE IF EXISTS public.permissoes_id_seq;
DROP TABLE IF EXISTS public.permissoes;
DROP SEQUENCE IF EXISTS public.pagamentos_contas_id_seq;
DROP TABLE IF EXISTS public.pagamentos_contas;
DROP SEQUENCE IF EXISTS public.orcamentos_id_seq;
DROP TABLE IF EXISTS public.orcamentos;
DROP SEQUENCE IF EXISTS public.orcamento_itens_id_seq;
DROP TABLE IF EXISTS public.orcamento_itens;
DROP SEQUENCE IF EXISTS public.estornos_pagamentos_id_seq;
DROP TABLE IF EXISTS public.estornos_pagamentos;
DROP SEQUENCE IF EXISTS public.estoque_movimentacoes_id_seq;
DROP TABLE IF EXISTS public.estoque_movimentacoes;
DROP SEQUENCE IF EXISTS public.contas_pagar_id_seq;
DROP TABLE IF EXISTS public.contas_pagar;
DROP SEQUENCE IF EXISTS public.configuracoes_id_seq;
DROP TABLE IF EXISTS public.configuracoes;
DROP SEQUENCE IF EXISTS public.clientes_id_seq;
DROP TABLE IF EXISTS public.clientes;
DROP SEQUENCE IF EXISTS public.categorias_id_seq;
DROP TABLE IF EXISTS public.categorias;
DROP SEQUENCE IF EXISTS public.caixa_id_seq;
DROP SEQUENCE IF EXISTS public.caixa_controle_id_seq;
DROP TABLE IF EXISTS public.caixa_controle;
DROP TABLE IF EXISTS public.caixa;
SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: caixa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.caixa (
    id integer NOT NULL,
    data_operacao date DEFAULT CURRENT_DATE NOT NULL,
    tipo character varying(20) NOT NULL,
    descricao text NOT NULL,
    valor numeric(10,2) NOT NULL,
    forma_pagamento character varying(20) DEFAULT 'dinheiro'::character varying NOT NULL,
    orcamento_id integer,
    usuario_id integer NOT NULL,
    observacoes text,
    data_registro timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    venda_id integer,
    cliente_id integer,
    conta_pagar_id integer,
    estorno_id integer,
    caixa_controle_id integer
);


--
-- Name: caixa_controle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.caixa_controle (
    id integer NOT NULL,
    data_abertura date NOT NULL,
    hora_abertura timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    valor_inicial numeric(10,2) DEFAULT 0 NOT NULL,
    data_fechamento date,
    hora_fechamento timestamp without time zone,
    valor_final numeric(10,2),
    valor_informado numeric(10,2),
    diferenca numeric(10,2),
    observacoes text,
    usuario_abertura_id integer,
    usuario_fechamento_id integer,
    observacoes_abertura text,
    valor_conferido_dinheiro numeric(10,2),
    valor_conferido_credito numeric(10,2),
    valor_conferido_debito numeric(10,2),
    valor_conferido_pix numeric(10,2),
    valor_conferido_outro numeric(10,2),
    observacoes_fechamento text
);


--
-- Name: caixa_controle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caixa_controle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: caixa_controle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caixa_controle_id_seq OWNED BY public.caixa_controle.id;


--
-- Name: caixa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caixa_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: caixa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caixa_id_seq OWNED BY public.caixa.id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    descricao character varying(255)
);


--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    tipo character varying(10) DEFAULT 'fisica'::character varying NOT NULL,
    cpf_cnpj character varying(20),
    email character varying(100),
    telefone character varying(20),
    endereco character varying(255),
    cidade character varying(100),
    estado character varying(2),
    cep character varying(10),
    observacoes text,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- Name: configuracoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuracoes (
    id integer NOT NULL,
    chave character varying(50) NOT NULL,
    valor text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cor_principal character varying(20) DEFAULT '#0d6efd'::character varying
);


--
-- Name: configuracoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuracoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuracoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuracoes_id_seq OWNED BY public.configuracoes.id;


--
-- Name: contas_pagar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contas_pagar (
    id integer NOT NULL,
    descricao character varying(255) NOT NULL,
    fornecedor character varying(255),
    data_emissao date NOT NULL,
    data_vencimento date NOT NULL,
    valor numeric(10,2) NOT NULL,
    status character varying(30) DEFAULT 'pendente'::character varying NOT NULL,
    observacoes text,
    documento character varying(100),
    categoria character varying(100),
    data_pagamento date,
    valor_pago numeric(10,2),
    forma_pagamento character varying(50),
    usuario_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp without time zone,
    recorrente boolean DEFAULT false,
    intervalo_dias integer DEFAULT 30,
    proxima_data date,
    conta_pai_id integer
);


--
-- Name: contas_pagar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contas_pagar_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contas_pagar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contas_pagar_id_seq OWNED BY public.contas_pagar.id;


--
-- Name: estoque_movimentacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.estoque_movimentacoes (
    id integer NOT NULL,
    produto_id integer NOT NULL,
    tipo character varying(20) NOT NULL,
    quantidade numeric(10,2) NOT NULL,
    valor_unitario numeric(10,2) NOT NULL,
    valor_total numeric(10,2) NOT NULL,
    observacao text,
    orcamento_id integer,
    data_movimentacao timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    usuario_id integer
);


--
-- Name: estoque_movimentacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.estoque_movimentacoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: estoque_movimentacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.estoque_movimentacoes_id_seq OWNED BY public.estoque_movimentacoes.id;


--
-- Name: estornos_pagamentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.estornos_pagamentos (
    id integer NOT NULL,
    conta_id integer NOT NULL,
    data_estorno date NOT NULL,
    valor numeric(10,2) NOT NULL,
    observacoes text,
    usuario_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: estornos_pagamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.estornos_pagamentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: estornos_pagamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.estornos_pagamentos_id_seq OWNED BY public.estornos_pagamentos.id;


--
-- Name: orcamento_itens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.orcamento_itens (
    id integer NOT NULL,
    orcamento_id integer NOT NULL,
    produto_id integer NOT NULL,
    descricao character varying(255) NOT NULL,
    unidade character varying(10) NOT NULL,
    quantidade numeric(10,2) NOT NULL,
    valor_unitario numeric(10,2) NOT NULL,
    valor_total numeric(10,2) NOT NULL
);


--
-- Name: orcamento_itens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.orcamento_itens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: orcamento_itens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.orcamento_itens_id_seq OWNED BY public.orcamento_itens.id;


--
-- Name: orcamentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.orcamentos (
    id integer NOT NULL,
    numero character varying(20) NOT NULL,
    cliente_id integer NOT NULL,
    data_criacao timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    data_validade date,
    status character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    taxa_mao_obra numeric(10,2) DEFAULT 0 NOT NULL,
    valor_produtos numeric(10,2) DEFAULT 0 NOT NULL,
    valor_mao_obra numeric(10,2) DEFAULT 0 NOT NULL,
    valor_total numeric(10,2) DEFAULT 0 NOT NULL,
    observacoes text,
    codigo_acesso character varying(32) NOT NULL,
    usuario_id integer,
    forma_pagamento character varying(50),
    status_pagamento character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    status_execucao character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    data_pagamento date,
    data_finalizacao date
);


--
-- Name: orcamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.orcamentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: orcamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.orcamentos_id_seq OWNED BY public.orcamentos.id;


--
-- Name: pagamentos_contas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pagamentos_contas (
    id integer NOT NULL,
    conta_id integer,
    data_pagamento date NOT NULL,
    valor numeric(10,2) NOT NULL,
    forma_pagamento character varying(50) NOT NULL,
    observacoes text,
    caixa_id integer,
    usuario_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: pagamentos_contas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pagamentos_contas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pagamentos_contas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pagamentos_contas_id_seq OWNED BY public.pagamentos_contas.id;


--
-- Name: permissoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissoes (
    id integer NOT NULL,
    usuario_id integer NOT NULL,
    gerenciar_usuarios boolean DEFAULT false,
    visualizar_relatorios_financeiros boolean DEFAULT false,
    gerenciar_estoque boolean DEFAULT false,
    gerenciar_produtos boolean DEFAULT false,
    gerenciar_orcamentos boolean DEFAULT false,
    gerenciar_vendas boolean DEFAULT false,
    gerenciar_clientes boolean DEFAULT false,
    gerenciar_caixa boolean DEFAULT false,
    gerenciar_contas boolean DEFAULT false,
    visualizar_relatorios_gerais boolean DEFAULT false,
    data_atualizacao timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: permissoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissoes_id_seq OWNED BY public.permissoes.id;


--
-- Name: produtos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produtos (
    id integer NOT NULL,
    codigo character varying(50),
    descricao character varying(255) NOT NULL,
    categoria_id integer,
    unidade character varying(10) NOT NULL,
    valor_unitario numeric(10,2) NOT NULL,
    estoque_atual numeric(10,2) DEFAULT 0 NOT NULL,
    estoque_minimo numeric(10,2) DEFAULT 0 NOT NULL,
    observacoes text,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    custo_unitario numeric DEFAULT 0 NOT NULL
);


--
-- Name: produtos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produtos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: produtos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produtos_id_seq OWNED BY public.produtos.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    email character varying(100) NOT NULL,
    senha character varying(255) NOT NULL,
    nivel character varying(20) DEFAULT 'usuario'::character varying NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: vendas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendas (
    id integer NOT NULL,
    numero character varying(20) NOT NULL,
    data_venda date DEFAULT CURRENT_DATE NOT NULL,
    cliente_id integer,
    valor_total numeric(10,2) DEFAULT 0 NOT NULL,
    forma_pagamento character varying(20) DEFAULT 'dinheiro'::character varying NOT NULL,
    status character varying(20) DEFAULT 'finalizada'::character varying NOT NULL,
    usuario_id integer NOT NULL,
    observacoes text,
    status_pagamento character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    data_pagamento date,
    valor_desconto numeric(10,2) DEFAULT 0
);


--
-- Name: vendas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendas_id_seq OWNED BY public.vendas.id;


--
-- Name: vendas_itens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendas_itens (
    id integer NOT NULL,
    venda_id integer NOT NULL,
    produto_id integer NOT NULL,
    descricao character varying(255) NOT NULL,
    unidade character varying(50) NOT NULL,
    quantidade numeric(10,2) NOT NULL,
    valor_unitario numeric(10,2) NOT NULL,
    valor_total numeric(10,2) NOT NULL
);


--
-- Name: vendas_itens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendas_itens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendas_itens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendas_itens_id_seq OWNED BY public.vendas_itens.id;


--
-- Name: caixa id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa ALTER COLUMN id SET DEFAULT nextval('public.caixa_id_seq'::regclass);


--
-- Name: caixa_controle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_controle ALTER COLUMN id SET DEFAULT nextval('public.caixa_controle_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- Name: configuracoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes ALTER COLUMN id SET DEFAULT nextval('public.configuracoes_id_seq'::regclass);


--
-- Name: contas_pagar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar ALTER COLUMN id SET DEFAULT nextval('public.contas_pagar_id_seq'::regclass);


--
-- Name: estoque_movimentacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estoque_movimentacoes ALTER COLUMN id SET DEFAULT nextval('public.estoque_movimentacoes_id_seq'::regclass);


--
-- Name: estornos_pagamentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estornos_pagamentos ALTER COLUMN id SET DEFAULT nextval('public.estornos_pagamentos_id_seq'::regclass);


--
-- Name: orcamento_itens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamento_itens ALTER COLUMN id SET DEFAULT nextval('public.orcamento_itens_id_seq'::regclass);


--
-- Name: orcamentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamentos ALTER COLUMN id SET DEFAULT nextval('public.orcamentos_id_seq'::regclass);


--
-- Name: pagamentos_contas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos_contas ALTER COLUMN id SET DEFAULT nextval('public.pagamentos_contas_id_seq'::regclass);


--
-- Name: permissoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes ALTER COLUMN id SET DEFAULT nextval('public.permissoes_id_seq'::regclass);


--
-- Name: produtos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos ALTER COLUMN id SET DEFAULT nextval('public.produtos_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Name: vendas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas ALTER COLUMN id SET DEFAULT nextval('public.vendas_id_seq'::regclass);


--
-- Name: vendas_itens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas_itens ALTER COLUMN id SET DEFAULT nextval('public.vendas_itens_id_seq'::regclass);


--
-- Data for Name: caixa; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) FROM stdin;
1	2025-04-30	entrada	Pagamento da venda #V2025040001	200.00	dinheiro	\N	1	Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 200,00, Valor restante: R$ 40.400,00	2025-04-30 04:13:59.851766	1	\N	\N	\N	\N
2	2025-04-30	entrada	Pagamento da venda #V2025040001	206.00	dinheiro	\N	1	Pagamento parcial - Valor pago anteriormente: R$ 200,00, Pagamento atual: R$ 206,00, Valor restante: R$ 40.194,00	2025-04-30 04:14:52.190417	1	\N	\N	\N	\N
3	2025-04-30	entrada	Pagamento do Orçamento #1	500.00	dinheiro	1	1		2025-04-30 04:21:27.780421	\N	1	\N	\N	\N
4	2025-04-30	entrada	Pagamento do Orçamento #1	312.00	dinheiro	1	1		2025-04-30 04:22:47.882286	\N	1	\N	\N	\N
\.


--
-- Data for Name: caixa_controle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.caixa_controle (id, data_abertura, hora_abertura, valor_inicial, data_fechamento, hora_fechamento, valor_final, valor_informado, diferenca, observacoes, usuario_abertura_id, usuario_fechamento_id, observacoes_abertura, valor_conferido_dinheiro, valor_conferido_credito, valor_conferido_debito, valor_conferido_pix, valor_conferido_outro, observacoes_fechamento) FROM stdin;
\.


--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categorias (id, nome, descricao) FROM stdin;
1	Cortes	\N
2	Parafusos	\N
\.


--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes (id, nome, tipo, cpf_cnpj, email, telefone, endereco, cidade, estado, cep, observacoes, data_cadastro) FROM stdin;
1	Sem Identificação	fisica									2025-04-29 20:01:03.918571
\.


--
-- Data for Name: configuracoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.configuracoes (id, chave, valor, created_at, updated_at, cor_principal) FROM stdin;
12	cor_secundaria	#6c757d	2025-04-29 19:15:31.577241	2025-04-29 19:15:31.577241	#0d6efd
13	cor_aprovado	#198754	2025-04-29 19:15:31.577241	2025-04-29 19:15:31.577241	#0d6efd
14	cor_pendente	#ffc107	2025-04-29 19:15:31.577241	2025-04-29 19:15:31.577241	#0d6efd
15	cor_rejeitado	#dc3545	2025-04-29 19:15:31.577241	2025-04-29 19:15:31.577241	#0d6efd
16	cor_primaria	#0d6efd	2025-04-29 20:00:17.935304	2025-04-29 20:00:17.935304	#0d6efd
7	estoque_alerta_minimo	50	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
1	empresa_nome	Grupo Sandro Calhas LTDA	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
2	empresa_telefone	(19) 99262-0970	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
3	empresa_email	contato@sandrocalhas.com.br	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
4	empresa_endereco	Rua Carlos Pulici, 387, Vila Franco, Descalvado - SP	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
5	empresa_cnpj	48.998.641/0001-03	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
6	taxa_padrao_mao_obra	100	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
9	desconto_pagamento_vista	10	2025-04-29 15:47:38.438476	2025-04-29 15:47:38.438476	#0d6efd
10	max_parcelas	12	2025-04-29 15:47:38.438476	2025-04-29 15:47:38.438476	#0d6efd
8	dias_validade_orcamento	30	2025-04-29 13:03:15.615062	2025-04-29 13:03:15.615062	#0d6efd
11	cor_principal	#0d6efd	2025-04-29 19:15:31.577241	2025-04-29 19:15:31.577241	#0d6efd
\.


--
-- Data for Name: contas_pagar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.contas_pagar (id, descricao, fornecedor, data_emissao, data_vencimento, valor, status, observacoes, documento, categoria, data_pagamento, valor_pago, forma_pagamento, usuario_id, created_at, updated_at, recorrente, intervalo_dias, proxima_data, conta_pai_id) FROM stdin;
\.


--
-- Data for Name: estoque_movimentacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) FROM stdin;
1	1	saida	50.00	8.12	406.00	Saída automática da venda #V2025040001	\N	2025-04-30 04:04:25.115919	\N
2	1	saida	50.00	8.12	406.00	Saída automática do orçamento #2025/04/0001	1	2025-04-30 04:15:44.888094	\N
\.


--
-- Data for Name: estornos_pagamentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.estornos_pagamentos (id, conta_id, data_estorno, valor, observacoes, usuario_id, created_at) FROM stdin;
\.


--
-- Data for Name: orcamento_itens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) FROM stdin;
1	1	1	Corte 10	metro	50.00	8.12	406.00
\.


--
-- Data for Name: orcamentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) FROM stdin;
1	2025/04/0001	1	2025-04-30 01:15:38	2025-05-30	aprovado	100.00	406.00	406.00	812.00		0c958bfd64cfe2e201ddfe546a7bfc90	1	prazo	pago_total	finalizado	2025-04-30	2025-04-30
\.


--
-- Data for Name: pagamentos_contas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pagamentos_contas (id, conta_id, data_pagamento, valor, forma_pagamento, observacoes, caixa_id, usuario_id, created_at) FROM stdin;
\.


--
-- Data for Name: permissoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) FROM stdin;
1	1	t	t	t	t	t	t	t	t	t	t	2025-04-29 22:35:17.976187
2	3	f	f	t	f	t	t	t	t	f	f	2025-04-29 22:51:00.378936
3	4	f	t	t	f	t	t	t	t	t	f	2025-04-29 22:51:18.493191
\.


--
-- Data for Name: produtos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) FROM stdin;
2	C12	Corte 12	1	metro	8.50	45.16	50.00		2025-04-29 20:08:26.091834	4.50
3	B1	Parafuso Brocante Panela Phillips	2	unidade	0.20	9945.00	500.00		2025-04-29 20:18:27.357121	0.10
1	C10	Corte 10	1	metro	8.12	378.55	50.00		2025-04-29 20:04:55.577645	4.08
\.


--
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) FROM stdin;
1	Administrador	admin@admin.com	$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK	admin	t	2025-04-29 01:02:32.26967
3	Atendente	atendente@atendente.com	$2y$10$WjRfcwHvPfbWWpyDM/KZ9.kRrDOdrRDcmMrw2iudnAlhli5zI6V8S	usuario	t	2025-04-29 22:50:41.853126
4	Financeiro	financeiro@financeiro.com	$2y$10$HOJC7Sv2LZkcum2AXv0vsOc4wjInJ6hYXW1iU25KoMlXPBQx2bidi	usuario	t	2025-04-29 22:50:41.853126
\.


--
-- Data for Name: vendas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) FROM stdin;
1	V2025040001	2025-04-30	1	406.00	dinheiro	finalizada	1		pago_total	2025-04-30	0.00
\.


--
-- Data for Name: vendas_itens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) FROM stdin;
1	1	1	Corte 10	metro	50.00	8.12	406.00
\.


--
-- Name: caixa_controle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.caixa_controle_id_seq', 1, false);


--
-- Name: caixa_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.caixa_id_seq', 4, true);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_id_seq', 2, true);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_seq', 2, true);


--
-- Name: configuracoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.configuracoes_id_seq', 16, true);


--
-- Name: contas_pagar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.contas_pagar_id_seq', 1, false);


--
-- Name: estoque_movimentacoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.estoque_movimentacoes_id_seq', 2, true);


--
-- Name: estornos_pagamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.estornos_pagamentos_id_seq', 1, false);


--
-- Name: orcamento_itens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.orcamento_itens_id_seq', 1, true);


--
-- Name: orcamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.orcamentos_id_seq', 1, true);


--
-- Name: pagamentos_contas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pagamentos_contas_id_seq', 1, false);


--
-- Name: permissoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissoes_id_seq', 3, true);


--
-- Name: produtos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.produtos_id_seq', 4, true);


--
-- Name: usuarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.usuarios_id_seq', 4, true);


--
-- Name: vendas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.vendas_id_seq', 1, true);


--
-- Name: vendas_itens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.vendas_itens_id_seq', 1, true);


--
-- Name: caixa_controle caixa_controle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_controle
    ADD CONSTRAINT caixa_controle_pkey PRIMARY KEY (id);


--
-- Name: caixa caixa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT caixa_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- Name: configuracoes configuracoes_chave_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_chave_key UNIQUE (chave);


--
-- Name: configuracoes configuracoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_pkey PRIMARY KEY (id);


--
-- Name: contas_pagar contas_pagar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_pkey PRIMARY KEY (id);


--
-- Name: estoque_movimentacoes estoque_movimentacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estoque_movimentacoes
    ADD CONSTRAINT estoque_movimentacoes_pkey PRIMARY KEY (id);


--
-- Name: estornos_pagamentos estornos_pagamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estornos_pagamentos
    ADD CONSTRAINT estornos_pagamentos_pkey PRIMARY KEY (id);


--
-- Name: orcamento_itens orcamento_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamento_itens
    ADD CONSTRAINT orcamento_itens_pkey PRIMARY KEY (id);


--
-- Name: orcamentos orcamentos_codigo_acesso_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_codigo_acesso_key UNIQUE (codigo_acesso);


--
-- Name: orcamentos orcamentos_numero_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_numero_key UNIQUE (numero);


--
-- Name: orcamentos orcamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_pkey PRIMARY KEY (id);


--
-- Name: pagamentos_contas pagamentos_contas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_pkey PRIMARY KEY (id);


--
-- Name: permissoes permissoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT permissoes_pkey PRIMARY KEY (id);


--
-- Name: permissoes permissoes_usuario_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT permissoes_usuario_id_key UNIQUE (usuario_id);


--
-- Name: produtos produtos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_key UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: vendas_itens vendas_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas_itens
    ADD CONSTRAINT vendas_itens_pkey PRIMARY KEY (id);


--
-- Name: vendas vendas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas
    ADD CONSTRAINT vendas_pkey PRIMARY KEY (id);


--
-- Name: caixa caixa_conta_pagar_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT caixa_conta_pagar_id_fkey FOREIGN KEY (conta_pagar_id) REFERENCES public.contas_pagar(id);


--
-- Name: caixa_controle caixa_controle_usuario_abertura_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_controle
    ADD CONSTRAINT caixa_controle_usuario_abertura_id_fkey FOREIGN KEY (usuario_abertura_id) REFERENCES public.usuarios(id);


--
-- Name: caixa_controle caixa_controle_usuario_fechamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_controle
    ADD CONSTRAINT caixa_controle_usuario_fechamento_id_fkey FOREIGN KEY (usuario_fechamento_id) REFERENCES public.usuarios(id);


--
-- Name: caixa caixa_venda_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT caixa_venda_id_fkey FOREIGN KEY (venda_id) REFERENCES public.vendas(id) ON DELETE SET NULL;


--
-- Name: contas_pagar contas_pagar_conta_pai_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_conta_pai_id_fkey FOREIGN KEY (conta_pai_id) REFERENCES public.contas_pagar(id);


--
-- Name: contas_pagar contas_pagar_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: estoque_movimentacoes estoque_movimentacoes_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estoque_movimentacoes
    ADD CONSTRAINT estoque_movimentacoes_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id);


--
-- Name: estoque_movimentacoes estoque_movimentacoes_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estoque_movimentacoes
    ADD CONSTRAINT estoque_movimentacoes_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: estornos_pagamentos estornos_pagamentos_conta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estornos_pagamentos
    ADD CONSTRAINT estornos_pagamentos_conta_id_fkey FOREIGN KEY (conta_id) REFERENCES public.contas_pagar(id);


--
-- Name: estornos_pagamentos estornos_pagamentos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estornos_pagamentos
    ADD CONSTRAINT estornos_pagamentos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: caixa fk_caixa_cliente; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_cliente FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: caixa fk_caixa_estorno; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_estorno FOREIGN KEY (estorno_id) REFERENCES public.estornos_pagamentos(id);


--
-- Name: caixa fk_caixa_orcamento; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_orcamento FOREIGN KEY (orcamento_id) REFERENCES public.orcamentos(id) ON DELETE SET NULL;


--
-- Name: caixa fk_caixa_usuario; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: vendas fk_vendas_cliente; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas
    ADD CONSTRAINT fk_vendas_cliente FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: vendas_itens fk_vendas_itens_produto; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas_itens
    ADD CONSTRAINT fk_vendas_itens_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE RESTRICT;


--
-- Name: vendas_itens fk_vendas_itens_venda; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas_itens
    ADD CONSTRAINT fk_vendas_itens_venda FOREIGN KEY (venda_id) REFERENCES public.vendas(id) ON DELETE CASCADE;


--
-- Name: vendas fk_vendas_usuario; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas
    ADD CONSTRAINT fk_vendas_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: orcamento_itens orcamento_itens_orcamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamento_itens
    ADD CONSTRAINT orcamento_itens_orcamento_id_fkey FOREIGN KEY (orcamento_id) REFERENCES public.orcamentos(id) ON DELETE CASCADE;


--
-- Name: orcamento_itens orcamento_itens_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamento_itens
    ADD CONSTRAINT orcamento_itens_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id);


--
-- Name: orcamentos orcamentos_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: orcamentos orcamentos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: pagamentos_contas pagamentos_contas_caixa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_caixa_id_fkey FOREIGN KEY (caixa_id) REFERENCES public.caixa(id);


--
-- Name: pagamentos_contas pagamentos_contas_conta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_conta_id_fkey FOREIGN KEY (conta_id) REFERENCES public.contas_pagar(id) ON DELETE CASCADE;


--
-- Name: pagamentos_contas pagamentos_contas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: permissoes permissoes_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT permissoes_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: produtos produtos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id);


--
-- PostgreSQL database dump complete
--


-- Correções adicionais para garantir compatibilidade
ALTER TABLE permissoes
  ALTER COLUMN gerenciar_usuarios SET DEFAULT false,
  ALTER COLUMN visualizar_relatorios_financeiros SET DEFAULT false,
  ALTER COLUMN gerenciar_estoque SET DEFAULT false,
  ALTER COLUMN gerenciar_produtos SET DEFAULT false,
  ALTER COLUMN gerenciar_orcamentos SET DEFAULT false,
  ALTER COLUMN gerenciar_vendas SET DEFAULT false,
  ALTER COLUMN gerenciar_clientes SET DEFAULT false,
  ALTER COLUMN gerenciar_caixa SET DEFAULT false,
  ALTER COLUMN gerenciar_contas SET DEFAULT false,
  ALTER COLUMN visualizar_relatorios_gerais SET DEFAULT false;

-- Corrigir dados do usuário admin
UPDATE usuarios SET senha = '$2y$10$eNcCAKmoIUIx9vo3aXPVmeXtzy4PLzL5tvNi8bWAVFqhQi0KHW4oy' WHERE email = 'admin@admin.com';
