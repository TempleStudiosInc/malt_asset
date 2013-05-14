<div class="well sidebar-nav">
    <ul class="nav nav-list">
        <li class="nav-header"><?php echo $requested_controller ?></li>
        <ul class="nav nav-list">
            <li class="nav-header">Media Types</li>
            <ul class="nav nav-list">
            	<li><?php echo HTML::anchor('/admin_asset/', 'All Types') ?></li>
	            <li><?php echo HTML::anchor('/admin_asset/?form[type]=video', 'Video') ?></li>
	            <li><?php echo HTML::anchor('/admin_asset/?form[type]=image', 'Image') ?></li>
	            <li><?php echo HTML::anchor('/admin_asset/?form[user_type]=user', 'User Images') ?></li>
	            <li><?php echo HTML::anchor('/admin_asset/?form[type]=audio', 'Audio') ?></li>
	            <li><?php echo HTML::anchor('/admin_asset/?form[type]=raw', 'Raw') ?></li>
            </ul>
        </ul>
        <li class="divider"></li>
        <ul class="nav nav-list">
            <li class="nav-header">Tags</li>
            <ul class="nav nav-list">
            	<?php
            		foreach ($tags as $tag)
					{
						echo '<li>';
						echo HTML::anchor('/admin_asset/?form[tag]='.$tag, $tag);
						echo '</li>';
					}
            	?>
            </ul>
        </ul>
        <li class="divider"></li>
        <li><?php echo HTML::anchor('/admin_asset/upload', 'Upload') ?></li>
    </ul>
</div><!--/.well -->