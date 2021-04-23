<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_6_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return ($this->config['anonymous_posts_version'] >= 0.06);
	}

	static public function depends_on()
	{
		return ['\toxyy\anonymousposts\migrations\release_0_1_0_data'];
	}

	public function update_date()
	{
		return [
			['config.update', ['anonymous_posts_version', '0.06']],
		];
	}

	public function update_schema()
	{
		return [
			'add_columns'   => [
				$this->table_prefix . 'posts'   => [
					'anonymous_index' => ['UINT', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns'  => [
				$this->table_prefix . 'posts'   => [
					'anonymous_index',
				],
			],
		];
	}
}
