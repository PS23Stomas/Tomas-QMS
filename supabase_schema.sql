-- ==========================================================================
--  Tomas-QMS — Supabase / PostgreSQL schema (structure only, no data)
--  Source: database_schema.sql (pg_dump, PostgreSQL 16.10)
--  Target: Supabase SQL Editor
--
--  Notes:
--   * Run this on a fresh project, BEFORE importing data.
--   * Owner/role lines and psql backslash meta-commands were removed.
--   * Orphan sequences (no longer referenced by any table) were dropped.
--   * Legacy "mt_"-prefixed sequence names are kept on purpose — the PHP app
--     and the dumped column defaults reference them.
--   * After loading data, run the sequence-resync block at the bottom.
-- ==========================================================================

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET search_path = public;

-- pgcrypto is not strictly required (defaults use core md5/random), kept for parity.
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA extensions;


-- ==========================================================================
--  SEQUENCES (only those referenced by a table)
-- ==========================================================================

CREATE SEQUENCE public.aktyvus_vartotojai_id_seq        AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.bandymai_prietaisai_id_seq1       AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.mt_dielektriniai_bandymai_id_seq  AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.mt_funkciniai_bandymai_id_seq     AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.gaminiai_id_seq                   AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.gaminio_tipai_id_seq              AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.gaminiu_rusys_id_seq              AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.imones_nustatymai_id_seq          AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.mt_izeminimo_tikrinimas_id_seq    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.mt_funkciniu_sablonas_id_seq      AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.mt_komponentai_id_seq             AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.mt_paso_teksto_korekcijos_id_seq  AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.mt_saugikliu_ideklai_id_seq       AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.objektai_id_seq                   AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.pretenzijos_id_seq                AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.pretenzijos_email_history_id_seq  AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.pretenzijos_failai_id_seq         AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.pretenzijos_nuotraukos_id_seq     AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.prietaisai_id_seq                 AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.remember_tokens_id_seq            AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.uzsakovai_id_seq                  AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.uzsakymai_id_seq                  AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE SEQUENCE public.vartotojai_id_seq                 AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;


-- ==========================================================================
--  TABLES
-- ==========================================================================

CREATE TABLE public.aktyvus_vartotojai (
    id integer DEFAULT nextval('public.aktyvus_vartotojai_id_seq'::regclass) NOT NULL,
    vartotojas_id integer NOT NULL,
    session_id character varying(255) NOT NULL,
    vardas character varying(100),
    pavarde character varying(100),
    prisijungimo_laikas timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    paskutine_veikla timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ip_adresas character varying(50),
    "naršykle" character varying(255)
);

CREATE TABLE public.bandymai_prietaisai (
    id integer DEFAULT nextval('public.bandymai_prietaisai_id_seq1'::regclass) NOT NULL,
    gaminys_id integer NOT NULL,
    prietaiso_tipas character varying(255),
    prietaiso_nr character varying(255),
    patikra_data date,
    galioja_iki date,
    sertifikato_nr character varying(255)
);

CREATE TABLE public.dielektriniai_bandymai (
    id integer DEFAULT nextval('public.mt_dielektriniai_bandymai_id_seq'::regclass) NOT NULL,
    gaminys_id integer,
    eiles_nr integer,
    aprasymas text,
    itampa character varying(50),
    schema1 character varying(100),
    schema2 character varying(100),
    schema3 character varying(100),
    schema4 character varying(100),
    schema5 character varying(100),
    schema6 character varying(100),
    isvada text,
    tipas character varying(20) DEFAULT 'mazos_itampos'::character varying,
    grandines_pavadinimas text,
    grandines_itampa character varying(50),
    bandymo_schema character varying(255),
    bandymo_itampa_kv character varying(50),
    bandymo_trukme character varying(50)
);

CREATE TABLE public.funkciniai_bandymai (
    id integer DEFAULT nextval('public.mt_funkciniai_bandymai_id_seq'::regclass) NOT NULL,
    gaminio_id integer,
    eil_nr integer,
    reikalavimas text,
    isvada text NOT NULL,
    defektas text,
    darba_atliko character varying(255),
    irase_vartotojas character varying(100),
    defekto_nuotrauka bytea,
    defekto_nuotraukos_pavadinimas character varying(255),
    pataisyta text DEFAULT ''::text,
    issiusta_kam text DEFAULT ''::text
);

