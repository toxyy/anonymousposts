<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_12_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return ($this->config['anonymous_posts_version'] >= 0.120);
	}
	static public function depends_on()
	{
		return ['\toxyy\anonymousposts\migrations\v_0_11_2'];
	}

	public function update_date()
	{
		return [
			['config.update', ['anonymous_posts_version', '0.120']],
		];
	}

	public function update_schema()
	{
		return [
			'add_columns'	=> [
				$this->table_prefix . 'forums'	=> [
					'anp_post_force'				=> ['BOOL', 0],
					'anp_ignore_post_permissions'	=> ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns'	=> [
				$this->table_prefix . 'forums'	=> [
					'anp_post_force',
					'anp_ignore_post_permissions',
				],
			],
		];
	}
}
