<?php
    echo HTML::style('_media/core/common/css/bootstrap-tagmanager.css');
	echo Html::style('_media/core/admin/css/asset.css');
	echo HTML::style('_media/core/common/css/bootstrap-image-gallery.min.css');
    echo HTML::style('_media/core/common/jquery_file_uploader/css/jquery.fileupload-ui.css');
?>
<div class="well">
    <div class="form medium_form">
        <?php
            echo Form::open('/admin_asset/save');
            echo Form::hidden('asset[id]', $asset->id);
        ?>
        <div class="form_field">
            <?php
				$image = $asset->files->where('type', '=', 'image_small_square')->find()->url;
				if ($image != '')
				{
					echo HTML::image($image, array('class' => 'thumbnail'));
				}
        	?>
        </div>
        <div class="form_field">
            <?php 
                echo Form::label('asset[title]', 'Title');
                echo Form::input('asset[title]', $asset->title, array('class' => 'span6'));
            ?>
        </div>
        <div class="form_field">
            <?php 
                echo Form::label('asset[description]', 'Description');
                echo Form::textarea('asset[description]', $asset->description, array('class' => 'span8'));
            ?>
        </div>
        <div class="form_field">
            <?php 
                echo Form::label('tags', 'Tags');
                echo Form::tags('tags', $asset->tags->find_all(), array('class' => 'span6'));
            ?>
        </div>
        <div class="buttons">
            <?php echo Form::button(NULL, 'Save', array('type' => 'submit', 'class' => 'btn btn-primary')); ?>
            or
            <?php echo HTML::anchor('/admin_asset/'.$asset->type, 'Cancel', array('class' => '')) ?>
        </div>
        <?php echo Form::close(); ?>
    </div>
</div>

<div class="well">
	<h3>Replacements</h3>
        <?php
        	$replacement_types = array('file');
			
			foreach ($replacement_types as $replacement_type)
			{
				if ($replacement_type == 'image' AND $asset->type != 'video')
    			{
    				continue;
				}
				echo '<div class="form medium_form">';
				echo Form::open('/admin_asset/handle_upload/'.$asset->id, array('class' => '', 'enctype' =>'multipart/form-data'));
	            echo Form::hidden('asset[id]', $asset->id);
				
				$name = 'replacement_'.$replacement_type;
				$type = $asset->type;
				$value = '';
				
				echo '<div class="form_field">';
	            echo '<div class="upload_file_upload">';
		    	echo '<div class="input-prepend">';
		    	echo Form::label($name.'_display', ucfirst($replacement_type).' Replacement');
		        echo Form::input($name.'_display', '', array('id' => $name.'_display', 'readonly' => 'readonly', 'class' => 'span4'));
				echo '<div class="btn btn-primary fileinput-button" id="'.$name.'_container">';
				echo '<i class="icon-plus icon-white"></i> File';
        		echo Form::file($name.'_fileupload', array('id' => $name.'_fileupload', 'targetinput' => $name, 'class' => 'fileupload', 'data-url' => '/admin_asset/handle_upload/'.$asset->id));
				echo Form::hidden($name, $value, array('id' => $name.'_content_selected_field_'.$type, 'class' => 'upload_input_field_'.$type));
		        echo '</div>';
		        echo Form::button(NULL, '<i class="icon-upload icon-white"></i> Upload', array('type' => 'button', 'class' => 'btn btn-primary upload_button', 'target' => $name.'_fileupload'));
		        echo '</div>';
				echo '<div class="loading" style="display:none;">'.HTML::image('_media/core/common/img/loader.gif').' Loading. Please wait.</div>';
		        echo '<div id="'.$name.'_progress">';
	            echo '<div class="bar" style="width: 0%;"></div>';
		        echo '</div>';
			    echo '</div>';
	        	echo '</div>';
    			echo Form::close();
				
				echo '</div>';
				
		?>
		<script>
		    var <?php echo $name ?>_data = false;
		    $(function () {
		        $('#<?php echo $name ?>_fileupload').fileupload({
		            replaceFileInput: false,
		            autoUpload: false,
		            dataType: 'json',
		            add: function(e, data) {
		                if ($('#<?php echo $name ?>_display').val() == '') {
		                    files_to_upload++;
		                }
		                <?php echo $name ?>_data = data;
		                $('#<?php echo $name ?>_display').val(data.files[0].name);
		            },
		            progressall: function (e, data) {
		                var progress = parseInt(data.loaded / data.total * 100, 10);
		                $('#<?php echo $name ?>_progress .bar').css('width', progress + '%');
		            },
		            success: function(result, textStatus, jqXHR) {
		                $.each(result, function (index, file) {
		                    $('<p/>').text(file.name+' upload complete.').appendTo($('#status_report'));
		                    $('<p/>').text(file.name+' processing.').appendTo($('#status_report'));
		                    
		                    $.ajax({
			                    url: '/admin_asset/upload_complete/<?php echo $asset->id ?>',
			                    data: { url: result[0]['url'], type: '<?php echo $type ?>'},
			                    dataType:'json',
			                    success:function(data, textStatus, jqXHR){
			                        var asset_id = data.asset_id;
			                        $('input[name="<?php echo $name ?>"]').val(asset_id);
			                        
			                        $.each(result, function (index, file) {
			                            $('<p/>').text(file.name+' processing complete.').appendTo($('#status_report'));
			                        });
			                        
			                        $('#<?php echo $name ?>_container').remove();
			                        $('.loading').hide();
			                        
			                        location.reload();
			                    }
			                });
		                });
		            }
		        });
		    });
		</script>
		<?php
			}
        ?>
