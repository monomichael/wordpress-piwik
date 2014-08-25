<?php
/*
Name: Expressions Analytics
Description: WordPress plugin for Expressions analytics.
Author: Expressions Team, Alexander O'Mara
Version: 1.0
*/

/**
 * The site id for global tracking in Google.
 *
 * Define as non-string or empty to disable.
 */
if ( ! defined( 'EXPANA_GOOGLE_GLOBAL_TRACKING_ID' ) ) {
	define( 'EXPANA_GOOGLE_GLOBAL_TRACKING_ID', null );
}

/**
 * The namespace for global tracking in Google.
 *
 * Define as non-string or empty to disable.
 */
if ( ! defined( 'EXPANA_GOOGLE_GLOBAL_TRACKING_NAMESPACE' ) ) {
	define( 'EXPANA_GOOGLE_GLOBAL_TRACKING_NAMESPACE', null );
}

/**
 * The site id for global tracking in Piwik.
 * 
 * Define as non-integer to disable.
 */
if ( ! defined( 'EXPANA_PIWIK_GLOBAL_TRACKING_ID' ) ) {
	define( 'EXPANA_PIWIK_GLOBAL_TRACKING_ID', 1 );
}

/**
 * The domain for global tracking in Piwik.
 *
 * Define as non-string or empty to disable.
 */
if ( ! defined( 'EXPANA_PIWIK_GLOBAL_TRACKING_DOMAIN' ) ) {
	define( 'EXPANA_PIWIK_GLOBAL_TRACKING_DOMAIN', '*.syr.edu' );
}

/**
 * The rest API URL for global tracking in Piwik, minus the protocol.
 *
 * Define as non-string or empty to disable.
 */
if ( ! defined( 'EXPANA_PIWIK_GLOBAL_TRACKING_REST_API' ) ) {
	define( 'EXPANA_PIWIK_GLOBAL_TRACKING_REST_API', null );
}

/**
 * Define the number of seconds to wait for remote API requests.
 */
if ( ! defined( 'EXPANA_EXTERNAL_API_TIMEOUT' ) ) {
	define( 'EXPANA_EXTERNAL_API_TIMEOUT', 30 );
}

/**
 * Define as true to disable remote API SSL verification.
 */
if ( ! defined( 'EXPANA_EXTERNAL_API_DISABLE_SSL_VERIFICATION' ) ) {
	define( 'EXPANA_EXTERNAL_API_DISABLE_SSL_VERIFICATION', false );
}

//Check if inside WordPress.
if ( ! defined( 'ABSPATH' ) ) { exit(); }

class ExpressionsAnalytics {
	
	/**
	 * Piwik tracking code format.
	 * 
	 * The following variables are substituted into the string.
	 * - %1$s = The top domain to track.
	 * - %2$s = The REST API base for the Piwik tracker.
	 * - %3$u = The unique site id.
	 */
	const TRACKING_CODE_PIWIK = <<<'EOS'
<!-- Piwik -->
<script type="text/javascript">
var _paq=_paq||[];
_paq.push(["setDocumentTitle",document.domain+"/"+document.title]);
_paq.push(["setCookieDomain","%1$s"]);
_paq.push(["setDomains",["%1$s"]]);
_paq.push(["trackPageView"]);
_paq.push(["enableLinkTracking"]);
(function(d,t,u,g,s) {
u=("https:"==d.location.protocol?"https":"http")+"://%2$s/";
_paq.push(["setTrackerUrl",u+"piwik.php"]);
_paq.push(["setSiteId",%3$u]);
g=d.createElement(t);
s=d.getElementsByTagName(t)[0];
g.type="text/javascript";
g.defer=true;
g.async=true;
g.src=u+"piwik.js";
s.parentNode.insertBefore(g,s);
})(document,"script");
</script>
<noscript><img src="//%2$s/piwik.php?idsite=%3$u&rec=1" style="border:0" alt="" /></noscript>
<!-- End Piwik Code -->

EOS;
	
