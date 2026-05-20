--
-- PostgreSQL database dump
--

\restrict UcEegdnVuioqAXfxV4KcRrzT2Gy5kidQyMZ1To5pSH2ObBxv0cD3Z0QOFB7XlnX

-- Dumped from database version 16.10
-- Dumped by pg_dump version 16.10

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

--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: aktyvus_vartotojai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.aktyvus_vartotojai (
    id integer NOT NULL,
    vartotojas_id integer NOT NULL,
    session_id character varying(255) NOT NULL,
    vardas character varying(100),
    pavarde character varying(100),
    prisijungimo_laikas timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    paskutine_veikla timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ip_adresas character varying(50),
    "naršykle" character varying(255)
);


ALTER TABLE public.aktyvus_vartotojai OWNER TO postgres;

--
-- Name: aktyvus_vartotojai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.aktyvus_vartotojai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.aktyvus_vartotojai_id_seq OWNER TO postgres;

--
-- Name: aktyvus_vartotojai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.aktyvus_vartotojai_id_seq OWNED BY public.aktyvus_vartotojai.id;


--
-- Name: antriniu_grandiniu_bandymai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.antriniu_grandiniu_bandymai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.antriniu_grandiniu_bandymai_id_seq OWNER TO postgres;

--
-- Name: bandymai_prietaisai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.bandymai_prietaisai (
    id integer NOT NULL,
    gaminys_id integer NOT NULL,
    prietaiso_tipas character varying(255),
    prietaiso_nr character varying(255),
    patikra_data date,
    galioja_iki date,
    sertifikato_nr character varying(255)
);


ALTER TABLE public.bandymai_prietaisai OWNER TO postgres;

--
-- Name: bandymai_prietaisai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.bandymai_prietaisai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bandymai_prietaisai_id_seq OWNER TO postgres;

--
-- Name: bandymai_prietaisai_id_seq1; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.bandymai_prietaisai_id_seq1
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bandymai_prietaisai_id_seq1 OWNER TO postgres;

--
-- Name: bandymai_prietaisai_id_seq1; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.bandymai_prietaisai_id_seq1 OWNED BY public.bandymai_prietaisai.id;


--
-- Name: mt_dielektriniai_bandymai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mt_dielektriniai_bandymai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mt_dielektriniai_bandymai_id_seq OWNER TO postgres;

--
-- Name: dielektriniai_bandymai; Type: TABLE; Schema: public; Owner: postgres
--

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


ALTER TABLE public.dielektriniai_bandymai OWNER TO postgres;

--
-- Name: mt_funkciniai_bandymai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mt_funkciniai_bandymai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mt_funkciniai_bandymai_id_seq OWNER TO postgres;

--
-- Name: funkciniai_bandymai; Type: TABLE; Schema: public; Owner: postgres
--

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


ALTER TABLE public.funkciniai_bandymai OWNER TO postgres;

--
-- Name: funkciniai_bandymai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.funkciniai_bandymai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.funkciniai_bandymai_id_seq OWNER TO postgres;

--
-- Name: funkciniu_sablonas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.funkciniu_sablonas (
    id integer NOT NULL,
    eil_nr integer NOT NULL,
    pavadinimas text NOT NULL,
    gaminiu_rusis_id integer DEFAULT 2
);


ALTER TABLE public.funkciniu_sablonas OWNER TO postgres;

--
-- Name: gaminiai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.gaminiai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.gaminiai_id_seq OWNER TO postgres;

--
-- Name: gaminiai; Type: TABLE; Schema: public; Owner: postgres
--

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


ALTER TABLE public.gaminiai OWNER TO postgres;

--
-- Name: gaminio_tipai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.gaminio_tipai (
    id integer NOT NULL,
    gaminio_tipas text,
    grupe text NOT NULL,
    atitikmuo_kodas text
);


ALTER TABLE public.gaminio_tipai OWNER TO postgres;

--
-- Name: gaminio_tipai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.gaminio_tipai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.gaminio_tipai_id_seq OWNER TO postgres;

--
-- Name: gaminio_tipai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.gaminio_tipai_id_seq OWNED BY public.gaminio_tipai.id;


--
-- Name: gaminiu_rusys; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.gaminiu_rusys (
    id integer NOT NULL,
    pavadinimas text NOT NULL
);


ALTER TABLE public.gaminiu_rusys OWNER TO postgres;

