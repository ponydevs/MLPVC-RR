<?php

namespace App;

use App\Models\User;

class UserSettingForm {
  /** @var string */
  private $_setting_name;
  /** @var User */
  private $_current_user;
  /** @var bool */
  private $_can_save;

  public const INPUT_MAP = [
    'cg_itemsperpage' => [
      'type' => 'number',
      'options' => [
        'desc' => 'Appearances per page',
        'min' => 7,
        'max' => 20,
      ],
    ],
    'cg_hidesynon' => [
      'type' => 'checkbox',
      'options' => [
        'perm' => 'staff',
        'desc' => 'Hide synonym tags under appearances',
      ],
    ],
    'cg_hideclrinfo' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Hide color details on appearance pages',
      ],
    ],
    'cg_fulllstprev' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Display previews and alternate names on the full list',
      ],
    ],
    'p_vectorapp' => [
      'type' => 'select',
      'options' => [
        'desc' => 'Publicly show my vector program of choice: ',
        'optg' => 'Vectoring applications',
        'opts' => CoreUtils::VECTOR_APPS,
      ],
    ],
    'p_hidediscord' => [
      'type' => 'checkbox',
      'options' => [
        'perm' => 'not_discord_member',
        'desc' => 'Hide Discord server link from the sidebar',
      ],
    ],
    'p_hidepcg' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Hide my Personal Color Guide from the public',
      ],
    ],
    'p_avatarprov' => [
      'type' => 'select',
      'options' => [
        'desc' => 'Choose which service to pull your avatar from: ',
        'optg' => 'Available providers',
        'opts' => User::AVATAR_PROVIDERS,
        'optperm' => [
          'discord' => 'discord_member',
        ],
      ],
    ],
    'p_homelastep' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Home page should open latest episode (instead of the color guide)',
      ],
    ],
    'ep_hidesynopses' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Hide the synopsis section from episode pages',
      ],
    ],
    'ep_noappprev' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Hide preview squares in front of related appearance names',
      ],
    ],
    'ep_revstepbtn' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Reverse order of next/previous episode buttons',
      ],
    ],
    'a_pcgearn' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Can earn PCG slots (from finishing requests)',
      ],
    ],
    'a_pcgmake' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Can create PCG appearances',
      ],
    ],
    'a_pcgsprite' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Can add sprites to PCG appearances',
      ],
    ],
    'a_postreq' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Can post requests',
      ],
    ],
    'a_postres' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Can post reservations',
      ],
    ],
    'a_reserve' => [
      'type' => 'checkbox',
      'options' => [
        'desc' => 'Can reserve requests',
      ],
    ],
  ];

  public function __construct(string $setting_name, ?User $current_user = null, ?string $req_perm = null) {
    if (!isset(UserPrefs::DEFAULTS[$setting_name]))
      throw new \Exception('Could not instantiate '.__CLASS__." for non-existant setting $setting_name");
    if (!isset(self::INPUT_MAP[$setting_name]))
      throw new \Exception('Could not instantiate '.__CLASS__." for $setting_name: Missing INPUT_MAP entry");
    $this->_setting_name = $setting_name;
    if ($current_user === null && Auth::$signed_in)
      $current_user = Auth::$user;
    $this->_current_user = $current_user;
    // By default only the user themselves can change this setting
    if ($req_perm === null)
      $this->_can_save = Auth::$signed_in && $this->_current_user->id === Auth::$user->id;
    // If a permission is required, make sure the authenticated user has it
    else $this->_can_save = Permission::sufficient($req_perm);
  }

  private function _permissionCheck(string $check_name) {
    switch ($check_name){
      case 'discord_member':
      case 'not_discord_member':
        if ($this->_current_user === null)
          return false;

        if ($this->_current_user->isDiscordServerMember())
          return $check_name === 'discord_member';
        else return $check_name === 'not_discord_member';
      default:
        return true;
    }
  }

  private function _getInput(string $type, array $options = []):string {
    if (isset($options['perm'])){
      if (isset(Permission::ROLES[$options['perm']])){
        if (Permission::insufficient($options['perm']))
          return '';
      }
      else {
        if (!$this->_permissionCheck($options['perm']))
          return '';
      }
    }
    $disabled = !$this->_can_save ? 'disabled' : '';
    $value = UserPrefs::get($this->_setting_name, $this->_current_user);
    switch ($type){
      case 'select':
        $select = '';
        $optgroup = '';
        if (isset($options['opts'][''])){
          $selected = $value === '' ? 'selected' : '';
          $select .= "<option value='' $selected>".CoreUtils::escapeHTML($options['opts']['']).'</option>';
          unset($options['opts']['']);
        }
        /** @noinspection ForeachSourceInspection */
        foreach ($options['opts'] as $name => $label){
          $selected = $value === $name ? 'selected' : '';
          $opt_disabled = isset($options['optperm'][$name]) && !$this->_permissionCheck($options['optperm'][$name]) ? 'disabled' : '';

          $optgroup .= "<option value='$name' $selected $opt_disabled>".CoreUtils::escapeHTML($label).'</option>';
        }
        $label = CoreUtils::escapeHTML($options['optg'], ENT_QUOTES);
        $select .= "<optgroup label='$label'>$optgroup</optgroup>";

        return "<select name='value' $disabled>$select</select>";
      case 'number':
        $min = isset($options['min']) ? "min='{$options['min']}'" : '';
        $max = isset($options['max']) ? "max='{$options['max']}'" : '';
        $value = CoreUtils::escapeHTML($value, ENT_QUOTES);

        return "<input type='number' $min $max name='value' value='$value' step='1' $disabled>";
      case 'checkbox':
        $checked = $value ? ' checked' : '';

        return "<input type='checkbox' name='value' value='1' $checked $disabled>";
    }
  }

  public function render() {
    $map = self::INPUT_MAP[$this->_setting_name];
    $input = $this->_getInput($map['type'], $map['options'] ?? []);
    if ($input === '')
      return '';

    echo Twig::$env->render('user/_setting_form.html.twig', [
      'map' => $map,
      'input' => $input,
      'can_save' => $this->_can_save,
      'setting_name' => $this->_setting_name,
      'current_user' => $this->_current_user,
      'signed_in' => Auth::$signed_in,
    ]);
  }
}
