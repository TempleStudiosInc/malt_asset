<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Customer_Asset extends Controller_Website {

    public function before()
    {
        $this->page_title = '';
        $this->model_name = 'asset';
        
        $request = Request::initial();
        $requested_action = $request->action();
        parent::before();
    }
    
    public function action_get_url_for_video()
	{
		 $id = Arr::get($_GET, 'id');
		 $asset = ORM::factory('Asset', $id);
		 $high_url = $asset->files->where('type', '=', 'video_high_720')->find()->url;
		 $med_url = $asset->files->where('type', '=', 'video_medium_480')->find()->url;
		 $low_url = $asset->files->where('type', '=', 'video_small_320')->find()->url;
		 $image = $asset->files->where('type', '=', 'image_medium')->find()->url;
		 echo json_encode(array('high_url'=> $high_url, 'med_url'=> $med_url, 'low_url' => $low_url,'image' => $image));
		 die;
	}
} // End Index
