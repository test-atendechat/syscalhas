--
-- PostgreSQL database dump
--

-- Dumped from database version 16.8
-- Dumped by pg_dump version 16.5

-- Started on 2025-04-30 04:18:25 UTC

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

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 232 (class 1259 OID 40963)
-- Name: caixa; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.caixa OWNER TO neondb_owner;

--
-- TOC entry 244 (class 1259 OID 122881)
-- Name: caixa_controle; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.caixa_controle OWNER TO neondb_owner;

--
-- TOC entry 243 (class 1259 OID 122880)
-- Name: caixa_controle_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.caixa_controle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.caixa_controle_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3569 (class 0 OID 0)
-- Dependencies: 243
-- Name: caixa_controle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.caixa_controle_id_seq OWNED BY public.caixa_controle.id;


--
-- TOC entry 231 (class 1259 OID 40962)
-- Name: caixa_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.caixa_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.caixa_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3570 (class 0 OID 0)
-- Dependencies: 231
-- Name: caixa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.caixa_id_seq OWNED BY public.caixa.id;


--
-- TOC entry 220 (class 1259 OID 24600)
-- Name: categorias; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.categorias (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    descricao character varying(255)
);


ALTER TABLE public.categorias OWNER TO neondb_owner;

--
-- TOC entry 219 (class 1259 OID 24599)
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.categorias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categorias_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3571 (class 0 OID 0)
-- Dependencies: 219
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- TOC entry 218 (class 1259 OID 24589)
-- Name: clientes; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.clientes OWNER TO neondb_owner;

--
-- TOC entry 217 (class 1259 OID 24588)
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.clientes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.clientes_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3572 (class 0 OID 0)
-- Dependencies: 217
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- TOC entry 230 (class 1259 OID 32769)
-- Name: configuracoes; Type: TABLE; Schema: public; Owner: neondb_owner
--

CREATE TABLE public.configuracoes (
    id integer NOT NULL,
    chave character varying(50) NOT NULL,
    valor text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    cor_principal character varying(20) DEFAULT '#0d6efd'::character varying
);


ALTER TABLE public.configuracoes OWNER TO neondb_owner;

--
-- TOC entry 229 (class 1259 OID 32768)
-- Name: configuracoes_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.configuracoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.configuracoes_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3573 (class 0 OID 0)
-- Dependencies: 229
-- Name: configuracoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.configuracoes_id_seq OWNED BY public.configuracoes.id;


--
-- TOC entry 238 (class 1259 OID 65537)
-- Name: contas_pagar; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.contas_pagar OWNER TO neondb_owner;

--
-- TOC entry 237 (class 1259 OID 65536)
-- Name: contas_pagar_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.contas_pagar_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.contas_pagar_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3574 (class 0 OID 0)
-- Dependencies: 237
-- Name: contas_pagar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.contas_pagar_id_seq OWNED BY public.contas_pagar.id;


--
-- TOC entry 224 (class 1259 OID 24624)
-- Name: estoque_movimentacoes; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.estoque_movimentacoes OWNER TO neondb_owner;

--
-- TOC entry 223 (class 1259 OID 24623)
-- Name: estoque_movimentacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.estoque_movimentacoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.estoque_movimentacoes_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3575 (class 0 OID 0)
-- Dependencies: 223
-- Name: estoque_movimentacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.estoque_movimentacoes_id_seq OWNED BY public.estoque_movimentacoes.id;


--
-- TOC entry 242 (class 1259 OID 90113)
-- Name: estornos_pagamentos; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.estornos_pagamentos OWNER TO neondb_owner;

--
-- TOC entry 241 (class 1259 OID 90112)
-- Name: estornos_pagamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.estornos_pagamentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.estornos_pagamentos_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3576 (class 0 OID 0)
-- Dependencies: 241
-- Name: estornos_pagamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.estornos_pagamentos_id_seq OWNED BY public.estornos_pagamentos.id;


--
-- TOC entry 228 (class 1259 OID 24673)
-- Name: orcamento_itens; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.orcamento_itens OWNER TO neondb_owner;

--
-- TOC entry 227 (class 1259 OID 24672)
-- Name: orcamento_itens_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.orcamento_itens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orcamento_itens_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3577 (class 0 OID 0)
-- Dependencies: 227
-- Name: orcamento_itens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.orcamento_itens_id_seq OWNED BY public.orcamento_itens.id;


