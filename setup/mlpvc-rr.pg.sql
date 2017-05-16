--
-- PostgreSQL database dump
--

-- Dumped from database version 9.6.3
-- Dumped by pg_dump version 9.6.3

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
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
-- Name: appearance_relations; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE appearance_relations (
    source integer NOT NULL,
    target integer NOT NULL,
    mutual boolean DEFAULT false NOT NULL
);


ALTER TABLE appearance_relations OWNER TO "mlpvc-rr";

--
-- Name: appearances; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE appearances (
    id integer NOT NULL,
    "order" integer NOT NULL,
    label character varying(70) NOT NULL,
    notes text,
    ishuman boolean,
    added timestamp with time zone DEFAULT now(),
    private boolean DEFAULT false NOT NULL,
    owner uuid,
    last_cleared timestamp with time zone DEFAULT now()
);


ALTER TABLE appearances OWNER TO "mlpvc-rr";

--
-- Name: appearances_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE appearances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE appearances_id_seq OWNER TO "mlpvc-rr";

--
-- Name: appearances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE appearances_id_seq OWNED BY appearances.id;


--
-- Name: cached-deviations; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE "cached-deviations" (
    provider character(6) NOT NULL,
    id character varying(20) NOT NULL,
    title character varying(255) NOT NULL,
    author character varying(20),
    preview character varying(255),
    fullsize character varying(255),
    updated_on timestamp with time zone DEFAULT now(),
    type character varying(12)
);


ALTER TABLE "cached-deviations" OWNER TO "mlpvc-rr";

--
-- Name: colorgroups; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE colorgroups (
    groupid integer NOT NULL,
    ponyid integer NOT NULL,
    label character varying(255) NOT NULL,
    "order" integer DEFAULT 0 NOT NULL
);


ALTER TABLE colorgroups OWNER TO "mlpvc-rr";

--
-- Name: colorgroups_groupid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE colorgroups_groupid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE colorgroups_groupid_seq OWNER TO "mlpvc-rr";

--
-- Name: colorgroups_groupid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE colorgroups_groupid_seq OWNED BY colorgroups.groupid;


--
-- Name: colors; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE colors (
    groupid integer NOT NULL,
    "order" integer,
    label character varying(255) NOT NULL,
    hex character(7),
    CONSTRAINT colors_hex_check CHECK ((hex ~* '^#[\da-f]{6}$'::text))
);


ALTER TABLE colors OWNER TO "mlpvc-rr";

--
-- Name: cutiemarks; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE cutiemarks (
    cmid integer NOT NULL,
    ponyid integer NOT NULL,
    facing character varying(10),
    favme character varying(7) NOT NULL,
    favme_rotation smallint NOT NULL,
    preview character varying(255),
    preview_src character varying(255),
    CONSTRAINT favme_rotation_constraint CHECK (((favme_rotation >= '-180'::integer) AND (favme_rotation <= 180)))
);


ALTER TABLE cutiemarks OWNER TO "mlpvc-rr";

--
-- Name: cutiemarks_cmid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE cutiemarks_cmid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE cutiemarks_cmid_seq OWNER TO "mlpvc-rr";

--
-- Name: cutiemarks_cmid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE cutiemarks_cmid_seq OWNED BY cutiemarks.cmid;


--
-- Name: discord-members; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE "discord-members" (
    id character varying(20) NOT NULL,
    userid uuid,
    username character varying(255) NOT NULL,
    discriminator integer NOT NULL,
    nick character varying(255),
    avatar_hash character varying(255),
    joined_at timestamp with time zone NOT NULL
);


ALTER TABLE "discord-members" OWNER TO "mlpvc-rr";

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
    no smallint,
    score double precision
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
    modified timestamp with time zone DEFAULT now(),
    not_broken_at timestamp with time zone
);


ALTER TABLE episodes__videos OWNER TO "mlpvc-rr";

