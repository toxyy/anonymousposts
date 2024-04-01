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
* Posting Event listener
* Events related to the posting.php page and submitting
*/

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class posting implements EventSubscriberInterface
{
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \phpbb\request\request */
	protected $request;
	/** @var \phpbb\notification\manager */
	protected $notification_manager;
	/** @var \toxyy\anonymousposts\driver\driver */
	protected $driver;

	/**
	* Constructor
	*
	* @param \phpbb\language\language						$language
	* @param \phpbb\template\template						$template
	* @param \phpbb\user									$user
	* @param \phpbb\auth\auth								$auth
	* @param \phpbb\request\request							$request
	* @param \phpbb\notification\manager					$notification_manager
	* @param \toxyy\anonymousposts\driver\driver			$driver
	*
	*/
	public function __construct(
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\phpbb\request\request $request,
		\phpbb\notification\manager $notification_manager,
		\toxyy\anonymousposts\driver\driver $driver
	)
	{
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
		$this->auth = $auth;
		$this->request = $request;
		$this->notification_manager = $notification_manager;
		$this->driver = $driver;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.posting_modify_post_data'					=> 'posting_modify_post_data',
			'core.posting_modify_template_vars'				=> 'posting_modify_template_vars',
			'core.posting_modify_submit_post_before'		=> 'posting_modify_submit_post_before',
			'core.modify_submit_post_data'					=> 'modify_submit_post_data',
			'core.posting_modify_quote_attributes'			=> 'posting_modify_quote_attributes',
			'core.submit_post_modify_sql_data'				=> 'submit_post_modify_sql_data',
			'core.modify_submit_notification_data'			=> 'modify_submit_notification_data',
			'core.notification_manager_add_notifications'	=> 'notification_manager_add_notifications',
		];
	}

	// change poster information in quotes, modify post_data for posting_modify_submit_post_before
	public function posting_modify_post_data($event)
	{
		$post_data = $event['post_data'];
		$post_data['is_checked'] = $this->request->variable('anonpost', 0);
		$is_anonymous = !isset($post_data['is_anonymous']) ? false : $post_data['is_anonymous'];

		// keep checkbox checked only if editing a post, otherwise it is unchecked by default
		if ($event['mode'] === 'edit')
		{
			// don't allow non staff (by default at least) to edit anon status, makes webmasters happy
			if (!($this->auth->acl_get('u_edit_anonpost') && $this->auth->acl_get('f_edit_anonpost', $event['forum_id'])))
			{
				$post_data['is_checked'] = $is_anonymous;
			}
		}
		if ($is_anonymous)
		{
			$post_data['poster_id_backup'] = $post_data['poster_id'];
			$post_data['quote_username'] = $this->language->lang('ANP_DEFAULT') . ' ' . $post_data['anonymous_index'];
			//$post_data['poster_id'] = ANONYMOUS;
		}
		$event['post_data'] = $post_data;
	}

	// removes the username field from posting.php if editing an anonymous post
	// keeps checkbox checked during some page refresh or error or some reason hitting submit doesnt submit the post
	public function posting_modify_template_vars($event)
	{
		$is_editing = $event['mode'] === 'edit';
		$is_anonymous = !isset($event['post_data']['is_anonymous']) ? false : $event['post_data']['is_anonymous'];
		$s_poll_data = !isset($event['page_data']['S_POLL_DATA']) ? '' : $event['page_data']['S_POLL_DATA'];
		$event['page_data'] = [
			'S_DISPLAY_USERNAME'	=> ((!$this->user->data['is_registered'] ||
				($is_editing &&
					(!$is_anonymous &&
						($event['post_data']['poster_id'] == ANONYMOUS)
					)
				)
			) ? 1 : 0),
			'S_POLL_DELETE'			=> (!$is_anonymous ? $s_poll_data :
				($is_editing && count($event['post_data']['poll_options']) &&
					((!$event['post_data']['poll_last_vote'] &&
							$event['post_data']['poster_id_backup'] == $this->user->data['user_id'] &&
							$this->auth->acl_get('f_delete', $event['forum_id'])) ||
						$this->auth->acl_get('m_delete', $event['forum_id'])
					)
				)
			),
		] + $event['page_data'];
		// keep checkbox checked if someone posts before you're done... might work for if your post was edited too, haven't tested
		if ($is_editing || $event['preview'] || !empty($event['error']) || (!$event['submit'] && $event['refresh'] && $this->template->retrieve_var('S_POST_REVIEW')))
		{
			$checkbox_attributes = '';
			// don't allow non staff (by default at least) to edit anon status, makes webmasters happy
			if ($is_editing && !($this->auth->acl_get('f_edit_anonpost', $event['forum_id']) && $this->auth->acl_get('u_edit_anonpost')))
			{
				$checkbox_attributes = 'disabled';
			}

			if ($is_anonymous || $event['post_data']['is_checked'])
			{
				$checkbox_attributes = 'checked ' . $checkbox_attributes;
			}

			$this->template->assign_vars(['POST_IS_ANONYMOUS' => $checkbox_attributes]);
		}
	}

	// fixed to return 1 for new topics, and mean it this time... wouldn't work sometimes for some weird reason
	public function get_anon_index($data, $post_mode) {
		// anon index isn't updated when editing & toggling off anon, so return 0 isnt bad
		if ($data['is_anonymous'])
		{
			// first post is always anon 1
			if ($post_mode === 'post')
			{
				return 1;
			}
			// are we editing? (this is or was an anon post, unless it is 0 anon index stays set forever)
			if ($data['anonymous_index'] > 0)
			{
				return $data['anonymous_index'];
			}
			// get a new one then
			return $this->driver->get_poster_index($data['topic_id'], ($post_mode === 'quote' ? (int) $this->user->data['user_id'] : $data['poster_id']));
		}
		// default value
		return 0;
	}

	// add variables to $data for use in submit_post_modify_sql_data
	public function posting_modify_submit_post_before($event)
	{
		$data = $event['data'];
		$post_mode = $event['mode'];
		// get checkbox value
		$data['is_anonymous'] = $event['post_data']['is_checked'];

		$data['was_anonymous'] = ($post_mode === 'edit') ? $event['post_data']['is_anonymous'] : 0;
		$data['anonymous_index'] = ($post_mode === 'edit') ? $event['post_data']['anonymous_index'] : 0;

		$data['anonymous_index'] = $this->get_anon_index($data, $post_mode);
		$data['forum_last_post_id'] = $event['post_data']['forum_last_post_id'];
		// these two are for checking if when posting/replying not anonymously and there are indices to update
		if ($event['post_data']['topic_last_anonymous_index'] > 0)
		{
			$data['topic_last_anonymous_index'] = $event['post_data']['topic_last_anonymous_index'];
		}
		if ($event['post_data']['forum_anonymous_index'] > 0)
		{
			$data['forum_anonymous_index'] = $event['post_data']['forum_anonymous_index'];
		}
		// data for unsetting anonymous post
		if ($data['was_anonymous'])
		{
			$data['fixed_poster_id'] = $event['post_data']['poster_id_backup'];
			$data['username_backup'] = $this->driver->get_username($data['fixed_poster_id']);
			if (!$data['is_anonymous'])
			{
				$event['post_author_name'] = $event['post_data']['topic_last_poster_name'];
			}
		}
		$event['data'] = $data;
	}

	// redundant functionality from posting_modify_submit_post_before for compatibility with extensions that use submit_post
	public function modify_submit_post_data($event)
	{
		$data = $event['data'];
		$post_mode = $event['mode'];
		// get checkbox value
		$data['is_anonymous'] = $this->request->variable('anonpost', 0);
		$data['anonymous_index'] = $this->get_anon_index($data, $post_mode);

		$event['data'] = $data;
	}

	// removes user id when quoting an anonymous post
	public function posting_modify_quote_attributes($event)
	{
		if ($event['post_data']['is_anonymous'])
		{
			$event->update_subarray('quote_attributes', 'user_id', ANONYMOUS);
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

		$data = $event['data'];
		// https://github.com/phpbb/phpbb/blob/dc966787e144d262dee74ac64bd449887a336f28/phpBB/includes/mcp/mcp_post.php#L634
		$user_id = $this->user->data['user_id'];
		// universal
		$sql_data[POSTS_TABLE]['sql']['is_anonymous'] = $is_anonymous;
		switch ([$post_mode, $is_anonymous, $was_anonymous])
		{	/*
			* NORMAL POSTS (false, false)
			* zeros out the current forum and topic (if this is not an op) anonymous index
			*/
			case ['reply', false, false]:
			case ['quote', false, false]:
				if (isset($data['topic_last_anonymous_index']))
				{
					$sql_data[TOPICS_TABLE]['stat']['topic_last_anonymous_index'] = 'topic_last_anonymous_index = ' . 0;
				}
			case ['post', false, false]:
				if (isset($data['forum_anonymous_index']))
				{
					$sql_data[FORUMS_TABLE]['stat']['forum_anonymous_index'] = 'forum_anonymous_index = ' . 0;
				}

			break;
			/*
			* TOGGLE ON/CREATE ANONYMOUS POST (true, false)
			* if posting, replying, or quoting, creates anonymous post
			* if editing a post to be anonymous, adds data to the posts/topics/forums tables depending on the edit mode
			*/
			case ['post', true, false]:
			case ['edit_topic', true, false]:
			case ['edit_first_post', true, false]:
				$sql_data[TOPICS_TABLE]['stat']['topic_first_anonymous_index'] = 'topic_first_anonymous_index = ' . $data['anonymous_index'];
			case ['reply', true, false]:
			case ['quote', true, false]:
				$modify_forum_anon_index = true;
			case ['edit_last_post', true, false]:
				// edit_first_post only occurs when the OP isn't the topic's last post
				if ($event['post_mode'] !== 'edit_first_post')
				{
					$sql_data[TOPICS_TABLE]['stat']['topic_last_anonymous_index'] = 'topic_last_anonymous_index = ' . $data['anonymous_index'];
					if (isset($modify_forum_anon_index) || $data['post_id'] == $data['forum_last_post_id'])
					{
						$sql_data[FORUMS_TABLE]['stat']['forum_anonymous_index'] = 'forum_anonymous_index = ' . $data['anonymous_index'];
					}
				}
			case ['edit', true, false]:
				$sql_data[POSTS_TABLE]['sql']['anonymous_index'] = $data['anonymous_index'];
				// if you search posts per user id, if anon status is toggled on, they still appear in results even if anonymous
				$this->driver->destroy_cache([], [$data['poster_id'], $user_id]);
			break;
			/*
			* TOGGLE OFF ANONYMOUS POST (false, true)
			* if editing a post and anonymous is removed, handles each case and updates the db accordingly
			*/
			case ['edit_topic', false, true]:
			case ['edit_first_post', false, true]:
				$sql_data[TOPICS_TABLE]['stat']['topic_first_anonymous_index'] = 'topic_first_anonymous_index = ' . 0;
				$sql_data[TOPICS_TABLE]['stat']['topic_first_poster_name'] = 'topic_first_poster_name = \'' . $data['username_backup'] . '\'';
				// from includes/functions_posting.php ~ line 2060
				// testing shows that if there are only two posts that this is edit_last_post, but the files included it so I am too
				$first_post_has_topic_info = ($post_mode === 'edit_first_post' &&
					(($post_visibility === ITEM_DELETED && $data['topic_posts_softdeleted'] === 1) ||
						($post_visibility === ITEM_UNAPPROVED && $data['topic_posts_unapproved'] === 1) ||
						($post_visibility === ITEM_REAPPROVE && $data['topic_posts_unapproved'] === 1) ||
						($post_visibility === ITEM_APPROVED && $data['topic_posts_approved'] === 1)));
			case ['edit_last_post', false, true]:
				$first_post_has_topic_info = ($post_mode === 'edit_first_post' && isset($first_post_has_topic_info));
				if ($first_post_has_topic_info || $post_mode !== 'edit_first_post')
				{
					$sql_data[TOPICS_TABLE]['stat']['topic_last_anonymous_index'] = 'topic_last_anonymous_index = ' . 0;
				}

				if ($first_post_has_topic_info || $data['post_id'] == $data['forum_last_post_id'])
				{
					$sql_data[FORUMS_TABLE]['stat']['forum_anonymous_index'] = 'forum_anonymous_index = ' . 0;
				}
			case ['edit', false, true]:
				// if you search posts per user id, if anon status is toggled on, they still appear in results even if anonymous
				$this->driver->destroy_cache([], [$data['poster_id'], $user_id]);
			/*
			* EDIT ANONYMOUS POST (true, true)
			* if editing an anonymous post, we need to fix the poster id so it doesnt save to guest
			*/
			case ['edit_topic', true, true]:
			case ['edit_first_post', true, true]:
				if ($is_anonymous)
				{
					$sql_data[TOPICS_TABLE]['stat']['topic_first_poster_name'] = 'topic_first_poster_name = \'' . $data['username_backup'] . '\'';
				}
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
		switch ([$event['mode'], $event['data_ary']['is_anonymous'] || $event['data_ary']['was_anonymous']])
		{
			case ['post', true]:
			case ['reply', true]:
			case ['quote', true]:
			case ['edit', true]:
				// poster id likely already anonymous when is_anonymous is true, id rather be sure and redo it here
				$event['notification_data'] = [
					'poster_id'		=> $event['data_ary']['is_anonymous'] ? ANONYMOUS : $event['data_ary']['poster_id'],
					'post_username'	=> $event['data_ary']['is_anonymous'] ? ($this->language->lang('ANP_DEFAULT') . ' ' . $event['data_ary']['anonymous_index']) : $event['data_ary']['username_backup'],
				] + $event['notification_data'];
			break;
		}
	}

	// fix notifying users who posted anonymously if people have quoted them
	public function notification_manager_add_notifications($event)
	{
		// adapted from phpbb/textformatter/s9e/utils.php get_outermost_quote_authors function
		if ($event['notification_type_name'] == 'notification.type.quote')
		{
			$data = $event['data'];
			$xml = $data['message'];
			if (!(strpos($xml, '<QUOTE ') === false))
			{
				$quote_data = array();
				$dom = new \DOMDocument;
				$dom->loadXML($xml);
				$xpath = new \DOMXPath($dom);
				// stores all outermost quote attributes in an array[x][y], where x is the outermost quote index
				$count = 0;
				foreach ($xpath->query('//QUOTE[not(ancestor::QUOTE)]/@*') as $element)
				{	// add some value to count for the first case of author, so the next index......idk if this is a good way to do this
					$count += ($element->nodeName == 'author') ? ($count == 0 ? 0.6 : 1) : 0;
					$quote_data[floor($count)][$element->nodeName] = $element->nodeValue;
				}
				// get second xpath query to preserve index order to match is_anonymous_list
				// if their sizes dont match, for some reason a quote doesn't have an author... would there even be a notification?
				$quote_authors = $xpath->query('//QUOTE[not(ancestor::QUOTE)]/@author');
				$is_anonymous_list = $this->driver->is_anonymous(array_column($quote_data, 'post_id'));

				// is_anonymous_list[][1] is the author_id
				foreach ($quote_data as $index => $value)
				{
					if ((bool) $is_anonymous_list[$index][0])
					{
						$quote_authors->item($index)->nodeValue = $is_anonymous_list[$index][1];
					}
				}

				// save the usernames back into the post_text we just got
				$data['post_text'] = $dom->saveXML();
			}
			// should this be in the if statement above?
			$event['notify_users'] = $this->notification_manager->get_item_type_class($event['notification_type_name'])
				->find_users_for_notification($data, $event['options'])
			;
		}
	}
}
