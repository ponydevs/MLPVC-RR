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
				'desc' => 'Hide synonym relations',
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
			]
		],
		'p_hidepcg' => [
			'type' => 'checkbox',
			'options' => [
				'desc' => 'Hide my Personal Color Guide from the public',
			]
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
			]
		],
		'a_pcgmake' => [
			'type' => 'checkbox',
			'options' => [
				'desc' => 'Can create PCG appearances',
			]
		],
		'a_postreq' => [
			'type' => 'checkbox',
			'options' => [
				'desc' => 'Can post requests',
			]
		],
		'a_postres' => [
			'type' => 'checkbox',
			'options' => [
				'desc' => 'Can post reservations',
			]
		],
		'a_reserve' => [
			'type' => 'checkbox',
			'options' => [
				'desc' => 'Can reserve requests',
			]
		],
	];

	public function __construct(string $setting_name, ?User $current_user = null, ?string $req_perm = null){
		if (!isset(UserPrefs::DEFAULTS[$setting_name]))
			throw new \Exception('Could not instantiate '.__CLASS__." for non-existant setting $setting_name");
		if (!isset(self::INPUT_MAP[$setting_name]))
			throw new \Exception('Could not instantiate '.__CLASS__." for $setting_name: Missing INPUT_MAP entry");
		$this->_setting_name = $setting_name;
		if ($current_user === null && Auth::$signed_in)
			 $current_user = Auth::$user;
		$this->_current_user = $current_user;
		$this->_can_save = ($req_perm === null && Auth::$signed_in && $this->_current_user->id === Auth::$user->id)
		                   || ($req_perm !== null || Permission::sufficient($req_perm, $this->_current_user->role));
	}

	private function _permissionCheck(string $check_name){
		switch($check_name){
			case 'discord_member':
			case 'not_discord_member':
				if ($this->_current_user === null)
					return false;

				if ($this->_current_user->isDiscordMember())
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
				$SELECT = '';
				$OPTGROUP = '';
				if (isset($options['opts'][''])){
					$selected = $value === '' ? 'selected' : '';
					$SELECT .= "<option value='' $selected>".CoreUtils::escapeHTML($options['opts']['']).'</option>';
					unset($options['opts']['']);
				}
				/** @noinspection ForeachSourceInspection */
				foreach ($options['opts'] as $name => $label){
					$selected = $value === $name ? 'selected' : '';
					$opt_disabled = isset($options['optperm'][$name]) && !$this->_permissionCheck($options['optperm'][$name]) ? 'disabled' : '';

					$OPTGROUP .= "<option value='$name' $selected $opt_disabled>".CoreUtils::escapeHTML($label).'</option>';
				}
				$label = CoreUtils::escapeHTML($options['optg'], ENT_QUOTES);
				$SELECT .= "<optgroup label='$label'>$OPTGROUP</optgroup>";
				return "<select name='value' $disabled>$SELECT</select>";
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

	public function __toString(){
		$map = self::INPUT_MAP[$this->_setting_name];
		$input = $this->_getInput($map['type'], $map['options'] ?? []);
		if ($input === '')
			return '';
		$savebtn = $this->_can_save ? '<button class="save typcn typcn-tick green" disabled>Save</button>' : '';
		$content = "<span>{$map['options']['desc']}</span>";
		if ($map['type'] === 'checkbox')
			$content = "$input $content";
		else $content .= " $input";
		$prefix = Auth::$signed_in && Auth::$user === $this->_current_user->id ? '' : "/@{$this->_current_user->name}";
		return <<<HTML
			<form action="$prefix/preference/set/{$this->_setting_name}">
				<label>
					$content
					$savebtn
				</label>
			</form>
HTML;
	}

	public function render(){
		echo (string) $this;
	}
}
