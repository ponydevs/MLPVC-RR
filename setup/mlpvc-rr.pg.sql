--
-- PostgreSQL database dump
--

-- Dumped from database version 9.5.3
-- Dumped by pg_dump version 9.5.3

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: citext; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS citext WITH SCHEMA public;


--
-- Name: EXTENSION citext; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION citext IS 'data type for case-insensitive character strings';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: deviation_cache; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE deviation_cache (
    provider character(6) NOT NULL,
    id character varying(20) NOT NULL,
    title character varying(255) NOT NULL,
    author character varying(20),
    preview character varying(255) NOT NULL,
    fullsize character varying(255) NOT NULL,
    updated_on timestamp with time zone DEFAULT now()
);


ALTER TABLE deviation_cache OWNER TO "mlpvc-rr";

--
-- Name: episodes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE episodes (
    season integer NOT NULL,
    episode integer NOT NULL,
    twoparter boolean DEFAULT false NOT NULL,
    title text NOT NULL,
    posted timestamp with time zone DEFAULT now() NOT NULL,
    posted_by uuid,
    airs timestamp with time zone,
    no smallint
);


ALTER TABLE episodes OWNER TO "mlpvc-rr";

--
-- Name: episodes__videos; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE episodes__videos (
    season integer NOT NULL,
    episode integer NOT NULL,
    provider character(2) NOT NULL,
    id character varying(15) NOT NULL,
    part integer DEFAULT 1 NOT NULL,
    fullep boolean DEFAULT true NOT NULL,
    modified timestamp with time zone DEFAULT now()
);


ALTER TABLE episodes__videos OWNER TO "mlpvc-rr";

--
-- Name: episodes__votes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE episodes__votes (
    season integer NOT NULL,
    episode integer NOT NULL,
    "user" uuid NOT NULL,
    vote smallint NOT NULL
);


ALTER TABLE episodes__votes OWNER TO "mlpvc-rr";

--
-- Name: global_settings; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE global_settings (
    key character varying(50) NOT NULL,
    value text
);


ALTER TABLE global_settings OWNER TO "mlpvc-rr";

--
-- Name: log; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log (
    entryid integer NOT NULL,
    initiator uuid,
    reftype character varying(20) NOT NULL,
    refid integer,
    "timestamp" timestamp with time zone DEFAULT now() NOT NULL,
    ip character varying(255)
);


ALTER TABLE log OWNER TO "mlpvc-rr";

--
-- Name: log__appearance_modify; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__appearance_modify (
    entryid integer NOT NULL,
    ponyid integer NOT NULL,
    changes jsonb NOT NULL
);


ALTER TABLE log__appearance_modify OWNER TO "mlpvc-rr";

--
-- Name: log__appearances; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__appearances (
    entryid integer NOT NULL,
    action character(3) NOT NULL,
    id integer NOT NULL,
    "order" integer,
    label character varying(70) NOT NULL,
    notes text NOT NULL,
    cm_favme character varying(20),
    ishuman boolean NOT NULL,
    added timestamp with time zone,
    cm_preview character varying(255),
    cm_dir boolean,
    usetemplate boolean DEFAULT false NOT NULL
);


ALTER TABLE log__appearances OWNER TO "mlpvc-rr";

--
-- Name: log__appearances_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__appearances_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__appearances_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__appearances_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__appearances_entryid_seq OWNED BY log__appearances.entryid;


--
-- Name: log__banish; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__banish (
    entryid integer NOT NULL,
    target uuid NOT NULL,
    reason character varying(255) NOT NULL
);


ALTER TABLE log__banish OWNER TO "mlpvc-rr";

--
-- Name: log__banish_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__banish_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__banish_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__banish_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__banish_entryid_seq OWNED BY log__banish.entryid;


--
-- Name: log__cg_modify; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__cg_modify (
    entryid integer NOT NULL,
    groupid integer NOT NULL,
    oldlabel character varying(255),
    newlabel character varying(255),
    oldcolors text,
    newcolors text,
    ponyid integer NOT NULL
);


ALTER TABLE log__cg_modify OWNER TO "mlpvc-rr";

--
-- Name: log__cg_modify_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__cg_modify_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__cg_modify_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__cg_modify_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__cg_modify_entryid_seq OWNED BY log__cg_modify.entryid;


--
-- Name: log__cg_order; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__cg_order (
    entryid integer NOT NULL,
    ponyid integer NOT NULL,
    oldgroups text NOT NULL,
    newgroups text NOT NULL
);


ALTER TABLE log__cg_order OWNER TO "mlpvc-rr";

--
-- Name: log__cg_order_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__cg_order_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__cg_order_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__cg_order_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__cg_order_entryid_seq OWNED BY log__cg_order.entryid;


