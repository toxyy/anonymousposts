<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class v_0_2_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['anonymous_posts']);
	}
        
	static public function depends_on()
	{
		return array('\toxyy\anonymousposts\migrations\release_0_1_0_data');
	}

	public function update_data()
	{
		return array(
			// Remove old bad configs
			array('config.remove', array('anonymous_posts')),
			array('config.remove', array('anonymous_posts_limit')),
			array('config.remove', array('anonymous_posts_hide')),
			array('config.remove', array('anonymous_posts_ignore')),
			array('config.remove', array('anonymous_posts_type')),
			array('config.remove', array('anonymous_posts_time')),
			array('config.remove', array('anonymous_posts_version')),
		);
	}
}