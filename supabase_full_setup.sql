-- ==========================================================================
--  Tomas-QMS — PILNA struktūra (be duomenų) Supabase'ui
--  Paleisti NAUJAME (arba atsigavusiame) projekte vienu kartu.
--  Apima: visas lenteles, FK, GVX, + modulius (gaminiu_rusys) ir 16 vartotojų.
--  NĖRA: užsakymų, gaminių, bandymų, GVX atsakymų/nuotraukų, dokumentų.
-- ==========================================================================

-- ===== 1. BAZINĖ SCHEMA =====
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

-- ===== 2. PATCH (gaminiu_pdf_failai, gvx_dokumentai, sync_log, defekto_sunkumas) =====
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

-- ===== 3. GVX SCHEMA =====
-- ==========================================================================
--  GVX modulio schema (Tomas-QMS / Supabase)
--  Adaptuota iš Quality-Tomas. Idempotentiška (IF NOT EXISTS).
-- ==========================================================================

-- 1) Klausimų šablonas
CREATE SEQUENCE IF NOT EXISTS public.gvx_klausimai_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE TABLE IF NOT EXISTS public.gvx_klausimai (
    id integer DEFAULT nextval('public.gvx_klausimai_id_seq'::regclass) NOT NULL,
    versija integer,
    skyrius character varying(120),
    nr integer,
    objektas character varying(255),
    aprasymas text,
    prioritetas text,
    privaloma smallint,
    unikalus_kodas character varying(64),
    CONSTRAINT gvx_klausimai_pkey PRIMARY KEY (id)
);
ALTER SEQUENCE public.gvx_klausimai_id_seq OWNED BY public.gvx_klausimai.id;

-- 2) Klausimų vertimai
CREATE TABLE IF NOT EXISTS public.gvx_klausimai_i18n (
    klausimas_id integer NOT NULL,
    lang character(2) NOT NULL,
    skyrius character varying(255),
    objektas character varying(255),
    aprasymas text,
    order_nr integer,
    CONSTRAINT gvx_klausimai_i18n_pkey PRIMARY KEY (klausimas_id, lang),
    CONSTRAINT gvx_klausimai_i18n_kid_fkey FOREIGN KEY (klausimas_id) REFERENCES public.gvx_klausimai(id) ON DELETE CASCADE
);

-- 3) Atsakymai (tikrinimo formų rezultatai)
CREATE SEQUENCE IF NOT EXISTS public.gvx_klausimu_atsakymai_id_seq AS bigint START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE TABLE IF NOT EXISTS public.gvx_klausimu_atsakymai (
    id bigint DEFAULT nextval('public.gvx_klausimu_atsakymai_id_seq'::regclass) NOT NULL,
    uzsakymo_id integer,
    gaminys_id integer,
    klausimo_id integer,
    pildytojas_id integer,
    pildytojas_vardas character varying(120),
    atliko_vardas character varying(100),
    atliko_operacija timestamp without time zone,
    atitikimas text,
    defektas text,
    istaisyta smallint,
    istaisyta_data timestamp without time zone,
    pastaba text,
    turi_foto smallint,
    foto_kiekis integer,
    foto_defektu_kiekis integer,
    foto_ist_kiekis integer,
    atnaujinta timestamp without time zone,
    defektai jsonb,
    CONSTRAINT gvx_klausimu_atsakymai_pkey PRIMARY KEY (id)
);
ALTER SEQUENCE public.gvx_klausimu_atsakymai_id_seq OWNED BY public.gvx_klausimu_atsakymai.id;
CREATE INDEX IF NOT EXISTS idx_gvx_ats_gaminys ON public.gvx_klausimu_atsakymai USING btree (gaminys_id);
CREATE INDEX IF NOT EXISTS idx_gvx_ats_uzsakymas ON public.gvx_klausimu_atsakymai USING btree (uzsakymo_id);

