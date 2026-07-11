<?php
/**
 * Main plugin class.
 *
 * @package CMPlyCookieConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CMPly plugin.
 */
final class CMPly_Cookie_Consent {
	const OPTION_NAME = 'cmply_options';
	const SECRET_OPTION_NAME = 'cmply_api_key';
	const SERVICE_HOST = 'cmply.app';
	private static $connection_state_error = 'callback_invalid';

	/**
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults() {
		return array(
			'enabled'       => 1,
			'auto_inject'   => 1,
			'site_id'       => '',
			'sdk_base_url'  => 'https://cmply.app',
			'sdk_version'   => '',
			'language'      => '',
			'exclude_paths' => '',
			'account_email' => '',
			'plan'          => 'Free',
			'pageviews_used'  => 0,
			'pageviews_limit' => 0,
			'connection_id' => '',
		);
	}

	/**
	 * Boot plugin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		load_plugin_textdomain( 'cmply', false, dirname( CMPLY_COOKIE_CONSENT_BASENAME ) . '/languages' );

		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_cmply_connect_callback', array( __CLASS__, 'handle_connect_callback' ) );
		add_action( 'admin_post_cmply_disconnect', array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_cmply_verify_connection', array( __CLASS__, 'handle_verify_connection' ) );
		add_action( 'admin_notices', array( __CLASS__, 'configuration_notice' ) );
		add_filter( 'plugin_action_links_' . CMPLY_COOKIE_CONSENT_BASENAME, array( __CLASS__, 'settings_link' ) );

		add_action( 'wp_head', array( __CLASS__, 'print_sdk_script' ), -999 );
	}

	/**
	 * Set default options on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_NAME ) ) {
			add_option( self::OPTION_NAME, self::defaults() );
		}
	}

	/**
	 * Get merged options.
	 *
	 * @return array<string, mixed>
	 */
	private static function options() {
		$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::defaults() );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public static function register_admin_page() {
		add_options_page(
			__( 'CMPly', 'cmply' ),
			__( 'CMPly', 'cmply' ),
			'manage_options',
			'cmply',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Load admin assets for CMPly screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_cmply' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'cmply-admin',
			plugins_url( 'assets/admin.css', CMPLY_COOKIE_CONSENT_FILE ),
			array(),
			CMPLY_COOKIE_CONSENT_VERSION
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'cmply',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'cmply_main',
			__( 'Connection', 'cmply' ),
			function () {
				echo '<p>' . esc_html__( 'Paste the Site ID from your CMPly dashboard. The SDK is printed early in the document head so it can block scripts before they run.', 'cmply' ) . '</p>';
			},
			'cmply'
		);

		self::add_field( 'enabled', __( 'Enable CMPly', 'cmply' ), 'render_enabled_field' );
		self::add_field( 'auto_inject', __( 'Auto-inject SDK', 'cmply' ), 'render_auto_inject_field' );
		self::add_field( 'site_id', __( 'Site ID', 'cmply' ), 'render_site_id_field' );
		self::add_field( 'sdk_base_url', __( 'SDK Base URL', 'cmply' ), 'render_sdk_base_url_field' );
		self::add_field( 'sdk_version', __( 'SDK Version', 'cmply' ), 'render_sdk_version_field' );
		self::add_field( 'language', __( 'Language override', 'cmply' ), 'render_language_field' );
		self::add_field( 'exclude_paths', __( 'Exclude Paths', 'cmply' ), 'render_exclude_paths_field' );
	}

