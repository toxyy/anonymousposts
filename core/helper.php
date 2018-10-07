<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\core;

class helper
{
	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/**
	* Constructor
	*
	* @param \phpbb\user                                    $user
	* @param \phpbb\db\driver\driver_interface              $db
	* @param \phpbb\auth\auth                               $auth
        *
	*/
	public function __construct(
		\phpbb\user $user,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\auth\auth $auth
        )
	{
		$this->user					= $user;
		$this->db					= $db;
		$this->auth                                     = $auth;
	}

        // get unique poster index for consistent distinct anonymous posters
        public function get_poster_index($topic_id, $poster_id)
        {
                // get a list of unique posters in the topic in time order
                $poster_list_query = 'SELECT DISTINCT poster_id FROM ' . POSTS_TABLE . '
                                      WHERE topic_id = ' . $topic_id . '
                                      AND is_anonymous = 1
                                      ORDER BY post_time ASC';

                $result = $poster_list = array();
                $result = $this->db->sql_query($poster_list_query);

                // get index of this post in that list
                $poster_index = 0;
                $count = 1;
                while($row = $this->db->sql_fetchrow($result))
                {
                        if($row['poster_id'] == $poster_id)
                                $poster_index = $count;

                        $count++;
                }

                $this->db->sql_freeresult($result);
                unset($result);

                return $poster_index;
        }

        // checks if the current user is an admin or mod
        // thanks juhas https://www.phpbb.com/community/viewtopic.php?f=71&t=2151259#p13117355
        public function is_staff()
        {
                $admins = $this->auth->acl_get_list(false, 'a_', false);
                $mods = $this->auth->acl_get_list(false, 'm_', false);

                $is_staff = in_array($this->user->data['user_id'], $admins[0]['a_']);
                if(!$is_staff)
                        $is_staff = in_array($this->user->data['user_id'], $mods[0]['m_']);

                return $is_staff;
        }

        // get data from topicrow to use in the event to change it
        // supports two modes, t and f (topic and forum)
        // topic mode returns first and last post in pairs [0] and [1] in the array
        // forum mode returns last post and topic id in pairs [0] and [1]
        public function is_anonymous($post_list, $mode)
        {       // this supports viewforum and viewtopic
                $array_key = $mode == 't' ? 'topic_id' : 'topic_id, forum_id';

                $is_anonymous_query = 'SELECT is_anonymous, post_id, ' . $array_key . '
                                        FROM ' . POSTS_TABLE . '
                                        WHERE post_id IN (' . implode(",", $post_list) . ')
                                        ORDER BY post_id ASC';

                $result = $is_anonymous_list = array();
                $result = $this->db->sql_query($is_anonymous_query);

                $array_key = $mode == 'f' ? 'forum_id' : $array_key;

                while($row = $this->db->sql_fetchrow($result))
                {
                        $is_anonymous_list[$row[$array_key]][] = $row['is_anonymous'];

                        if($mode == 'f') $is_anonymous_list[$row[$array_key]][1] = $row['topic_id'];
                }

                $this->db->sql_freeresult($result);
                unset($result);

                return $is_anonymous_list;
        }

        public function get_poster_id($post_id)
        {
                // get a list of unique posters in the topic in time order
                $poster_id_query = 'SELECT poster_id FROM ' . POSTS_TABLE . '
                                      WHERE post_id = ' . $post_id;

                $result = array();
                $result = $this->db->sql_query($poster_id_query);

                $poster_id = 0;

                while($row = $this->db->sql_fetchrow($result))
                {
                        $poster_id = $row['poster_id'];
                }

                $this->db->sql_freeresult($result);
                unset($result);

                return $poster_id;
        }
}
