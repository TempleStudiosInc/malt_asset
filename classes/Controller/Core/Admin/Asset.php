<?php defined('SYSPATH') or die('No direct script access.');

abstract class Controller_Core_Admin_Asset extends Controller_Admin_Website {

    public function before()
    {
    	parent::before();
        $this->page_title = '';
        $this->model_name = 'asset';
        
        $request = Request::initial();
        $requested_action = $request->action();
        $this->template->content_title = 'Media';
        if ($requested_action == 'handle_upload')
        {
            $this->template = 'templates/default/layout_ajax';
        }
    }
	
	public function after()
    {
    	$request = Request::initial();
		$sidebar_navigation_view = View::factory('asset/admin/navigation');
		$requested_controller = str_replace('Admin_', '', $request->controller());
		$sidebar_navigation_view->requested_controller = $requested_controller;
		$requested_action = $request->action();
		
		$assets_tags = ORM::factory('Assets_Tag');
		$assets_tags->select(DB::expr('COUNT(assets_tag.id) AS tag_count'));
        $assets_tags->join('assets');
        $assets_tags->on('assets.id', '=', 'assets_tag.asset_id');
        $assets_tags->where('assets.title', '!=', '');
		$assets_tags->limit(15);
		$assets_tags->group_by('tag_id');
		$assets_tags->order_by('tag_count', 'DESC');
		$assets_tags = $assets_tags->find_all();
		$tags = array();
		foreach ($assets_tags as $assets_tag)
		{
			$tag = ORM::factory('Tag', $assets_tag->tag_id);
			$tags[$tag->name] = $tag->name;
		}
		$sidebar_navigation_view->tags = $tags;
		
		$this->template->sidebar_navigation = $sidebar_navigation_view;
		
        parent::after();
    }
    
	public function action_index()
	{
		$breadcrumb = View::factory('asset/admin/breadcrumb');
		$breadcrumb_items = array();
		$breadcrumb->items = $breadcrumb_items;
		$this->template->breadcrumb = $breadcrumb;
		
		$model_name = $this->model_name;
        $view = View::factory($model_name.'/admin/assets');
        $view->model_name = $this->model_name;
		
        $request = Request::initial();
        $query_array = $request->query();
        $view->query_array = $query_array;
        
		
        $search_form = array(
            'title' => '',
            'type' => '',
            'user_type' => 'admin'
        );
        $form = Arr::get($_GET, 'form', $search_form);
        if ( ! isset($form))
        {
            $form = array();
        }
        foreach ($search_form as $key => $value)
        {
            $form[$key] = Arr::get($form, $key, $value);
        }
        $view->search = $form;
		
		$types = array(
			'' => 'All Types',
			'video' => 'Video',
			'audio' => 'Audio',
			'image' => 'Image',
			'raw' => 'Raw'
		);
		$view->types = $types;
		$type = $form['type'];
		$view->type = $type;
		if ($type == '')
		{
			$type = 'All Media';
		}
		else
		{
			$type = ucfirst($type);
		}
		
        $assets = $this->database_search($model_name = 'asset', $form);
        $result_count = $assets->count_all();
        
        $assets = $this->database_search($model_name = 'asset', $form);
        
        $page = Arr::get($_GET, 'page', 1);
        
        if (Arr::get($_GET, 'view_all', false))
        {
            $page_limit = $result_count;
            
            $view_all_button = HTML::anchor('/admin_asset/', 'Paginate List', array('id' => 'abutton', 'class' => 'btn btn-success'));
            $view->view_all_button = $view_all_button;
        }
        else
        {
            $page_limit = 20;
            $offset = ($page-1)*$page_limit;
            $assets->limit($page_limit)->offset($offset);
            
            $view_all_button = HTML::anchor('/admin_asset/?view_all=true', 'View All', array('id' => 'abutton', 'class' => 'btn btn-success'));
            $view->view_all_button = $view_all_button;
        }
        
        $pagination = Pagination::factory(array(
            'items_per_page' => $page_limit,
            'total_items' => $result_count,
        ));
        $view->pagination = $pagination;
        
        $order_by_value = Arr::get($_GET, 'order_by', 'id');
        $sorted = Arr::get($_GET, 'sorted', 'asc');
        
        $assets = $assets->order_by($order_by_value, $sorted)->find_all();
        $view->assets = $assets;
        $session = Session::instance();
		$session->delete('return_url');
		$session->set('return_url', Request::detect_uri().'?'.http_build_query($_GET, '&'));
        $this->template->body = $view;
	}
    
