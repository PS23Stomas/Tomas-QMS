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