</div>

<div class="well">
    <h3>Files</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Type</th>
                <th>Date Created</th>
                <th>Date Modified</th>
                <th>File Size</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            	foreach ($asset->files->find_all() as $file)
                {
                    $file_type = str_replace('_', ' ', $file->type);
                    $file_type = ucwords($file_type);
                    echo '<tr>';
                    echo '<td>'.$file_type.'</td>';
					echo '<td>';
					if ($file->date_created != '0000-00-00 00:00:00')
					{
						echo date('m/d/Y h:i A', strtotime($file->date_created));
					}
					echo '</td>';
					echo '<td>';
					if ($file->date_modified != '0000-00-00 00:00:00')
					{
						echo date('m/d/Y h:i A', strtotime($file->date_modified));
					}
					echo '</td>';
					echo '<td>'.Format::file_size($file->size).'</td>';
					echo '<td>';
					echo '<div class="btn-group">';
					echo HTML::anchor('admin_asset/download_file/'.$file->id, '<i class="icon-download-alt"></i> Download', array('class' => 'btn'));
					echo HTML::anchor($file->url, '<i class="icon-link"></i> Link', array('class' => 'btn', 'target' => '_blank'));
					echo '</div>';
					echo '</td>';
                    echo '</tr>';
                }
            ?>
        </tbody>
    </table>
</div>

<script>
	$(function () {
		$('.upload_button').click(function(event) {
			event.preventDefault();
			
			var target = $(this).attr('target');
			var target_input = $('#'+target).attr('targetinput');
			if (eval(target_input+'_data')) {
	            eval(target_input+'_data').submit();
	            $('.loading').show();
	        }
		})
	});
</script>
<?php
    echo HTML::script('_media/core/common/jquery_file_uploader/js/vendor/jquery.ui.widget.js');
    echo HTML::script('_media/core/common/jquery_file_uploader/js/jquery.iframe-transport.js');
    echo HTML::script('_media/core/common/js/load-image.min.js');
    echo HTML::script('_media/core/common/js/canvas-to-blob.min.js');
    echo HTML::script('_media/core/common/jquery_file_uploader/js/locale.js');  
?>