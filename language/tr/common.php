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
		'ANP_DEFAULT'				=> 'Anonim',
		'ANP_MESSAGE'				=> 'Anonim Gönderi',
		'ANP_ACTION'				=> 'Anonim olarak gönder',
		'ANP_PERMISSIONS'			=> 'Anonim olarak gönderebilir',
		'ANP_EDIT_PERMISSIONS'		=> 'Anonim durumu düzenleyebilir',
]);
