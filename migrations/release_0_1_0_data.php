<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class release_0_1_0_data extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['anonymous_posts_version']);
	}

	public function update_data()
	{
		return [
			// Add configs
			['config.add', ['anonymous_posts_version', '0.01']],
			// Add permissions
			['permission.add', ['u_anonpost']],
			['permission.add', ['f_anonpost', false]],
		];
	}

	public function update_schema()
	{
		return [
			'add_columns'   => [
				$this->table_prefix . 'posts'   => [
					'is_anonymous'  => ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns'  => [
				$this->table_prefix . 'posts'   => [
					'is_anonymous',
				],
			],
		];
	}
}
