<?php defined('SYSPATH') or die('No direct access allowed.');

class Model_Core_Asset extends ORM {
    public $s3 = false;
    
    protected $_has_many = array(
        'files' => array(
            'model'   => 'File'
        ),
        'categories' => array(
            'model'   => 'Category',
            'through' => 'assets_categories',
        ),
        'tags' => array(
            'model'   => 'Tag',
            'through' => 'assets_tags',
        ),
    );
    
    public function __construct($id = NULL)
    {
        parent::__construct($id);
		require_once Kohana::find_file('vendor', 'zencoder/zencoder-php/Services/Zencoder');
        $this->s3 = false;
    }
	
	private function setup_s3_connection()
	{
		if (! $this->s3)
		{
			require_once Kohana::find_file('vendor', 'amazon/sdk.class');
			require_once Kohana::find_file('vendor', 'zencoder/zencoder-php/Services/Zencoder');
	        $s3_credentials = Kohana::$config->load('amazon.credentials.development');
	        $this->s3 = new AmazonS3($s3_credentials);
		}
	}
    
    public function remove_all_relations()
    {
    	$this->setup_s3_connection();
		
        $files = $this->files->find_all();
        
        foreach ($files as $file)
        {
            if ($file->storage == 'local')
            {
                if (file_exists($file->url))
                {
                    unlink($file->url);
                }
            }
            else
            {
                $file_url_array = parse_url($file->url);
                $remote_file_path = substr($file_url_array['path'], 1);
                $bucket = str_replace('s3:', '', $file->storage);
                
                $this->s3->delete_object($bucket, $remote_file_path);
            }
            $file->delete();
        }
        
        $categories = $this->categories->find_all();
        foreach ($categories as $category)
        {
            $this->remove('categories', $category);
        }
        
        $tags = $this->tags->find_all();
        foreach ($tags as $tag)
        {
            $this->remove('tags', $tag);
        }
    }

    public function process_uploaded_asset($type, $replace = false, $video_image_replacement = false)
    {
    	if ($replace == false OR $this->short_key == '')
		{
			$short_filename = File::generate_unique_file_id();
			$this->short_key = $short_filename;
			$this->save();
		}
		elseif ($replace == true)
		{
			if ($video_image_replacement)
			{
				$files = $this->files->where('type', '!=', 'upload')
									 ->where('type', '!=', 'video_medium_480')
									 ->where('type', '!=', 'video_small_320')
									 ->where('type', '!=', 'video_high_720')
									 ->find_all();
									 
				foreach($files as $file)
				{
					$file->delete();
				}
			}
			else 
			{
				$files = $this->files->where('type', '!=', 'upload')->find_all();
				foreach($files as $file)
				{
					$file->delete();
				}
			}
		}
		
        switch ($type)
        {
            case 'image':
                $this->process_image($this, $replace);
                break;
            case 'video':
                $this->process_video($this, $replace);
                break;
            case 'pdf':
                $this->process_pdf($this, $replace);
                break;
            case 'raw':
                $this->process_raw($this, $replace);
                break;
			case 'audio':
				$this->process_audio($this, $replace);
                break;
        }
    }