--
-- TOC entry 226 (class 1259 OID 24644)
-- Name: orcamentos; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.orcamentos OWNER TO neondb_owner;

--
-- TOC entry 225 (class 1259 OID 24643)
-- Name: orcamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.orcamentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orcamentos_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3578 (class 0 OID 0)
-- Dependencies: 225
-- Name: orcamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.orcamentos_id_seq OWNED BY public.orcamentos.id;


--
-- TOC entry 240 (class 1259 OID 65553)
-- Name: pagamentos_contas; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.pagamentos_contas OWNER TO neondb_owner;

--
-- TOC entry 239 (class 1259 OID 65552)
-- Name: pagamentos_contas_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.pagamentos_contas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pagamentos_contas_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3579 (class 0 OID 0)
-- Dependencies: 239
-- Name: pagamentos_contas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.pagamentos_contas_id_seq OWNED BY public.pagamentos_contas.id;


--
-- TOC entry 246 (class 1259 OID 131096)
-- Name: permissoes; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.permissoes OWNER TO neondb_owner;

--
-- TOC entry 245 (class 1259 OID 131095)
-- Name: permissoes_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.permissoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissoes_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3580 (class 0 OID 0)
-- Dependencies: 245
-- Name: permissoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.permissoes_id_seq OWNED BY public.permissoes.id;


--
-- TOC entry 222 (class 1259 OID 24607)
-- Name: produtos; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.produtos OWNER TO neondb_owner;

--
-- TOC entry 221 (class 1259 OID 24606)
-- Name: produtos_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.produtos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.produtos_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3581 (class 0 OID 0)
-- Dependencies: 221
-- Name: produtos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.produtos_id_seq OWNED BY public.produtos.id;


--
-- TOC entry 216 (class 1259 OID 24577)
-- Name: usuarios; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.usuarios OWNER TO neondb_owner;

--
-- TOC entry 215 (class 1259 OID 24576)
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.usuarios_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3582 (class 0 OID 0)
-- Dependencies: 215
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- TOC entry 234 (class 1259 OID 40985)
-- Name: vendas; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.vendas OWNER TO neondb_owner;

--
-- TOC entry 233 (class 1259 OID 40984)
-- Name: vendas_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.vendas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vendas_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3583 (class 0 OID 0)
-- Dependencies: 233
-- Name: vendas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.vendas_id_seq OWNED BY public.vendas.id;


--
-- TOC entry 236 (class 1259 OID 41008)
-- Name: vendas_itens; Type: TABLE; Schema: public; Owner: neondb_owner
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


ALTER TABLE public.vendas_itens OWNER TO neondb_owner;

--
-- TOC entry 235 (class 1259 OID 41007)
-- Name: vendas_itens_id_seq; Type: SEQUENCE; Schema: public; Owner: neondb_owner
--

CREATE SEQUENCE public.vendas_itens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vendas_itens_id_seq OWNER TO neondb_owner;

--
-- TOC entry 3584 (class 0 OID 0)
-- Dependencies: 235
-- Name: vendas_itens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: neondb_owner
--

ALTER SEQUENCE public.vendas_itens_id_seq OWNED BY public.vendas_itens.id;


--
-- TOC entry 3284 (class 2604 OID 40966)
-- Name: caixa id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa ALTER COLUMN id SET DEFAULT nextval('public.caixa_id_seq'::regclass);


--
-- TOC entry 3305 (class 2604 OID 122884)
-- Name: caixa_controle id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa_controle ALTER COLUMN id SET DEFAULT nextval('public.caixa_controle_id_seq'::regclass);


--
-- TOC entry 3262 (class 2604 OID 24603)
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- TOC entry 3259 (class 2604 OID 24592)
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- TOC entry 3280 (class 2604 OID 32772)
-- Name: configuracoes id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.configuracoes ALTER COLUMN id SET DEFAULT nextval('public.configuracoes_id_seq'::regclass);


--
-- TOC entry 3296 (class 2604 OID 65540)
-- Name: contas_pagar id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.contas_pagar ALTER COLUMN id SET DEFAULT nextval('public.contas_pagar_id_seq'::regclass);


--
-- TOC entry 3268 (class 2604 OID 24627)
-- Name: estoque_movimentacoes id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estoque_movimentacoes ALTER COLUMN id SET DEFAULT nextval('public.estoque_movimentacoes_id_seq'::regclass);


