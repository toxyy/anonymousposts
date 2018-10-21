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

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var \toxyy\anonymousposts\core\helper */
	protected $helper;

	/**
	* Constructor
	*
	* @param \phpbb\template\template                       $template
	* @param \phpbb\auth\auth                               $auth
	* @param \phpbb\request\request		 		$request
	* @param phpbb\notification\manager		 	$notification_manager
	* @param \toxyy\anonymousposts\core\helper		$helper
	*
	*/
	public function __construct(
                \phpbb\language\language $language,
                \phpbb\template\template $template,
		\phpbb\auth\auth $auth,
		\phpbb\request\request $request,
		\phpbb\notification\manager $notification_manager,
                $helper
	)
	{
                $this->language                                 = $language;
                $this->template                                 = $template;
		$this->auth                                     = $auth;
		$this->request                                  = $request;
		$this->notification_manager                     = $notification_manager;
		$this->helper                                   = $helper;
	}

	static public function getSubscribedEvents()
	{
		return [
			'core.user_setup'                                   => 'core_user_setup',
			'core.permissions'                                  => 'core_permissions',
                        'core.modify_posting_auth'                          => 'modify_posting_auth',
                        'core.posting_modify_template_vars'                 => 'posting_modify_template_vars',
                        'core.posting_modify_post_data'                     => 'posting_modify_post_data',
                        'core.viewtopic_assign_template_vars_before'        => 'viewtopic_assign_template_vars_before',
                        'core.viewtopic_post_rowset_data'                   => 'viewtopic_post_rowset_data',
                        'core.viewtopic_modify_post_row'                    => 'viewtopic_modify_post_row',
                        'core.viewforum_modify_topics_data'                 => 'viewforum_modify_topics_data',
                        'core.viewforum_modify_topicrow'                    => 'viewforum_modify_topicrow',
                        'core.display_forums_before'                        => 'display_forums_before',
                        'core.topic_review_modify_post_list'                => 'topic_review_modify_post_list',
                        'core.topic_review_modify_row'                      => 'topic_review_modify_row',
                        'core.search_mysql_by_author_modify_search_key'     => 'search_mysql_by_author_modify_search_key',
                        'core.search_native_by_author_modify_search_key'    => 'search_native_by_author_modify_search_key',
                        'core.search_postgres_by_author_modify_search_key'  => 'search_postgres_by_author_modify_search_key',
                        'core.search_modify_rowset'                         => 'search_modify_rowset',
                        'core.search_modify_post_row'                       => 'search_modify_post_row',
                        'core.search_modify_tpl_ary'                        => 'search_modify_tpl_ary',
			'core.modify_submit_post_data'                      => 'modify_submit_post_data',
                        'core.submit_post_modify_sql_data'                  => 'submit_post_modify_sql_data',
                        'core.modify_submit_notification_data'              => 'modify_submit_notification_data',
                        'core.notification_manager_add_notifications'       => 'notification_manager_add_notifications',
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

        // fixes the posting page from treating edits to anonymous posts like guest edits
        public function posting_modify_template_vars($event)
        {
                $page_data = $event['page_data'];
                $post_data = $event['post_data'];
                // this is the same as in posting.php on line ~1798, with one variable added
                $page_data['S_DISPLAY_USERNAME'] = (!$this->helper->is_registered() ||
                                                            ($event['mode'] == 'edit' &&
                                                                    (!$post_data['is_anonymous'] &&
                                                                            ($post_data['poster_id'] == ANONYMOUS)
                                                                    )
                                                            )
                                                    ) ? 1 : 0;
                $event['page_data'] = $page_data;
        }

        // change poster information in quotes
        public function posting_modify_post_data($event)
        {
                $post_data = $event['post_data'];

                // keep checkbox checked only if editing a post, otherwise it is unchecked by default
                $this->template->assign_vars(array(
                        'POST_IS_ANONYMOUS' => (($event['mode'] == 'edit') && $post_data['is_anonymous']) ? 'checked' : '',
                ));

                if($post_data['is_anonymous'])
                {
                        $post_data['quote_username'] = $this->language->lang('ANP_DEFAULT') . ' ' . $post_data['anonymous_index'];
                        $post_data['poster_id'] = ANONYMOUS;
                }

                $event['post_data'] = $post_data;
        }

        // add F_ANONPOST variable to viewtopic.php
        public function viewtopic_assign_template_vars_before($event)
        {
                $topic_data = $event['topic_data'];

                // only want to run this once per page, it's an expensive query...
                $topic_data = array_merge($topic_data, array(
                        'is_staff' => $this->helper->is_staff(),
                        'anonymous_name' => $this->language->lang('ANP_DEFAULT'),
                ));

                $this->template->assign_vars(array(
                        'F_ANONPOST'   => $this->auth->acl_get('f_anonpost', $event['forum_id']),
                ));

                $event['topic_data'] = $topic_data;
        }

        // get is_anonymous in postrow sql query
        public function viewtopic_post_rowset_data($event)
        {
                $rowset = $event['rowset_data'];

                $rowset = array_merge($rowset, array(
                        'is_anonymous' => $event['row']['is_anonymous'],
                        'anonymous_index' => $event['row']['anonymous_index'],
                ));

                if($event['row']['is_anonymous'])
                {
                        if($event['row']['foe'])
                                $rowset['hide_post'] = false;

                        $rowset['friend'] = $rowset['foe'] = NULL;
                }

                $event['rowset_data'] = $rowset;
        }

        // delete info in anonymous posts for normal members
        public function viewtopic_modify_post_row($event)
        {
                $post_row = $event['post_row'];
                $is_anonymous = $event['row']['is_anonymous'];

                // delete info from the deleted post hidden div so sneaky members cant find out who it was
                // i did this the opposite way first, then reversed it into this shorter list... nothing should be missing
                if(!$event['topic_data']['is_staff'] && $is_anonymous)
                {
                        $topic_data = $event['topic_data'];
                        $post_id = $event['row']['post_id'];
                        $topic_id = $topic_data['topic_id'];
                        $forum_id = $event['row']['forum_id'];
                        $is_staff = $topic_data['is_staff'];
                        $is_anonymous = $event['row']['is_anonymous'];
                        $anonymous_name = $topic_data['anonymous_name'] . ' ' . $event['row']['anonymous_index'];

                        $post_row['POST_AUTHOR_FULL'] = $post_row['POST_AUTHOR'] =
                                $post_row['CONTACT_USER'] = $anonymous_name;

                        $post_row['S_CUSTOM_FIELDS'] = $post_row['S_FRIEND'] = $post_row['POSTER_ID'] =
                                $post_row['U_JABBER'] = $post_row['U_EMAIL'] = $post_row['U_PM'] =
                                $post_row['U_SEARCH'] = $post_row['S_ONLINE'] = $post_row['ONLINE_IMG'] =
                                $post_row['SIGNATURE'] = $post_row['POSTER_AGE'] = $post_row['POSTER_WARNINGS'] =
                                $post_row['POSTER_AVATAR'] = $post_row['POSTER_POSTS'] = $post_row['POSTER_JOINED'] =
                                $post_row['RANK_IMG_SRC'] = $post_row['RANK_IMG'] = $post_row['RANK_TITLE'] =
                                // next 3 add support for the Normal and Special Ranks extension
                                $post_row['EXTRA_RANK_IMG_SRC'] = $post_row['EXTRA_RANK_IMG'] = $post_row['EXTRA_RANK_TITLE'] =
                                $post_row['U_POST_AUTHOR'] = $event['user_poster_data'] = $event['cp_row'] = NULL;
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
                $is_anonymous_list = $this->helper->is_anonymous($post_list);

                // calling this once saves ram later on
                $is_staff = $this->helper->is_staff();
                $anonymous = $this->language->lang('ANP_DEFAULT');

                foreach($rowset as $index => $value)
                {
                        // fix last post null issue if topic has no replies
                        if($is_anonymous_list[$index][1] == NULL) $is_anonymous_list[$index][1] = $is_anonymous_list[$index][0];

                        $rowset[$index]['topic_first_is_anonymous'] = $is_anonymous_list[$index][0];
                        $rowset[$index]['topic_last_is_anonymous'] = (($is_anonymous_list[$index][1] == NULL) ? 0 : $is_anonymous_list[$index][1]);
                        $rowset[$index]['topic_first_anonymous_name'] = $anonymous . ' ' . 1;
                        $rowset[$index]['topic_last_anonymous_name'] = $anonymous . ' ' . $is_anonymous_list[$index]['last_index'];
                        $rowset[$index]['is_staff'] = $is_staff;
                }

                $event['rowset'] = $rowset;
        }

        // update first and last post in topicrow if they are anonymous
        public function viewforum_modify_topicrow($event)
        {
                $row = $event['row'];
                $topic_row = $event['topic_row'];

                if(!$row['is_staff'])
                {
                        if($row['topic_first_is_anonymous'])
                        {
                                $topic_row['TOPIC_AUTHOR'] = $topic_row['TOPIC_AUTHOR_FULL'] = $row['topic_first_anonymous_name'];
                                $topic_row['TOPIC_AUTHOR_COLOUR'] = NULL;
                        }

                        if($row['topic_last_is_anonymous'])
                        {
                                $topic_row['LAST_POST_AUTHOR'] = $topic_row['LAST_POST_AUTHOR_FULL'] = $row['topic_last_anonymous_name'];
                                $topic_row['LAST_POST_AUTHOR_COLOUR'] = NULL;
                        }
                }

                $event['topic_row'] = $topic_row;
        }

        // handles changing the last poster name for forumrow
        public function display_forums_before($event)
        {
                $forum_rows = $event['forum_rows'];

                // this event runs once, so having the function here is fine
                if(!$this->helper->is_staff())
                {
                        $post_list = array_merge(array_column($forum_rows, 'forum_last_post_id'));
                        $is_anonymous_list = $this->helper->is_anonymous($post_list, 'f');
                        $anonymous = $this->language->lang('ANP_DEFAULT');

                        foreach($forum_rows as $index => $value)
                        {
                                $post_list_index = array_search($forum_rows[$index]['forum_last_post_id'], $post_list);

                                if($post_list_index > 0)
                                {
                                        $forum_rows[$index]['forum_last_is_anonymous'] = $is_anonymous_list[$index][0];

                                        if($forum_rows[$index]['forum_last_is_anonymous'])
                                        {       // is_anonymous_list[][1] is the topic_id
                                                $forum_rows[$index]['forum_last_poster_name'] = $anonymous . ' ' . $is_anonymous_list[$index]['last_index'];
                                                $forum_rows[$index]['forum_last_poster_colour'] = $forum_rows[$index]['forum_last_poster_id'] = NULL;
                                        }
                                }
                        }
                }

                $event['forum_rows'] = $forum_rows;
        }

        public function topic_review_modify_post_list($event)
        {
                $rowset = $event['rowset'];

                $anonymous = $this->language->lang('ANP_DEFAULT');
                $is_staff = $this->helper->is_staff();

                foreach($rowset as $index => $value)
                {
                        $rowset[$index]['is_staff'] = $is_staff;
                        $rowset[$index]['anonymous_name'] = $anonymous . ' ' . $rowset[$index]['anonymous_index'];
                }

                $event['rowset'] = $rowset;
        }

        // make posts anonymous in posting.php topic review
        public function topic_review_modify_row($event)
        {
                $post_row = $event['post_row'];

                $is_staff = $event['row']['is_staff'];

                if(!$is_staff && $event['row']['is_anonymous'])
                {
                        $post_row['POST_AUTHOR_FULL'] = $post_row['POST_AUTHOR'] = $post_row['POSTER_QUOTE'] = $event['row']['anonymous_name'];
                        $post_row['POST_AUTHOR_COLOUR'] = $post_row['U_POST_AUTHOR'] =
                        $post_row['S_FRIEND'] = $post_row['USER_ID'] = NULL;
                }

                $post_row['IS_STAFF'] = $is_staff;
                $post_row['IS_ANONYMOUS'] = $event['row']['is_anonymous'];

                $event['post_row'] = $post_row;
        }

        public function search_mysql_by_author_modify_search_key($event)
        {

        }

        // when searching by author, don't show anonymous posts to people who arent the OP of it or staff.  clear cache to see results if updating
        public function search_native_by_author_modify_search_key($event)
        {
                $search_key_array = $event['search_key_array'];
                $post_visibility = $event['post_visibility'];

                $results = array();
                $results = $this->helper->remove_anonymous_from_author_posts($search_key_array, $post_visibility);

                $event['search_key_array'] = $results['search_key_array'];
                $event['post_visibility'] = $results['post_visibility'];
        }

        public function search_postgres_by_author_modify_search_key($event)
        {

        }

        // add array of data for the topicrow in searches for if first/last post are anonymous
        // runs once per page
        public function search_modify_rowset($event)
        {
                $rowset = $event['rowset'];
                $is_staff = $this->helper->is_staff();

                $anonymous = $this->language->lang('ANP_DEFAULT');

                if($event['show_results'] == 'topics')
                {

                        $post_list = array_merge(array_column($rowset, 'topic_first_post_id'), array_column($rowset, 'topic_last_post_id'));
                        $is_anonymous_list = $this->helper->is_anonymous($post_list);

                        foreach($rowset as $index => $value)
                        {
                                // fix last post null issue if topic has no replies
                                if($is_anonymous_list[$index][1] == NULL) $is_anonymous_list[$index][1] = $is_anonymous_list[$index][0];

                                $rowset[$index]['topic_first_is_anonymous'] = $is_anonymous_list[$index][0];
                                $rowset[$index]['topic_last_is_anonymous'] = $is_anonymous_list[$index][1];
                                $rowset[$index]['topic_first_anonymous_name'] = $anonymous . ' ' . 1;
                                $rowset[$index]['is_staff'] = $is_staff;

                                if($is_anonymous_list[$index][1])
                                        $rowset[$index]['topic_last_anonymous_name'] = $anonymous . ' ' . $is_anonymous_list[$index]['last_index'];
                        }
                }

                if($event['show_results'] == 'posts')
                {
                        foreach($rowset as $index => $value)
                        {
                                $rowset[$index]['is_staff'] = $is_staff;
                                $rowset[$index]['anonymous_name'] = $anonymous . ' ' . $rowset[$index]['anonymous_anonymous_index'];
                        }
                }

                $event['rowset'] = $rowset;
        }

        // edit data to change postrow username
        // well.... doesn't seem to do anything, so i'm removing this for now
        public function search_modify_post_row($event)
        {
                /*$row = $event['row'];
                $is_staff = $this->helper->is_staff();

                if(!$is_staff && $event['row']['is_anonymous'])
                {
                        $row['username'] = $row['username_clean'] = $this->language->lang('ANP_DEFAULT');
                        // 0 turns the post into a guest which achieves no url link, but haven't gotten around guest renaming, so negative works for now
                        $row['poster_id'] = ANONYMOUS;
                        $row['user_colour'] = $row['user_id'] = $row['user_sig'] =
                        $row['enable_sig'] = NULL;
                }

                $row['IS_STAFF'] = $is_staff;
                $row['IS_ANONYMOUS'] = $event['row']['is_anonymous'];

                $event['row'] = $row;*/
        }

        // modify each search topicrow as done in the forumrow, modify username link in postrow
        public function search_modify_tpl_ary($event)
        {
                $tpl_ary = $event['tpl_ary'];
                $row = $event['row'];

                if(!$row['is_staff'])
                {
                        if($event['show_results'] == 'topics')
                        {
                                if($row['topic_first_is_anonymous'])
                                {
                                        $tpl_ary['TOPIC_AUTHOR'] = $tpl_ary['TOPIC_AUTHOR_FULL'] = $row['topic_first_anonymous_name'];
                                        $tpl_ary['TOPIC_AUTHOR_COLOUR'] = NULL;
                                }

                                if($row['topic_last_is_anonymous'])
                                {
                                        $tpl_ary['LAST_POST_AUTHOR'] = $tpl_ary['LAST_POST_AUTHOR_FULL'] = $row['topic_last_anonymous_name'];
                                        $tpl_ary['LAST_POST_AUTHOR_COLOUR'] = NULL;
                                }
                        }
                        else
                        {       // fix anonymous name from being clickable in search postrow
                                if($row['is_anonymous'])
                                {
                                        $tpl_ary['POST_AUTHOR_FULL'] = $tpl_ary['POST_AUTHOR'] = $row['anonymous_name'];
                                }
                        }
                }

                $event['tpl_ary'] = $tpl_ary;
        }

        // add variables to $data for use in submit_post_modify_sql_data
        public function modify_submit_post_data($event)
        {
                $data = $event['data'];

                // get checkbox value
                $anonpost = $this->request->variable('anonpost', 0, true);

                $data = array_merge($data, array(
			'is_anonymous' => isset($anonpost) ? $anonpost : 0,
                        'anonymous_index' => isset($anonpost) ? $this->helper->get_poster_index($data['topic_id'], $this->helper->this_user_id()) : 0,
                        'fixed_poster_id' => $this->helper->get_poster_id($data['post_id']),
		));

                $event['data'] = $data;
        }

        // add is_anonymous to the sql data
        public function submit_post_modify_sql_data($event)
        {
                $sql_data = $event['sql_data'];
                $data = $event['data'];
                $poster_id = $sql_data[POSTS_TABLE]['sql']['poster_id'];

                // fix poster id getting deleted from sql data
                if(($data['is_anonymous'] && ($poster_id == 0)) || (!$data['is_anonymous'] && ($poster_id == 1)))
                {
                        switch($event['post_mode'])
                        {
                                case 'edit_first_post':
                                case 'edit':
                                case 'edit_last_post':
                                case 'edit_topic':
                                        $sql_data[POSTS_TABLE]['sql']['poster_id'] = $data['fixed_poster_id'];
                                break;
                        }
                }

                $sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
                        'is_anonymous'  => isset($data['is_anonymous']) ? $data['is_anonymous'] : 0,
                        'anonymous_index'  => isset($data['anonymous_index']) ? $data['anonymous_index'] : 0,
                ));

                $event['sql_data'] = $sql_data;
                $event['data'] = $data;
        }

        // edit notifications data to account for anonymous submissions
        // uses custom event made in functions_posting.php lines 2285 - 2296 ticket/15819
        public function modify_submit_notification_data($event)
        {
            $notification_data = $event['notification_data'];
            $data_ary = $event['data_ary'];
            $mode = $event['mode'];

            if($data_ary['is_anonymous'] && ($mode == 'post' || $mode == 'reply' || $mode == 'quote'))
            {
                    $notification_data['poster_id'] = ANONYMOUS;
                    $notification_data['post_username'] = "Anonymous " . $data_ary['anonymous_index'];
            }

            $event['notification_data'] = $notification_data;
        }

        // fix notifying users who posted anonymously if people have quoted them
        public function notification_manager_add_notifications($event)
        {
                $data = $event['data'];

                // adapted from phpbb/textformatter/s9e/utils.php get_outermost_quote_authors function
                if($event['notification_type_name'] == 'notification.type.quote')
                {
                        $xml = $data['message'];

                        if(!(strpos($xml, '<QUOTE ') === false))
                        {
                                $quote_data = array();
                                $dom = new \DOMDocument;
                                $dom->loadXML($xml);
                                $xpath = new \DOMXPath($dom);

                                // stores all outermost quote attributes in an array[x][y], where x is the outermost quote index
                                $count = 0;
                                foreach($xpath->query('//QUOTE[not(ancestor::QUOTE)]/@*') as $element)
                                {
                                        $count += ($element->nodeName == 'author') ? ($count == 0 ? 0.6 : 1) : 0;
                                        $quote_data[floor($count)][$element->nodeName] = $element->nodeValue;
                                }

                                // get second xpath query to preserve index order to match is_anonymous_list
                                // if their sizes dont match, for some reason a quote doesn't have an author... would there even be a notification?
                                $quote_authors = $xpath->query('//QUOTE[not(ancestor::QUOTE)]/@author');
                                $is_anonymous_list = $this->helper->is_anonymous(array_column($quote_data, 'post_id'), 'n');

                                foreach($quote_data as $index => $index)
                                        if(boolval($is_anonymous_list[$index][0]))
                                                $quote_authors->item($index)->nodeValue = $is_anonymous_list[$index][1];

                                // save the usernames back into the post_text we just got
                                $data['post_text'] = $dom->saveXML();
                        }
                }

                $event['notify_users'] = $this->notification_manager->get_item_type_class($event['notification_type_name'])->find_users_for_notification($data, $event['options']);
        }
}
