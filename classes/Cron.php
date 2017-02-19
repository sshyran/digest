<?php
/**
 * WP Digest Cron implementation.
 *
 * @package WP_Digest
 */

namespace Required\Digest;

if ( ! defined( 'EMPTY_TRASH_DAYS' ) ) {
	define( 'EMPTY_TRASH_DAYS', 30 );
}

/**
 * Cron class.
 *
 * It's run every hour.
 */
class Cron {
	/**
	 * The plugin options.
	 *
	 * @var array The plugin options.
	 */
	protected static $options;

	/**
	 * This method hooks to the cron action to process the queue.
	 */
	public static function init() {
		self::$options = get_option( 'digest_frequency', array(
			'period' => 'weekly',
			'hour'   => 18,
			'day'    => absint( get_option( 'start_of_week' ) ),
		) );

		if ( self::ready() ) {
			self::load_globals();
			self::run();
		}
	}

	/**
	 * Checks if it's already time to send the emails.
	 *
	 * @return bool True if the queue can be processed, false otherwise.
	 */
	protected static function ready() {
		// Return early if the hour is wrong.
		if ( absint( self::$options['hour'] ) !== absint( date_i18n( 'G' ) ) ) {
			return false;
		}

		// Return early if the day is wrong.
		if (
			'weekly' === self::$options['period'] &&
			absint( self::$options['day'] ) !== absint( date_i18n( 'w' ) )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Load required files and set up needed globals.
	 */
	protected static function load_globals() {
		// Load WP_Locale and other needed functions.
		require_once( ABSPATH . WPINC . '/pluggable.php' );
		require_once( ABSPATH . WPINC . '/locale.php' );
		require_once( ABSPATH . WPINC . '/rewrite.php' );

		$GLOBALS['wp_locale']  = new \WP_Locale();
		$GLOBALS['wp_rewrite'] = new \WP_Rewrite();
	}

	/**
	 * Run Boy Run
	 */
	protected static function run() {
		$queue = Queue::get();

		if ( empty( $queue ) ) {
			return;
		}

		// Set up the correct subject.
		$subject = ( 'daily' === self::$options['period'] ) ? __( 'Today on %s', 'digest' ) : __( 'Past Week on %s', 'digest' );

		/**
		 * Filter the digest subject.
		 *
		 * @param string $subject The digest's subject line.
		 *
		 * @return string The filtered subject.
		 */
		$subject = apply_filters( 'digest_cron_email_subject', sprintf( $subject, get_bloginfo( 'name' ) ) );

		wp_digest()->send_email( $subject );

		// Clear queue.
		Queue::clear();
	}
}