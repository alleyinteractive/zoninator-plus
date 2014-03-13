<?php

// If possible set the max execution time to run longer than the usual default 30 seconds
if( !ini_get('safe_mode') ) ini_set('max_execution_time',300);

// Load the WordPress environment
require_once('../../../../wp-load.php');

// Call the main function
exit(main_zoninator_plus());

/**
 * Main function
 *
 * @return void
 */
function main_zoninator_plus() {
	
	// Custom error handler
	set_error_handler( 'zp_handle_error' );
	
	// Generate the path to the tmp file that will indicate this process is currently running
	$backfill_tmp_file = sprintf(
		'%s/%s',
		sys_get_temp_dir(),
		"zp-backfill-running.tmp"
	);
	
	// Check for the existence of the tmp file
	if( file_exists( $backfill_tmp_file ) ) {
		if ( (time() - filemtime($backfill_tmp_file)) < 1800 ) {
			trigger_error( 
				sprintf( 
					"\r\nERROR: There may be already a backfill process running. Please delete %s and try again.\r\n\n",
					$backfill_tmp_file
				) 
			);
			exit(1);
		}
	}
	
	// Create the tmp file to prevent another backfill process from running while this is in process
	touch( $backfill_tmp_file );
	
	// Trigger the backfill process for all zones
	_e( "Starting backfill for all zones", "zoninator-plus" );
	echo "\r\n\r\n";
	global $zoninator_plus;
	echo $zoninator_plus->do_zone_group_backfill( "", "console" );
	echo "\r\n";
	_e( "Backfill finished for all zones", "zoninator-plus" );
	echo "\r\n";
	
	// Remove the tmp file after processing is complete
	unlink( $backfill_tmp_file );
}

/**
 * Custom error handler for the script
 *
 * @params string $error_level
 * @params string $error_message
 * @params string $error_file
 * @params string $error_line
 * @params string $error_context
 *
 * @return bool
 */
function zp_handle_error ($error_level, $error_message, $error_file, $error_line, $error_context) { // parent

	$errLevel = "";
	
	switch ($error_level) {
		case E_ERROR:
			$errLevel = "FATAL ERROR";
			break;
		case E_NOTICE:
			$errLevel = "NOTICE";
			break;
		case E_WARNING:
			$errLevel = "WARNING";
			break;
		default:
			$errLevel = "ERROR";
			break;
	}

	// log error to syslog.
	$msg = sprintf(
		"Zoninator Plus Backfill %s: %s in file %s on line %s.\r\n", 
		$errLevel, 
		$error_message, 
		$error_file, 
		$error_line
	);
	syslog(LOG_ERR, $msg);
	return FALSE;
}