--
-- TOC entry 3303 (class 2604 OID 90116)
-- Name: estornos_pagamentos id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estornos_pagamentos ALTER COLUMN id SET DEFAULT nextval('public.estornos_pagamentos_id_seq'::regclass);


--
-- TOC entry 3279 (class 2604 OID 24676)
-- Name: orcamento_itens id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamento_itens ALTER COLUMN id SET DEFAULT nextval('public.orcamento_itens_id_seq'::regclass);


--
-- TOC entry 3270 (class 2604 OID 24647)
-- Name: orcamentos id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamentos ALTER COLUMN id SET DEFAULT nextval('public.orcamentos_id_seq'::regclass);


--
-- TOC entry 3301 (class 2604 OID 65556)
-- Name: pagamentos_contas id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.pagamentos_contas ALTER COLUMN id SET DEFAULT nextval('public.pagamentos_contas_id_seq'::regclass);


--
-- TOC entry 3308 (class 2604 OID 131099)
-- Name: permissoes id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.permissoes ALTER COLUMN id SET DEFAULT nextval('public.permissoes_id_seq'::regclass);


--
-- TOC entry 3263 (class 2604 OID 24610)
-- Name: produtos id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.produtos ALTER COLUMN id SET DEFAULT nextval('public.produtos_id_seq'::regclass);


--
-- TOC entry 3255 (class 2604 OID 24580)
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- TOC entry 3288 (class 2604 OID 40988)
-- Name: vendas id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas ALTER COLUMN id SET DEFAULT nextval('public.vendas_id_seq'::regclass);


--
-- TOC entry 3295 (class 2604 OID 41011)
-- Name: vendas_itens id; Type: DEFAULT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas_itens ALTER COLUMN id SET DEFAULT nextval('public.vendas_itens_id_seq'::regclass);


--
-- TOC entry 3549 (class 0 OID 40963)
-- Dependencies: 232
-- Data for Name: caixa; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.caixa (id, data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, usuario_id, observacoes, data_registro, venda_id, cliente_id, conta_pagar_id, estorno_id, caixa_controle_id) FROM stdin;
1	2025-04-30	entrada	Pagamento da venda #V2025040001	200.00	dinheiro	\N	1	Pagamento parcial - Valor pago anteriormente: R$ 0,00, Pagamento atual: R$ 200,00, Valor restante: R$ 40.400,00	2025-04-30 04:13:59.851766	1	\N	\N	\N	\N
2	2025-04-30	entrada	Pagamento da venda #V2025040001	206.00	dinheiro	\N	1	Pagamento parcial - Valor pago anteriormente: R$ 200,00, Pagamento atual: R$ 206,00, Valor restante: R$ 40.194,00	2025-04-30 04:14:52.190417	1	\N	\N	\N	\N
\.


--
-- TOC entry 3561 (class 0 OID 122881)
-- Dependencies: 244
-- Data for Name: caixa_controle; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.caixa_controle (id, data_abertura, hora_abertura, valor_inicial, data_fechamento, hora_fechamento, valor_final, valor_informado, diferenca, observacoes, usuario_abertura_id, usuario_fechamento_id, observacoes_abertura, valor_conferido_dinheiro, valor_conferido_credito, valor_conferido_debito, valor_conferido_pix, valor_conferido_outro, observacoes_fechamento) FROM stdin;
\.


--
-- TOC entry 3537 (class 0 OID 24600)
-- Dependencies: 220
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.categorias (id, nome, descricao) FROM stdin;
1	Cortes	\N
2	Parafusos	\N
\.


--
-- TOC entry 3535 (class 0 OID 24589)
-- Dependencies: 218
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.clientes (id, nome, tipo, cpf_cnpj, email, telefone, endereco, cidade, estado, cep, observacoes, data_cadastro) FROM stdin;
1	Sem Identificação	fisica									2025-04-29 20:01:03.918571
\.


--
-- TOC entry 3547 (class 0 OID 32769)
-- Dependencies: 230
-- Data for Name: configuracoes; Type: TABLE DATA; Schema: public; Owner: neondb_owner
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
-- TOC entry 3555 (class 0 OID 65537)
-- Dependencies: 238
-- Data for Name: contas_pagar; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.contas_pagar (id, descricao, fornecedor, data_emissao, data_vencimento, valor, status, observacoes, documento, categoria, data_pagamento, valor_pago, forma_pagamento, usuario_id, created_at, updated_at, recorrente, intervalo_dias, proxima_data, conta_pai_id) FROM stdin;
\.


