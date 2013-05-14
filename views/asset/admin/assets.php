<?php
	echo HTML::style('_media/core/common/css/bootstrap-tagmanager.css');
?>
<div class="well">
	<div class="form medium_form">
		<?php
			echo Form::open('/admin_asset/', array('method' => 'get'));
		?>
		<div class="form_field">
			<?php 
				echo Form::label('form[title]', 'Title');
				echo Form::input('form[title]', $search['title']);
			?>
		</div>
		<div class="form_field">
			<?php 
				echo Form::label('form[type]', 'Type');
				echo Form::select('form[type]', $types, $search['type']);
			?>
		</div>
		<div class="buttons">
			<?php echo Form::button(NULL, 'Search', array('type' => 'submit', 'class' => 'btn btn-primary btn-small')); ?>
		</div>
		<?php
            echo Form::close();
        ?>
	</div>
	<div><?php echo $pagination; ?></div>
    <div class="navbar">
		<div class="navbar-inner">
			<ul class="nav">
				<li style="margin-top: 6px; margin-right: 15px;">
    	<?php 
    		echo Form::checkbox('mark_all', 1, false, array('id' => 'check_all', 'class' => 'tooltip_item', 'style' => '', 'rel' => 'tooltip', 'title' => 'Check All'));
		?>
				</li>
				<li style="margin-right: 15px;">
					<div class="navbar-form">
						<div class="input-append">
		<?php
			echo Form::tags('tags', array(), array('class' => ''));
			echo HTML::anchor('#', 'Tag Checked', array('class' => 'btn btn-small add-on', 'id' => 'tag_selected_btn'));
		?>
						</div>
					</div>
				</li>
				<li style="margin-right: 15px;">
					<div>
		<?php
			echo HTML::anchor('#', 'Delete Checked', array('class' => 'btn btn-danger', 'id' => 'delete_selected_btn'));
    	?>
    				</div>
				</li>
	    		<li style="margin-right: 15px;">
			    	<div class="loading " style="display:none; line-height:40px;">
			    		<?php echo HTML::image('media/common/img/loader.gif') ?> Loading. Please wait.
					</div>
	    		</li>
	    		<li style="margin-right: 15px;">
					<div><?php echo $view_all_button; ?></div>
				</li>
	    	</ul>
    	</div>
    </div>
	<table class="table table-striped" id="assets_table">
		<thead>
			<tr>
				<th>&nbsp;</th>
				<th>Preview</th>
				<th><?php echo Format::create_sort_link('Title', 'title', $query_array, '/admin_asset/') ?></th>
                <th><?php echo Format::create_sort_link('Date Created', 'date_created', $query_array, '/admin_asset/') ?></th>
                <th><?php echo Format::create_sort_link('Date Modified', 'date_modified', $query_array, '/admin_asset/') ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
				foreach ($assets as $asset)
				{
					echo '<tr class="table_row '.Text::alternate('odd', 'even').'">';
					echo '<td>'.Form::checkbox('asset_id', $asset->id, false, array('class' => 'asset_selected')).'</td>';
					echo '<td>';
					$image = '';
					$tiny_image = $asset->files->where('type', '=', 'image_tiny_square')->find()->url;
					if ($tiny_image != '')
					{
						$image = HTML::image($tiny_image, array('class' => 'thumbnail'));
					}
					else
					{
						$image = '<div class="thumbnail" style="width: 50px; color:#666; font-size: 25px; text-align: center;"><i class="icon-picture"></i></div>';
					}
					echo HTML::anchor('/admin_asset/edit/'.$asset->id, $image);
					echo '</td>';
					echo '<td>'.Text::limit_chars($asset->title, 50).'</td>';
					echo '<td>';
					if ($asset->date_created != '0000-00-00 00:00:00')
					{
						echo date('m/d/Y h:i A', strtotime($asset->date_created));
					}
					echo '</td>';
					echo '<td>';
					if ($asset->date_modified != '0000-00-00 00:00:00')
					{
						echo date('m/d/Y h:i A', strtotime($asset->date_modified));
					}
					echo '</td>';
					echo '<td style="text-align:right;" class="btn-group">';
					echo HTML::anchor('/admin_asset/edit/'.$asset->id, 'Edit', array('alt' => 'Edit', 'class' => 'btn btn-small'));
					echo HTML::anchor('/admin_asset/delete/'.$asset->id.'?'.http_build_query($query_array), 'Delete', array('alt' => 'Delete', 'class' => 'delete btn btn-small btn-danger'));
					echo '</td>';
					echo '</tr>';
				}
			?>
		</tbody>
	</table>
</div>

<div class="modal hide dialog" id="delete_dialog">
  <div class="modal-header">
    <a class="close" data-dismiss="modal">×</a>
    <h3>Confirmation Required</h3>
  </div>
  <div class="modal-body">
    <p>Are you sure you want to delete this?</p>
  </div>
  <div class="modal-footer">
    <a href="#" class="btn modal_hide">No</a>
    <a href="#" class="btn btn-primary modal_delete_yes_button">Yes</a>
  </div>
</div>