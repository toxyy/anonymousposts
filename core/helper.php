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
                // have we already anonymously posted in this topic?
                $anon_index_query = 'SELECT anonymous_index
                                        FROM ' . POSTS_TABLE . '
                                        WHERE topic_id = ' . $topic_id . '
                                        AND poster_id = ' . $poster_id . '
                                        AND is_anonymous = 1
                                        ORDER BY post_time ASC LIMIT 1';

                $result = array();
                $result = $this->db->sql_query($anon_index_query);

                // get index of this post in that list
                $poster_index = 0;

                while($row = $this->db->sql_fetchrow($result))
                {
                        $poster_index = ($row['anonymous_index'] > 0) ? $row['anonymous_index'] : $poster_index;
                }

                $this->db->sql_freeresult($result);
                unset($result);

                // this only runs if we've never posted in this topic...
                if($poster_index == 0)
                {
                        $anon_index_query = 'SELECT COUNT(DISTINCT(poster_id)) as anon_index
                                                FROM ' . POSTS_TABLE . '
                                                WHERE topic_id = ' . $topic_id . '
                                                AND is_anonymous = 1';

                        $result2 = array();
                        $result2 = $this->db->sql_query($anon_index_query);

                        while($row = $this->db->sql_fetchrow($result2))
                        {
                                $poster_index = $row['anon_index'] + 1;
                        }

                        $this->db->sql_freeresult($result2);
                        unset($result2);
                }

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

        public function is_registered()
        {
                return $this->user->data['is_registered'];
        }

        public function this_user_id()
        {
                return $this->user->data['user_id'];
        }

        /**
        * get data from topicrow to use in the event to change it
        * supports three modes: t (default), f, and n (topic, forum, and notification)
        * topic mode returns first and last post checked in pairs [0] and [1] in the array
        * forum mode returns last post checked and topic id in pairs [0] and [1]
        * notification mode returns post_id checked and username in pairs [0] and [1]
        */
        public function is_anonymous($post_list, $mode = 't')
        {       // this supports viewforum and viewtopic
                $array_key = $mode == 'f' ? 'topic_id, forum_id'
                            : ($mode == 'n' ? 'poster_id'
                            // default case (t)
                            : 'topic_id');

                $is_anonymous_query = 'SELECT anonymous_index, is_anonymous, post_id, ' . $array_key . '
                                        FROM ' . POSTS_TABLE . '
                                        WHERE post_id IN (' . implode(",", $post_list) . ')
                                        ORDER BY post_id ASC';

                $result = $is_anonymous_list = array();
                $result = $this->db->sql_query($is_anonymous_query);

                $array_key = $mode == 'f' ? 'forum_id' : $array_key;

                $index = 0;
                while($row = $this->db->sql_fetchrow($result))
                {
                        $is_anonymous_list[$mode == 'n' ? $index : $row[$array_key]][] = $row['is_anonymous'];

                        if($mode == 'n')
                        {
                                $username_query = 'SELECT username FROM ' . USERS_TABLE . '
                                                    WHERE user_id = ' . $row['poster_id'];

                                $result2 = array();
                                $result2 = $this->db->sql_query($username_query);

                                $username = '';

                                while($row = $this->db->sql_fetchrow($result2)) $username = $row['username'];

                                $this->db->sql_freeresult($result2);
                                unset($result2);

                                $is_anonymous_list[$index][1] = $username;
                        }
                        else
                        {
                                if($mode == 'f') $is_anonymous_list[$row[$array_key]][1] = $row['topic_id'];

                                // always gets set to the last index of the row
                                $is_anonymous_list[$row[$array_key]]['last_index'] = $row['anonymous_index'];
                        }

                        $index++;
                }

                $this->db->sql_freeresult($result);
                unset($result);

                return $is_anonymous_list;
        }

        public function get_poster_id($post_id)
        {
                // poster id from post_id
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

        // removes anonymous posts from "by author" search queries... unless the searcher is staff or the searchee himself
        public function remove_anonymous_from_author_posts($search_key_array, $post_visibility)
        {
                $is_staff = $this->is_staff();

                $no_anonymous_posts = $is_staff ?: ' AND IF(p.poster_id <> ' . $this->user->data['user_id'] . ', p.is_anonymous <> 1, p.poster_id = p.poster_id)';
                // i haven't found search_key_array to actually help, but I'm going to keep it here anyways
                if(!$is_staff)
                {
                        /*foreach($search_key_array as $index => $value)
                        {
                                if($value === $post_visibility)
                                        $search_key_array[$index] .= $no_anonymous_posts;
                        }*/

                        // the magic is right here
                        $post_visibility .= $no_anonymous_posts;
                }

                return array('search_key_array' => $search_key_array, 'post_visibility' => $post_visibility);
        }
}
