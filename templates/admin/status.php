<h3>Salesforce Status</h3>
<p>
<?php
	// translators: placeholder is for the version number of the Salesforce REST API
	echo sprintf('Currently, we are using version %1$s of the Salesforce REST API. Available versions are displayed below.',
		esc_html( $this->login_credentials['rest_api_version'] )
	);
?>
</p>
<table class="widefat striped">
	<thead>
		<summary>
			<h4><?php echo $versions_apicall_summary; ?></h4>
		</summary>
		<tr>
			<th>Label</th>
			<th>URL</th>
			<th>Version</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $versions['data'] as $version ) { ?>
			<?php
			$class = '';
			if ( $version['version'] === $this->login_credentials['rest_api_version'] ) {
				$class = ' class="current"';
			}
			?>
			<tr<?php echo esc_attr( $class ); ?>>
				<td><?php echo esc_html( $version['label'] ); ?></td>
				<td><?php echo esc_html( $version['url'] ); ?></td>
				<td><?php echo esc_html( $version['version'] ); ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>

<table class="widefat striped">
	<thead>
		<summary>
			<h4><?php echo $contacts_apicall_summary; ?></h4>
		</summary>
		<tr>
			<th><?php echo esc_html__( 'Contact ID' ); ?></th>
			<th><?php echo esc_html__( 'Name' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $contacts['data']['records'] as $contact ) { ?>
			<tr>
				<td><?php echo esc_html( $contact['Id'] ); ?></td>
				<td><?php echo esc_html( $contact['Name'] ); ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>
