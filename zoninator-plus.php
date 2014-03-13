<?php

/*
Plugin Name: Zoninator Plus
Plugin URI: https://github.com/alleyinteractive/zoninator-plus
Description: Adds additional functionality to Zoninator to allow control over zone display and automatic backfill of content based on specified crtieria
Author: Bradford Campeau-Laurion, Alley Interactive
Version: 0.1
Author URI: http://www.alleyinteractive.com/
*/

/**
 * @package Zoninator Plus
 * @version 0.1
 */

require_once( dirname( __FILE__ ) . '/php/class-plugin-dependency.php' );
require_once( dirname( __FILE__ ) . '/php/class-zoninator-plus-admin.php' );
require_once( dirname( __FILE__ ) . '/php/class-zoninator-plus-groups.php' );
require_once( dirname( __FILE__ ) . '/functions.php' );

if( !class_exists( 'Zoninator Plus' ) ) {

class Zoninator_Plus {

    /**
     * @var string
     * Prefix to use for meta field storing backfill designation on a post
     */
    var $backfill_meta_prefix = '_zoninator_backfill_';

    /**
     * @var string
     * Meta key for zone visible settings
     */
    var $visible_meta_key = '_zoninator_zone_visible';

    /**
     * @var string
     * Meta key for zone max posts
     */
    var $max_posts_meta_key = '_zoninator_zone_max_posts';

    /**
     * @var string
     * Meta key for zone backfill
     */
    var $backfill_meta_key = '_zoninator_zone_backfill';

    /**
     * @var string
     * Meta key for zone post types
     */
    var $post_types_meta_key = '_zoninator_zone_post_types';

    /**
     * @var string
     * Meta key for zone terms
     */
    var $terms_meta_key = '_zoninator_zone_terms';

    /**
     * @var string
     * Prefix to use for nonces
     */
    var $nonce_prefix = 'zoninator-plus-nonce';

    /**
     * @var string
     * Nonce action for ajax backfill
     */
    var $ajax_nonce_action = 'ajax-backfill';

    /**
     * @var array
     * Sets available options for backfilling content
     */
    private $backfill_options = array(
        array(
            'label' => __( 'Do Not Backfill', 'zoninator-plus' ),
            'value' => 'none'
        ),
        array(
            'label' => __( 'Most Recent Content', 'zoninator-plus' ),
            'value' => 'recent'
        ),
        /* FOR FUTURE USE
        array(
            'label' => 'Most Popular Content',
            'value' => 'popular'
        )*/
    );

    /**
     * Constructor to set necessary action hooks and filters
     *
     * @return void
     */
    function __construct() {
        // Plugin requirements
        register_activation_hook( __FILE__, array( $this, 'dependencies' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'init', array( $this, 'init' ) );

        // Zoninator hooks
        add_action( 'zoninator_pre_zone_fields', array( $this, 'zone_fields' ) );
        add_action( 'zoninator_pre_zone_readonly', array( $this, 'zone_readonly' ) );
        add_action( 'edited_term', array( $this, 'save_zone_fields'), 15, 3 );
        add_action( 'created_term', array( $this, 'save_zone_fields'), 15, 3 );
        add_action( 'delete_term', array( $this, 'delete_zone_fields'), 15, 3 );
        add_filter( 'zoninator_zone_post_columns', array( $this, 'zone_post_columns' ), 10, 3 );

        // Handle admin ajax requests for backfill
        add_action( 'wp_ajax_zoninator_plus_backfill', array( $this, 'ajax_do_backfill' ) );
    }

    /**
     * Handle init actions
     *
     * return @void
     */
    function init() {
        // Set up the admin interface
        global $zp_admin;
        if( class_exists( 'Zoninator_Plus_Admin' ) ) { $zp_admin = new Zoninator_Plus_Admin(); }

        // Enable groups
        global $zp_groups;
        if( class_exists( 'Zoninator_Plus_Groups' ) ) { $zp_groups = new Zoninator_Plus_Groups(); }
    }

    /**
     * Handle plugin dependencies on activation
     *
     * @return void
     */
    function dependencies() {
        $zoninator_dependency = new Plugin_Dependency( 'Zoninator Plus', 'Zone Manager (Zoninator)', 'http://wordpress.org/extend/plugins/zoninator/' );
        if( !$zoninator_dependency->verify() ) {
            // Cease activation
            die( $zoninator_dependency->message() );
        }

        $term_meta_dependency = new Plugin_Dependency( 'Zoninator Plus', 'Term Meta', 'https://github.com/bcampeau/term-meta' );
        if( !$term_meta_dependency->verify() ) {
            // Cease activation
            die( $term_meta_dependency->message() );
        }
    }

    /**
     * Add CSS and JS to admin area, hooked into admin_enqueue_scripts.
     */
    function enqueue_scripts() {
        $zoninator = z_get_zoninator();

        if( $zoninator->is_zoninator_page() ) {
            wp_enqueue_script( 'zoninator_plus_script', $this->get_baseurl() . 'js/zoninator-plus.js', array( 'zoninator-js' ) );
            wp_enqueue_style( 'zoninator_plus_style', $this->get_baseurl() . 'css/zoninator-plus.css' );

            wp_localize_script( 'zoninator_plus_script', 'zp_backfill_nonce', array( 'key' => $this->get_nonce_key( $this->ajax_nonce_action ), 'value' => wp_create_nonce( $this->get_nonce_key( $this->ajax_nonce_action ) ) ) );

            // Chosen.js library used for post type and taxonomy selection
            wp_enqueue_script( 'chosen', $this->get_baseurl() . 'js/chosen/chosen.jquery.js' );
            wp_enqueue_style( 'chosen_css', $this->get_baseurl() . 'js/chosen/chosen.css' );
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
            <label for="zone-visible"><?php _e( 'Visible?', 'zoninator-plus' ); ?></label>
            <?php
            $visible = tm_get_term_meta( $zone->term_id, $this->visible_meta_key, true );
            echo sprintf(
                '<input type="checkbox" id="zone-visible" name="%s" value="1" %s/>',
                $this->visible_meta_key,
                ( $visible == 1 ) ? "checked" : ""
            );
            ?>
            <br><i><?php _e( 'Whether or not to display the zone on the site', 'zoninator-plus' ); ?></i>
        </div>
        <div class="form-field zone-field">
            <label for="zone-max-posts"><?php _e( 'Maximum Pieces of Content', 'zoninator-plus' ); ?></label>
            <input type="text" id="zone-max-posts" name="<?php echo $this->max_posts_meta_key ?>" value="<?php echo esc_attr( tm_get_term_meta( $zone->term_id, $this->max_posts_meta_key, true ) ); ?>" />
            <br><i><?php _e( 'Maximum number of content items for this zone. Leave blank for unlimited.', 'zoninator-plus' ); ?></i>
        </div>
        <div class="form-field zone-field">
            <label for="zone-backfill"><?php _e( 'Automatically Backfill Using', 'zoninator-plus' ); ?></label>
            <select id="zone-backfill" name="<?php echo $this->backfill_meta_key ?>">
            <?php
            // Get the current value
            $backfill = esc_attr( tm_get_term_meta( $zone->term_id, $this->backfill_meta_key, true ) );

            // Iterate through the options
            foreach( $this->backfill_options as $option ) {
                echo sprintf(
                    '<option value="%s" %s>%s</option>',
                    $option['value'],
                    ( $option['value'] == $backfill ) ? "selected" : "",
                    $option['label']
                );
            }
            ?>
            </select>
            <br><i><?php _e( 'Choose what method to use to backfill content, if any.', 'zoninator-plus' ); ?></i>
        </div>
        <div class="form-field zone-field">
            <label for="zone-allowed-content-types"><?php _e( 'Allowed Content Types (for Automatic Backfill)', 'zoninator-plus' ); ?></label>
            <select class="chzn-select" id="zone-allowed-content-types-display" name="<?php echo $this->post_types_meta_key ?>_display[]" multiple="multiple" data-placeholder="Select Post Types">
            <?php
            // Get currently selected post types
            $selected_post_types = tm_get_term_meta( $zone->term_id, $this->post_types_meta_key, true );
            $selected_post_types_data = json_decode( $selected_post_types );
            $selected_post_types_names = array();
            if( is_array( $selected_post_types_data ) )
                foreach( $selected_post_types_data as $selected_post_type )
                    $selected_post_types_names[] = $selected_post_type->name;

            $post_types = $this->get_zoninator_post_types();

            // Order by name
            uasort( $post_types, function( $a, $b ) {
                 return ( $a->label < $b->label ) ? -1 : 1;
            } );

            // Display as option elements
            foreach( $post_types as $post_type ) {
                echo sprintf(
                    '<option value="%s" %s>%s</option>',
                    $post_type->name,
                    ( is_array( $selected_post_types_names ) && in_array( $post_type->name, $selected_post_types_names ) ) ? "selected" : "",
                    $post_type->label
                );
            }

            // Initialize chosen.js for this field
            add_action( 'admin_footer',  function() {
                echo '<script type="text/javascript"> $("#zone-allowed-content-types-display").chosen()</script>';
            } );
            ?>
            </select>
            <input type="hidden" id="zone-allowed-content-types" name="<?php echo $this->post_types_meta_key ?>" value="<?php echo esc_attr( tm_get_term_meta( $zone->term_id, $this->post_types_meta_key, true ) ); ?>" />
            <br><i><?php _e( 'Choose which post types to use for backfill. Leave blank to use all.', 'zoninator-plus' ); ?></i>
        </div>
        <div class="form-field zone-field">
            <label for="zone-content-terms"><?php _e( 'Terms (for Automatic Backfill)', 'zoninator-plus' ); ?></label>
            <select class="chzn-select" id="zone-content-terms-display" name="<?php echo $this->terms_meta_key ?>_display[]" multiple="multiple" data-placeholder="Select Terms">
            <?php
            // Get currently selected post types
            $selected_terms = tm_get_term_meta( $zone->term_id, $this->terms_meta_key, true );
            $selected_terms_data = json_decode( $selected_terms );
            $selected_terms_ids = array();
            if( is_array( $selected_terms_data ) )
                foreach( $selected_terms_data as $selected_term )
                    $selected_terms_ids[] = $selected_term->id;

            $taxonomies = $this->get_zoninator_taxonomies();

            // Display as option elements
            foreach( $taxonomies as $taxonomy ) {
                // Start the optgroup for this taxonomy
                echo sprintf(
                    '<optgroup label="%s" data-taxonomy="%s">',
                    $taxonomy->label,
                    $taxonomy->name
                );

                // Add the terms
                $terms = get_terms(
                    $taxonomy->name,
                    array(
                        'orderby' => 'name',
                        'hide_empty' => 0
                    )
                );

                // Output the terms
                foreach( $terms as $term ) {
                    echo sprintf(
                        '<option value="%s" %s />%s</option>',
                        $term->term_id,
                        ( is_array( $selected_terms_ids ) && in_array( $term->term_id, $selected_terms_ids ) ) ? "selected" : "",
                        $term->name
                    );
                }

                // Close the optgroup
                echo '</optgroup>';
            }

            // Initialize chosen.js for this field
            add_action( 'admin_footer',  function() {
                echo '<script type="text/javascript"> $("#zone-content-terms-display").chosen()</script>';
            } );
            ?>
            </select>
            <input type="hidden" id="zone-content-terms" name="<?php echo $this->terms_meta_key ?>" value="<?php echo esc_attr( tm_get_term_meta( $zone->term_id, $this->terms_meta_key, true ) ); ?>" />
            <br><i><?php _e( 'Choose which terms to use for backfill. Leave blank to use all.', 'zoninator-plus' ); ?></i>
        </div>
        <?php
            //TODO: ADD PREVIEW HERE
            ?>
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
            <label for="zone-visible"><?php _e( 'Visible?', 'zoninator-plus' ); ?></label>
            <span>
            <?php
            $visible = tm_get_term_meta( $zone->term_id, $this->visible_meta_key, true );
            echo ( $visible == 1 ) ? "Yes" : "No";
            ?>
            </span>
        </div>
        <div class="form-field zone-field">
            <label for="zone-max-content"><?php _e( 'Maximum Pieces of Content', 'zoninator-plus' ); ?></label>
            <span><?php echo esc_attr( tm_get_term_meta( $zone->term_id, $this->max_posts_meta_key, true ) ); ?></span>
        </div>
        <div class="form-field zone-field">
            <label for="zone-backfill"><?php _e( 'Automatically Backfill Using', 'zoninator-plus' ); ?></label>
            <span>
            <?php
            // Get the current value
            $backfill = esc_attr( tm_get_term_meta( $zone->term_id, $this->backfill_meta_key, true ) );
            $backfill_label = $this->backfill_options[0]['label'];
            // Iterate through the options
            foreach( $this->backfill_options as $option ) {
                if ( $option['value'] == $backfill ) $backfill_label = $option['label'];
            }
            echo $backfill_label;
            ?>
            </span>
        </div>
        <?php
        // Only display the last two fields if backfill is enabled
        if( $backfill != "none" ) { ?>
        <div class="form-field zone-field">
            <label for="zone-allowed-content-types"><?php _e( 'Post Types (for Automatic Backfill)', 'zoninator-plus' ); ?></label>
            <span>
            <?php
            // Get currently selected post types
            $selected_post_types = tm_get_term_meta( $zone->term_id, $this->post_types_meta_key, true );
            $selected_post_types_data = json_decode( $selected_post_types );
            $selected_post_types_labels = array();
            foreach( $selected_post_types_data as $selected_post_type ) $selected_post_types_labels[] = $selected_post_type->label;
            echo ( empty( $selected_post_types_labels ) ) ? "None" : implode( ", ", $selected_post_types_labels );
            ?>
            </span>
        </div>
        <div class="form-field zone-field">
            <label for="zone-content-terms"><?php _e( 'Terms (for Automatic Backfill)', 'zoninator-plus' ); ?></label>
            <span>
            <?php
            // Get currently selected post types
            $selected_terms = tm_get_term_meta( $zone->term_id, $this->terms_meta_key, true );
            $selected_terms_data = json_decode( $selected_terms );
            $selected_terms_labels = array();
            if( !empty( $selected_terms_data ) )
	            foreach( $selected_terms_data as $selected_term ) $selected_terms_labels[] = $selected_term->label;
            echo ( empty( $selected_terms_labels ) ) ? "None" : implode( ", ", $selected_terms_labels );
            ?>
            </span>
        </div>
        <?php
        }
            //TODO: ADD PREVIEW HERE
            ?>
            <?php
    }

    /**
     * Modifies display of zone post columns in the admin interface based
     * @params array $args
     * @params object $post
     * @params object $zone
     * @return array
     */
    public function zone_post_columns( $args, $post, $zone ) {
        $zoninator = z_get_zoninator();
        return array(
            'position' => array( $zoninator, 'admin_page_zone_post_col_position' ),
            'info' => array( $this, 'admin_page_zone_post_col_info' ),
            'backfill' => array( $this, 'admin_page_zone_post_col_backfill' )
        );
        return $args;
    }

    /**
     * Zoninator Plus display function for zone post info
     * @params object $post
     * @params object $zone
     * @return void
     */
    function admin_page_zone_post_col_info( $post, $zone ) {
        // Build the action links for the post
        $action_links = array(
            sprintf( '<a href="%s" class="edit" target="_blank" title="%s">%s</a>', get_edit_post_link( $post->ID ), __( 'Opens in new window', 'zoninator' ), __( 'Edit', 'zoninator' ) )
        );

        // Hide row actions for backfilled posts
        $backfill_meta_key = $this->backfill_meta_prefix . $zone->term_id;
        $backfill = get_post_meta( $post->ID, $backfill_meta_key, true );
        $zone_backfill = tm_get_term_meta( $zone->term_id, $this->backfill_meta_key, true );
        if( $backfill != 1 || $zone_backfill == 'none' ) {
        	// Display the remove link for all non-backfilled content or for all content if the zone does not have backfill enabled
            $action_links[] = sprintf( '<a href="#" class="delete" title="%s">%s</a>', __( 'Remove this item from the zone', 'zoninator' ), __( 'Remove', 'zoninator' ) );
        }

        $action_links[] = sprintf( '<a href="%s" class="view" target="_blank" title="%s">%s</a>', get_permalink( $post->ID ), __( 'Opens in new window', 'zoninator' ), __( 'View', 'zoninator' ) );

        // Get the post type object to display the proper label
        $post_type_obj = get_post_type_object( $post->post_type );

        // Get the post publish status; if it isn't published, give a big red warning.
        $post_publish_status = '';
        if ( $post->post_status != 'publish' ) {
            $post_publish_status = ' <span style="color: red;">(WARNING: Not Published)</span>';
        }
        ?>
        <?php echo sprintf( '%s <span class="zone-post-status">(%s)</span>%s', esc_html( $post->post_title ), esc_html( $post_type_obj->labels->singular_name ), $post_publish_status ); ?>
        <div class="row-actions">
            <?php echo implode( ' | ', $action_links ); ?>
        </div>
        <?php
    }

    /**
     * Zoninator Plus display function for backfill indicator
     * @params object $post
     * @params object $zone
     * @return void
     */
    function admin_page_zone_post_col_backfill( $post, $zone ) {
        // Retrieve the meta field for backfill
        $backfill_meta_key = $this->backfill_meta_prefix . $zone->term_id;
        $backfill = get_post_meta( $post->ID, $backfill_meta_key, true );
        if( $backfill == 1 ) {
            ?>
            <?php _e( 'Automatically Backfilled', 'zoninator-plus' ); ?>
            <script>
            $(function() {
                $( "#zone-post-<?php echo $post->ID; ?>" ).sortable({ cancel: ".ui-state-disabled" });
                $( "#zone-post-<?php echo $post->ID; ?>" ).css('cursor', 'auto');
                $( "#zone-post-<?php echo $post->ID; ?> td.zone-post-position" ).css({'background': 'transparent', 'text-indent': '0'});
            });
            </script>
            <?php
        }
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
            tm_update_term_meta( $term_id, $this->visible_meta_key, ( ( array_key_exists( $this->visible_meta_key, $_POST ) ) ? $_POST[$this->visible_meta_key] : "0" ) );
            tm_update_term_meta( $term_id, $this->max_posts_meta_key, ( ( array_key_exists( $this->max_posts_meta_key, $_POST ) ) ? $_POST[$this->max_posts_meta_key] : "" ) );
            tm_update_term_meta( $term_id, $this->backfill_meta_key, ( ( array_key_exists( $this->backfill_meta_key, $_POST ) ) ? $_POST[$this->backfill_meta_key] : "none" ) );
            tm_update_term_meta( $term_id, $this->post_types_meta_key, ( ( array_key_exists( $this->post_types_meta_key, $_POST ) ) ? $_POST[$this->post_types_meta_key] : "" ) );
            tm_update_term_meta( $term_id, $this->terms_meta_key, ( ( array_key_exists( $this->terms_meta_key, $_POST ) ) ? $_POST[$this->terms_meta_key] : "" ) );

			// Run backfill for the zone
			global $zp_groups;
			$zone_id = $term_id;
			$zone_group_id = tm_get_term_meta( $zone_id, $zp_groups->zone_group_meta_key, true );

			if( !empty( $zone_id ) && empty( $zone_group_id ) ) {
				// Do backfill for a single zone
				$this->do_zone_backfill( $zone_id );
			} else {
				// Do backfill for all zones
				$this->do_zone_group_backfill( $zone_group_id );
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
            tm_delete_term_meta( $term_id, $this->visible_meta_key );
            tm_delete_term_meta( $term_id, $this->max_posts_meta_key );
            tm_delete_term_meta( $term_id, $this->backfill_meta_key );
            tm_delete_term_meta( $term_id, $this->post_types_meta_key );
            tm_delete_term_meta( $term_id, $this->terms_meta_key );
        }
    }

    /**
     * Handle an ajax request to do a backfill action
     *
     * @return string
     */
    function ajax_do_backfill() {
        // Verify the nonce before doing anything
        $this->verify_nonce( $this->ajax_nonce_action );

        $zone_id = "";
        $zone_group_id = "";

        $zone_id = ( array_key_exists( "zone_id", $_POST ) ) ? $_POST['zone_id'] : "";
        $zone_group_id = ( array_key_exists( "zone_group_id", $_POST ) ) ? $_POST['zone_group_id'] : "";

        echo sprintf(
            '%s<br>',
            __( "Starting backfill", "zoninator-plus" )
        );
        if( !empty( $zone_id ) && empty( $zone_group_id ) ) {
            // Do backfill for a single zone
            echo $this->do_zone_backfill( $zone_id );
        } else {
            // Do backfill for all zones
            echo $this->do_zone_group_backfill( $zone_group_id );
        }
        echo sprintf(
            '<br>%s<br>',
            __( "Backfill complete", "zoninator-plus" )
        );

        die();
    }

    /**
     * Handle the zone group backfill action
     *
     * @return string
     */
    function do_zone_group_backfill( $zone_group_id="", $mode="browser" ) {
        // Get the Zoninator Groups global
        global $zp_groups;
        $message = "";
        $line_break = ( $mode == "browser" ) ? "<br>\r\n" : "\r\n";

        // Check to see if a scalar or array was passed for the zone group id
        // If nothing was passed, then we are backfilling all zone groups (including zones without a group)
        $zone_groups = array();
        if( !isset( $zone_group_id ) || empty( $zone_group_id ) ) {
            $zone_groups = get_terms(
                $zp_groups->zone_group_taxonomy,
                array(
                    'hide_empty' => 0,
                    'fields' => 'ids'
                )
            );

            // Also add the "no group" zones if we are backfilling all zones
            $zone_groups[] = -1;

        } else {
            if( !is_array( $zone_group_id ) ) $zone_group_id = array( $zone_group_id );
            $zone_groups = $zone_group_id;
        }

        // Iterate through the zone groups
        $backfill_stories = 0;
        if( !empty( $zone_groups ) ) {
            foreach( $zone_groups as $zone_group ) {
                if( $zone_group == -1 ) {
                    $message .=  sprintf(
                        '%s%s%s',
                        $line_break,
                        __( "Doing backfill for ungrouped zones", "zoninator-plus" ),
                        $line_break
                    );
                } else {
                    $message .=  sprintf(
                        '%s%s %s%s',
                        $line_break,
                        __( "Doing backfill for zone group", "zoninator-plus" ),
                        $zone_group,
                        $line_break
                    );
                }

                // Get the zones for this group
                $zone_group_zones = $zp_groups->get_group_zones( $zone_group );

                // Backfill the zones in this group
                if( isset( $zone_group_zones ) && is_array( $zone_group_zones ) && !empty( $zone_group_zones ) ) {

                    // Create an array to hold backfilled posts to avoid duplication within the group
                    $zone_group_posts = array();

                    foreach( $zone_group_zones as $zone_group_zone ) {

                        // If this is the "no group" zone, purge the $zone_group_posts array on each loop since these zones are not connected
                        if( $zone_group_zone == -1 ) $zone_group_posts = array();

                        // Backfill this zone
                        $message .=  $this->do_zone_backfill( $zone_group_zone, $zone_group_posts, $mode );
                    }
                }
            }
        }

        return $message;

    }

    /**
     * Handle the zone backfill action
     *
     * @return string
     */
    function do_zone_backfill( $zone="", &$zone_group_posts=array(), $mode="browser" ) {
        $zoninator = z_get_zoninator();
        $message = "";
        $line_break = ( $mode == "browser" ) ? "<br>\r\n" : "\r\n";

        // Determine if the zone has backfill enabled
        $backfill = tm_get_term_meta( $zone, $this->backfill_meta_key, true );
        $max_content = tm_get_term_meta( $zone, $this->max_posts_meta_key, true );

        if( !empty( $backfill ) && $backfill != 'none' ) {
            $message .= sprintf(
                '%s %s%s',
                __( "\r\nDoing backfill for zone", "zoninator-plus" ),
                $zone,
                $line_break
            );

            // Get all current posts for the zone
            $current_posts = z_get_posts_in_zone( intval($zone) );

            // Exclude any posts passed to this function explicitly, likely from zone group functionality
            $exclude_posts = $zone_group_posts;

            // Build the post query and get backfill content
            $backfill_query = $this->get_backfill_query( $backfill, $zone, $exclude_posts, $max_content );
            $backfill_posts = $backfill_query->get_posts();
            if( !isset( $max_content ) ) $max_content = $backfill_query->found_posts; // max_content is set to unlimited, so append all posts

            /*
            $message .= sprintf(
                "   Query: %s %s",
                print_r($backfill_query->query, true),
                $line_break
            );
            */

            // Iterate through the zone posts if any exist
            // Also skip if no backfill posts were found since this would be pointless
            $posts_filled = 0;
            $backfill_index = 0;
            if( !empty( $current_posts ) && !empty( $backfill_posts ) ) {
                foreach( $current_posts as $current_post ) {
                    /*
                    $message .= sprintf(
                        "   Now working with current ID {$current_post->ID} %s",
                        $line_break
                    );
                    */

                    // If we have not yet filled the zone or exhausted backfill content, continue populating the zone.
                    // Else check the overpopulated zone for outdated backfilled content and prune them from the zone.
                    if ( $posts_filled < $max_content && $backfill_index < count($backfill_posts) ) {
                        // If the post is backfilled and isn't the same post as the one next in line from our backfill list ($backfill_posts), swap it out.
                        // Else retain the post (it's either manually placed or a valid backfilled post).
                        if( $this->post_is_backfilled( $zone, $current_post ) ) {

                            /*
                            $message .= sprintf(
                                "   Backfill index is {$backfill_index}, so we'll test current ID {$current_post->ID} against backfill ID {$backfill_posts[$backfill_index]->ID} %s",
                                $line_break
                            );
                             */

                            $used_post_id = $current_post->ID;
                            if ( $current_post->ID != $backfill_posts[$backfill_index]->ID ) {
                                $this->replace_post( $zone, $current_post, $backfill_posts[$backfill_index] );
                                $message .=  sprintf(
                                    '%s %s (%s) %s %s (%s)%s',
                                    __( "Backfill replaced post", "zoninator-plus" ),
                                    $current_post->post_title,
                                    $current_post->ID,
                                     __( "with post", "zoninator-plus" ),
                                     $backfill_posts[$backfill_index]->post_title,
                                     $backfill_posts[$backfill_index]->ID,
                                     $line_break
                                );

                                // Exclude the post from being backfilled in any other zones in the group.
                                $used_post_id = $backfill_posts[$backfill_index]->ID;
                            }
                            else {
                                    $message .=  sprintf(
                                    '%s %s (%s)%s',
                                    __( "Backfill reused post", "zoninator-plus" ),
                                    $current_post->post_title,
                                    $current_post->ID,
                                    $line_break
                                );
                            }

                            // Exclude the post from being backfilled in any other zones in the group.
                            $zone_group_posts[] = $used_post_id;

                            // Whether we replace the backfilled post or not, the post is considered "used".
                            $posts_filled++;
                            $backfill_index++;
                        }
                        else {
                            $message .=  sprintf(
                                '%s %s (%s)%s',
                                __( "Retaining manual post placement", "zoninator-plus" ),
                                $current_post->post_title,
                                $current_post->ID,
                                $line_break
                            );

                            // If the post is marked as published, increment the count of posts that are filled.
                            // (Otherwise we pretend the post isn't there, as we need a post to fill that slot.
                            if ( $current_post->post_status == 'publish' ) {
                                $posts_filled++;
                            }

                            // Exclude the post from being backfilled in any other zones in the group.
                            $zone_group_posts[] = $current_post->ID;
                        }

                        /*
                        $message .= sprintf(
                            "   Filled {$posts_filled} of {$max_content} and used {$backfill_index} out of " . sizeof($backfill_posts) . " available backfill posts %s",
                            $line_break
                        );
                        */
                    }
                    else {
                        if ( $this->post_is_backfilled( $zone, $current_post ) ) {
                            $this->remove_post($zone, $current_post);

                            $message .= sprintf(
                                "Removed post {$current_post->ID} from zone because of overpopulation %s",
                                $line_break
                            );
                        }
                    }
                }
            } else {
                // If there was no backfill content, then posts filled is number of posts currently in the zone since it was not touched
                $posts_filled = count( $current_posts );
            }

            // If the zone is still not full, append the backfill content if there is any more available
            while ( $posts_filled < $max_content && $backfill_index < count( $backfill_posts ) ) {
                $this->append_post( $zone, $backfill_posts[$backfill_index], $posts_filled );
                $message .=  sprintf(
                    '%s %s (%s)%s',
                    __( "Backfill appended post", "zoninator-plus" ),
                    $backfill_posts[$backfill_index]->post_title,
                    $backfill_posts[$backfill_index]->ID,
                    $line_break
                );

                // Exclude this post from being backfilled in any other zone groups
                $zone_group_posts[] = $backfill_posts[$backfill_index]->ID;

                $posts_filled++;
                $backfill_index++;

                /*
                $message .= sprintf(
                    "   Filled {$posts_filled} of {$max_content} and used {$backfill_index} out of " . sizeof($backfill_posts) . " available backfill posts %s",
                    $line_break
                );
                */
            }

        } else {
            $message .=  sprintf(
                '%s %s%s',
                __( "Backfill not enabled for zone", "zoninator-plus" ),
                $zone,
                $line_break
            );
        }

        return $message;
    }

    /**
     * Create the backfill query
     *
     * @params string $backfill
     * @params int $zone
     * @params array $exclude_posts
     * @return object WP_Query
     */
    function get_backfill_query( $backfill, $zone, $exclude_posts = array(), $max_content = 10 ) {

        // Get the backfill criteria
        $content_types = json_decode( tm_get_term_meta( $zone, $this->post_types_meta_key, true ) );
        $terms = json_decode( tm_get_term_meta( $zone, $this->terms_meta_key, true ) );

        // Required criteria
        $args = array(
            'order' => 'DESC',
            'orderby' => 'date',
            'post_status' => 'publish',
            'posts_per_page' => $max_content
        );

        // Add post IDs to exclude if set
        if( is_array( $exclude_posts ) && !empty( $exclude_posts ) )
            $args['post__not_in'] = $exclude_posts;

        // Add content types if they are set
        if( isset( $content_types ) ) {
            $post_types = array();
            foreach( $content_types as $content_type ) {
                $post_types[] = $content_type->name;
            }
            $args['post_type'] = $post_types;
        }

        // Add terms if they are set
        if( isset( $terms ) ) {
            $tax_query = array();
            foreach( $terms as $term ) {
                $tax_query[] = array(
                    'taxonomy' => $term->taxonomy,
                    'terms' => $term->id,
                    'field' => 'id'
                );
            }
            $args['tax_query'] = $tax_query;
        }

        return new WP_Query( $args );

    }

    /**
     * Check if a post is backfilled
     *
     * @params int $zone
     * @params object $post
     * @return bool
     */
    function post_is_backfilled( $zone, $post ) {
        // Simply check for the existence of the backfill meta key
        $backfill = get_post_meta( $post->ID, $this->get_backfill_meta_key( $zone ), true );
        return !empty( $backfill );
    }

    // TODO: Make post_expired() take the zone's taxonomy and category criteria into account.
    /**
     * Check if a post should be replaced by the latest post in a set of backfill data
     *
     * @params object $post
     * @params array $backfill_posts
     * @return bool
     */
    function post_expired( $current_post, $new_post ) {

        // Compare the publish date of the top post on the backfill list since they are ordered by date descending and see if it is newer than this post
        if( strtotime( $new_post->post_date ) > strtotime( $current_post->post_date ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Replace a backfilled post with new content
     *
     * @params int $zone
     * @params object $current_post
     * @params object $new_post
     * @return void
     */
    function replace_post( $zone, $current_post, $new_post ) {

        $order_meta_key = $this->get_order_meta_key( $zone );
        $backfill_meta_key = $this->get_backfill_meta_key( $zone );

        // Get the order from the old post and transfer it to the new one
        $order = get_post_meta( $current_post->ID, $order_meta_key, true );
        delete_post_meta( $current_post->ID, $order_meta_key );
        update_post_meta( $new_post->ID, $order_meta_key, $order );

        // Remove the backfill designation from the old post and add to the new one
        delete_post_meta( $current_post->ID, $backfill_meta_key );
        update_post_meta( $new_post->ID, $backfill_meta_key, true );
    }

    /**
     * Append a backfilled post to the current zone
     *
     * @params int $zone
     * @params object $post
     * @params int $posts_filled
     * @return void
     */
    function append_post( $zone, $post, $posts_filled ) {
        $order_meta_key = $this->get_order_meta_key( $zone );
        $backfill_meta_key = $this->get_backfill_meta_key( $zone );
        $order = $posts_filled + 1;

        update_post_meta( $post->ID, $order_meta_key, $order );
        update_post_meta( $post->ID, $backfill_meta_key, true );
    }

    /**
     * Remove a backfilled post from a zone
     *
     * @params int $zone
     * @params object $post
     * @return void
     */
    function remove_post( $zone, $post ) {

        $order_meta_key = $this->get_order_meta_key( $zone );
        $backfill_meta_key = $this->get_backfill_meta_key( $zone );

        // Remove the ordering metadata from the post
        delete_post_meta( $post->ID, $order_meta_key );

        // Remove the backfill designation from post
        delete_post_meta( $post->ID, $backfill_meta_key );
    }

    /**
     * Get the nonce key for a given action
     * Adapted from Zoninator
     *
     * @return string
     */
    function get_nonce_key( $action ) {
        return sprintf( '%s-%s', $this->nonce_prefix, $action );
    }

    /**
     * Verifies the nonce for a given action
     * Adapted from Zoninator
     *
     * @return string
     */
    function verify_nonce( $action ) {
        $action = $this->get_nonce_key( $action );
        $nonce_field = ( array_key_exists( $action, $_REQUEST ) ) ? $action : "_wpnonce";
        $nonce = ( array_key_exists( $nonce_field, $_REQUEST ) ) ? $_REQUEST[$nonce_field] : "";

        if( !wp_verify_nonce( $nonce, $action ) )
            $this->_unauthorized_access();
    }

    /**
     * Get the zone order meta key
     *
     * @params int $zone
     * @return string
     */
    function get_order_meta_key( $zone ) {
        $zoninator = z_get_zoninator();
        return $zoninator->zone_meta_prefix . $zone;
    }

    /**
     * Get the zone backfill meta key
     *
     * @params int $zone
     * @return string
     */
    function get_backfill_meta_key( $zone ) {
        return $this->backfill_meta_prefix . $zone;
    }

    /**
     * Get taxonomies to use with Zoninator Plus
     *
     * @return array
     */
    function get_zoninator_taxonomies( $output = 'objects' ) {
        $args = array(
            'public' => true
        );
        $taxonomies = get_taxonomies( $args, $output );
        return $taxonomies;
    }

    /**
     * Get post types to use with Zoninator Plus
     *
     * @return array
     */
    function get_zoninator_post_types( $output = 'objects' ) {
        $args = array(
            'public' => true,
            'show_ui' => true
        );
        $post_types = get_post_types( $args, $output );
        return $post_types;
    }

    /**
     * Get the base URL for this plugin.
     *
     * @return string URL pointing to plugin top directory.
     */
    function get_baseurl() {
        return plugin_dir_url( __FILE__ );
    }

    /**
     * Verify Zoninator nonce and access using it's own methods for the zone edit form.
     * It is not possible to attach our own nonce to all Zoninator actions, so it's better to use its method.
     *
     * @params int $term_id
     * @return void
     */
    private function _zoninator_verify( $term_id ) {
        $zoninator = z_get_zoninator();
        $action = $zoninator->_get_request_var( 'action' );
        $zoninator->verify_nonce( $action );
        $zoninator->verify_access( $action, $term_id );
    }

    /**
     * Get the Zoninator order meta key value
     *
     * @params int $term_id
     * @return void
     */
    private function _zoninator_order_meta_key( $zone_id ) {
        $zoninator = z_get_zoninator();
        return $zoninator->zone_meta_prefix . $zone_id;
    }

    /**
     * Handle unauthorized access
     *
     * @params int $term_id
     * @return void
     */
    private function _unauthorized_access() {
        wp_die( __( "Sorry, you're not supposed to do that...", 'zoninator-plus' ) );
    }

}

// Create an instance of the class
global $zoninator_plus;
$zoninator_plus = new Zoninator_Plus;

}