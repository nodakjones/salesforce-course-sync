<?php
namespace SalesforceCourseSync;

/**
 * Ability to authorize and communicate with the Salesforce REST API. This class can make read and write calls to Salesforce, and also cache the responses in WordPress.
 */
class Salesforce {

	public $response;

	/**
	* Constructor which initializes the Salesforce APIs.
	*
	* @param string $consumer_key
	*   Salesforce key to connect to your Salesforce instance.
	* @param string $consumer_secret
	*   Salesforce secret to connect to your Salesforce instance.
	* @param string $login_url
	*   Login URL for Salesforce auth requests - differs for production and sandbox
	* @param string $callback_url
	*   WordPress URL where Salesforce should send you after authentication
	* @param string $authorize_path
	*   Oauth path that Salesforce wants
	* @param string $token_path
	*   Path Salesforce uses to give you a token
	* @param string $rest_api_version
	*   What version of the Salesforce REST API to use
	* @param object $wordpress
	*   Object for doing things to WordPress - retrieving data, cache, etc.
	* @param string $slug
	*   Slug for this plugin. Can be used for file including, especially
	* @param object $logging
	*   Logging object for this plugin.
	* @param array $schedulable_classes
	*   array of classes that can have scheduled tasks specific to them
	* @param string $option_prefix
	*   Option prefix for this plugin. Used for getting and setting options, actions, etc.
	*/
	public function __construct( $consumer_key, $consumer_secret, $login_url, $callback_url, $authorize_path, $token_path, $rest_api_version, $slug, $logging, $option_prefix = '' ) {
		$this->consumer_key        = $consumer_key;
		$this->consumer_secret     = $consumer_secret;
		$this->login_url           = $login_url;
		$this->callback_url        = $callback_url;
		$this->authorize_path      = $authorize_path;
		$this->token_path          = $token_path;
		$this->rest_api_version    = $rest_api_version;
		$this->slug                = $slug;
		$this->option_prefix       = isset( $option_prefix ) ? $option_prefix : 'salesforce_course_sync_';
		$this->logging             = $logging;
		$this->options             = [];

		$this->success_codes              = array( 200, 201, 204 );
		$this->refresh_code               = 401;
		$this->success_or_refresh_codes   = $this->success_codes;
		$this->success_or_refresh_codes[] = $this->refresh_code;

		$this->debug = get_option( $this->option_prefix . 'debug_mode', false );

	}

	/**
	* Converts a 15-character case-sensitive Salesforce ID to 18-character
	* case-insensitive ID. If input is not 15-characters, return input unaltered.
	*
	* @param string $sf_id_15
	*   15-character case-sensitive Salesforce ID
	* @return string
	*   18-character case-insensitive Salesforce ID
	*/
	public static function convert_id( $sf_id_15 ) {
		if ( strlen( $sf_id_15 ) !== 15 ) {
			return $sf_id_15;
		}
		$chunks = str_split( $sf_id_15, 5 );
		$extra  = '';
		foreach ( $chunks as $chunk ) {
			$chars = str_split( $chunk, 1 );
			$bits  = '';
			foreach ( $chars as $char ) {
				$bits .= ( ! is_numeric( $char ) && strtoupper( $char ) === $char ) ? '1' : '0';
			}
			$map    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
			$extra .= substr( $map, base_convert( strrev( $bits ), 2, 10 ), 1 );
		}
		return $sf_id_15 . $extra;
	}

	/**
	* Given a Salesforce ID, return the corresponding SObject name. (Based on
	*  keyPrefix from object definition, @see
	*  https://developer.salesforce.com/forums/?id=906F0000000901ZIAQ )
	*
	* @param string $sf_id
	*   15- or 18-character Salesforce ID
	* @return string
	*   sObject name, e.g. "Account", "Contact", "my__Custom_Object__c" or FALSE
	*   if no match could be found.
	* @throws SalesforceException
	*/
	public function get_sobject_type( $sf_id ) {
		$objects = $this->objects(
			array(
				'keyPrefix' => substr( $sf_id, 0, 3 ),
			)
		);
		if ( 1 === count( $objects ) ) {
			// keyPrefix is unique across objects. If there is exactly one return value from objects(), then we have a match.
			$object = reset( $objects );
			return $object['name'];
		}
		// Otherwise, we did not find a match.
		return false;
	}