CREATE TABLE public.funkciniu_sablonas (
    id integer DEFAULT nextval('public.mt_funkciniu_sablonas_id_seq'::regclass) NOT NULL,
    eil_nr integer NOT NULL,
    pavadinimas text NOT NULL,
    gaminiu_rusis_id integer DEFAULT 2
);

CREATE TABLE public.gaminiai (
    id integer DEFAULT nextval('public.gaminiai_id_seq'::regclass) NOT NULL,
    uzsakymo_id integer,
    gaminio_numeris character varying(50),
    gaminio_tipas_id integer,
    protokolo_nr character varying(100),
    atitikmuo_kodas character varying(20),
    mt_paso_pdf bytea,
    mt_paso_failas character varying(255),
    mt_dielektriniu_pdf bytea,
    mt_dielektriniu_failas character varying(255),
    mt_funkciniu_pdf bytea,
    mt_funkciniu_failas character varying(255),
    pavadinimas text,
    dielektriniai_issaugoti boolean DEFAULT false
);

CREATE TABLE public.gaminio_tipai (
    id integer DEFAULT nextval('public.gaminio_tipai_id_seq'::regclass) NOT NULL,
    gaminio_tipas text,
    grupe text NOT NULL,
    atitikmuo_kodas text
);

CREATE TABLE public.gaminiu_rusys (
    id integer DEFAULT nextval('public.gaminiu_rusys_id_seq'::regclass) NOT NULL,
    pavadinimas text NOT NULL
);

CREATE TABLE public.imones_nustatymai (
    id integer DEFAULT nextval('public.imones_nustatymai_id_seq'::regclass) NOT NULL,
    pavadinimas character varying(255) DEFAULT 'UAB "ELGA"'::character varying,
    adresas text DEFAULT 'Pramonės g. 12, LT-78150 Šiauliai, Lietuva'::text,
    telefonas character varying(100) DEFAULT '+370 41 594710'::character varying,
    faksas character varying(100) DEFAULT '+370 41 594725'::character varying,
    el_pastas character varying(255) DEFAULT 'info@elga.lt'::character varying,
    internetas character varying(255) DEFAULT 'www.elga.lt'::character varying,
    logotipas bytea,
    logotipo_tipas character varying(50)
);

CREATE TABLE public.izeminimo_tikrinimas (
    id integer DEFAULT nextval('public.mt_izeminimo_tikrinimas_id_seq'::regclass) NOT NULL,
    gaminys_id integer NOT NULL,
    eil_nr character varying(10),
    tasko_pavadinimas character varying(255),
    matavimo_tasku_skaicius integer,
    varza_ohm numeric(5,3),
    budas character varying(50),
    bukle character varying(50)
);

CREATE TABLE public.komponentai (
    id integer DEFAULT nextval('public.mt_komponentai_id_seq'::regclass) NOT NULL,
    eiles_numeris integer NOT NULL,
    gamintojo_kodas text,
    kiekis integer NOT NULL,
    aprasymas text,
    gamintojas text,
    gaminio_id integer,
    parinkta_projektui smallint DEFAULT 0
);

CREATE TABLE public.objektai (
    id integer DEFAULT nextval('public.objektai_id_seq'::regclass) NOT NULL,
    pavadinimas text NOT NULL
);