--
-- Name: log__cgs; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__cgs (
    entryid integer NOT NULL,
    action character(3) NOT NULL,
    groupid integer NOT NULL,
    ponyid integer NOT NULL,
    label character varying(255) NOT NULL,
    "order" integer
);


ALTER TABLE log__cgs OWNER TO "mlpvc-rr";

--
-- Name: log__cgs_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__cgs_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__cgs_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__cgs_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__cgs_entryid_seq OWNED BY log__cgs.entryid;


--
-- Name: log__color_modify; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__color_modify (
    entryid integer NOT NULL,
    ponyid integer,
    reason character varying(255) NOT NULL
);


ALTER TABLE log__color_modify OWNER TO "mlpvc-rr";

--
-- Name: log__color_modify_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__color_modify_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__color_modify_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__color_modify_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__color_modify_entryid_seq OWNED BY log__color_modify.entryid;


--
-- Name: log__da_namechange; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__da_namechange (
    entryid integer NOT NULL,
    old citext NOT NULL,
    new citext NOT NULL,
    id uuid NOT NULL
);


ALTER TABLE log__da_namechange OWNER TO "mlpvc-rr";

--
-- Name: log__da_namechange_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__da_namechange_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__da_namechange_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__da_namechange_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__da_namechange_entryid_seq OWNED BY log__da_namechange.entryid;


--
-- Name: log__episode_modify; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__episode_modify (
    entryid integer NOT NULL,
    target text NOT NULL,
    oldseason integer,
    newseason integer,
    oldepisode integer,
    newepisode integer,
    oldtwoparter boolean,
    newtwoparter boolean,
    oldtitle text,
    newtitle text,
    oldairs timestamp without time zone,
    newairs timestamp without time zone
);


ALTER TABLE log__episode_modify OWNER TO "mlpvc-rr";

--
-- Name: log__episode_modify_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__episode_modify_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__episode_modify_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__episode_modify_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__episode_modify_entryid_seq OWNED BY log__episode_modify.entryid;


--
-- Name: log__episodes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__episodes (
    entryid integer NOT NULL,
    action character(3) NOT NULL,
    season integer NOT NULL,
    episode integer NOT NULL,
    twoparter boolean NOT NULL,
    title text NOT NULL,
    airs timestamp without time zone
);


ALTER TABLE log__episodes OWNER TO "mlpvc-rr";

--
-- Name: log__episodes_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__episodes_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__episodes_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__episodes_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__episodes_entryid_seq OWNED BY log__episodes.entryid;


--
-- Name: log__img_update; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__img_update (
    entryid integer NOT NULL,
    id integer NOT NULL,
    thing character varying(11) NOT NULL,
    oldpreview character varying(255),
    newpreview character varying(255),
    oldfullsize character varying(255),
    newfullsize character varying(255)
);


ALTER TABLE log__img_update OWNER TO "mlpvc-rr";

--
-- Name: log__img_update_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__img_update_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__img_update_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__img_update_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__img_update_entryid_seq OWNED BY log__img_update.entryid;


--
-- Name: log__post_lock; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__post_lock (
    entryid integer NOT NULL,
    type character varying(11) NOT NULL,
    id integer NOT NULL
);


ALTER TABLE log__post_lock OWNER TO "mlpvc-rr";

--
-- Name: log__post_lock_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__post_lock_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__post_lock_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__post_lock_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__post_lock_entryid_seq OWNED BY log__post_lock.entryid;


--
-- Name: log__req_delete; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__req_delete (
    entryid integer NOT NULL,
    id integer,
    season integer,
    episode integer,
    label character varying(255),
    type character varying(4),
    requested_by uuid,
    posted timestamp without time zone,
    reserved_by uuid,
    deviation_id character varying(7),
    lock boolean
);


ALTER TABLE log__req_delete OWNER TO "mlpvc-rr";

--
-- Name: log__req_delete_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__req_delete_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__req_delete_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__req_delete_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__req_delete_entryid_seq OWNED BY log__req_delete.entryid;


--
-- Name: log__res_overtake; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__res_overtake (
    entryid integer NOT NULL,
    type character varying(11) NOT NULL,
    id integer NOT NULL,
    reserved_at timestamp with time zone,
    reserved_by uuid NOT NULL
);


ALTER TABLE log__res_overtake OWNER TO "mlpvc-rr";

--
-- Name: log__res_overtake_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__res_overtake_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__res_overtake_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__res_overtake_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__res_overtake_entryid_seq OWNED BY log__res_overtake.entryid;


--
-- Name: log__res_transfer; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__res_transfer (
    entryid integer NOT NULL,
    type character varying(11) NOT NULL,
    id integer NOT NULL,
    "to" uuid NOT NULL
);


