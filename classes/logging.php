<?php
namespace SalesforceCourseSync;

/**
 * Log events based on plugin settings
 */
class Logging {

	protected $wpdb;
	protected $version;
	protected $slug;
	protected $option_prefix;

	public $enabled;
	public $statuses_to_log;


	/**
	 * Constructor which sets content type and pruning for logs
	 *
	 * @param object $wpdb An instance of the wpdb class.
	 * @param string $version The version of this plugin.
	 * @param string $slug The plugin slug
	 * @param string $option_prefix The plugin's option prefix
	 * @throws \Exception
	 */
	public function __construct( $wpdb, $version, $slug = '', $option_prefix = '' ) {
		$this->init();
	}

	/**
	 * Start. This creates a schedule for pruning logs, and also the custom content type
	 *
	 * @throws \Exception
	 */
	private function init() {
		add_filter( 'wp_log_types', array( $this, 'set_log_types' ), 10, 1 );
	}

	/**
	 * Set terms for Salesforce logs
	 *
	 * @param array $terms An array of string log types in the WP_Logging class.
	 * @return array $terms
	 */
	public function set_log_types( $terms ) {
		$terms[] = 'salesforce';
		return $terms;
	}

	public static function add($title = '', $message = '', $parent = 0, $type = 'salesforce') {
		return \WP_Logging::add($title, $message, $parent, $type);
	}

	public static function get_logs( $object_id = 0, $type = 'salesforce', $paged = null ) {
		return \WP_Logging::get_logs($object_id, $type, $paged);
	}
}