CREATE TABLE public.paso_teksto_korekcijos (
    id integer DEFAULT nextval('public.mt_paso_teksto_korekcijos_id_seq'::regclass) NOT NULL,
    gaminio_id integer NOT NULL,
    field_key character varying(100) NOT NULL,
    lang character varying(5) DEFAULT 'lt'::character varying NOT NULL,
    tekstas text,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.pretenzijos (
    id integer DEFAULT nextval('public.pretenzijos_id_seq'::regclass) NOT NULL,
    uzsakymo_id integer,
    gaminio_id integer,
    pretenzijos_nr character varying(100),
    data date DEFAULT CURRENT_DATE,
    tipas character varying(100),
    aprasymas text,
    statusas character varying(50) DEFAULT 'nauja'::character varying,
    prioritetas character varying(50) DEFAULT 'vidutinis'::character varying,
    atsakingas_asmuo character varying(255),
    sprendimas text,
    uzdaryta_data date,
    sukure_id integer,
    sukurta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    atnaujinta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    aptikimo_vieta character varying(255),
    gaminys_info character varying(255),
    atsakingas_padalinys character varying(255),
    siulomas_sprendimas text,
    uzfiksavo_padalinys character varying(255),
    uzfiksavo_asmuo character varying(255),
    priezastis text,
    veiksmai text,
    terminas date,
    gavimo_data date,
    uzbaigimo_data date,
    sukure_vardas character varying(255),
    uzsakymo_numeris_ranka character varying(255),
    defekto_pdf_pavadinimas character varying(255),
    defekto_pdf_turinys bytea,
    qt_pretenzija_id integer,
    perziuros_token character varying(64) DEFAULT md5(((random())::text || (clock_timestamp())::text))
);

CREATE TABLE public.pretenzijos_email_history (
    id integer DEFAULT nextval('public.pretenzijos_email_history_id_seq'::regclass) NOT NULL,
    pretenzija_id integer NOT NULL,
    email_delegated_to character varying(255),
    email_cc text,
    email_subject character varying(500),
    sent_by character varying(255),
    sent_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    feedback_text text,
    feedback_at timestamp without time zone,
    feedback_by character varying(255),
    papildomas_komentaras text,
    outgoing_text text,
    parent_history_id integer
);

CREATE TABLE public.pretenzijos_failai (
    id integer DEFAULT nextval('public.pretenzijos_failai_id_seq'::regclass) NOT NULL,
    pretenzija_id integer NOT NULL,
    pavadinimas character varying(500) NOT NULL,
    tipas character varying(255),
    turinys bytea NOT NULL,
    ikelta timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.pretenzijos_nuotraukos (
    id integer DEFAULT nextval('public.pretenzijos_nuotraukos_id_seq'::regclass) NOT NULL,
    pretenzija_id integer NOT NULL,
    pavadinimas character varying(255),
    tipas character varying(100),
    turinys bytea,
    sukurta timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.prietaisai (
    id integer DEFAULT nextval('public.prietaisai_id_seq'::regclass) NOT NULL,
    vidinis_kodas character varying(50) NOT NULL,
    pavadinimas character varying(200) NOT NULL,
    gamintojas character varying(150),
    modelis character varying(100),
    serijos_nr character varying(100),
    matavimo_tipas character varying(150),
    matavimo_ribos text,
    tikslumo_klase character varying(50),
    busena character varying(50) DEFAULT 'naudojamas'::character varying,
    vieta character varying(150),
    atsakingas_asmuo character varying(150),
    kalibracijos_sertifikato_nr character varying(100),
    kalibravimo_istaiga character varying(200),
    kalibravimo_data date,
    galiojimo_pabaiga date,
    kita_kalibracija date,
    standartas_metodika text,
    sertifikato_pdf bytea,
    sertifikato_failas character varying(255),
    pastabos text,
    sukurta timestamp without time zone DEFAULT now(),
    atnaujinta timestamp without time zone DEFAULT now()
);

CREATE TABLE public.remember_tokens (
    id integer DEFAULT nextval('public.remember_tokens_id_seq'::regclass) NOT NULL,
    vartotojas_id integer NOT NULL,
    token character varying(255) NOT NULL,
    expires_at timestamp without time zone NOT NULL
);

CREATE TABLE public.saugikliu_ideklai (
    id integer DEFAULT nextval('public.mt_saugikliu_ideklai_id_seq'::regclass) NOT NULL,
    gaminio_id integer,
    sekcija character varying(10),
    pozicija integer,
    gabaritas character varying(20),
    nominalas character varying(10),
    pozicijos_numeris integer
);

CREATE TABLE public.uzsakovai (
    id integer DEFAULT nextval('public.uzsakovai_id_seq'::regclass) NOT NULL,
    uzsakovas text
);

CREATE TABLE public.uzsakymai (
    id integer DEFAULT nextval('public.uzsakymai_id_seq'::regclass) NOT NULL,
    uzsakymo_numeris character varying(50),
    sukurtas text DEFAULT CURRENT_TIMESTAMP NOT NULL,
    kiekis integer,
    uzsakovas_id integer,
    vartotojas_id integer,
    objektas_id integer,
    gaminiu_rusis_id integer DEFAULT 1 NOT NULL,
    imone_pavadinimas character varying(255),
    imone_adresas text,
    imone_telefonas character varying(100),
    imone_faksas character varying(100),
    imone_el_pastas character varying(255),
    imone_internetas character varying(255)
);

CREATE TABLE public.vartotojai (
    id integer DEFAULT nextval('public.vartotojai_id_seq'::regclass) NOT NULL,
    vardas character varying(100) NOT NULL,
    pavarde character varying(100),
    el_pastas character varying(255),
    slaptazodis character varying(255) NOT NULL,
    sukurta text DEFAULT CURRENT_TIMESTAMP NOT NULL,
    role text DEFAULT 'user'::text NOT NULL,
    login_token character varying(255),
    token_galiojimas timestamp without time zone,
    patvirtintas boolean DEFAULT false,
    patvirtino_id integer,
    patvirtinimo_data timestamp without time zone,
    parasas bytea,
    parasas_tipas character varying(50),
    pareigos character varying(100) DEFAULT ''::character varying
);


-- ==========================================================================
--  SEQUENCE OWNERSHIP (tie sequence lifecycle to the owning column)
-- ==========================================================================

ALTER SEQUENCE public.aktyvus_vartotojai_id_seq       OWNED BY public.aktyvus_vartotojai.id;
ALTER SEQUENCE public.bandymai_prietaisai_id_seq1      OWNED BY public.bandymai_prietaisai.id;
ALTER SEQUENCE public.gaminio_tipai_id_seq             OWNED BY public.gaminio_tipai.id;
ALTER SEQUENCE public.gaminiu_rusys_id_seq             OWNED BY public.gaminiu_rusys.id;
ALTER SEQUENCE public.imones_nustatymai_id_seq         OWNED BY public.imones_nustatymai.id;
ALTER SEQUENCE public.mt_funkciniu_sablonas_id_seq     OWNED BY public.funkciniu_sablonas.id;
ALTER SEQUENCE public.mt_komponentai_id_seq            OWNED BY public.komponentai.id;
ALTER SEQUENCE public.mt_paso_teksto_korekcijos_id_seq OWNED BY public.paso_teksto_korekcijos.id;
ALTER SEQUENCE public.objektai_id_seq                  OWNED BY public.objektai.id;
ALTER SEQUENCE public.pretenzijos_id_seq               OWNED BY public.pretenzijos.id;
ALTER SEQUENCE public.pretenzijos_email_history_id_seq OWNED BY public.pretenzijos_email_history.id;
ALTER SEQUENCE public.pretenzijos_failai_id_seq        OWNED BY public.pretenzijos_failai.id;
ALTER SEQUENCE public.pretenzijos_nuotraukos_id_seq    OWNED BY public.pretenzijos_nuotraukos.id;
ALTER SEQUENCE public.prietaisai_id_seq                OWNED BY public.prietaisai.id;
ALTER SEQUENCE public.remember_tokens_id_seq           OWNED BY public.remember_tokens.id;
ALTER SEQUENCE public.uzsakovai_id_seq                 OWNED BY public.uzsakovai.id;


-- ==========================================================================
--  PRIMARY KEYS / UNIQUE CONSTRAINTS
-- ==========================================================================

ALTER TABLE ONLY public.aktyvus_vartotojai      ADD CONSTRAINT aktyvus_vartotojai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.aktyvus_vartotojai      ADD CONSTRAINT aktyvus_vartotojai_session_id_key UNIQUE (session_id);
ALTER TABLE ONLY public.bandymai_prietaisai     ADD CONSTRAINT bandymai_prietaisai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.dielektriniai_bandymai  ADD CONSTRAINT dielektriniai_bandymai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.funkciniai_bandymai     ADD CONSTRAINT funkciniai_bandymai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.funkciniu_sablonas      ADD CONSTRAINT mt_funkciniu_sablonas_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.gaminiai                ADD CONSTRAINT gaminiai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.gaminio_tipai           ADD CONSTRAINT gaminio_tipai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.gaminiu_rusys           ADD CONSTRAINT gaminiu_rusys_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.imones_nustatymai       ADD CONSTRAINT imones_nustatymai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.izeminimo_tikrinimas    ADD CONSTRAINT izeminimo_tikrinimas_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.komponentai             ADD CONSTRAINT mt_komponentai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.objektai                ADD CONSTRAINT objektai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.paso_teksto_korekcijos  ADD CONSTRAINT mt_paso_teksto_korekcijos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.paso_teksto_korekcijos  ADD CONSTRAINT mt_paso_teksto_korekcijos_gaminio_id_field_key_lang_key UNIQUE (gaminio_id, field_key, lang);
ALTER TABLE ONLY public.pretenzijos             ADD CONSTRAINT pretenzijos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.pretenzijos_email_history ADD CONSTRAINT pretenzijos_email_history_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.pretenzijos_failai      ADD CONSTRAINT pretenzijos_failai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.pretenzijos_nuotraukos  ADD CONSTRAINT pretenzijos_nuotraukos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.prietaisai              ADD CONSTRAINT prietaisai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.remember_tokens         ADD CONSTRAINT remember_tokens_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.saugikliu_ideklai       ADD CONSTRAINT saugikliu_ideklai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.saugikliu_ideklai       ADD CONSTRAINT mt_saugikliu_ideklai_unique UNIQUE (gaminio_id, sekcija, pozicijos_numeris);
ALTER TABLE ONLY public.uzsakovai               ADD CONSTRAINT uzsakovai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.uzsakymai               ADD CONSTRAINT uzsakymai_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.vartotojai              ADD CONSTRAINT vartotojai_pkey PRIMARY KEY (id);


-- ==========================================================================
--  INDEXES
-- ==========================================================================

CREATE INDEX idx_pretenzijos_failai_pid ON public.pretenzijos_failai USING btree (pretenzija_id);
CREATE UNIQUE INDEX idx_pretenzijos_qt_id ON public.pretenzijos USING btree (qt_pretenzija_id) WHERE (qt_pretenzija_id IS NOT NULL);
CREATE UNIQUE INDEX pretenzijos_perziuros_token_idx ON public.pretenzijos USING btree (perziuros_token);


-- ==========================================================================
--  FOREIGN KEYS
-- ==========================================================================

ALTER TABLE ONLY public.pretenzijos_email_history
    ADD CONSTRAINT pretenzijos_email_history_parent_history_id_fkey
    FOREIGN KEY (parent_history_id) REFERENCES public.pretenzijos_email_history(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.pretenzijos_email_history
    ADD CONSTRAINT pretenzijos_email_history_pretenzija_id_fkey
    FOREIGN KEY (pretenzija_id) REFERENCES public.pretenzijos(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pretenzijos_failai
    ADD CONSTRAINT pretenzijos_failai_pretenzija_id_fkey
    FOREIGN KEY (pretenzija_id) REFERENCES public.pretenzijos(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.pretenzijos_nuotraukos
    ADD CONSTRAINT pretenzijos_nuotraukos_pretenzija_id_fkey
    FOREIGN KEY (pretenzija_id) REFERENCES public.pretenzijos(id) ON DELETE CASCADE;


-- ==========================================================================
--  AFTER DATA IMPORT — run this to resync sequences to MAX(id).
--  (Safe to run anytime; harmless on empty tables.)
-- ==========================================================================
-- SELECT setval('public.aktyvus_vartotojai_id_seq',       COALESCE((SELECT MAX(id) FROM public.aktyvus_vartotojai), 0) + 1, false);
-- SELECT setval('public.bandymai_prietaisai_id_seq1',      COALESCE((SELECT MAX(id) FROM public.bandymai_prietaisai), 0) + 1, false);
-- SELECT setval('public.mt_dielektriniai_bandymai_id_seq', COALESCE((SELECT MAX(id) FROM public.dielektriniai_bandymai), 0) + 1, false);
-- SELECT setval('public.mt_funkciniai_bandymai_id_seq',    COALESCE((SELECT MAX(id) FROM public.funkciniai_bandymai), 0) + 1, false);
-- SELECT setval('public.gaminiai_id_seq',                  COALESCE((SELECT MAX(id) FROM public.gaminiai), 0) + 1, false);
-- SELECT setval('public.gaminio_tipai_id_seq',             COALESCE((SELECT MAX(id) FROM public.gaminio_tipai), 0) + 1, false);
-- SELECT setval('public.gaminiu_rusys_id_seq',             COALESCE((SELECT MAX(id) FROM public.gaminiu_rusys), 0) + 1, false);
-- SELECT setval('public.imones_nustatymai_id_seq',         COALESCE((SELECT MAX(id) FROM public.imones_nustatymai), 0) + 1, false);
-- SELECT setval('public.mt_izeminimo_tikrinimas_id_seq',   COALESCE((SELECT MAX(id) FROM public.izeminimo_tikrinimas), 0) + 1, false);
-- SELECT setval('public.mt_funkciniu_sablonas_id_seq',     COALESCE((SELECT MAX(id) FROM public.funkciniu_sablonas), 0) + 1, false);
-- SELECT setval('public.mt_komponentai_id_seq',            COALESCE((SELECT MAX(id) FROM public.komponentai), 0) + 1, false);
-- SELECT setval('public.mt_paso_teksto_korekcijos_id_seq', COALESCE((SELECT MAX(id) FROM public.paso_teksto_korekcijos), 0) + 1, false);
-- SELECT setval('public.mt_saugikliu_ideklai_id_seq',      COALESCE((SELECT MAX(id) FROM public.saugikliu_ideklai), 0) + 1, false);
-- SELECT setval('public.objektai_id_seq',                  COALESCE((SELECT MAX(id) FROM public.objektai), 0) + 1, false);
-- SELECT setval('public.pretenzijos_id_seq',               COALESCE((SELECT MAX(id) FROM public.pretenzijos), 0) + 1, false);
-- SELECT setval('public.pretenzijos_email_history_id_seq', COALESCE((SELECT MAX(id) FROM public.pretenzijos_email_history), 0) + 1, false);
-- SELECT setval('public.pretenzijos_failai_id_seq',        COALESCE((SELECT MAX(id) FROM public.pretenzijos_failai), 0) + 1, false);
-- SELECT setval('public.pretenzijos_nuotraukos_id_seq',    COALESCE((SELECT MAX(id) FROM public.pretenzijos_nuotraukos), 0) + 1, false);
-- SELECT setval('public.prietaisai_id_seq',                COALESCE((SELECT MAX(id) FROM public.prietaisai), 0) + 1, false);
-- SELECT setval('public.remember_tokens_id_seq',           COALESCE((SELECT MAX(id) FROM public.remember_tokens), 0) + 1, false);
-- SELECT setval('public.uzsakovai_id_seq',                 COALESCE((SELECT MAX(id) FROM public.uzsakovai), 0) + 1, false);
-- SELECT setval('public.uzsakymai_id_seq',                 COALESCE((SELECT MAX(id) FROM public.uzsakymai), 0) + 1, false);
-- SELECT setval('public.vartotojai_id_seq',                COALESCE((SELECT MAX(id) FROM public.vartotojai), 0) + 1, false);


-- ==========================================================================
--  OPTIONAL — Row Level Security (RLS)
--  This app authenticates in PHP and connects with a direct Postgres role,
--  so it does NOT need RLS to function. Supabase's Security Advisor will,
--  however, warn that public tables have RLS disabled. If you want to silence
--  that AND ensure the anon/authenticated API keys can't read these tables,
--  enable RLS with no policies (deny-all to API; your direct connection still
--  works because the postgres role bypasses RLS):
-- ==========================================================================
-- DO $$
-- DECLARE t text;
-- BEGIN
--   FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public'
--   LOOP EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY;', t);
--   END LOOP;
-- END $$;
