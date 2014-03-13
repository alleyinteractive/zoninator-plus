<?php

if( !class_exists( 'Zoninator_Plus_Groups' ) ) {

class Zoninator_Plus_Groups {

	/**
	 * @var string
	 * Slug for Zoninator Groups admin page
	 */
	var $key = 'manage_zoninator_groups';
	
	/**
	 * @var string
	 * Name for Zoninator zone group taxonomy
	 */
	var $zone_group_taxonomy = 'zoninator_zone_groups';
	
	/**
	 * @var string
	 * Name for Zoninator zone group nonce
	 */
	var $zone_group_nonce = 'zone_group_nonce';
	
	/**
	 * @var string
	 * Meta key to use when attaching groups to zones
	 */
	var $zone_group_meta_key = '_zoninator_zone_group';
	
	/**
	 * @var string
	 * Meta key to use when ordering zones within groups
	 */
	var $zone_group_order_meta_key = '_zoninator_zone_group_order';

	/**
	 * @var array
	 * Messages to display after various zone group actions
	 */
	var $zone_group_messages = null;
	
	/**
	 * @var array
	 * Messages to display after various zone group actions
	 */
	var $zone_group_selected_transient_key = null;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	function __construct() {
		global $zoninator_plus;
		
		// Handle add/edit/delete actions
		add_action( 'admin_init', array( $this, 'admin_controller' ) );
		
		// Add the admin page and menu
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		
		// Enqueue scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
 		
 		// Zoninator hooks
		add_action( 'zoninator_pre_zone_fields', array( $this, 'zone_fields' ) );
		add_action( 'zoninator_pre_zone_readonly', array( $this, 'zone_readonly' ) );
		add_action( 'edited_term', array( $this, 'save_zone_fields'), 10, 3 );
		add_action( 'created_term', array( $this, 'save_zone_fields'), 10, 3 );
		add_action( 'delete_term', array( $this, 'delete_zone_fields'), 10, 3 );
		add_filter( 'get_terms', array( $this, 'filter_zones' ), 10, 3 );
 		
 		// Add a taxonomy for zone groups
 		// Default post type support
 		if( is_object( $zoninator_plus ) ) {	
			$zone_post_types = $zoninator_plus->get_zoninator_post_types( 'names' );
			foreach( $zone_post_types as $post_type )
				add_post_type_support( $post_type, $this->zone_group_taxonomy );
			
			// Register taxonomy
			if( ! taxonomy_exists( $this->zone_group_taxonomy ) ) {
				register_taxonomy( $this->zone_group_taxonomy, $zoninator_plus->get_zoninator_post_types( 'names' ), array(
					'label' => __( 'Zone Groups', 'zoninator-plus' ),
					'hierarchical' => false,
					'query_var' => false,
					'rewrite' => false,
					'public' => false,
	
				) );
			}
		}
		
		// Set messages
		$this->zone_group_messages = array(
			'insert-success' => __( 'The zone group was successfully created.', 'zoninator-plus' ),
			'update-success' => __( 'The zone group was successfully updated.', 'zoninator-plus' ),
			'delete-success' => __( 'The zone group was successfully deleted.', 'zoninator-plus' ),
			'error-general' => __( 'There was an error updating the zone group. Please try again.', 'zoninator-plus' )
		);
		
		// Check for a zone group filter
		if( !empty( get_currentuserinfo()->ID ) ){
					$this->zone_group_selected_transient_key = 'zone_group_selected_' . get_currentuserinfo()->ID;
		}
		$this->_zone_group_filter();
	}
	
	/**
	 * Add CSS and JS to admin area, hooked into admin_enqueue_scripts.
	 */
	function enqueue_scripts() {
		$zoninator = z_get_zoninator();
		if( $zoninator->is_zoninator_page() ) {
			global $zoninator_plus;
			wp_enqueue_script( 'zoninator_plus_groups_script', $zoninator_plus->get_baseurl() . 'js/zoninator-plus-groups.js' );
			wp_enqueue_style( 'zoninator_plus_groups_style', $zoninator_plus->get_baseurl() . 'css/zoninator-plus-groups.css' );
			
			// Add a nonce to the admin script via the Zoninator Plus class for ajax trigger of zone backfill
			wp_localize_script( 'zoninator_plus_groups_script', 'zp_zone_groups', array( 'zone_data' => $this->get_zones( true ), 'group_data' => $this->get_zone_groups() ) );
		}
	}
	
	/**
	 * Configure the admin menu to link to the settings page
	 *
	 * @return void
	 */
	function admin_menu() {
		$zoninator = z_get_zoninator();
		add_submenu_page( $zoninator->key, __( 'Manage Zones', 'zoninator-plus' ), __( 'Manage Zones', 'zoninator-plus' ), $zoninator->_get_manage_zones_cap(), $zoninator->key, array( $zoninator, 'admin_page' ) );
		add_submenu_page( $zoninator->key, __( 'Manage Zone Groups', 'zoninator-plus' ), __( 'Manage Zone Groups', 'zoninator-plus' ), $zoninator->_get_manage_zones_cap(), $this->key, array( $this, 'manage_zoninator_groups' ) );
	}
	
	/**
	 * Output for the Zoninator Plus Manage Groups page
	 *
	 * @return void
	 */
	function manage_zoninator_groups() {
		?>
		<div class="nav-tabs-wrapper"></div>
		<div class="wrap">
			<div id="icon-edit-pages" class="icon32"><br /></div>
			<h2>Zone Groups<a href="#" id="add-zone-group" class="add-new-h2">Add New Group</a></h2>
			
			<?php if( array_key_exists( 'message', $_GET ) && array_key_exists( $_GET['message'], $this->zone_group_messages ) ) : ?>
				<div id="zone-message" class="updated below-h2">
					<p><?php echo esc_html( $this->zone_group_messages[$_GET['message']] ); ?></p>
				</div>
			<?php endif; ?>
			<?php if( array_key_exists( 'error', $_GET ) && array_key_exists( $_GET['error'], $this->zone_group_messages ) ) : ?>
				<div id="zone-message" class="error below-h2">
					<p><?php echo esc_html( $this->zone_group_messages[$_GET['error']] ); ?></p>
				</div>
			<?php endif; ?>
			
			<form id="zone-group-form" method="post">
				<?php wp_nonce_field( $this->zone_group_nonce, $this->zone_group_nonce ); ?>
				<input type="hidden" id="zone-group-action" name="action" value="" />
				<input type="hidden" id="zone-group-id" name="zone_group_id" value="" />
				<table id="zone-groups" class="widefat">
					<thead>
						<tr>
							<th>Zone Group</th>
							<th>Zones</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php
							// Get all current zone groups
							$zone_groups = $this->get_zone_groups();
							$zoninator = z_get_zoninator();
							if( is_array( $zone_groups ) && !empty( $zone_groups ) ) {
								foreach( $zone_groups as $zone_group ) {
									// Get any zones in the group
									$zones_in_group = $this->get_group_zones( $zone_group->term_id );
									$zones_in_group_display = "";
									$zones_in_group_data = array();
									if( is_array( $zones_in_group ) && !empty( $zones_in_group ) ) {
										$zones_in_group_names = array();
										foreach( $zones_in_group as $zone_in_group ) {
											$zone_obj = get_term( intval( $zone_in_group ), $zoninator->zone_taxonomy );
											$zones_in_group_names[] = $zone_obj->name;
											$zones_in_group_data[] = array(
												'id' => $zone_in_group,
												'name' => $zone_obj->name
											);
										}
										$zones_in_group_display = implode( "<br>", $zones_in_group_names );
									}
										
									echo sprintf(
										"<tr id='%s'><td class='zone-group-title'>%s</td><td class='zone-group-zones' data-zone-group-zones='%s'>%s</td><td><a href='#' class='edit-zone-group' data-zone-group-id='%s'>Edit</a> | <a href='#' class='delete-zone-group' data-zone-group-id='%s'>Delete</a>",
										'zone-group-' . $zone_group->term_id,
										$zone_group->name,
										json_encode( $zones_in_group_data ),
										$zones_in_group_display,
										$zone_group->term_id,
										$zone_group->term_id
									);
								}
							} else {
								echo sprintf(
									'<tr><td colspan="3" class="zone-groups-message">%s</td></tr>',
									__( 'No zone groups currently exist', 'zoninator-groups' )
								);
							}
						?>
					</tbody>
				</table>
			</form>
		</div>
		<?php	
	}
	
	/**
	 * Handle zone group add/edit/delete actions
	 *
	 * @return void
	 */
	function admin_controller() {
		if( $this->is_zoninator_group_page() ) {
			// If no action is defined, return
			if( !array_key_exists( 'action', $_REQUEST ) ) return;
			
			// Define variables common to all actions
			$action = $_REQUEST['action'];
			$zone_group_id = ( array_key_exists( 'zone_group_id', $_POST ) ) ? $_POST['zone_group_id'] : "";
			
			switch( $action ) {
				
				case 'insert':
				case 'update':
				
					// Validate before continuing
					$this->verify_nonce( $action );
					$this->verify_access( $action, $zone_group_id );
					
					$zone_group_id = ( array_key_exists( 'zone_group_id', $_POST ) ) ? $_POST['zone_group_id'] : "";
					$zone_group_name = ( array_key_exists( 'zone_group_name', $_POST ) ) ? $_POST['zone_group_name'] : "";
					$zone_group_zones = ( array_key_exists( 'zones_selected', $_POST ) ) ? $_POST['zones_selected'] : "";
					
					if( $zone_group_id ) {
						$result = $this->update_zone_group( $zone_group_id, $zone_group_name, $zone_group_zones );
					} else {
						$result = $this->insert_zone_group( $zone_group_name, $zone_group_zones );
					}
					
					if( is_wp_error( $result ) ) {
						wp_redirect( add_query_arg( 'message', 'error-general' ) );
						exit;
					} else {
						// Redirect with success message
						$message = sprintf( '%s-success', $action );
						wp_redirect( $this->_get_zone_groups_page_url( array( 'action' => 'edit', 'message' => $message ) ) );
						exit;
					}
					break;
				
				case 'delete':

					// Validate before continuing
					$this->verify_nonce( $action );
					$this->verify_access( $action, $zone_group_id );

					if( $zone_group_id ) {
						$result = $this->delete_zone_group( $zone_group_id );
					}
					
					if( is_wp_error( $result ) ) {
						$redirect_args = array( 'error' => $result->get_error_messages() );
					} else {
						$redirect_args = array( 'message' => 'delete-success' );
					}
					
					wp_redirect( $this->_get_zone_groups_page_url( $redirect_args ) );
					exit;
			}
		}
	}
	
	/**
	 * Handle zone group insert action
	 *
	 * @params int $id
	 * @params int $name
	 * @params array $zones
	 * @return mixed
	 */
	function insert_zone_group( $name, $zones ) {
		
		// Name cannot be empty
		if( empty( $name ) )
			return new WP_Error( 'zone-group-empty-name', __( 'Name is a required field.', 'zoninator-plus' ) );
		
		// Insert the zone group
		$result_group = wp_insert_term( $name, $this->zone_group_taxonomy );
		
		// If there was an error, return now and display it
		if( is_wp_error( $result_group ) ) { return $result_group; }
        
		// Add the zones for the zone group
		$result = $this->add_group_zones( $result_group['term_id'], $zones, true );
		
		return $result_group['term_id'];
	}
	
	/**
	 * Handle zone group update action
	 *
	 * @params int $id
	 * @params int $name
	 * @params array $zones
	 * @return mixed
	 */
	function update_zone_group( $id, $name, $zones ) {
	
		// Name cannot be empty
		if( empty( $name ) )
			return new WP_Error( 'zone-group-empty-name', __( 'Name is a required field.', 'zoninator-plus' ) );
		
		// Update the zone group
		$result = wp_update_term( intval($id), $this->zone_group_taxonomy, array( 'name' => $name ) );
		
		// If there was an error, return now and display it
		if( is_wp_error( $result ) ) { return $result; }
		
		// Add the zones for the zone group
		$result = $this->add_group_zones( $id, $zones, true );
		
		return $result;
	
	}
	
	/**
	 * Handle zone group delete action
	 *
	 * @params int $id
	 * @return mixed
	 */
	function delete_zone_group( $id ) {
	
		// Remove all zones from the group
		$result = $this->remove_group_zones( $id );
		
		// If there was an error removing zones, stop execution
		if( !$result )
			return new WP_Error( 'zone-group-delete-remove-zones', __( 'There was an error removing zones from the group before deletion.', 'zoninator-plus' ) );
	
		$result = wp_delete_term( intval($id), $this->zone_group_taxonomy );
		return $result;
	
	}
	
	/**
	 * Handle adding zones to groups
	 *
	 * @params int $id
	 * @params int|array $zones
	 * @params bool $replace optional
	 * @return mixed
	 */
	function add_group_zones( $id, $zones, $replace = false ) {
	
		// If a single zone was provided, add it to a new array
		if( !is_array( $zones ) ) $zones = array( $zones );
		
		// If replace was specified, remove all zones first
		if( $replace ) $this->remove_group_zones( $id );
		
		// Add all zones to the group
		$zone_order = 1;
		foreach( $zones as $zone ) {
			$result = tm_add_term_meta( $zone, $this->zone_group_meta_key, $id );
			if( !$result )
				return new WP_Error( 'zone-group-add-zone', __( 'There was an error adding zones to the group.', 'zoninator-plus' ) );
				
			$result = tm_add_term_meta( $zone, $this->zone_group_order_meta_key, $zone_order );
			if( !$result )
				return new WP_Error( 'zone-group-add-zone', __( 'There was an error adding zones to the group.', 'zoninator-plus' ) );
				
			$zone_order++;
		}
		
		return true;
	}
	
	/**
	 * Handle adding zones to griups
	 *
	 * @params int $id
	 * @params int|array $zones
	 * @params bool $replace optional
	 * @return mixed
	 */
	function remove_group_zones( $id, $zones=array() ) {
		
		// If a single zone was provided, add it to a new array
		if( !is_array( $zones ) ) $zones = array( $zones );
		
		// If the list of zones is empty, we are deleting all zones
		if( empty( $zones ) ) $zones = $this->get_group_zones( $id );
		
		// Add all zones to the group
		foreach( $zones as $zone ) {
			$result = tm_delete_term_meta( $zone, $this->zone_group_meta_key );
			if( !$result )
				return new WP_Error( 'zone-group-delete-zone', __( 'There was an error removing zones from the group.', 'zoninator-plus' ) );
				
			$result = tm_delete_term_meta( $zone, $this->zone_group_order_meta_key );
			if( !$result )
				return new WP_Error( 'zone-group-delete-zone', __( 'There was an error removing zones from the group.', 'zoninator-plus' ) );
		}
		
		return true;
	
	}
	
	/**
	 * Get all zone groups
	 *
	 * @params int $zone optional
	 * @return array
	 */
	function get_zone_groups( $fields='all' ) {
		$zone_groups = get_terms( 
			$this->zone_group_taxonomy,
			array(
				'orderby' => 'name',
				'hide_empty' => 0,
				'fields' => $fields
			)
		);
		return $zone_groups;
	}
	
	/**
	 * Get zones in a zone group
	 *
	 * @params int $zone optional
	 * @return array
	 */
	function get_group_zones( $id="" ) {
	
		$group_zones = array();
	
		if( $id == -1 ) {
				
			// If the id was -1, we should be returning all zones NOT in a group.
			// Get all zones with groups and filter that for those without a group
			// TODO: consider replacing with WordPress 3.5 EXISTS and NOT EXISTS meta_compare in the else block logic
			$zones = $this->get_zones( true );
			foreach( $zones as $zone )
				if( empty( $zone['zone_group'] ) ) 
					$group_zones[] = $zone['zone']->term_id;
	
		} else {

			// Must use WP_Query in order to accomplish this since we're searching for term meta which is stored as post meta
			$args = array(
				'meta_key' => $this->zone_group_order_meta_key,
				'orderby' => 'meta_value_num',
				'order' => 'ASC',
				'post_type' => 'term-meta',
				'posts_per_page' => -1
			);
			
			if( !empty( $id ) ) {
				// Query for zones in a group
				$args['meta_query'] = array(
				   array(
					   'key' => $this->zone_group_meta_key,
					   'value' => array( $id ),
					   'compare' => 'IN',
				   )
			   );
			}
						
			// Query for current group zones
			$query = new WP_Query( $args );
			$zone_posts = $query->get_posts();
			
			// Get the zone ids from the post_title
			if( is_array( $zone_posts ) && !empty( $zone_posts ) ) {
				foreach( $zone_posts as $zone_post ) {
					$group_zones[] = intval( end( explode( "-", $zone_post->post_title ) ) );
				}
			}
		
		}
	
		return $group_zones;
	}
	
	/**
	 * Wrapper for the Zoninator get_zones function that offers the option to hide zones already in a group.
	 * A zone can only be assigned to one group at a time.
	 *
	 * @params bool $exclude_grouped optional
	 * @return array
	 */
	function get_zones( $include_group = false ) {
		// Get all zones from Zoninator
		$zoninator = z_get_zoninator();
		$zones = $zoninator->get_zones();

		if( $zones && $include_group ) {
			$zones_with_groups = array();
			
			// Loop through the zones to add the zone group to each zone
			// Also keep track of which zones are not in groups in case we are returning only those still available
			foreach( $zones as $zone ) {
				$zone_group = tm_get_term_meta( $zone->term_id, $this->zone_group_meta_key, true );
				$zone_group_order = tm_get_term_meta( $zone->term_id, $this->zone_group_order_meta_key, true );
				$zones_with_groups[] = array(
					'zone' => $zone,
					'zone_group' => $zone_group,
					'zone_group_order' => $zone_group_order
				);
			}
			
			$zones = $zones_with_groups;
			
		}
		
		// Add the zone group to each zone
		
		return $zones;
	}
	
	/**
	 * Check if this is the zone groups page
	 * Repurposed from Zoninator
	 *
	 * @return bool
	 */
	function is_zoninator_group_page() {
		global $current_screen;
		
		if( function_exists( 'get_current_screen' ) )
			$screen = get_current_screen();
		
		if( empty( $screen ) ) {
			return ! empty( $_REQUEST['page'] ) && sanitize_key( $_REQUEST['page'] ) == $this->key;
		} else {
			return ! empty( $screen->id ) && strstr( $screen->id, $this->key );
		}
	}
	
	/**
	 * Adds additional fields to the zone management interface for users with access to edit zones
	 *
	 * @return void
	 */
	public function zone_fields( $zone ) {
		?>	
		<div class="form-field zone-field">
			<label for="zone-group"><?php _e( 'Group', 'zoninator-plus' ); ?></label>
			<select class="chzn-select" id="zone-group" name="<?php echo $this->zone_group_meta_key ?>" data-placeholder="None">
			<option></option>
			<?php
			// Get currently selected group
			$selected_zone_group = intval( tm_get_term_meta( $zone->term_id, $this->zone_group_meta_key, true ) );
			
			// Get the zone groups
			$zone_groups = $this->get_zone_groups();
			
			// Display as option elements
			foreach( $zone_groups as $zone_group ) {
				echo sprintf( 
					'<option value="%s" %s>%s</option>',
					$zone_group->term_id,
					( $zone_group->term_id == $selected_zone_group ) ? "selected" : "",
					$zone_group->name
				);
			}
			
			// Initialize chosen.js for this field
			add_action( 'admin_footer',  function() {
				echo '<script type="text/javascript"> $("#zone-group").chosen({allow_single_deselect:true})</script>';
			} );
			?>
			</select>
			<br><i><?php _e( 'Choose a content grouping for this zone.', 'zoninator-plus' ); ?></i>
			<?php
				$zone_group_selected = get_transient( $this->zone_group_selected_transient_key );
				echo sprintf(
					'<input type="hidden" name="zone_group_selected" id="zone-group-selected" value="%s" />',
					( $zone_group_selected === false ) ? "" : $zone_group_selected
				);
			?>
		</div>
		<?php	
	}
	
	/**
	 * Adds additional fields to the zone management interface for users with access only to edit zone content
	 *
	 * @return void
	 */
	public function zone_readonly( $zone ) {
		?>	
		<div class="form-field zone-field">
			<label for="zone-groups"><?php _e( 'Group', 'zoninator-plus' ); ?></label>
			<span>
			<?php
			// Get currently selected post types
			$selected_zone_group = intval( tm_get_term_meta( $zone->term_id, $this->zone_group_meta_key, true ) );
			$selected_zone_group_label = __( 'None', 'zoninator-plus' );
			if( !empty( $selected_zone_group ) && is_numeric( $selected_zone_group ) ) {
				$selected_zone_group_obj = get_term( $selected_zone_group, $this->zone_group_taxonomy );
				$selected_zone_group_label = $selected_zone_group_obj->name;
			}
			echo $selected_zone_group_label;
			?>
			</span>
			<?php
				$zone_group_selected = get_transient( $this->zone_group_selected_transient_key );
				echo sprintf(
					'<input type="hidden" name="zone_group_selected" id="zone-group-selected" value="%s" />',
					( $zone_group_selected === false ) ? "" : $zone_group_selected
				);
			?>
		</div>
		<?php
	}
	
	/**
	 * Saves extended zone admin fields when the zone term is created or updated
	 *
	 * @params int $term_id
	 * @params int $tt_id
	 * @params string $taxonomy
	 * @return void
	 */
	function save_zone_fields( $term_id, $tt_id, $taxonomy ) {
		// If the custom term fields are present and this is the correct taxonomy, save them
		if( $taxonomy == "zoninator_zones" ) {
			$zone_group = ( ( array_key_exists( $this->zone_group_meta_key, $_POST ) && !empty( $_POST[$this->zone_group_meta_key] ) ) ? $_POST[$this->zone_group_meta_key] : "" );
			
			if( $zone_group != "" ) {
			
				tm_update_term_meta( $term_id, $this->zone_group_meta_key, $zone_group );
				
				// See if the zone group order is set
				// If not, set it after the last current zone in the group
				$zone_order = tm_get_term_meta( $term_id, $this->zone_group_order_meta_key, true );
				if( $zone_order == "" ) {
					$group_zones = $this->get_group_zones( $term_id );
					$zone_order = count( $group_zones ) + 1;
					tm_update_term_meta( $term_id, $this->zone_group_order_meta_key, $zone_order );
				}
			} else {
				// The zone group is not set
				tm_delete_term_meta( $term_id, $this->zone_group_meta_key );
				tm_delete_term_meta( $term_id, $this->zone_group_order_meta_key );
			}
			
		}
	}
	
	/**
	 * Removes extended zone admin fields when the zone term is deleted
	 *
	 * @params int $term_id
	 * @params int $tt_id
	 * @params string $taxonomy
	 * @return void
	 */
	function delete_zone_fields( $term_id, $tt_id, $taxonomy ) {
		// If this is a Zoninator term, remove all of the Zoninator Plus extended metadata
		if( $taxonomy == "zoninator_zones" ) {
			tm_delete_term_meta( $term_id, $this->zone_group_meta_key );
			tm_delete_term_meta( $term_id, $this->zone_group_order_meta_key );
		}
	}
	
	/**
	 * Filter Zoninator zones query for use with populating tabs
	 *
	 * @params array $terms
	 * @params array $taxonomies
	 * @params array $args
	 * @return void
	 */
	function filter_zones( $terms, $taxonomies, $args ) {
		$zoninator = z_get_zoninator();
		if( $zoninator->is_zoninator_page() && !$this->is_zoninator_group_page() ) {
			$zone_group_selected = get_transient( $this->zone_group_selected_transient_key );
			if( in_array( $zoninator->zone_taxonomy, $taxonomies ) && $zone_group_selected !== false ) {
				$filtered_terms = array();
				foreach( $terms as $term ) {
					// Get the zone group
					$zone_group = tm_get_term_meta( $term->term_id, $this->zone_group_meta_key, true );
					if( $zone_group == $zone_group_selected || ( $zone_group == "" && $zone_group_selected == -1 ) ) $filtered_terms[] = $term;
				}
				$terms = $filtered_terms;
			}
		}
		return $terms;
	}
	
	function verify_nonce( $action ) {
		if( !array_key_exists( $this->zone_group_nonce, $_REQUEST ) || !wp_verify_nonce( $_REQUEST[$this->zone_group_nonce], $this->zone_group_nonce ) )
			$this->_unauthorized_access();
	}
	
	function verify_access( $action = '', $zone_id = null ) {		
		$verify_function = '';
		switch( $action ) {
			case 'insert':
				$verify_function = '_current_user_can_add_zone_groups';
				break;
			case 'update':
			case 'delete':
				$verify_function = '_current_user_can_edit_zone_groups';
				break;
			default:
				$verify_function = '_current_user_can_manage_zone_groups';
				break;
		}
		
		if( ! call_user_func( array( $this, $verify_function ), $zone_id ) )
			$this->_unauthorized_access();
	}
	
	function _get_zone_groups_page_url( $args = array() ) {
		$url = menu_page_url( $this->key, false );
		
		foreach( $args as $arg_key => $arg_value ) {
			$url = add_query_arg( $arg_key, $arg_value, $url );
		}	
		
		return $url;
	}
	
	function _current_user_can_add_zone_groups() {
		return current_user_can( $this->_get_add_zone_groups_cap() );
	}
	
	function _current_user_can_edit_zone_groups( $zone_id ) {
		$has_cap = current_user_can( $this->_get_edit_zone_groups_cap() );
		return apply_filters( 'zoninator_current_user_can_edit_zone_group', $has_cap, $zone_id );
	}
	
	function _current_user_can_manage_zone_groups() {
		return current_user_can( $this->_get_manage_zone_groups_cap() );
	}
	
	function _get_add_zone_groups_cap() {
		return apply_filters( 'zoninator_add_zone_group_cap', 'edit_others_posts' );
	}
	
	function _get_edit_zone_groups_cap() {
		return apply_filters( 'zoninator_edit_zone_group_cap', 'edit_others_posts' );
	}
	
	function _get_manage_zone_groups_cap() {
		return apply_filters( 'zoninator_manage_zone_group_cap', 'edit_others_posts' );
	}
	
	private function _unauthorized_access() {
		wp_die( __( 'Unauthorized group action', 'zoninator-plus' ) );
	}
	
	private function _zone_group_filter() {
		if ( array_key_exists( 'zone_group_selected', $_REQUEST ) ) {
			set_transient( $this->zone_group_selected_transient_key, $_REQUEST['zone_group_selected'], 0 );
		}
	}
	

}

}