	/**
	* Determine if this SF instance is fully configured.
	*
	*/
	public function is_authorized() {
		return ! empty( $this->consumer_key ) && ! empty( $this->consumer_secret ) && $this->get_refresh_token();
	}

	/**
	* Get REST API versions available on this Salesforce organization
	* This is not an authenticated call, so it would not be a helpful test
	*/
	public function get_api_versions() {
		$options = array(
			'authenticated' => false,
			'full_url'      => true,
		);
		return $this->api_call( $this->get_instance_url() . '/services/data', [], 'GET', $options );
	}

	/**
	* Make a call to the Salesforce REST API.
	*
	* @param string $path
	*   Path to resource.
	* @param array $params
	*   Parameters to provide.
	* @param string $method
	*   Method to initiate the call, such as GET or POST. Defaults to GET.
	* @param array $options
	*   Any method can supply options for the API call, and they'll be preserved as far as the curl request
	*   They get merged with the class options
	* @param string $type
	*   Type of call. Defaults to 'rest' - currently we don't support other types.
	*   Other exammple in Drupal is 'apexrest'
	*
	* @return mixed
	*   The requested response.
	*
	* @throws SalesforceException
	*/
	public function api_call( $path, $params = array(), $method = 'GET', $options = array(), $type = 'rest' ) {
		if ( ! $this->get_access_token() ) {
			$this->refresh_token();
		}
		$this->response = $this->api_http_request( $path, $params, $method, $options, $type );

		// analytic calls that are expired return 404s for some absurd reason
		if ( $this->response['code'] && 'run_analytics_report' === debug_backtrace()[1]['function'] ) {
			return $this->response;
		}

		switch ( $this->response['code'] ) {
			// The session ID or OAuth token used has expired or is invalid.
			case $this->response['code'] === $this->refresh_code:
				// Refresh token.
				$this->refresh_token();
				// Rebuild our request and repeat request.
				$options['is_redo'] = true;
				$this->response     = $this->api_http_request( $path, $params, $method, $options, $type );
				// Throw an error if we still have bad response.
				if ( ! in_array( $this->response['code'], $this->success_codes, true ) ) {
					throw new SalesforceException( $this->response['data'][0]['message'], $this->response['code'] );
				}
				break;
			case in_array( $this->response['code'], $this->success_codes, true ):
				// All clear.
				break;
			default:
				// We have problem and no specific Salesforce error provided.
				if ( empty( $this->response['data'] ) ) {
					throw new SalesforceException( $this->response['error'], $this->response['code'] );
				}
		}

		if ( ! empty( $this->response['data'][0] ) && 1 === count( $this->response['data'] ) ) {
			$this->response['data'] = $this->response['data'][0];
		}

		if ( isset( $this->response['data']['error'] ) ) {
			throw new SalesforceException( $this->response['data']['error_description'], $this->response['data']['error'] );
		}

		if ( ! empty( $this->response['data']['errorCode'] ) ) {
			throw new SalesforceException( $this->response['data']['message'], $this->response['code'] );
		}

		return $this->response;
	}

