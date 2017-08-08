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
-- Name: appearances; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE appearances (
    id integer NOT NULL,
    "order" integer,
    label character varying(70) NOT NULL,
    notes text,
    ishuman boolean,
    added timestamp with time zone DEFAULT now(),
    private boolean DEFAULT false NOT NULL,
    owner_id uuid,
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
-- Name: cached_deviations; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE cached_deviations (
    provider character(6) NOT NULL,
    id character varying(20) NOT NULL,
    title character varying(255) NOT NULL,
    author character varying(20),
    preview character varying(255),
    fullsize character varying(255),
    updated_on timestamp with time zone DEFAULT now(),
    type character varying(12)
);


ALTER TABLE cached_deviations OWNER TO "mlpvc-rr";

--
-- Name: color_groups; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE color_groups (
    id integer NOT NULL,
    appearance_id integer NOT NULL,
    label character varying(255) NOT NULL,
    "order" integer NOT NULL
);


ALTER TABLE color_groups OWNER TO "mlpvc-rr";

--
-- Name: color_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE color_groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE color_groups_id_seq OWNER TO "mlpvc-rr";

--
-- Name: color_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE color_groups_id_seq OWNED BY color_groups.id;


--
-- Name: colors; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE colors (
    group_id integer NOT NULL,
    "order" integer NOT NULL,
    label character varying(255) NOT NULL,
    hex character(7)
);


ALTER TABLE colors OWNER TO "mlpvc-rr";

--
-- Name: cutiemarks; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE cutiemarks (
    id integer NOT NULL,
    appearance_id integer NOT NULL,
    facing character varying(10),
    favme character varying(7) NOT NULL,
    favme_rotation smallint NOT NULL,
    preview character varying(255),
    preview_src character varying(255)
);


ALTER TABLE cutiemarks OWNER TO "mlpvc-rr";

--
-- Name: cutiemarks_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE cutiemarks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE cutiemarks_id_seq OWNER TO "mlpvc-rr";

--
-- Name: cutiemarks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE cutiemarks_id_seq OWNED BY cutiemarks.id;


--
-- Name: discord_members; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE discord_members (
    id character varying(20) NOT NULL,
    user_id uuid,
    username character varying(255) NOT NULL,
    discriminator integer NOT NULL,
    nick character varying(255),
    avatar_hash character varying(255),
    joined_at timestamp with time zone NOT NULL
);


ALTER TABLE discord_members OWNER TO "mlpvc-rr";

--
-- Name: episode_videos; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE episode_videos (
    season integer NOT NULL,
    episode integer NOT NULL,
    provider character(2) NOT NULL,
    id character varying(15) NOT NULL,
    part integer DEFAULT 1 NOT NULL,
    fullep boolean DEFAULT true NOT NULL,
    modified timestamp with time zone DEFAULT now(),
    not_broken_at timestamp with time zone
);


ALTER TABLE episode_videos OWNER TO "mlpvc-rr";

--
-- Name: episode_votes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE episode_votes (
    season integer NOT NULL,
    episode integer NOT NULL,
    user_id uuid NOT NULL,
    vote smallint NOT NULL
);


ALTER TABLE episode_votes OWNER TO "mlpvc-rr";

--
-- Name: episodes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE episodes (
    season integer NOT NULL,
    episode integer NOT NULL,
    twoparter boolean DEFAULT false NOT NULL,
    title text NOT NULL,
    posted timestamp with time zone DEFAULT now() NOT NULL,
    posted_by uuid NOT NULL,
    airs timestamp with time zone,
    no smallint,
    score real DEFAULT 0 NOT NULL,
    notes text
);


ALTER TABLE episodes OWNER TO "mlpvc-rr";

--
-- Name: event_entries; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE event_entries (
    id integer NOT NULL,
    event_id integer NOT NULL,
    prev_src character varying(255),
    prev_full character varying(255),
    prev_thumb character varying(255),
    sub_prov character varying(20) NOT NULL,
    sub_id character varying(20) NOT NULL,
    submitted_by uuid NOT NULL,
    submitted_at timestamp with time zone DEFAULT now() NOT NULL,
    title character varying(64) NOT NULL,
    score integer,
    last_edited timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE event_entries OWNER TO "mlpvc-rr";

--
-- Name: event_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE event_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE event_entries_id_seq OWNER TO "mlpvc-rr";

--
-- Name: event_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE event_entries_id_seq OWNED BY event_entries.id;


--
-- Name: event_entry_votes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE event_entry_votes (
    entry_id integer NOT NULL,
    user_id uuid NOT NULL,
    value smallint NOT NULL,
    cast_at timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE event_entry_votes OWNER TO "mlpvc-rr";

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
    vote_role character varying(15),
    result_favme character varying(7),
    finalized_at timestamp with time zone,
    finalized_by uuid
);


ALTER TABLE events OWNER TO "mlpvc-rr";

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
-- Name: known_ips; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE known_ips (
    id integer NOT NULL,
    ip inet NOT NULL,
    user_id uuid,
    first_seen timestamp with time zone DEFAULT now() NOT NULL,
    last_seen timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE known_ips OWNER TO "mlpvc-rr";

--
-- Name: known_ips_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE known_ips_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE known_ips_id_seq OWNER TO "mlpvc-rr";

--
-- Name: known_ips_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE known_ips_id_seq OWNED BY known_ips.id;


--
-- Name: log; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log (
    entryid integer NOT NULL,
    initiator uuid,
    reftype character varying(20) NOT NULL,
    refid integer,
    "timestamp" timestamp with time zone DEFAULT now() NOT NULL,
    ip inet NOT NULL
);


ALTER TABLE log OWNER TO "mlpvc-rr";

--
-- Name: log__appearance_modify; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__appearance_modify (
    entryid integer NOT NULL,
    appearance_id integer NOT NULL,
    changes jsonb NOT NULL
);


ALTER TABLE log__appearance_modify OWNER TO "mlpvc-rr";

--
-- Name: log__appearance_modify_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__appearance_modify_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__appearance_modify_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__appearance_modify_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__appearance_modify_entryid_seq OWNED BY log__appearance_modify.entryid;


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
    ishuman boolean,
    added timestamp with time zone,
    usetemplate boolean DEFAULT false NOT NULL,
    private boolean DEFAULT false NOT NULL,
    owner_id uuid
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
    target_id uuid NOT NULL,
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
    group_id integer NOT NULL,
    oldlabel character varying(255),
    newlabel character varying(255),
    oldcolors text,
    newcolors text,
    appearance_id integer NOT NULL
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
    appearance_id integer NOT NULL,
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
    group_id integer NOT NULL,
    appearance_id integer NOT NULL,
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
    appearance_id integer NOT NULL,
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
    appearance_id integer NOT NULL,
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
-- Name: log__da_namechange; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__da_namechange (
    entryid integer NOT NULL,
    old citext NOT NULL,
    new citext NOT NULL,
    user_id uuid NOT NULL
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
    oldairs timestamp with time zone,
    newairs timestamp with time zone
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
    airs timestamp with time zone
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
-- Name: log__major_changes; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__major_changes (
    entryid integer NOT NULL,
    appearance_id integer NOT NULL,
    reason character varying(255) NOT NULL
);


ALTER TABLE log__major_changes OWNER TO "mlpvc-rr";

--
-- Name: log__major_changes_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__major_changes_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__major_changes_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__major_changes_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__major_changes_entryid_seq OWNED BY log__major_changes.entryid;


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
-- Name: log__post_fix; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__post_fix (
    entryid integer NOT NULL,
    type character varying(11) NOT NULL,
    id integer NOT NULL,
    reserved_by uuid
);


ALTER TABLE log__post_fix OWNER TO "mlpvc-rr";

--
-- Name: log__post_fix_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__post_fix_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__post_fix_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__post_fix_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__post_fix_entryid_seq OWNED BY log__post_fix.entryid;


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
    requested_at timestamp with time zone,
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
-- Name: log__unbanish; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE log__unbanish (
    entryid integer NOT NULL,
    target_id uuid NOT NULL,
    reason character varying(255) NOT NULL
);


ALTER TABLE log__unbanish OWNER TO "mlpvc-rr";

--
-- Name: log__unbanish_entryid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE log__unbanish_entryid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE log__unbanish_entryid_seq OWNER TO "mlpvc-rr";

--
-- Name: log__unbanish_entryid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE log__unbanish_entryid_seq OWNED BY log__unbanish.entryid;


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
    recipient_id uuid NOT NULL,
    type character varying(15) NOT NULL,
    data jsonb NOT NULL,
    sent_at timestamp with time zone DEFAULT now() NOT NULL,
    read_at timestamp with time zone,
    read_action character varying(15)
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
-- Name: phinxlog; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE phinxlog (
    version bigint NOT NULL,
    migration_name character varying(100),
    start_time timestamp without time zone,
    end_time timestamp without time zone,
    breakpoint boolean DEFAULT false NOT NULL
);


ALTER TABLE phinxlog OWNER TO "mlpvc-rr";

--
-- Name: related_appearances; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE related_appearances (
    source_id integer NOT NULL,
    target_id integer NOT NULL
);


ALTER TABLE related_appearances OWNER TO "mlpvc-rr";

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
    requested_by uuid NOT NULL,
    requested_at timestamp with time zone DEFAULT now() NOT NULL,
    reserved_by uuid,
    reserved_at timestamp with time zone,
    deviation_id character varying(7),
    lock boolean DEFAULT false NOT NULL,
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
    reserved_at timestamp with time zone NOT NULL,
    reserved_by uuid NOT NULL,
    deviation_id character varying(255),
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
    user_id uuid NOT NULL,
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
    tag_id integer NOT NULL,
    appearance_id integer NOT NULL
);


ALTER TABLE tagged OWNER TO "mlpvc-rr";

--
-- Name: tags; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE tags (
    id integer NOT NULL,
    name character varying(30) NOT NULL,
    title character varying(255),
    type character varying(4),
    uses integer DEFAULT 0 NOT NULL,
    synonym_of integer
);


ALTER TABLE tags OWNER TO "mlpvc-rr";

--
-- Name: tags_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE tags_id_seq OWNER TO "mlpvc-rr";

--
-- Name: tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE tags_id_seq OWNED BY tags.id;


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
-- Name: unread_notifications; Type: VIEW; Schema: public; Owner: mlpvc-rr
--

CREATE VIEW unread_notifications AS
 SELECT u.name AS "user",
    count(n.id) AS count
   FROM (notifications n
     LEFT JOIN users u ON ((n.recipient_id = u.id)))
  WHERE (n.read_at IS NULL)
  GROUP BY u.name
  ORDER BY (count(n.id)) DESC;


ALTER TABLE unread_notifications OWNER TO "mlpvc-rr";

--
-- Name: useful_links; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE useful_links (
    id integer NOT NULL,
    url character varying(255) NOT NULL,
    label character varying(40) NOT NULL,
    title character varying(255) NOT NULL,
    minrole character varying(10) DEFAULT 'user'::character varying NOT NULL,
    "order" integer
);


ALTER TABLE useful_links OWNER TO "mlpvc-rr";

--
-- Name: useful_links_id_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE useful_links_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE useful_links_id_seq OWNER TO "mlpvc-rr";

--
-- Name: useful_links_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE useful_links_id_seq OWNED BY useful_links.id;


--
-- Name: user_prefs; Type: TABLE; Schema: public; Owner: mlpvc-rr
--

CREATE TABLE user_prefs (
    user_id uuid NOT NULL,
    key character varying(50) NOT NULL,
    value text
);


ALTER TABLE user_prefs OWNER TO "mlpvc-rr";

--
-- Name: appearances id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearances ALTER COLUMN id SET DEFAULT nextval('appearances_id_seq'::regclass);


--
-- Name: color_groups id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY color_groups ALTER COLUMN id SET DEFAULT nextval('color_groups_id_seq'::regclass);


--
-- Name: cutiemarks id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cutiemarks ALTER COLUMN id SET DEFAULT nextval('cutiemarks_id_seq'::regclass);


--
-- Name: event_entries id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY event_entries ALTER COLUMN id SET DEFAULT nextval('event_entries_id_seq'::regclass);


--
-- Name: events id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events ALTER COLUMN id SET DEFAULT nextval('events_id_seq'::regclass);


--
-- Name: known_ips id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY known_ips ALTER COLUMN id SET DEFAULT nextval('known_ips_id_seq'::regclass);


--
-- Name: log entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log ALTER COLUMN entryid SET DEFAULT nextval('log_entryid_seq'::regclass);


--
-- Name: log__appearance_modify entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearance_modify ALTER COLUMN entryid SET DEFAULT nextval('log__appearance_modify_entryid_seq'::regclass);


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
-- Name: log__major_changes entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__major_changes ALTER COLUMN entryid SET DEFAULT nextval('log__major_changes_entryid_seq'::regclass);


--
-- Name: log__post_break entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_break ALTER COLUMN entryid SET DEFAULT nextval('log__post_break_entryid_seq'::regclass);


--
-- Name: log__post_fix entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_fix ALTER COLUMN entryid SET DEFAULT nextval('log__post_fix_entryid_seq'::regclass);


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
-- Name: log__unbanish entryid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__unbanish ALTER COLUMN entryid SET DEFAULT nextval('log__unbanish_entryid_seq'::regclass);


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
-- Name: tags id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tags ALTER COLUMN id SET DEFAULT nextval('tags_id_seq'::regclass);


--
-- Name: useful_links id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY useful_links ALTER COLUMN id SET DEFAULT nextval('useful_links_id_seq'::regclass);


--
-- Name: appearances appearances_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearances
    ADD CONSTRAINT appearances_pkey PRIMARY KEY (id);


--
-- Name: cached_deviations cached_deviations_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cached_deviations
    ADD CONSTRAINT cached_deviations_pkey PRIMARY KEY (provider, id);


--
-- Name: color_groups color_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY color_groups
    ADD CONSTRAINT color_groups_pkey PRIMARY KEY (id);


--
-- Name: cutiemarks cutiemarks_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cutiemarks
    ADD CONSTRAINT cutiemarks_pkey PRIMARY KEY (id);


--
-- Name: discord_members discord_members_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY discord_members
    ADD CONSTRAINT discord_members_pkey PRIMARY KEY (id);


--
-- Name: episode_videos episode_videos_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episode_videos
    ADD CONSTRAINT episode_videos_pkey PRIMARY KEY (season, episode, provider, part);


--
-- Name: episode_votes episode_votes_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episode_votes
    ADD CONSTRAINT episode_votes_pkey PRIMARY KEY (season, episode, user_id);


--
-- Name: episodes episodes_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_pkey PRIMARY KEY (season, episode);


--
-- Name: event_entries event_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY event_entries
    ADD CONSTRAINT event_entries_pkey PRIMARY KEY (id);


--
-- Name: event_entry_votes event_entry_votes_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY event_entry_votes
    ADD CONSTRAINT event_entry_votes_pkey PRIMARY KEY (entry_id, user_id);


--
-- Name: events events_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events
    ADD CONSTRAINT events_pkey PRIMARY KEY (id);


--
-- Name: known_ips known_ips_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY known_ips
    ADD CONSTRAINT known_ips_pkey PRIMARY KEY (id);


--
-- Name: log__appearance_modify log__appearance_modify_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearance_modify
    ADD CONSTRAINT log__appearance_modify_pkey PRIMARY KEY (entryid);


--
-- Name: log__appearances log__appearances_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__appearances
    ADD CONSTRAINT log__appearances_pkey PRIMARY KEY (entryid);


--
-- Name: log__banish log__banish_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish
    ADD CONSTRAINT log__banish_pkey PRIMARY KEY (entryid);


--
-- Name: log__cg_modify log__cg_modify_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_modify
    ADD CONSTRAINT log__cg_modify_pkey PRIMARY KEY (entryid);


--
-- Name: log__cg_order log__cg_order_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cg_order
    ADD CONSTRAINT log__cg_order_pkey PRIMARY KEY (entryid);


--
-- Name: log__cgs log__cgs_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cgs
    ADD CONSTRAINT log__cgs_pkey PRIMARY KEY (entryid);


--
-- Name: log__cm_delete log__cm_delete_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cm_delete
    ADD CONSTRAINT log__cm_delete_pkey PRIMARY KEY (entryid);


--
-- Name: log__cm_modify log__cm_modify_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__cm_modify
    ADD CONSTRAINT log__cm_modify_pkey PRIMARY KEY (entryid);


--
-- Name: log__da_namechange log__da_namechange_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange
    ADD CONSTRAINT log__da_namechange_pkey PRIMARY KEY (entryid);


--
-- Name: log__episode_modify log__episode_modify_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episode_modify
    ADD CONSTRAINT log__episode_modify_pkey PRIMARY KEY (entryid);


--
-- Name: log__episodes log__episodes_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__episodes
    ADD CONSTRAINT log__episodes_pkey PRIMARY KEY (entryid);


--
-- Name: log__img_update log__img_update_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__img_update
    ADD CONSTRAINT log__img_update_pkey PRIMARY KEY (entryid);


--
-- Name: log__major_changes log__major_changes_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__major_changes
    ADD CONSTRAINT log__major_changes_pkey PRIMARY KEY (entryid);


--
-- Name: log__post_break log__post_break_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_break
    ADD CONSTRAINT log__post_break_pkey PRIMARY KEY (entryid);


--
-- Name: log__post_fix log__post_fix_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_fix
    ADD CONSTRAINT log__post_fix_pkey PRIMARY KEY (entryid);


--
-- Name: log__post_lock log__post_lock_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__post_lock
    ADD CONSTRAINT log__post_lock_pkey PRIMARY KEY (entryid);


--
-- Name: log__req_delete log__req_delete_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__req_delete
    ADD CONSTRAINT log__req_delete_pkey PRIMARY KEY (entryid);


--
-- Name: log__res_overtake log__res_overtake_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_overtake
    ADD CONSTRAINT log__res_overtake_pkey PRIMARY KEY (entryid);


--
-- Name: log__res_transfer log__res_transfer_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__res_transfer
    ADD CONSTRAINT log__res_transfer_pkey PRIMARY KEY (entryid);


--
-- Name: log__rolechange log__rolechange_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__rolechange
    ADD CONSTRAINT log__rolechange_pkey PRIMARY KEY (entryid);


--
-- Name: log__unbanish log__unbanish_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__unbanish
    ADD CONSTRAINT log__unbanish_pkey PRIMARY KEY (entryid);


--
-- Name: log__userfetch log__userfetch_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__userfetch
    ADD CONSTRAINT log__userfetch_pkey PRIMARY KEY (entryid);


--
-- Name: log__video_broken log__video_broken_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__video_broken
    ADD CONSTRAINT log__video_broken_pkey PRIMARY KEY (entryid);


--
-- Name: log log_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log
    ADD CONSTRAINT log_pkey PRIMARY KEY (entryid);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: phinxlog phinxlog_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY phinxlog
    ADD CONSTRAINT phinxlog_pkey PRIMARY KEY (version);


--
-- Name: related_appearances related_appearances_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY related_appearances
    ADD CONSTRAINT related_appearances_pkey PRIMARY KEY (source_id, target_id);


--
-- Name: requests requests_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_pkey PRIMARY KEY (id);


--
-- Name: reservations reservations_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: tagged tagged_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tagged
    ADD CONSTRAINT tagged_pkey PRIMARY KEY (tag_id, appearance_id);


--
-- Name: tags tags_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: useful_links useful_links_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY useful_links
    ADD CONSTRAINT useful_links_pkey PRIMARY KEY (id);


--
-- Name: user_prefs user_prefs_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY user_prefs
    ADD CONSTRAINT user_prefs_pkey PRIMARY KEY (user_id, key);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


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
-- Name: appearances_owner_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX appearances_owner_id ON appearances USING btree (owner_id);


--
-- Name: cached_deviations_author; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX cached_deviations_author ON cached_deviations USING btree (author);


--
-- Name: color_groups_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX color_groups_appearance_id ON color_groups USING btree (appearance_id);


--
-- Name: color_groups_appearance_id_label; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE UNIQUE INDEX color_groups_appearance_id_label ON color_groups USING btree (appearance_id, label);


--
-- Name: colors_group_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX colors_group_id ON colors USING btree (group_id);


--
-- Name: cutiemarks_appearance_id_facing; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE UNIQUE INDEX cutiemarks_appearance_id_facing ON cutiemarks USING btree (appearance_id, facing);


--
-- Name: episode_votes_user_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX episode_votes_user_id ON episode_votes USING btree (user_id);


--
-- Name: episodes_posted_by; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX episodes_posted_by ON episodes USING btree (posted_by);


--
-- Name: known_ips_ip_user_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE UNIQUE INDEX known_ips_ip_user_id ON known_ips USING btree (ip, user_id);


--
-- Name: log__appearance_modify_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__appearance_modify_appearance_id ON log__appearance_modify USING btree (appearance_id);


--
-- Name: log__banish_target_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__banish_target_id ON log__banish USING btree (target_id);


--
-- Name: log__cg_modify_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__cg_modify_appearance_id ON log__cg_modify USING btree (appearance_id);


--
-- Name: log__cg_modify_group_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__cg_modify_group_id ON log__cg_modify USING btree (group_id);


--
-- Name: log__cg_order_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__cg_order_appearance_id ON log__cg_order USING btree (appearance_id);


--
-- Name: log__cgs_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__cgs_appearance_id ON log__cgs USING btree (appearance_id);


--
-- Name: log__cgs_group_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__cgs_group_id ON log__cgs USING btree (group_id);


--
-- Name: log__cm_delete_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__cm_delete_appearance_id ON log__cm_delete USING btree (appearance_id);


--
-- Name: log__cm_modify_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__cm_modify_appearance_id ON log__cm_modify USING btree (appearance_id);


--
-- Name: log__da_namechange_user_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__da_namechange_user_id ON log__da_namechange USING btree (user_id);


--
-- Name: log__major_changes_appearance_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__major_changes_appearance_id ON log__major_changes USING btree (appearance_id);


--
-- Name: log__rolechange_target; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__rolechange_target ON log__rolechange USING btree (target);


--
-- Name: log__unbanish_target_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__unbanish_target_id ON log__unbanish USING btree (target_id);


--
-- Name: log__userfetch_userid; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log__userfetch_userid ON log__userfetch USING btree (userid);


--
-- Name: log_initiator; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX log_initiator ON log USING btree (initiator);


--
-- Name: notifications_recipient_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX notifications_recipient_id ON notifications USING btree (recipient_id);


--
-- Name: notifications_type; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX notifications_type ON notifications USING btree (type);


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
-- Name: sessions_user_id; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX sessions_user_id ON sessions USING btree (user_id);


--
-- Name: tags_name; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX tags_name ON tags USING btree (name);


--
-- Name: tags_synonym_of; Type: INDEX; Schema: public; Owner: mlpvc-rr
--

CREATE INDEX tags_synonym_of ON tags USING btree (synonym_of);


--
-- Name: appearances appearances_owner_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearances
    ADD CONSTRAINT appearances_owner_id FOREIGN KEY (owner_id) REFERENCES users(id) ON UPDATE CASCADE;


--
-- Name: color_groups color_groups_appearance_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY color_groups
    ADD CONSTRAINT color_groups_appearance_id FOREIGN KEY (appearance_id) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: colors colors_group_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colors
    ADD CONSTRAINT colors_group_id FOREIGN KEY (group_id) REFERENCES color_groups(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: cutiemarks cutiemarks_appearance_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY cutiemarks
    ADD CONSTRAINT cutiemarks_appearance_id FOREIGN KEY (appearance_id) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: discord_members discord_members_user_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY discord_members
    ADD CONSTRAINT discord_members_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: episode_videos episode_videos_season_episode; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episode_videos
    ADD CONSTRAINT episode_videos_season_episode FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: episode_votes episode_votes_season_episode; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episode_votes
    ADD CONSTRAINT episode_votes_season_episode FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: episode_votes episode_votes_user_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episode_votes
    ADD CONSTRAINT episode_votes_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: episodes episodes_posted_by; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_posted_by FOREIGN KEY (posted_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: event_entries event_entries_event_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY event_entries
    ADD CONSTRAINT event_entries_event_id FOREIGN KEY (event_id) REFERENCES events(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: event_entries event_entries_submitted_by; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY event_entries
    ADD CONSTRAINT event_entries_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: event_entry_votes event_entry_votes_entry_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY event_entry_votes
    ADD CONSTRAINT event_entry_votes_entry_id FOREIGN KEY (entry_id) REFERENCES event_entries(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: event_entry_votes event_entry_votes_user_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY event_entry_votes
    ADD CONSTRAINT event_entry_votes_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: events events_added_by; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events
    ADD CONSTRAINT events_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: events events_finalized_by; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY events
    ADD CONSTRAINT events_finalized_by FOREIGN KEY (finalized_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: known_ips known_ips_user_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY known_ips
    ADD CONSTRAINT known_ips_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: log__banish log__banish_target_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__banish
    ADD CONSTRAINT log__banish_target_id FOREIGN KEY (target_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: log__da_namechange log__da_namechange_user_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__da_namechange
    ADD CONSTRAINT log__da_namechange_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: log__unbanish log__unbanish_target_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log__unbanish
    ADD CONSTRAINT log__unbanish_target_id FOREIGN KEY (target_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: log log_initiator; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY log
    ADD CONSTRAINT log_initiator FOREIGN KEY (initiator) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: notifications notifications_recipient_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY notifications
    ADD CONSTRAINT notifications_recipient_id FOREIGN KEY (recipient_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: related_appearances related_appearances_source_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY related_appearances
    ADD CONSTRAINT related_appearances_source_id FOREIGN KEY (source_id) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: related_appearances related_appearances_target_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY related_appearances
    ADD CONSTRAINT related_appearances_target_id FOREIGN KEY (target_id) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: requests requests_requested_by; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: requests requests_reserved_by; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_reserved_by FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: requests requests_season_episode; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_season_episode FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: reservations reservations_reserved_by; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_reserved_by FOREIGN KEY (reserved_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: reservations reservations_season_episode; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY reservations
    ADD CONSTRAINT reservations_season_episode FOREIGN KEY (season, episode) REFERENCES episodes(season, episode) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sessions sessions_user_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY sessions
    ADD CONSTRAINT sessions_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tagged tagged_appearance_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tagged
    ADD CONSTRAINT tagged_appearance_id FOREIGN KEY (appearance_id) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tagged tagged_tag_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tagged
    ADD CONSTRAINT tagged_tag_id FOREIGN KEY (tag_id) REFERENCES tags(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tags tags_synonym_of; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tags
    ADD CONSTRAINT tags_synonym_of FOREIGN KEY (synonym_of) REFERENCES tags(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: user_prefs user_prefs_user_id; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY user_prefs
    ADD CONSTRAINT user_prefs_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

