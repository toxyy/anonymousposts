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
	static public function depends_on()
	{
		return array('\toxyy\anonymousposts\migrations\v_0_6_1_data');
	}

        public function update_schema()
        {
                return array(
                        'add_columns'   => array(
                                $this->table_prefix . 'posts'   => array(
                                        'poster_id_backup' => array('UINT', 0),
                                ),
                                $this->table_prefix . 'topics'   => array(
                                        'topic_first_is_anonymous' => array('BOOL', 0),
                                        'topic_last_anonymous_index' => array('UINT', 0),
                                ),
                                $this->table_prefix . 'forums'   => array(
                                        'forum_anonymous_index' => array('UINT', 0),
                                ),
                        ),
                );
        }

        public function revert_schema()
        {
                return array(
                        'drop_columns'  => array(
                                $this->table_prefix . 'posts'   => array(
                                        'poster_id_backup',
                                ),
                                $this->table_prefix . 'topics'   => array(
                                        'topic_first_is_anonymous',
                                        'topic_last_anonymous_index',
                                ),
                                $this->table_prefix . 'forums'   => array(
                                        'forum_anonymous_index',
                                ),
                        ),
                );
        }
}
