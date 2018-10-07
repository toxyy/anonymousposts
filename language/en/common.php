<?php

/**
*
* phpBB Extension - toxyy Anonymous Posts
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
	'ANP_DEFAULT'       => 'Anonymous',
	'ANP_MESSAGE'       => 'Anonymous post',
	'ANP_ACTION'        => 'Post Anonymously',
        'ANP_PERMISSIONS'   => 'Can post anonymously',
]);
