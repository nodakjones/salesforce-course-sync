<?php
/*
Plugin Name: Salesforce Course Sync
Description: Salesforce Course Sync was developed specifically for the UofN Kona site to automatically pull pricing and other important information from Salesforce, and make sure it is always up to date.
Version: 1.5.2
Author: Dan Robinson
Author URI: https://tagstudios.io
License: MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: salesforce-course-sync
*/

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/salesforce-importer.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/course.php';

class Salesforce_Course_Sync {
	private $wpdb;
	private $slug;
	private $option_prefix;
	private $login_credentials;
	private $version;
	private $activated;
	private $logging;
	public $salesforce;
	static $instance = null; // Static property to hold an instance of the class; this seems to make it reusable

	static public function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Salesforce_Course_Sync();
		}
		return self::$instance;
	}

	protected function __construct() {

		global $wpdb;

		$this->wpdb              = $wpdb;
		$this->version           = '1.0.0';
		$this->slug              = 'salesforce-course-sync';
		$this->option_prefix     = 'salesforce_course_sync_';
		$this->login_credentials = $this->get_login_credentials();

		$this->activate();

		$this->logging = $this->logging( $this->wpdb, $this->version, $this->slug, $this->option_prefix );
		$this->salesforce = $this->salesforce_get_api();
		$this->importer = new SalesforceCourseSync\SalesforceImporter($this->salesforce);
		$this->load_admin( $this->wpdb, $this->version, $this->login_credentials, $this->slug, $this->option_prefix, $this->salesforce, $this->importer, $this->logging );

		$this->shortcodes = $this->add_shortcodes();
	}

	private function logging( $wpdb, $version, $slug, $option_prefix ) {
		require_once plugin_dir_path( __FILE__ ) . 'classes/logging.php';
		$logging = new SalesforceCourseSync\Logging( $wpdb, $version, $slug, $option_prefix );
		return $logging;
	}

	private function add_shortcodes() {
		require_once( plugin_dir_path( __FILE__ ) . 'classes/shortcodes.php' );
		return new SalesforceCourseSync\Shortcodes();
	}

	public function salesforce_get_api() {
		require_once( plugin_dir_path( __FILE__ ) . 'classes/salesforce.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'classes/salesforce-query.php' ); // this can be used to generate soql queries, but we don't often need it so it gets initialized whenever it's needed
		$consumer_key        = $this->login_credentials['consumer_key'];
		$consumer_secret     = $this->login_credentials['consumer_secret'];
		$login_url           = $this->login_credentials['login_url'];
		$callback_url        = $this->login_credentials['callback_url'];
		$authorize_path      = $this->login_credentials['authorize_path'];
		$token_path          = $this->login_credentials['token_path'];
		$rest_api_version    = $this->login_credentials['rest_api_version'];
		$slug                = $this->slug;
		$option_prefix       = $this->option_prefix;
		$logging             = $this->logging;
		$is_authorized       = false;
		$sfapi               = '';
		if ( $consumer_key && $consumer_secret ) {
			$sfapi = new SalesforceCourseSync\Salesforce( $consumer_key, $consumer_secret, $login_url, $callback_url, $authorize_path, $token_path, $rest_api_version, $slug, $logging, $option_prefix );
			if ( $sfapi->is_authorized() === true ) {
				$is_authorized = true;
			}
		}
		return array(
			'is_authorized' => $is_authorized,
			'sfapi'         => $sfapi,
		);
	}

	private function activate() {
		// register_activation_hook( __FILE__, array( 'SalesforceCourseSync\Migrations', 'run' ) );
	}

	private function load_admin( $wpdb, $version, $login_credentials, $slug, $option_prefix, $salesforce, $importer, $logging ) {
		require_once( plugin_dir_path( __FILE__ ) . 'classes/admin.php' );
		$admin = new SalesforceCourseSync\Admin( $wpdb, $version, $login_credentials, $slug, $salesforce, $importer, $logging, $option_prefix );
		add_action( 'admin_menu', array( $admin, 'create_admin_menu' ) );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 5 );
		return $admin;
	}

	/**
	* Display a Settings link on the main Plugins page
	*
	* @param array $links
	* @param string $file
	* @return array $links
	*   These are the links that go with this plugin's entry
	*/
	public function plugin_action_links( $links, $file ) {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$settings = '<a href="' . get_admin_url() . 'options-general.php?page=salesforce-course-sync-admin">Settings</a>';
			// make the 'Settings' link appear first
			array_unshift( $links, $settings );
		}
		return $links;
	}

	/**
	* Get the pre-login Salesforce credentials.
	* These depend on the plugin's settings
	*
	* @return array $login_credentials
	*   Includes all settings necessary to log into the Salesforce API.
	*/
	private function get_login_credentials() {

		$consumer_key       = get_option( $this->option_prefix . 'consumer_key', '' );
		$consumer_secret    = get_option( $this->option_prefix . 'consumer_secret', '' );
		$callback_url       = get_site_url() . '/wp-admin/options-general.php?page=object-sync-salesforce-admin&tab=authorize';
		$login_base_url     = get_option( $this->option_prefix . 'login_base_url', '' );
		$authorize_url_path = '/services/oauth2/authorize';
		$token_url_path     = '/services/oauth2/token';
		$api_version        = get_option( $this->option_prefix . 'api_version', '' );

		$login_credentials = array(
			'consumer_key'     => $consumer_key,
			'consumer_secret'  => $consumer_secret,
			'callback_url'     => $callback_url,
			'login_url'        => $login_base_url,
			'authorize_path'   => $authorize_url_path,
			'token_path'       => $token_url_path,
			'rest_api_version' => $api_version,
		);

		return $login_credentials;

	}

} // end class
// Instantiate our class
$salesforce_course_sync = Salesforce_Course_Sync::get_instance();