    private function process_image($asset, $replace = false)
    {
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        
        foreach ($asset->files->find_all() as $file)
        {
            if ($file->type == 'upload')
            {
                $upload_file = $file;
                if (file_exists($file->url))
                {
                    $filename = $file->url;
                }
                else
                {
                    $filename = substr($file->url, 1);
                }
                break;
            }
        }
        
        $image_sizes = Kohana::$config->load('amazon.image_sizes');
        
        $temp_folder = '_media/uploads/temp/';
		$short_filename = $asset->short_key;
        
        $size = getimagesize($filename);
        $width = $size[0];
        
        $filename_extension = File::find_extension($filename);
        
        foreach ($image_sizes as $size_name => $width)
        {
            $save_base_filename = $short_filename.'_'.$size_name.'.'.$filename_extension;
            $save_image_location = $temp_folder.$save_base_filename;
            $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filename;
            
            $image = Image::factory($filename);
			$this->image_processor($image, $size_name, $width, $save_image_location);
			
            $push_result = File::push_file_to_s3($save_image_location, $remote_filename, $media_bucket);
            if ($push_result)
            {
                $file = $asset->files->where('url', '=', 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
                $file->asset_id = $asset->id;
                $file->type = 'image_'.$size_name;
                $file->url = 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename;
                $file->storage = 's3:'.$media_bucket;
                $file->save();
                unlink($save_image_location);
            }
        }
        unlink($filename);
        $upload_file->delete();
    }

    private function process_video($asset)
    {
        $zencoder_config = Kohana::$config->load('zencoder');
        $zencoder_api_key = $zencoder_config->get('api_key');
        
        $zencoder = new Services_Zencoder($zencoder_api_key);
       
        foreach ($asset->files->find_all() as $file)
        {  
            if ($file->type == 'upload')
            {
                $upload_file = $file;
                $input = 'http://'.Kohana::$config->load('website.url').'/'.substr($file->url, 1);
                break;
            }
            if ($file->type == 'remote')
            {
                $input = $file->url;
                break;
            }                   
        }
        
		if ( isset($input))
		{
			$job = array(
	            'input' => $input,
	            'outputs' => $zencoder_config->get('output')
        	);
        
	        $short_filename = $asset->short_key;
	        
	        $job['outputs'] = Zencoder::assign_vars($job['outputs'], $short_filename, $this->id);
	        
	        $encoding_job = $zencoder->jobs->create($job);
		}
        
    }
    
    public function import_vimeo_videos()
    {
    	$this->setup_s3_connection();
		
        require_once Kohana::find_file('vendor', 'vimeo/vimeo', 'php');
        $vimeo_username = Kohana::$config->load('vimeo.username');
        $image_sizes = Kohana::$config->load('amazon.image_sizes');
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        $video_bucket = Kohana::$config->load('amazon.video_bucket');
        $vimeolib = new phpVimeo(Kohana::$config->load('vimeo.consumer_key'),Kohana::$config->load('vimeo.consumer_secret'));
        $user_info = $vimeolib->call('vimeo.people.getInfo', array('user_id' => $vimeo_username));
        $video_count = $user_info->person->number_of_videos;
        $total_pages = ceil(intval($video_count) / 50);
        
        $count = 1;
        while ($count <= $total_pages)
        {
            $videos = $vimeolib->call('vimeo.videos.getUploaded', array('user_id' => $vimeo_username, 'sort' => 'newest','page' => $count,'full_response' => true));
                
            if ($videos)
            {
                foreach($videos->videos->video as $video)
                {
                    $found_asset = ORM::factory('Asset')->where('remote_id', '=', $video->id)->find();
                    if ($found_asset)
                    {
                        if ($video->modified_date != $found_asset->date_modified)
                        {
                            $asset = $found_asset;
                            $files = $asset->files->find_all();
        
                            foreach ($files as $file)
                            {
                                if ($file->storage == 'local')
                                {
                                    if (file_exists($file->url))
                                    {
                                        unlink($file->url);
                                    }
                                }
                                else
                                {
                                    $file_url_array = parse_url($file->url);
                                    $remote_file_path = substr($file_url_array['path'], 1);
                                    $bucket = str_replace('s3:', '', $file->storage);
                                    
                                    $this->s3->delete_object($bucket, $remote_file_path);
                                }
                                $file->delete();
                            }
                        }
                        else
                        {
                            continue;
                        }                       
                    }
                    else
                    {
                        $asset = ORM::factory('Asset');
                    }
                                        
                    $asset->title = $video->title;
                    $asset->description = $video->description;
                    $asset->remote_id = $video->id;
                    $asset->date_modified = $video->modified_date;
                    $asset->type = 'vimeo';
                    $asset->user_type = 'admin';
                    $asset->save();
                    
                    $thumbnail_url_path = pathinfo($video->thumbnails->thumbnail[2]->_content);
                    
                    $temp_folder = '_media/uploads/temp/';
                    $short_filename = $thumbnail_url_path['filename'];
                    $filename_extension = $thumbnail_url_path['extension'];
                    $temp_image = $temp_folder.$short_filename.'.'.$filename_extension;
                    
                    file_put_contents($temp_image, file_get_contents($video->thumbnails->thumbnail[2]->_content));
                    
                    foreach ($image_sizes as $size_name => $width)
                    {
                        $save_base_filenamne = $short_filename.'_'.$size_name.'.'.$filename_extension;
                        $save_image_location = $temp_folder.$save_base_filenamne;
                        $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filenamne;
                        
                        $image = Image::factory($temp_image);
						$this->image_processor($image, $size_name, $width, $save_image_location);
                        
                        $push_result = File::push_file_to_s3($save_image_location, $remote_filename, $video_bucket);
                        if ($push_result)
                        {
                            $file = $asset->files->where('url', '=', 'http://'.$video_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
                            $file->asset_id = $asset->id;
                            $file->type = 'image_'.$size_name;
                            $file->url = 'http://'.$video_bucket.'.s3.amazonaws.com/'.$remote_filename;
                            $file->storage = 's3:'.$video_bucket;
                            $file->save();
                            unlink($save_image_location);
                        }
                    }
                    unlink($temp_image);
                    
                    
                    $file = ORM::factory('File');
                    $file->asset_id = $asset->id;
                    $file->type = 'vimeo_video';
                    $file->url = 'http://player.vimeo.com/video/'.$video->id;
                    $file->storage = 'vimeo';
                    $file->save();
                }
            }
            $count ++;
        }
        
    }

    public function update_vimeo_video($id)
    {
    	$this->setup_s3_connection();
		
        require_once Kohana::find_file('vendor', 'vimeo/vimeo', 'php');
        $vimeo_username = Kohana::$config->load('vimeo.username');
        $image_sizes = Kohana::$config->load('amazon.image_sizes');
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        $video_bucket = Kohana::$config->load('amazon.video_bucket');
        $vimeolib = new phpVimeo(Kohana::$config->load('vimeo.consumer_key'),Kohana::$config->load('vimeo.consumer_secret'));
        
        $vimeo_video = $vimeolib->call('vimeo.videos.getInfo', array('video_id' => $id));
        $video = $vimeo_video->video[0];
        
        $asset = ORM::factory('Asset')->where('remote_id', '=', $id)->find();
        $files = $asset->files->find_all();
        
        foreach ($files as $file)
        {
            if ($file->storage == 'local')
            {
                if (file_exists($file->url))
                {
                    unlink($file->url);
                }
            }
            else
            {
                $file_url_array = parse_url($file->url);
                $remote_file_path = substr($file_url_array['path'], 1);
                $bucket = str_replace('s3:', '', $file->storage);
                
                $this->s3->delete_object($bucket, $remote_file_path);
            }
            $file->delete();
        }
                                
        $asset->title = $video->title;
        $asset->description = $video->description;
        $asset->remote_id = $video->id;
        $asset->date_modified = $video->modified_date;
        $asset->type = 'vimeo';
        $asset->user_type = 'admin';
        $asset->save();
        
        $thumbnail_url_path = pathinfo($video->thumbnails->thumbnail[2]->_content);
        
        $temp_folder = '_media/uploads/temp/';
        $short_filename = $thumbnail_url_path['filename'];
        $filename_extension = $thumbnail_url_path['extension'];
        $temp_image = $temp_folder.$short_filename.'.'.$filename_extension;
        
        file_put_contents($temp_image, file_get_contents($video->thumbnails->thumbnail[2]->_content));
        
        foreach ($image_sizes as $size_name => $width)
        {
            $save_base_filenamne = $short_filename.'_'.$size_name.'.'.$filename_extension;
            $save_image_location = $temp_folder.$save_base_filenamne;
            $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filenamne;
            
            $image = Image::factory($temp_image);
            $this->image_processor($image, $size_name, $width, $save_image_location);
            
            $push_result = File::push_file_to_s3($save_image_location, $remote_filename, $video_bucket);
            if ($push_result)
            {
                $file = $asset->files->where('url', '=', 'http://'.$video_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
                $file->asset_id = $asset->id;
                $file->type = 'image_'.$size_name;
                $file->url = 'http://'.$video_bucket.'.s3.amazonaws.com/'.$remote_filename;
                $file->storage = 's3:'.$video_bucket;
                $file->save();
                unlink($save_image_location);
            }
        }
        unlink($temp_image);
                
        $file = ORM::factory('File');
        $file->asset_id = $asset->id;
        $file->type = 'vimeo_video';
        $file->url = 'http://player.vimeo.com/video/'.$video->id;
        $file->storage = 'vimeo';
        $file->save();
    }
    
    private function process_pdf($asset)
    {
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        
        foreach ($asset->files->find_all() as $file)
        {
            if ($file->type == 'upload')
            {
                $upload_file = $file;
                if (file_exists($file->url))
                {
                    $filename = $file->url;
                }
                else
                {
                    $filename = substr($file->url, 1);
                }
                break;
            }
        }
        
        $image_sizes = Kohana::$config->load('amazon.image_sizes');
        
        $temp_folder = '_media/uploads/temp/';
        $short_filename = $asset->short_key;
        
        $image_filename = '_media/core/common/img/pdf_document_image.jpg';
        $filename_extension = 'jpg';
        
        foreach ($image_sizes as $size_name => $width)
        {
            $save_base_filename = $short_filename.'_'.$size_name.'.'.$filename_extension;
            $save_image_location = $temp_folder.$save_base_filename;
            $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filename;
            
            $image = Image::factory($image_filename);
            $this->image_processor($image, $size_name, $width, $save_image_location);
            
            $push_result = File::push_file_to_s3($save_image_location, $remote_filename, $media_bucket);
            if ($push_result)
            {
                $file = $asset->files->where('url', '=', 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
                $file->asset_id = $asset->id;
                $file->type = 'image_'.$size_name;
                $file->url = 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename;
                $file->storage = 's3:'.$media_bucket;
                $file->save();
                
                unlink($save_image_location);
            }
        }
        
        $save_base_filename = $short_filename.'.pdf';
        $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filename;
        $push_result = File::push_file_to_s3($filename, $remote_filename, $media_bucket);
       
        $file = $asset->files->where('url', '=', 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
        $file->asset_id = $asset->id;
        $file->type = 'document';
        $file->url = 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename;
        $file->storage = 's3:'.$media_bucket;
        $file->save();
        unlink($filename);
        $upload_file->delete();
    }
    
    private function process_raw($asset)
    {
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        
        foreach ($asset->files->find_all() as $file)
        {
            if ($file->type == 'upload')
            {
                $upload_file = $file;
                if (file_exists($file->url))
                {
                    $filename = $file->url;
                }
                else
                {
                    $filename = substr($file->url, 1);
                }
                break;
            }
        }
        
        $upload_file_pathinfo = pathinfo($filename);
        $upload_file_extension = $upload_file_pathinfo['extension'];
        
        $image_sizes = Kohana::$config->load('amazon.image_sizes');
        
        $temp_folder = '_media/uploads/temp/';
        $short_filename = $asset->short_key;
        
        $image_filename = '_media/core/common/img/file_image.jpg';
        $filename_extension = 'jpg';
        
        foreach ($image_sizes as $size_name => $width)
        {
            $save_base_filename = $short_filename.'_'.$size_name.'.'.$filename_extension;
            $save_image_location = $temp_folder.$save_base_filename;
            $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filename;
            
            $image = Image::factory($image_filename);
            $this->image_processor($image, $size_name, $width, $save_image_location);
            
            $push_result = File::push_file_to_s3($save_image_location, $remote_filename, $media_bucket);
            if ($push_result)
            {
                $file = $asset->files->where('url', '=', 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
                $file->asset_id = $asset->id;
                $file->type = 'image_'.$size_name;
                $file->url = 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename;
                $file->storage = 's3:'.$media_bucket;
                $file->save();
                
                unlink($save_image_location);
            }
        }
        
        $save_base_filename = $short_filename.'.'.$upload_file_extension;
        $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filename;
        $push_result = File::push_file_to_s3($filename, $remote_filename, $media_bucket);
        
        $file = $asset->files->where('url', '=', 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
        $file->asset_id = $asset->id;
        $file->type = 'raw';
        $file->url = 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename;
        $file->storage = 's3:'.$media_bucket;
        $file->save();
        unlink($filename);
        $upload_file->delete();
    }

	private function process_audio($asset)
    {
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        
        foreach ($asset->files->find_all() as $file)
        {
            if ($file->type == 'upload')
            {
                $upload_file = $file;
                if (file_exists($file->url))
                {
                    $filename = $file->url;
                }
                else
                {
                    $filename = substr($file->url, 1);
                }
                break;
            }
        }
        
        $upload_file_pathinfo = pathinfo($filename);
        $upload_file_extension = $upload_file_pathinfo['extension'];
        
        $temp_folder = '_media/uploads/temp/';
        $short_filename = $asset->short_key;
		
        $save_base_filename = $short_filename.'.'.$upload_file_extension;
        $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filename;
        $push_result = File::push_file_to_s3($filename, $remote_filename, $media_bucket);
        
        $file = $asset->files->where('url', '=', 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
        $file->asset_id = $asset->id;
        $file->type = 'raw';
        $file->url = 'http://'.$media_bucket.'.s3.amazonaws.com/'.$remote_filename;
        $file->storage = 's3:'.$media_bucket;
        $file->save();
        
        $zencoder_config = Kohana::$config->load('zencoder_audio');
        $zencoder_api_key = $zencoder_config->get('api_key');
        
        $zencoder = new Services_Zencoder($zencoder_api_key);
        
        foreach ($asset->files->find_all() as $file)
        {   
            if ($file->type == 'upload')
            {
                $upload_file = $file;
                $input = 'http://'.Kohana::$config->load('website.url').'/'.substr($file->url, 1);
                break;
            }
            if ($file->type == 'remote')
            {
                $input = $file->url;
                break;
            }                   
        }
        
        $job = array(
            'input' => $input,
            'outputs' => $zencoder_config->get('output')
        );
        
        $short_filename = File::generate_unique_file_id();
        
        $job['outputs'] = Zencoder::assign_vars($job['outputs'], $short_filename);
        
        $encoding_job = $zencoder->jobs->create($job);
    }
    
	public function get_image_value($size = null)
	{
		if ($size != null)
        {
            $image_url = $this->files->where('type', '=', 'image_'.$size)->find()->url;
        }
        else
        {
            $image_url = $this->files->where('type', '=', 'image_medium')->find()->url;
        }
		return $image_url;
	}
	
	public function save(Validation $validation = NULL)
	{
		if ($this->id == 0)
		{
			$this->date_created = date('Y-m-d H:i:s');
		}
		$this->date_modified = date('Y-m-d H:i:s');
		parent::save($validation);
	}
	
	public function image_processor($image, $size_name, $width, $save_image_location)
	{
		if (strstr($size_name, 'wide') !== false)
		{
			$image->resize($width, ($width/16)*9, Image::INVERSE);
			$image->crop($width, ($width/16)*9);
		}
		elseif (strstr($size_name, 'square') !== false)
		{
			$image->resize($width, $width, Image::INVERSE);
			$image->crop($width, $width);
			$image->resize($width, $width, Image::WIDTH);
		}
		elseif($size_name == 'raw')
		{
			// Do nothing
		}
		else
		{
			$image->resize($width, null, Image::WIDTH);
		}
		$image->save($save_image_location, 75);
	}
	
	public function generate_unique_file_id()
    {
        $unique_id = uniqid();
        $random_string = substr($unique_id, strlen($unique_id)-8, 8);
        
        return $random_string;
    }
	
	
}