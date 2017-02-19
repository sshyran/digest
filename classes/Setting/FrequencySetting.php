<?php
/**
 * Frequency setting class.
 *
 * @package Digest
 */

namespace Required\Digest\Setting;

use Required\Digest\Plugin;

/**
 * Setting for the digest frequency.
 *
 * @since 2.0.0
 */
class FrequencySetting implements SettingInterface {
	/**
	 * Registers the setting.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function register() {
		register_setting(
			'general',
			'digest_frequency',
			array( $this, 'sanitize_frequency_option' )
		);

		add_action( 'admin_init', array( $this, 'add_settings_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Add an action link pointing to the options page.
		add_action( 'plugin_action_links_' . plugin_basename( \Required\Digest\PLUGIN_FILE ), array(
			$this,
			'plugin_action_links',
		) );
	}

	/**
	 * Adds a new settings section and settings fields to Settings -> General.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function add_settings_fields() {
		add_settings_section(
			'digest_notifications',
			__( 'Email Notifications', 'digest' ),
			function () {
				esc_html_e( "You get a daily or weekly digest of what's happening on your site. Here you can configure its frequency.", 'digest' );
			},
			'general'
		);

		add_settings_field(
			'digest_frequency',
			sprintf( '<label for="digest_frequency_period" id="digest">%s</label>', __( 'Frequency', 'digest' ) ),
			array( $this, 'settings_field_frequency' ),
			'general',
			'digest_notifications'
		);
	}

	/**
	 * Settings field callback that prints the actual input fields.
	 *
	 * @since  2.0.0
	 * @access public
	 */
	public function settings_field_frequency() {
		$options     = get_option( 'digest_frequency', array(
			'period' => 'weekly',
			'hour'   => 18,
			'day'    => absint( get_option( 'start_of_week' ) ),
		) );
		$time_format = get_option( 'time_format' );
		?>
		<p>
			<?php esc_html_e( 'Send me a digest of new site activity', 'digest' ); ?>
			<select name="digest_frequency[period]" id="digest_frequency_period">
				<option value="daily" <?php selected( 'daily', $options['period'] ); ?>>
					<?php echo esc_attr_x( 'every day', 'frequency', 'digest' ); ?>
				</option>
				<option value="weekly" <?php selected( 'weekly', $options['period'] ); ?>>
					<?php echo esc_attr_x( 'every week', 'frequency', 'digest' ); ?>
				</option>
			</select>
			<span id="digest_frequency_hour_wrapper">
				<?php esc_html_e( 'at', 'digest' ); ?>
				<select name="digest_frequency[hour]" id="digest_frequency_hour">
					<?php for ( $hour = 0; $hour <= 23; $hour ++ ) : ?>
						<option value="<?php echo esc_attr( $hour ); ?>" <?php selected( $hour, $options['hour'] ); ?>>
							<?php echo esc_html( date( $time_format, mktime( $hour, 0, 0, 1, 1, 2011 ) ) ); ?>
						</option>
					<?php endfor; ?>
				</select>
				<?php esc_html_e( "o'clock", 'digest' ); ?>
			</span>
			<span id="digest_frequency_day_wrapper" <?php echo 'weekly' !== $options['period'] ? 'class="digest-hidden"' : ''; ?>>
				<?php
				esc_html_e( 'on', 'digest' );

				global $wp_locale;
				?>
				<select name="digest_frequency[day]" id="digest_frequency_day">
					<?php for ( $day_index = 0; $day_index <= 6; $day_index ++ ) : ?>
						<option value="<?php echo esc_attr( $day_index ); ?>" <?php selected( $day_index, $options['day'] ); ?>>
							<?php echo esc_html( $wp_locale->get_weekday( $day_index ) ); ?>
						</option>
					<?php endfor; ?>
				</select>
			</span>
		</p>
		<?php
	}

	/**
	 * Sanitize the digest frequency option.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param array $value The POST da.
	 *
	 * @return array The sanitized frequency option.
	 */
	public function sanitize_frequency_option( array $value ) {
		if ( 'daily' !== $value['period'] ) {
			$value['period'] = 'weekly';
		}

		$value['hour'] = filter_var(
			$value['hour'],
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'default'   => 18,
					'min_range' => 0,
					'max_range' => 23,
				),
			)
		);

		$value['day'] = filter_var(
			$value['day'],
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'default'   => get_option( 'start_of_week', 0 ),
					'min_range' => 0,
					'max_range' => 6,
				),
			)
		);

		return $value;
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		if ( 'options-general.php' === $hook_suffix ) {
			$suffix = SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_script( 'digest', plugin_dir_url( \Required\Digest\PLUGIN_DIR ) . 'js/digest' . $suffix . '.js', array(), Plugin::VERSION, true );
			wp_enqueue_style( 'digest', plugin_dir_url( \Required\Digest\PLUGIN_DIR ) . 'css/digest' . $suffix . '.css', array(), Plugin::VERSION );
		}
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param array $links Plugin action links.
	 *
	 * @return array The modified plugin action links
	 */
	public function plugin_action_links( array $links ) {
		return array_merge(
			array(
				'settings' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'options-general.php#digest' ) ),
					__( 'Settings', 'digest' )
				),
			),
			$links
		);
	}
}