	/**
	 * Google tracking code format.
	 * 
	 * The following variables are substituted into the string.
	 * - %1$s = The tracking settings code.
	 */
	const TRACKING_CODE_GOOGLE = <<<'EOS'
<script type="text/javascript">
var _gaq=_gaq||[];
%1$s(function() {
var ga=document.createElement('script');
ga.type='text/javascript';
ga.async=true;
ga.src=('https:'==document.location.protocol?'https://ssl':'http://www')+'.google-analytics.com/ga.js';
var s=document.getElementsByTagName('script')[0];
s.parentNode.insertBefore(ga,s);
})();
</script>

EOS;
	
	/**
	 * Google tracking API call.
	 * 
	 * The following variables are substituted into the string.
	 * - %1$s = The API call arguments.
	 */
	const TRACKING_CODE_GOOGLE_API_CALL = <<<'EOS'
_gaq.push(%1$s);

EOS;
	
	private $admin_panel_menu_label = 'Analytics';
	private $admin_panel_page_title = 'Expressions Analytics';
	private $admin_panel_page_slug = 'expana';
	private $admin_panel_settings_field_slug = 'expana-settings';
	private $admin_panel_settings_capability = 'manage_options';
	
	private $settings_name = 'expana_settings';
	private $settings_data = null;
	private $settings_default = array(
		'piwik_auth_token'       => '',
		'piwik_site_id'          => null,
		'google_web_property_id' => ''
	);
	
	public function __construct() {
		$this->add_actions();
	}
	
	/**
	 * Generate the Piwik tracking code.
	 * 
	 * @param string $track_domain The domain to track.
	 * @param string $rest_api The rest API URL, minus the protocol.
	 * @param string $site_id The unique site id assigned by Piwik.
	 * 
	 * @return string The Piwik tracking code.
	 */
	public function tracking_code_piwik( $track_domain, $rest_api, $site_id ) {
		return sprintf( self::TRACKING_CODE_PIWIK, $track_domain, $rest_api, $site_id );
	}
	
	/**
	 * Generate the Google tracking code.
	 * 
	 * @param array $accounts The accounts to track.
	 * 
	 * @return string The Google tracking code.
	 */
	public function tracking_code_google( $accounts ) {
		$api_calls_str = '';
		if ( is_array( $accounts ) ) {
			foreach ( $accounts as $account=>&$tracking ) {
				$ns = isset( $tracking['namespace'] ) && is_string( $tracking['namespace'] ) && ! empty( $tracking['namespace'] ) ? $tracking['namespace'] . '.' : '';
				$api_calls_str .= $this->tracking_code_google_api_call( array( $ns . '_setAccount', $account ) );
				$api_calls_str .= $this->tracking_code_google_api_call( array( $ns . '_trackPageview' ) );
			}
			unset( $tracking );
		}
		return empty( $api_calls_str ) ? '' : sprintf( self::TRACKING_CODE_GOOGLE, $api_calls_str );
	}
	
	/**
	 * Generate the Google API call.
	 * 
	 * @param mixed $call The API call parameter.
	 * 
	 * @return string The API call JS string.
	 */
	public function tracking_code_google_api_call( $call ) {
		return sprintf( self::TRACKING_CODE_GOOGLE_API_CALL, json_encode( $call ) );
	}
	
	/**
	 * Initialize the action hooks.
	 */
	public function add_actions() {
		add_action( 'init',                  array( $this, 'action_init'                  )        );
		add_action( 'admin_init',            array( $this, 'action_admin_init'            )        );
		//add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' )        );
		add_action( 'admin_menu',            array( $this, 'action_admin_menu'            )        );
		add_action( 'wp_footer',             array( $this, 'action_print_tracking_code'   ), 99999 );
	}
	
	public function action_init() {
		
	}
	
	public function settings_get() {
		if ( ! is_array( $this->settings_data ) ) {
			$this->settings_data = wp_parse_args( (array)get_option( $this->settings_name, array() ), $this->settings_default );
		}
		return $this->settings_data;
	}
	
