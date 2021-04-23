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
* MCP Functions Event listener
* Events related to acp permission options, deleting/restoring, forking/copying, moving, splitting/merging, etc.
*/

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class mcp_functions implements EventSubscriberInterface
{
	/** @var \toxyy\anonymousposts\driver\driver */
	protected $driver;

	/**
	* Constructor
	*
	* @param \toxyy\anonymousposts\driver\driver			$driver
	*
	*/
	public function __construct(\toxyy\anonymousposts\driver\driver $driver)
	{
		$this->driver = $driver;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.permissions'								=> 'core_permissions',
			'core.move_posts_after'							=> 'move_posts_after',
			'core.mcp_main_modify_fork_sql'					=> 'mcp_main_modify_fork_sql',
			'core.mcp_main_modify_fork_post_sql'			=> 'mcp_main_modify_fork_post_sql',
			'core.update_post_info_modify_posts_sql'		=> 'sync_post_info_sql',
			'core.sync_forum_last_post_info_sql'			=> 'sync_post_info_sql',
			'core.sync_topic_last_post_info_sql'			=> [['sync_post_info_sql'], ['add_custom_fieldnames']],
			'core.update_post_info_modify_sql'				=> 'update_post_info_modify_sql',
			'core.sync_modify_topic_data'					=> 'sync_modify_topic_data',
			'core.sync_modify_forum_data'					=> 'sync_modify_forum_data',
		];
	}

	// fix permissions for acp
	public function core_permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions['f_anonpost'] = ['lang' => 'ANP_PERMISSIONS', 'cat' => 'post'];
		$permissions['f_edit_anonpost'] = ['lang' => 'ANP_EDIT_PERMISSIONS', 'cat' => 'post'];
		$permissions['u_anonpost'] = ['lang' => 'ANP_PERMISSIONS', 'cat' => 'post'];
		$permissions['u_edit_anonpost'] = ['lang' => 'ANP_EDIT_PERMISSIONS', 'cat' => 'post'];
		$event['permissions'] = $permissions;
	}

	// sync all post anon indexes so that each poster_id retains their unique index in the $to_topic_id thread, BEFORE the sync functions do their biz
	public function move_posts_after($event)
	{
		$this->driver->move_sync_topic_anonymous_posts($event['topic_id'], $event['post_ids']);
	}

	// add missing vars to the copy/fork topic sql array
	public function mcp_main_modify_fork_sql($event)
	{
		$event['sql_ary'] += [
			'topic_first_anonymous_index'	=> (int) $event['topic_row']['topic_first_anonymous_index'],
			'topic_last_anonymous_index'	=> (int) $event['topic_row']['topic_last_anonymous_index'],
		];
	}

	// add missing vars to the copy/fork posts sql array
	public function mcp_main_modify_fork_post_sql($event)
	{
		$event['sql_ary'] += [
			'is_anonymous'		=> (int) $event['row']['is_anonymous'],
			'anonymous_index'	=> (int) $event['row']['anonymous_index'],
		];
	}

	// add missing vars to the query to get last posts' data for update_post_info and sync
	public function sync_post_info_sql($event)
	{
		$event['sql_ary'] = ['SELECT' => $event['sql_ary']['SELECT'] . ', p.is_anonymous, p.anonymous_index'] + $event['sql_ary'];
	}

	// add custom fieldnames to update the topics_table with
	public function add_custom_fieldnames($event)
	{
		$event['custom_fieldnames'] += ['first_anonymous_index', 'last_anonymous_index'];
	}

	// this is the sync function only for last posts.  add missing vars to the update_sql array to have topics/forums
	public function update_post_info_modify_sql($event)
	{
		$type = $event['type'];
		$last = ($type === 'topic') ? '_last_' : '_';
		$update_sql = $event['update_sql'];

		$rowset = $event['rowset'];
		foreach ($rowset as $row)
			$update_sql[$row["{$type}_id"]][] = $type . $last . 'anonymous_index = ' . (int) ($row['is_anonymous'] ? $row['anonymous_index'] : 0);

		$event['update_sql'] = $update_sql;
	}

	// add missing data to topic_data to account for the anon data when syncing, fieldnames taken care of in the other sync topic event
	public function sync_modify_topic_data($event)
	{
		$topic_data = $event['topic_data'];
		$row = $event['row'];
		$topic_id = $event['topic_id'];
		// add a number that will never be >= 0, so this forces these two to sync (default value 0 had problems equaling _ and '')
		$topic_data[$topic_id]['topic_first_anonymous_index'] = $topic_data[$topic_id]['topic_last_anonymous_index'] = -1;

		$anonymous_index = (int) ($row['is_anonymous'] ? $row['anonymous_index'] : 0);
		if ($row['post_id'] === $topic_data[$topic_id]['first_post_id'])
			$topic_data[$topic_id]['first_anonymous_index'] = $anonymous_index;

		if ($row['post_id'] === $topic_data[$topic_id]['last_post_id'])
			$topic_data[$topic_id]['last_anonymous_index'] = $anonymous_index;

		$event['topic_data'] = $topic_data;
	}

	// add missing data to forum_data and field name to fieldnames to update forum last anon index appropriately
	public function sync_modify_forum_data($event)
	{
		$forum_data = $event['forum_data'];
		// you cant use += on a numeric array to append a new value
		$event['fieldnames'] = array_merge($event['fieldnames'], ['anonymous_index']);
		$post_info = $event['post_info'];
		foreach ($forum_data as $forum_id => &$data)
		{
			if ($data['last_post_id'])
			{
				$update_anonymous = (isset($post_info[$data['last_post_id']]) && $post_info[$data['last_post_id']]['is_anonymous']);
				$data['anonymous_index'] = (int) ($update_anonymous ? $post_info[$data['last_post_id']]['anonymous_index'] : 0);
			}
		}
		// phpstorm recommends unsetting this var
		unset($data);
		$event['forum_data'] = $forum_data;
	}
}