    public function action_edit()
    {
    	$breadcrumb = View::factory('asset/admin/breadcrumb');
		$breadcrumb_items = array(
			'/admin_asset/edit' => 'Edit Asset',
		);
		$breadcrumb->items = $breadcrumb_items;
		$this->template->breadcrumb = $breadcrumb;
		
        $model_name = $this->model_name;
        
        $id = $this->request->param('id');
        $asset = ORM::factory(ucfirst($this->model_name), $id);
        
        $view = View::factory($model_name.'/admin/add_edit');
        $view->asset = $asset;
        
        $category_model = new Model_Category;
        $categories = $category_model->get_category_tree();
        $view->categories = $categories;
        
        $selected_categories = array();
        foreach ($asset->categories->find_all() as $asset_category)
        {
            $selected_categories[] = $asset_category->id;
        }
        $view->selected_categories = $selected_categories;
        
        $this->template->body = $view;
    }
    
    public function action_save()
    {
        $post = Arr::get($_POST, 'asset');
        foreach ($post as $key => $value)
        {
            switch ($key)
            {
                case 'id':
                    if ($value == 0)
                    {
                        $asset = ORM::factory(ucfirst($this->model_name));
                    }
                    else
                    {
                        $asset = ORM::factory(ucfirst($this->model_name), $value);
                    }
                    break;
                default:
                    $asset->$key = $value;
                    break;
            }
        }
        $asset->save();
        
		$tags = Arr::get($_POST, 'hidden-tags', '');
		$tags = explode(',', $tags);
		$tag_model = new Model_Tag;
		$tag_model->add_tags($tags, $asset);
		
        $categories = Arr::get($_POST, 'categories');
        
        $category_model = new Model_Category;
        $category_model->add_categories($categories, $asset);
        
        Notice::add(Notice::SUCCESS, 'Asset Saved.');
		$session = Session::instance();
		$return = $session->get('return_url');
		if ($return != '')
		{
			$this->redirect($return);
		}
		else
		{
			$this->redirect('/admin_asset/');
		}
        
    }
    
    public function action_delete()
    {
		$id = $this->request->param('id');
		
        $asset = ORM::factory(ucfirst($this->model_name), $id);
        $type = $asset->type;
        $asset->remove_all_relations();
        $asset->delete();
        
        Notice::add(Notice::SUCCESS, 'Asset Deleted.');
		$query_string = '';
		if (count($_GET) > 0)
		{
			$query_string = '?'.http_build_query($_GET);
		}
		$this->redirect('/admin_asset/'.$query_string);
    }
    
    public function action_upload()
    {
    	$breadcrumb = View::factory('asset/admin/breadcrumb');
		$breadcrumb_items = array(
			'/admin_asset/upload' => 'Upload Media',
		);
		$breadcrumb->items = $breadcrumb_items;
		$this->template->breadcrumb = $breadcrumb;
		
    	ini_set('memory_limit', '-1');
		
        $view = View::factory($this->model_name.'/admin/upload');
        $this->template->body = $view;
    }
    
