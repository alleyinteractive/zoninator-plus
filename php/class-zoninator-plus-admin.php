<?php

if( !class_exists( 'Zoninator_Plus_Admin' ) ) {

class Zoninator_Plus_Admin {

	var $key = 'z_plus_settings';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	function __construct() {
		// Add the admin page and menu
		add_action( 'admin_init', array( $this, 'admin_settings' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
		// Enqueue scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Handle filters for overrides that can be specified in the admin interface
		// Not used at the moment since overrides are not technically possible
		/*
		add_filter( 'zoninator_zone_lock_period', array( $this, 'zone_lock_period' ) );
		add_filter( 'zoninator_zone_max_lock_period', array( $this, 'zone_max_lock_period' ) );
		add_filter( 'zoninator_posts_per_page', array( $this, 'posts_per_page' ) );
		*/
	}
	
	/**
	 * Add CSS and JS to admin area, hooked into admin_enqueue_scripts.
	 */
	function enqueue_scripts() {	
		if( $this->is_zoninator_admin_page() ) {
			global $zoninator_plus;
			wp_enqueue_script( 'zoninator_plus_admin_script', $zoninator_plus->get_baseurl() . 'js/zoninator-plus-admin.js' );
			wp_enqueue_style( 'zoninator_plus_admin_style', $zoninator_plus->get_baseurl() . 'css/zoninator-plus-admin.css' );
			
			// Add a nonce to the admin script via the Zoninator Plus class for ajax trigger of zone backfill
			wp_localize_script( 'zoninator_plus_admin_script', 'zp_admin_nonce', array( 'key' => $zoninator_plus->get_nonce_key( $zoninator_plus->ajax_nonce_action ), 'value' => wp_create_nonce( $zoninator_plus->get_nonce_key( $zoninator_plus->ajax_nonce_action ) ) ) );
		}
	}
	
	/**
	 * Configure the fields on the admin settings page for the Zoninator Plus plugin
	 *
	 * @return void
	 */
	function admin_settings() {
	
		// Zoninator overrides
		// TODO: can only enable these if Zoninator moves apply_filter for these values out of __construct and into init/admin_init 
		/*
		add_settings_section( 'zp_overrides_section',
			'Zoninator Override Settings',
			array( $this, 'overrides_section' ),
			$this->key 
		);
		
		// Zone lock period
		add_settings_field( 'zp_zone_lock_period',
			'Zone Lock Period',
			array( $this, 'zone_lock_period_field' ),
			$this->key,
			'zp_overrides_section' 
		);
		
		register_setting( $this->key, 'zp_zone_lock_period' );
		
		// Zone max lock period
		add_settings_field( 'zp_zone_max_lock_period',
			'Zone Max Lock Period',
			array( $this, 'zone_max_lock_period_field' ),
			$this->key,
			'zp_overrides_section' 
		);
		
		register_setting( $this->key, 'zp_zone_max_lock_period' );
		
		// Posts per page
		add_settings_field( 'zp_posts_per_page',
			'Posts Per Page',
			array( $this, 'posts_per_page_field' ),
			$this->key,
			'zp_overrides_section' 
		);
			
		register_setting( $this->key, 'zp_posts_per_page' );
		*/
		
		// Zoninator backfill settings
		add_settings_section( 'zp_backfill_section',
			'Zoninator Plus Backfill Settings',
			array( $this, 'backfill_section' ),
			$this->key 
		);
		
		// Backfill manual trigger
		add_settings_field( 'zp_do_backfill',
			'Manually Run Backfill',
			array( $this, 'do_backfill_field' ),
			$this->key,
			'zp_backfill_section' 
		);
		
	}
	
	/**
	 * Output the HTML for the admin settings page
	 *
	 * @return void
	 */
	function admin_settings_page() {
		?>
		<div class="wrap">
		<h2>Zoninator Plus Settings</h2>
		<form method="post" action="options.php">
			<?php 
			settings_fields( $this->key );
			do_settings_sections( $this->key );
			//submit_button(); 
			?>
		</form>
		</div>
		<?php	
	}
	
	/**
	 * Configure the admin menu to link to the settings page
	 *
	 * @return void
	 */
	function admin_menu() {
		add_options_page('Zoninator Plus Settings', 'Zoninator Plus', 'manage_options', $this->key, array( $this, 'admin_settings_page' ) );
	}
	
	/**
	 * Output for the Zoninator Overrides section header
	 * Not used at the moment since overrides are not technically possible
	 *
	 * @return void
	 */
	function overrides_section() {}
	
	/**
	 * Output for the zone lock period text field
	 * Not used at the moment since overrides are not technically possible
	 *
	 * @return void
	 */
	function zone_lock_period_field() {
		$zoninator = z_get_zoninator();
		echo sprintf(
			'<input type="text" name="zp_zone_lock_period" value="%s" size="4" /><br><i>%s <b>(%s %d)</b></i>',
			get_option( 'zp_zone_lock_period' ),
			__( 'The number of seconds a zone lock is valid' ),
			__( 'optional, default' ),
			$zoninator->zone_lock_period
		);	
	}
	
	/**
	 * Output for the zone lock period text field
	 * Not used at the moment since overrides are not technically possible
	 *
	 * @return void
	 */
	function zone_max_lock_period_field() {
		$zoninator = z_get_zoninator();
		echo sprintf(
			'<input type="text" name="zp_zone_max_lock_period" value="%s" size="4" /><br><i>%s <b>(%s %d)</b></i>',
			get_option( 'zp_zone_max_lock_period' ),
			__( 'The max number of seconds for all locks in a session are valid' ),
			__( 'optional, default' ),
			$zoninator->zone_max_lock_period
		);	
	}
	
	/**
	 * Output for the posts per page text field
	 * Not used at the moment since overrides are not technically possible
	 *
	 * @return void
	 */
	function posts_per_page_field() {
		$zoninator = z_get_zoninator();
		echo sprintf(
			'<input type="text" name="zp_posts_per_page" value="%s" size="4" /><br><i>%s <b>(%s %d)</b></i>',
			get_option( 'zp_posts_per_page' ),
			__( 'Posts per page to display on the manage zone screen' ),
			__( 'optional, default' ),
			$zoninator->posts_per_page
		);	
	}

	/**
	 * Output for the taxonomies section header
	 *
	 * @return void
	 */
	function backfill_section() {}
	
	/**
	 * Output for the maximum tabs text field
	 *
	 * @return void
	 */
	function do_backfill_field() {
		echo sprintf(
			'<input type="button" id="zp_do_backfill" name="zp_do_backfill" value="Run Backfill Now" size="4" /><br><span id="zp_backfill_message"><i>%s</i></span>',
			__( 'Click the button above to run the backfill process for all applicable zones. Use carefully.' )
		);	
	}
	
	/**
	 * Handle the zone lock period filter override
	 * Not used at the moment since overrides are not technically possible
	 *
	 * @return void
	 */
	function zone_lock_period( $zone_lock_period ) {
		return get_option( 'zp_zone_lock_period', $zone_lock_period );
	}
	
	/**
	 * Handle the zone max lock period filter override
	 * Not used at the moment since overrides are not technically possible
	 *
	 * @return void
	 */
	function zone_max_lock_period( $zone_max_lock_period ) {
		return get_option( 'zp_zone_max_lock_period', $zone_max_lock_period );
	}
	
	/**
	 * Handle the posts per page filter override
	 * Not used at the moment since overrides are not technically possible
	 *
	 * @return void
	 */
	function posts_per_page( $posts_per_page ) {
		return get_option( 'zp_posts_per_page', $posts_per_page );
	}
	
	/**
	 * Determine if we are on the Zoninator Plus admin page
	 * Copied from is_zoninator_page() in Zoninator
	 *
	 * @return bool
	 */
	function is_zoninator_admin_page() {
		global $current_screen;
		
		if( function_exists( 'get_current_screen' ) )
			$screen = get_current_screen();
		
		if( empty( $screen ) ) {
			return ! empty( $_REQUEST['page'] ) && sanitize_key( $_REQUEST['page'] ) == $this->key;
		} else {
			return ! empty( $screen->id ) && strstr( $screen->id, $this->key );
		}
	}

}

}