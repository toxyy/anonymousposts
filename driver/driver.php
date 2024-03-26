<?php
/**
*
* @package phpBB Extension - Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\driver;

class driver
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\cache */
	protected $cache;
	/** @var \phpbb\config */
	protected $config;

	/**
	* Constructor
	*
	* @param \phpbb\db\driver\driver_interface $db
	* @param \phpbb\cache\driver $cache
	* @param \phpbb\config\config $config
	*
	*/
	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\config\config $config
	)
	{
		$this->db = $db;
		$this->cache = $cache;
		$this->config = $config;
	}

	/**
	 * Removes old entries from the search results table and removes searches with keywords that contain a word in $words.
	 * https://github.com/phpbb/phpbb/blob/3.3.x/phpBB/phpbb/search/base.php#L241
	 */
	public function destroy_cache($words, $authors = false)
	{
		$authors = array_unique($authors);
		// clear all searches that searched for the specified words
		if (count($words))
		{
			$sql_where = '';
			foreach ($words as $word)
			{
				$sql_where .= " OR search_keywords " . $this->db->sql_like_expression($this->db->get_any_char() . $word . $this->db->get_any_char());
			}

			$sql = 'SELECT search_key
				FROM ' . SEARCH_RESULTS_TABLE . "
				WHERE search_keywords LIKE '%*%' $sql_where";
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->cache->destroy('_search_results_' . $row['search_key']);
			}
			$this->db->sql_freeresult($result);
		}

		// clear all searches that searched for the specified authors
		if (is_array($authors) && count($authors))
		{
			$sql_where = '';
			foreach ($authors as $author)
			{
				$sql_where .= (($sql_where) ? ' OR ' : '') . 'search_authors ' . $this->db->sql_like_expression($this->db->get_any_char() . ' ' . (int) $author . ' ' . $this->db->get_any_char());
			}

			$sql = 'SELECT search_key
				FROM ' . SEARCH_RESULTS_TABLE . "
				WHERE $sql_where";
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->cache->destroy('_search_results_' . $row['search_key']);
			}
			$this->db->sql_freeresult($result);
		}

		$sql = 'DELETE
			FROM ' . SEARCH_RESULTS_TABLE . '
			WHERE search_time < ' . (time() - (int) $this->config['search_store_results']);
		$this->db->sql_query($sql);
	}

	// get unique poster index for consistent distinct anonymous posters
	public function get_poster_index($topic_id, $poster_id)
	{
		// have we already anonymously posted in this topic?
		// 0.9.12 - rewrote the whole thing
		$anon_index_query = 'SELECT (	SELECT anonymous_index
										FROM ' . POSTS_TABLE . "
										WHERE poster_id = $poster_id AND topic_id = $topic_id AND anonymous_index > 0
										ORDER BY post_time ASC LIMIT 1
						  	  ) AS old_index,
							  (MAX(anonymous_index) + 1) AS new_index
							  FROM " . POSTS_TABLE . "
							  WHERE topic_id = $topic_id AND anonymous_index > 0";
		$result = $this->db->sql_query($anon_index_query);

		$old_index = $new_index = 0;
		while ($row = $this->db->sql_fetchrow($result))
		{
			$old_index = is_null($row['old_index']) ? 0 : $row['old_index'];
			// redundancy to ensure NO anon 0s... too critical of a bug.
			$new_index = ($row['new_index'] === 0) ? 1 : (is_null($row['new_index']) ? 1 : $row['new_index']);
		}
		$this->db->sql_freeresult($result);
		return (($old_index > 0) ? $old_index : $new_index);
	}

	// get username from db via user_id, needed for deanonymizing notifications
	public function get_username($user_id)
	{
		$sql = 'SELECT username
				FROM ' . USERS_TABLE . "
				WHERE user_id = $user_id";
		$result = $this->db->sql_query($sql);
		$username = $this->db->sql_fetchfield('username');
		$this->db->sql_freeresult($result);

		return $username;
	}

	// update active_f_row for main->display_user_activity_modify_actives with modified sql ary
	public function get_active_f_row($poster_id, $forum_visibility_sql)
	{
		$forum_sql_ary = "SELECT forum_id, COUNT(post_id) AS num_posts
			FROM " . POSTS_TABLE . "
			WHERE poster_id = $poster_id
				AND post_postcount = 1
				AND $forum_visibility_sql
				AND is_anonymous <> 1
			GROUP BY forum_id
			ORDER BY num_posts DESC";

		$result = $this->db->sql_query_limit($forum_sql_ary, 1);
		$active_f_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!empty($active_f_row))
		{
			$sql = 'SELECT forum_name
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id = ' . $active_f_row['forum_id'];
			$result = $this->db->sql_query($sql, 3600);
			$active_f_row['forum_name'] = (string) $this->db->sql_fetchfield('forum_name');
			$this->db->sql_freeresult($result);
		}
		return $active_f_row;
	}

	// update active_t_row for main->display_user_activity_modify_actives with modified sql ary
	public function get_active_t_row($poster_id, $forum_visibility_sql)
	{
		$topic_sql_ary = "SELECT topic_id, COUNT(post_id) AS num_posts
			FROM " . POSTS_TABLE . "
			WHERE poster_id = $poster_id
				AND post_postcount = 1
				AND $forum_visibility_sql
				AND is_anonymous <> 1
			GROUP BY topic_id
			ORDER BY num_posts DESC";

		$result = $this->db->sql_query_limit($topic_sql_ary, 1);
		$active_t_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!empty($active_t_row))
		{
			$sql = 'SELECT topic_title
				FROM ' . TOPICS_TABLE . '
				WHERE topic_id = ' . $active_t_row['topic_id'];
			$result = $this->db->sql_query($sql);
			$active_t_row['topic_title'] = (string) $this->db->sql_fetchfield('topic_title');
			$this->db->sql_freeresult($result);
		}

		return $active_t_row;
	}

	// get data from topicrow to use in the event to change it
	public function is_anonymous($post_list)
	{
		if (empty($post_list))
		{
			return array();
		}

		$is_anonymous_query = 'SELECT anonymous_index, is_anonymous, poster_id
								FROM ' . POSTS_TABLE . '
								WHERE ' . $this->db->sql_in_set('post_id', $post_list) . '
								ORDER BY post_id ASC';
		$result = $this->db->sql_query($is_anonymous_query);

		$index = 0;
		$continue = false;
		$is_anonymous_list = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['is_anonymous'])
			{
				$continue = true;
			}

			$is_anonymous_list[$index][] = $row['is_anonymous'];
			$is_anonymous_list[$index]['poster_id'] = $row['poster_id'];
			$index++;
		}
		$this->db->sql_freeresult($result);
		// doesn't run if no anonymous usernames to change :)
		if ($continue)
		{
			$username_query = 'SELECT username FROM ' . USERS_TABLE . '
								WHERE ' . $this->db->sql_in_set('user_id', array_column($is_anonymous_list, 'poster_id'));
			$result = $this->db->sql_query($username_query);

			$index = 0;
			while ($row = $this->db->sql_fetchrow($result))
			{
				$is_anonymous_list[$index][1] = $row['poster_id'];
				$index++;
			}
			$this->db->sql_freeresult($result);
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
		$sql = 'UPDATE ' . POSTS_TABLE . ' AS p
				INNER JOIN (	SELECT merged.poster_id, merged.anonymous_index AS new_index,
										old.post_id, old.anonymous_index AS old_index
								FROM ' . POSTS_TABLE . ' AS merged,
									' . POSTS_TABLE . ' AS old
								WHERE ' . $this->db->sql_in_set('merged.post_id', $post_id_list) . "
								AND merged.anonymous_index > 0
								AND merged.anonymous_index <> old.anonymous_index
								AND old.topic_id = $topic_id
								AND " . $this->db->sql_in_set('old.post_id', $post_id_list, true) . '
								AND old.anonymous_index > 0
								AND old.poster_id = merged.poster_id
								GROUP BY merged.poster_id
								ORDER BY NULL
				) AS postdata
				SET p.anonymous_index = postdata.old_index
				WHERE p.anonymous_index > 0
				AND ' . $this->db->sql_in_set('p.post_id', $post_id_list) . '
				AND p.poster_id = postdata.poster_id';
		$this->db->sql_query($sql);
		$this->db->sql_transaction('commit');
	}
}