--
-- Name: episodes__votes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE episodes__votes (
    season integer NOT NULL,
    episode integer NOT NULL,
    "user" uuid NOT NULL,
    vote smallint NOT NULL,
    CONSTRAINT episodes__votes_vote_check CHECK (((vote >= 1) AND (vote <= 5)))
);


ALTER TABLE episodes__votes OWNER TO "mlpvc-rr";

--
-- Name: events; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE events (
    id integer NOT NULL,
    name character varying(64) NOT NULL,
    type character varying(10) NOT NULL,
    entry_role character varying(15) NOT NULL,
    starts_at timestamp with time zone NOT NULL,
    ends_at timestamp with time zone NOT NULL,
    added_by uuid NOT NULL,
    added_at timestamp with time zone NOT NULL,
    desc_src text NOT NULL,
    desc_rend text NOT NULL,
    max_entries integer,
    vote_role character varying(15)
);


ALTER TABLE events OWNER TO "mlpvc-rr";

--
-- Name: events__entries; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE events__entries (
    entryid integer NOT NULL,
    eventid smallint NOT NULL,
    prev_full character varying(255),
    prev_thumb character varying(255),
    sub_prov character varying(20) NOT NULL,
    sub_id character varying(20) NOT NULL,
    submitted_by uuid NOT NULL,
    submitted_at timestamp with time zone DEFAULT now() NOT NULL,
    title character varying(64) NOT NULL,
    prev_src character varying(255),
    score integer,
    last_edited timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE events__entries OWNER TO "mlpvc-rr";

--
-- Name: events__entries__votes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE events__entries__votes (
    entryid integer NOT NULL,
    userid uuid NOT NULL,
    value smallint NOT NULL,
    cast_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE events__entries__votes OWNER TO "mlpvc-rr";

--
-- Name: events__entries_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE events__entries_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE events__entries_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: events__entries_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE events__entries_entryid_seq OWNED BY events__entries.entryid;


--
-- Name: events_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE events_id_seq OWNER TO "mlpvc-rr";

--
-- Name: events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE events_id_seq OWNED BY events.id;


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
    notes text,
    cm_favme character varying(20),
    ishuman boolean,
    added timestamp with time zone,
    cm_preview character varying(255),
    cm_dir boolean,
    usetemplate boolean DEFAULT false NOT NULL,
    private boolean DEFAULT false NOT NULL,
    owner uuid
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
-- Name: log__cm_delete; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__cm_delete (
    entryid integer NOT NULL,
    ponyid integer NOT NULL,
    data jsonb NOT NULL
);


ALTER TABLE log__cm_delete OWNER TO "mlpvc-rr";

--
-- Name: log__cm_delete_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__cm_delete_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__cm_delete_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__cm_delete_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__cm_delete_entryid_seq OWNED BY log__cm_delete.entryid;


--
-- Name: log__cm_modify; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__cm_modify (
    entryid integer NOT NULL,
    ponyid integer NOT NULL,
    olddata jsonb NOT NULL,
    newdata jsonb NOT NULL
);


ALTER TABLE log__cm_modify OWNER TO "mlpvc-rr";

--
-- Name: log__cm_modify_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__cm_modify_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__cm_modify_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__cm_modify_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__cm_modify_entryid_seq OWNED BY log__cm_modify.entryid;


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
-- Name: log__post_break; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__post_break (
    entryid integer NOT NULL,
    type character varying(11) NOT NULL,
    id integer NOT NULL,
    reserved_by uuid
);


ALTER TABLE log__post_break OWNER TO "mlpvc-rr";

--
-- Name: log__post_break_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__post_break_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__post_break_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__post_break_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__post_break_entryid_seq OWNED BY log__post_break.entryid;


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
-- Name: log__video_broken; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__video_broken (
    entryid integer NOT NULL,
    season integer NOT NULL,
    episode integer NOT NULL,
    provider character(2) NOT NULL,
    id character varying(15) NOT NULL
);


ALTER TABLE log__video_broken OWNER TO "mlpvc-rr";

--
-- Name: log__video_broken_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__video_broken_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__video_broken_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__video_broken_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__video_broken_entryid_seq OWNED BY log__video_broken.entryid;


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
-- Name: personal_cg_appearances; Type: VIEW; Schema: public; Owner: mlpvc-rr
--

CREATE VIEW personal_cg_appearances AS
 SELECT u.name AS owner,
    p.label,
    ((('https://mlpvc-rr.ml/@'::text || (u.name)::text) || '/cg/v/'::text) || p.id) AS link
   FROM (appearances p
     LEFT JOIN users u ON ((p.owner = u.id)))
  WHERE (p.owner IS NOT NULL);


ALTER TABLE personal_cg_appearances OWNER TO "mlpvc-rr";

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
    finished_at timestamp with time zone,
    broken boolean DEFAULT false NOT NULL
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
    finished_at timestamp with time zone,
    broken boolean DEFAULT false NOT NULL
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
    token character varying(64) NOT NULL,
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
-- Name: tagged; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE tagged (
    tid integer NOT NULL,
    ponyid integer NOT NULL
);


ALTER TABLE tagged OWNER TO "mlpvc-rr";

--
-- Name: tags; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE tags (
    tid integer NOT NULL,
    name character varying(30) NOT NULL,
    title character varying(255),
    type character varying(4),
    uses integer DEFAULT 0 NOT NULL,
    synonym_of integer
);


ALTER TABLE tags OWNER TO "mlpvc-rr";

--
-- Name: tags_tid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE tags_tid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE tags_tid_seq OWNER TO "mlpvc-rr";

--
-- Name: tags_tid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE tags_tid_seq OWNED BY tags.tid;


--
-- Name: unread_notifications; Type: VIEW; Schema: public; Owner: mlpvc-rr
--

CREATE VIEW unread_notifications AS
 SELECT u.name AS "user",
    count(n.id) AS count
   FROM (notifications n
     LEFT JOIN users u ON ((n."user" = u.id)))
  WHERE (n.read_at IS NULL)
  GROUP BY u.name
  ORDER BY (count(n.id)) DESC;


ALTER TABLE unread_notifications OWNER TO "mlpvc-rr";

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
-- Name: appearances id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearances ALTER COLUMN id SET DEFAULT nextval('appearances_id_seq'::regclass);


--
-- Name: colorgroups groupid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colorgroups ALTER COLUMN groupid SET DEFAULT nextval('colorgroups_groupid_seq'::regclass);


--
-- Name: cutiemarks cmid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cutiemarks ALTER COLUMN cmid SET DEFAULT nextval('cutiemarks_cmid_seq'::regclass);


--
-- Name: events id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events ALTER COLUMN id SET DEFAULT nextval('events_id_seq'::regclass);


--
-- Name: events__entries entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events__entries ALTER COLUMN entryid SET DEFAULT nextval('events__entries_entryid_seq'::regclass);


--
-- Name: log entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log ALTER COLUMN entryid SET DEFAULT nextval('log_entryid_seq'::regclass);


--
-- Name: log__appearance_modify entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearance_modify ALTER COLUMN entryid SET DEFAULT nextval('log_appearance_modify_entryid_seq'::regclass);


--
-- Name: log__appearances entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearances ALTER COLUMN entryid SET DEFAULT nextval('log__appearances_entryid_seq'::regclass);


--
-- Name: log__banish entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish ALTER COLUMN entryid SET DEFAULT nextval('log__banish_entryid_seq'::regclass);


--
-- Name: log__cg_modify entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_modify ALTER COLUMN entryid SET DEFAULT nextval('log__cg_modify_entryid_seq'::regclass);


--
-- Name: log__cg_order entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_order ALTER COLUMN entryid SET DEFAULT nextval('log__cg_order_entryid_seq'::regclass);


--
-- Name: log__cgs entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cgs ALTER COLUMN entryid SET DEFAULT nextval('log__cgs_entryid_seq'::regclass);


--
-- Name: log__cm_delete entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cm_delete ALTER COLUMN entryid SET DEFAULT nextval('log__cm_delete_entryid_seq'::regclass);


--
-- Name: log__cm_modify entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cm_modify ALTER COLUMN entryid SET DEFAULT nextval('log__cm_modify_entryid_seq'::regclass);


--
-- Name: log__color_modify entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__color_modify ALTER COLUMN entryid SET DEFAULT nextval('log__color_modify_entryid_seq'::regclass);


--
-- Name: log__da_namechange entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange ALTER COLUMN entryid SET DEFAULT nextval('log__da_namechange_entryid_seq'::regclass);


--
-- Name: log__episode_modify entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episode_modify ALTER COLUMN entryid SET DEFAULT nextval('log__episode_modify_entryid_seq'::regclass);


--
-- Name: log__episodes entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episodes ALTER COLUMN entryid SET DEFAULT nextval('log__episodes_entryid_seq'::regclass);


--
-- Name: log__img_update entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__img_update ALTER COLUMN entryid SET DEFAULT nextval('log__img_update_entryid_seq'::regclass);


--
-- Name: log__post_break entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_break ALTER COLUMN entryid SET DEFAULT nextval('log__post_break_entryid_seq'::regclass);


--
-- Name: log__post_lock entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_lock ALTER COLUMN entryid SET DEFAULT nextval('log__post_lock_entryid_seq'::regclass);


--
-- Name: log__req_delete entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__req_delete ALTER COLUMN entryid SET DEFAULT nextval('log__req_delete_entryid_seq'::regclass);


--
-- Name: log__res_overtake entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_overtake ALTER COLUMN entryid SET DEFAULT nextval('log__res_overtake_entryid_seq'::regclass);


--
-- Name: log__res_transfer entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_transfer ALTER COLUMN entryid SET DEFAULT nextval('log__res_transfer_entryid_seq'::regclass);


--
-- Name: log__rolechange entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__rolechange ALTER COLUMN entryid SET DEFAULT nextval('log__rolechange_entryid_seq'::regclass);


--
-- Name: log__un-banish entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "log__un-banish" ALTER COLUMN entryid SET DEFAULT nextval('"log__un-banish_entryid_seq"'::regclass);


--
-- Name: log__userfetch entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__userfetch ALTER COLUMN entryid SET DEFAULT nextval('log__userfetch_entryid_seq'::regclass);


--
-- Name: log__video_broken entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__video_broken ALTER COLUMN entryid SET DEFAULT nextval('log__video_broken_entryid_seq'::regclass);


--
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY notifications ALTER COLUMN id SET DEFAULT nextval('notifications_id_seq'::regclass);


--
-- Name: requests id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests ALTER COLUMN id SET DEFAULT nextval('requests_id_seq'::regclass);


--
-- Name: reservations id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations ALTER COLUMN id SET DEFAULT nextval('reservations_id_seq'::regclass);


--
-- Name: sessions id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions ALTER COLUMN id SET DEFAULT nextval('sessions_id_seq'::regclass);


--
-- Name: tags tid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tags ALTER COLUMN tid SET DEFAULT nextval('tags_tid_seq'::regclass);


--
-- Name: usefullinks id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY usefullinks ALTER COLUMN id SET DEFAULT nextval('usefullinks_id_seq'::regclass);


--
-- Name: appearance_relations appearance_relations_source_target; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearance_relations
    ADD CONSTRAINT appearance_relations_source_target UNIQUE (source, target);


--
-- Name: appearances appearances_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearances
    ADD CONSTRAINT appearances_id PRIMARY KEY (id);


--
-- Name: colorgroups colorgroups_groupid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colorgroups
    ADD CONSTRAINT colorgroups_groupid PRIMARY KEY (groupid);


--
-- Name: colorgroups colorgroups_groupid_label; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colorgroups
    ADD CONSTRAINT colorgroups_groupid_label UNIQUE (groupid, label);


--
-- Name: cutiemarks cutiemarks_cmid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cutiemarks
    ADD CONSTRAINT cutiemarks_cmid PRIMARY KEY (cmid);


--
-- Name: cutiemarks cutiemarks_ponyid_facing; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cutiemarks
    ADD CONSTRAINT cutiemarks_ponyid_facing UNIQUE (ponyid, facing);


--
-- Name: cached-deviations deviation_cache_provider_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "cached-deviations"
    ADD CONSTRAINT deviation_cache_provider_id PRIMARY KEY (provider, id);


--
-- Name: discord-members discord_members_discid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "discord-members"
    ADD CONSTRAINT discord_members_discid PRIMARY KEY (id);


--
-- Name: discord-members discord_members_userid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "discord-members"
    ADD CONSTRAINT discord_members_userid UNIQUE (userid);


--
-- Name: episodes__videos episodes__videos_season_episode_provider_part; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__videos
    ADD CONSTRAINT episodes__videos_season_episode_provider_part PRIMARY KEY (season, episode, provider, part);


--
-- Name: episodes__votes episodes__votes_season_episode_user; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__votes
    ADD CONSTRAINT episodes__votes_season_episode_user PRIMARY KEY (season, episode, "user");


--
-- Name: episodes episodes_season_episode; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_season_episode PRIMARY KEY (season, episode);


--
-- Name: events__entries events__entries_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events__entries
    ADD CONSTRAINT events__entries_entryid PRIMARY KEY (entryid);


--
-- Name: events__entries__votes events__votes_entryid_userid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events__entries__votes
    ADD CONSTRAINT events__votes_entryid_userid PRIMARY KEY (entryid, userid);


--
-- Name: events events_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events
    ADD CONSTRAINT events_id PRIMARY KEY (id);


--
-- Name: global_settings global_settings_key; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY global_settings
    ADD CONSTRAINT global_settings_key PRIMARY KEY (key);


--
-- Name: log__appearances log__appearances_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearances
    ADD CONSTRAINT log__appearances_entryid PRIMARY KEY (entryid);


--
-- Name: log__banish log__banish_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish
    ADD CONSTRAINT log__banish_entryid PRIMARY KEY (entryid);


--
-- Name: log__cg_modify log__cg_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_modify
    ADD CONSTRAINT log__cg_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log__cg_order log__cg_order_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_order
    ADD CONSTRAINT log__cg_order_entryid PRIMARY KEY (entryid);


--
-- Name: log__cgs log__cgs_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cgs
    ADD CONSTRAINT log__cgs_entryid PRIMARY KEY (entryid);


--
-- Name: log__cm_modify log__cm_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cm_modify
    ADD CONSTRAINT log__cm_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log__color_modify log__color_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__color_modify
    ADD CONSTRAINT log__color_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log__da_namechange log__da_namechange_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange
    ADD CONSTRAINT log__da_namechange_entryid PRIMARY KEY (entryid);


--
-- Name: log__episode_modify log__episode_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episode_modify
    ADD CONSTRAINT log__episode_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log__episodes log__episodes_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episodes
    ADD CONSTRAINT log__episodes_entryid PRIMARY KEY (entryid);


--
-- Name: log__img_update log__img_update_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__img_update
    ADD CONSTRAINT log__img_update_entryid PRIMARY KEY (entryid);


--
-- Name: log__post_break log__post_break_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_break
    ADD CONSTRAINT log__post_break_entryid PRIMARY KEY (entryid);


--
-- Name: log__post_lock log__post_lock_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_lock
    ADD CONSTRAINT log__post_lock_entryid PRIMARY KEY (entryid);


--
-- Name: log__req_delete log__req_delete_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__req_delete
    ADD CONSTRAINT log__req_delete_entryid PRIMARY KEY (entryid);


--
-- Name: log__res_overtake log__res_overtake_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_overtake
    ADD CONSTRAINT log__res_overtake_entryid PRIMARY KEY (entryid);


--
-- Name: log__res_transfer log__res_transfer_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_transfer
    ADD CONSTRAINT log__res_transfer_entryid PRIMARY KEY (entryid);


--
-- Name: log__rolechange log__rolechange_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__rolechange
    ADD CONSTRAINT log__rolechange_entryid PRIMARY KEY (entryid);


--
-- Name: log__un-banish log__un-banish_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "log__un-banish"
    ADD CONSTRAINT "log__un-banish_entryid" PRIMARY KEY (entryid);


--
-- Name: log__userfetch log__userfetch_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__userfetch
    ADD CONSTRAINT log__userfetch_entryid PRIMARY KEY (entryid);


--
-- Name: log__video_broken log__video_broken_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__video_broken
    ADD CONSTRAINT log__video_broken_entryid PRIMARY KEY (entryid);


--
-- Name: log__appearance_modify log_appearance_modify_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearance_modify
    ADD CONSTRAINT log_appearance_modify_entryid PRIMARY KEY (entryid);


--
-- Name: log log_entryid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log
    ADD CONSTRAINT log_entryid PRIMARY KEY (entryid);


--
-- Name: notifications notifications_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY notifications
    ADD CONSTRAINT notifications_id PRIMARY KEY (id);


--
-- Name: requests requests_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_id PRIMARY KEY (id);


--
-- Name: reservations reservations_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_id PRIMARY KEY (id);


--
-- Name: sessions sessions_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_id PRIMARY KEY (id);


--
-- Name: tagged tagged_tid_ponyid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tagged
    ADD CONSTRAINT tagged_tid_ponyid PRIMARY KEY (tid, ponyid);


--
-- Name: tags tags_tid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tags
    ADD CONSTRAINT tags_tid PRIMARY KEY (tid);


--
-- Name: usefullinks usefullinks_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY usefullinks
    ADD CONSTRAINT usefullinks_id PRIMARY KEY (id);


--
-- Name: user_prefs user_prefs_user_key; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY user_prefs
    ADD CONSTRAINT user_prefs_user_key PRIMARY KEY ("user", key);


--
-- Name: users users_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_id PRIMARY KEY (id);


--
-- Name: appearances_ishuman; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX appearances_ishuman ON appearances USING btree (ishuman);


--
-- Name: appearances_label; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX appearances_label ON appearances USING btree (label);


--
-- Name: appearances_order; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX appearances_order ON appearances USING btree ("order");


--
-- Name: colorgroups_ponyid; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX colorgroups_ponyid ON colorgroups USING btree (ponyid);


--
-- Name: colors_groupid; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX colors_groupid ON colors USING btree (groupid);


--
-- Name: episodes__votes_user; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX episodes__votes_user ON episodes__votes USING btree ("user");


--
-- Name: episodes_posted_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX episodes_posted_by ON episodes USING btree (posted_by);


--
-- Name: events__entries_eventid; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX events__entries_eventid ON events__entries USING btree (eventid);


--
-- Name: events__entries_score; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX events__entries_score ON events__entries USING btree (score);


--
-- Name: events__entries_submitted_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX events__entries_submitted_by ON events__entries USING btree (submitted_by);


--
-- Name: events_added_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX events_added_by ON events USING btree (added_by);


--
-- Name: log__banish_target; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__banish_target ON log__banish USING btree (target);


--
-- Name: log__da_namechange_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__da_namechange_id ON log__da_namechange USING btree (id);


--
-- Name: log__post_lock_type_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__post_lock_type_id ON log__post_lock USING btree (type, id);


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
-- Name: tags_synonym_of; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX tags_synonym_of ON tags USING btree (synonym_of);


--
-- Name: usefullinks_minrole; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX usefullinks_minrole ON usefullinks USING btree (minrole);


--
-- Name: appearance_relations appearance_relations_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearance_relations
    ADD CONSTRAINT appearance_relations_source_fkey FOREIGN KEY (source) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: appearance_relations appearance_relations_target_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearance_relations
    ADD CONSTRAINT appearance_relations_target_fkey FOREIGN KEY (target) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: appearances appearances_owner_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearances
    ADD CONSTRAINT appearances_owner_fkey FOREIGN KEY (owner) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: colorgroups colorgroups_ponyid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colorgroups
    ADD CONSTRAINT colorgroups_ponyid_fkey FOREIGN KEY (ponyid) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: colors colors_groupid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colors
    ADD CONSTRAINT colors_groupid_fkey FOREIGN KEY (groupid) REFERENCES colorgroups(groupid) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cutiemarks cutiemarks_ponyid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cutiemarks
    ADD CONSTRAINT cutiemarks_ponyid_fkey FOREIGN KEY (ponyid) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: discord-members discord_members_userid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "discord-members"
    ADD CONSTRAINT discord_members_userid_fkey FOREIGN KEY (userid) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: episodes__videos episodes__videos_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__videos
    ADD CONSTRAINT episodes__videos_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: episodes__votes episodes__votes_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__votes
    ADD CONSTRAINT episodes__votes_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: episodes__votes episodes__votes_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes__votes
    ADD CONSTRAINT episodes__votes_user_fkey FOREIGN KEY ("user") REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: episodes episodes_posted_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_posted_by_fkey FOREIGN KEY (posted_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: events__entries__votes events__entries__votes_entryid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events__entries__votes
    ADD CONSTRAINT events__entries__votes_entryid_fkey FOREIGN KEY (entryid) REFERENCES events__entries(entryid) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: events__entries events__entries_eventid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events__entries
    ADD CONSTRAINT events__entries_eventid_fkey FOREIGN KEY (eventid) REFERENCES events(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: events__entries events__entries_submitted_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events__entries
    ADD CONSTRAINT events__entries_submitted_by_fkey FOREIGN KEY (submitted_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: events events_added_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events
    ADD CONSTRAINT events_added_by_fkey FOREIGN KEY (added_by) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__banish log__banish_target_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish
    ADD CONSTRAINT log__banish_target_fkey FOREIGN KEY (target) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: log__da_namechange log__da_namechange_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange
    ADD CONSTRAINT log__da_namechange_id_fkey FOREIGN KEY (id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: log__post_break log__post_break_reserved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_break
    ADD CONSTRAINT log__post_break_reserved_by_fkey FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__res_overtake log__res_overtake_reserved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_overtake
    ADD CONSTRAINT log__res_overtake_reserved_by_fkey FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__res_transfer log__res_transfer_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_transfer
    ADD CONSTRAINT log__res_transfer_to_fkey FOREIGN KEY ("to") REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__rolechange log__rolechange_target_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__rolechange
    ADD CONSTRAINT log__rolechange_target_fkey FOREIGN KEY (target) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__un-banish log__un-banish_target_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY "log__un-banish"
    ADD CONSTRAINT "log__un-banish_target_fkey" FOREIGN KEY (target) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__userfetch log__userfetch_userid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__userfetch
    ADD CONSTRAINT log__userfetch_userid_fkey FOREIGN KEY (userid) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: log__video_broken log__video_broken_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__video_broken
    ADD CONSTRAINT log__video_broken_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE;


--
-- Name: log log_initiator_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log
    ADD CONSTRAINT log_initiator_fkey FOREIGN KEY (initiator) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: notifications notifications_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY notifications
    ADD CONSTRAINT notifications_user_fkey FOREIGN KEY ("user") REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: requests requests_requested_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_requested_by_fkey FOREIGN KEY (requested_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: requests requests_reserved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_reserved_by_fkey FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: requests requests_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: reservations reservations_reserved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_reserved_by_fkey FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: reservations reservations_season_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_season_fkey FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sessions sessions_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_user_fkey FOREIGN KEY ("user") REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tagged tagged_ponyid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tagged
    ADD CONSTRAINT tagged_ponyid_fkey FOREIGN KEY (ponyid) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tagged tagged_tid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tagged
    ADD CONSTRAINT tagged_tid_fkey FOREIGN KEY (tid) REFERENCES tags(tid) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tags tags_synonym_of_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tags
    ADD CONSTRAINT tags_synonym_of_fkey FOREIGN KEY (synonym_of) REFERENCES tags(tid) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: user_prefs user_prefs_user_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY user_prefs
    ADD CONSTRAINT user_prefs_user_fkey FOREIGN KEY ("user") REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

GRANT USAGE ON SCHEMA public TO "mlpvc-rr";


--
-- Name: appearances; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE appearances TO postgres;


--
-- Name: cached-deviations; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE "cached-deviations" TO postgres;


--
-- Name: colorgroups; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE colorgroups TO postgres;


--
-- Name: colors; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE colors TO postgres;


--
-- Name: episodes; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE episodes TO postgres;


--
-- Name: episodes__videos; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE episodes__videos TO postgres;


--
-- Name: episodes__votes; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE episodes__votes TO postgres;


--
-- Name: log; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log TO postgres;


--
-- Name: log__banish; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__banish TO postgres;


--
-- Name: log__banish_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__banish_entryid_seq TO postgres;


--
-- Name: log__color_modify; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__color_modify TO postgres;


--
-- Name: log__color_modify_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__color_modify_entryid_seq TO postgres;


--
-- Name: log__episode_modify; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__episode_modify TO postgres;


--
-- Name: log__episodes; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__episodes TO postgres;


--
-- Name: log__episodes_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__episodes_entryid_seq TO postgres;


--
-- Name: log__img_update; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__img_update TO postgres;


--
-- Name: log__img_update_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__img_update_entryid_seq TO postgres;


--
-- Name: log__post_lock; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__post_lock TO postgres;


--
-- Name: log__post_lock_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__post_lock_entryid_seq TO postgres;


--
-- Name: log__req_delete; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__req_delete TO postgres;


--
-- Name: log__req_delete_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__req_delete_entryid_seq TO postgres;


--
-- Name: log__rolechange; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__rolechange TO postgres;


--
-- Name: log__rolechange_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__rolechange_entryid_seq TO postgres;


--
-- Name: log__un-banish; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE "log__un-banish" TO postgres;


--
-- Name: log__un-banish_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE "log__un-banish_entryid_seq" TO postgres;


--
-- Name: log__userfetch; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE log__userfetch TO postgres;


--
-- Name: log__userfetch_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log__userfetch_entryid_seq TO postgres;


--
-- Name: log_entryid_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE log_entryid_seq TO postgres;


--
-- Name: users; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE users TO postgres;


--
-- Name: requests; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE requests TO postgres;


--
-- Name: requests_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE requests_id_seq TO postgres;


--
-- Name: reservations; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE reservations TO postgres;


--
-- Name: reservations_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE reservations_id_seq TO postgres;


--
-- Name: sessions; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE sessions TO postgres;


--
-- Name: sessions_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE sessions_id_seq TO postgres;


--
-- Name: tagged; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE tagged TO postgres;


--
-- Name: tags; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE tags TO postgres;


--
-- Name: usefullinks; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON TABLE usefullinks TO postgres;


--
-- Name: usefullinks_id_seq; Type: ACL; Schema: public; Owner: mlpvc-rr
--

GRANT ALL ON SEQUENCE usefullinks_id_seq TO postgres;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON SEQUENCES  FROM postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT SELECT,USAGE ON SEQUENCES  TO "mlpvc-rr";


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: postgres
--

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON TABLES  FROM postgres;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT SELECT ON TABLES  TO "mlpvc-rr";


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: mlpvc-rr
--

ALTER DEFAULT PRIVILEGES FOR ROLE "mlpvc-rr" IN SCHEMA public REVOKE ALL ON TABLES  FROM "mlpvc-rr";
ALTER DEFAULT PRIVILEGES FOR ROLE "mlpvc-rr" IN SCHEMA public GRANT SELECT,INSERT,DELETE,UPDATE ON TABLES  TO "mlpvc-rr";


--
-- PostgreSQL database dump complete
--

