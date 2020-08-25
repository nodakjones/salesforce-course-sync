<h2 class="nav-tab-wrapper">
	<?php foreach($tabs as $key => $title):
			$active = $active_tab === $key ? ' nav-tab-active' : ''; ?>
			<a class="nav-tab <?php echo $active ?>" href="?page=salesforce-course-sync-admin&tab=<?php echo $key ?>"><?php echo $title ?></a>
	<?php endforeach; ?>
</h2>