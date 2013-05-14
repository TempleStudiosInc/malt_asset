<?php
class File extends Kohana_File {
	public static function find_extension($filename)
    {
        $filename_array = explode('.', $filename);
        $filename = $filename_array[count($filename_array)-1];
        return strtolower($filename);
    }
	
	public static function generate_unique_file_id()
    {
        $unique_id = uniqid();
        $random_string = substr($unique_id, strlen($unique_id)-8, 8);
        
        return $random_string;
    }
	
	public static function push_file_to_s3($local_filename, $remote_filename, $bucket)
    {
    	require_once Kohana::find_file('vendor', 'amazon/sdk.class');
        $s3_credentials = Kohana::$config->load('amazon.credentials.development');
        $s3 = new AmazonS3($s3_credentials);
		
        $options = array();
        $options['fileUpload'] = $local_filename;
        $options['acl'] = AmazonS3::ACL_PUBLIC;
        $result = $s3->create_object($bucket, $remote_filename, $options);
        
        if ($result->status == 200)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}