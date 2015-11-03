--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: appearances; Type: TABLE; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

CREATE TABLE appearances (
    id integer NOT NULL,
    "order" integer,
    label character varying(255) NOT NULL,
    notes text NOT NULL,
    cm_favme character varying(20),
    ishuman boolean NOT NULL,
    added timestamp with time zone DEFAULT now()
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
-- Name: colorgroups; Type: TABLE; Schema: public; Owner: mlpvc-rr; Tablespace: 
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
-- Name: colors; Type: TABLE; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

CREATE TABLE colors (
    colorid integer NOT NULL,
    groupid integer NOT NULL,
    label character varying(255) NOT NULL,
    hex character(7),
    "order" integer
);


ALTER TABLE colors OWNER TO "mlpvc-rr";

--
-- Name: colors_colorid_seq; Type: SEQUENCE; Schema: public; Owner: mlpvc-rr
--

CREATE SEQUENCE colors_colorid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE colors_colorid_seq OWNER TO "mlpvc-rr";

--
-- Name: colors_colorid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mlpvc-rr
--

ALTER SEQUENCE colors_colorid_seq OWNED BY colors.colorid;


--
-- Name: tagged; Type: TABLE; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

CREATE TABLE tagged (
    tid integer NOT NULL,
    ponyid integer NOT NULL
);


ALTER TABLE tagged OWNER TO "mlpvc-rr";

--
-- Name: tags; Type: TABLE; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

CREATE TABLE tags (
    tid integer NOT NULL,
    name character varying(25) NOT NULL,
    title character varying(255) NOT NULL,
    type character varying(4),
    uses integer DEFAULT 0 NOT NULL
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
-- Name: id; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY appearances ALTER COLUMN id SET DEFAULT nextval('appearances_id_seq'::regclass);


--
-- Name: groupid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colorgroups ALTER COLUMN groupid SET DEFAULT nextval('colorgroups_groupid_seq'::regclass);


--
-- Name: colorid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colors ALTER COLUMN colorid SET DEFAULT nextval('colors_colorid_seq'::regclass);


--
-- Name: tid; Type: DEFAULT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY tags ALTER COLUMN tid SET DEFAULT nextval('tags_tid_seq'::regclass);


--
-- Data for Name: appearances; Type: TABLE DATA; Schema: public; Owner: mlpvc-rr
--

INSERT INTO appearances VALUES (1, 1, 'Twilight Sparkle', 'Far legs use darker colors.', 'd64bqyo', false, '2015-08-24 19:04:32+00');
INSERT INTO appearances VALUES (2, 2, 'Applejack', '', 'd64bqyo', false, '2015-09-21 18:46:54+00');
INSERT INTO appearances VALUES (3, 3, 'Fluttershy', '', 'd64bqyo', false, '2015-09-21 18:54:47+00');
INSERT INTO appearances VALUES (4, 4, 'Pinkie Pie', 'Far legs use darker colors.', 'd64bqyo', false, '2015-09-21 18:52:26+00');
INSERT INTO appearances VALUES (5, 5, 'Rainbow Dash', '', 'd64bqyo', false, '2015-09-21 19:01:33+00');
INSERT INTO appearances VALUES (6, 6, 'Rarity', '', 'd64bqyo', false, '2015-09-21 19:18:24+00');
INSERT INTO appearances VALUES (7, 7, 'Spike', '', NULL, false, '2015-09-21 19:43:59+00');
INSERT INTO appearances VALUES (9, 13, 'Princess Luna', '', NULL, false, '2015-09-29 18:55:38+00');
INSERT INTO appearances VALUES (10, NULL, 'Minuette', 'For convenience, the color offalsethe glass on her cutie mark is solid rather than transparent, thus the sand should be above.', NULL, false, '2015-07-25 14:49:44+00');
INSERT INTO appearances VALUES (11, NULL, 'Derpy / Muffins', '', NULL, false, '2015-08-26 03:53:49+00');
INSERT INTO appearances VALUES (12, NULL, 'Lyra Heartstrings', '', NULL, false, '2015-08-26 04:08:33+00');
INSERT INTO appearances VALUES (13, NULL, 'Whoa Nelly', '', NULL, false, '2015-09-17 03:43:22+00');
INSERT INTO appearances VALUES (14, NULL, 'Fashion Plate', '', NULL, false, '2015-09-17 04:02:26+00');
INSERT INTO appearances VALUES (15, NULL, 'Sassy Saddles', '', NULL, false, '2015-09-17 04:22:20+00');
INSERT INTO appearances VALUES (16, NULL, 'Twinkleshine', '', NULL, false, '2015-09-21 20:25:31+00');
INSERT INTO appearances VALUES (17, NULL, 'Lemon Hearts', '', NULL, false, '2015-09-21 20:30:06+00');
INSERT INTO appearances VALUES (18, NULL, 'Granny Smith', 'Far legs use darker colors.', NULL, false, '2015-09-21 20:37:57+00');
INSERT INTO appearances VALUES (19, NULL, 'Fleetfoot', '', 'd97x7vd', false, '2015-09-21 20:58:56+00');
INSERT INTO appearances VALUES (20, NULL, 'Stormy Flare', 'Only has one eye shine.', NULL, false, '2015-09-22 05:20:14+00');
INSERT INTO appearances VALUES (21, NULL, 'Wind Rider', 'Teeth use a different color than normal.', NULL, false, '2015-09-24 04:35:32+00');
INSERT INTO appearances VALUES (22, NULL, 'Sugar Belle', '', NULL, false, '2015-09-24 10:44:18+00');
INSERT INTO appearances VALUES (25, 11, 'Sweetie Belle', 'Cutie Mark colors subject to change on further episodes.', NULL, false, '2015-09-24 12:09:41+00');
INSERT INTO appearances VALUES (26, NULL, 'Night Glider', '', NULL, false, '2015-09-26 15:03:43+00');
INSERT INTO appearances VALUES (27, NULL, 'Double Diamond', '', NULL, false, '2015-09-26 17:22:19+00');
INSERT INTO appearances VALUES (28, NULL, 'Party Favor', 'Magic aura color is unknown.', NULL, false, '2015-09-26 17:34:42+00');
INSERT INTO appearances VALUES (29, NULL, 'Starlight Glimmer', '', NULL, false, '2015-09-26 18:01:51+00');
INSERT INTO appearances VALUES (30, NULL, 'Coco Pommel', '', NULL, false, '2015-09-26 21:18:32+00');
INSERT INTO appearances VALUES (31, NULL, 'Suri Polomare', 'From S4E08', NULL, false, '2015-09-30 05:35:59+00');
INSERT INTO appearances VALUES (32, NULL, 'Trixie Lulamoon', '', 'd9bxest', false, '2015-10-03 15:35:08+00');
INSERT INTO appearances VALUES (33, NULL, 'Alicorn Amulet', '', NULL, false, '2015-10-03 16:22:13+00');
INSERT INTO appearances VALUES (34, 12, 'Princess Celestia', 'Make sure to usefalseappropriate references when picking gradient angles and stops. They''ll differ based on the hair shape and angle.', NULL, false, '2015-10-03 19:26:50+00');
INSERT INTO appearances VALUES (35, NULL, 'Big Macintosh', '', NULL, false, '2015-10-04 19:11:42+00');
INSERT INTO appearances VALUES (36, NULL, 'Moondancer', '', NULL, false, '2015-10-05 05:17:36+00');
INSERT INTO appearances VALUES (37, NULL, 'Dinky Doo', 'From E5E17', NULL, false, '2015-10-06 03:39:35+00');
INSERT INTO appearances VALUES (38, NULL, 'Berry Pinch', 'From S5E17', NULL, false, '2015-10-06 03:48:24+00');
INSERT INTO appearances VALUES (39, NULL, 'Button Mash', 'Based on S5E18', NULL, false, '2015-10-15 03:32:38+00');
INSERT INTO appearances VALUES (40, NULL, 'Lily Longsocks', 'From S5E18', NULL, false, '2015-10-16 05:37:56+00');
INSERT INTO appearances VALUES (44, NULL, 'Shining Armor', '', NULL, false, '2015-10-23 14:27:32+00');
INSERT INTO appearances VALUES (45, NULL, 'Penny Curve', 'VectorClub Mascot', 'd9e7zgj', false, '2015-10-25 20:10:21+00');
INSERT INTO appearances VALUES (46, NULL, 'Cheese Sandwich', '', NULL, false, '2015-10-26 14:27:27+00');
INSERT INTO appearances VALUES (53, NULL, 'Igneous Rock', '', NULL, false, '2015-10-31 22:30:14+00');
INSERT INTO appearances VALUES (54, NULL, 'Cloudy Quartz', '', NULL, false, '2015-10-31 22:43:48.197299+00');
INSERT INTO appearances VALUES (50, NULL, 'Marble Pie', 'Two different hair outline colors.', 'd9eeci7', false, '2015-10-31 22:30:14+00');
INSERT INTO appearances VALUES (52, NULL, 'Maud Pie', 'Far legs use darker colors.', 'd7apvq4', false, '2015-10-31 22:30:14+00');
INSERT INTO appearances VALUES (51, NULL, 'Limestone Pie', '', 'd9eecqj', false, '2015-10-31 22:30:14+00');
INSERT INTO appearances VALUES (23, 9, 'Apple Bloom', 'Far legs use darker colors.
Cutie Mark colors subject to change on further episodes.', NULL, false, '2015-09-24 12:01:03+00');
INSERT INTO appearances VALUES (24, 10, 'Scootaloo', 'Far legs use darker colors.
Cutie Mark colors subject to change on further episodes.', NULL, false, '2015-09-24 12:06:23+00');


--
-- Name: appearances_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mlpvc-rr
--

SELECT pg_catalog.setval('appearances_id_seq', 54, true);


--
-- Data for Name: colorgroups; Type: TABLE DATA; Schema: public; Owner: mlpvc-rr
--

INSERT INTO colorgroups VALUES (17, 1, 'Coat', 0);
INSERT INTO colorgroups VALUES (18, 1, 'Mane & Tail', 0);
INSERT INTO colorgroups VALUES (19, 1, 'Iris', 0);
INSERT INTO colorgroups VALUES (20, 1, 'Cutie Mark', 0);
INSERT INTO colorgroups VALUES (21, 1, 'Magic', 0);
INSERT INTO colorgroups VALUES (22, 11, 'Coat', 0);
INSERT INTO colorgroups VALUES (23, 11, 'Mane & Tail', 0);
INSERT INTO colorgroups VALUES (24, 11, 'Iris', 0);
INSERT INTO colorgroups VALUES (25, 11, 'Cutie Mark', 0);
INSERT INTO colorgroups VALUES (26, 12, 'Coat', 0);
INSERT INTO colorgroups VALUES (27, 12, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (28, 12, 'Iris', 2);
INSERT INTO colorgroups VALUES (29, 12, 'Magic', 4);
INSERT INTO colorgroups VALUES (30, 12, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (35, 13, 'Coat', 0);
INSERT INTO colorgroups VALUES (36, 13, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (37, 13, 'Iris ', 2);
INSERT INTO colorgroups VALUES (38, 13, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (39, 13, 'Magic', 4);
INSERT INTO colorgroups VALUES (40, 14, 'Coat', 0);
INSERT INTO colorgroups VALUES (41, 14, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (42, 14, 'Iris', 2);
INSERT INTO colorgroups VALUES (43, 14, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (44, 14, 'Bandana', 6);
INSERT INTO colorgroups VALUES (45, 14, 'Magic', 4);
INSERT INTO colorgroups VALUES (46, 14, 'Glasses', 5);
INSERT INTO colorgroups VALUES (47, 15, 'Coat', 0);
INSERT INTO colorgroups VALUES (48, 15, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (49, 15, 'Iris', 2);
INSERT INTO colorgroups VALUES (51, 15, 'Dress', 4);
INSERT INTO colorgroups VALUES (52, 15, 'Saddle', 5);
INSERT INTO colorgroups VALUES (53, 15, 'Magic', 3);
INSERT INTO colorgroups VALUES (54, 2, 'Coat', 0);
INSERT INTO colorgroups VALUES (55, 2, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (56, 2, 'Iris', 2);
INSERT INTO colorgroups VALUES (57, 2, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (58, 2, 'Hat & Hair Tie', 4);
INSERT INTO colorgroups VALUES (59, 4, 'Coat', 0);
INSERT INTO colorgroups VALUES (60, 4, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (61, 4, 'Iris', 2);
INSERT INTO colorgroups VALUES (62, 4, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (63, 3, 'Coat', 0);
INSERT INTO colorgroups VALUES (64, 3, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (65, 3, 'Iris', 2);
INSERT INTO colorgroups VALUES (66, 3, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (67, 5, 'Coat', 0);
INSERT INTO colorgroups VALUES (68, 5, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (69, 5, 'Iris', 2);
INSERT INTO colorgroups VALUES (70, 5, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (71, 6, 'Coat', 0);
INSERT INTO colorgroups VALUES (72, 6, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (73, 6, 'Iris', 2);
INSERT INTO colorgroups VALUES (74, 6, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (75, 7, 'Body', 0);
INSERT INTO colorgroups VALUES (76, 7, 'Spikes', 1);
INSERT INTO colorgroups VALUES (77, 7, 'Iris', 3);
INSERT INTO colorgroups VALUES (78, 7, 'Ears', 2);
INSERT INTO colorgroups VALUES (79, 7, 'Mouth', 0);
INSERT INTO colorgroups VALUES (80, 6, 'Magic', 4);
INSERT INTO colorgroups VALUES (81, 10, 'Coat', 0);
INSERT INTO colorgroups VALUES (82, 10, 'Mane & Tail', 0);
INSERT INTO colorgroups VALUES (83, 10, 'Iris', 0);
INSERT INTO colorgroups VALUES (84, 10, 'Cutie Mark', 0);
INSERT INTO colorgroups VALUES (85, 16, 'Coat', 0);
INSERT INTO colorgroups VALUES (86, 16, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (87, 16, 'Iris', 2);
INSERT INTO colorgroups VALUES (88, 16, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (89, 17, 'Coat', 0);
INSERT INTO colorgroups VALUES (90, 17, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (91, 17, 'Iris', 2);
INSERT INTO colorgroups VALUES (92, 17, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (93, 18, 'Coat', 0);
INSERT INTO colorgroups VALUES (94, 18, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (95, 18, 'Iris', 2);
INSERT INTO colorgroups VALUES (96, 18, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (97, 18, 'Wrap', 4);
INSERT INTO colorgroups VALUES (98, 19, 'Coat', 0);
INSERT INTO colorgroups VALUES (99, 19, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (100, 19, 'Iris', 2);
INSERT INTO colorgroups VALUES (101, 19, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (102, 20, 'Coat', 0);
INSERT INTO colorgroups VALUES (103, 20, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (104, 20, 'Iris', 2);
INSERT INTO colorgroups VALUES (105, 20, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (106, 20, 'Earrings/Necklace', 5);
INSERT INTO colorgroups VALUES (107, 20, 'Sweater', 4);
INSERT INTO colorgroups VALUES (108, 21, 'Coat', 0);
INSERT INTO colorgroups VALUES (109, 21, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (110, 21, 'Iris', 2);
INSERT INTO colorgroups VALUES (111, 21, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (112, 21, 'Jacket', 5);
INSERT INTO colorgroups VALUES (113, 21, 'Scarf', 6);
INSERT INTO colorgroups VALUES (114, 21, 'Badge', 7);
INSERT INTO colorgroups VALUES (115, 21, 'Teeth', 4);
INSERT INTO colorgroups VALUES (116, 22, 'Coat', 0);
INSERT INTO colorgroups VALUES (117, 22, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (118, 22, 'Iris', 2);
INSERT INTO colorgroups VALUES (119, 22, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (120, 22, 'Magic', 4);
INSERT INTO colorgroups VALUES (121, 22, 'Hair Tie', 5);
INSERT INTO colorgroups VALUES (122, 23, 'Coat', 0);
INSERT INTO colorgroups VALUES (123, 23, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (124, 23, 'Iris', 2);
INSERT INTO colorgroups VALUES (126, 24, 'Coat', 0);
INSERT INTO colorgroups VALUES (127, 24, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (128, 24, 'Iris', 2);
INSERT INTO colorgroups VALUES (130, 23, 'Bow', 4);
INSERT INTO colorgroups VALUES (131, 25, 'Coat', 0);
INSERT INTO colorgroups VALUES (132, 25, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (133, 25, 'Iris', 2);
INSERT INTO colorgroups VALUES (135, 26, 'Coat', 0);
INSERT INTO colorgroups VALUES (136, 26, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (137, 26, 'Iris', 2);
INSERT INTO colorgroups VALUES (138, 26, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (139, 27, 'Coat', 0);
INSERT INTO colorgroups VALUES (140, 27, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (141, 27, 'Iris', 2);
INSERT INTO colorgroups VALUES (142, 27, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (143, 27, 'Scarf', 4);
INSERT INTO colorgroups VALUES (144, 28, 'Coat', 0);
INSERT INTO colorgroups VALUES (145, 28, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (146, 28, 'Iris', 2);
INSERT INTO colorgroups VALUES (147, 28, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (164, 29, 'Coat', 0);
INSERT INTO colorgroups VALUES (165, 29, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (166, 29, 'Iris', 2);
INSERT INTO colorgroups VALUES (167, 29, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (168, 29, 'Magic', 4);
INSERT INTO colorgroups VALUES (169, 30, 'Coat', 0);
INSERT INTO colorgroups VALUES (170, 30, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (171, 30, 'Iris', 2);
INSERT INTO colorgroups VALUES (172, 30, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (173, 30, 'Neckpiece', 4);
INSERT INTO colorgroups VALUES (174, 30, 'Flower', 5);
INSERT INTO colorgroups VALUES (175, 9, 'Coat', 0);
INSERT INTO colorgroups VALUES (176, 9, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (177, 9, 'Shoes', 5);
INSERT INTO colorgroups VALUES (178, 9, 'Regalia', 4);
INSERT INTO colorgroups VALUES (179, 9, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (180, 9, 'Iris', 2);
INSERT INTO colorgroups VALUES (182, 31, 'Coat', 0);
INSERT INTO colorgroups VALUES (183, 31, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (184, 31, 'Iris', 2);
INSERT INTO colorgroups VALUES (185, 31, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (186, 31, 'Scarf', 4);
INSERT INTO colorgroups VALUES (187, 31, 'Headband', 5);
INSERT INTO colorgroups VALUES (188, 17, 'Magic', 4);
INSERT INTO colorgroups VALUES (189, 10, 'Magic ', 0);
INSERT INTO colorgroups VALUES (190, 16, 'Magic', 4);
INSERT INTO colorgroups VALUES (191, 32, 'Coat', 0);
INSERT INTO colorgroups VALUES (192, 32, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (193, 32, 'Iris', 2);
INSERT INTO colorgroups VALUES (194, 32, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (195, 32, 'Hat & Cloak', 4);
INSERT INTO colorgroups VALUES (196, 32, 'Gem', 5);
INSERT INTO colorgroups VALUES (197, 32, 'Magic', 6);
INSERT INTO colorgroups VALUES (198, 33, 'Head', 0);
INSERT INTO colorgroups VALUES (199, 33, 'Body', 1);
INSERT INTO colorgroups VALUES (200, 33, 'Wings', 2);
INSERT INTO colorgroups VALUES (201, 33, 'Gem', 4);
INSERT INTO colorgroups VALUES (202, 33, 'Wearer Changes', 5);
INSERT INTO colorgroups VALUES (203, 33, 'Straps', 3);
INSERT INTO colorgroups VALUES (204, 34, 'Coat', 0);
INSERT INTO colorgroups VALUES (205, 34, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (206, 34, 'Iris', 2);
INSERT INTO colorgroups VALUES (207, 34, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (208, 34, 'Magic', 4);
INSERT INTO colorgroups VALUES (209, 34, 'Regalia', 5);
INSERT INTO colorgroups VALUES (210, 34, 'Shoes', 6);
INSERT INTO colorgroups VALUES (211, 35, 'Coat and Hooves', 1);
INSERT INTO colorgroups VALUES (212, 35, 'Mane and Tail', 2);
INSERT INTO colorgroups VALUES (213, 35, 'Iris', 3);
INSERT INTO colorgroups VALUES (214, 35, 'Freckles', 4);
INSERT INTO colorgroups VALUES (215, 35, 'Yoke', 5);
INSERT INTO colorgroups VALUES (216, 35, 'Cutie mark', 6);
INSERT INTO colorgroups VALUES (217, 36, 'Coat', 0);
INSERT INTO colorgroups VALUES (218, 36, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (219, 36, 'Iris', 2);
INSERT INTO colorgroups VALUES (220, 36, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (221, 36, 'Sweater', 4);
INSERT INTO colorgroups VALUES (222, 36, 'Sweater Buttons / Hair Beads', 5);
INSERT INTO colorgroups VALUES (223, 36, 'Glasses', 6);
INSERT INTO colorgroups VALUES (224, 36, 'Magic', 7);
INSERT INTO colorgroups VALUES (225, 37, 'Coat', 0);
INSERT INTO colorgroups VALUES (226, 37, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (227, 37, 'Iris', 2);
INSERT INTO colorgroups VALUES (229, 38, 'Coat', 0);
INSERT INTO colorgroups VALUES (230, 38, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (231, 38, 'Iris', 2);
INSERT INTO colorgroups VALUES (233, 23, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (234, 39, 'Coat', 0);
INSERT INTO colorgroups VALUES (235, 39, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (236, 39, 'Iris', 2);
INSERT INTO colorgroups VALUES (240, 40, 'Coat', 0);
INSERT INTO colorgroups VALUES (241, 40, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (242, 40, 'Iris', 2);
INSERT INTO colorgroups VALUES (243, 40, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (244, 24, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (245, 25, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (246, 25, 'Magic', 4);
INSERT INTO colorgroups VALUES (257, 44, 'Coat', 0);
INSERT INTO colorgroups VALUES (258, 44, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (259, 44, 'Iris', 2);
INSERT INTO colorgroups VALUES (260, 44, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (261, 44, 'Magic', 4);
INSERT INTO colorgroups VALUES (262, 45, 'Coat', 0);
INSERT INTO colorgroups VALUES (263, 45, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (264, 45, 'Iris', 2);
INSERT INTO colorgroups VALUES (265, 45, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (266, 45, 'Magic', 4);
INSERT INTO colorgroups VALUES (267, 46, 'Coat', 0);
INSERT INTO colorgroups VALUES (268, 46, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (269, 46, 'Iris', 2);
INSERT INTO colorgroups VALUES (270, 46, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (271, 46, 'Shirt', 4);
INSERT INTO colorgroups VALUES (272, 46, 'Glasses', 5);
INSERT INTO colorgroups VALUES (281, 50, 'Coat', 0);
INSERT INTO colorgroups VALUES (283, 50, 'Iris', 2);
INSERT INTO colorgroups VALUES (284, 50, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (285, 51, 'Coat', 0);
INSERT INTO colorgroups VALUES (286, 51, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (287, 51, 'Iris', 2);
INSERT INTO colorgroups VALUES (288, 51, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (293, 53, 'Coat', 0);
INSERT INTO colorgroups VALUES (294, 53, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (295, 53, 'Iris', 2);
INSERT INTO colorgroups VALUES (296, 53, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (297, 53, 'Hat & Tie/Collar', 4);
INSERT INTO colorgroups VALUES (298, 54, 'Coat', 0);
INSERT INTO colorgroups VALUES (299, 54, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (301, 54, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (300, 54, 'Iris', 2);
INSERT INTO colorgroups VALUES (302, 54, 'Glasses', 4);
INSERT INTO colorgroups VALUES (303, 54, 'Neckpiece', 5);
INSERT INTO colorgroups VALUES (238, 39, 'Beanie', 3);
INSERT INTO colorgroups VALUES (282, 50, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (290, 52, 'Mane & Tail', 1);
INSERT INTO colorgroups VALUES (291, 52, 'Iris', 2);
INSERT INTO colorgroups VALUES (292, 52, 'Dress', 4);
INSERT INTO colorgroups VALUES (304, 52, 'Boulder', 5);
INSERT INTO colorgroups VALUES (305, 52, 'Cutie Mark', 3);
INSERT INTO colorgroups VALUES (289, 52, 'Coat', 0);


--
-- Name: colorgroups_groupid_seq; Type: SEQUENCE SET; Schema: public; Owner: mlpvc-rr
--

SELECT pg_catalog.setval('colorgroups_groupid_seq', 305, true);


--
-- Data for Name: colors; Type: TABLE DATA; Schema: public; Owner: mlpvc-rr
--

INSERT INTO colors VALUES (55, 17, 'Outline', '#A46BBD', 0);
INSERT INTO colors VALUES (57, 17, 'Fill', '#CC9CDF', 1);
INSERT INTO colors VALUES (58, 17, 'Shadow Outline', '#9156A9', 2);
INSERT INTO colors VALUES (59, 17, 'Shadow Fill', '#BF89D1', 3);
INSERT INTO colors VALUES (60, 18, 'Outline and Inner Lines', '#132248', 0);
INSERT INTO colors VALUES (61, 18, 'Fill', '#243870', 1);
INSERT INTO colors VALUES (62, 18, 'Stripe 1', '#652D87', 2);
INSERT INTO colors VALUES (63, 18, 'Stripe 2', '#EA428B', 3);
INSERT INTO colors VALUES (64, 19, 'Gradient Top', '#1E093C', 0);
INSERT INTO colors VALUES (65, 19, 'Gradient Bottom', '#662F89', 1);
INSERT INTO colors VALUES (66, 19, 'Highlight Top', '#8D5DA4', 2);
INSERT INTO colors VALUES (67, 19, 'Highlight Bottom', '#CCB2D3', 3);
INSERT INTO colors VALUES (68, 20, 'Large Star', '#EA428B', 0);
INSERT INTO colors VALUES (69, 20, 'Small Stars', '#FFFFFF', 1);
INSERT INTO colors VALUES (70, 21, 'Aura', '#EA428B', 0);
INSERT INTO colors VALUES (71, 22, 'Outline', '#7F859F', 0);
INSERT INTO colors VALUES (72, 22, 'Fill', '#B5BBC7', 1);
INSERT INTO colors VALUES (73, 22, 'Shadow Fill', '#ADB1BE', 2);
INSERT INTO colors VALUES (74, 23, 'Outline', '#D6CD6B', 0);
INSERT INTO colors VALUES (75, 23, 'Fill', '#E7E6A7', 1);
INSERT INTO colors VALUES (76, 24, 'Gradient Top', '#D1973D', 0);
INSERT INTO colors VALUES (77, 24, 'Gradient Bottom/Highlight Top', '#D9E985', 1);
INSERT INTO colors VALUES (79, 24, 'Highlight Bottom', '#E1EBB0', 2);
INSERT INTO colors VALUES (80, 25, 'Bubble', '#CECEEA', 0);
INSERT INTO colors VALUES (81, 25, 'Bubble Highlight', '#D5E9F0', 1);
INSERT INTO colors VALUES (82, 26, 'Outline', '#3EA679', 0);
INSERT INTO colors VALUES (83, 26, 'Fill', '#8DEBCD', 1);
INSERT INTO colors VALUES (84, 26, 'Shadow Fill', '#6ACDA9', 2);
INSERT INTO colors VALUES (85, 27, 'Outline 1', '#7AD9D7', 0);
INSERT INTO colors VALUES (86, 27, 'Fill 1', '#EBEBEB', 2);
INSERT INTO colors VALUES (87, 27, 'Outline 2', '#55C1C1', 1);
INSERT INTO colors VALUES (88, 27, 'Fill 2', '#A5D7D7', 3);
INSERT INTO colors VALUES (89, 28, 'Gradient Top', '#D56315', 0);
INSERT INTO colors VALUES (90, 28, 'Gradient Bottom', '#E3C752', 1);
INSERT INTO colors VALUES (91, 28, 'Highlight Top', '#EAEA6B', 2);
INSERT INTO colors VALUES (92, 28, 'Highlight Bottom', '#ECECCF', 3);
INSERT INTO colors VALUES (93, 29, 'Aura', '#C49159', 0);
INSERT INTO colors VALUES (94, 30, 'Outline', '#AF821A', 0);
INSERT INTO colors VALUES (95, 30, 'Fill', '#E1B046', 1);
INSERT INTO colors VALUES (96, 30, 'Highlight', '#F2D297', 2);
INSERT INTO colors VALUES (97, 30, 'Strings', '#50B18C', 3);
INSERT INTO colors VALUES (111, 35, 'Outline', '#81CFBD', 0);
INSERT INTO colors VALUES (112, 35, 'Fill', '#A1FFE9', 1);
INSERT INTO colors VALUES (114, 35, 'Shadow Fill', '#90E4D0', 2);
INSERT INTO colors VALUES (115, 36, 'Outline', '#80D940', 0);
INSERT INTO colors VALUES (116, 36, 'Fill', '#D6FF58', 1);
INSERT INTO colors VALUES (117, 37, 'Gradient Top', '#A84E0D', 0);
INSERT INTO colors VALUES (119, 37, 'Gradient Bottom', '#EE8B2D', 1);
INSERT INTO colors VALUES (120, 37, 'Highlight Top', '#F2AA75', 2);
INSERT INTO colors VALUES (121, 37, 'Highlight Bottom', '#F5B98D', 3);
INSERT INTO colors VALUES (122, 38, 'Main Star Fills', '#FFFFFF', 0);
INSERT INTO colors VALUES (123, 38, 'Small Star Fills', '#D78F4F', 1);
INSERT INTO colors VALUES (124, 37, 'Eyeball', '#F9FFCB', 4);
INSERT INTO colors VALUES (125, 38, 'Head Outline', '#76C1AD', 2);
INSERT INTO colors VALUES (126, 38, 'Head Fill', '#BEFFF0', 3);
INSERT INTO colors VALUES (127, 38, 'Head Shadow', '#92DECF', 4);
INSERT INTO colors VALUES (128, 39, 'Aura', '#E69A44', 0);
INSERT INTO colors VALUES (129, 40, 'Outline', '#1D4555', 0);
INSERT INTO colors VALUES (130, 40, 'Fill', '#2F7A98', 1);
INSERT INTO colors VALUES (131, 40, 'Shadow Outline', '#203A45', 2);
INSERT INTO colors VALUES (132, 40, 'Shadow Fill', '#2D5B6C', 3);
INSERT INTO colors VALUES (133, 41, 'Outline', '#5E5A5A', 0);
INSERT INTO colors VALUES (134, 41, 'Fill 1', '#B0B0B0', 1);
INSERT INTO colors VALUES (135, 42, 'Gradient Top', '#2F8CB0', 0);
INSERT INTO colors VALUES (137, 42, 'Gradient Bottom', '#ADD7E6', 1);
INSERT INTO colors VALUES (140, 43, 'Stars', '#EED255', 0);
INSERT INTO colors VALUES (142, 41, 'Fill 2', '#797979', 2);
INSERT INTO colors VALUES (143, 44, 'Outline', '#9F1D3A', 0);
INSERT INTO colors VALUES (144, 44, 'Main Fill', '#D44350', 1);
INSERT INTO colors VALUES (145, 44, 'Thick Stripe', '#B1374F', 2);
INSERT INTO colors VALUES (146, 44, 'Thin Stripe', '#E49355', 3);
INSERT INTO colors VALUES (147, 45, 'Aura', '#F7DF53', 0);
INSERT INTO colors VALUES (148, 46, 'Frame Outline', '#919DA4', 0);
INSERT INTO colors VALUES (149, 46, 'Frame Fill', '#FFFFFF', 1);
INSERT INTO colors VALUES (150, 46, 'Lens Top (85% Opacity)', '#260F1C', 2);
INSERT INTO colors VALUES (151, 46, 'Lens Bottom (85% Opacity)', '#C06588', 3);
INSERT INTO colors VALUES (152, 47, 'Main Outline', '#4596B0', 0);
INSERT INTO colors VALUES (153, 47, 'Fill', '#B1EAFD', 2);
INSERT INTO colors VALUES (156, 48, 'Outline', '#A3413E', 0);
INSERT INTO colors VALUES (157, 48, 'Main Outer Fill', '#EB7F51', 1);
INSERT INTO colors VALUES (158, 49, 'Gradient Top', '#F5973C', 0);
INSERT INTO colors VALUES (160, 49, 'Gradient Bottom', '#F5B27D', 1);
INSERT INTO colors VALUES (161, 49, 'Highlight Top', '#FADBAB', 2);
INSERT INTO colors VALUES (162, 49, 'Highlight Bottom', '#FBEDD4', 3);
INSERT INTO colors VALUES (165, 47, 'Inside Outlines (Ear, horn)', '#57ACD4', 1);
INSERT INTO colors VALUES (166, 48, 'Outer Fill 1', '#E9D271', 2);
INSERT INTO colors VALUES (167, 48, 'Outer Fill 2', '#FFAC89', 3);
INSERT INTO colors VALUES (168, 48, 'Outer Fill 3 (at front)', '#EA5E5E', 4);
INSERT INTO colors VALUES (169, 48, 'Inside Outline Stroke', '#95346D', 5);
INSERT INTO colors VALUES (170, 48, 'Inside Fill Gradient Top', '#CE53B9', 6);
INSERT INTO colors VALUES (171, 48, 'Inside Fill Gradient Bottom', '#88387D', 7);
INSERT INTO colors VALUES (172, 48, 'Inside Fill Line', '#75326F', 8);
INSERT INTO colors VALUES (173, 51, 'Outline', '#1E1A24', 0);
INSERT INTO colors VALUES (174, 51, 'Fill 1', '#5D4D68', 1);
INSERT INTO colors VALUES (175, 51, 'Fill 2', '#3B2D45', 2);
INSERT INTO colors VALUES (176, 51, 'Stud Fill 1', '#FFFCCB', 3);
INSERT INTO colors VALUES (177, 51, 'Stud Fill 2', '#F4D05B', 4);
INSERT INTO colors VALUES (178, 52, 'Outline', '#E8AD34', 0);
INSERT INTO colors VALUES (179, 52, 'Fill 1', '#F5CF5D', 1);
INSERT INTO colors VALUES (180, 52, 'Fill 2', '#F9E457', 2);
INSERT INTO colors VALUES (181, 52, 'Fill 3', '#FAE356', 3);
INSERT INTO colors VALUES (182, 52, 'Seat fill', '#FEFBAA', 4);
INSERT INTO colors VALUES (183, 52, 'Seat Decoration Fill 1', '#8F35B4', 5);
INSERT INTO colors VALUES (184, 52, 'Seat Decoration Fill 2', '#AC5DCB', 6);
INSERT INTO colors VALUES (185, 52, 'Stud Fill 1', '#FFFCCB', 7);
INSERT INTO colors VALUES (186, 52, 'Stud Fill 2', '#F4D05B', 8);
INSERT INTO colors VALUES (187, 53, 'Aura', '#F6EF95', 0);
INSERT INTO colors VALUES (188, 49, 'Eyeshadow', '#564C9A', 4);
INSERT INTO colors VALUES (189, 54, 'Outline', '#EF6F2F', 0);
INSERT INTO colors VALUES (190, 54, 'Fill', '#FABA62', 1);
INSERT INTO colors VALUES (192, 54, 'Shadow Fill', '#F0AA52', 2);
INSERT INTO colors VALUES (193, 55, 'Outline', '#E7D462', 0);
INSERT INTO colors VALUES (194, 55, 'Fill', '#FAF5AB', 1);
INSERT INTO colors VALUES (195, 56, 'Gradient Top', '#287916', 0);
INSERT INTO colors VALUES (197, 56, 'Gradient Bottom', '#61BA4E', 1);
INSERT INTO colors VALUES (198, 56, 'Highlight Top', '#78D863', 2);
INSERT INTO colors VALUES (199, 56, 'Highlight Bottom', '#CAECC4', 3);
INSERT INTO colors VALUES (200, 57, 'Apples', '#EC3F41', 0);
INSERT INTO colors VALUES (201, 57, 'Leaves', '#6BB944', 1);
INSERT INTO colors VALUES (202, 58, 'Outline', '#B2884D', 0);
INSERT INTO colors VALUES (203, 58, 'Fill', '#CA9A56', 1);
INSERT INTO colors VALUES (204, 59, 'Outline', '#E880B0', 0);
INSERT INTO colors VALUES (205, 59, 'Fill', '#F5B7D0', 1);
INSERT INTO colors VALUES (206, 59, 'Shadow Outline', '#DD6FA4', 2);
INSERT INTO colors VALUES (207, 59, 'Shadow Fill', '#E89CBF', 3);
INSERT INTO colors VALUES (208, 60, 'Outline', '#BB1C76', 0);
INSERT INTO colors VALUES (209, 60, 'Fill', '#EB458B', 1);
INSERT INTO colors VALUES (210, 61, 'Gradient Top', '#196E91', 0);
INSERT INTO colors VALUES (212, 61, 'Gradient Bottom', '#7DD0F1', 1);
INSERT INTO colors VALUES (213, 61, 'Highlight Top', '#9CDCF4', 2);
INSERT INTO colors VALUES (214, 61, 'Highlight Bottom', '#DCF3FD', 3);
INSERT INTO colors VALUES (215, 62, 'Color 1', '#7ED0F2', 0);
INSERT INTO colors VALUES (216, 62, 'Color 2', '#FAF5AB', 1);
INSERT INTO colors VALUES (217, 63, 'Outline', '#E9D461', 0);
INSERT INTO colors VALUES (218, 63, 'Fill', '#FAF5AB', 1);
INSERT INTO colors VALUES (220, 63, 'Shadow Fill', '#F3E488', 2);
INSERT INTO colors VALUES (221, 64, 'Outline', '#E581B1', 0);
INSERT INTO colors VALUES (222, 64, 'Fill', '#F3B5CF', 1);
INSERT INTO colors VALUES (223, 65, 'Gradient Top', '#02534D', 0);
INSERT INTO colors VALUES (225, 65, 'Gradient Bottom', '#02ACA4', 1);
INSERT INTO colors VALUES (226, 65, 'Highlight Top', '#3CBEB7', 2);
INSERT INTO colors VALUES (227, 65, 'Highlight Bottom', '#84D2D4', 3);
INSERT INTO colors VALUES (228, 66, 'Wings', '#F3B5CF', 0);
INSERT INTO colors VALUES (229, 66, 'Body', '#69C8C3', 1);
INSERT INTO colors VALUES (230, 67, 'Outline', '#6BABDA', 0);
INSERT INTO colors VALUES (231, 67, 'Fill', '#9BDBF5', 1);
INSERT INTO colors VALUES (233, 67, 'Shadow Fill', '#88C4E9', 2);
INSERT INTO colors VALUES (234, 68, 'Outline/Blue Fill', '#1B98D1', 0);
INSERT INTO colors VALUES (235, 68, 'Red Fill', '#EC4141', 1);
INSERT INTO colors VALUES (236, 69, 'Gradient Top', '#580D36', 0);
INSERT INTO colors VALUES (238, 69, 'Gradient Bottom', '#BC1D75', 1);
INSERT INTO colors VALUES (239, 69, 'Highlight Top', '#D9539D', 2);
INSERT INTO colors VALUES (240, 69, 'Highlight Bottom', '#FCB6DF', 3);
INSERT INTO colors VALUES (241, 70, 'Cloud Outline/Blue Streak', '#1B98D1', 0);
INSERT INTO colors VALUES (242, 70, 'Red Streak', '#EC4141', 2);
INSERT INTO colors VALUES (243, 68, 'Orange Fill', '#EF7135', 2);
INSERT INTO colors VALUES (244, 68, 'Yellow Fill', '#FAF5AB', 3);
INSERT INTO colors VALUES (245, 68, 'Green Fill', '#5FBB4E', 4);
INSERT INTO colors VALUES (246, 68, 'Purple Fill', '#632E86', 5);
INSERT INTO colors VALUES (247, 70, 'Yellow Streak', '#FDE85F', 3);
INSERT INTO colors VALUES (248, 71, 'Outline', '#BDC1C2', 0);
INSERT INTO colors VALUES (249, 71, 'Fill', '#EAEEF0', 1);
INSERT INTO colors VALUES (251, 71, 'Shadow Fill', '#DFE4E3', 2);
INSERT INTO colors VALUES (252, 72, 'Outline/Gradient Dark Fill', '#4A1767', 0);
INSERT INTO colors VALUES (253, 72, 'Solid Fill', '#5E50A0', 2);
INSERT INTO colors VALUES (254, 73, 'Gradient Top', '#20476B', 0);
INSERT INTO colors VALUES (256, 73, 'Gradient Bottom', '#3977B8', 1);
INSERT INTO colors VALUES (257, 73, 'Highlight Top', '#5693CF', 2);
INSERT INTO colors VALUES (258, 73, 'Highlight Bottom', '#76ADE5', 3);
INSERT INTO colors VALUES (259, 74, 'Strokes', '#2696CB', 0);
INSERT INTO colors VALUES (260, 74, 'Fill', '#7DD1F5', 1);
INSERT INTO colors VALUES (263, 72, 'Gradient Light Fill', '#794897', 1);
INSERT INTO colors VALUES (264, 73, 'Eyeshadow', '#B8E1F0', 4);
INSERT INTO colors VALUES (265, 75, 'Purple Outline', '#985E9F', 0);
INSERT INTO colors VALUES (266, 75, 'Purple Fill', '#C290C6', 1);
INSERT INTO colors VALUES (267, 75, 'Green Outline', '#96CE7D', 2);
INSERT INTO colors VALUES (268, 75, 'Green Fill', '#D5EBAD', 3);
INSERT INTO colors VALUES (269, 76, 'Outline', '#2E992E', 0);
INSERT INTO colors VALUES (270, 76, 'Fill', '#50C356', 1);
INSERT INTO colors VALUES (271, 77, 'Gradient Top', '#277915', 0);
INSERT INTO colors VALUES (273, 77, 'Gradient Bottom', '#5EBA4A', 1);
INSERT INTO colors VALUES (274, 77, 'Highlight Top', '#77D963', 2);
INSERT INTO colors VALUES (275, 77, 'Highlight Bottom', '#CAECC3', 3);
INSERT INTO colors VALUES (276, 78, 'Outline', '#DCF188', 0);
INSERT INTO colors VALUES (277, 78, 'Fill', '#AFD95E', 1);
INSERT INTO colors VALUES (278, 75, 'Soles', '#AF72B6', 4);
INSERT INTO colors VALUES (279, 79, 'Teeth', '#94B5B3', 0);
INSERT INTO colors VALUES (280, 79, 'Tongue', '#F997C8', 1);
INSERT INTO colors VALUES (281, 79, 'Mouth', '#973365', 2);
INSERT INTO colors VALUES (282, 80, 'Aura', '#82C1DC', 0);
INSERT INTO colors VALUES (283, 81, 'Outline', '#457BBB', 0);
INSERT INTO colors VALUES (284, 81, 'Fill', '#81C3EA', 1);
INSERT INTO colors VALUES (285, 81, 'Shadow Fill', '#7ABAE5', 2);
INSERT INTO colors VALUES (286, 82, 'Dark Outline', '#303296', 0);
INSERT INTO colors VALUES (287, 82, 'Dark Fill', '#393CB0', 1);
INSERT INTO colors VALUES (288, 82, 'Light Outline', '#9E9FDC', 2);
INSERT INTO colors VALUES (289, 82, 'Light Fill', '#CED0EC', 3);
INSERT INTO colors VALUES (290, 83, 'Gradient Top', '#1F205D', 0);
INSERT INTO colors VALUES (291, 83, 'Gradient Bottom', '#526BCB', 1);
INSERT INTO colors VALUES (292, 83, 'Highlight Top', '#AAAAF8', 2);
INSERT INTO colors VALUES (293, 83, 'Highlight Bottom', '#DBDCFC', 3);
INSERT INTO colors VALUES (294, 84, 'Base', '#DBBF0F', 0);
INSERT INTO colors VALUES (295, 84, 'Base Highlight', '#FBEB98', 1);
INSERT INTO colors VALUES (296, 84, 'Glass', '#BFEAF8', 2);
INSERT INTO colors VALUES (297, 84, 'Sand', '#D4CF97', 3);
INSERT INTO colors VALUES (298, 85, 'Outline', '#BEB789', 0);
INSERT INTO colors VALUES (299, 85, 'Fill', '#F9FAED', 1);
INSERT INTO colors VALUES (301, 85, 'Shadow Fill', '#E0DEC1', 2);
INSERT INTO colors VALUES (302, 86, 'Outline', '#DE83CD', 0);
INSERT INTO colors VALUES (303, 86, 'Fill', '#F8AAE5', 1);
INSERT INTO colors VALUES (304, 87, 'Gradient Top', '#015B56', 0);
INSERT INTO colors VALUES (306, 87, 'Gradient Bottom', '#6BF0ED', 1);
INSERT INTO colors VALUES (307, 87, 'Highlight Top', '#B1F0F4', 2);
INSERT INTO colors VALUES (308, 87, 'Highlight Bottom', '#D5F9FB', 3);
INSERT INTO colors VALUES (309, 88, 'Stars', '#B6E0F7', 0);
INSERT INTO colors VALUES (311, 89, 'Outline', '#EFBD3B', 0);
INSERT INTO colors VALUES (312, 89, 'Fill', '#FBFC63', 1);
INSERT INTO colors VALUES (314, 89, 'Shadow Fill', '#F7E253', 2);
INSERT INTO colors VALUES (315, 90, 'Outline', '#3695B5', 0);
INSERT INTO colors VALUES (316, 90, 'Fill 1', '#68B5CF', 1);
INSERT INTO colors VALUES (317, 91, 'Gradient Top', '#580D36', 0);
INSERT INTO colors VALUES (319, 91, 'Gradient Bottom', '#BC1D75', 1);
INSERT INTO colors VALUES (320, 91, 'Highlight Top', '#D9539D', 2);
INSERT INTO colors VALUES (321, 91, 'Highlight Bottom', '#FCB6DF', 3);
INSERT INTO colors VALUES (322, 92, 'Blue Outline', '#63B5CB', 0);
INSERT INTO colors VALUES (323, 92, 'Blue Fill', '#A2D7E1', 1);
INSERT INTO colors VALUES (324, 90, 'Fill 2', '#7BCBE1', 2);
INSERT INTO colors VALUES (325, 92, 'Green Outline', '#96DC51', 2);
INSERT INTO colors VALUES (326, 92, 'Green Fill', '#BAEF85', 3);
INSERT INTO colors VALUES (327, 93, 'Outline', '#ACC849', 0);
INSERT INTO colors VALUES (328, 93, 'Fill', '#D2EA91', 1);
INSERT INTO colors VALUES (329, 93, 'Shadow Outline', '#A4C040', 2);
INSERT INTO colors VALUES (330, 93, 'Shadow Fill', '#BDD56C', 3);
INSERT INTO colors VALUES (331, 94, 'Outline', '#ADD9D5', 0);
INSERT INTO colors VALUES (332, 94, 'Fill', '#FFFFFF', 1);
INSERT INTO colors VALUES (333, 95, 'Gradient Top', '#EC565C', 0);
INSERT INTO colors VALUES (335, 95, 'Gradient Bottom', '#F49A43', 1);
INSERT INTO colors VALUES (338, 96, 'Plate Fill 1', '#9AA596', 0);
INSERT INTO colors VALUES (339, 96, 'Plate Fill 2', '#A2AF9D', 1);
INSERT INTO colors VALUES (340, 96, 'Plate Fill 3', '#B2BEAC', 2);
INSERT INTO colors VALUES (341, 96, 'Pie FIll 1', '#B68145', 3);
INSERT INTO colors VALUES (342, 96, 'Pie FIll 2', '#D29752', 4);
INSERT INTO colors VALUES (343, 96, 'Pie FIll 3', '#E4A95A', 5);
INSERT INTO colors VALUES (344, 96, 'Pie FIll 4', '#FBB963', 6);
INSERT INTO colors VALUES (345, 97, 'Outline', '#F58950', 0);
INSERT INTO colors VALUES (346, 97, 'Fill', '#FABA63', 1);
INSERT INTO colors VALUES (347, 97, 'Apples', '#EB575A', 2);
INSERT INTO colors VALUES (348, 97, 'Stems', '#7CA42A', 3);
INSERT INTO colors VALUES (349, 97, 'Frill Outline', '#ADD9D5', 4);
INSERT INTO colors VALUES (350, 97, 'Frill Fill', '#FFFFFF', 5);
INSERT INTO colors VALUES (351, 98, 'Outline', '#3DBAC9', 0);
INSERT INTO colors VALUES (352, 98, 'Fill', '#8BD8DF', 1);
INSERT INTO colors VALUES (354, 98, 'Shadow Fill', '#6ECAD5', 2);
INSERT INTO colors VALUES (355, 99, 'Outline', '#A6E9F9', 0);
INSERT INTO colors VALUES (356, 99, 'Fill', '#FFFFFF', 1);
INSERT INTO colors VALUES (357, 100, 'Gradient Top', '#320141', 0);
INSERT INTO colors VALUES (359, 100, 'Gradient Bottom', '#F990D1', 1);
INSERT INTO colors VALUES (360, 100, 'Highlight Top', '#D53AB6', 2);
INSERT INTO colors VALUES (361, 100, 'Highlight Bottom', '#FDCAE9', 3);
INSERT INTO colors VALUES (362, 101, 'Air Wave', '#FCFF98', 0);
INSERT INTO colors VALUES (363, 101, 'Horseshoe', '#3797C7', 1);
INSERT INTO colors VALUES (364, 99, 'Blue Fill', '#D4F3FC', 2);
INSERT INTO colors VALUES (365, 101, 'Horseshoe Dots', '#FFFFFF', 2);
INSERT INTO colors VALUES (366, 102, 'Outline', '#D8971C', 0);
INSERT INTO colors VALUES (367, 102, 'Fill', '#F3E365', 1);
INSERT INTO colors VALUES (368, 102, 'Shadow Outline', '#C18719', 2);
INSERT INTO colors VALUES (369, 102, 'Shadow Fill', '#DACB5A', 3);
INSERT INTO colors VALUES (370, 103, 'Outline', '#AC240B', 0);
INSERT INTO colors VALUES (371, 103, 'Fill 1', '#FA8A24', 1);
INSERT INTO colors VALUES (372, 104, 'Gradient Top', '#B74729', 0);
INSERT INTO colors VALUES (374, 104, 'Gradient Bottom', '#F48F66', 1);
INSERT INTO colors VALUES (377, 105, 'Outline', '#AA4300', 0);
INSERT INTO colors VALUES (378, 105, 'Fill 1', '#E37B00', 1);
INSERT INTO colors VALUES (379, 103, 'Fill 2', '#DF5C0A', 2);
INSERT INTO colors VALUES (380, 105, 'Fill 2', '#FDBF36', 2);
INSERT INTO colors VALUES (381, 104, 'Eyeshadow', '#7FA2C3', 2);
INSERT INTO colors VALUES (382, 106, 'Outline', '#75C0C8', 0);
INSERT INTO colors VALUES (383, 106, 'Fill', '#DEF2F0', 1);
INSERT INTO colors VALUES (384, 107, 'Outline', '#552E66', 0);
INSERT INTO colors VALUES (385, 107, 'Fill 1', '#743E8A', 1);
INSERT INTO colors VALUES (386, 107, 'Fill 2', '#A775BE', 2);
INSERT INTO colors VALUES (387, 107, 'Shadow Outline', '#4A295A', 3);
INSERT INTO colors VALUES (388, 107, 'Shadow Fill 1', '#69377D', 4);
INSERT INTO colors VALUES (389, 107, 'Shadow Fill 2', '#9568AA', 5);
INSERT INTO colors VALUES (390, 58, 'Hair Tie', '#EC3F41', 2);
INSERT INTO colors VALUES (392, 70, 'Cloud Fill', '#FFFFFF', 1);
INSERT INTO colors VALUES (394, 108, 'Outline', '#6BABDA', 0);
INSERT INTO colors VALUES (395, 108, 'Fill', '#9CDBF5', 1);
INSERT INTO colors VALUES (397, 108, 'Shadow Fill', '#89C7EB', 2);
INSERT INTO colors VALUES (398, 109, 'Outline', '#52574C', 0);
INSERT INTO colors VALUES (399, 109, 'Fill 1', '#8E9783', 1);
INSERT INTO colors VALUES (400, 110, 'Gradient Top', '#CB9932', 0);
INSERT INTO colors VALUES (402, 110, 'Gradient Bottom', '#FCEBA5', 1);
INSERT INTO colors VALUES (405, 111, 'Helmet Fill 1', '#48412C', 0);
INSERT INTO colors VALUES (406, 111, 'Helmet Fill 2', '#7C704C', 1);
INSERT INTO colors VALUES (407, 109, 'Fill 2', '#777B6C', 2);
INSERT INTO colors VALUES (408, 109, 'Shadow Fill', '#585B52', 3);
INSERT INTO colors VALUES (409, 110, 'Eyebrows', '#53564D', 2);
INSERT INTO colors VALUES (410, 112, 'Outline', '#324649', 0);
INSERT INTO colors VALUES (411, 112, 'Fill 1', '#507276', 1);
INSERT INTO colors VALUES (412, 112, 'Fill 2', '#A9AE82', 2);
INSERT INTO colors VALUES (413, 112, 'Sleeve Patch', '#BEC99D', 3);
INSERT INTO colors VALUES (414, 112, 'Zipper Outline', '#77ACA8', 4);
INSERT INTO colors VALUES (415, 112, 'Zipper Fill', '#C2DFE3', 5);
INSERT INTO colors VALUES (416, 112, 'Fur Outline', '#41585B', 6);
INSERT INTO colors VALUES (417, 112, 'Fur Fill', '#AAAE82', 7);
INSERT INTO colors VALUES (418, 113, 'Outline', '#D7D78C', 0);
INSERT INTO colors VALUES (419, 113, 'Fill', '#FAFCEE', 1);
INSERT INTO colors VALUES (420, 114, 'Outline', '#8E6E21', 0);
INSERT INTO colors VALUES (421, 114, 'Fill 1', '#E2D23B', 1);
INSERT INTO colors VALUES (422, 114, 'Fill 2', '#EFE89A', 2);
INSERT INTO colors VALUES (423, 111, 'Helmet Fill 3', '#C1AE73', 2);
INSERT INTO colors VALUES (424, 111, 'Helmet Shadow Fill', '#1E1D11', 3);
INSERT INTO colors VALUES (425, 111, 'Strap Fill', '#629672', 4);
INSERT INTO colors VALUES (426, 111, 'Strap Holes', '#35462A', 5);
INSERT INTO colors VALUES (427, 111, 'Wing Outline', '#D3D8A0', 6);
INSERT INTO colors VALUES (428, 111, 'Wing Fill', '#F7F8DC', 7);
INSERT INTO colors VALUES (429, 111, 'Goggles Rim', '#313131', 8);
INSERT INTO colors VALUES (430, 111, 'Goggles Fill 1', '#3AD0F5', 9);
INSERT INTO colors VALUES (431, 111, 'Goggles Fill 2', '#D7F5FB', 10);
INSERT INTO colors VALUES (432, 111, 'Goggles Fill 3 (At rim)', '#2BA9C4', 11);
INSERT INTO colors VALUES (433, 115, 'Outline', '#A3C6BC', 0);
INSERT INTO colors VALUES (434, 115, 'Fill', '#FFFFFF', 1);
INSERT INTO colors VALUES (435, 116, 'Outline', '#FD80C6', 0);
INSERT INTO colors VALUES (436, 116, 'Fill', '#FFC3E5', 1);
INSERT INTO colors VALUES (437, 116, 'Shadow Outline', '#F673B8', 2);
INSERT INTO colors VALUES (438, 116, 'Shadow Fill', '#FFB0D8', 3);
INSERT INTO colors VALUES (439, 117, 'Outline', '#533251', 0);
INSERT INTO colors VALUES (440, 117, 'Fill', '#955495', 1);
INSERT INTO colors VALUES (441, 118, 'Gradient Top', '#681D46', 0);
INSERT INTO colors VALUES (443, 118, 'Gradient Bottom', '#CD2F89', 1);
INSERT INTO colors VALUES (444, 118, 'Highlight Top', '#EB64B1', 2);
INSERT INTO colors VALUES (445, 118, 'Highlight Bottom', '#FFBFE7', 3);
INSERT INTO colors VALUES (446, 119, 'Candy 1', '#FD81BA', 0);
INSERT INTO colors VALUES (447, 119, 'Candy 2', '#FEAD89', 1);
INSERT INTO colors VALUES (448, 119, 'Candy 3', '#FFD77C', 2);
INSERT INTO colors VALUES (449, 119, 'Cup Top', '#7FC9C7', 4);
INSERT INTO colors VALUES (450, 119, 'Cup Fill 1', '#A2E3BE', 5);
INSERT INTO colors VALUES (451, 119, 'Cup Fill 2', '#BFF7C6', 6);
INSERT INTO colors VALUES (452, 119, 'Cupcake Fill 1', '#C477C6', 7);
INSERT INTO colors VALUES (453, 119, 'Cupcake Fill 2', '#E0A5E0', 8);
INSERT INTO colors VALUES (454, 119, 'Cherry Fill', '#FD696D', 9);
INSERT INTO colors VALUES (455, 119, 'Cherry Stroke', '#C8454F', 10);
INSERT INTO colors VALUES (456, 119, 'Cherry Stem', '#57334E', 11);
INSERT INTO colors VALUES (457, 119, 'Candy Highlight', '#FFFFFF', 3);
INSERT INTO colors VALUES (458, 120, 'Magic', '#47C0CC', 0);
INSERT INTO colors VALUES (459, 121, 'Outline 1', '#13A9A9', 0);
INSERT INTO colors VALUES (460, 121, 'Fill 1', '#0FDAD9', 1);
INSERT INTO colors VALUES (461, 121, 'Outline 2', '#89CBC8', 2);
INSERT INTO colors VALUES (462, 121, 'Fill 2', '#D1FEEC', 3);
INSERT INTO colors VALUES (463, 122, 'Outline', '#D9C574', 0);
INSERT INTO colors VALUES (464, 122, 'Fill', '#F3F49B', 1);
INSERT INTO colors VALUES (465, 122, 'Shadow Outline', '#D5C167', 2);
INSERT INTO colors VALUES (466, 122, 'Shadow Fill', '#E6DC7F', 3);
INSERT INTO colors VALUES (467, 123, 'Outline', '#C52452', 0);
INSERT INTO colors VALUES (468, 123, 'Fill', '#F5415F', 1);
INSERT INTO colors VALUES (469, 124, 'Gradient Top', '#ED585A', 0);
INSERT INTO colors VALUES (471, 124, 'Gradient Bottom', '#FBA93F', 1);
INSERT INTO colors VALUES (472, 124, 'Highlight Top', '#FCC657', 2);
INSERT INTO colors VALUES (473, 124, 'Highlight Bottom', '#FEE27A', 3);
INSERT INTO colors VALUES (476, 126, 'Outline', '#F37033', 0);
INSERT INTO colors VALUES (477, 126, 'Fill', '#F9B764', 1);
INSERT INTO colors VALUES (478, 126, 'Shadow Outline', '#EA6B2B', 2);
INSERT INTO colors VALUES (479, 126, 'Shadow Fill', '#F0AA56', 3);
INSERT INTO colors VALUES (480, 127, 'Outline', '#BD1F77', 0);
INSERT INTO colors VALUES (481, 127, 'Fill', '#BF5D93', 1);
INSERT INTO colors VALUES (482, 128, 'Gradient Top', '#482562', 0);
INSERT INTO colors VALUES (484, 128, 'Gradient Bottom', '#B28EC0', 1);
INSERT INTO colors VALUES (485, 128, 'Highlight Top', '#C5A6D0', 2);
INSERT INTO colors VALUES (486, 128, 'Highlight Bottom', '#E7CEE4', 3);
INSERT INTO colors VALUES (489, 130, 'Outline', '#C72965', 0);
INSERT INTO colors VALUES (490, 130, 'Fill 1', '#F35F91', 1);
INSERT INTO colors VALUES (491, 130, 'Fill 2', '#EC438C', 2);
INSERT INTO colors VALUES (492, 131, 'Outline', '#CEC8D1', 0);
INSERT INTO colors VALUES (493, 131, 'Fill', '#EFEDED', 1);
INSERT INTO colors VALUES (495, 131, 'Shadow Fill', '#E0DDE3', 2);
INSERT INTO colors VALUES (496, 132, 'Outline', '#785B88', 0);
INSERT INTO colors VALUES (497, 132, 'Fill 1', '#B28DC1', 1);
INSERT INTO colors VALUES (498, 133, 'Gradient Top', '#629558', 0);
INSERT INTO colors VALUES (500, 133, 'Gradient Bottom', '#AED79E', 1);
INSERT INTO colors VALUES (501, 133, 'Highlight Top', '#CBE4BE', 2);
INSERT INTO colors VALUES (502, 133, 'Highlight Bottom', '#F4F8ED', 3);
INSERT INTO colors VALUES (505, 132, 'Fill 2', '#F6B8D2', 2);
INSERT INTO colors VALUES (506, 135, 'Outline', '#2F5173', 0);
INSERT INTO colors VALUES (507, 135, 'Fill', '#4C7DAF', 1);
INSERT INTO colors VALUES (509, 135, 'Shadow Fill', '#406C93', 2);
INSERT INTO colors VALUES (510, 136, 'Outline', '#9BAAE8', 0);
INSERT INTO colors VALUES (511, 136, 'Fill 1', '#E1E6FA', 1);
INSERT INTO colors VALUES (512, 137, 'Gradient Top', '#1378AB', 0);
INSERT INTO colors VALUES (513, 137, 'Gradient Middle', '#47CFFF', 1);
INSERT INTO colors VALUES (514, 137, 'Gradient Bottom', '#6EDCFF', 2);
INSERT INTO colors VALUES (515, 137, 'Highlight Top', '#90E2FF', 3);
INSERT INTO colors VALUES (516, 137, 'Highlight Bottom', '#D5F3FF', 4);
INSERT INTO colors VALUES (517, 138, 'Moon 1', '#385E83', 0);
INSERT INTO colors VALUES (518, 138, 'Moon 2', '#FDFDCB', 1);
INSERT INTO colors VALUES (519, 138, 'Feather Center', '#D6E4FF', 2);
INSERT INTO colors VALUES (520, 138, 'Feather Fill 1', '#97ABEB', 3);
INSERT INTO colors VALUES (521, 138, 'Feather Fill 2', '#6A8AD2', 4);
INSERT INTO colors VALUES (522, 138, 'Feather Fill 3', '#44608D', 5);
INSERT INTO colors VALUES (523, 136, 'Fill 2', '#C4CFF4', 2);
INSERT INTO colors VALUES (524, 136, 'Fill 3', '#FFFFFF', 3);
INSERT INTO colors VALUES (525, 139, 'Outline', '#BFD1E1', 0);
INSERT INTO colors VALUES (526, 139, 'Fill', '#FFFFFF', 1);
INSERT INTO colors VALUES (527, 139, 'Shadow Outline', '#B2C7D2', 2);
INSERT INTO colors VALUES (528, 139, 'Shadow Fill', '#D6E3E5', 3);
INSERT INTO colors VALUES (529, 140, 'Outline', '#A6BFD8', 0);
INSERT INTO colors VALUES (530, 140, 'Fill 1', '#F2FBFC', 1);
INSERT INTO colors VALUES (531, 141, 'Gradient Top', '#2B4E99', 0);
INSERT INTO colors VALUES (532, 141, 'Gradient Middle', '#6E9FFC', 1);
INSERT INTO colors VALUES (533, 141, 'Gradient Bottom', '#B0EBFF', 2);
INSERT INTO colors VALUES (536, 142, 'Fill', '#87B1E6', 0);
INSERT INTO colors VALUES (537, 142, 'Inner Strokes', '#BEDBFA', 1);
INSERT INTO colors VALUES (538, 140, 'Fill 2', '#FFFFFF', 2);
INSERT INTO colors VALUES (539, 143, 'Outline', '#603860', 0);
INSERT INTO colors VALUES (540, 143, 'Fill', '#9B579B', 1);
INSERT INTO colors VALUES (541, 144, 'Outline', '#75C3D3', 0);
INSERT INTO colors VALUES (542, 144, 'Fill', '#C7F0F2', 1);
INSERT INTO colors VALUES (544, 144, 'Shadow Fill', '#A7DEE6', 2);
INSERT INTO colors VALUES (545, 145, 'Outline', '#366395', 0);
INSERT INTO colors VALUES (546, 145, 'Fill 1', '#4886CE', 1);
INSERT INTO colors VALUES (547, 146, 'Gradient Top', '#2B4E99', 0);
INSERT INTO colors VALUES (548, 146, 'Gradient Middle', '#6E9FFC', 1);
INSERT INTO colors VALUES (549, 146, 'Gradient Bottom', '#B0EBFF', 2);
INSERT INTO colors VALUES (552, 147, 'Balloon Fill 1', '#EB5095', 0);
INSERT INTO colors VALUES (553, 147, 'Balloon Fill 2', '#F36AAB', 1);
INSERT INTO colors VALUES (554, 145, 'Fill 2', '#62A8E1', 2);
INSERT INTO colors VALUES (555, 147, 'Balloon Fill 3', '#F88FC2', 2);
INSERT INTO colors VALUES (556, 147, 'Balloon Fill 4', '#FED3E9', 3);
INSERT INTO colors VALUES (557, 147, 'Confetti 1', '#7F6DAF', 4);
INSERT INTO colors VALUES (558, 147, 'Confetti 2', '#FDAE75', 5);
INSERT INTO colors VALUES (559, 147, 'Confetti 3', '#FCD269', 6);
INSERT INTO colors VALUES (560, 147, 'Confetti 4', '#FFFFFF', 7);
INSERT INTO colors VALUES (613, 164, 'Outline', '#D1A2E8', 0);
INSERT INTO colors VALUES (614, 164, 'Fill', '#FCCFFF', 1);
INSERT INTO colors VALUES (616, 164, 'Shadow Fill', '#EEC0F8', 2);
INSERT INTO colors VALUES (617, 165, 'Outline', '#683A8A', 0);
INSERT INTO colors VALUES (618, 165, 'Fill 1', '#7B45A4', 1);
INSERT INTO colors VALUES (619, 166, 'Gradient Top', '#362C91', 0);
INSERT INTO colors VALUES (621, 166, 'Gradient Bottom', '#A0A6FD', 1);
INSERT INTO colors VALUES (622, 166, 'Highlight Top', '#9996FB', 2);
INSERT INTO colors VALUES (623, 166, 'Highlight Bottom', '#C5C4FD', 3);
INSERT INTO colors VALUES (624, 167, 'Star Fill 1', '#CB79CD', 0);
INSERT INTO colors VALUES (625, 167, 'Star Fill 2', '#FDFDF2', 1);
INSERT INTO colors VALUES (626, 165, 'Fill 2', '#9952C9', 2);
INSERT INTO colors VALUES (627, 165, 'Fill 3', '#A4EDE1', 3);
INSERT INTO colors VALUES (628, 167, 'Trail Fill 1', '#19C8C7', 2);
INSERT INTO colors VALUES (629, 167, 'Trail Fill 2', '#A4F1E2', 3);
INSERT INTO colors VALUES (630, 168, 'Magic', '#70DACC', 0);
INSERT INTO colors VALUES (631, 169, 'Outline', '#E9CDA4', 0);
INSERT INTO colors VALUES (632, 169, 'Fill', '#FBF4DF', 1);
INSERT INTO colors VALUES (633, 169, 'Shadow Outline', '#DEC098', 2);
INSERT INTO colors VALUES (634, 169, 'Shadow Fill', '#EFE2CA', 3);
INSERT INTO colors VALUES (635, 170, 'Outline', '#45C8D5', 0);
INSERT INTO colors VALUES (636, 170, 'Fill 1', '#96E5EF', 1);
INSERT INTO colors VALUES (637, 171, 'Gradient Top', '#26A9BF', 0);
INSERT INTO colors VALUES (639, 171, 'Gradient Bottom', '#D7FFFA', 1);
INSERT INTO colors VALUES (640, 171, 'Highlight Top', '#C3F4FD', 2);
INSERT INTO colors VALUES (641, 171, 'Highlight Bottom', '#EFFCFC', 3);
INSERT INTO colors VALUES (642, 172, 'Hat Fill 1', '#D39CD1', 0);
INSERT INTO colors VALUES (643, 172, 'Hat Fill 2', '#B674BC', 1);
INSERT INTO colors VALUES (644, 170, 'Fill 2', '#CCF9F9', 2);
INSERT INTO colors VALUES (645, 173, 'Collar Outline', '#985C9D', 0);
INSERT INTO colors VALUES (646, 173, 'Collar Fill 1', '#D39CD1', 1);
INSERT INTO colors VALUES (647, 173, 'Collar Fill 2', '#FBFBFB', 2);
INSERT INTO colors VALUES (648, 173, 'Collar Shadow Fill', '#B674BC', 3);
INSERT INTO colors VALUES (649, 173, 'Tie Outline', '#E64E07', 4);
INSERT INTO colors VALUES (650, 173, 'Tie Fill', '#FF7D74', 5);
INSERT INTO colors VALUES (651, 174, 'Main Outline', '#E64E07', 0);
INSERT INTO colors VALUES (652, 174, 'Main Fill 1', '#FF7D74', 1);
INSERT INTO colors VALUES (653, 174, 'Main Fill 2', '#FFA085', 2);
INSERT INTO colors VALUES (654, 174, 'Blossom Outline', '#6C3F6B', 3);
INSERT INTO colors VALUES (655, 174, 'Blossom Outside Fill', '#985C9D', 4);
INSERT INTO colors VALUES (656, 174, 'Blossom Inside Fill 1', '#955E9C', 5);
INSERT INTO colors VALUES (657, 174, 'Blossom Inside Fill 2', '#D39CD1', 6);
INSERT INTO colors VALUES (658, 172, 'Hat Fill 3', '#FBFBFB', 2);
INSERT INTO colors VALUES (659, 172, 'Plume Fill', '#FF5555', 3);
INSERT INTO colors VALUES (660, 172, 'Plume Shadow', '#F24C4C', 4);
INSERT INTO colors VALUES (661, 175, 'Outline', '#151730', 0);
INSERT INTO colors VALUES (662, 175, 'Fill', '#38407C', 1);
INSERT INTO colors VALUES (663, 175, 'Shadow Fill', '#30376C', 2);
INSERT INTO colors VALUES (664, 176, 'Outline', '#8952E1', 0);
INSERT INTO colors VALUES (665, 176, 'Edge Fill (50% opacity)', '#473CC7', 1);
INSERT INTO colors VALUES (666, 176, 'Main Fill', '#1C4FC2', 2);
INSERT INTO colors VALUES (667, 177, 'Outline', '#9791F1', 0);
INSERT INTO colors VALUES (668, 177, 'Fill', '#C4BFF9', 1);
INSERT INTO colors VALUES (669, 177, 'Shadow Outline', '#7E79CB', 2);
INSERT INTO colors VALUES (670, 177, 'Shadow Fill', '#9F9DCE', 3);
INSERT INTO colors VALUES (671, 178, 'Outline', '#9F9DCE', 0);
INSERT INTO colors VALUES (672, 178, 'Fill', '#000000', 1);
INSERT INTO colors VALUES (673, 178, 'Moon', '#FFFFFF', 2);
INSERT INTO colors VALUES (674, 179, 'Dark Side of the Moon', '#000000', 0);
INSERT INTO colors VALUES (675, 179, 'Moon', '#FFFFFF', 1);
INSERT INTO colors VALUES (676, 176, 'Stars', '#FFFFFF', 3);
INSERT INTO colors VALUES (677, 180, 'Gradient Top', '#0E6183', 0);
INSERT INTO colors VALUES (678, 180, 'Gradient Bottom', '#06A79F', 1);
INSERT INTO colors VALUES (679, 180, 'Highlight Top', '#61E9D1', 2);
INSERT INTO colors VALUES (680, 180, 'Highlight Bottom', '#9AFCFD', 3);
INSERT INTO colors VALUES (681, 180, 'Eyeshadow', '#7696ED', 4);
INSERT INTO colors VALUES (683, 182, 'Outline', '#FA9EDE', 0);
INSERT INTO colors VALUES (684, 182, 'Fill', '#FFD9FB', 1);
INSERT INTO colors VALUES (685, 182, 'Shadow Outline', '#F591D7', 2);
INSERT INTO colors VALUES (686, 182, 'Shadow Fill', '#FDBBEC', 3);
INSERT INTO colors VALUES (687, 183, 'Outline', '#6B5584', 0);
INSERT INTO colors VALUES (688, 183, 'Fill', '#9775B8', 1);
INSERT INTO colors VALUES (689, 184, 'Gradient Top', '#584239', 0);
INSERT INTO colors VALUES (690, 184, 'Gradient Middle', '#A87A66', 1);
INSERT INTO colors VALUES (691, 184, 'Gradient Bottom', '#DFB77D', 2);
INSERT INTO colors VALUES (692, 184, 'Highlight Top', '#CEA06F', 3);
INSERT INTO colors VALUES (693, 184, 'Highlight Bottom', '#E2C6AB', 4);
INSERT INTO colors VALUES (694, 185, 'Button 1 main', '#9776B8', 0);
INSERT INTO colors VALUES (695, 185, 'Button 1 holes', '#43324C', 1);
INSERT INTO colors VALUES (696, 186, 'Outline', '#5EC3EF', 0);
INSERT INTO colors VALUES (697, 186, 'Main fill', '#C3F6FF', 1);
INSERT INTO colors VALUES (698, 186, 'Stripes', '#7CD7F9', 2);
INSERT INTO colors VALUES (699, 186, 'Dots', '#FBFBFB', 3);
INSERT INTO colors VALUES (700, 187, 'Outline', '#F6DDA5', 0);
INSERT INTO colors VALUES (701, 187, 'Fill', '#FFF9D8', 1);
INSERT INTO colors VALUES (702, 185, 'Button 1 rim', '#72528C', 2);
INSERT INTO colors VALUES (703, 185, 'Button 2 main', '#FFF8D8', 3);
INSERT INTO colors VALUES (704, 185, 'Button 2 holes', '#8E734D', 4);
INSERT INTO colors VALUES (705, 185, 'Button 2 rim', '#EFDEAD', 5);
INSERT INTO colors VALUES (706, 185, 'Button 3 main', '#C4F5FF', 6);
INSERT INTO colors VALUES (707, 185, 'Button 3 holes', '#2F6E87', 7);
INSERT INTO colors VALUES (708, 185, 'Button 3 rim', '#6ABFE3', 8);
INSERT INTO colors VALUES (709, 188, 'Aura', '#D9539D', 0);
INSERT INTO colors VALUES (710, 189, 'Aura', '#E8DA9C', 0);
INSERT INTO colors VALUES (711, 190, 'Aura', '#6BF0ED', 0);
INSERT INTO colors VALUES (712, 191, 'Outline', '#338FCC', 0);
INSERT INTO colors VALUES (713, 191, 'Fill', '#6CB2EA', 1);
INSERT INTO colors VALUES (715, 191, 'Shadow Fill', '#5DAAE3', 2);
INSERT INTO colors VALUES (716, 192, 'Outline', '#92CDF4', 0);
INSERT INTO colors VALUES (717, 192, 'Fill 1', '#B8E0F9', 1);
INSERT INTO colors VALUES (718, 193, 'Gradient Top', '#3A2851', 0);
INSERT INTO colors VALUES (719, 193, 'Gradient Middle', '#6E4386', 1);
INSERT INTO colors VALUES (720, 193, 'Gradient Bottom', '#E0A0D3', 2);
INSERT INTO colors VALUES (723, 194, 'Wand Star Outline', '#B7E0FA', 0);
INSERT INTO colors VALUES (724, 194, 'Wand Star Fill', '#71A2CD', 1);
INSERT INTO colors VALUES (725, 192, 'Fill 2', '#D8EEFB', 2);
INSERT INTO colors VALUES (726, 194, 'Wand Handle Fill 1', '#6EA3D3', 2);
INSERT INTO colors VALUES (727, 194, 'Wand Handle Fill 2', '#ADCCEA', 3);
INSERT INTO colors VALUES (728, 194, 'Trail Fill', '#82DEF4', 4);
INSERT INTO colors VALUES (729, 194, 'Trail Stars', '#FFFFFF', 5);
INSERT INTO colors VALUES (730, 195, 'Outline', '#7B5ACE', 0);
INSERT INTO colors VALUES (731, 195, 'Fill', '#9C82DA', 1);
INSERT INTO colors VALUES (732, 195, 'Stars 1', '#FAF99D', 2);
INSERT INTO colors VALUES (733, 195, 'Stars 2', '#82DDF4', 3);
INSERT INTO colors VALUES (734, 195, 'Stars 3', '#B7E0F9', 4);
INSERT INTO colors VALUES (735, 195, 'Stars 4', '#D7ECFA', 5);
INSERT INTO colors VALUES (736, 196, 'Outline', '#2C84A0', 0);
INSERT INTO colors VALUES (737, 196, 'Center Fill', '#BFEFFA', 1);
INSERT INTO colors VALUES (738, 196, 'Fill 1', '#32A2BA', 2);
INSERT INTO colors VALUES (739, 196, 'Fill 2', '#3ABDDC', 3);
INSERT INTO colors VALUES (740, 196, 'Fill 3', '#82DDF4', 4);
INSERT INTO colors VALUES (741, 196, 'Fill 4', '#A7F8E0', 5);
INSERT INTO colors VALUES (742, 196, 'Fill 5', '#CDF1FB', 6);
INSERT INTO colors VALUES (743, 197, 'Aura', '#E3B2DA', 0);
INSERT INTO colors VALUES (744, 198, 'Outline', '#161515', 0);
INSERT INTO colors VALUES (745, 198, 'Fill', '#5E5E5E', 1);
INSERT INTO colors VALUES (746, 198, 'Horn Inner Strokes', '#B7B7B7', 2);
INSERT INTO colors VALUES (747, 198, 'Eye Stroke', '#6E0303', 3);
INSERT INTO colors VALUES (748, 198, 'Eye Fill', '#DA0101', 4);
INSERT INTO colors VALUES (749, 199, 'Outline', '#161515', 0);
INSERT INTO colors VALUES (750, 199, 'Fill 1', '#434343', 1);
INSERT INTO colors VALUES (751, 199, 'Fill 2', '#5E5E5E', 2);
INSERT INTO colors VALUES (752, 199, 'Fill 3', '#8C8C8C', 3);
INSERT INTO colors VALUES (753, 200, 'Outline', '#161515', 0);
INSERT INTO colors VALUES (754, 200, 'Fill', '#333333', 1);
INSERT INTO colors VALUES (755, 200, 'Red Outline', '#6E0303', 2);
INSERT INTO colors VALUES (756, 200, 'Red Fill 1', '#B10101', 3);
INSERT INTO colors VALUES (757, 200, 'Red Fill 2', '#DA0101', 4);
INSERT INTO colors VALUES (758, 200, 'Red Fill 3', '#FD080B', 5);
INSERT INTO colors VALUES (759, 201, 'Outline', '#6E0303', 0);
INSERT INTO colors VALUES (760, 201, 'Center Fill', '#FCB3B3', 1);
INSERT INTO colors VALUES (761, 201, 'Fill 1', '#B10101', 2);
INSERT INTO colors VALUES (762, 201, 'Fill 2', '#FD080B', 3);
INSERT INTO colors VALUES (763, 201, 'Fill 3', '#FE9292', 4);
INSERT INTO colors VALUES (764, 202, 'Magic Aura/Eye Aura', '#FD080B', 0);
INSERT INTO colors VALUES (765, 202, 'Iris', '#B10101', 1);
INSERT INTO colors VALUES (767, 203, 'Outline', '#161515', 0);
INSERT INTO colors VALUES (768, 203, 'Fill 1', '#434343', 1);
INSERT INTO colors VALUES (769, 203, 'Fill 2', '#333333', 2);
INSERT INTO colors VALUES (770, 203, 'Highlight', '#B7B7B7', 3);
INSERT INTO colors VALUES (771, 204, 'Outline', '#EECFE1', 0);
INSERT INTO colors VALUES (772, 204, 'Fill', '#FEF7FB', 1);
INSERT INTO colors VALUES (774, 204, 'Shadow Fill', '#F2E6E9', 2);
INSERT INTO colors VALUES (775, 205, 'Gradient Outline 1', '#3D9DC4', 0);
INSERT INTO colors VALUES (776, 205, 'Gradient Outline 2', '#48BAA9', 1);
INSERT INTO colors VALUES (777, 206, 'Gradient Top', '#5F2F7A', 0);
INSERT INTO colors VALUES (779, 206, 'Gradient Bottom', '#E68CE3', 1);
INSERT INTO colors VALUES (780, 206, 'Highlight Top', '#CB7DD0', 2);
INSERT INTO colors VALUES (781, 206, 'Highlight Bottom', '#D8A0D9', 3);
INSERT INTO colors VALUES (782, 207, 'Sun Center', '#FDF5B7', 0);
INSERT INTO colors VALUES (783, 207, 'Sun Edge', '#FDD68F', 1);
INSERT INTO colors VALUES (784, 208, 'Aura', '#FEFD96', 0);
INSERT INTO colors VALUES (785, 207, 'Sun Rays', '#FAC18A', 2);
INSERT INTO colors VALUES (786, 205, 'Gradient Outline 3', '#7A9BDE', 2);
INSERT INTO colors VALUES (787, 205, 'Gradient Outline 4', '#D085D0', 3);
INSERT INTO colors VALUES (788, 205, 'Gradient Dark Fill 1', '#44B1CE', 4);
INSERT INTO colors VALUES (789, 205, 'Gradient Light Fill 1', '#8CDEE4', 5);
INSERT INTO colors VALUES (790, 205, 'Gradient Dark Fill 2', '#50CDA5', 6);
INSERT INTO colors VALUES (791, 205, 'Gradient Light Fill 2', '#CBF5C0', 7);
INSERT INTO colors VALUES (792, 205, 'Gradient Dark Fill 3', '#80A4EE', 8);
INSERT INTO colors VALUES (793, 205, 'Gradient Light Fill 3', '#AEDEFC', 9);
INSERT INTO colors VALUES (794, 205, 'Gradient Dark Fill 4', '#E599F2', 10);
INSERT INTO colors VALUES (795, 205, 'Gradient Light Fill 4', '#F2C4FD', 11);
INSERT INTO colors VALUES (796, 209, 'Outline & Trim', '#E9BF6E', 0);
INSERT INTO colors VALUES (797, 209, 'Fill', '#FFE398', 1);
INSERT INTO colors VALUES (798, 209, 'Gem Outline', '#6E3B7F', 2);
INSERT INTO colors VALUES (799, 209, 'Gem Center Fill', '#C671D0', 3);
INSERT INTO colors VALUES (800, 209, 'Gem Shine', '#DE9FE2', 4);
INSERT INTO colors VALUES (801, 209, 'Gem Saturated Fill', '#BE62C8', 5);
INSERT INTO colors VALUES (802, 209, 'Gem Main Fill', '#9E5DB5', 6);
INSERT INTO colors VALUES (803, 209, 'Gem Dark Fill', '#824997', 7);
INSERT INTO colors VALUES (804, 210, 'Outline', '#EECFE1', 0);
INSERT INTO colors VALUES (805, 210, 'Fill', '#FFEFBC', 1);
INSERT INTO colors VALUES (806, 210, 'Shadow Fill', '#F5E1C7', 2);
INSERT INTO colors VALUES (807, 211, 'Coat Outline', '#A32141', 0);
INSERT INTO colors VALUES (808, 211, 'Coat Fill', '#E64A57', 1);
INSERT INTO colors VALUES (809, 211, 'Shadow Coat Outline', '#9E164C', 2);
INSERT INTO colors VALUES (810, 211, 'Shadow Coat Fill', '#D0335C', 3);
INSERT INTO colors VALUES (811, 211, 'Hooves Outline', '#EACA79', 4);
INSERT INTO colors VALUES (812, 211, 'Hooves Fill', '#FCF8AC', 5);
INSERT INTO colors VALUES (813, 211, 'Shadow Hooves Outline', '#CF8D74', 6);
INSERT INTO colors VALUES (814, 211, 'Shadow Hooves Fill', '#DFAD96', 7);
INSERT INTO colors VALUES (815, 212, 'Outline', '#F08E43', 0);
INSERT INTO colors VALUES (816, 212, 'Fill', '#FBBA62', 1);
INSERT INTO colors VALUES (817, 213, 'Gradient top', '#267A14', 0);
INSERT INTO colors VALUES (818, 213, 'Gradient bottom', '#5FBB4B', 1);
INSERT INTO colors VALUES (819, 214, 'Freckles', '#FCF6AD', 0);
INSERT INTO colors VALUES (820, 215, 'Yoke outline', '#876235', 0);
INSERT INTO colors VALUES (821, 215, 'Yoke fill 1', '#C8954D', 1);
INSERT INTO colors VALUES (822, 215, 'Post fill 1', '#A2AF9D', 3);
INSERT INTO colors VALUES (823, 215, 'Post fill 2', '#919E8D', 4);
INSERT INTO colors VALUES (824, 215, 'Post fill 3', '#C2D9C4', 5);
INSERT INTO colors VALUES (825, 215, 'Yoke fill 2', '#AE8241', 2);
INSERT INTO colors VALUES (826, 216, 'Apple fill 1', '#87D557', 0);
INSERT INTO colors VALUES (827, 216, 'Apple fill 2', '#B6F677', 1);
INSERT INTO colors VALUES (828, 216, 'Apple fill 3', '#5EBA4A', 2);
INSERT INTO colors VALUES (829, 216, 'Seeds', '#AD8341', 3);
INSERT INTO colors VALUES (830, 216, 'Apple stem 1', '#DAFC87', 4);
INSERT INTO colors VALUES (831, 216, 'Apple stem 2 (=Apple fill 1)', '#87D557', 5);
INSERT INTO colors VALUES (832, 216, 'Apple leaf 1 (=Apple fill 3)', '#5EBA4A', 6);
INSERT INTO colors VALUES (833, 216, 'Apple leaf 2', '#309438', 7);
INSERT INTO colors VALUES (834, 217, 'Outline', '#DBBD5D', 0);
INSERT INTO colors VALUES (835, 217, 'Fill', '#F9F8D3', 1);
INSERT INTO colors VALUES (836, 217, 'Shadow Outline', '#C4A953', 2);
INSERT INTO colors VALUES (837, 217, 'Shadow Fill', '#E0DEBE', 3);
INSERT INTO colors VALUES (838, 218, 'Outline', '#9D2544', 0);
INSERT INTO colors VALUES (839, 218, 'Fill', '#E0535D', 1);
INSERT INTO colors VALUES (840, 219, 'Gradient Top', '#200B3E', 0);
INSERT INTO colors VALUES (842, 219, 'Gradient Bottom', '#622E87', 1);
INSERT INTO colors VALUES (843, 219, 'Highlight Top', '#8B5DA4', 2);
INSERT INTO colors VALUES (844, 219, 'Highlight Bottom', '#CBB2D3', 3);
INSERT INTO colors VALUES (845, 220, 'Color 1', '#663E64', 0);
INSERT INTO colors VALUES (846, 220, 'Color 2', '#C670B7', 1);
INSERT INTO colors VALUES (847, 218, 'Stripe 1', '#78549A', 2);
INSERT INTO colors VALUES (848, 218, 'Stripe 2', '#AD90D0', 3);
INSERT INTO colors VALUES (849, 221, 'Outline', '#1D1C2B', 0);
INSERT INTO colors VALUES (850, 221, 'Fill 1', '#454163', 1);
INSERT INTO colors VALUES (851, 221, 'Fill 2', '#34324C', 2);
INSERT INTO colors VALUES (852, 221, 'Shadow Fill 1', '#3D3B56', 3);
INSERT INTO colors VALUES (853, 221, 'Shadow Fill 2', '#2F2D44', 4);
INSERT INTO colors VALUES (854, 222, 'Outline', '#CF6995', 0);
INSERT INTO colors VALUES (855, 222, 'Fill', '#FCA1CB', 1);
INSERT INTO colors VALUES (856, 223, 'Frame', '#181723', 0);
INSERT INTO colors VALUES (857, 223, 'Frame Highlight', '#565175', 1);
INSERT INTO colors VALUES (858, 223, 'Pin Outline', '#B5B2D1', 2);
INSERT INTO colors VALUES (859, 223, 'Pin Fill', '#F0F2F8', 3);
INSERT INTO colors VALUES (860, 223, 'Tape Outline', '#87BACB', 4);
INSERT INTO colors VALUES (861, 223, 'Tape Fill', '#F8F9F1', 5);
INSERT INTO colors VALUES (862, 223, 'Lens ( 80% to 25% Opacity)', '#BEDAF4', 6);
INSERT INTO colors VALUES (863, 224, 'Aura color', '#E3BFD0', 0);
INSERT INTO colors VALUES (864, 215, 'Post fill 4 (screwtop on back)', '#6E7969', 6);
INSERT INTO colors VALUES (865, 225, 'Outline', '#A985D3', 0);
INSERT INTO colors VALUES (866, 225, 'Fill', '#CDB8E6', 1);
INSERT INTO colors VALUES (868, 225, 'Shadow Fill', '#BFA3DF', 2);
INSERT INTO colors VALUES (869, 226, 'Outline', '#D3B76A', 0);
INSERT INTO colors VALUES (870, 226, 'Fill 1', '#F7F0A6', 1);
INSERT INTO colors VALUES (871, 227, 'Gradient Top', '#C79835', 0);
INSERT INTO colors VALUES (873, 227, 'Gradient Bottom', '#FCF89A', 1);
INSERT INTO colors VALUES (878, 226, 'Fill 2', '#F8F8D5', 2);
INSERT INTO colors VALUES (879, 229, 'Outline', '#D76CA0', 0);
INSERT INTO colors VALUES (880, 229, 'Fill', '#E8A3C5', 1);
INSERT INTO colors VALUES (882, 229, 'Shadow Fill', '#E48CB7', 2);
INSERT INTO colors VALUES (883, 230, 'Outline', '#A3376E', 0);
INSERT INTO colors VALUES (884, 230, 'Fill 1', '#C24D87', 1);
INSERT INTO colors VALUES (885, 231, 'Gradient Top', '#78952C', 0);
INSERT INTO colors VALUES (887, 231, 'Gradient Bottom', '#D3EAAB', 1);
INSERT INTO colors VALUES (888, 231, 'Highlight Top', '#C6F2A6', 2);
INSERT INTO colors VALUES (889, 231, 'Highlight Bottom', '#EFFBD5', 3);
INSERT INTO colors VALUES (892, 230, 'Fill 2', '#CF78A4', 2);
INSERT INTO colors VALUES (893, 227, 'Highlight Top', '#FAFAB4', 2);
INSERT INTO colors VALUES (894, 227, 'Highlight Bottom', '#FDFDDB', 3);
INSERT INTO colors VALUES (895, 233, 'Shield Outline', '#AB3266', 0);
INSERT INTO colors VALUES (896, 233, 'Shield Fill 1: AB Mane Fill', '#F5415F', 1);
INSERT INTO colors VALUES (897, 233, 'Shield Fill 2: SB Mane Fill', '#F6B8D2', 2);
INSERT INTO colors VALUES (898, 233, 'Shield Fill 3: Sc Mane Fill', '#BF5D93', 3);
INSERT INTO colors VALUES (899, 233, 'Apple Fill', '#A02699', 4);
INSERT INTO colors VALUES (900, 233, 'Heart Fill', '#FD41F8', 5);
INSERT INTO colors VALUES (901, 234, 'Outline', '#845223', 0);
INSERT INTO colors VALUES (902, 234, 'Fill', '#C58E4F', 1);
INSERT INTO colors VALUES (903, 234, 'Shadow Outline', '#855C37', 2);
INSERT INTO colors VALUES (904, 234, 'Shadow Fill', '#BE9061', 3);
INSERT INTO colors VALUES (905, 235, 'Outline', '#613213', 0);
INSERT INTO colors VALUES (906, 235, 'Fill 1', '#C44933', 1);
INSERT INTO colors VALUES (907, 236, 'Gradient Top', '#B34236', 0);
INSERT INTO colors VALUES (909, 236, 'Gradient Bottom', '#FF814D', 1);
INSERT INTO colors VALUES (910, 236, 'Highlight Top', '#FE9863', 2);
INSERT INTO colors VALUES (911, 236, 'Highlight Bottom', '#FFEAD1', 3);
INSERT INTO colors VALUES (914, 235, 'Fill 2', '#E47743', 2);
INSERT INTO colors VALUES (925, 240, 'Outline', '#EF60A1', 0);
INSERT INTO colors VALUES (926, 240, 'Fill', '#FFB2D3', 1);
INSERT INTO colors VALUES (928, 240, 'Shadow Fill', '#FA92BE', 2);
INSERT INTO colors VALUES (929, 241, 'Outline', '#7A2682', 0);
INSERT INTO colors VALUES (930, 241, 'Fill 1', '#B739A6', 1);
INSERT INTO colors VALUES (931, 242, 'Gradient Top', '#128B8F', 0);
INSERT INTO colors VALUES (933, 242, 'Gradient Bottom', '#4AB1C4', 1);
INSERT INTO colors VALUES (934, 242, 'Highlight Top', '#8EEDF5', 2);
INSERT INTO colors VALUES (935, 242, 'Highlight Bottom', '#DDEFF5', 3);
INSERT INTO colors VALUES (936, 243, 'Body Fill', '#FCEECF', 0);
INSERT INTO colors VALUES (937, 243, 'Body Shadow Fill', '#EBCE96', 1);
INSERT INTO colors VALUES (938, 241, 'Fill 2', '#A031A8', 2);
INSERT INTO colors VALUES (939, 241, 'Band', '#51C8B8', 3);
INSERT INTO colors VALUES (940, 243, 'Eye', '#49341D', 2);
INSERT INTO colors VALUES (941, 243, 'Nose', '#7A4A52', 3);
INSERT INTO colors VALUES (942, 243, 'Quills Fill', '#996448', 4);
INSERT INTO colors VALUES (943, 243, 'Quills Highlight 1', '#F1C2A7', 5);
INSERT INTO colors VALUES (944, 243, 'Quills Highlight 2', '#7E4B3E', 6);
INSERT INTO colors VALUES (945, 244, 'Shield Outline', '#AB3266', 0);
INSERT INTO colors VALUES (946, 244, 'Shield Fill 1: AB Mane Fill', '#F5415F', 1);
INSERT INTO colors VALUES (947, 244, 'Shield Fill 2: SB Mane Fill', '#F6B8D2', 2);
INSERT INTO colors VALUES (948, 244, 'Shield Fill 3: Sc Mane Fill', '#BF5D93', 3);
INSERT INTO colors VALUES (949, 244, 'Wing Fill', '#A02699', 4);
INSERT INTO colors VALUES (950, 244, 'Lightning Bolt Fill', '#FD41F8', 5);
INSERT INTO colors VALUES (951, 245, 'Shield Outline', '#AB3266', 0);
INSERT INTO colors VALUES (952, 245, 'Shield Fill 1: AB Mane Fill', '#F5415F', 1);
INSERT INTO colors VALUES (953, 245, 'Shield Fill 2: SB Mane Fill', '#F6B8D2', 2);
INSERT INTO colors VALUES (954, 245, 'Shield Fill 3: Sc Mane Fill', '#BF5D93', 3);
INSERT INTO colors VALUES (955, 245, 'Star Fill', '#A02699', 4);
INSERT INTO colors VALUES (956, 245, 'Eight Note Fill', '#FD41F8', 5);
INSERT INTO colors VALUES (957, 246, 'Aura', '#C4FBB0', 0);
INSERT INTO colors VALUES (991, 257, 'Outline', '#9C9AAD', 0);
INSERT INTO colors VALUES (992, 257, 'Fill', '#FEFEFE', 1);
INSERT INTO colors VALUES (993, 257, 'Shadow Outline', '#9291A3', 2);
INSERT INTO colors VALUES (994, 257, 'Shadow Fill', '#F1F1F1', 3);
INSERT INTO colors VALUES (995, 258, 'Outline', '#101C2F', 0);
INSERT INTO colors VALUES (996, 258, 'Fill', '#325394', 1);
INSERT INTO colors VALUES (997, 259, 'Gradient Top', '#1A4859', 1);
INSERT INTO colors VALUES (999, 259, 'Gradient Bottom', '#63D1E9', 2);
INSERT INTO colors VALUES (1000, 259, 'Highlight Top', '#3CBDF1', 3);
INSERT INTO colors VALUES (1001, 259, 'Highlight Bottom', '#B1E8F2', 4);
INSERT INTO colors VALUES (1002, 260, 'Stars', '#6698CA', 0);
INSERT INTO colors VALUES (1003, 260, 'Shield Outline', '#111B26', 1);
INSERT INTO colors VALUES (1004, 257, 'Hoof Outline', '#2D3558', 4);
INSERT INTO colors VALUES (1005, 257, 'Hoof Fill', '#43558C', 5);
INSERT INTO colors VALUES (1006, 257, 'Shadow Hoof Outline', '#233250', 6);
INSERT INTO colors VALUES (1007, 257, 'Shadow Hoof Fill', '#3F5085', 7);
INSERT INTO colors VALUES (1008, 258, 'Stripe 1', '#57C4DD', 2);
INSERT INTO colors VALUES (1009, 258, 'Stripe 2', '#243871', 3);
INSERT INTO colors VALUES (1010, 259, 'Eyeball', '#E8F8F9', 0);
INSERT INTO colors VALUES (1011, 260, 'Shield Fill', '#243871', 2);
INSERT INTO colors VALUES (1012, 260, 'Shield Star Pattern', '#ED6C9C', 3);
INSERT INTO colors VALUES (1013, 261, 'Aura (Same as Twilight''s)', '#EA428B', 0);
INSERT INTO colors VALUES (1014, 262, 'Outline', '#9CBDDB', 0);
INSERT INTO colors VALUES (1015, 262, 'Fill', '#E6EFFC', 1);
INSERT INTO colors VALUES (1017, 262, 'Shadow Fill', '#D7E3F4', 2);
INSERT INTO colors VALUES (1018, 263, 'Outline', '#2864BC', 0);
INSERT INTO colors VALUES (1019, 263, 'Fill 1', '#3DA9FF', 1);
INSERT INTO colors VALUES (1020, 264, 'Gradient Top', '#FF8D38', 0);
INSERT INTO colors VALUES (1022, 264, 'Gradient Bottom', '#F6DF58', 1);
INSERT INTO colors VALUES (1023, 264, 'Highlight Top', '#F9E98C', 2);
INSERT INTO colors VALUES (1024, 264, 'Highlight Bottom', '#FCF1BD', 3);
INSERT INTO colors VALUES (1025, 265, 'Pen Nib Outline', '#C4AA1D', 0);
INSERT INTO colors VALUES (1026, 265, 'Pen Nib Fill', '#FCDF58', 1);
INSERT INTO colors VALUES (1027, 263, 'Fill 2', '#94D4FF', 2);
INSERT INTO colors VALUES (1028, 265, 'Node Fill', '#3DA9FF', 2);
INSERT INTO colors VALUES (1029, 265, 'Path Fill', '#000000', 3);
INSERT INTO colors VALUES (1030, 266, 'Aura', '#FCDF58', 0);
INSERT INTO colors VALUES (1031, 267, 'Outline', '#AC6600', 0);
INSERT INTO colors VALUES (1032, 267, 'Fill', '#FFB240', 1);
INSERT INTO colors VALUES (1033, 267, 'Shadow Outline', '#9D5700', 2);
INSERT INTO colors VALUES (1034, 267, 'Shadow Fill', '#F7A731', 3);
INSERT INTO colors VALUES (1035, 268, 'Outline', '#251100', 0);
INSERT INTO colors VALUES (1036, 268, 'Fill', '#683A00', 1);
INSERT INTO colors VALUES (1037, 269, 'Gradient Top', '#275D00', 0);
INSERT INTO colors VALUES (1039, 269, 'Gradient Bottom', '#8CEA4D', 1);
INSERT INTO colors VALUES (1042, 270, 'Crust', '#744126', 0);
INSERT INTO colors VALUES (1043, 270, 'Bread', '#E1D4AC', 1);
INSERT INTO colors VALUES (1044, 271, 'Outline', '#9C8300', 0);
INSERT INTO colors VALUES (1045, 271, 'Fill', '#FFBE39', 1);
INSERT INTO colors VALUES (1046, 272, 'Frame', '#000000', 0);
INSERT INTO colors VALUES (1047, 270, 'Crust spots', '#BE916D', 2);
INSERT INTO colors VALUES (916, 238, 'Hat Fill 1', '#FAC443', 1);
INSERT INTO colors VALUES (917, 238, 'Hat Fill 2', '#FBFBB4', 2);
INSERT INTO colors VALUES (918, 238, 'Hat Fill 3', '#F35356', 3);
INSERT INTO colors VALUES (919, 238, 'Hat Fill 4', '#FFFFFF', 4);
INSERT INTO colors VALUES (920, 238, 'Propeller Outline', '#63A214', 5);
INSERT INTO colors VALUES (921, 238, 'Propeller Fill', '#A3E34C', 6);
INSERT INTO colors VALUES (922, 238, 'Rotor Outline', '#746DC3', 7);
INSERT INTO colors VALUES (923, 238, 'Rotor Fill', '#BEBDF7', 8);
INSERT INTO colors VALUES (1048, 270, 'Bread spots', '#8E7A50', 3);
INSERT INTO colors VALUES (1049, 270, 'Grill marks', '#25150D', 4);
INSERT INTO colors VALUES (1050, 270, 'Cheese', '#FFFF13', 5);
INSERT INTO colors VALUES (1051, 270, 'Toasting Gradient (0 to 85%)', '#000000', 6);
INSERT INTO colors VALUES (1078, 281, 'Outline', '#778282', 0);
INSERT INTO colors VALUES (1079, 281, 'Fill', '#C2CDCD', 1);
INSERT INTO colors VALUES (1081, 281, 'Shadow Fill', '#A1ADAD', 2);
INSERT INTO colors VALUES (1084, 283, 'Gradient Top', '#713A9D', 0);
INSERT INTO colors VALUES (1086, 283, 'Gradient Bottom', '#B792DD', 1);
INSERT INTO colors VALUES (1087, 283, 'Highlight Top', '#B795E9', 2);
INSERT INTO colors VALUES (1088, 283, 'Highlight Bottom', '#EDE6F4', 3);
INSERT INTO colors VALUES (1089, 284, 'Color 1', '#9D8CB0', 0);
INSERT INTO colors VALUES (1090, 284, 'Color 2', '#88799C', 1);
INSERT INTO colors VALUES (1093, 285, 'Outline', '#545173', 0);
INSERT INTO colors VALUES (1094, 285, 'Fill', '#9495B9', 1);
INSERT INTO colors VALUES (1096, 285, 'Shadow Fill', '#77769B', 2);
INSERT INTO colors VALUES (1097, 286, 'Outline', '#6B7B7E', 0);
INSERT INTO colors VALUES (1098, 286, 'Fill', '#C7CECF', 1);
INSERT INTO colors VALUES (1099, 287, 'Gradient Top', '#9AA41C', 0);
INSERT INTO colors VALUES (1101, 287, 'Gradient Bottom', '#DDE261', 1);
INSERT INTO colors VALUES (1102, 287, 'Highlight Top', '#F2EE86', 2);
INSERT INTO colors VALUES (1103, 287, 'Highlight Bottom', '#FBFADF', 3);
INSERT INTO colors VALUES (1126, 293, 'Outline', '#8A5838', 0);
INSERT INTO colors VALUES (1127, 293, 'Fill', '#C39D4D', 1);
INSERT INTO colors VALUES (1104, 288, 'Lime Fill 1', '#619623', 0);
INSERT INTO colors VALUES (1105, 288, 'Lime Fill 2', '#90BA40', 1);
INSERT INTO colors VALUES (1106, 288, 'Lime Fill 3', '#DDEA79', 2);
INSERT INTO colors VALUES (1107, 288, 'Stone Fill 1', '#FEFEFE', 3);
INSERT INTO colors VALUES (1108, 288, 'Stone Fill 2', '#CCCED2', 4);
INSERT INTO colors VALUES (1113, 290, 'Outline', '#554373', 0);
INSERT INTO colors VALUES (1114, 290, 'Fill', '#9885B7', 1);
INSERT INTO colors VALUES (1115, 291, 'Gradient Top', '#196E5D', 0);
INSERT INTO colors VALUES (1117, 291, 'Gradient Bottom', '#99EAD7', 1);
INSERT INTO colors VALUES (1118, 291, 'Highlight Top', '#ADDEDF', 2);
INSERT INTO colors VALUES (1119, 291, 'Highlight Bottom', '#DFF1F2', 3);
INSERT INTO colors VALUES (1122, 291, 'Eyeshadow (Same as mane fill)', '#9885B7', 4);
INSERT INTO colors VALUES (1120, 292, 'Outline', '#395B6E', 0);
INSERT INTO colors VALUES (1121, 292, 'Fill', '#53829F', 1);
INSERT INTO colors VALUES (1123, 292, 'Shadow Outline', '#355063', 2);
INSERT INTO colors VALUES (1124, 292, 'Shadow Fill', '#4B748F', 3);
INSERT INTO colors VALUES (1125, 292, 'Belt', '#3B4144', 4);
INSERT INTO colors VALUES (1128, 293, 'Shadow Outline', '#9E6E41', 2);
INSERT INTO colors VALUES (1129, 293, 'Shadow Fill', '#C0974D', 3);
INSERT INTO colors VALUES (1130, 294, 'Outline', '#666666', 0);
INSERT INTO colors VALUES (1131, 294, 'Fill 1', '#989898', 1);
INSERT INTO colors VALUES (1139, 294, 'Fill 2', '#CCCCCC', 2);
INSERT INTO colors VALUES (1132, 295, 'Gradient Top', '#B48A25', 0);
INSERT INTO colors VALUES (1134, 295, 'Gradient Bottom', '#FAE695', 1);
INSERT INTO colors VALUES (1137, 296, 'Handle Outline', '#602A1D', 0);
INSERT INTO colors VALUES (1138, 296, 'Handle Fill', '#924623', 1);
INSERT INTO colors VALUES (1140, 296, 'Pickaxe Outline', '#646D6D', 2);
INSERT INTO colors VALUES (1141, 296, 'Pickaxe Fill', '#849492', 3);
INSERT INTO colors VALUES (1142, 297, 'Hat/Tie Outline', '#232323', 0);
INSERT INTO colors VALUES (1143, 297, 'Hat/Tie Fill', '#404040', 1);
INSERT INTO colors VALUES (1144, 297, 'Collar Outline', '#656565', 2);
INSERT INTO colors VALUES (1145, 297, 'Collar Fill/Hat Fill 2', '#CCCCCC', 3);
INSERT INTO colors VALUES (1146, 298, 'Outline', '#A9A9A7', 0);
INSERT INTO colors VALUES (1147, 298, 'Fill', '#D8DDEA', 1);
INSERT INTO colors VALUES (1149, 298, 'Shadow Fill', '#C1C5C9', 2);
INSERT INTO colors VALUES (1150, 299, 'Outline', '#223937', 0);
INSERT INTO colors VALUES (1151, 299, 'Fill', '#508383', 1);
INSERT INTO colors VALUES (1157, 301, 'Rock Fill 1', '#85B7B9', 0);
INSERT INTO colors VALUES (1158, 301, 'Rock Fill 2', '#A4D0D4', 1);
INSERT INTO colors VALUES (1159, 301, 'Rock Fill 3', '#BCE3E1', 2);
INSERT INTO colors VALUES (1160, 301, 'Cracks', '#1C484C', 3);
INSERT INTO colors VALUES (1152, 300, 'Gradient Top', '#218691', 0);
INSERT INTO colors VALUES (1154, 300, 'Gradient Bottom', '#91CFE1', 1);
INSERT INTO colors VALUES (1155, 300, 'Highlight Top', '#A3EBEB', 2);
INSERT INTO colors VALUES (1156, 300, 'Highlight Bottom', '#EBFAF8', 3);
INSERT INTO colors VALUES (1180, 305, 'Medium Details', '#476168', 8);
INSERT INTO colors VALUES (1181, 305, 'Dark Details', '#26383B', 9);
INSERT INTO colors VALUES (1161, 302, 'Frame/Chain Outline', '#D2AB0F', 0);
INSERT INTO colors VALUES (1162, 302, 'Chain Fill', '#EED33B', 1);
INSERT INTO colors VALUES (1163, 302, 'Lens Fill 1 (75% Opacity)', '#87D1E7', 2);
INSERT INTO colors VALUES (1164, 302, 'Lens Fill 2 (75% Opacity)', '#A0DAF0', 3);
INSERT INTO colors VALUES (1165, 303, 'Collar Outline', '#000000', 0);
INSERT INTO colors VALUES (1166, 303, 'Collar Fill 1', '#343434', 1);
INSERT INTO colors VALUES (1167, 303, 'Collar Fill 2', '#FFFFFF', 2);
INSERT INTO colors VALUES (1168, 303, 'Pendant Outline', '#D2AB0F', 3);
INSERT INTO colors VALUES (1169, 303, 'Pendant Fill 1', '#EED33B', 4);
INSERT INTO colors VALUES (1170, 303, 'Pendant Fill 2', '#54BEBA', 5);
INSERT INTO colors VALUES (1171, 303, 'Collar Shadow Fill 1', '#252525', 6);
INSERT INTO colors VALUES (1172, 303, 'Collar Shadow Fill 2', '#C0C0C0', 7);
INSERT INTO colors VALUES (915, 238, 'Hat Outline', '#7A3F3F', 0);
INSERT INTO colors VALUES (1173, 304, 'Fill 1', '#638081', 0);
INSERT INTO colors VALUES (1174, 304, 'Fill 2', '#3A4A4D', 1);
INSERT INTO colors VALUES (1082, 282, 'Main Outline', '#2C3838', 0);
INSERT INTO colors VALUES (1083, 282, 'Inner Outline', '#405252', 1);
INSERT INTO colors VALUES (1091, 282, 'FIll 1', '#607878', 2);
INSERT INTO colors VALUES (1092, 282, 'Fill 2', '#819B93', 3);
INSERT INTO colors VALUES (1176, 305, 'Top Medium Fill', '#9AB0B7', 1);
INSERT INTO colors VALUES (1183, 305, 'Top Dark Fill', '#85A2A7', 2);
INSERT INTO colors VALUES (1177, 305, 'Front Light Fill', '#698D95', 3);
INSERT INTO colors VALUES (1178, 305, 'Front Medium Fill', '#547176', 4);
INSERT INTO colors VALUES (1179, 305, 'Front Dark Fill', '#42585E', 5);
INSERT INTO colors VALUES (1184, 305, 'Bottom Fill', '#30474B', 6);
INSERT INTO colors VALUES (1182, 305, 'Light Details', '#7C949A', 7);
INSERT INTO colors VALUES (1185, 305, 'Very Dark Details', '#192425', 10);
INSERT INTO colors VALUES (1109, 289, 'Outline', '#8A8995', 0);
INSERT INTO colors VALUES (1110, 289, 'Fill', '#B8B7BE', 1);
INSERT INTO colors VALUES (1175, 305, 'Top Light Fill', '#B6C8CB', 0);
INSERT INTO colors VALUES (1111, 289, 'Shadow Outline', '#7B7C83', 2);
INSERT INTO colors VALUES (1112, 289, 'Shadow Fill', '#A4A4A8', 3);


--
-- Name: colors_colorid_seq; Type: SEQUENCE SET; Schema: public; Owner: mlpvc-rr
--

SELECT pg_catalog.setval('colors_colorid_seq', 1185, true);


--
-- Data for Name: tagged; Type: TABLE DATA; Schema: public; Owner: mlpvc-rr
--

INSERT INTO tagged VALUES (4, 1);
INSERT INTO tagged VALUES (6, 1);
INSERT INTO tagged VALUES (12, 1);
INSERT INTO tagged VALUES (14, 1);
INSERT INTO tagged VALUES (59, 1);
INSERT INTO tagged VALUES (2, 2);
INSERT INTO tagged VALUES (6, 2);
INSERT INTO tagged VALUES (12, 2);
INSERT INTO tagged VALUES (22, 2);
INSERT INTO tagged VALUES (3, 3);
INSERT INTO tagged VALUES (6, 3);
INSERT INTO tagged VALUES (12, 3);
INSERT INTO tagged VALUES (24, 3);
INSERT INTO tagged VALUES (2, 4);
INSERT INTO tagged VALUES (6, 4);
INSERT INTO tagged VALUES (12, 4);
INSERT INTO tagged VALUES (23, 4);
INSERT INTO tagged VALUES (3, 5);
INSERT INTO tagged VALUES (6, 5);
INSERT INTO tagged VALUES (12, 5);
INSERT INTO tagged VALUES (25, 5);
INSERT INTO tagged VALUES (1, 6);
INSERT INTO tagged VALUES (6, 6);
INSERT INTO tagged VALUES (12, 6);
INSERT INTO tagged VALUES (26, 6);
INSERT INTO tagged VALUES (6, 7);
INSERT INTO tagged VALUES (11, 7);
INSERT INTO tagged VALUES (29, 7);
INSERT INTO tagged VALUES (30, 7);
INSERT INTO tagged VALUES (4, 9);
INSERT INTO tagged VALUES (7, 9);
INSERT INTO tagged VALUES (12, 9);
INSERT INTO tagged VALUES (57, 9);
INSERT INTO tagged VALUES (59, 9);
INSERT INTO tagged VALUES (1, 10);
INSERT INTO tagged VALUES (7, 10);
INSERT INTO tagged VALUES (12, 10);
INSERT INTO tagged VALUES (31, 10);
INSERT INTO tagged VALUES (3, 11);
INSERT INTO tagged VALUES (7, 11);
INSERT INTO tagged VALUES (12, 11);
INSERT INTO tagged VALUES (50, 11);
INSERT INTO tagged VALUES (1, 12);
INSERT INTO tagged VALUES (7, 12);
INSERT INTO tagged VALUES (12, 12);
INSERT INTO tagged VALUES (32, 12);
INSERT INTO tagged VALUES (1, 13);
INSERT INTO tagged VALUES (7, 13);
INSERT INTO tagged VALUES (12, 13);
INSERT INTO tagged VALUES (43, 13);
INSERT INTO tagged VALUES (51, 13);
INSERT INTO tagged VALUES (1, 14);
INSERT INTO tagged VALUES (7, 14);
INSERT INTO tagged VALUES (11, 14);
INSERT INTO tagged VALUES (33, 14);
INSERT INTO tagged VALUES (43, 14);
INSERT INTO tagged VALUES (1, 15);
INSERT INTO tagged VALUES (7, 15);
INSERT INTO tagged VALUES (12, 15);
INSERT INTO tagged VALUES (34, 15);
INSERT INTO tagged VALUES (1, 16);
INSERT INTO tagged VALUES (7, 16);
INSERT INTO tagged VALUES (12, 16);
INSERT INTO tagged VALUES (35, 16);
INSERT INTO tagged VALUES (1, 17);
INSERT INTO tagged VALUES (7, 17);
INSERT INTO tagged VALUES (12, 17);
INSERT INTO tagged VALUES (36, 17);
INSERT INTO tagged VALUES (2, 18);
INSERT INTO tagged VALUES (7, 18);
INSERT INTO tagged VALUES (12, 18);
INSERT INTO tagged VALUES (28, 18);
INSERT INTO tagged VALUES (37, 18);
INSERT INTO tagged VALUES (3, 19);
INSERT INTO tagged VALUES (7, 19);
INSERT INTO tagged VALUES (12, 19);
INSERT INTO tagged VALUES (27, 19);
INSERT INTO tagged VALUES (38, 19);
INSERT INTO tagged VALUES (3, 20);
INSERT INTO tagged VALUES (7, 20);
INSERT INTO tagged VALUES (12, 20);
INSERT INTO tagged VALUES (28, 20);
INSERT INTO tagged VALUES (39, 20);
INSERT INTO tagged VALUES (42, 20);
INSERT INTO tagged VALUES (3, 21);
INSERT INTO tagged VALUES (7, 21);
INSERT INTO tagged VALUES (11, 21);
INSERT INTO tagged VALUES (27, 21);
INSERT INTO tagged VALUES (40, 21);
INSERT INTO tagged VALUES (42, 21);
INSERT INTO tagged VALUES (1, 22);
INSERT INTO tagged VALUES (7, 22);
INSERT INTO tagged VALUES (12, 22);
INSERT INTO tagged VALUES (41, 22);
INSERT INTO tagged VALUES (65, 22);
INSERT INTO tagged VALUES (2, 23);
INSERT INTO tagged VALUES (7, 23);
INSERT INTO tagged VALUES (12, 23);
INSERT INTO tagged VALUES (44, 23);
INSERT INTO tagged VALUES (45, 23);
INSERT INTO tagged VALUES (46, 23);
INSERT INTO tagged VALUES (3, 24);
INSERT INTO tagged VALUES (7, 24);
INSERT INTO tagged VALUES (12, 24);
INSERT INTO tagged VALUES (44, 24);
INSERT INTO tagged VALUES (45, 24);
INSERT INTO tagged VALUES (47, 24);
INSERT INTO tagged VALUES (1, 25);
INSERT INTO tagged VALUES (7, 25);
INSERT INTO tagged VALUES (12, 25);
INSERT INTO tagged VALUES (44, 25);
INSERT INTO tagged VALUES (45, 25);
INSERT INTO tagged VALUES (48, 25);
INSERT INTO tagged VALUES (3, 26);
INSERT INTO tagged VALUES (7, 26);
INSERT INTO tagged VALUES (12, 26);
INSERT INTO tagged VALUES (49, 26);
INSERT INTO tagged VALUES (65, 26);
INSERT INTO tagged VALUES (2, 27);
INSERT INTO tagged VALUES (7, 27);
INSERT INTO tagged VALUES (11, 27);
INSERT INTO tagged VALUES (52, 27);
INSERT INTO tagged VALUES (65, 27);
INSERT INTO tagged VALUES (1, 28);
INSERT INTO tagged VALUES (7, 28);
INSERT INTO tagged VALUES (11, 28);
INSERT INTO tagged VALUES (53, 28);
INSERT INTO tagged VALUES (65, 28);
INSERT INTO tagged VALUES (1, 29);
INSERT INTO tagged VALUES (9, 29);
INSERT INTO tagged VALUES (12, 29);
INSERT INTO tagged VALUES (54, 29);
INSERT INTO tagged VALUES (2, 30);
INSERT INTO tagged VALUES (7, 30);
INSERT INTO tagged VALUES (12, 30);
INSERT INTO tagged VALUES (55, 30);
INSERT INTO tagged VALUES (56, 30);
INSERT INTO tagged VALUES (2, 31);
INSERT INTO tagged VALUES (9, 31);
INSERT INTO tagged VALUES (12, 31);
INSERT INTO tagged VALUES (58, 31);
INSERT INTO tagged VALUES (1, 32);
INSERT INTO tagged VALUES (9, 32);
INSERT INTO tagged VALUES (12, 32);
INSERT INTO tagged VALUES (60, 32);
INSERT INTO tagged VALUES (61, 33);
INSERT INTO tagged VALUES (62, 33);
INSERT INTO tagged VALUES (64, 33);
INSERT INTO tagged VALUES (4, 34);
INSERT INTO tagged VALUES (7, 34);
INSERT INTO tagged VALUES (12, 34);
INSERT INTO tagged VALUES (59, 34);
INSERT INTO tagged VALUES (63, 34);
INSERT INTO tagged VALUES (2, 35);
INSERT INTO tagged VALUES (7, 35);
INSERT INTO tagged VALUES (11, 35);
INSERT INTO tagged VALUES (66, 35);
INSERT INTO tagged VALUES (1, 36);
INSERT INTO tagged VALUES (7, 36);
INSERT INTO tagged VALUES (12, 36);
INSERT INTO tagged VALUES (68, 36);
INSERT INTO tagged VALUES (1, 37);
INSERT INTO tagged VALUES (8, 37);
INSERT INTO tagged VALUES (12, 37);
INSERT INTO tagged VALUES (44, 37);
INSERT INTO tagged VALUES (72, 37);
INSERT INTO tagged VALUES (1, 38);
INSERT INTO tagged VALUES (8, 38);
INSERT INTO tagged VALUES (12, 38);
INSERT INTO tagged VALUES (44, 38);
INSERT INTO tagged VALUES (73, 38);
INSERT INTO tagged VALUES (2, 39);
INSERT INTO tagged VALUES (8, 39);
INSERT INTO tagged VALUES (11, 39);
INSERT INTO tagged VALUES (44, 39);
INSERT INTO tagged VALUES (74, 39);
INSERT INTO tagged VALUES (2, 40);
INSERT INTO tagged VALUES (8, 40);
INSERT INTO tagged VALUES (12, 40);
INSERT INTO tagged VALUES (44, 40);
INSERT INTO tagged VALUES (75, 40);
INSERT INTO tagged VALUES (1, 44);
INSERT INTO tagged VALUES (7, 44);
INSERT INTO tagged VALUES (11, 44);
INSERT INTO tagged VALUES (59, 44);
INSERT INTO tagged VALUES (77, 44);
INSERT INTO tagged VALUES (1, 45);
INSERT INTO tagged VALUES (12, 45);
INSERT INTO tagged VALUES (78, 45);
INSERT INTO tagged VALUES (2, 46);
INSERT INTO tagged VALUES (7, 46);
INSERT INTO tagged VALUES (11, 46);
INSERT INTO tagged VALUES (79, 46);
INSERT INTO tagged VALUES (12, 47);
INSERT INTO tagged VALUES (80, 50);
INSERT INTO tagged VALUES (12, 50);
INSERT INTO tagged VALUES (2, 50);
INSERT INTO tagged VALUES (7, 50);
INSERT INTO tagged VALUES (81, 51);
INSERT INTO tagged VALUES (12, 51);
INSERT INTO tagged VALUES (2, 51);
INSERT INTO tagged VALUES (7, 51);
INSERT INTO tagged VALUES (82, 52);
INSERT INTO tagged VALUES (12, 52);
INSERT INTO tagged VALUES (2, 52);
INSERT INTO tagged VALUES (7, 52);
INSERT INTO tagged VALUES (83, 53);
INSERT INTO tagged VALUES (11, 53);
INSERT INTO tagged VALUES (2, 53);
INSERT INTO tagged VALUES (7, 53);
INSERT INTO tagged VALUES (84, 54);
INSERT INTO tagged VALUES (12, 54);
INSERT INTO tagged VALUES (7, 54);
INSERT INTO tagged VALUES (2, 54);
INSERT INTO tagged VALUES (85, 4);
INSERT INTO tagged VALUES (85, 50);
INSERT INTO tagged VALUES (85, 51);
INSERT INTO tagged VALUES (85, 52);
INSERT INTO tagged VALUES (85, 53);
INSERT INTO tagged VALUES (85, 54);
INSERT INTO tagged VALUES (86, 53);
INSERT INTO tagged VALUES (86, 54);
INSERT INTO tagged VALUES (86, 51);
INSERT INTO tagged VALUES (86, 50);


--
-- Data for Name: tags; Type: TABLE DATA; Schema: public; Owner: mlpvc-rr
--

INSERT INTO tags VALUES (1, 'unicorn', '', 'spec', 18);
INSERT INTO tags VALUES (3, 'pegasus', '', 'spec', 8);
INSERT INTO tags VALUES (4, 'alicorn', '', 'spec', 3);
INSERT INTO tags VALUES (5, 'bat pony', '', 'spec', 0);
INSERT INTO tags VALUES (14, 'twilight sparkle', '', 'char', 1);
INSERT INTO tags VALUES (19, 's1e1', '', 'ep', 0);
INSERT INTO tags VALUES (20, 's1e26', '', 'ep', 0);
INSERT INTO tags VALUES (21, 's5e12', '', 'ep', 0);
INSERT INTO tags VALUES (23, 'pinkie pie', '', 'char', 1);
INSERT INTO tags VALUES (24, 'fluttershy', '', 'char', 1);
INSERT INTO tags VALUES (25, 'rainbow dash', '', 'char', 1);
INSERT INTO tags VALUES (26, 'rarity', '', 'char', 1);
INSERT INTO tags VALUES (29, 'dragon', '', 'spec', 1);
INSERT INTO tags VALUES (30, 'spike', '', 'char', 1);
INSERT INTO tags VALUES (31, 'minuette', '', 'char', 1);
INSERT INTO tags VALUES (32, 'lyra heartstrings', '', 'char', 1);
INSERT INTO tags VALUES (33, 'fashion plate', '', 'char', 1);
INSERT INTO tags VALUES (34, 'sassy saddles', '', 'char', 1);
INSERT INTO tags VALUES (35, 'twinkleshine', '', 'char', 1);
INSERT INTO tags VALUES (36, 'lemon hearts', '', 'char', 1);
INSERT INTO tags VALUES (37, 'granny smith', '', 'char', 1);
INSERT INTO tags VALUES (38, 'fleetfoot', '', 'char', 1);
INSERT INTO tags VALUES (39, 'stormy flare', '', 'char', 1);
INSERT INTO tags VALUES (40, 'wind rider', '', 'char', 1);
INSERT INTO tags VALUES (41, 'sugar belle', '', 'char', 1);
INSERT INTO tags VALUES (42, 's5e15', '', 'ep', 2);
INSERT INTO tags VALUES (43, 's5e14', '', 'ep', 2);
INSERT INTO tags VALUES (47, 'scootaloo', '', 'char', 1);
INSERT INTO tags VALUES (48, 'sweetie belle', '', 'char', 1);
INSERT INTO tags VALUES (49, 'night glider', '', 'char', 1);
INSERT INTO tags VALUES (50, 'derpy hooves', 'Derpy Hooves or Muffins', 'char', 1);
INSERT INTO tags VALUES (51, 'whoa nelly', '', 'char', 1);
INSERT INTO tags VALUES (52, 'double diamond', '', 'char', 1);
INSERT INTO tags VALUES (53, 'party favor', '', 'char', 1);
INSERT INTO tags VALUES (54, 'starlight glimmer', '', 'char', 1);
INSERT INTO tags VALUES (55, 'coco pommel', '', 'char', 1);
INSERT INTO tags VALUES (57, 'princess luna', '', 'char', 1);
INSERT INTO tags VALUES (58, 'suri polomare', '', 'char', 1);
INSERT INTO tags VALUES (60, 'trixie', '', 'char', 1);
INSERT INTO tags VALUES (61, 's3e5', '', 'ep', 1);
INSERT INTO tags VALUES (62, 'alicorn amulet', '', NULL, 1);
INSERT INTO tags VALUES (63, 'princess celestia', '', 'char', 1);
INSERT INTO tags VALUES (65, 's5e2', '', 'ep', 4);
INSERT INTO tags VALUES (68, 'moondancer', '', 'char', 1);
INSERT INTO tags VALUES (72, 'dinky doo', '', 'char', 1);
INSERT INTO tags VALUES (75, 'lily longsocks', '', 'char', 1);
INSERT INTO tags VALUES (76, 'human', '', 'spec', 1);
INSERT INTO tags VALUES (77, 'shining armor', '', 'char', 1);
INSERT INTO tags VALUES (28, 'parent', 'Parents of other characters', 'cat', 2);
INSERT INTO tags VALUES (80, 'marble pie', '', 'char', 1);
INSERT INTO tags VALUES (10, 'pet', '', 'cat', 0);
INSERT INTO tags VALUES (85, 'pie family', '', 'cat', 6);
INSERT INTO tags VALUES (59, 'royalty', '', 'cat', 4);
INSERT INTO tags VALUES (81, 'limestone pie', '', 'char', 1);
INSERT INTO tags VALUES (27, 'wonderbolt', 'Wonderbolt characters', 'cat', 2);
INSERT INTO tags VALUES (46, 'apple bloom', '', 'char', 1);
INSERT INTO tags VALUES (22, 'applejack', '', 'char', 1);
INSERT INTO tags VALUES (82, 'maud pie', '', 'char', 1);
INSERT INTO tags VALUES (73, 'berry pinch', '', 'char', 1);
INSERT INTO tags VALUES (66, 'big macintosh', '', 'char', 1);
INSERT INTO tags VALUES (74, 'button mash', '', 'char', 1);
INSERT INTO tags VALUES (83, 'igenous rock', '', 'char', 1);
INSERT INTO tags VALUES (11, 'male', '', 'gen', 10);
INSERT INTO tags VALUES (79, 'cheese sandwich', '', 'char', 1);
INSERT INTO tags VALUES (84, 'cloudy quartz', '', 'char', 1);
INSERT INTO tags VALUES (12, 'female', '', 'gen', 37);
INSERT INTO tags VALUES (2, 'earth pony', '', 'spec', 16);
INSERT INTO tags VALUES (86, 's5e20', '', 'ep', 4);
INSERT INTO tags VALUES (9, 'antagonist', '', 'cat', 3);
INSERT INTO tags VALUES (8, 'background character', 'Ponies whose only purpose is filling crowds, with no to minimal speaking roles', 'cat', 4);
INSERT INTO tags VALUES (45, 'cutie mark crusader', '', 'cat', 3);
INSERT INTO tags VALUES (44, 'foal', '', 'cat', 7);
INSERT INTO tags VALUES (56, 'manehatten', '', 'cat', 1);
INSERT INTO tags VALUES (6, 'mane six', 'Ponies who are one of the show''s six main characters', 'cat', 7);
INSERT INTO tags VALUES (7, 'minor character', 'Ponies who had a speaking role and/or interacted with the mane six', 'cat', 31);
INSERT INTO tags VALUES (64, 'object', '', 'cat', 1);
INSERT INTO tags VALUES (78, 'original character', 'Characters not canon to the show''s universe', 'cat', 1);


--
-- Name: tags_tid_seq; Type: SEQUENCE SET; Schema: public; Owner: mlpvc-rr
--

SELECT pg_catalog.setval('tags_tid_seq', 86, true);


--
-- Name: appearances_id; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

ALTER TABLE ONLY appearances
    ADD CONSTRAINT appearances_id PRIMARY KEY (id);


--
-- Name: colorgroups_groupid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

ALTER TABLE ONLY colorgroups
    ADD CONSTRAINT colorgroups_groupid PRIMARY KEY (groupid);


--
-- Name: colorgroups_groupid_label; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

ALTER TABLE ONLY colorgroups
    ADD CONSTRAINT colorgroups_groupid_label UNIQUE (groupid, label);


--
-- Name: colors_colorid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

ALTER TABLE ONLY colors
    ADD CONSTRAINT colors_colorid PRIMARY KEY (colorid);


--
-- Name: tagged_tid_ponyid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

ALTER TABLE ONLY tagged
    ADD CONSTRAINT tagged_tid_ponyid PRIMARY KEY (tid, ponyid);


--
-- Name: tags_tid; Type: CONSTRAINT; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

ALTER TABLE ONLY tags
    ADD CONSTRAINT tags_tid PRIMARY KEY (tid);


--
-- Name: colorgroups_ponyid; Type: INDEX; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

CREATE INDEX colorgroups_ponyid ON colorgroups USING btree (ponyid);


--
-- Name: colors_groupid; Type: INDEX; Schema: public; Owner: mlpvc-rr; Tablespace: 
--

CREATE INDEX colors_groupid ON colors USING btree (groupid);


--
-- Name: colorgroups_ponyid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colorgroups
    ADD CONSTRAINT colorgroups_ponyid_fkey FOREIGN KEY (ponyid) REFERENCES appearances(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: colors_groupid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mlpvc-rr
--

ALTER TABLE ONLY colors
    ADD CONSTRAINT colors_groupid_fkey FOREIGN KEY (groupid) REFERENCES colorgroups(groupid) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- Name: appearances; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE appearances FROM PUBLIC;
REVOKE ALL ON TABLE appearances FROM "mlpvc-rr";
GRANT ALL ON TABLE appearances TO "mlpvc-rr";
GRANT ALL ON TABLE appearances TO postgres;


--
-- Name: colorgroups; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE colorgroups FROM PUBLIC;
REVOKE ALL ON TABLE colorgroups FROM "mlpvc-rr";
GRANT ALL ON TABLE colorgroups TO "mlpvc-rr";
GRANT ALL ON TABLE colorgroups TO postgres;


--
-- Name: colors; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE colors FROM PUBLIC;
REVOKE ALL ON TABLE colors FROM "mlpvc-rr";
GRANT ALL ON TABLE colors TO "mlpvc-rr";
GRANT ALL ON TABLE colors TO postgres;


--
-- Name: tagged; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE tagged FROM PUBLIC;
REVOKE ALL ON TABLE tagged FROM "mlpvc-rr";
GRANT ALL ON TABLE tagged TO "mlpvc-rr";
GRANT ALL ON TABLE tagged TO postgres;


--
-- Name: tags; Type: ACL; Schema: public; Owner: mlpvc-rr
--

REVOKE ALL ON TABLE tags FROM PUBLIC;
REVOKE ALL ON TABLE tags FROM "mlpvc-rr";
GRANT ALL ON TABLE tags TO "mlpvc-rr";
GRANT ALL ON TABLE tags TO postgres;


--
-- PostgreSQL database dump complete
--