--
-- TOC entry 3541 (class 0 OID 24624)
-- Dependencies: 224
-- Data for Name: estoque_movimentacoes; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.estoque_movimentacoes (id, produto_id, tipo, quantidade, valor_unitario, valor_total, observacao, orcamento_id, data_movimentacao, usuario_id) FROM stdin;
1	1	saida	50.00	8.12	406.00	Saída automática da venda #V2025040001	\N	2025-04-30 04:04:25.115919	\N
2	1	saida	50.00	8.12	406.00	Saída automática do orçamento #2025/04/0001	1	2025-04-30 04:15:44.888094	\N
\.


--
-- TOC entry 3559 (class 0 OID 90113)
-- Dependencies: 242
-- Data for Name: estornos_pagamentos; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.estornos_pagamentos (id, conta_id, data_estorno, valor, observacoes, usuario_id, created_at) FROM stdin;
\.


--
-- TOC entry 3545 (class 0 OID 24673)
-- Dependencies: 228
-- Data for Name: orcamento_itens; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.orcamento_itens (id, orcamento_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) FROM stdin;
1	1	1	Corte 10	metro	50.00	8.12	406.00
\.


--
-- TOC entry 3543 (class 0 OID 24644)
-- Dependencies: 226
-- Data for Name: orcamentos; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.orcamentos (id, numero, cliente_id, data_criacao, data_validade, status, taxa_mao_obra, valor_produtos, valor_mao_obra, valor_total, observacoes, codigo_acesso, usuario_id, forma_pagamento, status_pagamento, status_execucao, data_pagamento, data_finalizacao) FROM stdin;
1	2025/04/0001	1	2025-04-30 01:15:38	2025-05-30	aprovado	100.00	406.00	406.00	812.00		0c958bfd64cfe2e201ddfe546a7bfc90	1	prazo	pendente	pendente	\N	\N
\.


--
-- TOC entry 3557 (class 0 OID 65553)
-- Dependencies: 240
-- Data for Name: pagamentos_contas; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.pagamentos_contas (id, conta_id, data_pagamento, valor, forma_pagamento, observacoes, caixa_id, usuario_id, created_at) FROM stdin;
\.


--
-- TOC entry 3563 (class 0 OID 131096)
-- Dependencies: 246
-- Data for Name: permissoes; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.permissoes (id, usuario_id, gerenciar_usuarios, visualizar_relatorios_financeiros, gerenciar_estoque, gerenciar_produtos, gerenciar_orcamentos, gerenciar_vendas, gerenciar_clientes, gerenciar_caixa, gerenciar_contas, visualizar_relatorios_gerais, data_atualizacao) FROM stdin;
1	1	t	t	t	t	t	t	t	t	t	t	2025-04-29 22:35:17.976187
2	3	f	f	t	f	t	t	t	t	f	f	2025-04-29 22:51:00.378936
3	4	f	t	t	f	t	t	t	t	t	f	2025-04-29 22:51:18.493191
\.


--
-- TOC entry 3539 (class 0 OID 24607)
-- Dependencies: 222
-- Data for Name: produtos; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.produtos (id, codigo, descricao, categoria_id, unidade, valor_unitario, estoque_atual, estoque_minimo, observacoes, data_cadastro, custo_unitario) FROM stdin;
2	C12	Corte 12	1	metro	8.50	45.16	50.00		2025-04-29 20:08:26.091834	4.50
3	B1	Parafuso Brocante Panela Phillips	2	unidade	0.20	9945.00	500.00		2025-04-29 20:18:27.357121	0.10
1	C10	Corte 10	1	metro	8.12	378.55	50.00		2025-04-29 20:04:55.577645	4.08
\.


--
-- TOC entry 3533 (class 0 OID 24577)
-- Dependencies: 216
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.usuarios (id, nome, email, senha, nivel, ativo, data_cadastro) FROM stdin;
1	Administrador	admin@admin.com	$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK	admin	t	2025-04-29 01:02:32.26967
4	Financeiro	financeiro@exemplo.com	$2y$10$F7eKrgjmoA5dIMw9kj3v1erbn4Qx004ilz7NisRaNC4D44gXJYiyK	usuario	t	2025-04-29 22:50:41.853126
3	Atendente	atendente@atendente.com	$2y$10$WjRfcwHvPfbWWpyDM/KZ9.kRrDOdrRDcmMrw2iudnAlhli5zI6V8S	usuario	t	2025-04-29 22:50:41.853126
\.


