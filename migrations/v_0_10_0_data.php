<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_10_0_data extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\toxyy\anonymousposts\migrations\v_0_9_9_data');
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
                        // rename column, change it from bool to mediumint(8)
                        array('custom', array(array($this, 'update_column_data'))),
		);
        }

        // see above
	public function update_column_data()
	{
                $sql = "ALTER TABLE " . TOPICS_TABLE . "
                        CHANGE topic_first_is_anonymous topic_first_anonymous_index MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0'";
                $this->db->sql_query($sql);
	}
}