    public function action_handle_upload()
    {
    	ini_set('upload_max_filesize', '2048m');
    	ini_set('memory_limit', '-1');
		
    	$id = $this->request->param('id');
		
        require Kohana::find_file('vendor', 'jquery_file_uploader/upload.class', 'php');
        $upload_options = array(
            'script_url' => '/_media/common/jquery_file_uploader/',
            'upload_dir' => '_media/uploads/',
            'upload_url' => '/_media/uploads/',
            'image_versions' => array(
                'thumbnail' => array(
                    'upload_dir' => '_media/uploads/thumbnails/',
                    'upload_url' => '/_media/uploads/thumbnails/',
                    'max_width' => 80,
                    'max_height' => 80
                )
            )
        );
        
        if (isset($_FILES) AND count($_FILES) > 0) {
            foreach ($_FILES as $file_key => $file_value)
            {
                $param_name = $file_key;
                break;
            }
            
            $upload_options['param_name'] = $param_name;
        }
        
        $upload_handler = new UploadHandler($upload_options);
        
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Content-Disposition: inline; filename="files.json"');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: OPTIONS, HEAD, GET, POST, PUT, DELETE');
        header('Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size');

        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'OPTIONS':
                break;
            case 'HEAD':
            case 'GET':
                $upload_handler->get();
                break;
            case 'POST':
				$files = Arr::get($_FILES, 'files', array());
				$names = Arr::get($files, 'name', array());
				foreach ($names as $key => $name)
				{
					$name = urldecode($name);
					$name = str_replace(array(',', '?', '!', '"', "'", '|'), '', $name);
					$name = str_replace(array(' ', '(', ')', '-'), '_', $name);
					$name = str_replace('_.', '.', $name);
					$name = preg_replace('/[_]+/', '_', $name);
					$_FILES['files']['name'][$key] = $name;
				}
                if (isset($_REQUEST['_method']) && $_REQUEST['_method'] === 'DELETE') {
                    $upload_handler->delete();
                }
                else
                {
                    $upload_handler->post();
                }
                break;
            case 'DELETE':
                $upload_handler->delete();
                break;
            default:
                header('HTTP/1.1 405 Method Not Allowed');
        }
        die();
    }
    
    public function action_upload_complete()
    {
    	$id = $this->request->param('id');
		
        $url = urldecode(Arr::get($_GET, 'url'));
        $url_parts = pathinfo($url);
        $type = Arr::get($_GET, 'type');
        
        $asset_type = explode('/', $type);
        $asset_type = $asset_type[0];
		$img_extensions = array('jpg', 'png', 'gif','tiff', 'jpeg');
		
        if ($asset_type == 'application')
        {
            // $asset_type = explode('/', $type);
            // $asset_type = $asset_type[1];
			
			$asset_type = 'raw';
        }

		if ($asset_type == 'text' OR $asset_type == 'all')
        {
			$asset_type = 'raw';
        }
        
		if ($id !== null)
		{
			$asset = ORM::factory(ucfirst($this->model_name), $id);
			$replace = true;
		}
		else
		{
			$asset = ORM::factory(ucfirst($this->model_name));
			$asset->title = $url_parts['filename'];
	        $asset->type = $asset_type;
	        $asset->user_type = 'admin';
	        $asset->save();
			$replace = false;
		}
        
        $file = ORM::factory('File');
        $file->asset_id = $asset->id;
        $file->type = 'upload';
        $file->url = $url;
        $file->storage = 'local';
        $file->save();
		
		if ($asset->type == 'video')
		{
			if (in_array(strtolower($url_parts['extension']) , $img_extensions))
			{
				$asset->process_uploaded_asset($asset_type = 'image', $replace, $video_image_replace = true);
			}
			else
			{
				$asset->process_uploaded_asset($asset_type, $replace, $video_image_replace = false);
			}
		}
		else 
		{
			$asset->process_uploaded_asset($asset_type, $replace, $video_image_replace = false);
		}
		
        
        $return = array('asset_id' => $asset->id);
        echo json_encode($return);
        die();
    }
	
    public function action_search()
    {
        $type = Arr::get($_GET, 'type', 'video');
        $search_term = Arr::get($_GET, 'search_term', '');
        $selected_assets = Arr::get($_GET, 'selected_assets', '');
        
        $selected_assets_array = explode(',', $selected_assets);
        
        $assets = ORM::factory(ucfirst($this->model_name));
        $assets = $assets->where('type', '=', $type);
        $assets = $assets->where('title', 'LIKE', '%'.$search_term.'%');
        $assets = $assets->where('user_type', '=', 'admin');
        foreach ($selected_assets_array as $selected_asset)
        {
            $assets->where('id', '!=', $selected_asset);
        }
        $assets = $assets->limit(10)->find_all();
        foreach ($assets as $asset)
        {
            $asset_div = '<div class="asset" asset_id="'.$asset->id.'">';
            $asset_div.= '<div class="asset_handle pull-left hidden">';
            $asset_div.= '<div class="drag_handle">'.HTML::image('_media/admin/img/drag_vertical.png').'</div>';
            $asset_div.= '</div>';
            $asset_div.= '<div class="asset_image pull-left">';
            $asset_div.= HTML::image($asset->files->where('type', '=', 'image_tiny_square')->find()->url);
            $asset_div.= '</div>';
            $asset_div.= '<div class="asset_title pull-left">';
            $asset_div.= '<h4>'.$asset->title.'</h4>';
            $asset_div.= '</div>';
            $asset_div.= '<div class="asset_button pull-right">';
            $asset_div.= Form::button(NULL, 'Add '.ucfirst($asset->type), array('class' => 'add_'.$asset->type.' btn btn-primary'));
            $asset_div.= '</div>';
            $asset_div.= '</div>';
            
            echo $asset_div;
        }
        die();
    }
    
    public function action_get_assets()
    {
        $asset_ids = Arr::get($_GET, 'asset_ids', '');
        
        $asset_ids = explode(',', $asset_ids);
        
        if (count($asset_ids) > 0)
        {
            foreach ($asset_ids as $asset_id)
            {
                if ($asset_id != 0 AND $asset_id != '')
                {
                    $asset = ORM::factory(ucfirst($this->model_name))->where('id', '=', $asset_id)->find();
        
                    $asset_div = '<div class="asset" id="asset_'.$asset->id.'" asset_id="'.$asset->id.'">';
                    $asset_div.= '<div class="asset_handle pull-left">';
                    $asset_div.= '<div class="drag_handle">'.HTML::image('media/admin/img/drag_vertical.png').'</div>';
                    $asset_div.= '</div>';
                    $asset_div.= '<div class="asset_image pull-left">';
					$image = $asset->files->where('type', '=', 'image_tiny_square')->find()->url;
					if ($image != '')
					{
						$asset_div.= HTML::image($image);
					}
                    $asset_div.= '</div>';
                    $asset_div.= '<div class="asset_title pull-left">';
                    $asset_div.= '<h4>'.$asset->title.'</h4>';
                    $asset_div.= '</div>';
                    $asset_div.= '<div class="asset_button pull-right">';
                    $asset_div.= Form::button(NULL, 'Remove '.ucfirst($asset->type), array('class' => 'remove_'.$asset->type.' btn btn-danger'));
                    $asset_div.= '</div>';
                    $asset_div.= '</div>';
                    
                    echo $asset_div;
                }
            }
        }
        die();
    }
    
    private function database_search($model, $params)
    {
        $model_orm = ORM::factory(ucfirst($model));
        foreach ($params as $key => $value)
        {
            if ($value != '')
            {
            	if ($key == 'tag')
				{
					$model_orm->join('assets_tags');
                	$model_orm->on('assets_tags.asset_id', '=', 'asset.id');
                	$model_orm->join('tags');
                	$model_orm->on('tags.id', '=', 'assets_tags.tag_id');
					$model_orm->where('name', 'like', '%'.$value.'%');
				}
				else
				{
					$model_orm->where($key, 'like', '%'.$value.'%');
				}
            }
        }
        return $model_orm;
    }
    
    public function action_replace_image()
    {
        $replacement_file = Arr::get($_FILES, 'image_replacement', false);
        $asset_id = Arr::get($_POST, 'asset_id', false);
        
        if ($replacement_file AND $asset_id)
        {
            $asset = ORM::factory(ucfirst($this->model_name), $asset_id);
            
            $test_file = $asset->files->find();
            $path_parts = pathinfo($test_file->url);
            $filename_parts = explode('_', $path_parts['filename']);
            
            $filename_base = $filename_parts[0];
            
            $temporary_url = '_media/uploads/'.$filename_base.'.jpg';
            move_uploaded_file(Arr::get($replacement_file, 'tmp_name'), $temporary_url);
            
            $file = ORM::factory('File');
            $file->type = 'upload';
            $file->url = $temporary_url;
            $file->storage = 'local';
			$file->asset_id = $asset->id;
            $file->save();
            
            $asset_image_files = $asset->files->where('type', 'LIKE', 'image%')->find_all();
            foreach ($asset_image_files as $asset_image_file)
            {
                $asset_image_file->delete();
            }
            
            $asset->process_uploaded_asset('image');
        }
        
        Notice::add(Notice::SUCCESS, 'Asset image Saved.');
        $this->redirect('/admin_asset/edit/'.$asset->id);
    }

    public function action_import_vimeo()
    {
        $asset = ORM::factory(ucfirst($this->model_name));
        $asset->import_vimeo_videos();
        Notice::add(Notice::SUCCESS, 'Vimeo Library Updated.');
        $this->redirect('/admin_asset/vimeo_video');
    }
    public function action_update_vimeo_video()
    {
        $id = $this->request->param('id');
        $asset = ORM::factory(ucfirst($this->model_name));
        $asset->update_vimeo_video($id);
        Notice::add(Notice::SUCCESS, 'Video '.$id.' updated.');
        $this->redirect('/admin_asset/vimeo_video');
    }
	
	public function action_content_search()
    {
        $type = Arr::get($_GET, 'type', false);
        $search_term = Arr::get($_GET, 'search_term', '');
        $selected_assets = Arr::get($_GET, 'selected_assets', '');
		$page = Arr::get($_GET, 'page', 1);
		$page_limit = 10;
		$offset = $page_limit*($page-1);
        
        $selected_assets_array = explode(',', $selected_assets);
        
        $assets = ORM::factory(ucfirst($this->model_name));
		if ($type AND $type != 'all')
		{
			$assets = $assets->where('type', '=', $type);
		}
        $assets = $assets->where('title', 'LIKE', '%'.$search_term.'%');
        $assets = $assets->where('user_type', '=', 'admin');
        foreach ($selected_assets_array as $selected_asset)
        {
            $assets->where('id', '!=', $selected_asset);
        }
		$assets_count = clone $assets;
		$result_count = $assets_count->count_all();
		
		$assets = $assets->limit($page_limit)->offset($offset)->find_all();
		echo '<table class="table table-striped table-hover">';
		echo '<tbody>';
        foreach ($assets as $asset)
        {
			$ajax_asset = View::factory('asset/admin/ajax_asset');
			$ajax_asset->type = $type;
			$ajax_asset->action = 'add';
			$ajax_asset->asset = $asset;
			echo $ajax_asset;
        }
		echo '</tbody>';
		echo '</table>';
		
		$pagination = Pagination::factory(array(
			'items_per_page' => $page_limit,
			'total_items' => $result_count,
		));
		
		echo '<div class="ajax_pagination" asset_type="'.$type.'">';
		echo $pagination;
		echo '</div>';
        die();
    }
    
	public function action_content_get_assets()
    {
    	$type = Arr::get($_GET, 'type', false);
        $content_id = Arr::get($_GET, 'content_id', '');
        $asset_ids = Arr::get($_GET, 'asset_ids', '');
        $asset_ids = explode(',', $asset_ids);
        
		echo '<table class="table table-striped table-hover">';
		echo '<tbody>';
        if (count($asset_ids) > 0)
        {
            foreach ($asset_ids as $asset_id)
            {
                if ($asset_id != 0 AND $asset_id != '')
                {
                    $asset = ORM::factory(ucfirst($this->model_name))->where('id', '=', $asset_id)->find();
					
					$ajax_asset = View::factory('asset/admin/ajax_asset');
					$ajax_asset->type = $type;
					$ajax_asset->action = 'remove';
					$ajax_asset->asset = $asset;
					echo $ajax_asset;
                }
            }
        }
        echo '</tbody>';
		echo '</table>';
        die();
    }

	public function action_delete_multiple_assets()
	{
		$asset_ids =  Arr::get($_GET, 'assets');
		foreach ($asset_ids as $key => $value)
		{
			$asset = ORM::factory(ucfirst($this->model_name), $value);
	        $type = $asset->type;
	        $asset->remove_all_relations();
	        $asset->delete();
		}
		echo 'deleted';
		die;
	}
	
	public function action_tag_multiple_assets()
	{
		$asset_ids =  Arr::get($_GET, 'assets');
		$tags = Arr::get($_GET, 'tags', '');
		
		$tags = explode(',', $tags);
		$tag_model = new Model_Tag;
		foreach ($asset_ids as $key => $value)
		{
			$asset = ORM::factory(ucfirst($this->model_name), $value);
	        $tag_model->add_tags($tags, $asset);
		}
		echo 'tagged';
		die;
	}
	
	public function action_download_file()
	{
		$id = $this->request->param('id');
		
		$file = ORM::factory('File', $id);
		
		$download_filename = $file->asset->title;
		$download_filename.= ' - '.str_replace('_', ' ', $file->type);
		$download_filename.= '.'.pathinfo($file->url, PATHINFO_EXTENSION);
        
		if (strstr($file->storage, 's3') !== false)
		{
			$download_url = substr(parse_url($file->url, PHP_URL_PATH), 1);
			
			$bucket_name = Kohana::$config->load('amazon.media_bucket');
			
			require_once Kohana::find_file('vendor', 'amazon/sdk.class');
			$s3_credentials = Kohana::$config->load('amazon.credentials.development');
	        $s3 = new AmazonS3($s3_credentials);
			
			$time_out = 30;
			$opt = array(
				'response-content-disposition' => 'attachment; filename='.$download_filename,
				'response-content-type' => 'application/octet-stream',
			);
			$download_url = $s3->get_object_url($bucket_name, $download_url, time()+$time_out, $opt);
			$this->redirect($download_url);
		}
		elseif ($file->storage == 'local')
		{
			$download_url = substr($file->url, 1);
			header('Content-Type: application/csv');
			header('Content-Disposition: attachment; filename='.$download_filename);
			header('Pragma: no-cache');
			readfile($download_url);
		}
		die();
	}

	public function action_reprocess_images()
	{
		$assets = ORM::factory('Asset')->where('user_type', '=', 'admin')->where('id', '>', 718)->where('type', '=', 'image')->find_all();
		
		$asset_type = 'image';
		
		foreach ($assets as $asset)
		{
			$replace = true;
			$image = false;
			$url = '/_media/uploads/'.$asset->generate_unique_file_id().'.jpg';
			
			$raw_file = $asset->files->where('type', '=', 'image_raw')->find();
			
			try {
				if($raw_file->id == NULL) 
				{
					//blah
					$large_file = $asset->files->where('type', '=', 'image_large')->find();
					if( $large_file->id == NULL)
					{
						//suck it 
					}
					else
					{
						$image = file_get_contents($large_file->url);
					}
				}
				else
				{
					$image = file_get_contents($raw_file->url);
				}
			}
			catch(Exception $e) 
			{
				print $e->getMessage();
				continue;
			}
			if ($image)
			{
				file_put_contents('/var/www/html'.$url, $image);
			
		        $file = ORM::factory('File');
		        $file->type = 'upload';
				$file->asset_id = $asset->id;
		        $file->url = $url;
		        $file->storage = 'local';
		        $file->save();
				
		        $asset->process_uploaded_asset($asset_type, $replace);
				
				echo Debug::vars($asset->id);
				}
			
		}
		die();
	}

	public function action_reprocess_videos()
	{
		$assets = ORM::factory('Asset')->where('user_type', '=', 'admin')->where('type', '=', 'video')->limit(20)->find_all();
		
		$asset_type = 'video';
		
		foreach ($assets as $asset)
		{
			$replace = true;
			$video = false;
			
			$raw_file = $asset->files->where('type', '=', 'video_high_720')->find();
			$url = $raw_file->url;

			if($raw_file->id == NULL) 
			{
				continue;
			}
			
	        $file = ORM::factory('File');
	        $file->type = 'remote';
	        $file->url = $url;
	        $file->storage = 's3:savn-tv-video';
	        $file->save();
			$asset->add('files', $file);
	        $asset->process_uploaded_asset($asset_type, $replace);
			
			echo Debug::vars($asset->id);
			
			
		}
		die();
	}

    public function action_update_files_with_id()
	{
		$assets = ORM::factory('asset')->find_all();
		foreach($assets as $asset)
		{
			$files = $asset->files->find_all();
			foreach($files as $file)
			{
				$file->asset_id = $asset->id;
				$file->save();
			}
			
			
		}
		die;
	}
}
