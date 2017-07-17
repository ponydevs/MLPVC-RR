DROP VIEW "personal_cg_appearances";
ALTER SEQUENCE log_appearance_modify_entryid_seq RENAME TO log__appearance_modify_entryid_seq;

ALTER TABLE "appearances" RENAME "owner" TO "owner_id";
ALTER TABLE "appearances" DROP CONSTRAINT "appearances_owner_fkey";
CREATE INDEX "appearances_owner_id" ON "appearances" ("owner_id");
ALTER TABLE "appearances" ADD FOREIGN KEY ("owner_id") REFERENCES "users" ("id") ON DELETE NO ACTION ON UPDATE CASCADE;


ALTER TABLE "events__entries__votes" RENAME "entryid" TO "entry_id";
ALTER TABLE "events__entries__votes" RENAME "userid" TO "user_id";
ALTER TABLE "events__entries__votes"
DROP CONSTRAINT "events__entries__votes_entryid_fkey";
ALTER TABLE "events__entries__votes" ADD CONSTRAINT "events__votes_entry_id_user_id" PRIMARY KEY ("entry_id", "user_id"),
DROP CONSTRAINT "events__votes_entryid_userid";

ALTER TABLE "events__entries" RENAME "entryid" TO "id";
ALTER TABLE "events__entries" RENAME "eventid" TO "event_id";
ALTER TABLE "events__entries" DROP CONSTRAINT "events__entries_eventid_fkey";
ALTER TABLE "events__entries" ADD CONSTRAINT "events__entries_id" PRIMARY KEY ("id"), DROP CONSTRAINT "events__entries_entryid";
CREATE INDEX "events__entries_event_id" ON "events__entries" ("event_id");
ALTER TABLE "events__entries" ADD FOREIGN KEY ("event_id") REFERENCES "events" ("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER SEQUENCE events__entries_entryid_seq RENAME TO events__entries_id_seq;
ALTER TABLE "events__entries__votes" ADD FOREIGN KEY ("entry_id") REFERENCES "events__entries" ("id") ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE "log__req_delete" RENAME "posted" TO "requested_at";

ALTER TABLE "log__banish" RENAME "target" TO "target_id";
DROP INDEX "log__banish_target";
CREATE INDEX "log__banish_target_id" ON "log__banish" ("target_id");

ALTER TABLE "log__un-banish" RENAME "target" TO "target_id";
ALTER TABLE "log__un-banish" RENAME TO "log__unbanish";
DROP INDEX "log__un-banish_target";
ALTER TABLE "log__unbanish" ADD CONSTRAINT "log__unbanish_entryid" PRIMARY KEY ("entryid"), DROP CONSTRAINT "log__un-banish_entryid";
CREATE INDEX "log__unbanish_target" ON "log__unbanish" ("target_id");
ALTER TABLE "log__unbanish" DROP CONSTRAINT "log__un-banish_target_fkey";
ALTER TABLE "log__unbanish" ADD FOREIGN KEY ("target_id") REFERENCES "users" ("id") ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE "log__da_namechange" RENAME "id" TO "user_id";

ALTER TABLE "log__color_modify" RENAME TO "log__major_changes";
ALTER TABLE "log__major_changes" ADD CONSTRAINT "log__major_changes_entryid" PRIMARY KEY ("entryid"), DROP CONSTRAINT "log__color_modify_entryid";
ALTER TABLE "log__major_changes" RENAME "ponyid" TO "appearance_id";
CREATE INDEX "log__major_changes_appearance_id" ON "log__major_changes" ("appearance_id");

ALTER TABLE "log__appearance_modify" RENAME "ponyid" TO "appearance_id";
CREATE INDEX "log__appearance_modify_appearance_id" ON "log__appearance_modify" ("appearance_id");

ALTER TABLE "log__cg_order" RENAME "ponyid" TO "appearance_id";
CREATE INDEX "log__cg_order_appearance_id" ON "log__cg_order" ("appearance_id");

ALTER TABLE "log__cm_modify" RENAME "ponyid" TO "appearance_id";
CREATE INDEX "log__cm_modify_appearance_id" ON "log__cm_modify" ("appearance_id");

ALTER TABLE "log__cm_delete" RENAME "ponyid" TO "appearance_id";
CREATE INDEX "log__cm_delete_appearance_id" ON "log__cm_delete" ("appearance_id");

ALTER TABLE "log__cgs" RENAME "groupid" TO "group_id";
ALTER TABLE "log__cgs" RENAME "ponyid" TO "appearance_id";
CREATE INDEX "log__cgs_group_id" ON "log__cgs" ("group_id");
CREATE INDEX "log__cgs_appearance_id" ON "log__cgs" ("appearance_id");

ALTER TABLE "log__cg_modify" RENAME "groupid" TO "group_id";
ALTER TABLE "log__cg_modify" RENAME "ponyid" TO "appearance_id";
CREATE INDEX "log__cg_modify_group_id" ON "log__cg_modify" ("group_id");
CREATE INDEX "log__cg_modify_appearance_id" ON "log__cg_modify" ("appearance_id");

UPDATE log SET reftype = 'major_changes' WHERE reftype = 'color_modify';

ALTER TABLE "sessions" RENAME "user" TO "user_id";
ALTER TABLE "sessions" DROP CONSTRAINT "sessions_user_fkey";
ALTER TABLE "sessions" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE ON UPDATE CASCADE;
DROP INDEX "sessions_user";
CREATE INDEX "sessions_user_id" ON "sessions" ("user_id");

ALTER TABLE "user_prefs" RENAME "user" TO "user_id";
ALTER TABLE "user_prefs" DROP CONSTRAINT "user_prefs_user_fkey";
ALTER TABLE "user_prefs" ADD CONSTRAINT "user_prefs_user_id_key" PRIMARY KEY ("user_id", "key"), DROP CONSTRAINT "user_prefs_user_key";
ALTER TABLE "user_prefs" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE "tagged" DROP CONSTRAINT "tagged_tid_fkey";
ALTER TABLE "tagged" DROP CONSTRAINT "tagged_ponyid_fkey";

ALTER TABLE "tags" RENAME "tid" TO "id";
ALTER TABLE "tags" DROP CONSTRAINT "tags_synonym_of_fkey";
ALTER TABLE "tags" ADD CONSTRAINT "tags_id" PRIMARY KEY ("id"), DROP CONSTRAINT "tags_tid";
ALTER TABLE "tags" ADD FOREIGN KEY ("synonym_of") REFERENCES "tags" ("id") ON DELETE SET NULL ON UPDATE CASCADE;
ALTER SEQUENCE tags_tid_seq RENAME TO tags_id_seq;
CREATE INDEX "tags_name" ON "tags" ("name");

ALTER TABLE "tagged" RENAME "tid" TO "tag_id";
ALTER TABLE "tagged" RENAME "ponyid" TO "appearance_id";
ALTER TABLE "tagged" ADD CONSTRAINT "tagged_tag_id_appearance_id" PRIMARY KEY ("tag_id", "appearance_id"), DROP CONSTRAINT "tagged_tid_ponyid";
ALTER TABLE "tagged" ADD FOREIGN KEY ("tag_id") REFERENCES "tags" ("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "tagged" ADD FOREIGN KEY ("appearance_id") REFERENCES "appearances" ("id") ON DELETE CASCADE ON UPDATE CASCADE;

DROP VIEW "unread_notifications";

ALTER TABLE "notifications" RENAME "user" TO "recipient_id";
ALTER TABLE "notifications" DROP CONSTRAINT "notifications_user_fkey";
ALTER TABLE "notifications" ADD FOREIGN KEY ("recipient_id") REFERENCES "users" ("id") ON DELETE RESTRICT ON UPDATE CASCADE;

CREATE VIEW "unread_notifications" AS
  SELECT
    u.name      AS "user",
    count(n.id) AS count
  FROM (notifications n
    LEFT JOIN users u ON ((n."recipient_id" = u.id)))
  WHERE (n.read_at IS NULL)
  GROUP BY u.name
  ORDER BY (count(n.id)) DESC;

ALTER TABLE "requests" RENAME "posted" TO "requested_at";
ALTER TABLE "reservations" RENAME "posted" TO "reserved_at";

ALTER TABLE "colors" RENAME "groupid" TO "group_id";
DROP INDEX "colors_groupid";
CREATE INDEX "colors_group_id" ON "colors" ("group_id");
ALTER TABLE "colors" DROP CONSTRAINT "colors_groupid_fkey";
ALTER TABLE "colors" ADD CONSTRAINT "colors_group_id_order" PRIMARY KEY ("group_id", "order");

ALTER TABLE "colorgroups" RENAME TO "color_groups";
ALTER TABLE "color_groups" RENAME "groupid" TO "id";
ALTER TABLE "colors" ADD FOREIGN KEY ("group_id") REFERENCES "color_groups" ("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "color_groups" RENAME "ponyid" TO "appearance_id";
ALTER SEQUENCE colorgroups_groupid_seq RENAME TO color_groups_id_seq;
ALTER TABLE "color_groups" DROP CONSTRAINT "colorgroups_ponyid_fkey";
ALTER TABLE "color_groups" ADD FOREIGN KEY ("appearance_id") REFERENCES "appearances" ("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "color_groups" ALTER "order" DROP DEFAULT;

ALTER TABLE "usefullinks" RENAME TO "useful_links";
DROP INDEX "usefullinks_minrole";
ALTER TABLE "useful_links" ADD CONSTRAINT "useful_links_id" PRIMARY KEY ("id"), DROP CONSTRAINT "usefullinks_id";

ALTER TABLE "cutiemarks" RENAME "cmid" TO "id";
ALTER TABLE "cutiemarks" RENAME "ponyid" TO "appearance_id";
ALTER SEQUENCE cutiemarks_cmid_seq RENAME TO cutiemarks_id_seq;

ALTER TABLE "discord-members" RENAME "userid" TO "user_id";
ALTER TABLE "discord-members" RENAME TO "discord_members";
ALTER TABLE "discord_members"
ADD CONSTRAINT "discord_members_id" PRIMARY KEY ("id"), ADD CONSTRAINT "discord_members_user_id" UNIQUE ("user_id"), DROP CONSTRAINT "discord_members_discid", DROP CONSTRAINT "discord_members_userid";
ALTER TABLE "discord_members" DROP CONSTRAINT "discord_members_userid_fkey";
ALTER TABLE "discord_members" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE "log__video_broken" DROP CONSTRAINT "log__video_broken_season_fkey";

ALTER TABLE "cached-deviations" RENAME TO "cached_deviations";
ALTER TABLE "cached_deviations" ADD CONSTRAINT "cached_deviations_provider_id" PRIMARY KEY ("provider", "id"), DROP CONSTRAINT "deviation_cache_provider_id";
CREATE INDEX "cached_deviations_author" ON "cached_deviations" ("author");

ALTER TABLE "episodes__votes" RENAME "user" TO "user_id";
ALTER TABLE "episodes__votes" RENAME TO "episode_votes";
DROP INDEX "episodes__votes_user";
ALTER TABLE "episode_votes" ADD CONSTRAINT "episode_votes_season_episode_user" PRIMARY KEY ("season", "episode", "user_id"),
DROP CONSTRAINT "episodes__votes_season_episode_user";
CREATE INDEX "episode_votes_user_id" ON "episode_votes" ("user_id");
ALTER TABLE "episode_votes" DROP CONSTRAINT "episodes__votes_season_fkey";
ALTER TABLE "episode_votes" DROP CONSTRAINT "episodes__votes_user_fkey";
ALTER TABLE "episode_votes" ADD FOREIGN KEY ("season", "episode") REFERENCES "episodes" ("season", "episode") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "episode_votes" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE "episodes__videos" RENAME TO "episode_videos";
ALTER TABLE "episode_videos" ADD CONSTRAINT "episode_videos_season_episode_provider_part" PRIMARY KEY ("season", "episode", "provider", "part"), DROP CONSTRAINT "episodes__videos_season_episode_provider_part";
ALTER TABLE "episode_videos" DROP CONSTRAINT "episodes__videos_season_fkey";
ALTER TABLE "episode_videos" ADD FOREIGN KEY ("season", "episode") REFERENCES "episodes" ("season", "episode") ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE "related_appearances" (
  "source_id" integer NOT NULL,
  "target_id" integer NOT NULL
);
ALTER TABLE "related_appearances" OWNER TO "mlpvc-rr";
ALTER TABLE "related_appearances" ADD CONSTRAINT "related_appearances_source_id_target_id" PRIMARY KEY ("source_id", "target_id");
ALTER TABLE "related_appearances" ADD FOREIGN KEY ("source_id") REFERENCES "appearances" ("id") ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE "related_appearances" ADD FOREIGN KEY ("target_id") REFERENCES "appearances" ("id") ON DELETE CASCADE ON UPDATE CASCADE;