	/**
	 * Register a settings field.
	 *
	 * @param string $id Field ID.
	 * @param string $title Field label.
	 * @param string $callback Callback method.
	 * @return void
	 */
	private static function add_field( $id, $title, $callback ) {
		add_settings_field(
			$id,
			$title,
			array( __CLASS__, $callback ),
			'cmply',
			'cmply_main'
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_options( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$current  = self::options();
		$output   = array();

		$output['enabled']       = empty( $input['enabled'] ) ? 0 : 1;
		$output['auto_inject']   = empty( $input['auto_inject'] ) ? 0 : 1;
		$output['site_id']       = isset( $input['site_id'] ) ? sanitize_text_field( wp_unslash( $input['site_id'] ) ) : '';
		$output['sdk_base_url']  = isset( $input['sdk_base_url'] ) ? self::sanitize_service_url( wp_unslash( $input['sdk_base_url'] ) ) : $defaults['sdk_base_url'];
		$output['sdk_version']   = isset( $input['sdk_version'] ) ? preg_replace( '/[^a-zA-Z0-9._-]/', '', sanitize_text_field( wp_unslash( $input['sdk_version'] ) ) ) : '';
		$output['language']      = isset( $input['language'] ) ? preg_replace( '/[^a-zA-Z_-]/', '', sanitize_text_field( wp_unslash( $input['language'] ) ) ) : '';
		$output['exclude_paths'] = isset( $input['exclude_paths'] ) ? sanitize_textarea_field( wp_unslash( $input['exclude_paths'] ) ) : '';
		$output['account_email'] = isset( $input['account_email'] ) ? sanitize_email( wp_unslash( $input['account_email'] ) ) : $current['account_email'];
		$output['plan']          = isset( $input['plan'] ) ? sanitize_text_field( wp_unslash( $input['plan'] ) ) : $current['plan'];
		$output['pageviews_used']  = max( 0, (int) $current['pageviews_used'] );
		$output['pageviews_limit'] = (int) $current['pageviews_limit'];
		$output['connection_id'] = $current['connection_id'];

		if ( empty( $output['sdk_base_url'] ) ) {
			$output['sdk_base_url'] = $defaults['sdk_base_url'];
		}

		return $output;
	}

	/**
	 * Render enabled field.
	 *
	 * @return void
	 */
	public static function render_enabled_field() {
		$options = self::options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( 1, (int) $options['enabled'] ); ?> />
			<?php esc_html_e( 'Load CMPly on the public website', 'cmply' ); ?>
		</label>
		<?php
	}

	/**
	 * Render auto-inject field.
	 *
	 * @return void
	 */
	public static function render_auto_inject_field() {
		$options = self::options();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[auto_inject]" value="1" <?php checked( 1, (int) $options['auto_inject'] ); ?> />
			<?php esc_html_e( 'Automatically print the CMPly SDK in wp_head', 'cmply' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Turn this off only if the CMPly script is already inserted manually in your theme or header manager. Keep exactly one CMPly SDK script on the page.', 'cmply' ); ?></p>
		<?php
	}

	/**
	 * Render site ID field.
	 *
	 * @return void
	 */
	public static function render_site_id_field() {
		$options = self::options();
		?>
		<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[site_id]" value="<?php echo esc_attr( $options['site_id'] ); ?>" placeholder="YOUR_SITE_ID" autocomplete="off" />
		<p class="description"><?php esc_html_e( 'Find this in CMPly dashboard under your site installation tab.', 'cmply' ); ?></p>
		<?php
	}

	/**
	 * Render SDK base URL field.
	 *
	 * @return void
	 */
	public static function render_sdk_base_url_field() {
		$options = self::options();
		?>
		<input class="regular-text code" type="url" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sdk_base_url]" value="<?php echo esc_attr( $options['sdk_base_url'] ); ?>" />
		<p class="description"><?php esc_html_e( 'For security, connection credentials are sent only to https://cmply.app.', 'cmply' ); ?></p>
		<?php
	}

	/**
	 * Render SDK version field.
	 *
	 * @return void
	 */
	public static function render_sdk_version_field() {
		$options = self::options();
		?>
		<input class="small-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sdk_version]" value="<?php echo esc_attr( $options['sdk_version'] ); ?>" placeholder="3" />
		<p class="description"><?php esc_html_e( 'Optional cache-busting value. CMPly currently recommends v=3 when you need to force a refresh.', 'cmply' ); ?></p>
		<?php
	}

	/**
	 * Render language field.
	 *
	 * @return void
	 */
	public static function render_language_field() {
		$options = self::options();
		?>
		<input class="small-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[language]" value="<?php echo esc_attr( $options['language'] ); ?>" placeholder="auto" />
		<p class="description"><?php esc_html_e( 'Leave empty to auto-detect each visitor browser language from CMPly dashboard translations. Set only to force a specific language, for example en, ru, de, fr, es, or it.', 'cmply' ); ?></p>
		<?php
	}

	/**
	 * Render exclude paths field.
	 *
	 * @return void
	 */
	public static function render_exclude_paths_field() {
		$options = self::options();
		?>
		<textarea class="large-text code" rows="5" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[exclude_paths]" placeholder="/checkout/&#10;/account/*"><?php echo esc_textarea( $options['exclude_paths'] ); ?></textarea>
		<p class="description"><?php esc_html_e( 'One path per line. Use * as a wildcard. CMPly will not load on matching public URLs.', 'cmply' ); ?></p>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = self::options();
		$tab     = self::current_tab();
		?>
		<div class="wrap cmply-admin">
			<div class="cmply-shell">
				<?php self::render_header( $tab, $options ); ?>
				<?php self::render_connection_notice( $options ); ?>

				<?php
				if ( 'gcm' === $tab ) {
					self::render_gcm_screen( $options );
				} elseif ( 'site-settings' === $tab ) {
					self::render_site_settings_screen( $options );
				} else {
					self::render_dashboard_screen( $options );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get selected admin tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

		if ( ! in_array( $tab, array( 'dashboard', 'gcm', 'site-settings' ), true ) ) {
			$tab = 'dashboard';
		}

		return $tab;
	}

	/**
	 * Handle return from CMPly web app connection flow.
	 *
	 * @return void
	 */
	public static function handle_connect_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to connect CMPly.', 'cmply' ) );
		}

		$state = isset( $_GET['cmply_state'] ) ? sanitize_text_field( wp_unslash( $_GET['cmply_state'] ) ) : '';
		if ( ! self::consume_connection_state( $state ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=' . self::$connection_state_error ) );
			exit;
		}

		$site_id         = isset( $_GET['site_id'] ) ? sanitize_text_field( wp_unslash( $_GET['site_id'] ) ) : '';
		$connection_id   = isset( $_GET['connection_id'] ) ? sanitize_text_field( wp_unslash( $_GET['connection_id'] ) ) : '';
		$connection_code = isset( $_GET['connection_code'] ) ? sanitize_text_field( wp_unslash( $_GET['connection_code'] ) ) : '';
		$exchange_url    = isset( $_GET['exchange_url'] ) ? esc_url_raw( wp_unslash( $_GET['exchange_url'] ) ) : '';
		$options         = self::options();
		$expected_url    = self::service_endpoint( $options, '/api/integrations/wordpress/connect/exchange' );

		if ( empty( $site_id ) || empty( $connection_id ) || empty( $connection_code ) || empty( $expected_url ) || $exchange_url !== $expected_url ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=missing_site_id' ) );
			exit;
		}

		$response = wp_safe_remote_post(
			$exchange_url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'connectionId'   => $connection_id,
						'connectionCode' => $connection_code,
						'siteUrl'        => home_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=service_unavailable' ) );
			exit;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error = in_array( $response_code, array( 409, 410 ), true ) ? 'exchange_expired' : 'exchange_failed';
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=' . $error ) );
			exit;
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) || empty( $payload['ok'] ) || empty( $payload['data']['apiKey'] ) || $site_id !== $payload['data']['siteId'] ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=invalid_exchange' ) );
			exit;
		}

		$options['enabled']       = 1;
		$options['site_id']       = $site_id;
		$options['connection_id'] = $connection_id;
		self::save_api_key( sanitize_text_field( $payload['data']['apiKey'] ) );
		self::apply_account_snapshot( $options, $payload['data']['account'] ?? array() );

		update_option( self::OPTION_NAME, $options );

		wp_safe_redirect( admin_url( 'options-general.php?page=cmply&cmply_connected=1' ) );
		exit;
	}

	/**
	 * Disconnect CMPly from this WordPress site.
	 *
	 * @return void
	 */
	public static function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to disconnect CMPly.', 'cmply' ) );
		}

