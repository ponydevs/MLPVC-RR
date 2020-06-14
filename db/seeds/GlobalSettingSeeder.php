<?php

use Phinx\Seed\AbstractSeed;

class GlobalSettingSeeder extends AbstractSeed {
  public function run() {
    $data = [
      [
        'key' => 'about_reservations',
        'value' => '<p>People usually get excited whenever a new episode comes out, and start making vectors of any pose/object/etc. that they found hilarious/interesting enough. It often results in various people unnecessarily doing the very same thing. Vector Reservations can help organize our efforts by listing who\'s working on what and to reduce the number of duplicates.</p>',
      ],
      [
        'key' => 'reservation_rules',
        'value' => '<ol><li>You MUST have an image to make a reservation! For the best quality, get your references from the episode in 1080p.</li>
	<li>Making a reservation does NOT forbid other people from working on a pose anyway. It is only information that you are working on it, so other people can coordinate to avoid doing the same thing twice.</li>
	<li>There are no time limits, but remember that the longer you wait, the greater the chance that someone might take your pose anyway. It\'s generally advised to finish your reservations before a new episode comes out.</li>
	<li>The current limit for reservations is 4 at any given time. You can reserve more after completing or cancelling any previous reservations.</li>
	<li>Please remember that <strong>you have to be a member of the group in order to make a reservation</strong>. The idea is to add the finished vector to our gallery, so it has to meet all of our quality requirements.</li>
</ol>',
      ],
      [
        'key' => 'dev_role_label',
        'value' => 'staff',
      ],
    ];

    $this->table('global_settings')
      ->insert($data)
      ->save();
  }
}
