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

	/** @var \toxyy\anonymousposts\core\helper->is_staff() */
        protected $is_staff;

	/** @var \toxyy\anonymousposts\language->lang('ANP_DEFAULT') */
        protected $anonymous;

	/**
	* Constructor
	*
	* @param \phpbb\language\language                               $language
	* @param \phpbb\template\template                               $template
	* @param \phpbb\auth\auth                                       $auth
	* @param \phpbb\request\request                                 $request
	* @param phpbb\notification\manager                             $notification_manager
	* @param \toxyy\anonymousposts\core\helper                      $helper
	* @param \toxyy\anonymousposts\core\helper->is_staff()          $is_staff
	* @param \toxyy\anonymousposts\language->lang('ANP_DEFAULT')    $anonymous
	*
	*/
	public function __construct(
                \phpbb\language\language $language,
                \phpbb\template\template $template,
		\phpbb\auth\auth $auth,
		\phpbb\request\request $request,
		\phpbb\notification\manager $notification_manager,
                $helper,
                $is_staff = 0,
                $anonymous = ''
	)
	{
                $this->language                                 = $language;
                $this->template                                 = $template;
		$this->auth                                     = $auth;
		$this->request                                  = $request;
		$this->notification_manager                     = $notification_manager;
		$this->helper                                   = $helper;
		$this->is_staff                                 = $is_staff;
		$this->anonymous                                = $anonymous;
	}

	static public function getSubscribedEvents()
	{
		return [
			'core.user_setup'                                   => 'core_user_setup',
                        'core.user_setup_after'                             => 'user_setup_after',
			'core.permissions'                                  => 'core_permissions',
                        'core.viewtopic_assign_template_vars_before'        => 'viewtopic_assign_template_vars_before',
                        'core.viewtopic_post_rowset_data'                   => 'viewtopic_post_rowset_data',
                        'core.viewtopic_modify_post_row'                    => 'viewtopic_modify_post_row',
                        'core.viewforum_modify_topics_data'                 => 'viewforum_modify_topics_data',
                        'core.viewforum_modify_topicrow'                    => 'viewforum_modify_topicrow',
                        'paybas.recenttopics.modify_topics_list'            => 'recenttopics_modify_topics_list',
                        'paybas.recenttopics.modify_tpl_ary'                => 'recenttopics_modify_tpl_ary',
                        'core.display_forums_before'                        => 'display_forums_before',
                        'core.topic_review_modify_row'                      => 'topic_review_modify_row',
                        'core.search_modify_rowset'                         => 'search_modify_rowset',
                        'core.search_modify_tpl_ary'                        => 'search_modify_tpl_ary',
                        'core.search_mysql_by_author_modify_search_key'     => 'search_mysql_by_author_modify_search_key',
                        'core.search_native_by_author_modify_search_key'    => 'search_native_by_author_modify_search_key',
                        'core.search_postgres_by_author_modify_search_key'  => 'search_postgres_by_author_modify_search_key',
                        'core.modify_posting_auth'                          => 'modify_posting_auth',
                        'core.posting_modify_template_vars'                 => 'posting_modify_template_vars',
                        'core.posting_modify_post_data'                     => 'posting_modify_post_data',
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

        // define global variables
        public function user_setup_after($event)
        {
		$this->is_staff = $this->helper->is_staff();
		$this->anonymous = $this->language->lang('ANP_DEFAULT');
        }

        // fix permissions for acp
        public function core_permissions($event)
        {
            	$permissions = $event['permissions'];
		$permissions['u_anonpost'] = array('lang' => 'ANP_PERMISSIONS', 'cat' => 'post');
		$permissions['f_anonpost'] = array('lang' => 'ANP_PERMISSIONS', 'cat' => 'post');
		$event['permissions'] = $permissions;
        }

        // add F_ANONPOST variable to viewtopic.php
        public function viewtopic_assign_template_vars_before($event)
        {
                $topic_data = $event['topic_data'];

                $this->template->assign_vars(array(
                        'F_ANONPOST'   => $this->auth->acl_get('f_anonpost', $event['forum_id']),
                ));

                $event['topic_data'] = $topic_data;
        }

        // add data to each postrow, take care of friend/foe stuff
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

                $post_row['anonymous_index'] = $event['row']['anonymous_index'];

                // delete info from the deleted post hidden div so sneaky members cant find out who it was
                // i did this the opposite way first, then reversed it into this shorter list... nothing should be missing
                $post_row = $this->row_handler($is_anonymous, $post_row, 'posts_viewtopic');

                // unique to this event
                if($is_anonymous) $event['user_poster_data'] = $event['cp_row'] = NULL;

                $event['post_row'] = $post_row;
        }

        // get array of first and last posts, check them for anonymity
        public function viewforum_modify_topics_data($event)
        {
                $rowset = $event['rowset'];

                $rowset = $this->rowset_handler($rowset);

                $event['rowset'] = $rowset;
        }

        // update first and last post in topicrow if they are anonymous
        public function viewforum_modify_topicrow($event)
        {
                $topic_row = $event['topic_row'];

                $topic_row = $this->row_handler($event['row'], $topic_row);

                $event['topic_row'] = $topic_row;
        }

        // add support for Recent Topics extension
        public function recenttopics_modify_topics_list($event)
        {
                $rowset = $event['rowset'];

                $rowset = $this->rowset_handler($rowset);

                $event['rowset'] = $rowset;
        }

        // modify recenttopicrow
        public function recenttopics_modify_tpl_ary($event)
        {
                $tpl_ary = $event['tpl_ary'];

                $tpl_ary = $this->row_handler($event['row'], $tpl_ary);

                $event['tpl_ary'] = $tpl_ary;
        }

        // handles changing the last poster name for forumrow
        public function display_forums_before($event)
        {
                $forum_rows = $event['forum_rows'];

                $forum_rows = $this->rowset_handler($forum_rows, 'f');

                $event['forum_rows'] = $forum_rows;
        }

        // make posts anonymous in posting.php topic review
        public function topic_review_modify_row($event)
        {
                $post_row = $event['post_row'];
                $is_anonymous = (bool)$event['row']['is_anonymous'];

                $post_row['anonymous_index'] = $is_anonymous;
                $post_row = $this->row_handler($is_anonymous, $post_row, 'posts_topicreview');

                $event['post_row'] = $post_row;
        }

        // add array of data for the topicrow in searches for if first/last post are anonymous
        // runs once per page
        public function search_modify_rowset($event)
        {
                $rowset = $event['rowset'];

                if($event['show_results'] == 'topics')
                        $rowset = $this->rowset_handler($rowset);

                $event['rowset'] = $rowset;
        }

        // modify each search topicrow as done in the forumrow, modify username link in postrow
        public function search_modify_tpl_ary($event)
        {
                $tpl_ary = $event['tpl_ary'];

                switch($event['show_results'])
                {
                case 'topics':
                        $tpl_ary = $this->row_handler($event['row'], $tpl_ary);
                        break;
                default: // posts?
                        $tpl_ary['anonymous_index'] = $event['row']['anonymous_index'];
                        $this->row_handler($event['row']['is_anonymous'], $tpl_ary, 'posts_searchrow');
                        break;
                }

                $event['tpl_ary'] = $tpl_ary;
        }

        // untested
        public function search_mysql_by_author_modify_search_key($event)
        {
                $results = $this->helper->remove_anonymous_from_author_posts($event['search_key_array'], $event['post_visibility'], $this->is_staff);

                $event['search_key_array'] = $results['search_key_array'];
                $event['post_visibility'] = $results['post_visibility'];
        }

        // when searching by author, don't show anonymous posts to people who arent the OP of it or staff.  clear cache to see results if updating
        public function search_native_by_author_modify_search_key($event)
        {
                $results = $this->helper->remove_anonymous_from_author_posts($event['search_key_array'], $event['post_visibility'], $this->is_staff);

                $event['search_key_array'] = $results['search_key_array'];
                $event['post_visibility'] = $results['post_visibility'];
        }

        // untested
        public function search_postgres_by_author_modify_search_key($event)
        {
                $results = $this->helper->remove_anonymous_from_author_posts($event['search_key_array'], $event['post_visibility'], $this->is_staff);

                $event['search_key_array'] = $results['search_key_array'];
                $event['post_visibility'] = $results['post_visibility'];
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
        // which ive fixed multiple other places so idk how affective this is tbh
        public function posting_modify_template_vars($event)
        {
                $page_data = $event['page_data'];

                // this is the same as in posting.php on line ~1798, with one variable added
                $page_data['S_DISPLAY_USERNAME'] = (!$this->helper->is_registered() ||
                                                            ($event['mode'] == 'edit' &&
                                                                    (!$event['post_data']['is_anonymous'] &&
                                                                            ($event['post_data']['poster_id'] == ANONYMOUS)
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
                        $post_data['quote_username'] = $this->anonymous . ' ' . $post_data['anonymous_index'];
                        $post_data['poster_id'] = ANONYMOUS;
                }

                $event['post_data'] = $post_data;
        }

        // add variables to $data for use in submit_post_modify_sql_data
        public function modify_submit_post_data($event)
        {
                $data = $event['data'];

                // get checkbox value
                $anonpost = $this->request->variable('anonpost', 0, true);

                $data = array_merge($data, array(
			'is_anonymous' => isset($anonpost) ? $anonpost : 0,
                        'fixed_poster_id' => $this->helper->get_poster_id($data['post_id']),
                        'anonymous_index' => isset($anonpost) ? $this->helper->get_poster_index($data['topic_id']) : 0,
		));

                $event['data'] = $data;
        }

        // add is_anonymous to the sql data
        public function submit_post_modify_sql_data($event)
        {
                $sql_data = $event['sql_data'];
                $data = $event['data'];
                $poster_id = $sql_data[POSTS_TABLE]['sql']['poster_id'];
                $poster_id = (($poster_id == 0) || ($poster_id == 1)) ? (bool)$poster_id : NULL;

                $sql_data[POSTS_TABLE]['sql'] = array_merge($sql_data[POSTS_TABLE]['sql'], array(
                        'is_anonymous'  => isset($data['is_anonymous']) ? $data['is_anonymous'] : 0,
                        'anonymous_index'  => isset($data['anonymous_index']) ? $data['anonymous_index'] : 0,
                ));

                // fix poster id getting deleted from sql data
                // xor would work here, but $poster_id isnt always equal to 0 or 1...
                if(!is_null($poster_id) && ($data['is_anonymous'] || $poster_id))
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

                $event['sql_data'] = $sql_data;
        }

        // edit notifications data to account for anonymous submissions
        // uses custom event made in functions_posting.php lines 2285 - 2296 ticket/15819
        public function modify_submit_notification_data($event)
        {
                $data_ary = $event['data_ary'];
                $mode = $event['mode'];

                if($data_ary['is_anonymous'] && ($mode == 'post' || $mode == 'reply' || $mode == 'quote'))
                {
                        $notification_data = $event['notification_data'];

                        $notification_data['poster_id'] = ANONYMOUS;
                        $notification_data['post_username'] = $this->anonymous . ' ' . $data_ary['anonymous_index'];

                        $event['notification_data'] = $notification_data;
                }
        }

        // fix notifying users who posted anonymously if people have quoted them
        public function notification_manager_add_notifications($event)
        {
                // adapted from phpbb/textformatter/s9e/utils.php get_outermost_quote_authors function
                if($event['notification_type_name'] == 'notification.type.quote')
                {
                        $data = $event['data'];
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

                        $event['notify_users'] = $this->notification_manager->get_item_type_class($event['notification_type_name'])->find_users_for_notification($data, $event['options']);
                }
        }

        // add common data to (usually) topic rowset... standardization
        private function rowset_handler($generic_rowset, $mode = 't')
        {
                if(!$this->is_staff)
                {
                        $post_list = $mode == 'f' ? array_merge(array_column($generic_rowset, 'forum_last_post_id'))
                                     : array_merge(array_column($generic_rowset, 'topic_first_post_id'), array_column($generic_rowset, 'topic_last_post_id'));
                        $is_anonymous_list = $this->helper->is_anonymous($post_list, $mode);

                        foreach($generic_rowset as $index => $value)
                        {
                                if($mode == 'f')
                                {
                                        $post_list_index = array_search($generic_rowset[$index]['forum_last_post_id'], $post_list);

                                        if($post_list_index > 0)
                                        {
                                                $generic_rowset[$index]['forum_last_is_anonymous'] = $is_anonymous_list[$index][0];

                                                if($generic_rowset[$index]['forum_last_is_anonymous'])
                                                {       // is_anonymous_list[][1] is the topic_id
                                                        $generic_rowset[$index]['forum_last_poster_name'] = $this->anonymous . ' ' . $is_anonymous_list[$index]['last_index'];
                                                        $generic_rowset[$index]['forum_last_poster_colour'] = $generic_rowset[$index]['forum_last_poster_id'] = NULL;
                                                }
                                        }
                                }
                                else // mode == 't'
                                {
                                        $topic_id = $generic_rowset[$index]['topic_id'];

                                        // fix last post null issue if topic has no replies
                                        if($is_anonymous_list[$topic_id][1] == NULL) $is_anonymous_list[$topic_id][1] = $is_anonymous_list[$topic_id][0];

                                        $generic_rowset[$index]['topic_first_is_anonymous'] = $is_anonymous_list[$topic_id][0];
                                        if($generic_rowset[$index]['topic_first_is_anonymous'])
                                                $generic_rowset[$index]['topic_first_anonymous_name'] = $this->anonymous . ' ' . 1;

                                        $generic_rowset[$index]['topic_last_is_anonymous'] = (($is_anonymous_list[$topic_id][1] == NULL) ? 0 : $is_anonymous_list[$topic_id][1]);
                                        if($generic_rowset[$index]['topic_last_is_anonymous'])
                                                $generic_rowset[$index]['topic_last_anonymous_name'] = $this->anonymous . ' ' . $is_anonymous_list[$topic_id]['last_index'];
                                }
                        }
                }

                return $generic_rowset;
        }

        // we do the same thing twice, so let's standardize it
        // for posts, $row is the is_anonymous variable from the db
        private function row_handler($row, $generic_row, $mode = 'topics')
        {
                if(!$this->is_staff)
                {
                        switch($mode)
                        {
                        case 'topics':
                                if($row['topic_first_is_anonymous'] || $row['topic_last_is_anonymous'])
                                {
                                        if($row['topic_first_is_anonymous'])
                                        {
                                                $generic_row['TOPIC_AUTHOR'] = $generic_row['TOPIC_AUTHOR_FULL'] = $row['topic_first_anonymous_name'];
                                                $generic_row['TOPIC_AUTHOR_COLOUR'] = NULL;
                                        }

                                        if($row['topic_last_is_anonymous'])
                                        {
                                                $generic_row['LAST_POST_AUTHOR'] = $generic_row['LAST_POST_AUTHOR_FULL'] = $row['topic_last_anonymous_name'];
                                                $generic_row['LAST_POST_AUTHOR_COLOUR'] = NULL;
                                        }
                                }
                                break;
                        case 'posts_searchrow':
                        case 'posts_viewtopic':
                        case 'posts_topicreview':
                                $is_anonymous = $row;

                                $generic_row['IS_ANONYMOUS'] = $is_anonymous;
                                $generic_row['IS_STAFF'] = $this->is_staff;

                                if($is_anonymous)
                                {
                                        $anonymous_name = $this->anonymous . ' ' . $generic_row['anonymous_index'];
                                        $generic_row['POST_AUTHOR_FULL'] = $generic_row['POST_AUTHOR'] = $anonymous_name;
                                        $generic_row['anonymous_index'] = NULL;

                                        switch($mode)
                                        {
                                        case 'posts_viewtopic':
                                                $generic_row['CONTACT_USER'] = $anonymous_name;

                                                $generic_row['S_CUSTOM_FIELDS'] = $generic_row['S_FRIEND'] = $generic_row['POSTER_ID'] =
                                                        $generic_row['U_JABBER'] = $generic_row['U_EMAIL'] = $generic_row['U_PM'] =
                                                        $generic_row['U_SEARCH'] = $generic_row['S_ONLINE'] = $generic_row['ONLINE_IMG'] =
                                                        $generic_row['SIGNATURE'] = $generic_row['POSTER_AGE'] = $generic_row['POSTER_WARNINGS'] =
                                                        $generic_row['POSTER_AVATAR'] = $generic_row['POSTER_POSTS'] = $generic_row['POSTER_JOINED'] =
                                                        $generic_row['RANK_IMG_SRC'] = $generic_row['RANK_IMG'] = $generic_row['RANK_TITLE'] =
                                                        // next 3 add support for the Normal and Special Ranks extension
                                                        $generic_row['EXTRA_RANK_IMG_SRC'] = $generic_row['EXTRA_RANK_IMG'] = $generic_row['EXTRA_RANK_TITLE'] =
                                                        $generic_row['U_POST_AUTHOR'] = NULL;
                                                break;
                                        case 'posts_topicreview':
                                                $generic_row['POSTER_QUOTE'] = $anonymous_name;
                                                $generic_row['POST_AUTHOR_COLOUR'] = $generic_row['U_POST_AUTHOR'] =
                                                $generic_row['S_FRIEND'] = $generic_row['USER_ID'] = NULL;
                                                break;
                                        }
                                }
                                break;
                        }
                }

                return $generic_row;
        }
}