		check_admin_referer( 'cmply_disconnect' );

		$options                  = self::options();
		$options['site_id']       = '';
		$options['account_email'] = '';
		$options['plan']          = 'Free';
		$options['pageviews_used']  = 0;
		$options['pageviews_limit'] = 0;
		$options['connection_id'] = '';
		delete_option( self::SECRET_OPTION_NAME );

		update_option( self::OPTION_NAME, $options );

		wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_disconnected=1' ) );
		exit;
	}

	/**
	 * Verify the saved server-to-server connection.
	 *
	 * @return void
	 */
	public static function handle_verify_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to verify CMPly.', 'cmply' ) );
		}
		check_admin_referer( 'cmply_verify_connection' );
		$options = self::options();
		$api_key = get_option( self::SECRET_OPTION_NAME, '' );
		if ( empty( $options['site_id'] ) || empty( $options['connection_id'] ) || empty( $api_key ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=not_connected' ) );
			exit;
		}
		$url = self::service_endpoint( $options, '/api/integrations/wordpress/connect/verify' );
		if ( empty( $url ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=invalid_service_url' ) );
			exit;
		}
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'connectionId' => $options['connection_id'],
						'siteId'       => $options['site_id'],
						'apiKey'       => $api_key,
						'siteUrl'      => home_url(),
					)
				),
			)
		);
		$status = 'cmply_error=verify_failed';
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$payload = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $payload ) && ! empty( $payload['ok'] ) && ! empty( $payload['data']['account'] ) ) {
				self::apply_account_snapshot( $options, $payload['data']['account'] );
				update_option( self::OPTION_NAME, $options );
				$status = 'cmply_verified=1';
			}
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&' . $status ) );
		exit;
	}

	/**
	 * Show connection results and actionable errors.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return void
	 */
	private static function render_connection_notice( $options ) {
		$error = isset( $_GET['cmply_error'] ) ? sanitize_key( wp_unslash( $_GET['cmply_error'] ) ) : '';
		$messages = array(
			'missing_site_id'     => __( 'The callback data was incomplete. Start the connection again.', 'cmply' ),
			'callback_missing'    => __( 'CMPly returned without the WordPress security state. Start again and do not reuse an older CMPly tab.', 'cmply' ),
			'callback_malformed'  => __( 'The WordPress security state was changed during the redirect.', 'cmply' ),
			'callback_wrong_user' => __( 'The connection was started by a different WordPress administrator session.', 'cmply' ),
			'callback_expired'    => __( 'The WordPress connection state expired. Start the connection again.', 'cmply' ),
			'callback_signature'  => __( 'The WordPress connection state signature is invalid. The site security keys may have changed during the connection.', 'cmply' ),
			'callback_invalid'    => __( 'The WordPress connection state is invalid. Start the connection again.', 'cmply' ),
			'service_unavailable' => __( 'WordPress could not reach CMPly. Check outbound HTTPS access and try again.', 'cmply' ),
			'exchange_expired'    => __( 'The connection code expired or was already used. Start the connection again.', 'cmply' ),
			'exchange_failed'     => __( 'CMPly rejected the request. Confirm that the selected CMPly site matches this WordPress domain.', 'cmply' ),
			'invalid_exchange'    => __( 'CMPly returned an invalid response. No connection credentials were saved.', 'cmply' ),
			'not_connected'       => __( 'This site has only a manual Site ID. Use Connect to CMPly to link an account.', 'cmply' ),
			'invalid_service_url' => __( 'The configured CMPly service URL is invalid.', 'cmply' ),
			'verify_failed'       => __( 'The saved connection could not be verified. Reconnect the site to refresh its credentials.', 'cmply' ),
		);

		if ( $error && isset( $messages[ $error ] ) ) {
			printf( '<div class="notice notice-error inline cmply-notice"><p><strong>%s</strong> %s</p></div>', esc_html__( 'Connection failed.', 'cmply' ), esc_html( $messages[ $error ] ) );
			return;
		}

		if ( isset( $_GET['cmply_connected'] ) ) {
			echo '<div class="notice notice-success inline cmply-notice"><p>' . esc_html__( 'CMPly connected successfully. Plan and pageview usage were synchronized.', 'cmply' ) . '</p></div>';
		} elseif ( isset( $_GET['cmply_verified'] ) ) {
			echo '<div class="notice notice-success inline cmply-notice"><p>' . esc_html__( 'CMPly connection verified and account information refreshed.', 'cmply' ) . '</p></div>';
		} elseif ( isset( $_GET['cmply_disconnected'] ) ) {
			echo '<div class="notice notice-success inline cmply-notice"><p>' . esc_html__( 'CMPly disconnected and saved credentials removed.', 'cmply' ) . '</p></div>';
		} elseif ( ! empty( $options['site_id'] ) && ! self::has_account_connection( $options ) ) {
			echo '<div class="notice notice-warning inline cmply-notice"><p><strong>' . esc_html__( 'Manual configuration.', 'cmply' ) . '</strong> ' . esc_html__( 'The SDK can use this Site ID, but no CMPly account is connected. Plan, usage, and verification are unavailable.', 'cmply' ) . '</p></div>';
		}
	}

	/**
	 * Check whether server-side account credentials exist.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return bool
	 */
	private static function has_account_connection( $options ) {
		return ! empty( $options['site_id'] ) && ! empty( $options['connection_id'] ) && ! empty( get_option( self::SECRET_OPTION_NAME, '' ) );
	}

	/**
	 * Render CookieYes-style header tabs.
	 *
	 * @param string               $active_tab Active tab.
	 * @param array<string, mixed> $options Plugin options.
	 * @return void
	 */
	private static function render_header( $active_tab, $options ) {
		$tabs = array(
			'dashboard'     => __( 'Dashboard', 'cmply' ),
			'gcm'           => __( 'Google Consent Mode (GCM)', 'cmply' ),
			'site-settings' => __( 'Site Settings', 'cmply' ),
		);
		$plan            = ucfirst( sanitize_key( (string) $options['plan'] ) );
		$pageviews_used  = max( 0, (int) $options['pageviews_used'] );
		$pageviews_limit = (int) $options['pageviews_limit'];
		$percentage      = $pageviews_limit > 0 ? min( 100, (int) round( ( $pageviews_used / $pageviews_limit ) * 100 ) ) : 0;
		$usage_label     = $pageviews_limit < 0
			? sprintf( /* translators: %s: pageviews used */ __( '%s / Unlimited', 'cmply' ), number_format_i18n( $pageviews_used ) )
			: sprintf( /* translators: 1: pageviews used, 2: pageview limit, 3: percentage */ __( '%1$s / %2$s (%3$d%%)', 'cmply' ), number_format_i18n( $pageviews_used ), number_format_i18n( max( 0, $pageviews_limit ) ), $percentage );
		?>
		<div class="cmply-topbar">
			<nav class="cmply-tabs" aria-label="<?php esc_attr_e( 'CMPly sections', 'cmply' ); ?>">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<a class="<?php echo esc_attr( 'cmply-tab' . ( $active_tab === $tab ? ' is-active' : '' ) ); ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=cmply&tab=' . $tab ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="cmply-plan">
				<div class="cmply-plan-copy">
					<span><?php esc_html_e( 'Current plan:', 'cmply' ); ?> <strong><?php echo esc_html( $plan ); ?></strong></span>
					<small><?php esc_html_e( 'Pageviews used:', 'cmply' ); ?> <strong><?php echo esc_html( $usage_label ); ?></strong></small>
				</div>
				<a class="cmply-button cmply-button-pro" href="https://cmply.app/pricing" target="_blank" rel="noopener noreferrer"><span class="cmply-crown" aria-hidden="true">&#9813;</span><?php esc_html_e( 'Try Pro for free', 'cmply' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render dashboard screen.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return void
	 */
	private static function render_dashboard_screen( $options ) {
		$is_configured = ! empty( $options['site_id'] );
		$is_connected  = self::has_account_connection( $options );
		$is_loading    = $is_configured && ! empty( $options['enabled'] ) && ! empty( $options['auto_inject'] );
		$is_manual     = $is_configured && ! empty( $options['enabled'] ) && empty( $options['auto_inject'] );
		?>
		<div class="cmply-main-grid">
			<div class="cmply-primary">
				<?php self::render_review_notice(); ?>

				<div class="cmply-panel cmply-connection">
					<h2>
						<span class="<?php echo esc_attr( $is_connected ? 'cmply-status-dot is-ok' : 'cmply-status-dot is-warn' ); ?>"></span>
						<?php
						echo esc_html(
							$is_connected
								? __( 'Your website is connected to CMPly', 'cmply' )
								: ( $is_configured ? __( 'CMPly is configured manually', 'cmply' ) : __( 'Connect your website to CMPly', 'cmply' ) )
						);
						?>
					</h2>
					<p><?php esc_html_e( 'Access banner settings, cookie manager, scanner, languages, consent records, and analytics in the CMPly web app.', 'cmply' ); ?></p>
					<?php if ( $is_connected ) : ?>
						<div class="cmply-meta">
							<?php if ( ! empty( $options['account_email'] ) ) : ?>
								<div><strong><?php esc_html_e( 'Email:', 'cmply' ); ?></strong> <?php echo esc_html( $options['account_email'] ); ?></div>
							<?php endif; ?>
							<div><strong><?php esc_html_e( 'Site ID:', 'cmply' ); ?></strong> <code><?php echo esc_html( $options['site_id'] ); ?></code></div>
							<div><strong><?php esc_html_e( 'SDK:', 'cmply' ); ?></strong> <code><?php echo esc_html( self::sdk_url( $options ) ); ?></code></div>
							<div><strong><?php esc_html_e( 'Plan:', 'cmply' ); ?></strong> <?php echo esc_html( $options['plan'] ); ?></div>
						</div>
					<?php endif; ?>
					<div class="cmply-actions">
						<?php if ( $is_connected ) : ?>
							<a class="cmply-button cmply-button-primary" href="https://cmply.app/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Go to Web App', 'cmply' ); ?></a>
						<?php else : ?>
							<a class="cmply-button cmply-button-primary" href="<?php echo esc_url( self::connect_url( $options ) ); ?>"><?php esc_html_e( 'Connect to CMPly', 'cmply' ); ?></a>
						<?php endif; ?>
						<a class="cmply-button cmply-button-secondary" href="<?php echo esc_url( admin_url( 'options-general.php?page=cmply&tab=site-settings' ) ); ?>"><?php echo esc_html( $is_connected ? __( 'Edit connection', 'cmply' ) : __( 'Add Site ID', 'cmply' ) ); ?></a>
					</div>
				</div>

				<div class="cmply-panel">
					<div class="cmply-panel-heading">
						<h2><?php esc_html_e( 'Overview', 'cmply' ); ?></h2>
					</div>
					<div class="cmply-overview">
						<?php
						self::render_overview_card( __( 'Banner status', 'cmply' ), $is_loading ? __( 'Active', 'cmply' ) : ( $is_manual ? __( 'Manual embed', 'cmply' ) : __( 'Inactive', 'cmply' ) ), 'banner', $is_loading );
						self::render_overview_card( __( 'Regulation', 'cmply' ), __( 'GDPR', 'cmply' ), 'shield', true );
						self::render_overview_card( __( 'SDK mode', 'cmply' ), empty( $options['auto_inject'] ) ? __( 'Manual', 'cmply' ) : __( 'Auto-inject', 'cmply' ), 'code', ! empty( $options['auto_inject'] ) );
						self::render_overview_card( __( 'Language', 'cmply' ), ! empty( $options['language'] ) ? strtoupper( (string) $options['language'] ) : __( 'Auto-detect', 'cmply' ), 'language', true );
						?>
					</div>
					<div class="cmply-panel-footer">
						<a href="https://cmply.app/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Customize Banner', 'cmply' ); ?></a>
					</div>
				</div>

				<div class="cmply-card-grid">
					<div class="cmply-panel">
						<div class="cmply-panel-heading">
							<h2><?php esc_html_e( 'Installation diagnostics', 'cmply' ); ?></h2>
						</div>
						<div class="cmply-summary-grid">
							<?php self::render_stat( __( 'Plugin status', 'cmply' ), empty( $options['enabled'] ) ? __( 'Disabled', 'cmply' ) : __( 'Enabled', 'cmply' ), 'banner' ); ?>
							<?php self::render_stat( __( 'Site ID', 'cmply' ), $is_configured ? __( 'Configured', 'cmply' ) : __( 'Missing', 'cmply' ), 'document' ); ?>
							<?php self::render_stat( __( 'SDK output', 'cmply' ), empty( $options['auto_inject'] ) ? __( 'Manual embed', 'cmply' ) : __( 'Auto-inject', 'cmply' ), 'code' ); ?>
							<?php self::render_stat( __( 'Excluded paths', 'cmply' ), empty( $options['exclude_paths'] ) ? __( 'None', 'cmply' ) : __( 'Configured', 'cmply' ), 'target' ); ?>
						</div>
					</div>

					<div class="cmply-panel">
						<div class="cmply-panel-heading">
							<h2><?php esc_html_e( 'CMPly web app', 'cmply' ); ?></h2>
						</div>
						<div class="cmply-webapp-card">
							<p><?php esc_html_e( 'Banner design, cookie scans, translations, consent records, analytics, and Google Consent Mode settings are managed in CMPly.', 'cmply' ); ?></p>
							<p><?php esc_html_e( 'This WordPress plugin focuses on connecting the site and loading the SDK safely.', 'cmply' ); ?></p>
						</div>
						<div class="cmply-panel-footer">
							<a href="https://cmply.app/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Web App', 'cmply' ); ?></a>
						</div>
					</div>
				</div>
			</div>

			<aside class="cmply-sidebar">
				<?php self::render_upgrade_card(); ?>
				<?php self::render_faq_card(); ?>
			</aside>
		</div>
		<?php
		self::render_admin_footer();
	}

	/**
	 * Render GCM screen.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return void
	 */
	private static function render_gcm_screen( $options ) {
		?>
		<div class="cmply-panel cmply-gcm">
			<div class="cmply-titlebar">
				<h1><?php esc_html_e( 'Google Consent Mode Settings', 'cmply' ); ?></h1>
				<a class="cmply-button cmply-button-primary" href="https://cmply.app/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Configure in Web App', 'cmply' ); ?></a>
			</div>

			<div class="cmply-setting-row">
				<div>
					<h2><?php esc_html_e( 'Enable Google Consent Mode (GCM)', 'cmply' ); ?></h2>
				</div>
				<div>
					<span class="cmply-toggle" aria-hidden="true"></span>
					<p><?php esc_html_e( 'CMPly can implement Google Consent Mode from your CMPly site settings. When auto-inject is enabled, the WordPress plugin loads the SDK early so consent defaults can run before tags.', 'cmply' ); ?></p>
				</div>
			</div>

			<hr />

			<h2><?php esc_html_e( 'Default consent settings', 'cmply' ); ?></h2>
			<p><?php esc_html_e( 'The default consent state applies to non-necessary categories until visitor consent is received. Manage regional defaults in the CMPly web app.', 'cmply' ); ?></p>

			<table class="widefat cmply-gcm-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Analytics', 'cmply' ); ?></th>
						<th><?php esc_html_e( 'Advertisement', 'cmply' ); ?></th>
						<th><?php esc_html_e( 'Functional', 'cmply' ); ?></th>
						<th><?php esc_html_e( 'Necessary', 'cmply' ); ?></th>
						<th><?php esc_html_e( 'Share user data with Google', 'cmply' ); ?></th>
						<th><?php esc_html_e( 'Use data for ads personalisation', 'cmply' ); ?></th>
						<th><?php esc_html_e( 'Region', 'cmply' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Denied', 'cmply' ); ?></td>
						<td><?php esc_html_e( 'Denied', 'cmply' ); ?></td>
						<td><?php esc_html_e( 'Denied', 'cmply' ); ?></td>
						<td><?php esc_html_e( 'Granted', 'cmply' ); ?></td>
						<td><?php esc_html_e( 'Denied', 'cmply' ); ?></td>
						<td><?php esc_html_e( 'Denied', 'cmply' ); ?></td>
						<td><?php esc_html_e( 'All', 'cmply' ); ?></td>
					</tr>
				</tbody>
			</table>

			<a class="cmply-button cmply-button-primary" href="https://cmply.app/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '+ New Region', 'cmply' ); ?></a>

		</div>
		<?php
		self::render_admin_footer();
	}

	/**
	 * Render site settings screen.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return void
	 */
	private static function render_site_settings_screen( $options ) {
		$script_url   = self::sdk_url( $options );
		$is_configured = ! empty( $options['site_id'] );
		$is_connected  = self::has_account_connection( $options );
		?>
		<div class="cmply-panel">
			<div class="cmply-titlebar">
				<h1><?php esc_html_e( 'Site Settings', 'cmply' ); ?></h1>
			</div>

			<div class="cmply-connect-box">
				<h2>
					<span class="<?php echo esc_attr( $is_connected ? 'cmply-status-dot is-ok' : 'cmply-status-dot is-warn' ); ?>"></span>
					<?php echo esc_html( $is_connected ? __( 'Your website is connected to CMPly', 'cmply' ) : ( $is_configured ? __( 'Site ID configured manually — account not connected', 'cmply' ) : __( 'Connect this website to CMPly', 'cmply' ) ) ); ?>
				</h2>
				<p><?php esc_html_e( 'Use the connection button to sign in to CMPly, choose a site, and return with the Site ID filled automatically. Manual Site ID entry remains available below.', 'cmply' ); ?></p>
				<div class="cmply-actions">
					<a class="cmply-button cmply-button-primary" href="<?php echo esc_url( self::connect_url( $options ) ); ?>"><?php echo esc_html( $is_connected ? __( 'Reconnect', 'cmply' ) : __( 'Connect to CMPly', 'cmply' ) ); ?></a>
					<?php if ( $is_configured ) : ?>
						<a class="cmply-button cmply-button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cmply_verify_connection' ), 'cmply_verify_connection' ) ); ?>"><?php esc_html_e( 'Verify connection', 'cmply' ); ?></a>
						<a class="cmply-button cmply-button-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cmply_disconnect' ), 'cmply_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'cmply' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<form action="options.php" method="post" class="cmply-settings-form">
				<?php
				settings_fields( 'cmply' );
				do_settings_sections( 'cmply' );
				submit_button( __( 'Save Changes', 'cmply' ), 'primary cmply-save-button' );
				?>
			</form>

			<?php if ( ! empty( $options['site_id'] ) ) : ?>
				<hr />
				<h2><?php esc_html_e( 'Current Embed Code', 'cmply' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						empty( $options['auto_inject'] )
							? __( 'Auto-inject is disabled. Add this script manually near the top of the document head and keep only one CMPly SDK script on the page:', 'cmply' )
							: __( 'CMPly is inserted automatically. This is the equivalent script tag:', 'cmply' )
					);
					?>
				</p>
				<textarea class="large-text code cmply-code" rows="4" readonly><?php echo esc_textarea( self::script_tag( $script_url, $options ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Do not add defer or async. CMPly must execute before other third-party scripts.', 'cmply' ); ?></p>
			<?php endif; ?>

			<hr />
			<h2><?php esc_html_e( 'External Service Disclosure', 'cmply' ); ?></h2>
			<p><?php esc_html_e( 'This plugin connects your website to CMPly.app to load banner settings, cookie/provider data, and record consent choices. The CMPly SDK runs on the public website only when the plugin is enabled and a Site ID is configured.', 'cmply' ); ?></p>
			<p>
				<a href="https://cmply.app/privacy" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'CMPly Privacy Policy', 'cmply' ); ?></a>
				|
				<a href="https://cmply.app/terms" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'CMPly Terms', 'cmply' ); ?></a>
			</p>

		</div>
		<?php
		self::render_admin_footer();
	}

	/**
	 * Render review notice.
	 *
	 * @return void
	 */
	private static function render_review_notice() {
		?>
		<div class="cmply-panel cmply-review">
			<h2><?php esc_html_e( 'CMPly', 'cmply' ); ?></h2>
			<p><?php esc_html_e( 'Thanks for using CMPly. A quick review helps us keep improving consent tools for WordPress teams.', 'cmply' ); ?></p>
			<div class="cmply-actions">
				<a class="cmply-button cmply-button-primary" href="https://wordpress.org/support/plugin/cmply/reviews/#new-post" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Review now', 'cmply' ); ?></a>
				<a class="cmply-button cmply-button-secondary" href="<?php echo esc_url( admin_url( 'options-general.php?page=cmply&tab=site-settings' ) ); ?>"><?php esc_html_e( 'Manage settings', 'cmply' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render overview card.
	 *
	 * @param string $label Card label.
	 * @param string $value Card value.
	 * @param string $icon Icon key.
	 * @param bool   $active Whether active.
	 * @return void
	 */
	private static function render_overview_card( $label, $value, $icon, $active ) {
		?>
		<div class="cmply-overview-card">
			<div class="<?php echo esc_attr( 'cmply-icon cmply-icon-' . $icon ); ?>"></div>
			<span><?php echo esc_html( $label ); ?></span>
			<strong class="<?php echo esc_attr( $active ? 'is-active' : '' ); ?>"><?php echo esc_html( $value ); ?></strong>
		</div>
		<?php
	}

	/**
	 * Render stat item.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $icon Icon key.
	 * @return void
	 */
	private static function render_stat( $label, $value, $icon ) {
		?>
		<div class="cmply-stat">
			<div class="<?php echo esc_attr( 'cmply-icon cmply-icon-' . $icon ); ?>"></div>
			<div>
				<span><?php echo esc_html( $label ); ?></span>
				<strong><?php echo esc_html( $value ); ?></strong>
			</div>
		</div>
		<?php
	}

	/**
	 * Render upgrade card.
	 *
	 * @return void
	 */
	private static function render_upgrade_card() {
		?>
		<div class="cmply-panel cmply-upgrade">
			<h2><?php esc_html_e( 'Upgrade as your website grows', 'cmply' ); ?></h2>
			<p><?php esc_html_e( 'Access advanced consent features and future-proof your business against legal risks.', 'cmply' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Advanced banner customisation', 'cmply' ); ?></li>
				<li><?php esc_html_e( 'Increased monthly pageviews', 'cmply' ); ?></li>
				<li><?php esc_html_e( 'Geo-targeted cookie banners', 'cmply' ); ?></li>
				<li><?php esc_html_e( 'Scheduled scans for automatic updates', 'cmply' ); ?></li>
			</ul>
			<a class="cmply-button cmply-button-primary cmply-upgrade-button" href="https://cmply.app/pricing" target="_blank" rel="noopener noreferrer"><span class="cmply-crown" aria-hidden="true">&#9813;</span><?php esc_html_e( 'Try Pro for free', 'cmply' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Render FAQ card.
	 *
	 * @return void
	 */
	private static function render_faq_card() {
		$items = array(
			array( 'question' => __( 'How do I customise the cookie consent banner?', 'cmply' ), 'answer' => __( 'Open the CMPly web app and select your site. Banner layout, colours, text, categories, and languages are managed there and published to WordPress automatically.', 'cmply' ) ),
			array( 'question' => __( 'How do I scan web pages for cookies?', 'cmply' ), 'answer' => __( 'Open your site in the CMPly web app and start a scan from the scanner section. Scan availability and frequency depend on your current plan.', 'cmply' ) ),
			array( 'question' => __( 'What are pageviews?', 'cmply' ), 'answer' => __( 'A pageview is counted when a visitor loads a page where the CMPly SDK is active. The counter resets for each billing period.', 'cmply' ) ),
			array( 'question' => __( 'What happens if the monthly pageview limit is exceeded?', 'cmply' ), 'answer' => __( 'CMPly shows your current usage and limit in this dashboard. Upgrade the plan in the web app if your site needs a higher monthly allowance.', 'cmply' ) ),
			array( 'question' => __( 'How do I disconnect the plugin from the web app?', 'cmply' ), 'answer' => __( 'Open Site Settings in this plugin and choose Disconnect. The saved connection credentials are removed from WordPress.', 'cmply' ) ),
		);
		?>
		<div class="cmply-faq">
			<h2><?php esc_html_e( 'Frequently Asked Questions', 'cmply' ); ?></h2>
			<?php foreach ( $items as $item ) : ?>
				<details>
					<summary><?php echo esc_html( $item['question'] ); ?></summary>
					<p><?php echo esc_html( $item['answer'] ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render admin footer.
	 *
	 * @return void
	 */
	private static function render_admin_footer() {
		?>
		<div class="cmply-footer">
			<span><?php esc_html_e( 'Please rate CMPly on WordPress.org to help us spread the word.', 'cmply' ); ?></span>
			<span><?php echo esc_html( sprintf( 'v%s', CMPLY_COOKIE_CONSENT_VERSION ) ); ?></span>
		</div>
		<?php
	}

	/**
	 * Add settings link to plugin list.
	 *
	 * @param array<int, string> $links Plugin action links.
	 * @return array<int, string>
	 */
	public static function settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=cmply' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cmply' ) . '</a>' );

		return $links;
	}

	/**
	 * Show setup notice.
	 *
	 * @return void
	 */
	public static function configuration_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_cmply' === $screen->id ) {
			return;
		}

		$options = self::options();
		if ( ! empty( $options['site_id'] ) || empty( $options['enabled'] ) ) {
			return;
		}

		$url = admin_url( 'options-general.php?page=cmply' );
		echo '<div class="notice notice-warning"><p>';
		printf(
			/* translators: %s: settings page URL */
			wp_kses_post( __( 'CMPly is enabled but no Site ID is configured. <a href="%s">Add your Site ID</a>.', 'cmply' ) ),
			esc_url( $url )
		);
		echo '</p></div>';
	}

	/**
	 * Print SDK script in the frontend head.
	 *
	 * @return void
	 */
	public static function print_sdk_script() {
		$options = self::options();
		if ( empty( $options['enabled'] ) || empty( $options['auto_inject'] ) || empty( $options['site_id'] ) || is_admin() || self::is_excluded_path( $options ) ) {
			return;
		}

		$script_url = self::sdk_url( $options );

		echo "\n<!-- CMPly: early SDK load for consent auto-blocking -->\n";
		echo self::script_tag( $script_url, $options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n";
	}

	/**
	 * Build the SDK URL.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return string
	 */
	private static function sdk_url( $options ) {
		$base_url = untrailingslashit( (string) $options['sdk_base_url'] );
		$url      = $base_url . '/sdk/init.js';

		if ( ! empty( $options['sdk_version'] ) ) {
			$url = add_query_arg( 'v', rawurlencode( (string) $options['sdk_version'] ), $url );
		}

		return esc_url( $url );
	}

	/**
	 * Build CMPly web app connect URL.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return string
	 */
	private static function connect_url( $options ) {
		$base_url   = self::sanitize_service_url( (string) $options['sdk_base_url'] );
		$state      = self::create_connection_state();
		$return_url = admin_url( 'admin-post.php?action=cmply_connect_callback' );

		$url = add_query_arg(
			array(
				'return_url'     => $return_url,
				'site_url'       => home_url(),
				'admin_url'      => admin_url( 'options-general.php?page=cmply' ),
				'plugin_version' => CMPLY_COOKIE_CONSENT_VERSION,
				'source'         => 'wordpress',
				'connection_state' => $state,
			),
			$base_url . '/integrations/wordpress/connect'
		);

		return esc_url( $url );
	}

	/**
	 * Create a one-time state token for the external connection round trip.
	 *
	 * @return string
	 */
	private static function create_connection_state() {
		$payload   = get_current_user_id() . '.' . ( time() + ( 30 * MINUTE_IN_SECONDS ) ) . '.' . wp_generate_password( 20, false, false );
		$signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

		return $payload . '.' . $signature;
	}

	/**
	 * Validate and consume a one-time connection state token.
	 *
	 * @param string $state State token returned by CMPly.
	 * @return bool
	 */
	private static function consume_connection_state( $state ) {
		if ( empty( $state ) ) {
			self::$connection_state_error = 'callback_missing';
			return false;
		}
		if ( strlen( $state ) > 200 ) {
			self::$connection_state_error = 'callback_malformed';
			return false;
		}

		$parts = explode( '.', $state );
		if ( 4 !== count( $parts ) ) {
			self::$connection_state_error = 'callback_malformed';
			return false;
		}

		list( $user_id, $expires_at, $random, $signature ) = $parts;
		if ( ! ctype_digit( $user_id ) || ! ctype_digit( $expires_at ) || empty( $random ) ) {
			self::$connection_state_error = 'callback_malformed';
			return false;
		}
		if ( time() > (int) $expires_at ) {
			self::$connection_state_error = 'callback_expired';
			return false;
		}
		if ( (int) $user_id !== get_current_user_id() ) {
			self::$connection_state_error = 'callback_wrong_user';
			return false;
		}

		$payload  = $user_id . '.' . $expires_at . '.' . $random;
		$expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

		if ( ! hash_equals( $expected, $signature ) ) {
			self::$connection_state_error = 'callback_signature';
			return false;
		}

		return true;
	}

	/**
	 * Restrict service URLs to CMPly over HTTPS.
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	private static function sanitize_service_url( $url ) {
		$url   = esc_url_raw( untrailingslashit( (string) $url ), array( 'https' ) );
		$host  = wp_parse_url( $url, PHP_URL_HOST );
		$port  = wp_parse_url( $url, PHP_URL_PORT );
		$query = wp_parse_url( $url, PHP_URL_QUERY );

		if ( self::SERVICE_HOST !== strtolower( (string) $host ) || ! empty( $port ) || ! empty( $query ) ) {
			return 'https://' . self::SERVICE_HOST;
		}

		return $url;
	}

	/**
	 * Build a trusted CMPly API endpoint.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @param string               $path Endpoint path.
	 * @return string
	 */
	private static function service_endpoint( $options, $path ) {
		$base_url = self::sanitize_service_url( (string) $options['sdk_base_url'] );

		return untrailingslashit( $base_url ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Store the API key without autoloading it on every request.
	 *
	 * @param string $api_key CMPly API key.
	 * @return void
	 */
	private static function save_api_key( $api_key ) {
		delete_option( self::SECRET_OPTION_NAME );
		add_option( self::SECRET_OPTION_NAME, $api_key, '', false );
	}

	/**
	 * Apply an account snapshot received from CMPly.
	 *
	 * @param array<string, mixed> $options Account options, passed by reference.
	 * @param mixed                $account Raw account snapshot.
	 * @return void
	 */
	private static function apply_account_snapshot( &$options, $account ) {
		if ( ! is_array( $account ) ) {
			return;
		}

		$options['account_email']   = isset( $account['email'] ) ? sanitize_email( $account['email'] ) : '';
		$options['plan']            = isset( $account['plan'] ) ? sanitize_key( $account['plan'] ) : 'free';
		$options['pageviews_used']  = isset( $account['pageViewsUsed'] ) ? max( 0, (int) $account['pageViewsUsed'] ) : 0;
		$options['pageviews_limit'] = isset( $account['pageViewsLimit'] ) ? (int) $account['pageViewsLimit'] : 0;
	}

	/**
	 * Build the script tag.
	 *
	 * @param string               $script_url SDK URL.
	 * @param array<string, mixed> $options Plugin options.
	 * @return string
	 */
	private static function script_tag( $script_url, $options ) {
		$attributes = array(
			'src'          => $script_url,
			'data-site-id' => (string) $options['site_id'],
		);

		if ( ! empty( $options['language'] ) ) {
			$attributes['data-lang'] = (string) $options['language'];
		}

		$parts = array();
		foreach ( $attributes as $name => $value ) {
			$parts[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		return '<script ' . implode( ' ', $parts ) . '></script>';
	}

	/**
	 * Check whether the current frontend path should be excluded.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @return bool
	 */
	private static function is_excluded_path( $options ) {
		if ( empty( $options['exclude_paths'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$current_path = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		if ( empty( $current_path ) ) {
			return false;
		}

		$patterns = preg_split( '/\r\n|\r|\n/', (string) $options['exclude_paths'] );
		foreach ( $patterns as $pattern ) {
			$pattern = trim( $pattern );
			if ( '' === $pattern ) {
				continue;
			}

			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#';
			if ( preg_match( $regex, $current_path ) ) {
				return true;
			}
		}

		return false;
	}

}
