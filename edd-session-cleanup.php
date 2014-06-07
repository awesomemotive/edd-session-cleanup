<?php
/*
 * Plugin Name: Easy Digital Downloads - Session Cleanup
 * Description: Provides a Tool to clean up sessions for when the Cron Job has been failing
 * Author: Chris Klosowski
 * Version: 1.0
 */

class EDD_Session_Cleanup {

	public function __construct() {
		add_filter( 'edd_tools_tabs', array( $this, 'add_sessions_tab' ), 10, 1 );
		add_action( 'edd_tools_tab_cleanup_sessions', array( $this, 'clean_sessions_area' ) );
		add_action( 'edd_cleanup_sessions', array( $this, 'cleanup_the_sessions' ) );
	}

	public function add_sessions_tab( $tabs ) {
		$tabs['cleanup_sessions'] = __( 'Cleanup Sessions', 'edd' );

		return $tabs;
	}

	public function clean_sessions_area() {
	?>
		<div class="postbox">
			<h3><span><?php _e( 'Cleanup Sessions', 'edd' ); ?></span></h3>
			<div class="inside">
				<p><?php _e( 'Use this tool to clean up your store\'s session entries. Removes invalid and expired sessions.', 'edd' ); ?></p>
				<form method="post" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-tools&tab=cleanup_sessions' ); ?>">
					<p><input type="hidden" name="edd_action" value="cleanup_sessions" /></p>
					<p>
						<?php submit_button( __( 'Cleanup Sessions', 'edd' ), 'secondary', 'submit', false ); ?>
					</p>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
	<?php
	}

	public function cleanup_the_sessions() {
		global $wpdb;

		// Clean up expired sessions
		$expiration_keys = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '_wp_session_expires_%'" );
		$session_keys = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_wp_session_%' AND option_name NOT LIKE '_wp_session_expires_%'" );
		foreach( $session_keys as $session ) {
			$session_ids[] = substr( $session->option_name, 20 );
		}

		$now = time();
		$twentyfifty = 2524608000;

		$expired_sessions   = array();
		$invalid_sessions   = array();
		$missing_expiration = array();

		$expiration_key_ids = array();

		foreach( $expiration_keys as $expiration ) {
			// Get the session ID by parsing the option_name
			$session_id = substr( $expiration->option_name, 20 );
			$expiration_key_ids[] = $session_id;

			if ( $now > intval( $expiration->option_value ) ) {
				// If the session has expired
				$expired_sessions[] = $expiration->option_name;
				$expired_sessions[] = "_wp_session_$session_id";
			} elseif( $expiration->option_value > $twentyfifty ) {
				// If the session is after 2050 (from a bug in the session usage < 2.0)
				$invalid_sessions[] = $expiration->option_name;
				$invalid_sessions[] = "_wp_session_$session_id";
			}
		}

		foreach ( $session_keys as $existing_session ) {
			$session_id = substr( $existing_session->option_name, 12 );
			if ( !in_array( $session_id, $expiration_key_ids ) ) {
				$sessions_without_expirations[] = $existing_session->option_name;
			}
		}

		// Delete all expired sessions in a single query
		if ( ! empty( $expired_sessions ) ) {
			$expired_names = implode( "','", $expired_sessions );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name IN ('$expired_names')" );
		}

		// Delete all invalid sessions in a single query
		if ( ! empty( $invalid_sessions ) ) {
			$invalid_names = implode( "','", $invalid_sessions );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name IN ('$invalid_names')" );
		}

		// Delete all sessions without an expiration entry
		if ( ! empty( $sessions_without_expirations ) ) {
			$missing_names = implode( "','", $sessions_without_expirations );
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name IN ('$missing_names')" );
		}
	}
}

new EDD_Session_Cleanup;