--
-- Name: gaminiu_rusys_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.gaminiu_rusys_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.gaminiu_rusys_id_seq OWNER TO postgres;

--
-- Name: gaminiu_rusys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.gaminiu_rusys_id_seq OWNED BY public.gaminiu_rusys.id;


--
-- Name: imones_nustatymai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.imones_nustatymai (
    id integer NOT NULL,
    pavadinimas character varying(255) DEFAULT 'UAB "ELGA"'::character varying,
    adresas text DEFAULT 'Pramonės g. 12, LT-78150 Šiauliai, Lietuva'::text,
    telefonas character varying(100) DEFAULT '+370 41 594710'::character varying,
    faksas character varying(100) DEFAULT '+370 41 594725'::character varying,
    el_pastas character varying(255) DEFAULT 'info@elga.lt'::character varying,
    internetas character varying(255) DEFAULT 'www.elga.lt'::character varying,
    logotipas bytea,
    logotipo_tipas character varying(50)
);


ALTER TABLE public.imones_nustatymai OWNER TO postgres;

--
-- Name: imones_nustatymai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.imones_nustatymai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.imones_nustatymai_id_seq OWNER TO postgres;

--
-- Name: imones_nustatymai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.imones_nustatymai_id_seq OWNED BY public.imones_nustatymai.id;


--
-- Name: mt_izeminimo_tikrinimas_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mt_izeminimo_tikrinimas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mt_izeminimo_tikrinimas_id_seq OWNER TO postgres;

--
-- Name: izeminimo_tikrinimas; Type: TABLE; Schema: public; Owner: postgres
--

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


ALTER TABLE public.izeminimo_tikrinimas OWNER TO postgres;

--
-- Name: komponentai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.komponentai (
    id integer NOT NULL,
    eiles_numeris integer NOT NULL,
    gamintojo_kodas text,
    kiekis integer NOT NULL,
    aprasymas text,
    gamintojas text,
    gaminio_id integer,
    parinkta_projektui smallint DEFAULT 0
);


ALTER TABLE public.komponentai OWNER TO postgres;

--
-- Name: mt_funkciniu_sablonas_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mt_funkciniu_sablonas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mt_funkciniu_sablonas_id_seq OWNER TO postgres;

--
-- Name: mt_funkciniu_sablonas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mt_funkciniu_sablonas_id_seq OWNED BY public.funkciniu_sablonas.id;


--
-- Name: mt_komponentai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mt_komponentai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mt_komponentai_id_seq OWNER TO postgres;

--
-- Name: mt_komponentai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mt_komponentai_id_seq OWNED BY public.komponentai.id;


