<form method="post" action="options.php">
	<h1>Connecting to Salesforce</h1>
	<p>You'll need to have access to a Salesforce developer account. This should come with Enterprise Edition, Unlimited Edition, or Performance Edition. Developers can register for a free Developer Edition account at <a href="https://developer.salesforce.com/signup" target="_blank">https://developer.salesforce.com/signup</a>.</p>

	<h2>Create an App</h2>
	<ol>
		<li>In Salesforce, go to Your Name > Setup (the cog at the top right of the screen when you are logged in). Then on the left sidebar, under Apps click App Manager. At the top right of the page, click 'New Connected App'.</li>
		<li>Enable OAuth Settings</li>
		<li>Set the callback URL to: <?php echo get_site_url() ?>/wp-admin/options-general.php?page=salesforce-course-sync-admin&tab=authorize (must use HTTPS). </li>
		<li>Select "Perform requests on your behalf at any time" and "Access and manage your data (api)" for OAuth Scope.</li>
		<li>Make sure the Connected App is enabled in the profile of the user you are authenticating.</li>
	</ol>

	<?php
		settings_fields( 'settings' ) . do_settings_sections( 'settings' );
		submit_button('Save settings');
	?>
</form>