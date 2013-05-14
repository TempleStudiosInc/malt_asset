<tr class="asset" id="asset_<?php echo $asset->id ?>" asset_id="<?php echo $asset->id ?>">
	<td style="width:130px;">
		<?php
			if ($action == 'remove')
			{
				$button_text = '<i class="icon-remove icon-white"></i> Remove '.ucfirst($asset->type);
				$button_attributes = array('class' => 'remove_'.$type.' btn btn-small btn-danger');
			}
			elseif ($action == 'add')
			{
				$button_text = '<i class="icon-plus icon-white"></i> Add '.ucfirst($asset->type);
				$button_attributes = array('class' => 'content_add_'.$type.' btn btn-small btn-primary');
			}
			
			echo Form::button(NULL, $button_text, $button_attributes);
		?>
	</td>
	<td>
		<div class="asset_image pull-left">
		<?php
            $image = $asset->files->where('type', '=', 'image_tiny_square')->find()->url;
			if ($image != '' AND $image != NULL)
			{
				echo HTML::image($image, array('class' => 'thumbnail'));
			}
		?>
        </div>
        <div class="asset_title pull-left"><?php echo $asset->title; ?></div>
		<div class="asset_file_type pull-left">
			<?php
				$extension = '';
				$file = $asset->files->where('type', '=', 'raw')->find();
				
				if ($file->id == 0)
				{
					$file = $asset->files->where('type', 'NOT LIKE', 'video_audio')->find();
				}
				
				$extension = pathinfo($file->url, PATHINFO_EXTENSION);
				$extension_type = 'file';
				switch(strtolower($extension))
				{
					case 'pdf':
					case 'doc':
					case 'docx':
						$extension_type = 'file-alt';
						break;
					case 'zip':
					case 'exe':
					case 'msi':
						$extension_type = 'desktop';
						break;
					case 'gif':
					case 'jpg':
					case 'jpeg':
					case 'png':
					case 'tiff':
					case 'bmp':
						$extension_type = 'picture';
						break;
					case 'wmv':
					case 'mov':
					case 'mp4':
					case 'avi':
						$extension_type = 'facetime-video';
						break;
					case 'mp3':
					case 'aif':
					case 'wav':
						$extension_type = 'music';
						break;
				}
				echo '<span class="label label-info" style="padding:5px;">';
				echo '<i class="icon-'.$extension_type.'"></i> &nbsp;';
				echo '<b>'.strtoupper($extension).'</b>';
				echo '</span>';
			?>
		</div>
	</td>
</tr>