-- 4) Atsakymų nuotraukos (BYTEA)
CREATE SEQUENCE IF NOT EXISTS public.gvx_klausimu_fotos_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE TABLE IF NOT EXISTS public.gvx_klausimu_fotos (
    id integer DEFAULT nextval('public.gvx_klausimu_fotos_id_seq'::regclass) NOT NULL,
    atsakymo_id bigint,
    uzsakymo_id integer,
    gaminys_id integer,
    klausimo_id integer,
    tipas text,
    failas character varying(255),
    mime character varying(100),
    dydis_b integer,
    irase_vardas character varying(120),
    sukurta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    komentaras character varying(500),
    turinys_lob bytea,
    defekto_nr integer,
    CONSTRAINT gvx_klausimu_fotos_pkey PRIMARY KEY (id)
);
ALTER SEQUENCE public.gvx_klausimu_fotos_id_seq OWNED BY public.gvx_klausimu_fotos.id;
CREATE INDEX IF NOT EXISTS idx_gvx_fotos_atsakymas ON public.gvx_klausimu_fotos USING btree (atsakymo_id);
CREATE INDEX IF NOT EXISTS idx_gvx_fotos_gaminys ON public.gvx_klausimu_fotos USING btree (gaminys_id);

-- 5) Izoliacijos matavimai (+foto BYTEA)
CREATE SEQUENCE IF NOT EXISTS public.gvx_izoliacijos_matavimai_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
CREATE TABLE IF NOT EXISTS public.gvx_izoliacijos_matavimai (
    id integer DEFAULT nextval('public.gvx_izoliacijos_matavimai_id_seq'::regclass) NOT NULL,
    gaminys_id integer,
    uzsakymo_id integer,
    punktas character varying(50),
    reiksme character varying(100),
    foto_turinys bytea,
    foto_pavadinimas character varying(255),
    irase_vartotojas character varying(100),
    sukurta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT gvx_izoliacijos_matavimai_pkey PRIMARY KEY (id)
);
ALTER SEQUENCE public.gvx_izoliacijos_matavimai_id_seq OWNED BY public.gvx_izoliacijos_matavimai.id;

-- 6) Esamą gvx_dokumentai papildome gaminys_id (kad atitiktų šaltinį)
ALTER TABLE public.gvx_dokumentai ADD COLUMN IF NOT EXISTS gaminys_id integer;
ALTER TABLE public.gvx_dokumentai ADD COLUMN IF NOT EXISTS sukurejas_old character varying(120);

-- ===== 4. MODULIAI (gaminiu_rusys) =====
INSERT INTO public.gaminiu_rusys (id, pavadinimas) VALUES
 (2,'MT'),(7,'Dėžes'),(10,'SĮ-04'),(11,'USN'),(12,'LSP'),(13,'SRS'),(14,'UVN'),(15,'KAMP'),(16,'GVX')
ON CONFLICT (id) DO NOTHING;
SELECT setval('public.gaminiu_rusys_id_seq',(SELECT GREATEST(MAX(id),1) FROM public.gaminiu_rusys));

-- ===== 5. VARTOTOJAI (16) + aktyvus stulpelis =====
-- ==========================================================================
--  Vartotojų įkėlimas į public.vartotojai (16 įrašų)
--  Paleisk Supabase SQL Editor lange.
--  parasas (bytea) visiems tuščias -> praleidžiamas (liks NULL).
-- ==========================================================================

-- 1) Trūkstamas stulpelis (jo nebuvo schemos eksporte, bet yra gyvoje DB ir
--    jo reikalauja login.php). Saugu paleisti kartotinai.
ALTER TABLE public.vartotojai
    ADD COLUMN IF NOT EXISTS aktyvus boolean NOT NULL DEFAULT true;

-- 2) Duomenys
INSERT INTO public.vartotojai
    (id, vardas, pavarde, el_pastas, slaptazodis, sukurta, role,
     login_token, token_galiojimas, patvirtintas, patvirtino_id,
     patvirtinimo_data, parasas_tipas, pareigos, aktyvus)
