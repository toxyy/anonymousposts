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
                $result = $this->db->sql_query($poster_list_query, 1);
                
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
        public function is_anonymous($post_list)
        {
                $is_anonymous_query = 'SELECT is_anonymous, post_id, topic_id
                                        FROM ' . POSTS_TABLE . '
                                        WHERE post_id IN (' . implode(",", $post_list) . ')
                                        ORDER BY post_id ASC';
                
                $result = $is_anonymous_list = array();
                $result = $this->db->sql_query($is_anonymous_query);
                
                while($row = $this->db->sql_fetchrow($result))
                {
                        $is_anonymous_list[$row['topic_id']][] = $row['is_anonymous'];
                }
                        
                $this->db->sql_freeresult($result);
                unset($result);
                
                return $is_anonymous_list;
        }
}