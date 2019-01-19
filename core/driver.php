<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/
namespace toxyy\anonymousposts\core;

class driver
{       /** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/**
	* Constructor
	*
	* @param \phpbb\db\driver\driver_interface              $db
        *
	*/
	public function __construct(\phpbb\db\driver\driver_interface $db)
	{
		$this->db = $db;
	}

        // get unique poster index for consistent distinct anonymous posters
        public function get_poster_index($topic_id, $poster_id)
        {
                // have we already anonymously posted in this topic?
                // 0.9.12 - rewrote the whole thing
                $anon_index_query = "SELECT (    SELECT anonymous_index
                                                 FROM " . POSTS_TABLE . "
                                                 WHERE poster_id = $poster_id AND topic_id = $topic_id AND anonymous_index > 0
                                                 ORDER BY post_time ASC LIMIT 1
                                            ) AS old_index,
                                            (MAX(anonymous_index) + 1) AS new_index
                                     FROM " . POSTS_TABLE . "
                                     WHERE topic_id = $topic_id AND anonymous_index > 0";

                $result = array();
                $result = $this->db->sql_query($anon_index_query);

                $old_index = $new_index = 0;
                while($row = $this->db->sql_fetchrow($result))
                {
                        $old_index = $row['old_index'];
                        $new_index = $row['new_index'];
                }
                $this->db->sql_freeresult($result);
                unset($result);
                // redundancy to ensure NO anon 0s... too critical of a bug.
                $anon_zero_fix = ($new_index == 0) ? 1 : $new_index;
                // return old_index if it is > 0
                return (($old_index > 0) ? $old_index : $anon_zero_fix);
        }

        // get username from db via user_id, needed for deanonymizing notifications
        public function get_username($user_id)
        {
                $sql = "SELECT username
                        FROM " . USERS_TABLE . "
                        WHERE user_id = $user_id";

                $result = $this->db->sql_query($sql);
                $username = $this->db->sql_fetchfield('username');
                $this->db->sql_freeresult($result);
                unset($result);

                return $username;
        }

        // get data from topicrow to use in the event to change it
        public function is_anonymous($post_list)
        {
                $is_anonymous_query = 'SELECT anonymous_index, is_anonymous, poster_id
                                        FROM ' . POSTS_TABLE . '
                                        WHERE ' . $this->db->sql_in_set('post_id', $post_list) . '
                                        ORDER BY post_id ASC';

                $result = $is_anonymous_list = $notification_list = array();
                $result = $this->db->sql_query($is_anonymous_query);

                $index = 0;
                $continue = false;
                while($row = $this->db->sql_fetchrow($result))
                {
                        if($row['is_anonymous']) $continue = true;

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
                                           WHERE ' . $this->db->sql_in_set('user_id', array_column($is_anonymous_list, 'poster_id'));

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

        // update the moved posts`anon indices if they don't match their poster's current anon index in the topic
        // assumes that the topic anon indices arent messed up, does NOT sync the whole topic.  just newly moved posts that might not have matching anon indices
        public function move_sync_topic_anonymous_posts($topic_id, $post_id_list)
        {
                $this->db->sql_transaction('begin');
                // select some data & group all poster_ids that dont have consistent anon indexes
                // dont select the posts that got moved, those anon indices are likely not the same as their posters OG index
                // then update all anon indexes of all posts in the thread by those users to their OG index
                $sql = "UPDATE " . POSTS_TABLE . " AS p
                        INNER JOIN (    SELECT merged.poster_id, merged.anonymous_index AS new_index,
                                               old.post_id, old.anonymous_index AS old_index
                                        FROM " . POSTS_TABLE . " AS merged,
                                             " . POSTS_TABLE . " AS old
                                        WHERE " . $this->db->sql_in_set('merged.post_id', $post_id_list) . "
                                        AND merged.anonymous_index > 0
                                        AND merged.anonymous_index <> old.anonymous_index
                                        AND old.topic_id = $topic_id
                                        AND " . $this->db->sql_in_set('old.post_id', $post_id_list, true) . "
                                        AND old.anonymous_index > 0
                                        AND old.poster_id = merged.poster_id
                                        GROUP BY merged.poster_id
                                        ORDER BY NULL
                        ) AS postdata
                        SET p.anonymous_index = postdata.old_index
                        WHERE p.anonymous_index > 0
                        AND " . $this->db->sql_in_set('p.post_id', $post_id_list) . "
                        AND p.poster_id = postdata.poster_id";
                $this->db->sql_query($sql);
                $this->db->sql_transaction('commit');
        }
}
