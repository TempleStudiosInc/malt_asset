<?php defined('SYSPATH') or die('No direct access allowed.');

class Model_File extends ORM {
    protected $_belongs_to = array(
	    'asset' => array(
	        'model'   => 'Asset'
	    )
    );
	
	public function save(Validation $validation = NULL)
	{
		if ($this->id == 0)
		{
			$this->date_created = date('Y-m-d H:i:s');
		}
		
		if (strstr($this->storage, 's3') !== false)
		{
			require_once Kohana::find_file('vendor', 'amazon/sdk.class');
	        $s3_credentials = Kohana::$config->load('amazon.credentials.development');
	        $s3 = new AmazonS3($s3_credentials);
			
			$bucket = str_replace('s3:', '', $this->storage);
            $remote_filename = str_replace('http://'.$bucket.'.s3.amazonaws.com/', '', $this->url);
            $result = $s3->get_object_metadata($bucket, $remote_filename);
			$this->date_modified = date('Y-m-d H:i:s', strtotime($result['LastModified']));
			$this->size = $result['Size'];
			$this->key = $result['Key'];
		}
		elseif ($this->storage == 'local')
		{
			$this->date_modified = date('Y-m-d H:i:s', filemtime(substr($this->url, 1)));
			$this->size = filesize(substr($this->url, 1));
		}
		
		parent::save($validation);
	}
	
	public function delete()
	{
		$bucket = Kohana::$config->load('amazon.media_bucket');
		
	    if ($this->storage == 'local')
        {
            if (file_exists($this->url))
            {
                unlink($this->url);
            }
        }
        else
        {
            require_once Kohana::find_file('vendor', 'amazon/sdk.class');
	        $s3_credentials = Kohana::$config->load('amazon.credentials.development');
	        $s3 = new AmazonS3($s3_credentials);
			
            $bucket = str_replace('s3:', '', $this->storage);
            $remote_filename = str_replace('http://'.$bucket.'.s3.amazonaws.com/', '', $this->url);
            $result = $s3->delete_object($bucket, $remote_filename);
        }
		
		parent::delete();
	}
}