<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_9_9 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return ($this->config['anonymous_posts_version'] >= 0.099);
	}
	static public function depends_on()
	{
		return ['\toxyy\anonymousposts\migrations\v_0_9_5'];
	}

	public function update_data()
	{
		return [
			['config.update', ['anonymous_posts_version', '0.099']],
			// Add permissions
			['permission.add', ['f_edit_anonpost', false]],
			['permission.add', ['u_edit_anonpost']],
			['permission.permission_set', ['ADMINISTRATORS', 'u_edit_anonpost', 'group']],
			['permission.permission_set', ['GLOBAL_MODERATORS', 'u_edit_anonpost', 'group']],
		];
	}
}