--
-- TOC entry 3551 (class 0 OID 40985)
-- Dependencies: 234
-- Data for Name: vendas; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.vendas (id, numero, data_venda, cliente_id, valor_total, forma_pagamento, status, usuario_id, observacoes, status_pagamento, data_pagamento, valor_desconto) FROM stdin;
1	V2025040001	2025-04-30	1	406.00	dinheiro	finalizada	1		pago_total	2025-04-30	0.00
\.


--
-- TOC entry 3553 (class 0 OID 41008)
-- Dependencies: 236
-- Data for Name: vendas_itens; Type: TABLE DATA; Schema: public; Owner: neondb_owner
--

COPY public.vendas_itens (id, venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total) FROM stdin;
1	1	1	Corte 10	metro	50.00	8.12	406.00
\.


--
-- TOC entry 3585 (class 0 OID 0)
-- Dependencies: 243
-- Name: caixa_controle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.caixa_controle_id_seq', 1, false);


--
-- TOC entry 3586 (class 0 OID 0)
-- Dependencies: 231
-- Name: caixa_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.caixa_id_seq', 2, true);


--
-- TOC entry 3587 (class 0 OID 0)
-- Dependencies: 219
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.categorias_id_seq', 2, true);


--
-- TOC entry 3588 (class 0 OID 0)
-- Dependencies: 217
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.clientes_id_seq', 2, true);


--
-- TOC entry 3589 (class 0 OID 0)
-- Dependencies: 229
-- Name: configuracoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.configuracoes_id_seq', 16, true);


--
-- TOC entry 3590 (class 0 OID 0)
-- Dependencies: 237
-- Name: contas_pagar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.contas_pagar_id_seq', 1, false);


--
-- TOC entry 3591 (class 0 OID 0)
-- Dependencies: 223
-- Name: estoque_movimentacoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.estoque_movimentacoes_id_seq', 2, true);


--
-- TOC entry 3592 (class 0 OID 0)
-- Dependencies: 241
-- Name: estornos_pagamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.estornos_pagamentos_id_seq', 1, false);


--
-- TOC entry 3593 (class 0 OID 0)
-- Dependencies: 227
-- Name: orcamento_itens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.orcamento_itens_id_seq', 1, true);


--
-- TOC entry 3594 (class 0 OID 0)
-- Dependencies: 225
-- Name: orcamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.orcamentos_id_seq', 1, true);


--
-- TOC entry 3595 (class 0 OID 0)
-- Dependencies: 239
-- Name: pagamentos_contas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.pagamentos_contas_id_seq', 1, false);


--
-- TOC entry 3596 (class 0 OID 0)
-- Dependencies: 245
-- Name: permissoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.permissoes_id_seq', 3, true);


--
-- TOC entry 3597 (class 0 OID 0)
-- Dependencies: 221
-- Name: produtos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.produtos_id_seq', 4, true);


--
-- TOC entry 3598 (class 0 OID 0)
-- Dependencies: 215
-- Name: usuarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.usuarios_id_seq', 4, true);


--
-- TOC entry 3599 (class 0 OID 0)
-- Dependencies: 233
-- Name: vendas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.vendas_id_seq', 1, true);


--
-- TOC entry 3600 (class 0 OID 0)
-- Dependencies: 235
-- Name: vendas_itens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: neondb_owner
--

SELECT pg_catalog.setval('public.vendas_itens_id_seq', 1, true);


--
-- TOC entry 3357 (class 2606 OID 122890)
-- Name: caixa_controle caixa_controle_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa_controle
    ADD CONSTRAINT caixa_controle_pkey PRIMARY KEY (id);


--
-- TOC entry 3345 (class 2606 OID 40973)
-- Name: caixa caixa_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT caixa_pkey PRIMARY KEY (id);


--
-- TOC entry 3327 (class 2606 OID 24605)
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- TOC entry 3325 (class 2606 OID 24598)
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- TOC entry 3341 (class 2606 OID 32780)
-- Name: configuracoes configuracoes_chave_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_chave_key UNIQUE (chave);


--
-- TOC entry 3343 (class 2606 OID 32778)
-- Name: configuracoes configuracoes_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_pkey PRIMARY KEY (id);