ALTER TABLE log__res_transfer OWNER TO "mlpvc-rr";

--
-- Name: log__res_transfer_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__res_transfer_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__res_transfer_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__res_transfer_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__res_transfer_entryid_seq OWNED BY log__res_transfer.entryid;


--
-- Name: log__rolechange; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__rolechange (
    entryid integer NOT NULL,
    target uuid NOT NULL,
    oldrole character varying(10) NOT NULL,
    newrole character varying(10) NOT NULL
);


ALTER TABLE log__rolechange OWNER TO "mlpvc-rr";

--
-- Name: log__rolechange_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__rolechange_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__rolechange_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__rolechange_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__rolechange_entryid_seq OWNED BY log__rolechange.entryid;


--
-- Name: log__un-banish; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE "log__un-banish" (
    entryid integer NOT NULL,
    target uuid NOT NULL,
    reason character varying(255) NOT NULL
);


ALTER TABLE "log__un-banish" OWNER TO "mlpvc-rr";

--
-- Name: log__un-banish_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE "log__un-banish_entryid_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE "log__un-banish_entryid_seq" OWNER TO "mlpvc-rr";

--
-- Name: log__un-banish_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE "log__un-banish_entryid_seq" OWNED BY "log__un-banish".entryid;


--
-- Name: log__userfetch; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__userfetch (
    entryid integer NOT NULL,
    userid uuid NOT NULL
);


ALTER TABLE log__userfetch OWNER TO "mlpvc-rr";

--
-- Name: log__userfetch_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__userfetch_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__userfetch_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__userfetch_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__userfetch_entryid_seq OWNED BY log__userfetch.entryid;


--
-- Name: log_appearance_modify_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log_appearance_modify_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log_appearance_modify_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log_appearance_modify_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log_appearance_modify_entryid_seq OWNED BY log__appearance_modify.entryid;


--
-- Name: log_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log_entryid_seq OWNED BY log.entryid;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE notifications (
    id integer NOT NULL,
    "user" uuid NOT NULL,
    type character varying(15) NOT NULL,
    data jsonb NOT NULL,
    sent_at timestamp with time zone DEFAULT now() NOT NULL,
    read_at timestamp with time zone,
    read_action character varying(10)
);


ALTER TABLE notifications OWNER TO "mlpvc-rr";

--
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE notifications_id_seq OWNER TO "mlpvc-rr";

--
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE notifications_id_seq OWNED BY notifications.id;


--
-- Name: requests; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE requests (
    id integer NOT NULL,
    type character varying(3) NOT NULL,
    season integer NOT NULL,
    episode integer NOT NULL,
    preview character varying(255) NOT NULL,
    fullsize character varying(255) NOT NULL,
    label character varying(255) NOT NULL,
    requested_by uuid,
    posted timestamp with time zone DEFAULT now() NOT NULL,
    reserved_by uuid,
    deviation_id character varying(7),
    lock boolean DEFAULT false NOT NULL,
    reserved_at timestamp with time zone,
    finished_at timestamp with time zone
);


ALTER TABLE requests OWNER TO "mlpvc-rr";

--
-- Name: requests_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE requests_id_seq OWNER TO "mlpvc-rr";

--
-- Name: requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE requests_id_seq OWNED BY requests.id;


--
-- Name: reservations; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE reservations (
    id integer NOT NULL,
    season integer NOT NULL,
    episode integer NOT NULL,
    preview character varying(255),
    fullsize character varying(255),
    label character varying(255),
    posted timestamp with time zone DEFAULT now() NOT NULL,
    reserved_by uuid,
    deviation_id character varying(7),
    lock boolean DEFAULT false NOT NULL,
    finished_at timestamp with time zone
);


ALTER TABLE reservations OWNER TO "mlpvc-rr";

--
-- Name: reservations_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE reservations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE reservations_id_seq OWNER TO "mlpvc-rr";

--
-- Name: reservations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE reservations_id_seq OWNED BY reservations.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE sessions (
    id integer NOT NULL,
    "user" uuid NOT NULL,
    platform character varying(50) NOT NULL,
    browser_name character varying(50),
    browser_ver character varying(50),
    user_agent character varying(300),
    token character varying(40) NOT NULL,
    access character varying(50) NOT NULL,
    refresh character varying(40) NOT NULL,
    expires timestamp with time zone,
    created timestamp with time zone DEFAULT now() NOT NULL,
    lastvisit timestamp with time zone DEFAULT now() NOT NULL,
    scope character varying(50) DEFAULT 'user browse'::character varying NOT NULL
);


ALTER TABLE sessions OWNER TO "mlpvc-rr";

