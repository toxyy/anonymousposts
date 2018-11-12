<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_9_5_data extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\toxyy\anonymousposts\migrations\v_0_9_5');
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
                        array('custom', array(array($this, 'update_new_anonymous_columns'))),
		);
        }

        // see above
	public function update_new_anonymous_columns()
	{
                $sql = 'UPDATE ' . TOPICS_TABLE . ' t
                        INNER JOIN( SELECT (p.post_id) AS pid
                                    FROM ' . POSTS_TABLE . ' AS p
                                    WHERE is_anonymous = 1
                        ) AS q ON q.pid = t.topic_first_post_id
                        SET t.topic_first_is_anonymous = 1';
                $this->db->sql_query($sql);

                // i know i know but i just want to go to bed, i only know of one person who uses this and i honestly cant be asked to fix this ever
                sleep(4);

                $sql2 = 'UPDATE ' . TOPICS_TABLE . ' t
                        INNER JOIN( SELECT (p.post_id) AS pid,
                                           (p.anonymous_index) AS anon_index
                                    FROM ' . POSTS_TABLE . ' AS p
                                    WHERE is_anonymous = 1
                        ) AS q ON q.pid = t.topic_last_post_id
                        SET t.topic_last_anonymous_index = q.anon_index';
                $this->db->sql_query($sql2);

                sleep(4);

                $sql3 = 'UPDATE ' . FORUMS_TABLE . ' f
                        INNER JOIN( SELECT (t.topic_last_post_id) AS pid,
                                           (t.topic_last_anonymous_index) AS anon_index
                                    FROM ' . TOPICS_TABLE . ' AS t
                                    WHERE topic_last_anonymous_index > 0
                        ) AS q ON q.pid = f.forum_last_post_id
                        SET f.forum_anonymous_index = q.anon_index';
                $this->db->sql_query($sql3);
	}
}