--
-- TOC entry 3351 (class 2606 OID 65546)
-- Name: contas_pagar contas_pagar_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_pkey PRIMARY KEY (id);


--
-- TOC entry 3331 (class 2606 OID 24632)
-- Name: estoque_movimentacoes estoque_movimentacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estoque_movimentacoes
    ADD CONSTRAINT estoque_movimentacoes_pkey PRIMARY KEY (id);


--
-- TOC entry 3355 (class 2606 OID 90121)
-- Name: estornos_pagamentos estornos_pagamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estornos_pagamentos
    ADD CONSTRAINT estornos_pagamentos_pkey PRIMARY KEY (id);


--
-- TOC entry 3339 (class 2606 OID 24678)
-- Name: orcamento_itens orcamento_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamento_itens
    ADD CONSTRAINT orcamento_itens_pkey PRIMARY KEY (id);


--
-- TOC entry 3333 (class 2606 OID 24661)
-- Name: orcamentos orcamentos_codigo_acesso_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_codigo_acesso_key UNIQUE (codigo_acesso);


--
-- TOC entry 3335 (class 2606 OID 24659)
-- Name: orcamentos orcamentos_numero_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_numero_key UNIQUE (numero);


--
-- TOC entry 3337 (class 2606 OID 24657)
-- Name: orcamentos orcamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_pkey PRIMARY KEY (id);


--
-- TOC entry 3353 (class 2606 OID 65561)
-- Name: pagamentos_contas pagamentos_contas_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_pkey PRIMARY KEY (id);


--
-- TOC entry 3359 (class 2606 OID 131112)
-- Name: permissoes permissoes_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT permissoes_pkey PRIMARY KEY (id);


--
-- TOC entry 3361 (class 2606 OID 131114)
-- Name: permissoes permissoes_usuario_id_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT permissoes_usuario_id_key UNIQUE (usuario_id);


--
-- TOC entry 3329 (class 2606 OID 24617)
-- Name: produtos produtos_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_pkey PRIMARY KEY (id);


--
-- TOC entry 3321 (class 2606 OID 24587)
-- Name: usuarios usuarios_email_key; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_key UNIQUE (email);


--
-- TOC entry 3323 (class 2606 OID 24585)
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- TOC entry 3349 (class 2606 OID 41013)
-- Name: vendas_itens vendas_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas_itens
    ADD CONSTRAINT vendas_itens_pkey PRIMARY KEY (id);


--
-- TOC entry 3347 (class 2606 OID 40996)
-- Name: vendas vendas_pkey; Type: CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas
    ADD CONSTRAINT vendas_pkey PRIMARY KEY (id);


--
-- TOC entry 3369 (class 2606 OID 65577)
-- Name: caixa caixa_conta_pagar_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT caixa_conta_pagar_id_fkey FOREIGN KEY (conta_pagar_id) REFERENCES public.contas_pagar(id);