	/**
	* Private helper to issue an SF API request.
	* This method is the only place where we read to or write from the cache
	*
	* @param string $path
	*   Path to resource.
	* @param array $params
	*   Parameters to provide.
	* @param string $method
	*   Method to initiate the call, such as GET or POST.  Defaults to GET.
	* @param array $options
	*   This is the options array from the api_call method
	*   This is where it gets merged with $this->options
	* @param string $type
	*   Type of call. Defaults to 'rest' - currently we don't support other types
	*   Other exammple in Drupal is 'apexrest'
	*
	* @return array
	*   The requested data.
	*/
	protected function api_http_request( $path, $params, $method, $options = array(), $type = 'rest' ) {
		$options = array_merge( $this->options, $options ); // this will override a value in $this->options with the one in $options if there is a matching key
		$url     = $this->get_api_endpoint( $type ) . $path;
		if ( isset( $options['full_url'] ) && true === $options['full_url'] ) {
			$url = $path;
		}
		$headers = array(
			'Authorization'   => 'Authorization: OAuth ' . $this->get_access_token(),
			'Accept-Encoding' => 'Accept-Encoding: gzip, deflate',
		);
		if ( 'POST' === $method || 'PATCH' === $method ) {
			$headers['Content-Type'] = 'Content-Type: application/json';
		}

		// if headers are being passed in the $options, use them.
		if ( isset( $options['headers'] ) ) {
			$headers = array_merge( $headers, $options['headers'] );
		}

		if ( isset( $options['authenticated'] ) && true === $options['authenticated'] ) {
			$headers = false;
		}

		$data                 = wp_json_encode( $params );
		$result               = $this->http_request( $url, $data, $headers, $method, $options );
		$result['from_cache'] = false;
		$result['cached']     = false;

		if ( isset( $options['is_redo'] ) && true === $options['is_redo'] ) {
			$result['is_redo'] = true;
		} else {
			$result['is_redo'] = false;
		}

		// it would be very unfortunate to ever have to do this in a production site
		if ( 1 === (int) $this->debug ) {
			// create log entry for the api call if debug is true
			$status = 'debug';

			// translators: placeholder is the URL of the Salesforce API request
			$title = sprintf( esc_html__( 'Debug: on Salesforce API HTTP Request to URL: %1$s.', 'object-sync-for-salesforce' ),
				esc_url( $url )
			);

			Logging::add(
				$title,
				print_r( $result, true ) // log the result because we are debugging the whole api call
			);
		}

		return $result;
	}

	/**
	* Make the HTTP request. Wrapper around curl().
	*
	* @param string $url
	*   Path to make request from.
	* @param array $data
	*   The request body.
	* @param array $headers
	*   Request headers to send as name => value.
	* @param string $method
	*   Method to initiate the call, such as GET or POST. Defaults to GET.
	* @param array $options
	*   This is the options array from the api_http_request method
	*
	* @return array
	*   Salesforce response object.
	*/
	protected function http_request( $url, $data, $headers = array(), $method = 'GET', $options = array() ) {
		// Build the request, including path and headers. Internal use.

		/*
		 * Note: curl is used because wp_remote_get, wp_remote_post, wp_remote_request don't work. Salesforce returns various errors.
		 * There is a GitHub branch attempting with the goal of addressing this in a future version: https://github.com/MinnPost/object-sync-for-salesforce/issues/94
		*/

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );
		if ( false !== $headers ) {
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
		} else {
			curl_setopt( $curl, CURLOPT_HEADER, false );
		}

		if ( 'POST' === $method ) {
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
		} elseif ( 'PATCH' === $method || 'DELETE' === $method ) {
			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
		}
		$json_response = curl_exec( $curl ); // this is possibly gzipped json data
		$code          = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

		if ( ( 'PATCH' === $method || 'DELETE' === $method ) && '' === $json_response && 204 === $code ) {
			// delete and patch requests return a 204 with an empty body upon success for whatever reason
			$data = array(
				'success' => true,
				'body'    => '',
			);
			curl_close( $curl );

			$result = array(
				'code' => $code,
			);

			$return_format = isset( $options['return_format'] ) ? $options['return_format'] : 'array';

			switch ( $return_format ) {
				case 'array':
					$result['data'] = $data;
					break;
				case 'json':
					$result['json'] = wp_json_encode( $data );
					break;
				case 'both':
					$result['json'] = wp_json_encode( $data );
					$result['data'] = $data;
					break;
			}

			return $result;
		}

		if ( ( ord( $json_response[0] ) == 0x1f ) && ( ord( $json_response[1] ) == 0x8b ) ) {
			// skip header and ungzip the data
			$json_response = gzinflate( substr( $json_response, 10 ) );
		}
		$data = json_decode( $json_response, true ); // decode it into an array

