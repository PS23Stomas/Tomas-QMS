-- ==========================================================================
--  PATCH: suderina Supabase schema su gyvaja Neon DB
--  Paleisti Supabase SQL Editor PRIES duomenu migracija.
--  Idempotentiska - saugu paleisti kelis kartus.
-- ==========================================================================

-- 1) Trukstamas stulpelis funkciniai_bandymai (naudoja pareto.php)
ALTER TABLE public.funkciniai_bandymai
    ADD COLUMN IF NOT EXISTS defekto_sunkumas character varying(20) DEFAULT ''::character varying;

-- 2) gaminiu_pdf_failai
CREATE SEQUENCE IF NOT EXISTS public.gaminiu_pdf_failai_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE TABLE IF NOT EXISTS public.gaminiu_pdf_failai (
    id integer DEFAULT nextval('public.gaminiu_pdf_failai_id_seq'::regclass) NOT NULL,
    gaminio_id integer NOT NULL,
    pdf_tipas character varying(20) NOT NULL,
    failas_vardas character varying(500) NOT NULL,
    turinys bytea NOT NULL,
    ikelta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    vartotojas_id integer,
    CONSTRAINT gaminiu_pdf_failai_pkey PRIMARY KEY (id)
);
ALTER SEQUENCE public.gaminiu_pdf_failai_id_seq OWNED BY public.gaminiu_pdf_failai.id;
CREATE INDEX IF NOT EXISTS idx_gaminiu_pdf_failai_gid ON public.gaminiu_pdf_failai USING btree (gaminio_id, pdf_tipas);

-- 3) gvx_dokumentai
CREATE SEQUENCE IF NOT EXISTS public.gvx_dokumentai_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE TABLE IF NOT EXISTS public.gvx_dokumentai (
    id integer DEFAULT nextval('public.gvx_dokumentai_id_seq'::regclass) NOT NULL,
    uzsakymo_id integer NOT NULL,
    tipas character varying(50) NOT NULL,
    pavadinimas character varying(500),
    failas character varying(500),
    dydis_b integer,
    turinys_lob bytea,
    sukurta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    sukurejas character varying(200),
    CONSTRAINT gvx_dokumentai_pkey PRIMARY KEY (id)
);
ALTER SEQUENCE public.gvx_dokumentai_id_seq OWNED BY public.gvx_dokumentai.id;
CREATE INDEX IF NOT EXISTS idx_gvx_dokumentai_uzs ON public.gvx_dokumentai USING btree (uzsakymo_id, tipas);

-- 4) sync_log  (gyvojoje DB seka vadinasi sync_log_id_seq1, PK sync_log_pkey1)
CREATE SEQUENCE IF NOT EXISTS public.sync_log_id_seq1 AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE TABLE IF NOT EXISTS public.sync_log (
    id integer DEFAULT nextval('public.sync_log_id_seq1'::regclass) NOT NULL,
    data timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    veiksmas character varying(100) NOT NULL,
    lentele character varying(100),
    uzsakymo_numeris character varying(100),
    irasu_kiekis integer DEFAULT 0,
    statusas character varying(20) DEFAULT 'ok'::character varying,
    klaida text,
    vartotojas character varying(100),
    CONSTRAINT sync_log_pkey1 PRIMARY KEY (id)
);
ALTER SEQUENCE public.sync_log_id_seq1 OWNED BY public.sync_log.id;
