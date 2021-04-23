<?php
/**
 *
 * @package phpBB Extension - Anonymous Posts
 * @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace toxyy\anonymousposts\event;

/**
 * Event listener
 * All other events
 */

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main implements EventSubscriberInterface
{
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \phpbb\content_visibility */
	protected $content_visibility;
	/** @var \toxyy\anonymousposts\driver\driver */
	protected $driver;
	protected $is_staff;
	protected $anonymous;

	/**
	 * Constructor
	 *
	 * @param \phpbb\language\language								$language
	 * @param \phpbb\template\template								$template
	 * @param \phpbb\user											$user
	 * @param \phpbb\auth\auth										$auth
	 * @param \phpbb\content_visibility								$content_visibility
	 * @param \toxyy\anonymousposts\driver\driver					$driver
	 *
	 */
	public function __construct(
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\phpbb\content_visibility $content_visibility,
		\toxyy\anonymousposts\driver\driver $driver
	)
	{
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
		$this->auth = $auth;
		$this->content_visibility = $content_visibility;
		$this->driver = $driver;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'										=> 'core_user_setup',
			'core.user_setup_after'									=> 'user_setup_after',
			'core.viewtopic_assign_template_vars_before'			=> 'assign_template_vars',
			'core.mcp_global_f_read_auth_after'						=> 'assign_template_vars',
			'core.modify_posting_auth'								=> 'assign_template_vars',
			'core.ucp_main_topiclist_topic_modify_template_vars'	=> 'ucp_bookmarked_and_subscribed_modify_topicrow',
			'core.mcp_topic_review_modify_row'						=> 'mcp_topic_review_modify_row',
			'core.topic_review_modify_row'							=> 'topic_review_modify_row',
			'core.viewtopic_post_rowset_data'						=> 'viewtopic_post_rowset_data',
			'core.viewtopic_modify_post_row'						=> 'viewtopic_modify_post_row',
			'core.viewtopic_post_row_after'							=> 'viewtopic_post_row_after',
			'core.viewforum_modify_topicrow'						=> 'viewforum_modify_topicrow',
			'core.display_forums_before'							=> 'display_forums_before',
			'paybas.recenttopics.modify_tpl_ary'					=> 'recenttopics_modify_tpl_ary',
			'core.search_modify_tpl_ary'							=> 'search_modify_tpl_ary',
			'core.search_mysql_by_author_modify_search_key' 		=> 'search_modify_search_key',
			'core.search_native_by_author_modify_search_key'		=> 'search_modify_search_key',
			'core.search_postgres_by_author_modify_search_key'		=> 'search_modify_search_key',
			'core.display_user_activity_modify_actives' 			=> 'display_user_activity_modify_actives',
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
	public function user_setup_after()
	{
		$this->is_staff = $this->auth->acl_gets('a_', 'm_');
		$this->anonymous = $this->language->lang('ANP_DEFAULT');
	}

	// processes row data from posts in a topic/topics in a forum
	// for posts, removes all sensitive info. for topics, changes their first/last poster name
	private function row_handler($row, $generic_row, $mode = 'topics')
	{
		switch ([$mode, $this->is_staff])
		{
			case ['topics', false]:
				if ($row['topic_first_anonymous_index'])
				{
					$generic_row['TOPIC_AUTHOR'] = $generic_row['TOPIC_AUTHOR_FULL'] = $this->anonymous . ' ' . $row['topic_first_anonymous_index'];
					$generic_row['TOPIC_AUTHOR_COLOUR'] = null;
				}

				if ($row['topic_last_anonymous_index'] > 0)
				{
					$generic_row['LAST_POST_AUTHOR'] = $generic_row['LAST_POST_AUTHOR_FULL'] = $this->anonymous . ' ' . $row['topic_last_anonymous_index'];
					$generic_row['LAST_POST_AUTHOR_COLOUR'] = null;
				}
			break;
			case ['posts_searchrow', false]:
			case ['posts_viewtopic', false]:
			case ['posts_topicreview', false]:
				$is_anonymous = $row;
				if ($is_anonymous)
				{
					$anonymous_name = $this->anonymous . ' ' . $generic_row['anonymous_index'];
					$generic_row['POST_AUTHOR_FULL'] = $generic_row['POST_AUTHOR'] = $anonymous_name;
					$generic_row['anonymous_index'] = null;
					switch ($mode)
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
							$generic_row['U_POST_AUTHOR'] = null;

							// update edit message if the edit user id is equal to the poster's id
							if ($generic_row['update_edit_message'] && $generic_row['EDITED_MESSAGE'])
							{
								$generic_row['EDITED_MESSAGE'] = $this->language->lang('EDITED_TIMES_TOTAL', (int) $generic_row['post_edit_count'], $anonymous_name, $this->user->format_date($generic_row['post_edit_time'], false, true));
							}

						break;
						case 'posts_topicreview':
							$generic_row['POSTER_QUOTE'] = $anonymous_name;
							$generic_row['POST_AUTHOR_COLOUR'] = $generic_row['U_POST_AUTHOR'] =
							$generic_row['S_FRIEND'] = $generic_row['USER_ID'] = null;
						break;
					}
				}
			case ['posts_searchrow', true]:
			case ['posts_viewtopic', true]:
			case ['posts_topicreview', true]:
			case ['posts_mcpreview', true]:
				$is_anonymous = $row;

				$generic_row['IS_ANONYMOUS'] = $is_anonymous;
				$generic_row['IS_STAFF'] = $this->is_staff;
			break;
		}
		return $generic_row;
	}

	// change last poster information in forum index only, keeping it in its own function down here to place it by row handler
	private function rowset_handler($generic_rowset)
	{
		if (!$this->is_staff)
		{
			foreach ($generic_rowset as &$row)
			{
				if ($row['forum_anonymous_index'] > 0)
				{
					$row['forum_last_poster_name'] = $this->anonymous . ' ' . $row['forum_anonymous_index'];
					$row['forum_last_poster_colour'] = $row['forum_last_poster_id'] = null;
				}
			}
		}
		return $generic_rowset;
	}

	// add F_ANONPOST variable to viewtopic.php, mcp.php, and posting.php
	public function assign_template_vars($event)
	{
		$this->template->assign_vars([
			'F_ANONPOST' => $this->auth->acl_get('f_anonpost', $event['forum_id']),
			'U_ANONPOST' => $this->auth->acl_get('u_anonpost'),
		]);
	}

	// handles subscribed/bookmarked topics in the ucp
	public function ucp_bookmarked_and_subscribed_modify_topicrow($event)
	{
		$event['template_vars'] = $this->row_handler($event['row'], $event['template_vars']);
	}

	public function mcp_topic_review_modify_row($event)
	{
		$event['post_row'] = $this->row_handler($event['row']['is_anonymous'], $event['post_row'], 'posts_mcpreview');
	}

	// make posts anonymous in posting.php topic review
	public function topic_review_modify_row($event)
	{
		$event['post_row'] += ['anonymous_index' => $event['row']['anonymous_index']];
		$event['post_row'] = $this->row_handler($event['row']['is_anonymous'], $event['post_row'], 'posts_topicreview');
	}

	// add data to each postrow, take care of friend/foe stuff
	public function viewtopic_post_rowset_data($event)
	{
		$event['rowset_data'] += [
			'is_anonymous'		=> $event['row']['is_anonymous'],
			'anonymous_index'	=> $event['row']['anonymous_index'],
		];
		if ($event['row']['is_anonymous'])
		{
			if ($event['row']['foe'] == '1')
			{
				$event['rowset_data'] = ['foe' => '0', 'hide_post' => false] + $event['rowset_data'];
			}
			if ($event['row']['friend'] == '1')
			{
				$event['row'] = ['friend' => '0'] + $event['row'];
			}
		}
	}

	// delete info in anonymous posts for normal members
	public function viewtopic_modify_post_row($event)
	{
		// delete info from the deleted post hidden div so sneaky members cant find out who it was
		// i did this the opposite way first, then reversed it into this shorter list... nothing should be missing
		$event['post_row'] += [
			'anonymous_index'		=> $event['row']['anonymous_index'],
			'post_edit_count'		=> $event['row']['post_edit_count'],
			'post_edit_time'		=> $event['row']['post_edit_time'],
			'update_edit_message'	=> $event['row']['post_edit_user'] == $event['row']['user_id'],
		];
		$event['post_row'] = $this->row_handler($event['row']['is_anonymous'], $event['post_row'], 'posts_viewtopic');
		// unique to this event
		if ($event['row']['is_anonymous'])
		{
			$event['cp_row'] = null;
		}
	}

	// remove some extra info like emails from anonymous posts that get autoadded to the template after we scrub them the firs time
	public function viewtopic_post_row_after($event)
	{
		if ($event['post_row']['IS_ANONYMOUS'] && !$event['post_row']['IS_STAFF'])
		{
			$this->template->alter_block_array('postrow.contact', [], ['ID' => 'email'], 'delete');
		}
	}

	// update first and last post in topicrow if they are anonymous
	public function viewforum_modify_topicrow($event)
	{
		$event['topic_row'] = $this->row_handler($event['row'], $event['topic_row']);
	}

	// handles changing the last poster name for forumrow
	public function display_forums_before($event)
	{
		$event['forum_rows'] = $this->rowset_handler($event['forum_rows']);
	}

	// modify recenttopicrow, shows on index only by default
	public function recenttopics_modify_tpl_ary($event)
	{
		$event['tpl_ary'] = $this->row_handler($event['row'], $event['tpl_ary']);
	}

	// modify each search topicrow as done in the forumrow, modify username link in postrow
	public function search_modify_tpl_ary($event)
	{
		switch ($event['show_results'])
		{
			case 'topics':
				$event['tpl_ary'] = $this->row_handler($event['row'], $event['tpl_ary']);
			break;
			default: // posts?
				$event['tpl_ary'] += ['anonymous_index' => $event['row']['anonymous_index']];
				$event['tpl_ary'] = $this->row_handler($event['row']['is_anonymous'], $event['tpl_ary'], 'posts_searchrow');
			break;
		}
	}

	// when searching by author, don't show anonymous posts to people who arent the OP of it or staff.  clear cache to see results if updating
	// mysql and postgres function untested
	public function search_modify_search_key($event)
	{
		/**
		 * get data from topicrow to use in the event to change it
		 * removes anonymous posts from "by author" search queries... unless the searcher is staff or searches himself
		 * i haven't found search_key_array to actually help at all
		 */
		if (!$this->is_staff)
		{
			$event['post_visibility'] .= ' AND IF(p.poster_id <> ' . $this->user->data['user_id'] . ', p.is_anonymous <> 1, p.poster_id = p.poster_id)';
		}
	}

	// recalculate user's most active forum and topic count to remove anonymous posts, if it isn't their profile
	public function display_user_activity_modify_actives($event)
	{
		$userdata_ary = $event['userdata'];

		$poster_id = $userdata_ary['user_id'];
		// checks if that poster id ISN'T the current user id and the user isnt staff
		if (!$this->is_staff && ($poster_id !== $this->user->data['user_id']))
		{
			$forum_ary = array();

			$forum_read_ary = $this->auth->acl_getf('f_read');
			foreach ($forum_read_ary as $forum_id => $allowed)
			{
				if ($allowed['f_read'])
				{
					$forum_ary[] = (int) $forum_id;
				}
			}

			$forum_ary = array_diff($forum_ary, $this->user->get_passworded_forums());
			if (!empty($forum_ary))
			{
				$forum_visibility_sql = $this->content_visibility->get_forums_visibility_sql('post', $forum_ary);

				// Have to rerun all 4 queries in functions_display.php starting at line ####
				// Obtain active forum
				$event['active_f_row'] = $this->driver->get_active_f_row($poster_id, $forum_visibility_sql);
				$event['active_t_row'] = $this->driver->get_active_t_row($poster_id, $forum_visibility_sql);
			}
		}
	}
}
