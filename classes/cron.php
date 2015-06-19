<?php
defined( 'WPINC' ) or die;

if ( ! defined( 'EMPTY_TRASH_DAYS' ) ) {
	define( 'EMPTY_TRASH_DAYS', 30 );
}

class WP_Digest_Cron {
	/**
	 * @var array The plugin options.
	 */
	protected static $options;

	/**
	 * @var false|WP_User User object or false.
	 */
	protected static $user;

	/**
	 * This method hooks to the cron action to process the queue.
	 */
	public static function init() {
		self::$options = get_option( 'digest_frequency', array(
			'period' => 'weekly',
			'hour'   => 18,
			'day'    => absint( get_option( 'start_of_week' ) ),
		) );

		self::run();
		if ( self::ready() ) {
			//self::run();
		}
	}

	/**
	 * Checks if it's already time to send the emails.
	 *
	 * @return bool True if the queue can be processed, false otherwise.
	 */
	public static function ready() {
		// Return early if the hour is wrong
		if ( absint( self::$options['hour'] ) !== absint( date_i18n( 'G' ) ) ) {
			return false;
		}

		// Return early if the day is wrong
		if ( 'weekly' === self::$options['period'] && absint( self::$options['day'] ) !== absint( date_i18n( 'w' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Run Boy Run
	 */
	public static function run() {
		$queue = WP_Digest_Queue::get();

		if ( empty( $queue ) ) {
			return;
		}

		// Load WP_Locale and other needed functions
		require_once( ABSPATH . WPINC . '/pluggable.php' );
		require_once( ABSPATH . WPINC . '/locale.php' );
		require_once( ABSPATH . WPINC . '/rewrite.php' );
		$GLOBALS['wp_locale']  = new WP_Locale();
		$GLOBALS['wp_rewrite'] = new WP_Rewrite();

		do_action( 'digest_cron_before', $queue, self::$options );

		$subject = ( 'daily' === self::$options['period'] ) ? __( 'Today on %s', 'digest' ) : __( 'Past Week on %s', 'digest' );
		$subject = sprintf( $subject, get_bloginfo( 'name' ) );

		foreach ( $queue as $recipient => $items ) {
			$events = array();

			self::$user = get_user_by( 'email', $recipient );

			foreach ( $items as $item ) {
				$method = array( 'WP_Digest_Cron', 'get_' . $item[1] . '_message' );
				if ( is_callable( $method ) ) {
					$events[ $item[1] ][] = call_user_func( $method, $item[2], $item[0] );
				}
			}

			ksort( $events );

			$message = '';

			foreach ( $events as $event => $entries ) {
				if ( empty( $entries ) ) {
					continue;
				}

				switch ( $event ) {
					case 'comment_notification':
						$message .= '<p><b>' . __( 'New Comments', 'digest' ) . '</b></p>';
						$message .= implode( '', $entries );
						break;
					case 'comment_moderation':
						$message .= '<p><b>' . __( 'Pending Comments', 'digest' ) . '</b></p>';
						$message .= '<p>' . sprintf(
								_n(
									'Currently %s comment is waiting for approval.',
									'Currently %s comments are waiting for approval.',
									count( $entries ),
									'digest'
								),
								number_format_i18n( count( $entries ) )
							) . '</p>';

						$message .= implode( '', $entries );

						$message .= sprintf( __( 'Please visit the <a href="%s">moderation panel</a>.', 'digest' ), admin_url( 'edit-comments.php?comment_status=moderated' ) ) . '<br />';
						break;
					case 'new_user_notification':
						$message .= '<p><b>' . __( 'New User Signups', 'digest' ) . '</b></p>';
						$message .= '<p>' . _n( 'The following user signed up on your site:', 'The following users signed up on your site:', count( $entries ), 'digest' ) . '</p>';
						$message .= '<ul>' . implode( '', $entries ) . '</ul>';
						break;
					case 'password_change_notification':
						$message .= '<p><b>' . __( 'Password Changes', 'digest' ) . '</b></p>';
						$message .= '<p>' . _n( 'The following user lost and changed his password:', 'The following users lost and changed their passwords:', count( $entries ), 'digest' ) . '</p>';
						$message .= '<ul>' . implode( '', $entries ) . '</ul>';
						break;
					default:
						break;
				}
			}

			if ( '' === $message ) {
				return;
			}

			if ( self::$user ) {
				$salutation = sprintf( __( 'Hi %s', 'digest' ), self::$user->display_name );
			} else {
				$salutation = __( 'Hi there', 'digest' );
			}

			$message = '<p>' . $salutation . '</p><p>' . __( "See what's happening on your site:", 'digest' ) . '</p>' . $message;

			// Send digest
			wp_mail( $recipient, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}

		// Clear queue
		WP_Digest_Queue::clear();
	}

	/**
	 * Get the comment notification message.
	 *
	 * @param int $comment_id The comment ID.
	 * @param int $time       The timestamp when the comment was written.
	 *
	 * @return string The comment moderation message.
	 */
	public static function get_comment_notification_message( $comment_id, $time ) {
		/** @var object $comment */
		$comment = get_comment( $comment_id );

		if ( null === $comment ) {
			return '';
		}

		$message = self::comment_message( $comment, $time );

		$actions = array();

		if ( self::$user && user_can( self::$user, 'edit_comment' ) ) {
			if ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = _x( 'Trash', 'verb', 'digest' );
			} else {
				$actions['delete'] = __( 'Delete', 'digest' );
			}
			$actions['spam'] = _x( 'Spam', 'verb', 'digest' );
		}

		$message .= self::comment_action_links( $actions, $comment_id );

		return $message;
	}

	/**
	 * Get the comment moderation message.
	 *
	 * @param int $comment_id The comment ID.
	 * @param int $time       The timestamp when the comment was written.
	 *
	 * @return string The comment moderation message.
	 */
	public static function get_comment_moderation_message( $comment_id, $time ) {
		/** @var object $comment */
		$comment = get_comment( $comment_id );

		if ( null === $comment ) {
			return '';
		}

		$message = self::comment_message( $comment, $time );

		$message .= '<a href="' . get_comment_link( $comment_id ) . '">' . __( 'Permalink', 'digest' ) . '</a>';

		if ( self::$user && user_can( self::$user, 'edit_comment' ) ) {
			$message .= ' | ';

			$actions = array( 'approve' => __( 'Approve', 'digest' ) );
			if ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = _x( 'Trash', 'verb', 'digest' );
			} else {
				$actions['delete'] = __( 'Delete', 'digest' );
			}
			$actions['spam'] = _x( 'Spam', 'verb', 'digest' );
		}

		$message .= self::comment_action_links( $actions, $comment_id ) . '<br /><br />';

		return $message;
	}

	/**
	 * Get the new user notification message.
	 *
	 * @param int $user_id The user ID.
	 * @param int $time    The timestamp when the user signed up.
	 *
	 * @return string The new user notification message.
	 */
	public static function get_new_user_notification_message( $user_id, $time ) {
		$user = new WP_User( $user_id );

		return sprintf(
			__( '<li>%s (ID: %s) %s ago</li>', 'digest' ),
			$user->display_name, $user->ID,
			human_time_diff( $time, current_time( 'timestamp' ) )
		);
	}

	/**
	 * Get the password change notification message.
	 *
	 * @param int $user_id The user ID.
	 * @param int $time    The timestamp when the user changed his password.
	 *
	 * @return string The password change notification message.
	 */
	public static function get_password_change_notification_message( $user_id, $time ) {
		$user = new WP_User( $user_id );

		return sprintf(
			__( '<li>%s (ID: %s) %s ago</li>', 'digest' ),
			$user->display_name, $user->ID,
			human_time_diff( $time, current_time( 'timestamp' ) )
		);
	}

	/**
	 * Get the comment message.
	 *
	 * @param object $comment The comment object.
	 * @param int    $time    The timestamp when the comment was written.
	 *
	 * @return string The comment message.
	 */
	protected static function comment_message( $comment, $time ) {
		$post_link = '<a href="' . esc_url( get_permalink( $comment->comment_post_ID ) ) . '">' . get_the_title( $comment->comment_post_ID ) . '</a>';

		switch ( $comment->comment_type ) {
			case 'trackback':
				$message = sprintf( __( 'Trackback on %1$s %2$s ago:', 'digest' ), $post_link, human_time_diff( $time, current_time( 'timestamp' ) ) ) . '<br />';
				$message .= sprintf( __( 'Website: %s', 'digest' ), '<a href="' . esc_url( $comment->comment_author_url ) . '">' . esc_html( $comment->comment_author ) . '</a>' ) . '<br />';
				$message .= sprintf( __( 'Excerpt: %s', 'digest' ), '<br />' . wpautop( $comment->comment_content ) );
				break;
			case 'pingback':
				$message = sprintf( __( 'Pingback on %1$s %2$s ago:', 'digest' ), $post_link, human_time_diff( $time, current_time( 'timestamp' ) ) ) . '<br />';
				$message .= sprintf( __( 'Website: %s', 'digest' ), '<a href="' . esc_url( $comment->comment_author_url ) . '">' . esc_html( $comment->comment_author ) . '</a>' ) . '<br />';
				$message .= sprintf( __( 'Excerpt: %s', 'digest' ), '<br />' . wpautop( $comment->comment_content ) );
				break;
			default: // Comments
				if ( ! empty( $comment->comment_author_url ) ) {
					$author = sprintf( __( 'Author: %s', 'digest' ), '<a href="' . esc_url( $comment->comment_author_url ) . '">' . esc_html( $comment->comment_author ) . '</a>' );
				} else {
					$author = sprintf( __( 'Author: %s', 'digest' ), esc_html( $comment->comment_author ) );
				}
				$message = sprintf( __( 'Comment on %1$s %2$s ago:', 'digest' ), $post_link, human_time_diff( $time, current_time( 'timestamp' ) ) ) . '<br />';
				$message .= $author . '<br />';
				$message .= sprintf( __( 'Email: %s', 'digest' ), '<a href="mailto:' . esc_attr( $comment->comment_author_email ) . '">' . esc_html( $comment->comment_author_email ) . '</a>' ) . '<br />';
				$message .= sprintf( __( 'Comment: %s', 'digest' ), '<br />' . wpautop( $comment->comment_content ) );
				break;
		}

		return $message;
	}

	/**
	 * Add action links to the message
	 *
	 * @param array $actions    Actions for that comment.
	 * @param int   $comment_id The comment ID.
	 *
	 * @return string The comment action links.
	 */
	protected static function comment_action_links( $actions, $comment_id ) {
		$links = '';
		foreach ( $actions as $action => $label ) {
			$links .= self::comment_action_link( $label, $action, $comment_id ) . ' | ';
		}

		$links = rtrim( $links, '| ' );

		return $links;
	}

	/**
	 * Creates a comment action link
	 *
	 * @param string $label      The action label, like "Approve comment" or "Trash comment".
	 * @param string $action     The action itself, like approve or trash.
	 * @param int    $comment_id The comment ID.
	 *
	 * @return string The comment action link.
	 */
	protected static function comment_action_link( $label, $action, $comment_id ) {
		$url = admin_url( sprintf( 'comment.php?action=%s&c=%d', $action, $comment_id ) );

		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $label ) );
	}
}

add_action( 'digest_event', array( 'WP_Digest_Cron', 'init' ) );