	public function action_admin_init() {
		$setting = $this->settings_get();
		
		//Register the plugin settings.
		register_setting(
			$this->admin_panel_settings_field_slug,
			$this->settings_name,
			array( $this, 'callback_settings_sanitize' )
		);
		//Add a section to the settings.
		//Piwik group.
		add_settings_section(
			$this->admin_panel_settings_field_slug . '-piwik',
			__( 'Piwik Analytics', 'expana' ),
			function(){
				?><p><?php echo __( 'Enter your Piwik Auto Token below to enable tracking.', 'expana' ); ?></p><?php
			},
			$this->admin_panel_settings_field_slug
		);
		//Add a field to the section.
		//Piwik inputs.
		add_settings_field(
			'piwik',//A unique slug for this settings field, otherwise apparently unused.
			__( 'Auth Token' ),
			array( $this, 'callback_settings_section_field' ),
			$this->admin_panel_settings_field_slug,
			$this->admin_panel_settings_field_slug . '-piwik',
			array(
				'label_for'   => 'piwik_auth_token',
				'input_type'  => 'text',
				'input_class' => 'regular-text code',
				'input_value' => $setting['piwik_auth_token']
			)
		);
		//Add a section to the settings.
		//Google group.
		add_settings_section(
			$this->admin_panel_settings_field_slug . '-google',
			__( 'Google Analytics', 'expana' ),
			function(){
				?><p><?php echo __( 'Enter your Google Web Property ID below to enable tracking.', 'expana' ); ?></p><?php
			},
			$this->admin_panel_settings_field_slug
		);
		//Add a field to the section.
		//Google inputs.
		add_settings_field(
			'piwik',//A unique slug for this settings field, otherwise apparently unused.
			__( 'Web Property ID' ),
			array( $this, 'callback_settings_section_field' ),
			$this->admin_panel_settings_field_slug,
			$this->admin_panel_settings_field_slug . '-google',
			array(
				'label_for'   => 'google_web_property_id',
				'input_type'  => 'text',
				'input_class' => 'regular-text code',
				'input_value' => $setting['google_web_property_id']
			)
		);
	}
	
	/**
	 * Query the Piwik API for the site id associated with the URL and return the contents and success in an associative array.
	 * 
	 * @param string $resturl The URL to the REST API.
	 * @param array $restauth The Piwik auth token.
	 * 
	 * @return array The associative array.
	 */
	public function piwik_api_get_site_id_from_site_url( $resturl, $restauth ) {
		$siteid = null;
		$error = null;
		//Query the REST API.
		$req = $this->query_piwik_api(
			$resturl,
			array(
				'token_auth' => $restauth,
				'method'     => 'SitesManager.getSitesIdFromSiteUrl',
				'url'        => get_site_url()
			)
		);
		//Check success.
		if ( $req['result'] === 'success' && ! empty( $req['content'] ) ) {
			//Decode the JSON content.
			$content = @json_decode( $req['content'], true );
			if ( is_array( $content ) ) {
				//If JSON result is not error.
				if ( ! ( isset( $content['result'] ) && $content['result'] === 'error' ) ) {
					//Loop over the sites.
					foreach ( $content as &$site ) {
						//Check the ID.
						if ( isset( $site['idsite'] ) ) {
							$idsite = (int)$site['idsite'];
							//Make sure the ID is not the global one.
							if ( $idsite !== EXPANA_PIWIK_GLOBAL_TRACKING_ID ) {
								$siteid = $idsite;
								break;
							}
						}
					}
					unset( $site );
					if ( $siteid === null ) {
						$error = __( 'No site associated with this URL', 'expana' );
					}
				} else {
					$error = __( 'Piwik API error', 'expana' );
				}
			} else {
				$error = __( 'Piwik API returned an invalid response', 'expana' );
			}
		} else {
			$error = __( 'Failed to connect to the Piwik API', 'expana' );
		}
		return $siteid === null ? array( 'result' => 'error', 'content' => $error ) : array( 'result' => 'success', 'content' => $siteid );
	}
	
