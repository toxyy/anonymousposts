<?php
/**
*
* phpBB Extension - toxyy Anonymous Posts
* @copyright (c) 2018 toxyy <thrashtek@yahoo.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace toxyy\anonymousposts\migrations;

class release_0_1_0_data extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['anonymous_posts']);
	}

	public function update_data()
	{
		return array(
			// Add configs
			array('config.add', array('anonymous_posts', '0')),
			array('config.add', array('anonymous_posts_limit', '5')),
			array('config.add', array('anonymous_posts_hide', '')),
			array('config.add', array('anonymous_posts_ignore', '')),
			array('config.add', array('anonymous_posts_type', 'y')),
			array('config.add', array('anonymous_posts_time', '365')),
			array('config.add', array('anonymous_posts_version', '0.1.0')),
                    
			// Add permissions
			array('permission.add', array('u_anonpost')),
			array('permission.add', array('f_anonpost', false)),
		);
	}
        
        public function update_schema()
        {
                return array(
                        'add_columns'   => array(
                                $this->table_prefix . 'posts'   => array(
                                        'is_anonymous'  => array('BOOL', 0),
                                ),
                        ),
                );
        }
        
        public function revert_schema()
        {
                return array(
                        'drop_columns'  => array(
                                $this->table_prefix . 'posts'   => array(
                                        'is_anonymous',
                                ),
                        ),
                );
        }
}
