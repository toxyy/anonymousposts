<?php
/**
* 
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
* 
*/

namespace toxyy\anonymousposts\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\event\data as event;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\template\template */
	protected $template;
        
	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\auth\auth */
	protected $auth;
        
	/** @var \phpbb\request\request */
	protected $request;

	/**
	* Constructor
	*
	* @param \phpbb\template\template                       $template
	* @param \phpbb\user                                    $user
	* @param \phpbb\db\driver\driver_interface              $db
	* @param \phpbb\auth\auth                               $auth
	* @param \phpbb\request\request		 		$request
	*
	*/
	public function __construct(
                \phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\auth\auth $auth,
		\phpbb\request\request $request
	)
	{
                $this->template                                 = $template;
		$this->user					= $user;
		$this->db					= $db;
		$this->auth                                     = $auth;
		$this->request                                  = $request;
	}
        
	static public function getSubscribedEvents()
	{
		return [
			'core.user_setup'                               => 'core_user_setup',
			'core.permissions'                              => 'core_permissions',
                        'core.modify_posting_auth'                      => 'modify_posting_auth',
                        'core.viewtopic_assign_template_vars_before'    => 'viewtopic_assign_template_vars_before',
                        'core.viewtopic_post_rowset_data'               => 'viewtopic_post_rowset_data',
                        'core.viewtopic_modify_post_row'                => 'viewtopic_modify_post_row',
                        'core.topic_review_modify_row'                  => 'topic_review_modify_row',
			'core.modify_submit_post_data'                  => 'modify_submit_post_data',
                        'core.submit_post_modify_sql_data'              => 'submit_post_modify_sql_data',
		];
	}

	public function core_user_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
                
		$lang_set_ext[] = [
			'ext_name' => 'toxyy/anonymousposts',
			'lang_set' => 'common',
		];
                
		$event['lang_set_ext'] = $lang_set_ext;
	}
        
        // fix permissions for acp
        public function core_permissions($event)
        {
            	$permissions = $event['permissions'];
		$permissions['u_anonpost'] = array('lang' => 'ANP_PERMISSIONS', 'cat' => 'post');
		$permissions['f_anonpost'] = array('lang' => 'ANP_PERMISSIONS', 'cat' => 'post');
		$event['permissions'] = $permissions;
        }
        
        // add F_ANONPOST variable to posting.php
        public function modify_posting_auth($event)
        {
            $this->template->assign_vars(array(
                    'F_ANONPOST'   => $this->auth->acl_get('f_anonpost', $event['forum_id']),
                    'U_ANONPOST'   => $this->auth->acl_get('u_anonpost'),
            ));
        }
        
        // add F_ANONPOST variable to viewtopic.php
        public function viewtopic_assign_template_vars_before($event)
        {
                $this->template->assign_vars(array(
                        'F_ANONPOST'   => $this->auth->acl_get('f_anonpost', $event['forum_id']),
                ));
        }
        
        // get is_anonymous in postrow sql query
        public function viewtopic_post_rowset_data($event)
        {
                $is_anonymous = $event['row']['is_anonymous'];
                $rowset = $event['rowset_data'];
                
                $rowset = array_merge($rowset, array(
                        'is_anonymous' => $is_anonymous,
                ));
                
                $event['rowset_data'] = $rowset;
        }
        
        // delete info in anonymous posts for normal members
        public function viewtopic_modify_post_row($event)
        {
                $topic_data = $event['topic_data'];
                $post_id = $event['row']['post_id'];
                $topic_id = $topic_data['topic_id'];
                $forum_id = $event['row']['forum_id'];
                $post_row = $event['post_row'];
                
                // thanks juhas https://www.phpbb.com/community/viewtopic.php?f=71&t=2151259#p13117355
                $admins = $this->auth->acl_get_list(false, 'a_', false);
                $mods = $this->auth->acl_get_list(false, 'm_', false);

                $is_staff = in_array($this->user->data['user_id'], $admins[0]['a_']);
                if(!$is_staff)
                        $is_staff = in_array($this->user->data['user_id'], $mods[0]['m_']);
                
                $is_anonymous = $event['row']['is_anonymous'];
                
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
                        if($row['poster_id'] == $event['poster_id'])
                                $poster_index = $count;
                        
                        $count++;
                }
                
                $this->db->sql_freeresult($result);
                unset($result);
                
                // delete info from the deleted post hidden div so sneaky members cant find out who it was
                if(!$is_staff && $is_anonymous)
                {       $post_row_old = $post_row;
                        $post_row = array_map(create_function('$n', 'return null;'), $post_row);
                        $post_row['POST_AUTHOR_FULL'] = $post_row['POST_AUTHOR'] = "Anonymous " . $poster_index;
                        $post_row['POST_DATE'] = $post_row_old['POST_DATE'];
                        $post_row['MESSAGE'] = $post_row_old['MESSAGE'];
                        $post_row['POST_SUBJECT'] = $post_row_old['POST_SUBJECT'];
                        $post_row['EDITED_MESSAGE'] = $post_row_old['EDITED_MESSAGE'];
                        $post_row['EDIT_REASON'] = $post_row_old['EDIT_REASON'];
                        $post_row['DELETED_MESSAGE'] = $post_row_old['DELETED_MESSAGE'];
                        $post_row['DELETE_REASON'] = $post_row_old['DELETE_REASON'];
                        $post_row['BUMPED_MESSAGE'] = $post_row_old['BUMPED_MESSAGE'];
                        $post_row['MINI_POST_IMG'] = $post_row_old['MINI_POST_IMG'];
                        $post_row['U_EDIT'] = $post_row_old['U_EDIT'];
                        $post_row['U_QUOTE'] = $post_row_old['U_QUOTE'];
                        $post_row['U_DELETE'] = $post_row_old['U_DELETE'];
                        $post_row['U_INFO'] = $post_row_old['U_INFO'];
                        $post_row['U_REPORT'] = $post_row_old['U_REPORT'];
                        $post_row['U_APPROVE_ACTION'] = $post_row_old['U_APPROVE_ACTION'];
                        $post_row['U_MCP_REPORT'] = $post_row_old['U_MCP_REPORT'];
                        $post_row['U_MCP_APPROVE'] = $post_row_old['U_MCP_APPROVE'];
                        $post_row['U_MCP_RESTORE'] = $post_row_old['U_MCP_RESTORE'];
                        $post_row['POST_ICON_IMG'] = $post_row_old['POST_ICON_IMG'];
                        $post_row['POST_ICON_IMG_WIDTH'] = $post_row_old['POST_ICON_IMG_WIDTH'];
                        $post_row['POST_ICON_IMG_HEIGHT'] = $post_row_old['POST_ICON_IMG_HEIGHT'];
                        $post_row['POST_ICON_IMG_ALT'] = $post_row_old['POST_ICON_IMG_ALT'];
                        $post_row['POST_NUMBER'] = $post_row_old['POST_NUMBER'];
                        $post_row['MINI_POST'] = $post_row_old['MINI_POST'];
                        $post_row['S_HAS_ATTACHMENTS'] = $post_row_old['S_HAS_ATTACHMENTS'];
                        $post_row['S_MULTIPLE_ATTACHMENTS'] = $post_row_old['S_MULTIPLE_ATTACHMENTS'];
                        $post_row['S_POST_UNAPPROVED'] = $post_row_old['S_POST_UNAPPROVED'];
                        $post_row['S_POST_DELETED'] = $post_row_old['S_POST_DELETED'];
                        $post_row['L_POST_DELETED_MESSAGE'] = $post_row_old['L_POST_DELETED_MESSAGE'];
                        $post_row['S_POST_REPORTED'] = $post_row_old['S_POST_REPORTED'];
                        $post_row['S_UNREAD_POST'] = $post_row_old['S_UNREAD_POST'];
                        $post_row['S_DELETE_PERMANENT'] = $post_row_old['S_DELETE_PERMANENT'];
                        $post_row['U_MINI_POST'] = $post_row_old['U_MINI_POST'];
                        $post_row['U_NEXT_POST_ID'] = $post_row_old['U_NEXT_POST_ID'];
                        $post_row['U_NOTES'] = $post_row_old['U_NOTES'];
                        $post_row['U_WARN'] = $post_row_old['U_WARN'];
                        $post_row['S_FIRST_UNREAD'] = $post_row_old['S_FIRST_UNREAD'];
                        $post_row['S_POST_HIDDEN'] = $post_row_old['S_POST_HIDDEN'];
                        $post_row['U_PREV_POST_ID'] = $post_row_old['U_PREV_POST_ID'];
                        $post_row['L_POST_DISPLAY'] = $post_row_old['L_POST_DISPLAY'];
                        $post_row['S_POST_HIDDEN'] = $post_row_old['S_POST_HIDDEN'];
                        $post_row['L_IGNORE_POST'] = $post_row_old['L_IGNORE_POST'];
                        $post_row['S_IGNORE_POST'] = $post_row_old['S_IGNORE_POST'];
                        $post_row['S_TOPIC_POSTER'] = $post_row_old['S_TOPIC_POSTER'];
                        $post_row['S_DISPLAY_NOTICE'] = $post_row_old['S_DISPLAY_NOTICE'];
                        unset($post_row_old);
                }
                
                $post_row['IS_ANONYMOUS'] = $is_anonymous;
                $post_row['IS_STAFF'] = $is_staff;
                        
                $event['post_row'] = $post_row;
        }
        
        // make posts anonymous in posting.php topic review
        public function topic_review_modify_row($event)
        {
                $post_row = $event['post_row'];
                
                // thanks juhas https://www.phpbb.com/community/viewtopic.php?f=71&t=2151259#p13117355
                $admins = $this->auth->acl_get_list(false, 'a_', false);
                $mods = $this->auth->acl_get_list(false, 'm_', false);

                $is_staff = in_array($this->user->data['user_id'], $admins[0]['a_']);
                if(!$is_staff)
                        $is_staff = in_array($this->user->data['user_id'], $mods[0]['m_']);
                
                // same as above
                $poster_list_query = 'SELECT DISTINCT poster_id FROM ' . POSTS_TABLE . '
                      WHERE topic_id = ' . $event['topic_id'] . '
                      AND is_anonymous = 1
                      ORDER BY post_time ASC';
                
                $result = $poster_list = array();
                $result = $this->db->sql_query($poster_list_query, 1);
                
                $poster_index = 0;
                $count = 1;
                while($row = $this->db->sql_fetchrow($result))
                {
                        if($event['cur_post_id'] == $event['poster_id'])
                                $poster_index = $count;
                        
                        $count++;
                }
                
                $this->db->sql_freeresult($result);
                unset($result);
                
                if(!$is_staff && $event['row']['is_anonymous'])
                {
                        $post_row['POST_AUTHOR_FULL'] = $post_row['POST_AUTHOR'] = $post_row['POSTER_QUOTE'] = "Anonymous " . $poster_index;
                        $post_row['POST_AUTHOR_COLOUR'] = $post_row['U_POST_AUTHOR'] = 
                        $post_row['S_FRIEND'] = $post_row['USER_ID'] = NULL;
                }
                
                $post_row['IS_STAFF'] = $is_staff;
                $post_row['IS_ANONYMOUS'] = $event['row']['is_anonymous'];
                
                $event['post_row'] = $post_row;
        }
        
        // add variables to $data for use in submit_post_modify_sql_data
        public function modify_submit_post_data($event)
        {
                $data = $event['data'];
                
                $anonpost = $this->request->variable('anonpost', 0, true);
                
                $data = array_merge($data, array(
			'is_anonymous' => isset($anonpost) ? $anonpost : 0,
		));
                                
                $event['data'] = $data;
        }
        
        // add is_anonymous to the sql data
        public function submit_post_modify_sql_data($event)
        {
                $sql_data = $event['sql_data'];
                $data = $event['data'];
                
                $sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
                        'is_anonymous'  => isset($data['is_anonymous']) ? $data['is_anonymous'] : 0,
                ));
                
                $event['sql_data'] = $sql_data;
        }
}