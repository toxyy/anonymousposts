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
	'ANP_INSTALL_ERROR'	=> 'martti\'nin Group Template Variables eklentisi gerekli.',
]);
// TRANSLATORS CAN IGNORE THIS.
// Overwrite core error message keys with a more specific message.
$lang = array_merge($lang, [
	'EXTENSION_NOT_ENABLEABLE'		=> isset($lang['EXTENSION_NOT_ENABLEABLE']) ?
		$lang['EXTENSION_NOT_ENABLEABLE'] . '<br /><br />' . sprintf($lang['ANP_INSTALL_ERROR']) :
		null,
	'CLI_EXTENSION_ENABLE_FAILURE'	=> isset($lang['CLI_EXTENSION_ENABLE_FAILURE']) ?
		$lang['CLI_EXTENSION_ENABLE_FAILURE'] . '. ' . sprintf($lang['ANP_INSTALL_ERROR']) :
		null,
]);
