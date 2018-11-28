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
        * @param \phpbb\language\language                                   $language
        * @param \phpbb\template\template                                   $template
        * @param \phpbb\auth\auth                                           $auth
        * @param \phpbb\request\request                                     $request
        * @param \phpbb\notification\manager                                $notification_manager
        * @param \toxyy\anonymousposts\core\helper                          $helper
        * @param \toxyy\anonymousposts\core\helper->is_staff()              $is_staff
        * @param \toxyy\anonymousposts\language->lang('ANP_DEFAULT')        $anonymous
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
                $this->language                                             = $language;
                $this->template                                             = $template;
                $this->auth                                                 = $auth;
                $this->request                                              = $request;
                $this->notification_manager                                 = $notification_manager;
                $this->helper                                               = $helper;
                $this->is_staff                                             = $is_staff;
                $this->anonymous                                            = $anonymous;
        }

        static public function getSubscribedEvents()
        {
                return [
                        'core.user_setup'                                   => 'core_user_setup',
                        'core.user_setup_after'                             => 'user_setup_after',
                        'core.permissions'                                  => 'core_permissions',
                        'core.viewtopic_assign_template_vars_before'        => 'viewtopic_assign_template_vars_before',
                        'core.mcp_global_f_read_auth_after'                 => 'mcp_global_f_read_auth_after',
                        'core.modify_posting_auth'                          => 'modify_posting_auth',
                        'core.viewtopic_post_rowset_data'                   => 'viewtopic_post_rowset_data',
                        'core.viewtopic_modify_post_row'                    => 'viewtopic_modify_post_row',
                        'core.viewforum_modify_topicrow'                    => 'viewforum_modify_topicrow',
                        'paybas.recenttopics.modify_tpl_ary'                => 'recenttopics_modify_tpl_ary',
                        'core.display_forums_before'                        => 'display_forums_before',
                        'core.topic_review_modify_row'                      => 'topic_review_modify_row',
                        'core.display_user_activity_modify_sql'             => 'display_user_activity_modify_sql',
                        'core.search_modify_tpl_ary'                        => 'search_modify_tpl_ary',
                        'core.search_mysql_by_author_modify_search_key'     => 'search_mysql_by_author_modify_search_key',
                        'core.search_native_by_author_modify_search_key'    => 'search_native_by_author_modify_search_key',
                        'core.search_postgres_by_author_modify_search_key'  => 'search_postgres_by_author_modify_search_key',
                        'core.posting_modify_post_data'                     => 'posting_modify_post_data',
                        'core.posting_modify_template_vars'                 => 'posting_modify_template_vars',
                        'core.posting_modify_submit_post_before'            => 'posting_modify_submit_post_before',
                        'core.posting_modify_default_variables'             => 'posting_modify_default_variables',
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
                $permissions['f_anonpost'] = array('lang' => 'ANP_PERMISSIONS', 'cat' => 'post');
                $permissions['f_edit_anonpost'] = array('lang' => 'ANP_EDIT_PERMISSIONS', 'cat' => 'post');
                $permissions['u_anonpost'] = array('lang' => 'ANP_PERMISSIONS', 'cat' => 'post');
                $permissions['u_edit_anonpost'] = array('lang' => 'ANP_EDIT_PERMISSIONS', 'cat' => 'post');
                $event['permissions'] = $permissions;
        }

        // add F_ANONPOST variable to viewtopic.php
        public function viewtopic_assign_template_vars_before($event)
        {
                $this->template->assign_vars(array(
                        'F_ANONPOST'    => $this->auth->acl_get('f_anonpost', $event['forum_id']),
                        'U_ANONPOST'    => $this->auth->acl_get('u_anonpost'),
                ));
        }

        // add F_ANONPOST variable to mcp.php
        public function mcp_global_f_read_auth_after($event)
        {
                $this->template->assign_vars(array(
                        'F_ANONPOST'    => $this->auth->acl_get('f_anonpost', $event['forum_id']),
                        'U_ANONPOST'    => $this->auth->acl_get('u_anonpost'),
                ));
        }

        // add F_ANONPOST variable to posting.php
        public function modify_posting_auth($event)
        {
                $this->template->assign_vars(array(
                        'F_ANONPOST'    => $this->auth->acl_get('f_anonpost', $event['forum_id']),
                        'U_ANONPOST'    => $this->auth->acl_get('u_anonpost'),
                ));
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
                        if($event['row']['foe'] == '1')
                        {
                                $rowset['foe'] = '0';
                                $rowset['hide_post'] = false;
                        }

                        $event['row']['friend'] = ($event['row']['friend'] != '1') ?: '0';
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
                $this->row_handler($is_anonymous, $post_row, 'posts_viewtopic');

                // unique to this event
                if($is_anonymous) $event['cp_row'] = NULL;

                $event['post_row'] = $post_row;
        }

        // update first and last post in topicrow if they are anonymous
        public function viewforum_modify_topicrow($event)
        {
                $topic_row = $event['topic_row'];
                $this->row_handler($event['row'], $topic_row);
                $event['topic_row'] = $topic_row;
        }

        // modify recenttopicrow
        public function recenttopics_modify_tpl_ary($event)
        {
                $tpl_ary = $event['tpl_ary'];
                $this->row_handler($event['row'], $tpl_ary);
                $event['tpl_ary'] = $tpl_ary;
        }

        // handles changing the last poster name for forumrow
        public function display_forums_before($event)
        {
                $forum_rows = $event['forum_rows'];
                $this->rowset_handler($forum_rows);
                $event['forum_rows'] = $forum_rows;
        }

        // make posts anonymous in posting.php topic review
        public function topic_review_modify_row($event)
        {
                $post_row = $event['post_row'];
                $post_row['anonymous_index'] = $event['row']['anonymous_index'];

                $this->row_handler($event['row']['is_anonymous'], $post_row, 'posts_topicreview');

                $event['post_row'] = $post_row;
        }

        // recalculate user's most active forum and topic count to remove anonymous posts, if it isn't their profile
        public function display_user_activity_modify_sql($event)
        {
                $forum_sql_ary = $event['forum_sql_ary'];
                $topic_sql_ary = $event['topic_sql_ary'];
                $no_anonymous_posts = 'AND is_anonymous <> 1';
                // get poster_id from the sql array... it is hard coded in the first spot of the where
                $poster_id = preg_replace('/\D+/', '', $forum_sql_ary['WHERE'][0]);

                if(($poster_id !== $this->helper->get_user_id()) && !$this->helper->is_staff())
                {
                        array_push($forum_sql_ary['WHERE'], $no_anonymous_posts);
                        array_push($topic_sql_ary['WHERE'], $no_anonymous_posts);
                }

                $event['forum_sql_ary'] = $forum_sql_ary;
                $event['topic_sql_ary'] = $topic_sql_ary;
        }

        // modify each search topicrow as done in the forumrow, modify username link in postrow
        public function search_modify_tpl_ary($event)
        {
                $tpl_ary = $event['tpl_ary'];

                switch($event['show_results'])
                {
                case 'topics':
                        $this->row_handler($event['row'], $tpl_ary);
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
                $post_visibility = $event['post_visibility'];
                $this->helper->remove_anonymous_from_author_posts($post_visibility, $this->is_staff);
                $event['post_visibility'] = $post_visibility;
        }

        // when searching by author, don't show anonymous posts to people who arent the OP of it or staff.  clear cache to see results if updating
        public function search_native_by_author_modify_search_key($event)
        {
                $post_visibility = $event['post_visibility'];
                $this->helper->remove_anonymous_from_author_posts($post_visibility, $this->is_staff);
                $event['post_visibility'] = $post_visibility;
        }

        // untested
        public function search_postgres_by_author_modify_search_key($event)
        {
                $post_visibility = $event['post_visibility'];
                $this->helper->remove_anonymous_from_author_posts($post_visibility, $this->is_staff);
                $event['post_visibility'] = $post_visibility;
        }

        // change poster information in quotes, modify post_data for posting_modify_submit_post_before
        public function posting_modify_post_data($event)
        {
                $post_data = $event['post_data'];
                $post_data['is_checked'] = $this->request->variable('anonpost', 0);

                // keep checkbox checked only if editing a post, otherwise it is unchecked by default
                if($event['mode'] == 'edit')
                {
                        $checkbox_attributes = '';

                        // don't allow non staff (by default at least) to edit anon status, makes webmasters happy
                        if(!($this->auth->acl_get('f_edit_anonpost', $event['forum_id']) && $this->auth->acl_get('u_edit_anonpost')))
                        {
                                $post_data['is_checked'] = $post_data['is_anonymous'];
                                $checkbox_attributes = 'disabled';
                        }

                        if($post_data['is_anonymous'])
                        {
                                $checkbox_attributes = 'checked ' . $checkbox_attributes;
                        }

                        $this->template->assign_vars(array(
                                'POST_IS_ANONYMOUS' => $checkbox_attributes,
                        ));
                }

                if($post_data['is_anonymous'])
                {
                        $post_data['poster_id_backup'] = $post_data['poster_id'];
                        $post_data['quote_username'] = $this->anonymous . ' ' . $post_data['anonymous_index'];
                        $post_data['poster_id'] = ANONYMOUS;
                }

                $event['post_data'] = $post_data;
        }

        // removes the username field from posting.php if editing an anonymous post
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

        // add variables to $data for use in submit_post_modify_sql_data
        public function posting_modify_submit_post_before($event)
        {
                $data = $event['data'];
                $username = $event['post_author_name'];

                // get checkbox value
                $anonpost = $event['post_data']['is_checked'];

                $data['is_anonymous'] = $anonpost;
                $data['was_anonymous'] = $event['post_data']['is_anonymous'];

                // need to use these 2 in the lambda
                $data['anonymous_index'] = $event['post_data']['anonymous_index'];
                $post_mode = $event['mode'];

                // fixed to return 1 for new topics, and mean it this time... wouldn't work sometimes for some weird reason
                $get_anon_index = function() use($data, $post_mode)
                {
                        if($data['is_anonymous'])
                        {
                                if($data['was_anonymous'] || ($data['anonymous_index'] > 0)) return $data['anonymous_index'];
                                else return $this->helper->get_poster_index($data['topic_id'], $data['poster_id']);
                        }

                        // 0 is default case... is default case really necessary?
                        return (int) ($post_mode == 'post');
                };

                $data['anonymous_index'] = $get_anon_index();
                $data['forum_last_post_id'] = $event['post_dat6fvga']['forum_last_post_id'];

                // these two are for checking if when posting/replying not anonymously and there are indeces to update
                if($event['post_data']['topic_last_anonymous_index'] > 0) $data['topic_last_anonymous_index'] = $event['post_data']['topic_last_anonymous_index'];
                if($event['post_data']['forum_anonymous_index'] > 0) $data['forum_anonymous_index'] = $event['post_data']['forum_anonymous_index'];

                // data for unsetting anonymous post
                if($data['was_anonymous'])
                {
                        $data['fixed_poster_id'] = $event['post_data']['poster_id_backup'];
                        $data['username_backup'] = $this->helper->get_username($data['fixed_poster_id']);
                        if(!$anonpost) $username = $event['post_data']['topic_last_poster_name'];
                }

                $event['data'] = $data;
                $event['post_author_name'] = $username;
        }

        // fix access denied error for attachments in anonymous posts
        public function posting_modify_default_variables($event)
        {
                $post_data = $event['post_data'];

                if($post_data['is_anonymous'])
                {
                        unset($post_data['poster_id']);
                        $event['post_data'] = $post_data;
                }
        }

        // handle all postmodes and add appropriate data to the database
        public function submit_post_modify_sql_data($event)
        {
                $sql_data = $event['sql_data'];
                $data = $event['data'];
                $post_mode = $event['post_mode'];
                $post_visibility = $data['post_visibility'];
                $is_anonymous = $data['is_anonymous'];
                $was_anonymous = $data['was_anonymous'];

                // universal
                $sql_data[POSTS_TABLE]['sql']['is_anonymous'] = $is_anonymous;

                $topic_is_empty = false;
                switch([$post_mode, $is_anonymous, $was_anonymous])
                {
                /*
                * NORMAL POSTS (false, false)
                * zeros out the current forum and topic (if this is not an op) anonymous index
                */
                case ['reply', false, false]:
                case ['quote', false, false]:
                        if(isset($data['topic_last_anonymous_index']))
                                $sql_data[TOPICS_TABLE]['stat']['topic_last_anonymous_index'] = 'topic_last_anonymous_index = ' . 0;
                case ['post', false, false]:
                        if(isset($data['forum_anonymous_index']))
                                $sql_data[FORUMS_TABLE]['stat']['forum_anonymous_index'] = 'forum_anonymous_index = ' . 0;

                        break;
                /*
                * TOGGLE ON/CREATE ANONYMOUS POST (true, false)
                * if posting, replying, or quoting, creates anonymous post
                * if editing a post to be anonymous, adds data to the posts/topics/forums tables depending on the edit mode
                */
                case ['post', true, false]:
                        $posting = true;
                case ['edit_topic', true, false]:
                        $topic_is_empty = true;
                case ['edit_first_post', true, false]:
                        $sql_data[TOPICS_TABLE]['stat']['topic_first_is_anonymous'] = 'topic_first_is_anonymous = ' . true;
                case ['reply', true, false]:
                case ['quote', true, false]:
                        $modify_forum_anon_index = true;
                case ['edit_last_post', true, false]:
                        if($event['post_mode'] !== 'edit_first_post')
                        {
                                $fixed_anon_index = ($topic_is_empty ? 1 : $data['anonymous_index']);

                                $sql_data[TOPICS_TABLE]['stat']['topic_last_anonymous_index'] = 'topic_last_anonymous_index = ' . $fixed_anon_index;

                                if($modify_forum_anon_index || $data['post_id'] == $data['forum_last_post_id'])
                                        $sql_data[FORUMS_TABLE]['stat']['forum_anonymous_index'] = 'forum_anonymous_index = ' . $fixed_anon_index;
                        }
                case ['edit', true, false]:
                        $anonymous_index = ($topic_is_empty ? 1 : $data['anonymous_index']);
                        $sql_data[POSTS_TABLE]['sql']['anonymous_index'] = $anonymous_index;

                        break;
                /*
                * TOGGLE OFF ANONYMOUS POST (false, true)
                * if editing a post and anonymous is removed, handles each case and updates the db accordingly
                */
                case ['edit_topic', false, true]:
                case ['edit_first_post', false, true]:
                        $sql_data[TOPICS_TABLE]['stat']['topic_first_is_anonymous'] = 'topic_first_is_anonymous = ' . 0;
                        $sql_data[TOPICS_TABLE]['stat']['topic_first_poster_name'] = 'topic_first_poster_name = \'' . $data['username_backup'] . '\'';

                        // from includes/functions_posting.php ~ line 2060
                        // testing shows that if there are only two posts that this is edit_last_post, but the files included it so I am too
                        $first_post_has_topic_info = ($post_mode == 'edit_first_post' &&
                                (($post_visibility == ITEM_DELETED && $data['topic_posts_softdeleted'] == 1) ||
                                ($post_visibility == ITEM_UNAPPROVED && $data['topic_posts_unapproved'] == 1) ||
                                ($post_visibility == ITEM_REAPPROVE && $data['topic_posts_unapproved'] == 1) ||
                                ($post_visibility == ITEM_APPROVED && $data['topic_posts_approved'] == 1)));
                case ['edit_last_post', false, true]:
                        $first_post_has_topic_info = ($post_mode == 'edit_first_post' && $first_post_has_topic_info);

                        if($first_post_has_topic_info || $post_mode != 'edit_first_post')
                                $sql_data[TOPICS_TABLE]['stat']['topic_last_anonymous_index'] = 'topic_last_anonymous_index = ' . 0;

                        if($first_post_has_topic_info || $data['post_id'] == $data['forum_last_post_id'])
                                $sql_data[FORUMS_TABLE]['stat']['forum_anonymous_index'] = 'forum_anonymous_index = ' . 0;
                case ['edit', false, true]:
                /*
                * EDIT ANONYMOUS POST (true, true)
                * if editing an anonymous post, we need to fix the poster id so it doesnt save to guest
                */
                case ['edit_topic', true, true]:
                case ['edit_first_post', true, true]:
                        if($is_anonymous) $sql_data[TOPICS_TABLE]['stat']['topic_first_poster_name'] = 'topic_first_poster_name = \'' . $data['username_backup'] . '\'';
                case ['edit_last_post', true, true]:
                case ['edit', true, true]:
                        // posts table doesnt have this because poster id was set to 1 to make links unclickable
                        $sql_data[POSTS_TABLE]['sql']['poster_id'] = $data['fixed_poster_id'];

                        break;
                }

                $event['data'] = $data;
                $event['sql_data'] = $sql_data;
        }

        // edit notifications data to account for anonymous submissions
        // uses custom event made in functions_posting.php lines 2285 - 2296 ticket/15819
        public function modify_submit_notification_data($event)
        {
                switch([$event['mode'], $event['data_ary']['is_anonymous'] || $event['data_ary']['was_anonymous']])
                {
                case ['post', true]:
                case ['reply', true]:
                case ['quote', true]:
                case ['edit', true]:
                        $notification_data = $event['notification_data'];

                        // poster id likely already anonymous when is_anonymous is true, id rather be sure and redo it here
                        $poster_data = ($event['data_ary']['is_anonymous']) ?
                                                array(ANONYMOUS, $this->anonymous . ' ' . $event['data_ary']['anonymous_index'])
                                                : array($event['data_ary']['poster_id'], $event['data_ary']['username_backup']);

                        list($notification_data['poster_id'], $notification_data['post_username']) = $poster_data;

                        $event['notification_data'] = $notification_data;
                        break;
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
                                {       // add some value to count for the first case of author, so the next index......idk if this is a good way to do this
                                        $count += ($element->nodeName == 'author') ? ($count == 0 ? 0.6 : 1) : 0;
                                        $quote_data[floor($count)][$element->nodeName] = $element->nodeValue;
                                }

                                // get second xpath query to preserve index order to match is_anonymous_list
                                // if their sizes dont match, for some reason a quote doesn't have an author... would there even be a notification?
                                $quote_authors = $xpath->query('//QUOTE[not(ancestor::QUOTE)]/@author');
                                $is_anonymous_list = $this->helper->is_anonymous(array_column($quote_data, 'post_id'));

                                foreach($quote_data as $index => $value)
                                        if(boolval($is_anonymous_list[$index][0]))
                                                $quote_authors->item($index)->nodeValue = $is_anonymous_list[$index][1];

                                // save the usernames back into the post_text we just got
                                $data['post_text'] = $dom->saveXML();
                        }

                        // should this be in the if statement above?
                        $event['notify_users'] = $this->notification_manager->get_item_type_class($event['notification_type_name'])->find_users_for_notification($data, $event['options']);
                }
        }

        // change last poster information in forum index only, keeping it in its own function down here to place it by row handler
        private function rowset_handler(&$generic_rowset)
        {
                if(!$this->is_staff)
                {
                        foreach($generic_rowset as &$row)
                        {
                                if($row['forum_anonymous_index'] > 0)
                                {       // is_anonymous_list[][1] is the topic_id
                                        $row['forum_last_poster_name'] = $this->anonymous . ' ' . $row['forum_anonymous_index'];
                                        $row['forum_last_poster_colour'] = $row['forum_last_poster_id'] = NULL;
                                }
                        }
                }
        }

        // processes row data from posts in a topic/topics in a forum
        // for posts, removes all sensitive info. for topics, changes their first/last poster name
        private function row_handler($row, &$generic_row, $mode = 'topics')
        {
                switch([$mode, $this->is_staff])
                {
                case ['topics', false]:
                        if($row['topic_first_is_anonymous'])
                        {
                                $generic_row['TOPIC_AUTHOR'] = $generic_row['TOPIC_AUTHOR_FULL'] = $this->anonymous . ' ' . 1;
                                $generic_row['TOPIC_AUTHOR_COLOUR'] = NULL;
                        }

                        if($row['topic_last_anonymous_index'] > 0)
                        {
                                $generic_row['LAST_POST_AUTHOR'] = $generic_row['LAST_POST_AUTHOR_FULL'] = $this->anonymous . ' ' . $row['topic_last_anonymous_index'];
                                $generic_row['LAST_POST_AUTHOR_COLOUR'] = NULL;
                        }
                        break;
                case ['posts_searchrow', false]:
                case ['posts_viewtopic', false]:
                case ['posts_topicreview', false]:
                        $is_anonymous = $row;

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
                case ['posts_searchrow', true]:
                case ['posts_viewtopic', true]:
                case ['posts_topicreview', true]:
                        $is_anonymous = $row;

                        $generic_row['IS_ANONYMOUS'] = $is_anonymous;
                        $generic_row['IS_STAFF'] = $this->is_staff;
                        break;
                }
        }
}
