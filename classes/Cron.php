<?php
/**
 * Cron class.
 *
 * @package Digest
 */

namespace Required\Digest;

/**
 * Digest Cron implementation.
 *
 * By default, the cron is run every hour.
 *
 * @since 2.0.0
 */
class Cron {
	/**
	 * The plugin options.
	 *
	 * @since  2.0.0
	 * @access protected
	 *
	 * @var array The plugin options.
	 */
	protected static $options;

	/**
	 * This method hooks to the cron action to process the queue.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public static function init() {
		self::$options = get_option( 'digest_frequency', array(
			'period' => 'weekly',
			'hour'   => 18,
			'day'    => absint( get_option( 'start_of_week' ) ),
		) );

		self::ready() && self::run();
	}

	/**
	 * Checks if it's already time to send the emails.
	 *
	 * @since  1.0.0
	 * @access protected
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
	 * Processes the queue and sends the emails.
	 *
	 * Run Boy Run.
	 *
	 * @since  1.0.0
	 * @access protected
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

		digest()->send_email( $subject );

		// Clear queue.
		Queue::clear();
	}
}
