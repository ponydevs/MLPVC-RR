<?php

use Phinx\Migration\AbstractMigration;

class SetHomepageSettingForExistingUsers extends AbstractMigration {
  public function up() {
    $ids = $this->query(
      'SELECT requested_by as id1, reserved_by as id2 FROM requests
			UNION ALL
			SELECT NULL as id1, reserved_by as id2 FROM reservations'
    );
    $uniqids = [];
    foreach ($ids as $row){
      if ($row['id1'] !== null && !isset($uniqids[$row['id1']]))
        $uniqids[$row['id1']] = true;
      if ($row['id2'] !== null && !isset($uniqids[$row['id2']]))
        $uniqids[$row['id2']] = true;
    }
    if (empty($uniqids))
      return;

    $values = [];
    foreach ($uniqids as $k => $_)
      $values[] = "('$k', 'p_homelastep', '1')";
    $values = implode(', ', $values);
    $this->query("INSERT INTO user_prefs (user_id, key, value) VALUES $values");
  }

  public function down() {
    // NOOP
  }
}
