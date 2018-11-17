<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_9_6_data extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\toxyy\anonymousposts\migrations\v_0_9_5_data');
	}

	/**
	 * Add or update data in the database
	 *
	 * @return array Array of table data
	 * @access public
	 */
        public function update_data()
        {
                return array(
                        // update the new anonymous_index column in the posts table with their values, if you had this extension installed already
                        array('custom', array(array($this, 'update_poster_id_data'))),
		);
        }

        // see above
	public function update_poster_id_data()
	{
                $sql = 'UPDATE ' . POSTS_TABLE . '
                        SET poster_id = poster_id_backup
                        WHERE is_anonymous = 1
                        AND poster_id_backup > 0
                        AND poster_id < 2';
                $this->db->sql_query($sql);
	}
}
