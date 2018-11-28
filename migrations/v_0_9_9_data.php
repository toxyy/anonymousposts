<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_9_9_data extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\toxyy\anonymousposts\migrations\v_0_9_6_schema');
	}

        public function update_data()
        {
                return array(
			// Add permissions
			array('permission.add', array('f_edit_anonpost', false)),
			array('permission.add', array('u_edit_anonpost')),
                        array('permission.permission_set', array('ADMINISTRATORS', 'u_edit_anonpost', 'group')),
                        array('permission.permission_set', array('GLOBAL_MODERATORS', 'u_edit_anonpost', 'group')),
                );
        }
}
