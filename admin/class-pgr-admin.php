<?php
/**
 * WordPress admin product surface for Persian Gravity Forms.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Admin {

	const OPTION        = 'pgr_settings';
	const CAPABILITY    = 'manage_options';
	const MENU_SLUG     = 'persian-gravityforms';
	const PROFILES_SLUG = 'persian-gravityforms-scanner-profiles';
	const SETTINGS_SLUG = 'persian-gravityforms-settings';
	const STATUS_SLUG   = 'persian-gravityforms-system-status';

	/** @var array<int,string> */
	private $page_hooks = array();

	/**
	 * Register WordPress admin hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_pgr_scanner_profile_save', array( $this, 'handle_profile_save' ) );
		add_action( 'admin_post_pgr_scanner_profile_delete', array( $this, 'handle_profile_delete' ) );
		add_action( 'admin_post_pgr_scanner_profile_duplicate', array( $this, 'handle_profile_duplicate' ) );
	}

	/**
	 * Register the top-level product menu and canonical subpages.
	 *
	 * @return void
	 */
	public function add_menu() {
		$this->remember_hook(
			add_menu_page(
				esc_html__( 'Persian Gravity', 'persian-gravityforms' ),
				esc_html__( 'Persian Gravity', 'persian-gravityforms' ),
				self::CAPABILITY,
				self::MENU_SLUG,
				array( $this, 'render_overview_page' ),
				'dashicons-forms',
				58
			)
		);

		$this->remember_hook(
			add_submenu_page(
				self::MENU_SLUG,
				esc_html__( 'Overview', 'persian-gravityforms' ),
				esc_html__( 'Overview', 'persian-gravityforms' ),
				self::CAPABILITY,
				self::MENU_SLUG,
				array( $this, 'render_overview_page' )
			)
		);
		$this->remember_hook(
			add_submenu_page(
				self::MENU_SLUG,
				esc_html__( 'Scanner Profiles', 'persian-gravityforms' ),
				esc_html__( 'Scanner Profiles', 'persian-gravityforms' ),
				self::CAPABILITY,
				self::PROFILES_SLUG,
				array( $this, 'render_profiles_page' )
			)
		);
		$this->remember_hook(
			add_submenu_page(
				self::MENU_SLUG,
				esc_html__( 'Settings', 'persian-gravityforms' ),
				esc_html__( 'Settings', 'persian-gravityforms' ),
				self::CAPABILITY,
				self::SETTINGS_SLUG,
				array( $this, 'render_settings_page' )
			)
		);
		$this->remember_hook(
			add_submenu_page(
				self::MENU_SLUG,
				esc_html__( 'System Status', 'persian-gravityforms' ),
				esc_html__( 'System Status', 'persian-gravityforms' ),
				self::CAPABILITY,
				self::STATUS_SLUG,
				array( $this, 'render_status_page' )
			)
		);
	}

	/**
	 * Load product assets only on PersianGravity pages.
	 *
	 * @param string $hook_suffix Current page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'pgr-admin',
			PGR_URL . 'assets/css/pgr-admin.css',
			array(),
			PGR_VERSION
		);

		if ( false === strpos( $hook_suffix, self::PROFILES_SLUG ) ) {
			return;
		}

		wp_enqueue_script(
			'pgr-admin-profiles',
			PGR_URL . 'assets/js/pgr-admin-profiles.js',
			array(),
			PGR_VERSION,
			true
		);
		wp_localize_script(
			'pgr-admin-profiles',
			'PGRProfileAdmin',
			array(
				'outputKey'     => esc_html__( 'Output key', 'persian-gravityforms' ),
				'outputLabel'   => esc_html__( 'Output label', 'persian-gravityforms' ),
				'required'      => esc_html__( 'Required', 'persian-gravityforms' ),
				'moveUp'        => esc_html__( 'Move up', 'persian-gravityforms' ),
				'moveDown'      => esc_html__( 'Move down', 'persian-gravityforms' ),
				'remove'        => esc_html__( 'Remove', 'persian-gravityforms' ),
				'deleteConfirm' => esc_html__( 'Deleting this profile may leave existing Structured Scanner fields with an unavailable profile. Continue?', 'persian-gravityforms' ),
			)
		);
	}

	/**
	 * Preserve the existing pgr_settings Settings API model.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pgr_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array( 'default_force_english' => 1 ),
			)
		);
		add_settings_section(
			'pgr_national_id_defaults',
			esc_html__( 'National ID defaults', 'persian-gravityforms' ),
			'__return_false',
			self::SETTINGS_SLUG
		);
		add_settings_field(
			'default_force_english',
			esc_html__( 'Normalize digits while typing', 'persian-gravityforms' ),
			array( $this, 'render_default_force_english' ),
			self::SETTINGS_SLUG,
			'pgr_national_id_defaults'
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array<string,int>
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array( 'default_force_english' => empty( $input['default_force_english'] ) ? 0 : 1 );
	}

	/**
	 * Render existing National ID default.
	 *
	 * @return void
	 */
	public function render_default_force_english() {
		$settings = get_option( self::OPTION, array( 'default_force_english' => 1 ) );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_force_english]" value="1" <?php checked( ! empty( $settings['default_force_english'] ) ); ?> />
			<?php esc_html_e( 'Convert Persian/Arabic digits to ASCII in new National ID fields while the user types. Server-side normalization always remains authoritative.', 'persian-gravityforms' ); ?>
		</label>
		<?php
	}

	/**
	 * Render product overview.
	 *
	 * @return void
	 */
	public function render_overview_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap pgr-admin">
			<div class="pgr-admin__header">
				<div>
					<h1><?php esc_html_e( 'Persian Gravity', 'persian-gravityforms' ); ?></h1>
					<p class="pgr-admin__lead"><?php esc_html_e( 'Generic Persian and Iranian enhancements for Gravity Forms.', 'persian-gravityforms' ); ?></p>
				</div>
				<span class="pgr-badge" data-state="AVAILABLE"><?php echo esc_html( PGR_VERSION ); ?></span>
			</div>
			<div class="pgr-card-grid">
				<?php $this->capability_card( __( 'National ID', 'persian-gravityforms' ), __( 'Server-authoritative Iranian National ID validation and digit normalization.', 'persian-gravityforms' ) ); ?>
				<?php $this->capability_card( __( 'Jalali Date', 'persian-gravityforms' ), __( 'Dedicated Jalali date field with canonical Jalali storage semantics.', 'persian-gravityforms' ) ); ?>
				<?php $this->capability_card( __( 'Iranian Address', 'persian-gravityforms' ), __( 'Iranian address type and province choices for Gravity Forms.', 'persian-gravityforms' ) ); ?>
				<?php $this->capability_card( __( 'Persian digit normalization', 'persian-gravityforms' ), __( 'Persian and Arabic digit normalization at supported input and save boundaries.', 'persian-gravityforms' ) ); ?>
				<?php $this->capability_card( __( 'IRR / IRT currency', 'persian-gravityforms' ), __( 'Iranian Rial and Toman currency definitions.', 'persian-gravityforms' ) ); ?>
				<section class="pgr-card">
					<h2><?php esc_html_e( 'Structured Scanner', 'persian-gravityforms' ); ?></h2>
					<p><?php esc_html_e( 'A non-persistent controller that parses structured scanner input and populates ordinary Gravity Forms fields.', 'persian-gravityforms' ); ?></p>
					<p class="pgr-card__meta">
						<?php
						printf(
							/* translators: %d: number of active Scanner profiles. */
							esc_html__( '%d active profiles', 'persian-gravityforms' ),
							count( PGR_Scanner_Profile_Registry::active() )
						);
						?>
					</p>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PROFILES_SLUG ) ); ?>"><?php esc_html_e( 'Manage Scanner Profiles', 'persian-gravityforms' ); ?></a>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Route profiles list/create/edit/inspect.
	 *
	 * @return void
	 */
	public function render_profiles_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing; no state mutation occurs here.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing; no state mutation occurs here.
		$profile_id = isset( $_GET['profile'] ) ? sanitize_key( wp_unslash( $_GET['profile'] ) ) : '';

		if ( 'new' === $action ) {
			$this->render_profile_form( null );
			return;
		}

		if ( 'edit' === $action && '' !== $profile_id ) {
			$profile = PGR_Scanner_Profile_Registry::get( $profile_id );
			if ( null !== $profile ) {
				if ( ! empty( $profile['read_only'] ) ) {
					$this->render_profile_inspect( $profile );
				} else {
					$this->render_profile_form( $profile );
				}
				return;
			}
		}

		$this->render_profiles_list();
	}

	/**
	 * Render canonical settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap pgr-admin">
			<h1><?php esc_html_e( 'Settings', 'persian-gravityforms' ); ?></h1>
			<p class="pgr-admin__lead"><?php esc_html_e( 'General PersianGravity defaults. Existing saved settings remain unchanged.', 'persian-gravityforms' ); ?></p>
			<section class="pgr-panel">
				<form method="post" action="options.php">
					<?php settings_fields( 'pgr_settings_group' ); ?>
					<?php do_settings_sections( self::SETTINGS_SLUG ); ?>
					<?php submit_button(); ?>
				</form>
			</section>
		</div>
		<?php
	}

	/**
	 * Render small non-destructive system status.
	 *
	 * @return void
	 */
	public function render_status_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$gf_available = class_exists( 'GFForms' );
		$gf_version   = $gf_available ? GFForms::$version : __( 'Not available', 'persian-gravityforms' );
		?>
		<div class="wrap pgr-admin">
			<h1><?php esc_html_e( 'System Status', 'persian-gravityforms' ); ?></h1>
			<p class="pgr-admin__lead"><?php esc_html_e( 'Relevant runtime facts for this installation. No secrets or destructive actions are exposed here.', 'persian-gravityforms' ); ?></p>
			<div class="pgr-card-grid">
				<?php $this->status_card( __( 'PersianGravity', 'persian-gravityforms' ), PGR_VERSION, 'AVAILABLE' ); ?>
				<?php $this->status_card( __( 'WordPress', 'persian-gravityforms' ), get_bloginfo( 'version' ), 'AVAILABLE' ); ?>
				<?php $this->status_card( __( 'PHP', 'persian-gravityforms' ), PHP_VERSION, 'AVAILABLE' ); ?>
				<?php $this->status_card( __( 'Gravity Forms', 'persian-gravityforms' ), $gf_version, $gf_available ? 'AVAILABLE' : 'UNAVAILABLE' ); ?>
				<?php $this->status_card( __( 'Structured Scanner', 'persian-gravityforms' ), $gf_available ? __( 'Available', 'persian-gravityforms' ) : __( 'Waiting for Gravity Forms', 'persian-gravityforms' ), $gf_available ? 'AVAILABLE' : 'WARNING' ); ?>
				<?php
				$this->status_card(
					__( 'Profile registry', 'persian-gravityforms' ),
					sprintf(
						/* translators: %d: number of effective Scanner profiles. */
						__( '%d effective profiles', 'persian-gravityforms' ),
						count( PGR_Scanner_Profile_Registry::all() )
					),
					'AVAILABLE'
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save a custom profile.
	 *
	 * @return void
	 */
	public function handle_profile_save() {
		$this->assert_profile_write_access( 'pgr_scanner_profile_save' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above by assert_profile_write_access().
		$existing_id = isset( $_POST['existing_id'] ) ? sanitize_key( wp_unslash( $_POST['existing_id'] ) ) : '';
		$result      = PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile_from_request(), $existing_id );
		$this->redirect_profiles( array( 'pgr_notice' => $result['success'] ? 'saved' : 'save_error' ) );
	}

	/**
	 * Delete a custom profile without rewriting forms.
	 *
	 * @return void
	 */
	public function handle_profile_delete() {
		$this->assert_profile_write_access( 'pgr_scanner_profile_delete' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above by assert_profile_write_access().
		$profile_id = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
		$deleted    = PGR_Scanner_Profile_Registry::delete_custom_profile( $profile_id );
		$this->redirect_profiles( array( 'pgr_notice' => $deleted ? 'deleted' : 'delete_error' ) );
	}

	/**
	 * Duplicate any effective profile as custom.
	 *
	 * @return void
	 */
	public function handle_profile_duplicate() {
		$this->assert_profile_write_access( 'pgr_scanner_profile_duplicate' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above by assert_profile_write_access().
		$profile_id = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
		$source     = PGR_Scanner_Profile_Registry::get( $profile_id );
		if ( null === $source ) {
			$this->redirect_profiles( array( 'pgr_notice' => 'duplicate_error' ) );
		}

		$new_id        = $this->next_duplicate_id( $source['id'] );
		$copy          = $source;
		$copy['id']    = $new_id;
		$copy['label'] = sprintf(
			/* translators: %s: source Scanner profile label. */
			__( 'Copy of %s', 'persian-gravityforms' ),
			$source['label']
		);
		$copy['kind']      = 'custom';
		$copy['read_only'] = false;
		$copy['enabled']   = true;
		$result            = PGR_Scanner_Profile_Registry::save_custom_profile( $copy );
		if ( ! $result['success'] ) {
			$this->redirect_profiles( array( 'pgr_notice' => 'duplicate_error' ) );
		}

		$this->redirect_profiles(
			array(
				'action'     => 'edit',
				'profile'    => $new_id,
				'pgr_notice' => 'duplicated',
			)
		);
	}

	/**
	 * Render profile cards.
	 *
	 * @return void
	 */
	private function render_profiles_list() {
		?>
		<div class="wrap pgr-admin">
			<div class="pgr-admin__header">
				<div>
					<h1><?php esc_html_e( 'Scanner Profiles', 'persian-gravityforms' ); ?></h1>
					<p class="pgr-admin__lead"><?php esc_html_e( 'Define deterministic structures for scanner input. Gravity Forms destination fields are mapped later inside each form.', 'persian-gravityforms' ); ?></p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PROFILES_SLUG . '&action=new' ) ); ?>"><?php esc_html_e( 'Create Profile', 'persian-gravityforms' ); ?></a>
			</div>
			<?php $this->render_notice(); ?>
			<div class="pgr-profile-grid">
				<?php foreach ( PGR_Scanner_Profile_Registry::all() as $profile ) : ?>
					<article class="pgr-card pgr-profile-card">
						<div class="pgr-profile-card__heading">
							<div>
								<h2><?php echo esc_html( $profile['label'] ); ?></h2>
								<code dir="ltr"><?php echo esc_html( $profile['id'] ); ?></code>
							</div>
							<div class="pgr-badge-row">
								<span class="pgr-badge pgr-badge--type"><?php echo esc_html( 'built_in' === $profile['kind'] ? __( 'Built-in', 'persian-gravityforms' ) : __( 'Custom', 'persian-gravityforms' ) ); ?></span>
								<span class="pgr-badge" data-state="<?php echo esc_attr( $profile['enabled'] ? 'AVAILABLE' : 'DISABLED' ); ?>"><?php echo esc_html( $profile['enabled'] ? __( 'Active', 'persian-gravityforms' ) : __( 'Disabled', 'persian-gravityforms' ) ); ?></span>
								<?php if ( ! empty( $profile['read_only'] ) ) : ?>
									<span class="pgr-badge" data-state="DISABLED"><?php esc_html_e( 'Read-only', 'persian-gravityforms' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<p class="pgr-card__meta">
							<?php
							printf(
								/* translators: %d: number of outputs in the Scanner profile. */
								esc_html__( '%d outputs', 'persian-gravityforms' ),
								count( $profile['outputs'] )
							);
							?>
						</p>
						<div class="pgr-actions">
							<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PROFILES_SLUG . '&action=edit&profile=' . rawurlencode( $profile['id'] ) ) ); ?>"><?php echo esc_html( ! empty( $profile['read_only'] ) ? __( 'Inspect', 'persian-gravityforms' ) : __( 'Edit', 'persian-gravityforms' ) ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="pgr_scanner_profile_duplicate" />
								<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile['id'] ); ?>" />
								<?php wp_nonce_field( 'pgr_scanner_profile_duplicate' ); ?>
								<button type="submit" class="button button-secondary"><?php esc_html_e( 'Duplicate', 'persian-gravityforms' ); ?></button>
							</form>
							<?php if ( empty( $profile['read_only'] ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-pgr-delete-profile="1">
									<input type="hidden" name="action" value="pgr_scanner_profile_delete" />
									<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile['id'] ); ?>" />
									<?php wp_nonce_field( 'pgr_scanner_profile_delete' ); ?>
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'persian-gravityforms' ); ?></button>
								</form>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<p class="pgr-warning-note"><?php esc_html_e( 'Deleting a custom profile does not rewrite existing form mappings. Existing Scanner fields may then report the profile as unavailable.', 'persian-gravityforms' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a create/edit custom profile form.
	 *
	 * @param array<string,mixed>|null $profile Existing custom profile.
	 * @return void
	 */
	private function render_profile_form( $profile ) {
		$is_edit = is_array( $profile );
		if ( ! $is_edit ) {
			$profile = array(
				'id'      => '',
				'label'   => '',
				'enabled' => true,
				'parser'  => array(
					'type'             => PGR_Scanner_Profile_Registry::PARSER_SEGMENTS,
					'separator'        => 'newline',
					'trim'             => true,
					'normalize_digits' => true,
				),
				'outputs' => array(
					array(
						'key'      => '',
						'label'    => '',
						'required' => true,
					),
				),
			);
		}
		?>
		<div class="wrap pgr-admin">
			<h1><?php echo esc_html( $is_edit ? __( 'Edit Scanner Profile', 'persian-gravityforms' ) : __( 'Create Scanner Profile', 'persian-gravityforms' ) ); ?></h1>
			<p class="pgr-admin__lead"><?php esc_html_e( 'Scanner Profiles v1 uses only the bounded newline-based segments_v1 parser. No executable rules or regular-expression language is stored.', 'persian-gravityforms' ); ?></p>
			<?php $this->render_notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pgr-profile-form">
				<input type="hidden" name="action" value="pgr_scanner_profile_save" />
				<input type="hidden" name="existing_id" value="<?php echo esc_attr( $is_edit ? $profile['id'] : '' ); ?>" />
				<?php wp_nonce_field( 'pgr_scanner_profile_save' ); ?>
				<section class="pgr-panel">
					<h2><?php esc_html_e( 'Profile details', 'persian-gravityforms' ); ?></h2>
					<div class="pgr-form-grid">
						<label>
							<span><?php esc_html_e( 'Display name', 'persian-gravityforms' ); ?></span>
							<input type="text" class="regular-text" name="profile[label]" value="<?php echo esc_attr( $profile['label'] ); ?>" required />
						</label>
						<label>
							<span><?php esc_html_e( 'Profile ID', 'persian-gravityforms' ); ?></span>
							<input type="text" class="regular-text pgr-ltr" dir="ltr" name="profile[id]" pattern="[a-z][a-z0-9_]*" value="<?php echo esc_attr( $profile['id'] ); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?> />
							<small><?php esc_html_e( 'ASCII identifier. It cannot be changed after creation.', 'persian-gravityforms' ); ?></small>
						</label>
					</div>
					<label class="pgr-checkbox"><input type="checkbox" name="profile[enabled]" value="1" <?php checked( ! empty( $profile['enabled'] ) ); ?> /><span><?php esc_html_e( 'Active and selectable for new Scanner fields', 'persian-gravityforms' ); ?></span></label>
				</section>
				<section class="pgr-panel">
					<h2><?php esc_html_e( 'Parser', 'persian-gravityforms' ); ?></h2>
					<input type="hidden" name="profile[parser][type]" value="segments_v1" />
					<input type="hidden" name="profile[parser][separator]" value="newline" />
					<p><span class="pgr-badge pgr-badge--type" dir="ltr">segments_v1</span> <?php esc_html_e( 'Newline-separated ordered segments.', 'persian-gravityforms' ); ?></p>
					<div class="pgr-checkbox-row">
						<label class="pgr-checkbox"><input type="checkbox" name="profile[parser][trim]" value="1" <?php checked( ! empty( $profile['parser']['trim'] ) ); ?> /><span><?php esc_html_e( 'Trim surrounding whitespace', 'persian-gravityforms' ); ?></span></label>
						<label class="pgr-checkbox"><input type="checkbox" name="profile[parser][normalize_digits]" value="1" <?php checked( ! empty( $profile['parser']['normalize_digits'] ) ); ?> /><span><?php esc_html_e( 'Normalize Persian/Arabic digits to ASCII', 'persian-gravityforms' ); ?></span></label>
					</div>
				</section>
				<section class="pgr-panel">
					<div class="pgr-section-heading">
						<div>
							<h2><?php esc_html_e( 'Outputs', 'persian-gravityforms' ); ?></h2>
							<p><?php esc_html_e( 'Order is part of the parser contract.', 'persian-gravityforms' ); ?></p>
						</div>
						<button type="button" class="button button-secondary" data-pgr-add-output="1"><?php esc_html_e( 'Add output', 'persian-gravityforms' ); ?></button>
					</div>
					<div class="pgr-output-list" data-pgr-output-list="1">
						<?php
						foreach ( $profile['outputs'] as $index => $output ) {
							$this->render_output_row( $output, $index );
						}
						?>
					</div>
				</section>
				<?php submit_button( $is_edit ? __( 'Save Profile', 'persian-gravityforms' ) : __( 'Create Profile', 'persian-gravityforms' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a built-in profile inspection page.
	 *
	 * @param array<string,mixed> $profile Built-in profile.
	 * @return void
	 */
	private function render_profile_inspect( $profile ) {
		?>
		<div class="wrap pgr-admin">
			<h1><?php echo esc_html( $profile['label'] ); ?></h1>
			<p class="pgr-admin__lead"><?php esc_html_e( 'This profile is built into PersianGravity and is read-only.', 'persian-gravityforms' ); ?></p>
			<section class="pgr-panel">
				<div class="pgr-badge-row">
					<span class="pgr-badge pgr-badge--type"><?php esc_html_e( 'Built-in', 'persian-gravityforms' ); ?></span>
					<span class="pgr-badge" data-state="AVAILABLE"><?php esc_html_e( 'Active', 'persian-gravityforms' ); ?></span>
					<span class="pgr-badge" data-state="DISABLED"><?php esc_html_e( 'Read-only', 'persian-gravityforms' ); ?></span>
				</div>
				<dl class="pgr-definition-list">
					<dt><?php esc_html_e( 'Profile ID', 'persian-gravityforms' ); ?></dt>
					<dd><code dir="ltr"><?php echo esc_html( $profile['id'] ); ?></code></dd>
					<dt><?php esc_html_e( 'Parser', 'persian-gravityforms' ); ?></dt>
					<dd><code dir="ltr"><?php echo esc_html( $profile['parser']['type'] ); ?></code></dd>
				</dl>
				<h2><?php esc_html_e( 'Outputs', 'persian-gravityforms' ); ?></h2>
				<ol class="pgr-output-summary">
					<?php foreach ( $profile['outputs'] as $output ) : ?>
						<li><strong><?php echo esc_html( $output['label'] ); ?></strong> <code dir="ltr"><?php echo esc_html( $output['key'] ); ?></code></li>
					<?php endforeach; ?>
				</ol>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pgr_scanner_profile_duplicate" />
					<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile['id'] ); ?>" />
					<?php wp_nonce_field( 'pgr_scanner_profile_duplicate' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Duplicate as custom profile', 'persian-gravityforms' ); ?></button>
				</form>
			</section>
		</div>
		<?php
	}

	/**
	 * Render one profile output row.
	 *
	 * @param array<string,mixed> $output Output.
	 * @param int                 $index  Row index.
	 * @return void
	 */
	private function render_output_row( $output, $index ) {
		?>
		<div class="pgr-output-row" data-pgr-output-row="1">
			<span class="pgr-output-row__index" data-pgr-output-index="1"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
			<label>
				<span><?php esc_html_e( 'Output key', 'persian-gravityforms' ); ?></span>
				<input type="text" class="pgr-ltr" dir="ltr" name="profile[outputs][<?php echo esc_attr( $index ); ?>][key]" pattern="[a-z][a-z0-9_]*" value="<?php echo esc_attr( $output['key'] ); ?>" required />
			</label>
			<label>
				<span><?php esc_html_e( 'Output label', 'persian-gravityforms' ); ?></span>
				<input type="text" name="profile[outputs][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $output['label'] ); ?>" required />
			</label>
			<label class="pgr-checkbox pgr-output-row__required">
				<input type="checkbox" name="profile[outputs][<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( ! empty( $output['required'] ) ); ?> />
				<span><?php esc_html_e( 'Required', 'persian-gravityforms' ); ?></span>
			</label>
			<div class="pgr-output-row__actions">
				<button type="button" class="button button-small" data-pgr-output-up="1"><?php esc_html_e( 'Up', 'persian-gravityforms' ); ?></button>
				<button type="button" class="button button-small" data-pgr-output-down="1"><?php esc_html_e( 'Down', 'persian-gravityforms' ); ?></button>
				<button type="button" class="button button-small" data-pgr-output-remove="1"><?php esc_html_e( 'Remove', 'persian-gravityforms' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Build a bounded profile candidate from POST data.
	 *
	 * Registry validation remains authoritative. This method is invoked only
	 * after handle_profile_save() has verified capability and nonce.
	 *
	 * @return array<string,mixed>
	 */
	private function profile_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by handle_profile_save() before this method is called.
		$input         = isset( $_POST['profile'] ) && is_array( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array();
		$parser        = isset( $input['parser'] ) && is_array( $input['parser'] ) ? $input['parser'] : array();
		$outputs       = isset( $input['outputs'] ) && is_array( $input['outputs'] ) ? $input['outputs'] : array();
		$clean_outputs = array();

		foreach ( $outputs as $output ) {
			if ( ! is_array( $output ) ) {
				continue;
			}
			$clean_outputs[] = array(
				'key'      => isset( $output['key'] ) ? sanitize_text_field( $output['key'] ) : '',
				'label'    => isset( $output['label'] ) ? sanitize_text_field( $output['label'] ) : '',
				'required' => ! empty( $output['required'] ),
			);
		}

		return array(
			'id'      => isset( $input['id'] ) ? sanitize_text_field( $input['id'] ) : '',
			'label'   => isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : '',
			'enabled' => ! empty( $input['enabled'] ),
			'parser'  => array(
				'type'             => isset( $parser['type'] ) ? sanitize_key( $parser['type'] ) : '',
				'separator'        => isset( $parser['separator'] ) ? sanitize_key( $parser['separator'] ) : '',
				'trim'             => ! empty( $parser['trim'] ),
				'normalize_digits' => ! empty( $parser['normalize_digits'] ),
			),
			'outputs' => $clean_outputs,
		);
	}

	/**
	 * Assert capability and nonce for profile writes.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private function assert_profile_write_access( $nonce_action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Scanner Profiles.', 'persian-gravityforms' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirect to Scanner Profiles.
	 *
	 * @param array<string,string> $args Query args.
	 * @return void
	 */
	private function redirect_profiles( $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . self::PROFILES_SLUG ) ) );
		exit;
	}

	/**
	 * Build the next available duplicate profile ID.
	 *
	 * @param string $source_id Source ID.
	 * @return string
	 */
	private function next_duplicate_id( $source_id ) {
		$base = trim( (string) preg_replace( '/[^a-z0-9_]/', '_', strtolower( $source_id ) ), '_' ) . '_copy';
		if ( 1 !== preg_match( '/^[a-z]/', $base ) ) {
			$base = 'profile_' . $base;
		}

		$candidate = $base;
		$suffix    = 2;
		while ( null !== PGR_Scanner_Profile_Registry::get( $candidate ) ) {
			$candidate = $base . '_' . $suffix;
			++$suffix;
		}

		return $candidate;
	}

	/**
	 * Render redirect notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice selection; no state mutation occurs here.
		$notice   = isset( $_GET['pgr_notice'] ) ? sanitize_key( wp_unslash( $_GET['pgr_notice'] ) ) : '';
		$messages = array(
			'saved'           => __( 'Scanner profile saved.', 'persian-gravityforms' ),
			'deleted'         => __( 'Scanner profile deleted.', 'persian-gravityforms' ),
			'duplicated'      => __( 'Profile duplicated. Review the new custom profile before use.', 'persian-gravityforms' ),
			'save_error'      => __( 'The Scanner profile could not be saved. Check the profile ID, labels, parser settings, and output definitions.', 'persian-gravityforms' ),
			'delete_error'    => __( 'The Scanner profile could not be deleted.', 'persian-gravityforms' ),
			'duplicate_error' => __( 'The Scanner profile could not be duplicated.', 'persian-gravityforms' ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		$is_error = in_array( $notice, array( 'save_error', 'delete_error', 'duplicate_error' ), true );
		printf(
			'<div class="notice %1$s inline"><p>%2$s</p></div>',
			esc_attr( $is_error ? 'notice-error' : 'notice-success' ),
			esc_html( $messages[ $notice ] )
		);
	}

	/**
	 * Render a capability card.
	 *
	 * @param string $title Title.
	 * @param string $body  Body.
	 * @return void
	 */
	private function capability_card( $title, $body ) {
		printf(
			'<section class="pgr-card"><h2>%1$s</h2><p>%2$s</p></section>',
			esc_html( $title ),
			esc_html( $body )
		);
	}

	/**
	 * Render a status card.
	 *
	 * @param string $title Title.
	 * @param string $value Value.
	 * @param string $state State.
	 * @return void
	 */
	private function status_card( $title, $value, $state ) {
		$labels = array(
			'AVAILABLE'   => __( 'Available', 'persian-gravityforms' ),
			'UNAVAILABLE' => __( 'Unavailable', 'persian-gravityforms' ),
			'WARNING'     => __( 'Warning', 'persian-gravityforms' ),
		);

		printf(
			'<section class="pgr-card"><div class="pgr-profile-card__heading"><h2>%1$s</h2><span class="pgr-badge" data-state="%2$s">%3$s</span></div><p class="pgr-status-value">%4$s</p></section>',
			esc_html( $title ),
			esc_attr( $state ),
			esc_html( $labels[ $state ] ?? $state ),
			esc_html( $value )
		);
	}

	/**
	 * Remember a registered page hook.
	 *
	 * @param mixed $hook Page hook.
	 * @return void
	 */
	private function remember_hook( $hook ) {
		if ( is_string( $hook ) && '' !== $hook ) {
			$this->page_hooks[] = $hook;
		}
	}
}
