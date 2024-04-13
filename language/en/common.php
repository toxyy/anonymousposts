<?php
/**
*
* @package phpBB Extension - Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ANP_DEFAULT'							=> 'Anonymous',
	'ANP_MESSAGE'							=> 'Anonymous post',
	'ANP_ACTION'							=> 'Post Anonymously',
	'ANP_PERMISSIONS'						=> 'Can post anonymously',
	'ANP_EDIT_PERMISSIONS'					=> 'Can edit anonymous status',
	'ANP_SETTINGS'							=> 'Anonymous post settings',
	'ANP_POST_FORCE'						=> 'Force anonymous posts',
	'ANP_POST_FORCE_EXPLAIN'				=> 'If set to yes posts made to this forum will be anonymous by default.',
	'ANP_IGNORE_POST_PERMISSIONS'			=> 'Ignore anonymous post permissions',
	'ANP_IGNORE_POST_PERMISSIONS_EXPLAIN'	=> 'If set to yes anonymous post permissions will be ignored and ever user with permissions to post will be able to post anonymously. This setting does not affect permissions to edit a post\'s anonymous status.',
]);
