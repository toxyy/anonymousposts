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
        public function get_poster_index($topic_id)
        {
                // have we already anonymously posted in this topic?
                // 0.7.0 - redundancy added (AND anonymous_index > 0)
                $anon_index_query = 'SELECT anonymous_index
                                        FROM ' . POSTS_TABLE . '
                                        WHERE topic_id = ' . $topic_id . '
                                        AND poster_id = ' . $this->user->data['user_id'] . '
                                        AND is_anonymous = 1
                                        AND anonymous_index > 0
                                        ORDER BY post_time ASC LIMIT 1';

                $result = array();
                $result = $this->db->sql_query($anon_index_query);

                // these two get index of this post in that list
                $anonymous_index = (int) $this->db->sql_fetchfield('anonymous_index');
                $poster_index = !($anonymous_index >= 0) ?: $anonymous_index;

                $this->db->sql_freeresult($result);
                unset($result);

                // this only runs if we've never posted in this topic, having data from previous query...
                if($poster_index == 0)
                {
                        $anon_index_query = 'SELECT COUNT(DISTINCT(poster_id)) as anon_index
                                                FROM ' . POSTS_TABLE . '
                                                WHERE topic_id = ' . $topic_id . '
                                                AND is_anonymous = 1';

                        $result = array();
                        $result = $this->db->sql_query($anon_index_query);

                        $poster_index = ((int) $this->db->sql_fetchfield('anon_index')) + 1;

                        $this->db->sql_freeresult($result);
                        unset($result);
                }

                return $poster_index;
        }

        /**
        * checks if the current user is an admin or mod
        * thanks juhas https://www.phpbb.com/community/viewtopic.php?f=71&t=2151259#p13117355
        * v0.8.0 - check for mods first, since there's more of them. is the admin not in the mod list?
        * also made it a bit smaller
        * v0.9.5 noticed that this was giving the right result by coincidence, and this is better used anyways.
        */
        public function is_staff()
        {
                $user_id = $this->user->data['user_id'];

                if(!empty($this->auth->acl_get_list($user_id, 'm_')))
                        return true;

                if(!empty($this->auth->acl_get_list($user_id, 'a_')))
                        return true;

                return false;
        }

        public function is_registered()
        {
                return $this->user->data['is_registered'];
        }

        /**
        * get data from topicrow to use in the event to change it
        * supports three modes: t (default), f, and n (topic, forum, and notification)
        * topic mode returns first and last post checked in pairs [0] and [1] in the array
        * forum mode returns last post checked and topic id in pairs [0] and [1]
        * notification mode returns post_id checked and username in pairs [0] and [1]
        */
        public function is_anonymous($post_list)
        {
                $is_anonymous_query = 'SELECT anonymous_index, is_anonymous, poster_id
                                        FROM ' . POSTS_TABLE . '
                                        WHERE post_id IN (' . implode(",", $post_list) . ')
                                        ORDER BY post_id ASC';

                $result = $is_anonymous_list = $notification_list = array();
                $result = $this->db->sql_query($is_anonymous_query);

                $index = 0;
                $continue = false;
                while($row = $this->db->sql_fetchrow($result))
                {
                        if($$row['is_anonymous']) $continue = true;

                        $is_anonymous_list[$index][] = $row['is_anonymous'];
                        $is_anonymous_list[$index]['poster_id'] = $row['poster_id'];

                        $index++;
                }

                $this->db->sql_freeresult($result);
                unset($result);

                // doesn't run if no anonymous usernames to change :)
                if($continue)
                {
                        $username_query = 'SELECT username FROM ' . USERS_TABLE . '
                                            WHERE user_id IN (' . implode(",", array_column($is_anonymous_list, 'poster_id')) . ')';

                        $result2 = $testo = array();
                        $result2 = $this->db->sql_query($username_query);

                        $index = 0;
                        while($row = $this->db->sql_fetchrow($result2))
                        {
                                $is_anonymous_list[$index][1] = $row['poster_id'];

                                $index++;
                        }

                        $this->db->sql_freeresult($result2);
                        unset($result2);
                }

                return $is_anonymous_list;
        }

        /**
        * get data from topicrow to use in the event to change it
        * removes anonymous posts from "by author" search queries... unless the searcher is staff or the searchee himself
        * i haven't found search_key_array to actually help at all
        * 0.8.0. - removed that bit
        * 0.8.6? - shortened to one line
        * 0.9.5 - fixed that to actually work
        */
        public function remove_anonymous_from_author_posts($post_visibility, $is_staff)
        {
                return array('post_visibility' => ($is_staff ? $post_visibility : $post_visibility . ' AND IF(p.poster_id <> ' . $this->user->data['user_id'] . ', p.is_anonymous <> 1, p.poster_id = p.poster_id)'));
        }
}
