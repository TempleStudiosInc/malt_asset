<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Zencoder extends Controller_Website {
    
    public function action_test()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'http://timeofgracetest.com/zencoder/notification/26');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'input' => '{"output":{"height":480,"duration_in_ms":150100,"audio_bitrate_in_kbps":69,"video_bitrate_in_kbps":651,"url":"http://templestudiosbucket.s3.amazonaws.com/assets/26/ecc42be4_default.mp4","channels":"2","video_codec":"h264","frame_rate":30.0,"label":"default","thumbnails":[{"images":[{"url":"http://templestudiosbucket.s3.amazonaws.com/assets/26/ecc42be4.jpg","file_size_bytes":58699,"format":"JPG","dimensions":"1420x800"}],"label":null}],"width":852,"format":"mpeg4","file_size_in_bytes":13574823,"total_bitrate_in_kbps":720,"md5_checksum":null,"audio_codec":"aac","state":"finished","id":74447896,"audio_sample_rate":48000},"job":{"test":false,"submitted_at":"2013-02-05T19:34:38Z","pass_through":null,"updated_at":"2013-02-05T19:36:44Z","created_at":"2013-02-05T19:34:38Z","state":"finished","id":37815306},"input":{"height":720,"duration_in_ms":150050,"audio_bitrate_in_kbps":155,"video_bitrate_in_kbps":1271,"channels":"2","video_codec":"h264","frame_rate":29.96,"width":1280,"format":"mpeg4","file_size_in_bytes":26845272,"total_bitrate_in_kbps":1426,"md5_checksum":null,"state":"finished","audio_codec":"aac","id":37807918,"audio_sample_rate":48000}}'
        )); 
        $response = curl_exec($ch);
        
        echo $response;
        die();
    }
    
    public function action_notification()
    {
        $id = $this->request->param('id');
        $asset = ORM::factory('Asset', $id);
        
        require_once Kohana::find_file('vendor', 'amazon/sdk.class');
        $s3_credentials = Kohana::$config->load('amazon.credentials.development');
        $this->s3 = new AmazonS3($s3_credentials);
        
        $zencoder_config = Kohana::$config->load('zencoder');
        $zencoder_api_key = $zencoder_config->get('api_key');
        
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        $video_bucket = Kohana::$config->load('amazon.video_bucket');
        $image_sizes = Kohana::$config->load('amazon.image_sizes');
        
        require_once Kohana::find_file('vendor', 'zencoder/zencoder-php/Services/Zencoder');
        
        $zencoder = new Services_Zencoder($zencoder_api_key);
        
        $notification = $zencoder->notifications->parseIncoming();
        
        foreach ($notification->job->outputs as $output)
        {
            if ($output->state == 'finished')
            {
                $file = $asset->files->where('url', '=', $output->url)->find();
                $file->type = 'video_'.str_replace(' ', '_', $output->label);
                $file->url = $output->url;
                $file->storage = 's3:'.$video_bucket;
				$file->asset_id = $asset->id;
                $file->save();
                
                if (isset($output->thumbnails[0]))
                {
                    foreach ($output->thumbnails[0]->images as $thumbnail)
                    {
                        $thumbnail_url_path = pathinfo($thumbnail->url);
                        $temp_folder = '_media/uploads/temp/';
                        $short_filename = $thumbnail_url_path['filename'];
                        $filename_extension = $thumbnail_url_path['extension'];
                        $temp_image = $temp_folder.$short_filename.'.'.$filename_extension;
                        
                        file_put_contents($temp_image, file_get_contents($thumbnail->url));
                        
                        foreach ($image_sizes as $size_name => $width)
                        {
                            $save_base_filenamne = $short_filename.'_'.$size_name.'.'.$filename_extension;
                            $save_image_location = $temp_folder.$save_base_filenamne;
                            $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filenamne;
                            
                            $image = Image::factory($temp_image);
							$asset->image_processor($image, $size_name, $width, $save_image_location);
                            
                            $push_result = $this->push_file_to_s3($save_image_location, $remote_filename, $video_bucket);
                            if ($push_result)
                            {
                                $file = $asset->files->where('url', '=', 'http://'.$video_bucket.'.s3.amazonaws.com/'.$remote_filename)->find();
                                $file->type = 'image_'.$size_name;
                                $file->url = 'http://'.$video_bucket.'.s3.amazonaws.com/'.$remote_filename;
                                $file->storage = 's3:'.$video_bucket;
                                $file->asset_id = $asset->id;
                                $file->save();
                                
                                unlink($save_image_location);
                            }
                        }
                        unlink($temp_image);
                    }
                    
                    $upload_file = $asset->files->where('type', '=', 'upload')->find();
                    if ($upload_file->id > 0)
                    {
                        unlink(substr($upload_file->url, 1));
                        $upload_file->delete();
                    }
                }
            }
        }
        die();
    }

    public function action_notification_audio()
    {
        $id = $this->request->param('id');
        $asset = ORM::factory('Asset', $id);
        $this->memcache = Cache::instance();
        require_once Kohana::find_file('vendor', 'amazon/sdk.class');
        $s3_credentials = Kohana::$config->load('amazon.credentials.development');
        $this->s3 = new AmazonS3($s3_credentials);
        
        $zencoder_config = Kohana::$config->load('zencoder');
        $zencoder_api_key = $zencoder_config->get('api_key');
        
        $media_bucket = Kohana::$config->load('amazon.media_bucket');
        
        require_once Kohana::find_file('vendor', 'zencoder/zencoder-php/Services/Zencoder');
        
        $zencoder = new Services_Zencoder($zencoder_api_key);
        
        $notification = $zencoder->notifications->parseIncoming();
        
        foreach ($notification->job->outputs as $output)
        {
            if ($output->state == 'finished')
            {
                $local_file = $this->get_file_from_s3($output->url);
                $url_path_info = pathinfo($local_file);
                $save_base_filenamne = $url_path_info['basename'];
                $remote_filename = 'assets/'.$asset->id.'/'.$save_base_filenamne;
                
                $source_file = $asset->files->where('type', '=', 'upload')->find()->url;
                $source_file = substr($source_file, 1);
                $this->write_tags_to_mp3($source_file, $local_file);
                $this->push_file_to_s3($local_file, $remote_filename, $media_bucket, true);
                
                $file = $asset->files->where('url', '=', $output->url)->find();
                $file->type = 'audio_'.str_replace(' ', '_', $output->label);
                $file->url = $output->url;
                $file->storage = 's3:'.$media_bucket;
                $file->asset_id = $asset->id;
                $file->save();
            }
        }
        
        $low_audio = $asset->files->where('type', '=', 'audio_low_160')->find();
        $high_audio = $asset->files->where('type', '=', 'audio_high_320')->find();
        
        if ($low_audio->id > 0 AND $high_audio->id > 0)
        {
        	$genre_title = $this->get_genre_from_source($source_file);
			$genre = ORM::factory('Genre')->where('title', '=', $genre_title)->find();
			if ($genre->id == 0)
			{
				$genre->title = ucwords($genre_title);
				$genre->save();
			}
			$track_title = $this->get_title_from_source($source_file);
			
			$track = ORM::factory('Track');
			$track->asset_id = $asset->id;
			$track->genre_id = $genre->id;
			$track->title = $track_title;
			$track->save();
			$this->memcache->delete($genre->id.'_'.$genre->title.'_tracks');
            $upload_file = $asset->files->where('type', '=', 'upload')->find();
            if ($upload_file->id > 0)
            {
                unlink(substr($upload_file->url, 1));
                $upload_file->delete();
            }
        }
        die();
    }
    
    private function write_tags_to_mp3($source_file, $local_file)
    {
    	require_once Kohana::find_file('vendor', 'getid3/getid3');
		
		$imagetypes = array(1 => 'gif', 2 => 'jpeg', 3 => 'png');
        
        $id3 = new getID3;
        getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);
        $id3->setOption(array('encoding' => 'UTF-8'));
        $file_info = $id3->analyze($source_file);
        getid3_lib::CopyTagsToComments($file_info);
		
        $new_file_info = array();
        $new_file_info['artist'] = isset($file_info['comments_html']['artist'][0])?$file_info['comments_html']['artist'][0]:'';
        $new_file_info['genre'] = isset($file_info['comments_html']['genre'][0])?$file_info['comments_html']['genre'][0]:'';
        $new_file_info['title'] = isset($file_info['tags']['id3v2']['title'][0])?$file_info['tags']['id3v2']['title'][0]:'';
		$new_file_info['album'] = isset($file_info['tags']['id3v2']['album'][0])?$file_info['tags']['id3v2']['album'][0]:'';
		$new_file_info['year'] = isset($file_info['tags']['id3v2']['year'][0])?$file_info['tags']['id3v2']['year'][0]:'';
        $new_file_info['tags'] = isset($file_info['tags_html'])?$file_info['tags_html']:'';
		$album_art_data = isset($file_info['comments']['picture'][0]['data'])?$file_info['comments']['picture'][0]['data']:false;
		
		foreach ($new_file_info['tags']['id3v2']['comment'] as $comment)
		{
			if (strstr($comment, 'license') !== false)
			{
				$new_file_info['tags']['id3v2']['comment'] = array();
				$new_file_info['tags']['id3v2']['comment'][] = $comment;
				break;
			}
		}
		$new_file_info['genre'] = str_replace('&#8230;', '...', $new_file_info['genre']);
		$new_file_info['tags']['id3v2']['genre'] = str_replace('&#8230;', '...', $new_file_info['tags']['id3v2']['genre']);
		
        $tag_data = array();
		$tag_data['artist'][] = $new_file_info['artist'];
        $tag_data['title'][] = $new_file_info['title'];
        $tag_data['genre'][] = $new_file_info['genre'];
		$tag_data['album'][] = $new_file_info['album'];
		$tag_data['year'][] = $new_file_info['year'];
		$tag_data['comment'][] = $comment;
		
		if ($album_art_data)
		{
	        $temp_image_filename = '_media/uploads/temp/temp_album_art_'.rand(1,9999).'.png';
			$fp = fopen($temp_image_filename, 'w+');
			fwrite($fp, $album_art_data);
			fclose($fp);
			
			list($APIC_width, $APIC_height, $APIC_imageTypeID) = GetImageSize($temp_image_filename);
			
			if (isset($imagetypes[$APIC_imageTypeID]))
			{
				$tag_data['attached_picture'][0]['data'] = $album_art_data;
				$tag_data['attached_picture'][0]['picturetypeid'] = 0;
				$tag_data['attached_picture'][0]['description'] = '';
				$tag_data['attached_picture'][0]['mime'] = 'image/'.$imagetypes[$APIC_imageTypeID];
			}
		}
        
        $id3_tagwriter = new getid3_writetags;
        $id3_tagwriter->filename = $local_file;
        $id3_tagwriter->tagformats = array('id3v2.3');
        // $id3_tagwriter->overwrite_tags = false;
        $id3_tagwriter->remove_other_tags = true;
        $id3_tagwriter->tag_encoding = 'UTF-8';
        $id3_tagwriter->tag_data = $tag_data;
        
        $result = $id3_tagwriter->WriteTags();
		
		if ( ! $result)
		{
			echo 'Failed to write tags!<BLOCKQUOTE STYLE="background-color:#FF9999; padding: 10px;">'.implode('<BR><BR>', $id3_tagwriter->errors).'</BLOCKQUOTE>';
		}
    }

	private function get_genre_from_source($source_file)
	{
		require_once Kohana::find_file('vendor', 'getid3/getid3');
        
        $id3 = new getID3;
        $id3->setOption(array('encoding' => 'UTF-8'));
        $file_info = $id3->analyze($source_file);
        getid3_lib::CopyTagsToComments($file_info);
		$genre = Arr::get($file_info['comments_html']['genre'], 0, '');
		
		return $genre;
	}

	private function get_title_from_source($source_file)
	{
		require_once Kohana::find_file('vendor', 'getid3/getid3');
        
        $id3 = new getID3;
        $id3->setOption(array('encoding' => 'UTF-8'));
        $file_info = $id3->analyze($source_file);
        getid3_lib::CopyTagsToComments($file_info);
		$title = Arr::get($file_info['tags']['id3v2']['title'], 0, '');
		
		return $title;
	}

    private function push_file_to_s3($local_filename, $remote_filename, $bucket, $delete = false)
    {
        $options = array();
        $options['fileUpload'] = $local_filename;
        $options['acl'] = AmazonS3::ACL_PUBLIC;
        $result = $this->s3->create_object($bucket, $remote_filename, $options);
        
        if ($result->status == 200)
        {
        	if ($delete == true)
			{
				unlink($local_filename);
			}
            return true;
        }
        else
        {
            return false;
        }
    }
    
    private function get_file_from_s3($url)
    {
        $url_path_info = pathinfo($url);
        $local_file_name = $url_path_info['basename'];
        $local_file_name = '_media/uploads/temp/'.$local_file_name;
        $local_file = fopen ($local_file_name, 'w+');
        
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_BINARYTRANSFER => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FILE           => $local_file,
            CURLOPT_TIMEOUT        => 50,
            CURLOPT_USERAGENT      => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)'
        ));
        $results = curl_exec($ch);
        
        return $local_file_name;
    }
}   