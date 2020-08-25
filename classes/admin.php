<?php

namespace SalesforceCourseSync;

class Admin {

	protected $wpdb;
	protected $version;
	protected $login_credentials;
	protected $slug;
	protected $salesforce;
	protected $importer;
	protected $schedulable_classes;
	protected $option_prefix;

	public function __construct( $wpdb, $version, $login_credentials, $slug, $salesforce, $importer, $logging, $option_prefix = '' ) {
		$this->wpdb                = $wpdb;
		$this->version             = $version;
		$this->login_credentials   = $login_credentials;
		$this->slug                = $slug;
		$this->option_prefix       = $option_prefix;
		$this->salesforce          = $salesforce;
		$this->importer            = $importer;
		$this->logging             = $logging;

		$this->add_actions();

	}

	/**
	* Create the action hooks to create the admin page(s)
	*
	*/
	public function add_actions() {
		add_action( 'admin_init', array( $this, 'salesforce_settings_forms' ) );
		add_action( 'wp_ajax_pull_from_salesforce', array( $this, 'pull_from_salesforce' ) );
	}

	/**
	* Create WordPress admin options page
	*
	*/
	public function create_admin_menu() {
		add_options_page( 'Salesforce', 'Salesforce', 'manage_options', 'salesforce-course-sync-admin', array( $this, 'show_admin_page' ) );
	}

	/**
	* Render full admin pages in WordPress
	* This allows other plugins to add tabs to the Salesforce settings screen
	*
	* todo: better front end: html, organization of html into templates, css, js
	*
	*/
	public function show_admin_page() {
		$tabs = array(
			'landing'       => 'Landing',
			'settings'      => 'Settings',
			'authorize'     => 'Authorize',
			'courses'       => 'Courses',
			'logs'          => 'Logs'
		); // this creates the tabs for the admin

		$consumer_key    = $this->login_credentials['consumer_key'];
		$consumer_secret = $this->login_credentials['consumer_secret'];
		$callback_url    = $this->login_credentials['callback_url'];

		$get_data = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
		$active_tab = isset( $get_data['tab'] ) ? sanitize_key( $get_data['tab'] ) : 'landing';

		require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/tabs.php' );

		switch($active_tab) {
			case 'settings':
				require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/settings.php' );
				break;
			case 'authorize':
				if ( isset( $get_data['code'] ) ) {
					// this string is an oauth token
					$data          = esc_html( wp_unslash( $get_data['code'] ) );
					$is_authorized = $this->salesforce['sfapi']->request_token( $data );
					?>
					<script>window.location = '?page=salesforce-course-sync-admin&tab=authorize'</script>
					<?php
				} elseif ( true === $this->salesforce['is_authorized'] ) {
						require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/authorized.php' );
						$this->status( $this->salesforce['sfapi'] );
				} elseif ( true === is_object( $this->salesforce['sfapi'] ) && isset( $consumer_key ) && isset( $consumer_secret ) ) {
					?>
					<p><a class="button button-primary" href="<?php echo esc_url( $this->salesforce['sfapi']->get_authorization_code() ); ?>">Connect to Salesforce</a></p>
					<?php
				} else {
					$url    = esc_url( get_admin_url( null, 'options-general.php?page=salesforce-course-sync-admin&tab=settings' ) );
					$message = sprintf( esc_html( 'Salesforce needs to be authorized to connect to this website but the credentials are missing. Use the <a href="%1$s">Settings</a> tab to add them.'), $url, $anchor );
					require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/error.php' );
				}
				break;
			case 'courses':
				if (isset($get_data['refresh'])) {
					$result = $this->importer->refresh_salesforce_courses();
					if ($result !== true) {
						$message = 'Failed to fetch from Salesforce: ' . var_dump($result);
						require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/error.php' );
					}
				}
				if (isset($get_data['showfields'])) {
					// Hidden option for checking what fields we have available from salesforce (dev only)
					$fields = $this->importer->available_fields();
					require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/available-fields.php' );
				}

				$courses = Course::all();
				require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/courses.php' );
				break;
			case 'logs':
				$logs = Logging::get_logs();
				require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/logs.php' );
				break;
			default: // settings
				require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/landing.php' );
				break;
		}


	}

	/**
	* Create default WordPress admin settings form for salesforce
	* This is for the Settings page/tab
	*
	*/
	public function salesforce_settings_forms() {
		$input_callback_default   = array( $this, 'display_input_field' );
		$input_select_default     = array( $this, 'display_select' );

		$all_field_callbacks = array(
			'text'       => $input_callback_default,
			'select'     => $input_select_default,
		);

		$this->fields_settings( 'settings', 'settings', $all_field_callbacks );
	}