--
-- TOC entry 3386 (class 2606 OID 122891)
-- Name: caixa_controle caixa_controle_usuario_abertura_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa_controle
    ADD CONSTRAINT caixa_controle_usuario_abertura_id_fkey FOREIGN KEY (usuario_abertura_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3387 (class 2606 OID 122896)
-- Name: caixa_controle caixa_controle_usuario_fechamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa_controle
    ADD CONSTRAINT caixa_controle_usuario_fechamento_id_fkey FOREIGN KEY (usuario_fechamento_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3370 (class 2606 OID 49153)
-- Name: caixa caixa_venda_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT caixa_venda_id_fkey FOREIGN KEY (venda_id) REFERENCES public.vendas(id) ON DELETE SET NULL;


--
-- TOC entry 3379 (class 2606 OID 73730)
-- Name: contas_pagar contas_pagar_conta_pai_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_conta_pai_id_fkey FOREIGN KEY (conta_pai_id) REFERENCES public.contas_pagar(id);


--
-- TOC entry 3380 (class 2606 OID 65547)
-- Name: contas_pagar contas_pagar_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3363 (class 2606 OID 24633)
-- Name: estoque_movimentacoes estoque_movimentacoes_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estoque_movimentacoes
    ADD CONSTRAINT estoque_movimentacoes_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id);


--
-- TOC entry 3364 (class 2606 OID 24638)
-- Name: estoque_movimentacoes estoque_movimentacoes_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estoque_movimentacoes
    ADD CONSTRAINT estoque_movimentacoes_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3384 (class 2606 OID 90122)
-- Name: estornos_pagamentos estornos_pagamentos_conta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estornos_pagamentos
    ADD CONSTRAINT estornos_pagamentos_conta_id_fkey FOREIGN KEY (conta_id) REFERENCES public.contas_pagar(id);


--
-- TOC entry 3385 (class 2606 OID 90127)
-- Name: estornos_pagamentos estornos_pagamentos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.estornos_pagamentos
    ADD CONSTRAINT estornos_pagamentos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3371 (class 2606 OID 57344)
-- Name: caixa fk_caixa_cliente; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_cliente FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- TOC entry 3372 (class 2606 OID 90132)
-- Name: caixa fk_caixa_estorno; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_estorno FOREIGN KEY (estorno_id) REFERENCES public.estornos_pagamentos(id);


--
-- TOC entry 3373 (class 2606 OID 40974)
-- Name: caixa fk_caixa_orcamento; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_orcamento FOREIGN KEY (orcamento_id) REFERENCES public.orcamentos(id) ON DELETE SET NULL;


--
-- TOC entry 3374 (class 2606 OID 40979)
-- Name: caixa fk_caixa_usuario; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.caixa
    ADD CONSTRAINT fk_caixa_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- TOC entry 3375 (class 2606 OID 40997)
-- Name: vendas fk_vendas_cliente; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas
    ADD CONSTRAINT fk_vendas_cliente FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- TOC entry 3377 (class 2606 OID 41019)
-- Name: vendas_itens fk_vendas_itens_produto; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas_itens
    ADD CONSTRAINT fk_vendas_itens_produto FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE RESTRICT;


--
-- TOC entry 3378 (class 2606 OID 41014)
-- Name: vendas_itens fk_vendas_itens_venda; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas_itens
    ADD CONSTRAINT fk_vendas_itens_venda FOREIGN KEY (venda_id) REFERENCES public.vendas(id) ON DELETE CASCADE;


--
-- TOC entry 3376 (class 2606 OID 41002)
-- Name: vendas fk_vendas_usuario; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.vendas
    ADD CONSTRAINT fk_vendas_usuario FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- TOC entry 3367 (class 2606 OID 24679)
-- Name: orcamento_itens orcamento_itens_orcamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamento_itens
    ADD CONSTRAINT orcamento_itens_orcamento_id_fkey FOREIGN KEY (orcamento_id) REFERENCES public.orcamentos(id) ON DELETE CASCADE;


--
-- TOC entry 3368 (class 2606 OID 24684)
-- Name: orcamento_itens orcamento_itens_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamento_itens
    ADD CONSTRAINT orcamento_itens_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id);


--
-- TOC entry 3365 (class 2606 OID 24662)
-- Name: orcamentos orcamentos_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- TOC entry 3366 (class 2606 OID 24667)
-- Name: orcamentos orcamentos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.orcamentos
    ADD CONSTRAINT orcamentos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3381 (class 2606 OID 65567)
-- Name: pagamentos_contas pagamentos_contas_caixa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_caixa_id_fkey FOREIGN KEY (caixa_id) REFERENCES public.caixa(id);


--
-- TOC entry 3382 (class 2606 OID 65562)
-- Name: pagamentos_contas pagamentos_contas_conta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_conta_id_fkey FOREIGN KEY (conta_id) REFERENCES public.contas_pagar(id) ON DELETE CASCADE;


--
-- TOC entry 3383 (class 2606 OID 65572)
-- Name: pagamentos_contas pagamentos_contas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.pagamentos_contas
    ADD CONSTRAINT pagamentos_contas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3388 (class 2606 OID 131115)
-- Name: permissoes permissoes_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.permissoes
    ADD CONSTRAINT permissoes_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- TOC entry 3362 (class 2606 OID 24618)
-- Name: produtos produtos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: neondb_owner
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id);


--
-- TOC entry 2114 (class 826 OID 16392)
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: public; Owner: cloud_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE cloud_admin IN SCHEMA public GRANT ALL ON SEQUENCES TO neon_superuser WITH GRANT OPTION;


--
-- TOC entry 2113 (class 826 OID 16391)
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: cloud_admin
--

ALTER DEFAULT PRIVILEGES FOR ROLE cloud_admin IN SCHEMA public GRANT ALL ON TABLES TO neon_superuser WITH GRANT OPTION;


-- Completed on 2025-04-30 04:18:34 UTC

--
-- PostgreSQL database dump complete
--

