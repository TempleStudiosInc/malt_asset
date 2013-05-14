<?php
class Zencoder {
	public static function assign_vars($array, $filename, $id)
    {
        $video_bucket = Kohana::$config->load('amazon.video_bucket');
        
        $new_array = array();
        foreach ($array as $key => $value)
        {
            if (is_array($value))
            {
                $value = Zencoder::assign_vars($value, $filename, $id);
            }
            else
            {
                $value = str_replace('{{ASSET_ID}}', $id, $value);
                $value = str_replace('{{VIDEO_NAME}}', $filename, $value);
                $value = str_replace('{{SITE_URL}}', Kohana::$config->load('website.url'), $value);
                $value = str_replace('{{VIDEO_BUCKET}}', $video_bucket, $value);
            }
            $new_array[$key] = $value;
        }
        return $new_array;
    }
}
