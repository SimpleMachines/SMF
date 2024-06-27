/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding SpoofDetector support
/******************************************************************************/

---# Adding a new column "spoofdetector_name" to members table
ALTER TABLE {$db_prefix}members
ADD COLUMN IF NOT EXISTS spoofdetector_name VARCHAR(255) NOT NULL DEFAULT '';
CREATE INDEX {$db_prefix}idx_spoofdetector_name ON {$db_prefix}members (spoofdetector_name);
CREATE INDEX {$db_prefix}idx_spoofdetector_name_id ON {$db_prefix}members (spoofdetector_name, id_member);
---#

---# Adding new "spoofdetector_censor" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('spoofdetector_censor', '1') ON CONFLICT DO NOTHING;
---#