VALUES
 (8,  'Tomas',    'Atkociunas', 'tomas.atkociunas@elga.lt',   '$2y$10$Dqhkp2fQuFjeUD6qbRH7x.Cz6NBemKn6S1H/EaBPCu2TZpP8jeR7u', '2026-06-15 10:16:53.617191+00', 'vartotojas',     NULL, NULL,                          false, NULL, NULL,                      NULL, 'Kokybės inžinierius', true),
 (9,  'Tomas',    'Viržintas',  'virzintas@gmail.com',        '$2y$10$uhX1Z6AQwDKYLrsw/cnsEuDeFpPLaA7EBhTOPiLj6vSUmjpNIGBjm', '2025-06-06 18:54:31',           'administratorius', '7ece3f71cdcb00b691e0d71e0404f273b9950bd60d284f17437e70c4e8aacf12', '2026-05-22T21:33:06.000Z', true,  NULL, NULL,                      NULL, '', true),
 (13, 'Nerius',   'Grublys',    'nerius.grublys@elga.lt',     '$2y$10$zPqp5tht/Uu8vvWGQ3Dklu6BUIgoTZLuPsEcr1BkPX8on77.piiIG', '2025-08-05 11:51:17',           'user',           NULL, NULL,                          true,  NULL, NULL,                      NULL, '', true),
 (14, 'Aivaras',  'Čerkauskas', NULL,                         '$2y$10$s5Si5Yl.bffhy1i.qvyV7u5Qjl8vlIJrKg92tapGFduZPLe2DUbym', '2026-06-15 10:16:54.018222+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (15, 'Tadas',    'Bumblauskas','Tad@darb.com',               '$2y$10$2caP6mH2WKorLSNwb8LjHuHRqXohHraxyQVtdyDlFqVLR7oZklxnG', '2025-08-08 07:11:30',           'user',           NULL, NULL,                          true,  NULL, NULL,                      NULL, '', true),
 (17, 'Demo',     'Vartotojas', 'demo@example.com',           '$2y$10$MlcITFAHEqV3Wr8j1M2cJOYGtkbQ/cd.wOOCCGSEdyPClzsJpRHTe', '2026-06-15 10:16:54.281328+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (19, 'GVX',      'Darbuotojas',NULL,                         '$2y$10$jUt.tjcfh3evryq0E.Y1nOcKixDgNOaLLiEQWXuVB7L14R.3qxc8q', '2026-06-15 10:16:54.41077+00',  'skaitytojas',    NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (21, 'Sigitas',  'Dimša',      'sigitas.dimsa@elga.lt',      '$2y$10$2/WzN9HdvRmmkPC3FbX3AeSJL29KNq42CeBl42TpMvwssRjgoTg1m', '2026-06-15 10:16:54.542047+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (22, 'Augustas', 'Volbekas',   'augustas@elga.lt',           '$2y$10$idskLuCrX2QGy2yQGUFmDe6Brvz1.br76UZLyCzyJZzu/CtcmjcwK', '2026-06-15 10:16:54.670466+00', 'administratorius', NULL, NULL,                        true,  9,    '2026-06-17T05:58:39.877Z', NULL, '', true),
 (23, 'Saulius',  'Vidmantas',  NULL,                         '$2y$10$7qz2Hgbh1NeuL24NvLI6huYTB7wKMedXAORU24usnNbHkziJo/rUu', '2026-06-15 10:16:54.799386+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (24, 'Valdas',   'Meiliunas',  'valdas@elga.lt',             '$2y$10$a6UJqT6TYETourT4XLlEiesRPWpMTmk6xAlJWnq10aZy8S6YpanRO', '2026-06-15 10:16:54.928975+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (25, 'Laurynas', 'Butkys',     'laurynas.butkys@elga.lt',    '$2y$10$OG8jE07qL8pxHlRSUgr69uFqGxlHTXeduKEQYxzrSxsrjw1BItnO6', '2026-06-15 10:16:55.057895+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (26, 'Laimutis', 'Meiliunas',  'Laimutis.Meiliunas@elga.lt', '$2y$10$FjEgOAgKdff6fKxXYqqrX.Sw3whtzRXgMAEtCWaXPmDQBRvl8i9um', '2026-06-15 10:16:55.185351+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (27, 'Kestutis', 'Ruzgis',     'ruzgis@elga.lt',             '$2y$10$qTAUX7xU0N/oTNJSu8fiV.iyZekNpsbJWg0WD5ZyH1QzPLbmtXREi', '2026-06-15 10:16:55.312671+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (28, 'Aivaras',  'Berneckis',  'aivaras@elga.lt',            '$2y$10$a/zn66K9I03g8bc88/jrleViFr.WxxbEwO6Uc..68LNDlmls4h6eS', '2026-06-15 10:16:55.442632+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (29, 'Robertas', 'Dapkus',     'dapkus@elga.lt',             '$2y$10$VBiYZbb6D3kzJGZWF8oqwe6ROfIiqxkxNQPxEU/xGUWU93Vtv4y/C', '2026-06-15 10:16:55.569629+00', 'vartotojas',     NULL, NULL,                          true,  9,    '2026-06-20T09:37:35.840Z', NULL, '', true);

-- 3) Sekos sinchronizavimas (kad nauji vartotojai negautų jau užimto id)
SELECT setval('public.vartotojai_id_seq',
              COALESCE((SELECT MAX(id) FROM public.vartotojai), 0) + 1, false);

-- ===== 6. FOREIGN KEYS (RESTRICT / SET NULL / CASCADE) =====
ALTER TABLE public.komponentai             ADD CONSTRAINT fk_komponentai_gaminio FOREIGN KEY (gaminio_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.funkciniai_bandymai     ADD CONSTRAINT fk_funkciniai_gaminio FOREIGN KEY (gaminio_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.dielektriniai_bandymai  ADD CONSTRAINT fk_dielektriniai_gaminys FOREIGN KEY (gaminys_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.izeminimo_tikrinimas    ADD CONSTRAINT fk_izeminimo_gaminys FOREIGN KEY (gaminys_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.saugikliu_ideklai       ADD CONSTRAINT fk_saugikliu_gaminio FOREIGN KEY (gaminio_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.bandymai_prietaisai     ADD CONSTRAINT fk_bandprietaisai_gaminys FOREIGN KEY (gaminys_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.paso_teksto_korekcijos  ADD CONSTRAINT fk_pasokorekc_gaminio FOREIGN KEY (gaminio_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.gaminiu_pdf_failai      ADD CONSTRAINT fk_gampdf_gaminio FOREIGN KEY (gaminio_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.gaminiai                ADD CONSTRAINT fk_gaminiai_uzsakymo FOREIGN KEY (uzsakymo_id) REFERENCES public.uzsakymai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.gaminiai                ADD CONSTRAINT fk_gaminiai_tipas FOREIGN KEY (gaminio_tipas_id) REFERENCES public.gaminio_tipai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.gvx_dokumentai          ADD CONSTRAINT fk_gvx_uzsakymo FOREIGN KEY (uzsakymo_id) REFERENCES public.uzsakymai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.pretenzijos             ADD CONSTRAINT fk_pret_uzsakymo FOREIGN KEY (uzsakymo_id) REFERENCES public.uzsakymai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.pretenzijos             ADD CONSTRAINT fk_pret_gaminio FOREIGN KEY (gaminio_id) REFERENCES public.gaminiai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.uzsakymai               ADD CONSTRAINT fk_uzsakymai_uzsakovas FOREIGN KEY (uzsakovas_id) REFERENCES public.uzsakovai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.uzsakymai               ADD CONSTRAINT fk_uzsakymai_objektas FOREIGN KEY (objektas_id) REFERENCES public.objektai(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.uzsakymai               ADD CONSTRAINT fk_uzsakymai_rusis FOREIGN KEY (gaminiu_rusis_id) REFERENCES public.gaminiu_rusys(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.funkciniu_sablonas      ADD CONSTRAINT fk_sablonas_rusis FOREIGN KEY (gaminiu_rusis_id) REFERENCES public.gaminiu_rusys(id) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE public.uzsakymai               ADD CONSTRAINT fk_uzsakymai_vartotojas FOREIGN KEY (vartotojas_id) REFERENCES public.vartotojai(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE public.pretenzijos             ADD CONSTRAINT fk_pret_sukure FOREIGN KEY (sukure_id) REFERENCES public.vartotojai(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE public.gaminiu_pdf_failai      ADD CONSTRAINT fk_gampdf_vartotojas FOREIGN KEY (vartotojas_id) REFERENCES public.vartotojai(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE public.vartotojai              ADD CONSTRAINT fk_vartotojai_patvirtino FOREIGN KEY (patvirtino_id) REFERENCES public.vartotojai(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE public.remember_tokens         ADD CONSTRAINT fk_remember_vartotojas FOREIGN KEY (vartotojas_id) REFERENCES public.vartotojai(id) ON DELETE CASCADE ON UPDATE CASCADE;