--
-- Name: paso_teksto_korekcijos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.paso_teksto_korekcijos (
    id integer NOT NULL,
    gaminio_id integer NOT NULL,
    field_key character varying(100) NOT NULL,
    lang character varying(5) DEFAULT 'lt'::character varying NOT NULL,
    tekstas text,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.paso_teksto_korekcijos OWNER TO postgres;

--
-- Name: mt_paso_teksto_korekcijos_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mt_paso_teksto_korekcijos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mt_paso_teksto_korekcijos_id_seq OWNER TO postgres;

--
-- Name: mt_paso_teksto_korekcijos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mt_paso_teksto_korekcijos_id_seq OWNED BY public.paso_teksto_korekcijos.id;


--
-- Name: mt_saugikliu_ideklai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mt_saugikliu_ideklai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mt_saugikliu_ideklai_id_seq OWNER TO postgres;

--
-- Name: narvelio_komponentai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.narvelio_komponentai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.narvelio_komponentai_id_seq OWNER TO postgres;

--
-- Name: objektai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.objektai (
    id integer NOT NULL,
    pavadinimas text NOT NULL
);


ALTER TABLE public.objektai OWNER TO postgres;

--
-- Name: objektai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.objektai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.objektai_id_seq OWNER TO postgres;

--
-- Name: objektai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.objektai_id_seq OWNED BY public.objektai.id;


--
-- Name: ominiu_varzos_matavimai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ominiu_varzos_matavimai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ominiu_varzos_matavimai_id_seq OWNER TO postgres;

--
-- Name: pirminiu_bandymai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pirminiu_bandymai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pirminiu_bandymai_id_seq OWNER TO postgres;

--
-- Name: pretenzijos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pretenzijos (
    id integer NOT NULL,
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


ALTER TABLE public.pretenzijos OWNER TO postgres;

--
-- Name: pretenzijos_email_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pretenzijos_email_history (
    id integer NOT NULL,
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


ALTER TABLE public.pretenzijos_email_history OWNER TO postgres;

--
-- Name: pretenzijos_email_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pretenzijos_email_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pretenzijos_email_history_id_seq OWNER TO postgres;

--
-- Name: pretenzijos_email_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pretenzijos_email_history_id_seq OWNED BY public.pretenzijos_email_history.id;


--
-- Name: pretenzijos_failai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pretenzijos_failai (
    id integer NOT NULL,
    pretenzija_id integer NOT NULL,
    pavadinimas character varying(500) NOT NULL,
    tipas character varying(255),
    turinys bytea NOT NULL,
    ikelta timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.pretenzijos_failai OWNER TO postgres;

--
-- Name: pretenzijos_failai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pretenzijos_failai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pretenzijos_failai_id_seq OWNER TO postgres;

--
-- Name: pretenzijos_failai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pretenzijos_failai_id_seq OWNED BY public.pretenzijos_failai.id;


--
-- Name: pretenzijos_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pretenzijos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pretenzijos_id_seq OWNER TO postgres;

--
-- Name: pretenzijos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pretenzijos_id_seq OWNED BY public.pretenzijos.id;


--
-- Name: pretenzijos_nuotraukos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pretenzijos_nuotraukos (
    id integer NOT NULL,
    pretenzija_id integer NOT NULL,
    pavadinimas character varying(255),
    tipas character varying(100),
    turinys bytea,
    sukurta timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.pretenzijos_nuotraukos OWNER TO postgres;

--
-- Name: pretenzijos_nuotraukos_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pretenzijos_nuotraukos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pretenzijos_nuotraukos_id_seq OWNER TO postgres;

--
-- Name: pretenzijos_nuotraukos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pretenzijos_nuotraukos_id_seq OWNED BY public.pretenzijos_nuotraukos.id;


--
-- Name: prietaisai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.prietaisai (
    id integer NOT NULL,
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


ALTER TABLE public.prietaisai OWNER TO postgres;

--
-- Name: prietaisai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.prietaisai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.prietaisai_id_seq OWNER TO postgres;

--
-- Name: prietaisai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.prietaisai_id_seq OWNED BY public.prietaisai.id;


--
-- Name: remember_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.remember_tokens (
    id integer NOT NULL,
    vartotojas_id integer NOT NULL,
    token character varying(255) NOT NULL,
    expires_at timestamp without time zone NOT NULL
);


ALTER TABLE public.remember_tokens OWNER TO postgres;

--
-- Name: remember_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.remember_tokens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.remember_tokens_id_seq OWNER TO postgres;

--
-- Name: remember_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.remember_tokens_id_seq OWNED BY public.remember_tokens.id;


--
-- Name: saugikliu_ideklai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.saugikliu_ideklai (
    id integer DEFAULT nextval('public.mt_saugikliu_ideklai_id_seq'::regclass) NOT NULL,
    gaminio_id integer,
    sekcija character varying(10),
    pozicija integer,
    gabaritas character varying(20),
    nominalas character varying(10),
    pozicijos_numeris integer
);


ALTER TABLE public.saugikliu_ideklai OWNER TO postgres;

--
-- Name: uzsakovai; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.uzsakovai (
    id integer NOT NULL,
    uzsakovas text
);


ALTER TABLE public.uzsakovai OWNER TO postgres;

--
-- Name: uzsakovai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.uzsakovai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.uzsakovai_id_seq OWNER TO postgres;

--
-- Name: uzsakovai_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.uzsakovai_id_seq OWNED BY public.uzsakovai.id;


--
-- Name: uzsakymai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.uzsakymai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.uzsakymai_id_seq OWNER TO postgres;

--
-- Name: uzsakymai; Type: TABLE; Schema: public; Owner: postgres
--

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


ALTER TABLE public.uzsakymai OWNER TO postgres;

--
-- Name: vartotojai_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.vartotojai_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vartotojai_id_seq OWNER TO postgres;

--
-- Name: vartotojai; Type: TABLE; Schema: public; Owner: postgres
--

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


ALTER TABLE public.vartotojai OWNER TO postgres;

--
-- Name: aktyvus_vartotojai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.aktyvus_vartotojai ALTER COLUMN id SET DEFAULT nextval('public.aktyvus_vartotojai_id_seq'::regclass);


--
-- Name: bandymai_prietaisai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bandymai_prietaisai ALTER COLUMN id SET DEFAULT nextval('public.bandymai_prietaisai_id_seq1'::regclass);


--
-- Name: funkciniu_sablonas id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.funkciniu_sablonas ALTER COLUMN id SET DEFAULT nextval('public.mt_funkciniu_sablonas_id_seq'::regclass);


--
-- Name: gaminio_tipai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gaminio_tipai ALTER COLUMN id SET DEFAULT nextval('public.gaminio_tipai_id_seq'::regclass);


--
-- Name: gaminiu_rusys id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gaminiu_rusys ALTER COLUMN id SET DEFAULT nextval('public.gaminiu_rusys_id_seq'::regclass);


--
-- Name: imones_nustatymai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.imones_nustatymai ALTER COLUMN id SET DEFAULT nextval('public.imones_nustatymai_id_seq'::regclass);


--
-- Name: komponentai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.komponentai ALTER COLUMN id SET DEFAULT nextval('public.mt_komponentai_id_seq'::regclass);


--
-- Name: objektai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objektai ALTER COLUMN id SET DEFAULT nextval('public.objektai_id_seq'::regclass);


--
-- Name: paso_teksto_korekcijos id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.paso_teksto_korekcijos ALTER COLUMN id SET DEFAULT nextval('public.mt_paso_teksto_korekcijos_id_seq'::regclass);


--
-- Name: pretenzijos id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos ALTER COLUMN id SET DEFAULT nextval('public.pretenzijos_id_seq'::regclass);


--
-- Name: pretenzijos_email_history id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_email_history ALTER COLUMN id SET DEFAULT nextval('public.pretenzijos_email_history_id_seq'::regclass);


--
-- Name: pretenzijos_failai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_failai ALTER COLUMN id SET DEFAULT nextval('public.pretenzijos_failai_id_seq'::regclass);


--
-- Name: pretenzijos_nuotraukos id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_nuotraukos ALTER COLUMN id SET DEFAULT nextval('public.pretenzijos_nuotraukos_id_seq'::regclass);


--
-- Name: prietaisai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.prietaisai ALTER COLUMN id SET DEFAULT nextval('public.prietaisai_id_seq'::regclass);


--
-- Name: remember_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.remember_tokens ALTER COLUMN id SET DEFAULT nextval('public.remember_tokens_id_seq'::regclass);


--
-- Name: uzsakovai id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.uzsakovai ALTER COLUMN id SET DEFAULT nextval('public.uzsakovai_id_seq'::regclass);


--
-- Name: aktyvus_vartotojai aktyvus_vartotojai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.aktyvus_vartotojai
    ADD CONSTRAINT aktyvus_vartotojai_pkey PRIMARY KEY (id);


--
-- Name: aktyvus_vartotojai aktyvus_vartotojai_session_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.aktyvus_vartotojai
    ADD CONSTRAINT aktyvus_vartotojai_session_id_key UNIQUE (session_id);


--
-- Name: bandymai_prietaisai bandymai_prietaisai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bandymai_prietaisai
    ADD CONSTRAINT bandymai_prietaisai_pkey PRIMARY KEY (id);


--
-- Name: gaminiai gaminiai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gaminiai
    ADD CONSTRAINT gaminiai_pkey PRIMARY KEY (id);


--
-- Name: gaminio_tipai gaminio_tipai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gaminio_tipai
    ADD CONSTRAINT gaminio_tipai_pkey PRIMARY KEY (id);


--
-- Name: gaminiu_rusys gaminiu_rusys_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gaminiu_rusys
    ADD CONSTRAINT gaminiu_rusys_pkey PRIMARY KEY (id);


--
-- Name: imones_nustatymai imones_nustatymai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.imones_nustatymai
    ADD CONSTRAINT imones_nustatymai_pkey PRIMARY KEY (id);


--
-- Name: funkciniu_sablonas mt_funkciniu_sablonas_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.funkciniu_sablonas
    ADD CONSTRAINT mt_funkciniu_sablonas_pkey PRIMARY KEY (id);


--
-- Name: komponentai mt_komponentai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.komponentai
    ADD CONSTRAINT mt_komponentai_pkey PRIMARY KEY (id);


--
-- Name: paso_teksto_korekcijos mt_paso_teksto_korekcijos_gaminio_id_field_key_lang_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.paso_teksto_korekcijos
    ADD CONSTRAINT mt_paso_teksto_korekcijos_gaminio_id_field_key_lang_key UNIQUE (gaminio_id, field_key, lang);


--
-- Name: paso_teksto_korekcijos mt_paso_teksto_korekcijos_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.paso_teksto_korekcijos
    ADD CONSTRAINT mt_paso_teksto_korekcijos_pkey PRIMARY KEY (id);


--
-- Name: saugikliu_ideklai mt_saugikliu_ideklai_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.saugikliu_ideklai
    ADD CONSTRAINT mt_saugikliu_ideklai_unique UNIQUE (gaminio_id, sekcija, pozicijos_numeris);


--
-- Name: objektai objektai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.objektai
    ADD CONSTRAINT objektai_pkey PRIMARY KEY (id);


--
-- Name: pretenzijos_email_history pretenzijos_email_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_email_history
    ADD CONSTRAINT pretenzijos_email_history_pkey PRIMARY KEY (id);


--
-- Name: pretenzijos_failai pretenzijos_failai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_failai
    ADD CONSTRAINT pretenzijos_failai_pkey PRIMARY KEY (id);


--
-- Name: pretenzijos_nuotraukos pretenzijos_nuotraukos_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_nuotraukos
    ADD CONSTRAINT pretenzijos_nuotraukos_pkey PRIMARY KEY (id);


--
-- Name: pretenzijos pretenzijos_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos
    ADD CONSTRAINT pretenzijos_pkey PRIMARY KEY (id);


--
-- Name: prietaisai prietaisai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.prietaisai
    ADD CONSTRAINT prietaisai_pkey PRIMARY KEY (id);


--
-- Name: remember_tokens remember_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.remember_tokens
    ADD CONSTRAINT remember_tokens_pkey PRIMARY KEY (id);


--
-- Name: uzsakovai uzsakovai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.uzsakovai
    ADD CONSTRAINT uzsakovai_pkey PRIMARY KEY (id);


--
-- Name: uzsakymai uzsakymai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.uzsakymai
    ADD CONSTRAINT uzsakymai_pkey PRIMARY KEY (id);


--
-- Name: vartotojai vartotojai_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.vartotojai
    ADD CONSTRAINT vartotojai_pkey PRIMARY KEY (id);


--
-- Name: idx_pretenzijos_failai_pid; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pretenzijos_failai_pid ON public.pretenzijos_failai USING btree (pretenzija_id);


--
-- Name: idx_pretenzijos_qt_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX idx_pretenzijos_qt_id ON public.pretenzijos USING btree (qt_pretenzija_id) WHERE (qt_pretenzija_id IS NOT NULL);


--
-- Name: pretenzijos_perziuros_token_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX pretenzijos_perziuros_token_idx ON public.pretenzijos USING btree (perziuros_token);


--
-- Name: pretenzijos_email_history pretenzijos_email_history_parent_history_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_email_history
    ADD CONSTRAINT pretenzijos_email_history_parent_history_id_fkey FOREIGN KEY (parent_history_id) REFERENCES public.pretenzijos_email_history(id) ON DELETE SET NULL;


--
-- Name: pretenzijos_email_history pretenzijos_email_history_pretenzija_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_email_history
    ADD CONSTRAINT pretenzijos_email_history_pretenzija_id_fkey FOREIGN KEY (pretenzija_id) REFERENCES public.pretenzijos(id) ON DELETE CASCADE;


--
-- Name: pretenzijos_failai pretenzijos_failai_pretenzija_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_failai
    ADD CONSTRAINT pretenzijos_failai_pretenzija_id_fkey FOREIGN KEY (pretenzija_id) REFERENCES public.pretenzijos(id) ON DELETE CASCADE;


--
-- Name: pretenzijos_nuotraukos pretenzijos_nuotraukos_pretenzija_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pretenzijos_nuotraukos
    ADD CONSTRAINT pretenzijos_nuotraukos_pretenzija_id_fkey FOREIGN KEY (pretenzija_id) REFERENCES public.pretenzijos(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict UcEegdnVuioqAXfxV4KcRrzT2Gy5kidQyMZ1To5pSH2ObBxv0cD3Z0QOFB7XlnX