--
-- Name: sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE sessions_id_seq OWNER TO "mlpvc-rr";

--
-- Name: sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE sessions_id_seq OWNED BY sessions.id;


--
-- Name: usefullinks; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE usefullinks (
    id integer NOT NULL,
    url character varying(255) NOT NULL,
    label character varying(40) NOT NULL,
    title character varying(255) NOT NULL,
    minrole character varying(10) DEFAULT 'user'::character varying NOT NULL,
    "order" integer
);


ALTER TABLE usefullinks OWNER TO "mlpvc-rr";

--
-- Name: usefullinks_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE usefullinks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE usefullinks_id_seq OWNER TO "mlpvc-rr";

--
-- Name: usefullinks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE usefullinks_id_seq OWNED BY usefullinks.id;


--
-- Name: user_prefs; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE user_prefs (
    "user" uuid NOT NULL,
    key character varying(50) NOT NULL,
    value text
);


ALTER TABLE user_prefs OWNER TO "mlpvc-rr";

--
-- Name: users; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE users (
    id uuid NOT NULL,
    name citext NOT NULL,
    role character varying(10) DEFAULT 'user'::character varying NOT NULL,
    avatar_url character varying(255) NOT NULL,
    signup_date timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE users OWNER TO "mlpvc-rr";

--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log ALTER COLUMN entryid SET DEFAULT nextval('log_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearance_modify ALTER COLUMN entryid SET DEFAULT nextval('log_appearance_modify_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearances ALTER COLUMN entryid SET DEFAULT nextval('log__appearances_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish ALTER COLUMN entryid SET DEFAULT nextval('log__banish_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_modify ALTER COLUMN entryid SET DEFAULT nextval('log__cg_modify_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_order ALTER COLUMN entryid SET DEFAULT nextval('log__cg_order_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cgs ALTER COLUMN entryid SET DEFAULT nextval('log__cgs_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__color_modify ALTER COLUMN entryid SET DEFAULT nextval('log__color_modify_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange ALTER COLUMN entryid SET DEFAULT nextval('log__da_namechange_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episode_modify ALTER COLUMN entryid SET DEFAULT nextval('log__episode_modify_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episodes ALTER COLUMN entryid SET DEFAULT nextval('log__episodes_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__img_update ALTER COLUMN entryid SET DEFAULT nextval('log__img_update_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_lock ALTER COLUMN entryid SET DEFAULT nextval('log__post_lock_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__req_delete ALTER COLUMN entryid SET DEFAULT nextval('log__req_delete_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_overtake ALTER COLUMN entryid SET DEFAULT nextval('log__res_overtake_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_transfer ALTER COLUMN entryid SET DEFAULT nextval('log__res_transfer_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__rolechange ALTER COLUMN entryid SET DEFAULT nextval('log__rolechange_entryid_seq'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "log__un-banish" ALTER COLUMN entryid SET DEFAULT nextval('"log__un-banish_entryid_seq"'::regclass);


--
-- Name: entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__userfetch ALTER COLUMN entryid SET DEFAULT nextval('log__userfetch_entryid_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY notifications ALTER COLUMN id SET DEFAULT nextval('notifications_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests ALTER COLUMN id SET DEFAULT nextval('requests_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations ALTER COLUMN id SET DEFAULT nextval('reservations_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions ALTER COLUMN id SET DEFAULT nextval('sessions_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY usefullinks ALTER COLUMN id SET DEFAULT nextval('usefullinks_id_seq'::regclass);


--
-- Name: deviation_cache_provider_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY deviation_cache
    ADD CONSTRAINT deviation_cache_provider_id PRIMARY KEY (provider, id);


--
-- Name: episodes__videos_season_episode_provider_part; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__videos
    ADD CONSTRAINT episodes__videos_season_episode_provider_part PRIMARY KEY (season, episode, provider, part);


--
-- Name: episodes__votes_season_episode_user; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__votes
    ADD CONSTRAINT episodes__votes_season_episode_user PRIMARY KEY (season, episode, "user");


--
-- Name: episodes_season_episode; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_season_episode PRIMARY KEY (season, episode);


--
-- Name: global_settings_key; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY global_settings
    ADD CONSTRAINT global_settings_key PRIMARY KEY (key);


--
-- Name: log__appearances_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearances
    ADD CONSTRAINT log__appearances_entryid PRIMARY KEY (entryid);


--
-- Name: log__banish_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish
    ADD CONSTRAINT log__banish_entryid PRIMARY KEY (entryid);


--
-- Name: log__cg_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_modify
    ADD CONSTRAINT log__cg_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log__cg_order_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_order
    ADD CONSTRAINT log__cg_order_entryid PRIMARY KEY (entryid);


--
-- Name: log__cgs_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cgs
    ADD CONSTRAINT log__cgs_entryid PRIMARY KEY (entryid);


--
-- Name: log__color_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__color_modify
    ADD CONSTRAINT log__color_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log__da_namechange_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange
    ADD CONSTRAINT log__da_namechange_entryid PRIMARY KEY (entryid);


--
-- Name: log__episode_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episode_modify
    ADD CONSTRAINT log__episode_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log__episodes_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episodes
    ADD CONSTRAINT log__episodes_entryid PRIMARY KEY (entryid);


--
-- Name: log__img_update_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__img_update
    ADD CONSTRAINT log__img_update_entryid PRIMARY KEY (entryid);


--
-- Name: log__post_lock_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_lock
    ADD CONSTRAINT log__post_lock_entryid PRIMARY KEY (entryid);


--
-- Name: log__req_delete_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__req_delete
    ADD CONSTRAINT log__req_delete_entryid PRIMARY KEY (entryid);


--
-- Name: log__res_overtake_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_overtake
    ADD CONSTRAINT log__res_overtake_entryid PRIMARY KEY (entryid);


--
-- Name: log__res_transfer_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_transfer
    ADD CONSTRAINT log__res_transfer_entryid PRIMARY KEY (entryid);


--
-- Name: log__rolechange_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__rolechange
    ADD CONSTRAINT log__rolechange_entryid PRIMARY KEY (entryid);


--
-- Name: log__un-banish_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "log__un-banish"
    ADD CONSTRAINT "log__un-banish_entryid" PRIMARY KEY (entryid);


--
-- Name: log__userfetch_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__userfetch
    ADD CONSTRAINT log__userfetch_entryid PRIMARY KEY (entryid);


--
-- Name: log_appearance_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearance_modify
    ADD CONSTRAINT log_appearance_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log
    ADD CONSTRAINT log_entryid PRIMARY KEY (entryid);


--
-- Name: notifications_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY notifications
    ADD CONSTRAINT notifications_id PRIMARY KEY (id);


--
-- Name: requests_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_id PRIMARY KEY (id);


--
-- Name: reservations_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_id PRIMARY KEY (id);


--
-- Name: sessions_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_id PRIMARY KEY (id);


--
-- Name: usefullinks_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY usefullinks
    ADD CONSTRAINT usefullinks_id PRIMARY KEY (id);


--
-- Name: user_prefs_user_key; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY user_prefs
    ADD CONSTRAINT user_prefs_user_key PRIMARY KEY ("user", key);


--
-- Name: users_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_id PRIMARY KEY (id);


--
-- Name: episodes__votes_user; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX episodes__votes_user ON episodes__votes USING btree ("user");


--
-- Name: episodes_posted_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX episodes_posted_by ON episodes USING btree (posted_by);


--
-- Name: log__banish_target; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__banish_target ON log__banish USING btree (target);


--
-- Name: log__da_namechange_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__da_namechange_id ON log__da_namechange USING btree (id);


--
-- Name: log__rolechange_target; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__rolechange_target ON log__rolechange USING btree (target);


--
-- Name: log__un-banish_target; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX "log__un-banish_target" ON "log__un-banish" USING btree (target);


--
-- Name: log__userfetch_userid; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__userfetch_userid ON log__userfetch USING btree (userid);


--
-- Name: log_initiator; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log_initiator ON log USING btree (initiator);


--
-- Name: requests_requested_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX requests_requested_by ON requests USING btree (requested_by);


--
-- Name: requests_reserved_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX requests_reserved_by ON requests USING btree (reserved_by);


--
-- Name: requests_season_episode; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX requests_season_episode ON requests USING btree (season, episode);


--
-- Name: reservations_reserved_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX reservations_reserved_by ON reservations USING btree (reserved_by);


--
-- Name: reservations_season_episode; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX reservations_season_episode ON reservations USING btree (season, episode);


--
-- Name: sessions_user; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX sessions_user ON sessions USING btree ("user");


--
-- Name: usefullinks_minrole; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX usefullinks_minrole ON usefullinks USING btree (minrole);


--
-- Name: episodes__votes_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__votes
    ADD CONSTRAINT episodes__votes_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: episodes__votes_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__votes
    ADD CONSTRAINT episodes__votes_user_fkey FOREIGN KEY ("user") REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: episodes_posted_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_posted_by_fkey FOREIGN KEY (posted_by) REFERENCES users(id) ON UPDATE SET NULL ON DELETE SET NULL;


--
-- Name: log__banish_target_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish
    ADD CONSTRAINT log__banish_target_fkey FOREIGN KEY (target) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: log__da_namechange_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange
    ADD CONSTRAINT log__da_namechange_id_fkey FOREIGN KEY (id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: log__rolechange_target_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__rolechange
    ADD CONSTRAINT log__rolechange_target_fkey FOREIGN KEY (target) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__un-banish_target_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "log__un-banish"
    ADD CONSTRAINT "log__un-banish_target_fkey" FOREIGN KEY (target) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__userfetch_userid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__userfetch
    ADD CONSTRAINT log__userfetch_userid_fkey FOREIGN KEY (userid) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log_initiator_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log
    ADD CONSTRAINT log_initiator_fkey FOREIGN KEY (initiator) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: requests_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: requests_reserved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_reserved_by_fkey FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: requests_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: reservations_reserved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_reserved_by_fkey FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: reservations_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sessions_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_user_fkey FOREIGN KEY ("user") REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: user_prefs_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY user_prefs
    ADD CONSTRAINT user_prefs_user_fkey FOREIGN KEY ("user") REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;
GRANT USAGE ON SCHEMA public TO "mlpvc-rr";


--
-- Name: deviation_cache; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE deviation_cache FROM PUBLIC;
REVOKE ALL ON TABLE deviation_cache FROM "mlpvc-rr";
GRANT ALL ON TABLE deviation_cache TO "mlpvc-rr";
GRANT ALL ON TABLE deviation_cache TO postgres;


--
-- Name: episodes; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE episodes FROM PUBLIC;
REVOKE ALL ON TABLE episodes FROM "mlpvc-rr";
GRANT ALL ON TABLE episodes TO "mlpvc-rr";
GRANT ALL ON TABLE episodes TO postgres;


--
-- Name: episodes__videos; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE episodes__videos FROM PUBLIC;
REVOKE ALL ON TABLE episodes__videos FROM "mlpvc-rr";
GRANT ALL ON TABLE episodes__videos TO "mlpvc-rr";
GRANT ALL ON TABLE episodes__videos TO postgres;


--
-- Name: episodes__votes; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE episodes__votes FROM PUBLIC;
REVOKE ALL ON TABLE episodes__votes FROM "mlpvc-rr";
GRANT ALL ON TABLE episodes__votes TO "mlpvc-rr";
GRANT ALL ON TABLE episodes__votes TO postgres;


--
-- Name: global_settings; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE global_settings FROM PUBLIC;
REVOKE ALL ON TABLE global_settings FROM "mlpvc-rr";
GRANT ALL ON TABLE global_settings TO "mlpvc-rr";


--
-- Name: log; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log FROM PUBLIC;
REVOKE ALL ON TABLE log FROM "mlpvc-rr";
GRANT ALL ON TABLE log TO "mlpvc-rr";
GRANT ALL ON TABLE log TO postgres;


--
-- Name: log__appearance_modify; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__appearance_modify FROM PUBLIC;
REVOKE ALL ON TABLE log__appearance_modify FROM "mlpvc-rr";
GRANT ALL ON TABLE log__appearance_modify TO "mlpvc-rr";


--
-- Name: log__appearances; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__appearances FROM PUBLIC;
REVOKE ALL ON TABLE log__appearances FROM "mlpvc-rr";
GRANT ALL ON TABLE log__appearances TO "mlpvc-rr";


--
-- Name: log__appearances_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__appearances_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__appearances_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__appearances_entryid_seq TO "mlpvc-rr";


--
-- Name: log__banish; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__banish FROM PUBLIC;
REVOKE ALL ON TABLE log__banish FROM "mlpvc-rr";
GRANT ALL ON TABLE log__banish TO "mlpvc-rr";
GRANT ALL ON TABLE log__banish TO postgres;


--
-- Name: log__banish_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__banish_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__banish_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__banish_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__banish_entryid_seq TO postgres;


--
-- Name: log__cg_modify; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__cg_modify FROM PUBLIC;
REVOKE ALL ON TABLE log__cg_modify FROM "mlpvc-rr";
GRANT ALL ON TABLE log__cg_modify TO "mlpvc-rr";


--
-- Name: log__cg_modify_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__cg_modify_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__cg_modify_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__cg_modify_entryid_seq TO "mlpvc-rr";


--
-- Name: log__cg_order; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__cg_order FROM PUBLIC;
REVOKE ALL ON TABLE log__cg_order FROM "mlpvc-rr";
GRANT ALL ON TABLE log__cg_order TO "mlpvc-rr";


--
-- Name: log__cg_order_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__cg_order_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__cg_order_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__cg_order_entryid_seq TO "mlpvc-rr";


--
-- Name: log__cgs; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__cgs FROM PUBLIC;
REVOKE ALL ON TABLE log__cgs FROM "mlpvc-rr";
GRANT ALL ON TABLE log__cgs TO "mlpvc-rr";


--
-- Name: log__cgs_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__cgs_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__cgs_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__cgs_entryid_seq TO "mlpvc-rr";


--
-- Name: log__color_modify; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__color_modify FROM PUBLIC;
REVOKE ALL ON TABLE log__color_modify FROM "mlpvc-rr";
GRANT ALL ON TABLE log__color_modify TO "mlpvc-rr";
GRANT ALL ON TABLE log__color_modify TO postgres;


--
-- Name: log__color_modify_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__color_modify_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__color_modify_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__color_modify_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__color_modify_entryid_seq TO postgres;


--
-- Name: log__da_namechange; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__da_namechange FROM PUBLIC;
REVOKE ALL ON TABLE log__da_namechange FROM "mlpvc-rr";
GRANT ALL ON TABLE log__da_namechange TO "mlpvc-rr";


--
-- Name: log__da_namechange_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__da_namechange_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__da_namechange_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__da_namechange_entryid_seq TO "mlpvc-rr";


--
-- Name: log__episode_modify; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__episode_modify FROM PUBLIC;
REVOKE ALL ON TABLE log__episode_modify FROM "mlpvc-rr";
GRANT ALL ON TABLE log__episode_modify TO "mlpvc-rr";
GRANT ALL ON TABLE log__episode_modify TO postgres;


--
-- Name: log__episodes; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__episodes FROM PUBLIC;
REVOKE ALL ON TABLE log__episodes FROM "mlpvc-rr";
GRANT ALL ON TABLE log__episodes TO "mlpvc-rr";
GRANT ALL ON TABLE log__episodes TO postgres;


--
-- Name: log__episodes_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__episodes_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__episodes_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__episodes_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__episodes_entryid_seq TO postgres;


--
-- Name: log__img_update; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__img_update FROM PUBLIC;
REVOKE ALL ON TABLE log__img_update FROM "mlpvc-rr";
GRANT ALL ON TABLE log__img_update TO "mlpvc-rr";
GRANT ALL ON TABLE log__img_update TO postgres;


--
-- Name: log__img_update_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__img_update_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__img_update_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__img_update_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__img_update_entryid_seq TO postgres;


--
-- Name: log__post_lock; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__post_lock FROM PUBLIC;
REVOKE ALL ON TABLE log__post_lock FROM "mlpvc-rr";
GRANT ALL ON TABLE log__post_lock TO "mlpvc-rr";
GRANT ALL ON TABLE log__post_lock TO postgres;


--
-- Name: log__post_lock_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__post_lock_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__post_lock_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__post_lock_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__post_lock_entryid_seq TO postgres;


--
-- Name: log__req_delete; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__req_delete FROM PUBLIC;
REVOKE ALL ON TABLE log__req_delete FROM "mlpvc-rr";
GRANT ALL ON TABLE log__req_delete TO "mlpvc-rr";
GRANT ALL ON TABLE log__req_delete TO postgres;


--
-- Name: log__req_delete_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__req_delete_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__req_delete_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__req_delete_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__req_delete_entryid_seq TO postgres;


--
-- Name: log__res_overtake; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__res_overtake FROM PUBLIC;
REVOKE ALL ON TABLE log__res_overtake FROM "mlpvc-rr";
GRANT ALL ON TABLE log__res_overtake TO "mlpvc-rr";


--
-- Name: log__res_overtake_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__res_overtake_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__res_overtake_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__res_overtake_entryid_seq TO "mlpvc-rr";


--
-- Name: log__res_transfer; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__res_transfer FROM PUBLIC;
REVOKE ALL ON TABLE log__res_transfer FROM "mlpvc-rr";
GRANT ALL ON TABLE log__res_transfer TO "mlpvc-rr";


--
-- Name: log__res_transfer_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__res_transfer_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__res_transfer_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__res_transfer_entryid_seq TO "mlpvc-rr";


--
-- Name: log__rolechange; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__rolechange FROM PUBLIC;
REVOKE ALL ON TABLE log__rolechange FROM "mlpvc-rr";
GRANT ALL ON TABLE log__rolechange TO "mlpvc-rr";
GRANT ALL ON TABLE log__rolechange TO postgres;


--
-- Name: log__rolechange_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__rolechange_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__rolechange_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__rolechange_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__rolechange_entryid_seq TO postgres;


--
-- Name: log__un-banish; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE "log__un-banish" FROM PUBLIC;
REVOKE ALL ON TABLE "log__un-banish" FROM "mlpvc-rr";
GRANT ALL ON TABLE "log__un-banish" TO "mlpvc-rr";
GRANT ALL ON TABLE "log__un-banish" TO postgres;


--
-- Name: log__un-banish_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE "log__un-banish_entryid_seq" FROM PUBLIC;
REVOKE ALL ON SEQUENCE "log__un-banish_entryid_seq" FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE "log__un-banish_entryid_seq" TO "mlpvc-rr";
GRANT ALL ON SEQUENCE "log__un-banish_entryid_seq" TO postgres;


--
-- Name: log__userfetch; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE log__userfetch FROM PUBLIC;
REVOKE ALL ON TABLE log__userfetch FROM "mlpvc-rr";
GRANT ALL ON TABLE log__userfetch TO "mlpvc-rr";
GRANT ALL ON TABLE log__userfetch TO postgres;


--
-- Name: log__userfetch_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log__userfetch_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log__userfetch_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log__userfetch_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log__userfetch_entryid_seq TO postgres;


--
-- Name: log_appearance_modify_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log_appearance_modify_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log_appearance_modify_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log_appearance_modify_entryid_seq TO "mlpvc-rr";


--
-- Name: log_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE log_entryid_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE log_entryid_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE log_entryid_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE log_entryid_seq TO postgres;


--
-- Name: notifications; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE notifications FROM PUBLIC;
REVOKE ALL ON TABLE notifications FROM "mlpvc-rr";
GRANT ALL ON TABLE notifications TO "mlpvc-rr";


--
-- Name: notifications_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE notifications_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE notifications_id_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE notifications_id_seq TO "mlpvc-rr";


--
-- Name: requests; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE requests FROM PUBLIC;
REVOKE ALL ON TABLE requests FROM "mlpvc-rr";
GRANT ALL ON TABLE requests TO "mlpvc-rr";
GRANT ALL ON TABLE requests TO postgres;


--
-- Name: requests_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE requests_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE requests_id_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE requests_id_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE requests_id_seq TO postgres;


--
-- Name: reservations; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE reservations FROM PUBLIC;
REVOKE ALL ON TABLE reservations FROM "mlpvc-rr";
GRANT ALL ON TABLE reservations TO "mlpvc-rr";
GRANT ALL ON TABLE reservations TO postgres;


--
-- Name: reservations_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE reservations_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE reservations_id_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE reservations_id_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE reservations_id_seq TO postgres;


--
-- Name: sessions; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE sessions FROM PUBLIC;
REVOKE ALL ON TABLE sessions FROM "mlpvc-rr";
GRANT ALL ON TABLE sessions TO "mlpvc-rr";
GRANT ALL ON TABLE sessions TO postgres;


--
-- Name: sessions_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE sessions_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE sessions_id_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE sessions_id_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE sessions_id_seq TO postgres;


--
-- Name: usefullinks; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE usefullinks FROM PUBLIC;
REVOKE ALL ON TABLE usefullinks FROM "mlpvc-rr";
GRANT ALL ON TABLE usefullinks TO "mlpvc-rr";
GRANT ALL ON TABLE usefullinks TO postgres;


--
-- Name: usefullinks_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON SEQUENCE usefullinks_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE usefullinks_id_seq FROM "mlpvc-rr";
GRANT ALL ON SEQUENCE usefullinks_id_seq TO "mlpvc-rr";
GRANT ALL ON SEQUENCE usefullinks_id_seq TO postgres;


--
-- Name: user_prefs; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE user_prefs FROM PUBLIC;
REVOKE ALL ON TABLE user_prefs FROM "mlpvc-rr";
GRANT ALL ON TABLE user_prefs TO "mlpvc-rr";


--
-- Name: users; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE users FROM PUBLIC;
REVOKE ALL ON TABLE users FROM "mlpvc-rr";
GRANT ALL ON TABLE users TO "mlpvc-rr";
GRANT ALL ON TABLE users TO postgres;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON SEQUENCES  FROM PUBLIC;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON SEQUENCES  FROM postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT SELECT,USAGE ON SEQUENCES  TO "mlpvc-rr";


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON TABLES  FROM PUBLIC;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON TABLES  FROM postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT SELECT ON TABLES  TO "mlpvc-rr";


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: mlpvc-rr
--

ALTER DEFAULT PRIVILEGES FOR ROLE "mlpvc-rr" IN SCHEMA public REVOKE ALL ON TABLES  FROM PUBLIC;
ALTER DEFAULT PRIVILEGES FOR ROLE "mlpvc-rr" IN SCHEMA public REVOKE ALL ON TABLES  FROM "mlpvc-rr";
ALTER DEFAULT PRIVILEGES FOR ROLE "mlpvc-rr" IN SCHEMA public GRANT SELECT,INSERT,DELETE,UPDATE ON TABLES  TO "mlpvc-rr";


--
-- PostgreSQL database dump complete
--

