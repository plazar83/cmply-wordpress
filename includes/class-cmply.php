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

	/**
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults() {
		return array(
			'enabled'       => 1,
			'site_id'       => '',
			'sdk_base_url'  => 'https://cmply.app',
			'sdk_version'   => '',
			'language'      => '',
			'exclude_paths' => '',
			'account_email' => '',
			'plan'          => 'Free',
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
		add_action( 'admin_notices', array( __CLASS__, 'configuration_notice' ) );
		add_filter( 'plugin_action_links_' . CMPLY_COOKIE_CONSENT_BASENAME, array( __CLASS__, 'settings_link' ) );

		add_action( 'wp_head', array( __CLASS__, 'print_sdk_script' ), 0 );
		add_shortcode( 'cmply_revisit', array( __CLASS__, 'revisit_shortcode' ) );
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
		self::add_field( 'site_id', __( 'Site ID', 'cmply' ), 'render_site_id_field' );
		self::add_field( 'sdk_base_url', __( 'SDK Base URL', 'cmply' ), 'render_sdk_base_url_field' );
		self::add_field( 'sdk_version', __( 'SDK Version', 'cmply' ), 'render_sdk_version_field' );
		self::add_field( 'language', __( 'Language', 'cmply' ), 'render_language_field' );
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
		$output   = array();

		$output['enabled']       = empty( $input['enabled'] ) ? 0 : 1;
		$output['site_id']       = isset( $input['site_id'] ) ? sanitize_text_field( wp_unslash( $input['site_id'] ) ) : '';
		$output['sdk_base_url']  = isset( $input['sdk_base_url'] ) ? esc_url_raw( untrailingslashit( wp_unslash( $input['sdk_base_url'] ) ) ) : $defaults['sdk_base_url'];
		$output['sdk_version']   = isset( $input['sdk_version'] ) ? preg_replace( '/[^a-zA-Z0-9._-]/', '', sanitize_text_field( wp_unslash( $input['sdk_version'] ) ) ) : '';
		$output['language']      = isset( $input['language'] ) ? preg_replace( '/[^a-zA-Z_-]/', '', sanitize_text_field( wp_unslash( $input['language'] ) ) ) : '';
		$output['exclude_paths'] = isset( $input['exclude_paths'] ) ? sanitize_textarea_field( wp_unslash( $input['exclude_paths'] ) ) : '';
		$output['account_email'] = isset( $input['account_email'] ) ? sanitize_email( wp_unslash( $input['account_email'] ) ) : '';
		$output['plan']          = isset( $input['plan'] ) ? sanitize_text_field( wp_unslash( $input['plan'] ) ) : 'Free';

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
		<p class="description"><?php esc_html_e( 'Use https://cmply.app for production, or a staging CMPly domain when testing.', 'cmply' ); ?></p>
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
		<input class="small-text" type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[language]" value="<?php echo esc_attr( $options['language'] ); ?>" placeholder="en" />
		<p class="description"><?php esc_html_e( 'Optional. Passes data-lang to CMPly, for example en, ru, de, fr, es, or it.', 'cmply' ); ?></p>
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
				<?php self::render_header( $tab ); ?>

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

		check_admin_referer( 'cmply_connect' );

		$site_id = isset( $_GET['site_id'] ) ? sanitize_text_field( wp_unslash( $_GET['site_id'] ) ) : '';
		if ( empty( $site_id ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_error=missing_site_id' ) );
			exit;
		}

		$options                  = self::options();
		$options['enabled']       = 1;
		$options['site_id']       = $site_id;
		$options['account_email'] = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : $options['account_email'];
		$options['plan']          = isset( $_GET['plan'] ) ? sanitize_text_field( wp_unslash( $_GET['plan'] ) ) : $options['plan'];

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

		update_option( self::OPTION_NAME, $options );

		wp_safe_redirect( admin_url( 'options-general.php?page=cmply&tab=site-settings&cmply_disconnected=1' ) );
		exit;
	}

	/**
	 * Render CookieYes-style header tabs.
	 *
	 * @param string $active_tab Active tab.
	 * @return void
	 */
	private static function render_header( $active_tab ) {
		$tabs = array(
			'dashboard'     => __( 'Dashboard', 'cmply' ),
			'gcm'           => __( 'Google Consent Mode (GCM)', 'cmply' ),
			'site-settings' => __( 'Site Settings', 'cmply' ),
		);
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
				<span><?php esc_html_e( 'Current plan:', 'cmply' ); ?> <strong><?php esc_html_e( 'Free', 'cmply' ); ?></strong></span>
				<a class="cmply-button cmply-button-pro" href="https://cmply.app/pricing" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Try Pro for free', 'cmply' ); ?></a>
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
		$is_connected = ! empty( $options['site_id'] );
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
								: __( 'Connect your website to CMPly', 'cmply' )
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
						self::render_overview_card( __( 'Banner status', 'cmply' ), $is_connected && ! empty( $options['enabled'] ) ? __( 'Active', 'cmply' ) : __( 'Inactive', 'cmply' ), 'banner', $is_connected && ! empty( $options['enabled'] ) );
						self::render_overview_card( __( 'Regulation', 'cmply' ), __( 'GDPR', 'cmply' ), 'shield', true );
						self::render_overview_card( __( 'Language', 'cmply' ), ! empty( $options['language'] ) ? strtoupper( (string) $options['language'] ) : __( 'Auto-detect', 'cmply' ), 'language', true );
						self::render_overview_card( __( 'Targeted location', 'cmply' ), __( 'Worldwide', 'cmply' ), 'target', true );
						?>
					</div>
					<div class="cmply-panel-footer">
						<a href="https://cmply.app/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Customize Banner', 'cmply' ); ?></a>
					</div>
				</div>

				<div class="cmply-card-grid">
					<div class="cmply-panel">
						<div class="cmply-panel-heading">
							<h2><?php esc_html_e( 'Cookie Summary', 'cmply' ); ?></h2>
						</div>
						<div class="cmply-summary-grid">
							<?php self::render_stat( __( 'Total cookies', 'cmply' ), __( 'Available after scan', 'cmply' ), 'cookie' ); ?>
							<?php self::render_stat( __( 'Categories', 'cmply' ), __( 'Managed in CMPly', 'cmply' ), 'grid' ); ?>
							<?php self::render_stat( __( 'Last successful scan', 'cmply' ), __( 'Run in web app', 'cmply' ), 'search' ); ?>
							<?php self::render_stat( __( 'Pages scanned', 'cmply' ), __( 'Managed in CMPly', 'cmply' ), 'document' ); ?>
						</div>
					</div>

					<div class="cmply-panel">
						<div class="cmply-panel-heading">
							<h2><?php esc_html_e( 'Consent trends', 'cmply' ); ?> <span><?php esc_html_e( 'Last 7 days', 'cmply' ); ?></span></h2>
						</div>
						<div class="cmply-empty-chart">
							<div class="cmply-empty-icon">○</div>
							<p><?php esc_html_e( 'Consent analytics are available in the CMPly web app.', 'cmply' ); ?></p>
						</div>
						<div class="cmply-panel-footer">
							<a href="https://cmply.app/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View All', 'cmply' ); ?></a>
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
					<p><?php esc_html_e( 'CMPly can implement Google Consent Mode from your CMPly site settings. The WordPress plugin loads the SDK early so consent defaults can run before tags.', 'cmply' ); ?></p>
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

			<hr />

			<h2><?php esc_html_e( 'Other settings', 'cmply' ); ?></h2>
			<div class="cmply-setting-row">
				<div><?php esc_html_e( 'SDK connection', 'cmply' ); ?></div>
				<div><code><?php echo esc_html( empty( $options['site_id'] ) ? __( 'Add a Site ID first', 'cmply' ) : self::script_tag( self::sdk_url( $options ), $options ) ); ?></code></div>
			</div>
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
		$is_connected = ! empty( $options['site_id'] );
		?>
		<div class="cmply-panel">
			<div class="cmply-titlebar">
				<h1><?php esc_html_e( 'Site Settings', 'cmply' ); ?></h1>
			</div>

			<div class="cmply-connect-box">
				<h2>
					<span class="<?php echo esc_attr( $is_connected ? 'cmply-status-dot is-ok' : 'cmply-status-dot is-warn' ); ?>"></span>
					<?php echo esc_html( $is_connected ? __( 'Your website is connected to CMPly', 'cmply' ) : __( 'Connect this website to CMPly', 'cmply' ) ); ?>
				</h2>
				<p><?php esc_html_e( 'Use the connection button to sign in to CMPly, choose a site, and return with the Site ID filled automatically. Manual Site ID entry remains available below.', 'cmply' ); ?></p>
				<div class="cmply-actions">
					<a class="cmply-button cmply-button-primary" href="<?php echo esc_url( self::connect_url( $options ) ); ?>"><?php echo esc_html( $is_connected ? __( 'Reconnect', 'cmply' ) : __( 'Connect to CMPly', 'cmply' ) ); ?></a>
					<?php if ( $is_connected ) : ?>
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
				<p><?php esc_html_e( 'CMPly is inserted automatically. This is the equivalent script tag:', 'cmply' ); ?></p>
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

			<hr />
			<h2><?php esc_html_e( 'Revisit Consent Button', 'cmply' ); ?></h2>
			<p><?php esc_html_e( 'Use this shortcode anywhere you want visitors to reopen preferences:', 'cmply' ); ?></p>
			<code>[cmply_revisit label="Cookie settings"]</code>
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
			<a class="cmply-button cmply-button-primary" href="https://cmply.app/pricing" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Try Pro for free', 'cmply' ); ?></a>
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
			__( 'How do I customise the cookie consent banner?', 'cmply' ),
			__( 'How do I scan web pages for cookies?', 'cmply' ),
			__( 'What are pageviews?', 'cmply' ),
			__( 'What happens if the monthly pageview limit exceeds?', 'cmply' ),
			__( 'How do I disconnect the plugin from the web app?', 'cmply' ),
		);
		?>
		<div class="cmply-faq">
			<h2><?php esc_html_e( 'Frequently Asked Questions', 'cmply' ); ?></h2>
			<?php foreach ( $items as $item ) : ?>
				<a href="https://cmply.app" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item ); ?><span>›</span></a>
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
		if ( empty( $options['enabled'] ) || empty( $options['site_id'] ) || is_admin() || self::is_excluded_path( $options ) ) {
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
		$base_url   = untrailingslashit( (string) $options['sdk_base_url'] );
		$return_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=cmply_connect_callback' ),
			'cmply_connect'
		);

		$url = add_query_arg(
			array(
				'return_url'     => $return_url,
				'site_url'       => home_url(),
				'admin_url'      => admin_url( 'options-general.php?page=cmply' ),
				'plugin_version' => CMPLY_COOKIE_CONSENT_VERSION,
				'source'         => 'wordpress',
			),
			$base_url . '/integrations/wordpress/connect'
		);

		return esc_url( $url );
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

	/**
	 * Render a button that opens CMPly preferences.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function revisit_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Cookie settings', 'cmply' ),
				'class' => 'cmply-revisit-button',
			),
			$atts,
			'cmply_revisit'
		);

		return sprintf(
			'<button type="button" class="%1$s" onclick="window.CMPly && window.CMPly.showBanner && window.CMPly.showBanner();">%2$s</button>',
			esc_attr( $atts['class'] ),
			esc_html( $atts['label'] )
		);
	}
}
