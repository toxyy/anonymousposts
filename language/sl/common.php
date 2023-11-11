<?php
/**
*
* @package phpBB Extension - Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
* Slovenian Translation - Marko K.(max, max-ima,...)
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
	'ANP_DEFAULT'				=> 'Anonimno',
	'ANP_MESSAGE'				=> 'Anonimna objava',
	'ANP_ACTION'				=> 'Objavi anonimno',
	'ANP_PERMISSIONS'			=> 'Lahko objavi anonimno',
	'ANP_EDIT_PERMISSIONS'		=> 'Lahko ureja anonimni status',
]);