	/**
	* Fields for the Settings tab
	* This runs add_settings_section once, as well as add_settings_field and register_setting methods for each option
	*
	* @param string $page
	* @param string $section
	* @param string $input_callback
	*/
	private function fields_settings( $page, $section, $callbacks ) {
		add_settings_section( $page, ucwords( $page ), null, $page );
		$salesforce_settings = array(
			'consumer_key' => array(
				'title'    => 'Consumer Key',
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'     => 'text',
					'desc'     => '',
				),

			),
			'consumer_secret' => array(
				'title'    => 'Consumer Secret',
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'     => 'text',
					'desc'     => '',
				),
			),
			'login_base_url'                 => array(
				'title'    => 'Login Base URL',
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'     => 'url',
					// translators: 1) production salesforce login, 2) sandbox salesforce login
					'desc'     => sprintf( 'For most Salesforce setups, you should use %1$s for production and %2$s for sandbox.',
						esc_url( 'https://login.salesforce.com' ),
						esc_url( 'https://test.salesforce.com' )
					),
				),
			),
			'api_version'                    => array(
				'title'    => 'Salesforce API Version',
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'     => 'text',
					'desc'     => 'In decimal format, eg: 48.0',
					'default'  => $this->default_api_version || 48.0,
				),
			),
		);

		if ( true === is_object( $this->salesforce['sfapi'] ) && true === $this->salesforce['sfapi']->is_authorized() ) {
			$salesforce_settings['api_version'] = array(
				'title'    => 'Salesforce API Version',
				'callback' => $callbacks['select'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'     => 'select',
					'desc'     => '',
					'items'    => $this->version_options(),
				),
			);
		}

		foreach ( $salesforce_settings as $key => $attributes ) {
			$id       = $this->option_prefix . $key;
			$name     = $this->option_prefix . $key;
			$title    = $attributes['title'];
			$callback = $attributes['callback'];
			$validate = $attributes['args']['validate'];
			$page     = $attributes['page'];
			$section  = $attributes['section'];
			$args     = array_merge(
				$attributes['args'],
				array(
					'title'     => $title,
					'id'        => $id,
					'label_for' => $id,
					'name'      => $name,
				)
			);

			// if there is a constant and it is defined, don't run a validate function
			if ( isset( $attributes['args']['constant'] ) && defined( $attributes['args']['constant'] ) ) {
				$validate = '';
			}

			add_settings_field( $id, $title, $callback, $page, $section, $args );
			register_setting( $page, $id, array( $this, $validate ) );
		}
	}

	/**
	* Default display for <input> fields
	*
	* @param array $args
	*/
	public function display_input_field( $args ) {
		$type    = $args['type'];
		$id      = $args['label_for'];
		$name    = $args['name'];
		$desc    = $args['desc'];
		$checked = '';

		$class = 'regular-text';

		if ( 'checkbox' === $type ) {
			$class = 'checkbox';
		}

		if ( ! isset( $args['constant'] ) || ! defined( $args['constant'] ) ) {
			$value = esc_attr( get_option( $id, '' ) );
			if ( 'checkbox' === $type ) {
				if ( '1' === $value ) {
					$checked = 'checked ';
				}
				$value = 1;
			}
			if ( '' === $value && isset( $args['default'] ) && '' !== $args['default'] ) {
				$value = $args['default'];
			}

			echo sprintf( '<input type="%1$s" value="%2$s" name="%3$s" id="%4$s" class="%5$s"%6$s>',
				esc_attr( $type ),
				esc_attr( $value ),
				esc_attr( $name ),
				esc_attr( $id ),
				sanitize_html_class( $class . esc_html( ' code' ) ),
				esc_html( $checked )
			);
			if ( '' !== $desc ) {
				echo sprintf( '<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
		} else {
			echo sprintf( '<p><code>%1$s</code></p>',
				esc_html('Defined in wp-config.php')
			);
		}
	}

	/**
	* Display for a dropdown
	*
	* @param array $args
	*/
	public function display_select( $args ) {
		$type = $args['type'];
		$id   = $args['label_for'];
		$name = $args['name'];
		$desc = $args['desc'];
		if ( ! isset( $args['constant'] ) || ! defined( $args['constant'] ) ) {
			$current_value = get_option( $name );

			echo sprintf( '<div class="select"><select id="%1$s" name="%2$s"><option value="">- ' . 'Select one' . ' -</option>',
				esc_attr( $id ),
				esc_attr( $name )
			);

			foreach ( $args['items'] as $key => $value ) {
				$text     = $value['text'];
				$value    = $value['value'];
				$selected = '';
				if ( $key === $current_value || $value === $current_value ) {
					$selected = ' selected';
				}

				echo sprintf( '<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $value ),
					esc_attr( $selected ),
					esc_html( $text )
				);

			}
			echo '</select>';
			if ( '' !== $desc ) {
				echo sprintf( '<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
			echo '</div>';
		} else {
			echo sprintf( '<p><code>%1$s</code></p>',
				esc_html('Defined in wp-config.php')
			);
		}
	}

	/**
	* Dropdown formatted list of Salesforce API versions
	*
	* @return array $args
	*/
	private function version_options() {
		$versions = $this->salesforce['sfapi']->get_api_versions();
		$args     = array();
		foreach ( $versions['data'] as $key => $value ) {
			$args[] = array(
				'value' => $value['version'],
				'text'  => $value['label'] . ' (' . $value['version'] . ')',
			);
		}
		return $args;
	}

	/**
	* Run a demo of Salesforce API call on the authenticate tab after WordPress has authenticated with it
	*
	* @param object $sfapi
	*/
	private function status( $sfapi ) {

		$versions = $sfapi->get_api_versions();

		// format this array into text so users can see the versions
		if ( true === $versions['cached'] ) {
			$versions_is_cached = esc_html('This list is cached, and');
		} else {
			$versions_is_cached = esc_html('This list is not cached, but');
		}

		if ( true === $versions['from_cache'] ) {
			$versions_from_cache = esc_html('items were loaded from the cache');
		} else {
			$versions_from_cache = esc_html('items were not loaded from the cache');
		}

		// translators: 1) $versions_is_cached is the "This list is/is not cached, and/but" line, 2) $versions_from_cache is the "items were/were not loaded from the cache" line
		$versions_apicall_summary = sprintf( esc_html('Available Salesforce API versions. %1$s %2$s. This is not an authenticated request, so it does not touch the Salesforce token.'),
			$versions_is_cached,
			$versions_from_cache
		);

		$contacts = $sfapi->query( 'SELECT Name, Id from Contact LIMIT 100' );

		// format this array into html so users can see the contacts
		if ( true === $contacts['cached'] ) {
			$contacts_is_cached = esc_html('They are cached, and');
		} else {
			$contacts_is_cached = esc_html('They are not cached, but');
		}

		if ( true === $contacts['from_cache'] ) {
			$contacts_from_cache = esc_html('they were loaded from the cache');
		} else {
			$contacts_from_cache = esc_html('they were not loaded from the cache');
		}

		if ( true === $contacts['is_redo'] ) {
			$contacts_refreshed_token = esc_html('This request did require refreshing the Salesforce token');
		} else {
			$contacts_refreshed_token = esc_html('This request did not require refreshing the Salesforce token');
		}

		// translators: 1) $contacts['data']['totalSize'] is the number of items loaded, 2) $contacts['data']['records'][0]['attributes']['type'] is the name of the Salesforce object, 3) $contacts_is_cached is the "They are/are not cached, and/but" line, 4) $contacts_from_cache is the "they were/were not loaded from the cache" line, 5) is the "this request did/did not require refreshing the Salesforce token" line
		$contacts_apicall_summary = sprintf( esc_html('Salesforce successfully returned %1$s %2$s records. %3$s %4$s. %5$s.'),
			absint( $contacts['data']['totalSize'] ),
			esc_html( $contacts['data']['records'][0]['attributes']['type'] ),
			$contacts_is_cached,
			$contacts_from_cache,
			$contacts_refreshed_token
		);

		require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/status.php' );

	}

	/**
	* Deauthorize WordPress from Salesforce.
	* This deletes the tokens from the database; it does not currently do anything in Salesforce
	* For this plugin at this time, that is the decision we are making: don't do any kind of authorization stuff inside Salesforce
	*/
	private function logout() {
		$this->access_token  = delete_option( $this->option_prefix . 'access_token' );
		$this->instance_url  = delete_option( $this->option_prefix . 'instance_url' );
		$this->refresh_token = delete_option( $this->option_prefix . 'refresh_token' );
		echo sprintf( '<p>You have been logged out. You can use the <a href="%1$s">%2$s</a> tab to log in again.</p>',
			esc_url( get_admin_url( null, 'options-general.php?page=salesforce-course-sync-admin&tab=authorize' ) ),
			esc_html('Authorize')
		);
	}
}