		// don't use the exception if the status is a success one, or if it just needs a refresh token (salesforce uses 401 for this)
		if ( ! in_array( $code, $this->success_or_refresh_codes, true ) ) {
			$curl_error = curl_error( $curl );
			if ( '' !== $curl_error ) {
				// create log entry for failed curl
				$status = 'error';

				// translators: placeholder is the HTTP status code returned by the Salesforce API request
				$title = sprintf( esc_html__( 'Error: %1$s: on Salesforce http request', 'object-sync-for-salesforce' ),
					esc_attr( $code )
				);

				Logging::add(
					$title,
					$curl_error
				);
			} elseif ( isset( $data[0]['errorCode'] ) && '' !== $data[0]['errorCode'] ) { // salesforce uses this structure to return errors
				// create log entry for failed curl
				$status = 'error';

				// translators: placeholder is the server code returned by the api
				$title = sprintf( esc_html__( 'Error: %1$s: on Salesforce http request', 'object-sync-for-salesforce' ),
					absint( $code )
				);

				// translators: placeholders are: 1) the URL requested, 2) the message returned by the error, 3) the server code returned
				$body = sprintf( '<p>' . esc_html__( 'URL: %1$s', 'object-sync-for-salesforce' ) . '</p><p>' . esc_html__( 'Message: %2$s', 'object-sync-for-salesforce' ) . '</p><p>' . esc_html__( 'Code: %3$s', 'object-sync-for-salesforce' ),
					esc_attr( $url ),
					esc_html( $data[0]['message'] ),
					absint( $code )
				);

				Logging::add(
					$title,
					$body
				);
			} else {
				// create log entry for failed curl
				$status = 'error';

				// translators: placeholder is the server code returned by Salesforce
				$title = sprintf( esc_html__( 'Error: %1$s: on Salesforce http request', 'object-sync-for-salesforce' ),
					absint( $code )
				);

				Logging::add(
					$title,
					print_r( $data, true ) // log the result because we are debugging the whole api call
				);
			} // End if().
		} // End if().

		curl_close( $curl );

		$result = array(
			'code' => $code,
		);

		$return_format = isset( $options['return_format'] ) ? $options['return_format'] : 'array';

		switch ( $return_format ) {
			case 'array':
				$result['data'] = $data;
				break;
			case 'json':
				$result['json'] = $json_response;
				break;
			case 'both':
				$result['json'] = $json_response;
				$result['data'] = $data;
				break;
		}

