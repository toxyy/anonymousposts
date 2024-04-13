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
* ACP Functions Event listener
* Events related to adding acp forum settings
*/

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class acp_functions implements EventSubscriberInterface
{
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\request\request */
	protected $request;

	/**
	* Constructor
	*
	* @param \phpbb\language\language	$language
	* @param \phpbb\request\request		$request
	*
	*/
	public function __construct(
		\phpbb\language\language $language,
		\phpbb\request\request $request
	)
	{
		$this->language = $language;
		$this->request = $request;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.acp_manage_forums_display_form'		=> 'acp_manage_forums_display_form',
			'core.acp_manage_forums_initialise_data'	=> 'acp_manage_forums_initialise_data',
			'core.acp_manage_forums_request_data'		=> 'acp_manage_forums_request_data',
		];
	}

	public function acp_manage_forums_display_form($event)
	{
		$event->update_subarray('template_data', 'S_ANP_POST_FORCE', $event['forum_data']['anp_post_force']);
		$event->update_subarray('template_data', 'S_ANP_IGNORE_POST_PERMISSIONS', $event['forum_data']['anp_ignore_post_permissions']);
	}

	public function acp_manage_forums_initialise_data($event)
	{
		if ($event['action'] == 'add')
		{
			$event->update_subarray('forum_data', 'anp_ignore_post_permissions', false);
			$event->update_subarray('forum_data', 'anp_ignore_post_permissions', false);
		}
	}

	public function acp_manage_forums_request_data($event)
	{
		$event->update_subarray('forum_data', 'anp_post_force', $this->request->variable('anp_post_force', false));
		$event->update_subarray('forum_data', 'anp_ignore_post_permissions', $this->request->variable('anp_ignore_post_permissions', false));
	}
}
