<?php
/**
 * Utility functions to leverage the functionality in Zoninator Plus
 *
 * @package Zoninator Plus
 */


/**
 * Find out of the "visible" checkbox is checked or not for a given zone
 *
 * @param int|string|object $zone Can either be a zone ID, zone slug, or Zoninator object
 * @return bool
 * @author Matthew Boynes
 */
function zp_is_zone_visible( $zone ) {
	$zone = z_get_zone( $zone );
	$visible = is_object( $zone ) ? fm_get_term_meta( $zone->term_id, z_get_zoninator()->zone_taxonomy, '_zoninator_zone_visible', true ) : false;
	return 1 == $visible;
}


/**
 * Wrapper for get_zone_posts which leverages ZoninatorPlus
 *
 * @param int|string|object $zone
 * @param int $limit Optional. Adds posts_per_page to the $args sent to Zoninator::get_zone_posts. Note that if in $options you set post__not_in, that takes precedence.
 * @param array $options Optional. Passed to Zoninator::get_zone_posts as the $args array, with additional options:
 * 				bool ignore_current Optional. If true, the global $post->ID is passed to post__not_in. Note that if in $options you set post__not_in, that takes precedence.
 * @return array post objects
 * @author Matthew Boynes
 */
function zp_get_posts_in_zone( $zone, $limit = null, $options = array() ) {
	global $zoninator_plus;

	$options = wp_parse_args( $options, array(
		'ignore_current' => true
	) );
	$args = array();

	# Set the number of returned posts
	if ( $limit ) {
		$args['posts_per_page'] = $limit;
	} else {
		$zone = z_get_zone( $zone );
		$args['posts_per_page'] = fm_get_term_meta( $zone->term_id, z_get_zoninator()->zone_taxonomy, $zoninator_plus->max_posts_meta_key, true ); //Number of posts returned for this zone
	}
	# By default, we probably don't want to include the current post
	if ( $options['ignore_current'] && is_single() && isset( $GLOBALS['post'] ) )
		$args['post__not_in'] = array( get_the_ID() );
	# Any remaining options will be sent to WP_Query
	unset( $options['ignore_current'] );

	$args = wp_parse_args( $options, $args );
	return z_get_zoninator()->get_zone_posts( $zone, $args );
}