	/**
	 * Sanitize the input.
	 * 
	 * @param array $input The updated settings.
	 * 
	 * @return string The sanitized settings.
	 */
	public function callback_settings_sanitize( $input = null ) {
		//Get current settings.
		$settings = $this->settings_get();
		if ( is_array( $input ) ) {
			//Parse the input
			$input = wp_parse_args( $input, $this->settings_default );
			
			//If the Piwik Auth Token has changed, re-pull the site ID to track.
			if (
				$settings['piwik_auth_token'] !== $input['piwik_auth_token'] || 
				( ! is_int( $settings['piwik_site_id'] ) && trim( $input['piwik_auth_token'] ) )
			) {
				//Get the ID if possible.
				if ( is_string( EXPANA_PIWIK_GLOBAL_TRACKING_REST_API ) && ! empty( EXPANA_PIWIK_GLOBAL_TRACKING_REST_API ) ) {
					//var_dump( $this->piwik_api_get_site_id_from_site_url( 'http://' . EXPANA_PIWIK_GLOBAL_TRACKING_REST_API, $input['piwik_auth_token'] ) );
					//exit;
				} else {
					//ERROR
				}
			}
			$settings['piwik_auth_token']       = trim( $input['piwik_auth_token'] );
			$settings['google_web_property_id'] = trim( $input['google_web_property_id'] );
		}
		return $settings;
	}
	
	/**
	 * Admin panel settings input callback.
	 * 
	 * @param array $args Data from add_settings_field.
	 */
	public function callback_settings_section_field( $args ) {
		$args = wp_parse_args( $args, array(
			'label_for'   => '',
			'input_type'  => '',
			'input_class' => '',
			'input_value' => ''
		) );
		switch ( $args['input_type'] ) {
			case 'text':
				?><input <?php
					?>type="text" <?php
					?>id="<?php echo $args['label_for']; ?>" <?php
					?>class="<?php echo $args['input_class']; ?>" <?php
					?>name="<?php echo $this->settings_name; ?>[<?php echo $args['label_for']; ?>]" <?php
					?>value="<?php echo $args['input_value']; ?>" <?php
				?>/><?php
			break;
		}
	}
	
	/**
	 * Admin panel script enqueue callback.
	 * 
	 * @param string $hook The WordPress unique page slug.
	 */
	public function action_admin_enqueue_scripts( $hook ) {
		if ( $hook === 'settings_page_' . $this->admin_panel_page_slug ) {
			
		}
	}
	
	/**
	 * Add to admin panel menu.
	 */
	public function action_admin_menu() {
		add_options_page(
			__( $this->admin_panel_page_title, 'expana' ),
			__( $this->admin_panel_menu_label, 'expana' ),
			$this->admin_panel_settings_capability,
			$this->admin_panel_page_slug,
			array( $this, 'callback_settings_page' )
		);
	}
	
