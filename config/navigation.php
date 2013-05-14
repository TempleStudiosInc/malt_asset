<?php defined('SYSPATH') or die('No direct access allowed.');

return array(
	'admin' => array(
		'content' => array(
			'title' => 'Content',
			'url' => '/admin_content',
			'controller' => 'Content',
			'permission' => 'admin',
			'submenu' => array(
				'media' => array(
					'title' => 'Media',
					'url' => '/admin_asset',
					'controller' => 'Asset',
					'permission' => 'asset',
					'icon' => 'icon-picture'
				),
			)
		)
	)
);