		return $result;

	}

	/**
	* Get the API end point for a given type of the API.
	*
	* @param string $api_type
	*   E.g., rest, partner, enterprise.
	*
	* @return string
	*   Complete URL endpoint for API access.
	*/
	public function get_api_endpoint( $api_type = 'rest' ) {
		// Special handling for apexrest, since it's not in the identity object.
		if ( 'apexrest' === $api_type ) {
			$url = $this->get_instance_url() . '/services/apexrest/';
		} else {
			$identity = $this->get_identity();
			$url      = str_replace( '{version}', $this->rest_api_version, $identity['urls'][ $api_type ] );
			if ( '' === $identity ) {
				$url = $this->get_instance_url() . '/services/data/v' . $this->rest_api_version . '/';
			}
		}
		return $url;
	}

	/**
	* Get the SF instance URL. Useful for linking to objects.
	*/
	public function get_instance_url() {
		return get_option( $this->option_prefix . 'instance_url', '' );
	}

	/**
	* Set the SF instanc URL.
	*
	* @param string $url
	*   URL to set.
	*/
	protected function set_instance_url( $url ) {
		update_option( $this->option_prefix . 'instance_url', $url );
	}

	/**
	* Get the access token.
	*/
	public function get_access_token() {
		return get_option( $this->option_prefix . 'access_token', '' );
	}

	/**
	* Set the access token.
	*
	* It is stored in session.
	*
	* @param string $token
	*   Access token from Salesforce.
	*/
	protected function set_access_token( $token ) {
		update_option( $this->option_prefix . 'access_token', $token );
	}

	/**
	* Get refresh token.
	*/
	protected function get_refresh_token() {
		return get_option( $this->option_prefix . 'refresh_token', '' );
	}

	/**
	* Set refresh token.
	*
	* @param string $token
	*   Refresh token from Salesforce.
	*/
	protected function set_refresh_token( $token ) {
		update_option( $this->option_prefix . 'refresh_token', $token );
	}

	/**
	* Refresh access token based on the refresh token. Updates session variable.
	*
	* todo: figure out how to do this as part of the schedule class
	* this is a scheduleable class and so we could add a method from this class to run every 24 hours, but it's unclear to me that we need it. salesforce seems to refresh itself as it needs to.
	* but it could be a performance boost to do it at scheduleable intervals instead.
	*
	* @throws SalesforceException
	*/
	protected function refresh_token() {
		$refresh_token = $this->get_refresh_token();
		if ( empty( $refresh_token ) ) {
			throw new SalesforceException( esc_html__( 'There is no refresh token.', 'object-sync-for-salesforce' ) );
		}

		$data = array(
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh_token,
			'client_id'     => $this->consumer_key,
			'client_secret' => $this->consumer_secret,
		);

		$url      = $this->login_url . $this->token_path;
		$headers  = array(
			// This is an undocumented requirement on Salesforce's end.
			'Content-Type'    => 'Content-Type: application/x-www-form-urlencoded',
			'Accept-Encoding' => 'Accept-Encoding: gzip, deflate',
			'Authorization'   => 'Authorization: OAuth ' . $this->get_access_token(),
		);
		$headers  = false;
		$response = $this->http_request( $url, $data, $headers, 'POST' );

		if ( 200 !== $response['code'] ) {
			throw new SalesforceException(
				esc_html(
					sprintf(
						__( 'Unable to get a Salesforce access token. Salesforce returned the following errorCode: ', 'object-sync-for-salesforce' ) . $response['code']
					)
				),
				$response['code']
			);
		}

		$data = $response['data'];

		if ( is_array( $data ) && isset( $data['error'] ) ) {
			throw new SalesforceException( $data['error_description'], $data['error'] );
		}

		$this->set_access_token( $data['access_token'] );
		$this->set_identity( $data['id'] );
		$this->set_instance_url( $data['instance_url'] );
	}

	/**
	* Retrieve and store the Salesforce identity given an ID url.
	*
	* @param string $id
	*   Identity URL.
	*
	* @throws SalesforceException
	*/
	protected function set_identity( $id ) {
		$headers  = array(
			'Authorization'   => 'Authorization: OAuth ' . $this->get_access_token(),
			//'Content-type'  => 'application/json',
			'Accept-Encoding' => 'Accept-Encoding: gzip, deflate',
		);
		$response = $this->http_request( $id, null, $headers );
		if ( 200 !== $response['code'] ) {
			throw new SalesforceException( esc_html__( 'Unable to access identity service.', 'object-sync-for-salesforce' ), $response['code'] );
		}
		$data = $response['data'];
		update_option( $this->option_prefix . 'identity', $data );
	}

	/**
	* Return the Salesforce identity, which is stored in a variable.
	*
	* @return array
	*   Returns FALSE if no identity has been stored.
	*/
	public function get_identity() {
		return get_option( $this->option_prefix . 'identity', false );
	}

	/**
	* OAuth step 1: Redirect to Salesforce and request and authorization code.
	*/
	public function get_authorization_code() {
		$url = add_query_arg(
			array(
				'response_type' => 'code',
				'client_id'     => $this->consumer_key,
				'redirect_uri'  => $this->callback_url,
			),
			$this->login_url . $this->authorize_path
		);
		return $url;
	}

	/**
	* OAuth step 2: Exchange an authorization code for an access token.
	*
	* @param string $code
	*   Code from Salesforce.
	*/
	public function request_token( $code ) {
		$data = array(
			'code'          => $code,
			'grant_type'    => 'authorization_code',
			'client_id'     => $this->consumer_key,
			'client_secret' => $this->consumer_secret,
			'redirect_uri'  => $this->callback_url,
		);

		$url      = $this->login_url . $this->token_path;
		$headers  = array(
			// This is an undocumented requirement on SF's end.
			//'Content-Type'  => 'application/x-www-form-urlencoded',
			'Accept-Encoding' => 'Accept-Encoding: gzip, deflate',
		);
		$response = $this->http_request( $url, $data, $headers, 'POST' );

		$data = $response['data'];

		if ( 200 !== $response['code'] ) {
			$error = isset( $data['error_description'] ) ? $data['error_description'] : $response['error'];
			throw new SalesforceException( $error, $response['code'] );
		}

		// Ensure all required attributes are returned. They can be omitted if the
		// OAUTH scope is inadequate.
		$required = array( 'refresh_token', 'access_token', 'id', 'instance_url' );
		foreach ( $required as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				return false;
			}
		}

		$this->set_refresh_token( $data['refresh_token'] );
		$this->set_access_token( $data['access_token'] );
		$this->set_identity( $data['id'] );
		$this->set_instance_url( $data['instance_url'] );

		return true;
	}

	/* Core API calls */

	/**
	* Available objects and their metadata for your organization's data.
	*
	* @param array $conditions
	*   Associative array of filters to apply to the returned objects. Filters
	*   are applied after the list is returned from Salesforce.
	* @param bool $reset
	*   Whether to reset the cache and retrieve a fresh version from Salesforce.
	*
	* @return array
	*   Available objects and metadata.
	*
	* part of core API calls. this call does require authentication, and the basic url it becomes is like this:
	* https://instance.salesforce.com/services/data/v#.0/sobjects
	*
	* updateable is really how the api spells it
	*/
	public function objects(
		$conditions = array(
			'updateable'  => true,
			'triggerable' => true,
		),
		$reset = false
	) {

		$options = array(
			'reset' => $reset,
		);
		$result  = $this->api_call( 'sobjects', array(), 'GET', $options );

		if ( ! empty( $conditions ) ) {
			foreach ( $result['data']['sobjects'] as $key => $object ) {
				foreach ( $conditions as $condition => $value ) {
					if ( $object[ $condition ] !== $value ) {
						unset( $result['data']['sobjects'][ $key ] );
					}
				}
			}
		}

		ksort( $result['data']['sobjects'] );

		return $result['data']['sobjects'];
	}

	/**
	* Use SOQL to get objects based on query string.
	*
	* @param string $query
	*   The SOQL query.
	* @param array $options
	*   Allow for the query to have options based on what the user needs from it, ie caching, read/write, etc.
	* @param boolean $all
	*   Whether this should get all results for the query
	* @param boolean $explain
	*   If set, Salesforce will return feedback on the query performance
	*
	* @return array
	*   Array of Salesforce objects that match the query.
	*
	* part of core API calls
	*/
	public function query( $query, $options = array(), $all = false, $explain = false ) {
		$search_data = [
			'q' => (string) $query,
		];
		if ( true === $explain ) {
			$search_data['explain'] = $search_data['q'];
			unset( $search_data['q'] );
		}
		// all is a search through deleted and merged data as well
		if ( true === $all ) {
			$path = 'queryAll';
		} else {
			$path = 'query';
		}
		$result = $this->api_call( $path . '?' . http_build_query( $search_data ), array(), 'GET', $options );
		return $result;
	}

	/**
	* Retrieve all the metadata for an object.
	*
	* @param string $name
	*   Object type name, E.g., Contact, Account, etc.
	* @param bool $reset
	*   Whether to reset the cache and retrieve a fresh version from Salesforce.
	*
	* @return array
	*   All the metadata for an object, including information about each field,
	*   URLs, and child relationships.
	*
	* part of core API calls
	*/
	public function object_describe( $name, $reset = false ) {
		if ( empty( $name ) ) {
			return array();
		}
		$options = array(
			'reset' => $reset,
		);
		$object  = $this->api_call( "sobjects/{$name}/describe", array(), 'GET', $options );
		// Sort field properties, because salesforce API always provides them in a
		// random order. We sort them so that stored and exported data are
		// standardized and predictable.
		$fields = array();
		foreach ( $object['data']['fields'] as &$field ) {
			ksort( $field );
			if ( ! empty( $field['picklistValues'] ) ) {
				foreach ( $field['picklistValues'] as &$picklist_value ) {
					ksort( $picklist_value );
				}
			}
			$fields[ $field['name'] ] = $field;
		}
		ksort( $fields );
		$object['fields'] = $fields;
		return $object;
	}

	/**
	* Create a new object of the given type.
	*
	* @param string $name
	*   Object type name, E.g., Contact, Account, etc.
	* @param array $params
	*   Values of the fields to set for the object.
	*
	* @return array
	*   json: {"id":"00190000001pPvHAAU","success":true,"errors":[]}
	*   code: 201
	*   data:
	*     "id" : "00190000001pPvHAAU",
	*     "success" : true
	*     "errors" : [ ],
	*   from_cache:
	*   cached:
	*   is_redo:
	*
	* part of core API calls
	*/
	public function object_create( $name, $params ) {
		$options = array(
			'type' => 'write',
		);
		$result  = $this->api_call( "sobjects/{$name}", $params, 'POST', $options );
		return $result;
	}

	/**
	* Create new records or update existing records.
	*
	* The new records or updated records are based on the value of the specified
	* field.  If the value is not unique, REST API returns a 300 response with
	* the list of matching records.
	*
	* @param string $name
	*   Object type name, E.g., Contact, Account.
	* @param string $key
	*   The field to check if this record should be created or updated.
	* @param string $value
	*   The value for this record of the field specified for $key.
	* @param array $params
	*   Values of the fields to set for the object.
	*
	* @return array
	*   json: {"id":"00190000001pPvHAAU","success":true,"errors":[]}
	*   code: 201
	*   data:
	*     "id" : "00190000001pPvHAAU",
	*     "success" : true
	*     "errors" : [ ],
	*   from_cache:
	*   cached:
	*   is_redo:
	*
	* part of core API calls
	*/
	public function object_upsert( $name, $key, $value, $params ) {
		$options = array(
			'type' => 'write',
		);
		// If key is set, remove from $params to avoid UPSERT errors.
		if ( isset( $params[ $key ] ) ) {
			unset( $params[ $key ] );
		}

		// allow developers to change both the key and value by which objects should be matched
		$key   = apply_filters( $this->option_prefix . 'modify_upsert_key', $key );
		$value = apply_filters( $this->option_prefix . 'modify_upsert_value', $value );

		$data = $this->api_call( "sobjects/{$name}/{$key}/{$value}", $params, 'PATCH', $options );
		if ( 300 === $this->response['code'] ) {
			$data['message'] = esc_html( 'The value provided is not unique.' );
		}
		return $data;
	}

	/**
	* Update an existing object.
	*
	* @param string $name
	*   Object type name, E.g., Contact, Account.
	* @param string $id
	*   Salesforce id of the object.
	* @param array $params
	*   Values of the fields to set for the object.
	*
	* part of core API calls
	*
	* @return array
	*   json: {"success":true,"body":""}
	*   code: 204
	*   data:
		success: 1
		body:
	*   from_cache:
	*   cached:
	*   is_redo:
	*/
	public function object_update( $name, $id, $params ) {
		$options = array(
			'type' => 'write',
		);
		$result  = $this->api_call( "sobjects/{$name}/{$id}", $params, 'PATCH', $options );
		return $result;
	}

	/**
	* Return a full loaded Salesforce object.
	*
	* @param string $name
	*   Object type name, E.g., Contact, Account.
	* @param string $id
	*   Salesforce id of the object.
	* @param array $options
	*   Optional options to pass to the API call
	*
	* @return object
	*   Object of the requested Salesforce object.
	*
	* part of core API calls
	*/
	public function object_read( $name, $id, $options = array() ) {
		return $this->api_call( "sobjects/{$name}/{$id}", array(), 'GET', $options );
	}

	/**
	* Make a call to the Analytics API
	*
	* @param string $name
	*   Object type name, E.g., Report
	* @param string $id
	*   Salesforce id of the object.
	* @param string $route
	*   What comes after the ID? E.g. instances, ?includeDetails=True
	* @param array $params
	*   Params to put with the request
	* @param string $method
	*   GET or POST
	*
	* @return object
	*   Object of the requested Salesforce object.
	*
	* part of core API calls
	*/
	public function analytics_api( $name, $id, $route = '', $params = array(), $method = 'GET' ) {
		return $this->api_call( "analytics/{$name}/{$id}/{$route}", $params, $method );
	}

}

class SalesforceException extends \Exception {
	public function __toString() {
		echo '<pre>';
		return parent::__toString();
	}
}