	/**
	 * Admin panel settings page callback.
	 */
	public function callback_settings_page() {
		if ( ! current_user_can( $this->admin_panel_settings_capability ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		?><div class="wrap">
			<h2><?php echo __( $this->admin_panel_page_title, 'expana' ); ?></h2>
			<form action="options.php" method="post">
				<?php
				settings_fields( $this->admin_panel_settings_field_slug );
				do_settings_sections( $this->admin_panel_settings_field_slug );
				?>
				<p class="submit">
					<input type="submit" value="<?php esc_attr_e('Save Changes'); ?>" class="button button-primary" id="submit" name="submit" />
				</p>
			</form>
		</div><?php
	}
	
	/**
	 * Action callback to print all the tracking code.
	 */
	public function action_print_tracking_code() {
		//Global tracking Piwik.
		if (
			is_string( EXPANA_PIWIK_GLOBAL_TRACKING_DOMAIN ) && ! empty( EXPANA_PIWIK_GLOBAL_TRACKING_DOMAIN ) &&
			is_string( EXPANA_PIWIK_GLOBAL_TRACKING_REST_API ) && ! empty( EXPANA_PIWIK_GLOBAL_TRACKING_REST_API ) &&
			is_int( EXPANA_PIWIK_GLOBAL_TRACKING_ID )
		) {
			echo $this->tracking_code_piwik(
				EXPANA_PIWIK_GLOBAL_TRACKING_DOMAIN,
				EXPANA_PIWIK_GLOBAL_TRACKING_REST_API,
				EXPANA_PIWIK_GLOBAL_TRACKING_ID
			);
		}
		
		//TODO: User defined Piwik.
		$ga_accounts = array();
		//Add global tracking to the list.
		if ( is_string( EXPANA_GOOGLE_GLOBAL_TRACKING_ID ) && ! empty( EXPANA_GOOGLE_GLOBAL_TRACKING_ID ) ) {
			$ga_accounts[EXPANA_GOOGLE_GLOBAL_TRACKING_ID] = array(
				'namespace' => EXPANA_GOOGLE_GLOBAL_TRACKING_NAMESPACE
			);
		}
		
		//TODO: Add site tracking to the list.
		$ga_accounts['TEST-USER'] = array(
			'namespace' => ''
		);
		echo $this->tracking_code_google( $ga_accounts );
	}
	
	/**
	 * Delete the specified setting or delete all settings if none are specified.
	 * 
	 * @param string $setting A specific setting to delete.
	 */
	public function settings_delete( $setting = null ) {
		//Check if deleting a specific setting.
		if ( $setting !== null ) {
			//If there to delete, remove it and save.
			if ( property_exists( $this->settings, $setting ) ) {
				unset( $this->settings[$setting] );
				update_option( $this->settings_name, $this->settings );
			}
		}
		//If deleting all, then remove the option completely.
		delete_option( $this->settings_name );
	}
	
	/**
	 * Query the Piwik API with the specified parameters and return the contents in an associative array.
	 * 
	 * @param string $restapi The URL to the REST API.
	 * @param array $query An associative array of query parameters.
	 * 
	 * @return array The associative array.
	 */
	public function query_piwik_api( $restapi, $query ) {
		return $this->remote_request( rtrim( $restapi, '/' ) . '/?' . http_build_query( wp_parse_args( $query, array(
			'module'     => 'API',
			'format'     => 'JSON'
		) ) ) );
	}
	
	/**
	 * Fetch an external URL and return the contents and success in an associative array.
	 * 
	 * @param string $url The URL to fetch.
	 * 
	 * @return array The associative array.
	 */
	public function remote_request( $url ) {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			return array(
				'result'  => 'error',
				'content' => __( 'Invalid URL', 'expana' )
			);
		}
		if ( function_exists( 'curl_init' ) ) {
			//Init CURL.
			$ctx = curl_init( $url );
			//Check success.
			if ( ! $ctx ) {
				return array(
					'result'  => 'error',
					'content' => __( 'Failed to initialize CURL', 'expana' )
				);
			}
			//Return string.
			curl_setopt( $ctx, CURLOPT_RETURNTRANSFER, true );
			//Suppress headers.
			curl_setopt( $ctx, CURLOPT_HEADER, false );
			//Verify SSL certificates.
			curl_setopt( $ctx, CURLOPT_SSL_VERIFYPEER, EXPANA_EXTERNAL_API_DISABLE_SSL_VERIFICATION !== true );
			//Set user agent if readable, else rely on the default.
			$php_user_agent = @ini_get( 'user_agent' );
			if ( ! empty( $php_user_agent ) ) {
				curl_setopt( $ctx, CURLOPT_USERAGENT, $php_user_agent );
			}
			//Set timeout.
			curl_setopt( $ctx, CURLOPT_TIMEOUT, EXPANA_EXTERNAL_API_TIMEOUT );
			//Send request.
			$response = curl_exec( $ctx );
			//Grab any error message.
			$curl_error = curl_error( $ctx );
			//Close connection.
			curl_close( $ctx );
			//Check response.
			if ( is_string( $response ) ) {
				return array(
					'result'  => 'success',
					'content' => $response
				);
			} else {
				return array(
					'result'  => 'error',
					'content' => $curl_error
				);
			}
		}
		elseif ( @ini_get( 'allow_url_fopen' ) && function_exists( 'stream_context_create' ) ) {
			//Create stream.
			$ctx = stream_context_create( array(
				'http' => array(
					'timeout' => EXPANA_EXTERNAL_API_TIMEOUT
				)
			) );
			//Send request.
			$response = @file_get_contents( $url, false, $ctx );
			//Check response.
			if ( is_string( $response ) ) {
				return array(
					'result'  => 'success',
					'content' => $response
				);
			} else {
				return array(
					'result'  => 'error',
					'content' => __( 'Remote fopen request failed', 'expana' )
				);
			}
		}
		//Return failure.
		return array(
			'result'  => 'error',
			'content' => __( 'CURL and remote fopen are disabled', 'expana' )
		);
	}
}
new ExpressionsAnalytics();
