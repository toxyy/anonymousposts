<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_9_6_schema extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\toxyy\anonymousposts\migrations\v_0_9_6_data');
	}

        public function update_schema()
        {
                return array(
                        'drop_columns'  => array(
                                $this->table_prefix . 'posts'   => array(
                                        'poster_id_backup',
                                ),
                        ),
                );
        }

        public function revert_schema()
        {
                return array(
                        'add_columns'  => array(
                                $this->table_prefix . 'posts'   => array(
                                        'poster_id_backup' => array('UINT', 0),
                                ),
                        ),
                );
        }
}
