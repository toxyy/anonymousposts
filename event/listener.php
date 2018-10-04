<?php
/**
* 
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
* 
*/

namespace toxyy\anonymousposts\event;

/**
* Event listener
*/

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\language\language */
	protected $language;
        
	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\auth\auth */
	protected $auth;
        
	/** @var \phpbb\request\request */
	protected $request;
        
	/** @var \toxyy\anonymousposts\core\helper */
	protected $helper;

	/**
	* Constructor
	*
	* @param \phpbb\template\template                       $template
	* @param \phpbb\auth\auth                               $auth
	* @param \phpbb\request\request		 		$request
	* @param \toxyy\anonymousposts\core\helper		$helper
	*
	*/
	public function __construct(
                \phpbb\language\language $language,
                \phpbb\template\template $template,
		\phpbb\auth\auth $auth,
		\phpbb\request\request $request,
                $helper
	)
	{
                $this->language                                 = $language;
                $this->template                                 = $template;
		$this->auth                                     = $auth;
		$this->request                                  = $request;
		$this->helper                                   = $helper;
	}
        
	static public function getSubscribedEvents()
	{
		return [
			'core.user_setup'                               => 'core_user_setup',
			'core.permissions'                              => 'core_permissions',
                        'core.modify_posting_auth'                      => 'modify_posting_auth',
                        'core.posting_modify_post_data'                 => 'posting_modify_post_data',
                        'core.viewtopic_assign_template_vars_before'    => 'viewtopic_assign_template_vars_before',
                        'core.viewtopic_post_rowset_data'               => 'viewtopic_post_rowset_data',
                        'core.viewtopic_modify_post_row'                => 'viewtopic_modify_post_row',
                        'core.viewforum_modify_topics_data'             => 'viewforum_modify_topics_data',
                        'core.viewforum_modify_topicrow'                => 'viewforum_modify_topicrow',
                        'core.display_forums_before'                    => 'display_forums_before',
                        'core.topic_review_modify_row'                  => 'topic_review_modify_row',
                        'core.search_modify_rowset'                     => 'search_modify_rowset',
                        'core.search_modify_post_row'                   => 'search_modify_post_row',
                        'core.search_modify_tpl_ary'                    => 'search_modify_tpl_ary',
			'core.modify_submit_post_data'                  => 'modify_submit_post_data',
                        'core.submit_post_modify_sql_data'              => 'submit_post_modify_sql_data',
                        'core.modify_submit_notification_data'          => 'modify_submit_notification_data',
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
        
        // change poster information in quotes
        public function posting_modify_post_data($event)
        {
                $post_data = $event['post_data'];
                
                $this->template->assign_vars(array(
                        'POST_IS_ANONYMOUS' => $post_data['is_anonymous'] ? 'checked' : '',
                ));
                
                var_dump($post_data);
                
                if($post_data['is_anonymous'])
                {
                        $poster_index = $this->helper->get_poster_index($event['topic_id'], $post_data['poster_id']);
                        $post_data['quote_username'] = $this->language->lang('ANP_DEFAULT') . ' ' . $poster_index;
                        $post_data['poster_id'] = 0;
                }

                $event['post_data'] = $post_data;
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
                
                $is_staff = $this->helper->is_staff();
                $is_anonymous = $event['row']['is_anonymous'];
                
                $poster_index = $this->helper->get_poster_index($topic_id, $event['poster_id']);
                
                // delete info from the deleted post hidden div so sneaky members cant find out who it was
                if(!$is_staff && $is_anonymous)
                {       $post_row_old = $post_row;
                        $post_row = array_map(create_function('$n', 'return null;'), $post_row);
                        $post_row['POST_AUTHOR_FULL'] = $post_row['POST_AUTHOR'] = $this->language->lang('ANP_DEFAULT') . ' ' . $poster_index;
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
        
        // get array of first and last posts, check them for anonymity
        public function viewforum_modify_topics_data($event)
        {
                $rowset = $event['rowset'];
                
                $post_list = array_merge(array_column($rowset, 'topic_first_post_id'), array_column($rowset, 'topic_last_post_id'));
                $is_anonymous_list = $this->helper->is_anonymous($post_list, 't');
                
                foreach($rowset as $index => $value)
                {
                        // fix last post null issue if topic has no replies
                        if($is_anonymous_list[$index][1] == NULL) $is_anonymous_list[$index][1] = $is_anonymous_list[$index][0];
                        
                        $rowset[$index]['topic_first_is_anonymous'] = $is_anonymous_list[$index][0];
                        $rowset[$index]['topic_last_is_anonymous'] = $is_anonymous_list[$index][1];
                }
                
                $event['rowset'] = $rowset;
        }
        
        // update first and last post in topicrow if they are anonymous
        public function viewforum_modify_topicrow($event)
        {
                $row = $event['row'];
                $topic_row = $event['topic_row'];
                $anonymous = $this->language->lang('ANP_DEFAULT');
                
                if(!$this->helper->is_staff())
                {
                        if($row['topic_first_is_anonymous'])
                        {
                                $topic_row['TOPIC_AUTHOR'] = $topic_row['TOPIC_AUTHOR_FULL'] = $anonymous . ' 1';
                                $topic_row['TOPIC_AUTHOR_COLOUR'] = NULL;
                        }

                        if($row['topic_last_is_anonymous'])
                        {
                                $poster_index = $this->helper->get_poster_index($topic_row['TOPIC_ID'], $row['topic_last_poster_id']);
                                $topic_row['LAST_POST_AUTHOR'] = $topic_row['LAST_POST_AUTHOR_FULL'] = $anonymous . ' ' . $poster_index;
                                $topic_row['LAST_POST_AUTHOR_COLOUR'] = NULL;
                        }
                }
                
                $event['topic_row'] = $topic_row;
        }
        
        // handles changing the last poster name for forumrow
        public function display_forums_before($event)
        {
                $forum_rows = $event['forum_rows'];
                
                $post_list = array_merge(array_column($forum_rows, 'forum_last_post_id'));
                $is_anonymous_list = $this->helper->is_anonymous($post_list, 'f');
                
                foreach($forum_rows as $index => $value)
                {
                        $last_post_id = $forum_rows[$index]['forum_last_post_id'];
                        $post_list_index = array_search($last_post_id, $post_list);
                        
                        if($post_list_index > 0)
                        {
                                $forum_rows[$index]['forum_last_is_anonymous'] = $is_anonymous_list[$index][0];
                                $topic_id = $is_anonymous_list[$index][1];
                                
                                if($forum_rows[$index]['forum_last_is_anonymous'])
                                {
                                        $poster_index = $this->helper->get_poster_index($topic_id, $forum_rows[$index]['forum_last_poster_id']);
                                        $forum_rows[$index]['forum_last_poster_name'] = $this->language->lang('ANP_DEFAULT') . ' ' . $poster_index;
                                        $forum_rows[$index]['forum_last_poster_colour'] = $forum_rows[$index]['forum_last_poster_id'] = NULL;
                                }
                        }
                }
                
                $event['forum_rows'] = $forum_rows;
        }
        
        // make posts anonymous in posting.php topic review
        public function topic_review_modify_row($event)
        {
                $post_row = $event['post_row'];
                
                $is_staff = $this->helper->is_staff();
                
                if(!$is_staff && $event['row']['is_anonymous'])
                {
                        $poster_index = $this->helper->get_poster_index($event['topic_id'], $event['poster_id']);
                        $post_row['POST_AUTHOR_FULL'] = $post_row['POST_AUTHOR'] = $post_row['POSTER_QUOTE'] = $this->language->lang('ANP_DEFAULT') . ' ' . $poster_index;
                        $post_row['POST_AUTHOR_COLOUR'] = $post_row['U_POST_AUTHOR'] = 
                        $post_row['S_FRIEND'] = $post_row['USER_ID'] = NULL;
                }
                
                $post_row['IS_STAFF'] = $is_staff;
                $post_row['IS_ANONYMOUS'] = $event['row']['is_anonymous'];
                
                $event['post_row'] = $post_row;
        }
        
        // add array of data for the topicrow in searches for if first/last post are anonymous
        public function search_modify_rowset($event)
        {
                $rowset = $event['rowset'];
                
                $post_list = array_merge(array_column($rowset, 'topic_first_post_id'), array_column($rowset, 'topic_last_post_id'));
                $is_anonymous_list = $this->helper->is_anonymous($post_list, 't');
                
                foreach($rowset as $index => $value)
                {
                        // fix last post null issue if topic has no replies
                        if($is_anonymous_list[$index][1] == NULL) $is_anonymous_list[$index][1] = $is_anonymous_list[$index][0];
                        
                        $rowset[$index]['topic_first_is_anonymous'] = $is_anonymous_list[$index][0];
                        $rowset[$index]['topic_last_is_anonymous'] = $is_anonymous_list[$index][1];
                }
                
                $event['rowset'] = $rowset;
        }
        
        // edit data to change postrow username
        public function search_modify_post_row($event)
        {
                $row = $event['row'];
                
                $is_staff = $this->helper->is_staff();
                
                if(!$is_staff && $event['row']['is_anonymous'])
                {
                        $poster_index = $this->helper->get_poster_index($row['topic_id'], $row['poster_id']);
                        $row['username'] = $row['username_clean'] = $this->language->lang('ANP_DEFAULT') . ' ' . $poster_index;
                        // 0 turns the post into a guest which achieves no url link, but haven't gotten around guest renaming, so negative works for now
                        $row['poster_id'] = -1;
                        $row['user_colour'] = $row['user_id'] = $row['user_sig'] =
                        $row['enable_sig'] = NULL;
                }
                
                $row['IS_STAFF'] = $is_staff;
                $row['IS_ANONYMOUS'] = $event['row']['is_anonymous'];
                
                $event['row'] = $row;
        }
        
        // modify each search topicrow as done in the forumrow
        public function search_modify_tpl_ary($event)
        {
                $tpl_ary = $event['tpl_ary'];
                $row = $event['row'];
                
                $is_staff = $this->helper->is_staff();
                $anonymous = $this->language->lang('ANP_DEFAULT');
                
                if(!$is_staff)
                {
                        if($row['topic_first_is_anonymous'])
                        {
                                $tpl_ary['TOPIC_AUTHOR'] = $tpl_ary['TOPIC_AUTHOR_FULL'] = $anonymous . ' 1';
                                $tpl_ary['TOPIC_AUTHOR_COLOUR'] = NULL;
                        }

                        if($row['topic_last_is_anonymous'])
                        {
                                $poster_index = $this->helper->get_poster_index($row['topic_id'], $row['topic_last_poster_id']);
                                $tpl_ary['LAST_POST_AUTHOR'] = $tpl_ary['LAST_POST_AUTHOR_FULL'] = $anonymous . ' ' . $poster_index;
                                $tpl_ary['LAST_POST_AUTHOR_COLOUR'] = NULL;
                        }
                }
                
                $event['tpl_ary'] = $tpl_ary;
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
                
                $is_anonymous = isset($data['is_anonymous']) ? $data['is_anonymous'] : 0;
                // fix poster id getting deleted from sql data
                if(($sql_data[POSTS_TABLE]['sql']['poster_id'] == 0) && ($event['post_mode'] == "edit" || $event['post_mode'] == "edit_topic"))
                        $sql_data[POSTS_TABLE]['sql']['poster_id'] = $this->helper->get_poster_id($data['post_id']);
                
                $sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
                        'is_anonymous'  => $is_anonymous,
                ));
                
                $event['sql_data'] = $sql_data;
        }
        
        // edit notifications data to account for anonymous submissions
        // uses custom event made in functions_posting.php lines 2285 - 2296
        public function modify_submit_notification_data($event)
        {
            $notification_data = $event['notification_data'];
            $data_ary = $event['data_ary'];
            $mode = $event['mode'];
            
            if($data_ary['is_anonymous'] && ($mode == 'post' || $mode == 'reply' || $mode == 'quote'))
            {
                    $notification_data['poster_id'] = ANONYMOUS;
                    $notification_data['post_username'] = "Anonymous " . $this->helper->get_poster_index($data_ary['topic_id'], $event['poster_id']);
            }
            
            $event['notification_data'] = $notification_data;
        }
}
