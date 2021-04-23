<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_9_5 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return ($this->config['anonymous_posts_version'] >= 0.095);
	}
	static public function depends_on()
	{
		return ['\toxyy\anonymousposts\migrations\v_0_6_0'];
	}

	public function update_date()
	{
		return [
			['config.update', ['anonymous_posts_version', '0.095']],
		];
	}

	public function update_schema()
	{
		return [
			'add_columns'   => [
				$this->table_prefix . 'topics'   => [
					'topic_first_anonymous_index' => ['UINT', 0],
					'topic_last_anonymous_index' => ['UINT', 0],
				],
				$this->table_prefix . 'forums'   => [
					'forum_anonymous_index' => ['UINT', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns'  => [
				$this->table_prefix . 'topics'   => [
					'topic_first_anonymous_index',
					'topic_last_anonymous_index',
				],
				$this->table_prefix . 'forums'   => [
					'forum_anonymous_index',
				],
			],
		];
	}
}
