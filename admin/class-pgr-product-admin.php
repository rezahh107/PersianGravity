<?php
/**
 * PersianGravity 4.2 product admin shell and Module Manager.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Product_Admin {
	const HELP_SLUG   = 'persian-gravityforms-help';
	const CONFIRM_TTL = 300;

	/** @var PGR_Admin */
	private $legacy;

	/** @var array<string,string> */
	private $page_topics = array();

	/** @var array<int,string> */
	private $page_hooks = array();

	/** @param PGR_Admin $legacy Existing Scanner Profiles/settings implementation. */
	public function __construct( PGR_Admin $legacy ) {
		$this->legacy = $legacy;
	}

	/** Register non-disableable admin infrastructure. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this->legacy, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_pgr_module_toggle', array( $this, 'handle_module_toggle' ) );
		add_action( 'admin_post_pgr_scanner_profile_save', array( $this->legacy, 'handle_profile_save' ) );
		add_action( 'admin_post_pgr_scanner_profile_delete', array( $this->legacy, 'handle_profile_delete' ) );
		add_action( 'admin_post_pgr_scanner_profile_duplicate', array( $this->legacy, 'handle_profile_duplicate' ) );
	}

	/** Register the canonical product menu. */
	public function add_menu() {
		$overview_hook = add_menu_page(
			__( 'Persian Gravity', 'persian-gravityforms' ),
			__( 'Persian Gravity', 'persian-gravityforms' ),
			PGR_Admin::CAPABILITY,
			PGR_Admin::MENU_SLUG,
			array( $this, 'render_overview_page' ),
			'dashicons-forms',
			58
		);
		$this->remember( $overview_hook, 'module-manager' );
		if ( is_string( $overview_hook ) && '' !== $overview_hook ) {
			// WordPress runs this screen-specific load hook before admin-header.php.
			add_action( 'load-' . $overview_hook, array( $this, 'handle_overview_cancel' ) );
		}

		$pages = array(
			array( 'Overview', PGR_Admin::MENU_SLUG, 'render_overview_page', 'module-manager' ),
			array( 'Scanner Profiles', PGR_Admin::PROFILES_SLUG, 'render_profiles_page', 'scanner-profiles' ),
			array( 'Settings', PGR_Admin::SETTINGS_SLUG, 'render_settings_page', 'settings' ),
			array( 'System Status', PGR_Admin::STATUS_SLUG, 'render_status_page', 'system-status' ),
			array( 'Help & Documentation', self::HELP_SLUG, 'render_help_page', 'quick-start' ),
		);

		foreach ( $pages as $page ) {
			$this->remember(
				add_submenu_page(
					PGR_Admin::MENU_SLUG,
					__( $page[0], 'persian-gravityforms' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					__( $page[0], 'persian-gravityforms' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					PGR_Admin::CAPABILITY,
					$page[1],
					array( $this, $page[2] )
				),
				$page[3]
			);
		}
	}

	/** Load product assets only on PersianGravity screens. */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'pgr-admin', PGR_URL . 'assets/css/pgr-admin.css', array(), PGR_VERSION );
		if ( false !== strpos( $hook_suffix, self::HELP_SLUG ) ) {
			wp_enqueue_script( 'pgr-admin-help', PGR_URL . 'assets/js/pgr-admin-help.js', array(), PGR_VERSION, true );
		}
		if ( false === strpos( $hook_suffix, PGR_Admin::PROFILES_SLUG ) ) {
			return;
		}

		wp_enqueue_script( 'pgr-admin-profiles', PGR_URL . 'assets/js/pgr-admin-profiles.js', array(), PGR_VERSION, true );
		wp_localize_script(
			'pgr-admin-profiles',
			'PGRProfileAdmin',
			array(
				'outputKey'     => __( 'Output key', 'persian-gravityforms' ),
				'outputLabel'   => __( 'Output label', 'persian-gravityforms' ),
				'required'      => __( 'Required', 'persian-gravityforms' ),
				'moveUp'        => __( 'Move up', 'persian-gravityforms' ),
				'moveDown'      => __( 'Move down', 'persian-gravityforms' ),
				'remove'        => __( 'Remove', 'persian-gravityforms' ),
				'deleteConfirm' => __( 'Deleting this profile may leave existing Structured Scanner fields with an unavailable profile. Continue?', 'persian-gravityforms' ),
			)
		);
	}

	/**
	 * Handle the non-mutating Cancel link before WordPress sends the admin header.
	 * Only the canonical Overview page's load-{page_hook} calls this callback.
	 */
	public function handle_overview_cancel() {
		if ( ! current_user_can( PGR_Admin::CAPABILITY ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A GET Cancel can only revoke the current user's matching ephemeral intent.
		$cancel_token = $this->query_key( 'pgr_cancel_confirm' );
		if ( '' === $cancel_token ) {
			return;
		}
		$pending = get_transient( $this->disable_confirmation_key() );
		if ( is_array( $pending ) &&
			$this->matches_disable_confirmation( $pending, $pending['module_id'] ?? '', $cancel_token ) ) {
			delete_transient( $this->disable_confirmation_key() );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . PGR_Admin::MENU_SLUG ) );
		exit;
	}

	/** Render Overview / Module Manager. */
	public function render_overview_page() {
		if ( ! current_user_can( PGR_Admin::CAPABILITY ) ) {
			return;
		}

		$lang     = $this->primary_language();
		$decision = $this->pending_disable_confirmation();
		?>
		<div class="wrap pgr-admin">
			<div class="pgr-admin__header">
				<div>
					<h1><?php esc_html_e( 'Persian Gravity', 'persian-gravityforms' ); ?></h1>
					<p class="pgr-admin__lead"><?php esc_html_e( 'Manage the bounded Persian/Iranian capabilities that participate in Gravity Forms runtime.', 'persian-gravityforms' ); ?></p>
				</div>
				<span class="pgr-badge" data-state="AVAILABLE"><?php echo esc_html( PGR_VERSION ); ?></span>
			</div>
			<?php $this->render_notice( $decision ); ?>
			<?php
			if ( $decision ) {
				$this->render_disable_decision( $decision['module'], $decision['token'], $lang );
			}
			?>
			<div class="pgr-module-grid pgr-module-list" role="list">
				<?php
				foreach ( PGR_Module_Registry::all() as $module ) {
					if ( ! $decision || $decision['module']['id'] !== $module['id'] ) {
						$this->module_card( $module, $lang );
					}
				}
				?>
			</div>
		</div>
		<?php
	}

	/** Keep Scanner Profiles administration available when Scanner runtime is off. */
	public function render_profiles_page() {
		if ( ! PGR_Module_Registry::is_enabled( 'structured_scanner' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Structured Scanner runtime is disabled. Profiles remain available and will be used again after the module is enabled.', 'persian-gravityforms' ) . '</p></div>';
		}
		$this->legacy->render_profiles_page();
	}

	/** Preserve existing settings while explaining inactive National-ID defaults. */
	public function render_settings_page() {
		if ( ! PGR_Module_Registry::is_enabled( 'national_id' ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'National ID is disabled. Its saved default remains preserved but is inactive until the module is enabled again.', 'persian-gravityforms' ) . '</p></div>';
		}
		$this->legacy->render_settings_page();
	}

	/**
	 * Return only a recent, user-bound UNKNOWN decision for an enabled module.
	 * GET parameters select a candidate; they never establish inspection authority.
	 *
	 * @return array{module:array<string,mixed>,token:string}|null
	 */
	private function pending_disable_confirmation() {
		if ( 'confirm' !== $this->query_key( 'pgr_module_notice' ) ) {
			return null;
		}
		$id = $this->query_key( 'pgr_module' );
		if ( $id !== $this->query_key( 'pgr_confirm_module' ) ||
			! PGR_Module_Registry::exists( $id ) ||
			! PGR_Module_Registry::is_enabled( $id ) ) {
			return null;
		}
		$token   = $this->query_key( 'pgr_confirm_token' );
		$pending = get_transient( $this->disable_confirmation_key() );
		if ( ! $this->matches_disable_confirmation( $pending, $id, $token ) ||
			PGR_Module_Usage::UNKNOWN !== PGR_Module_Usage::inspect( $id )['status'] ) {
			return null;
		}
		return array(
			'module' => PGR_Module_Registry::get( $id ),
			'token'  => $token,
		);
	}

	/** User-specific ephemeral presentation intent, not a usage-inspection cache. */
	private function disable_confirmation_key() {
		return 'pgr_disable_confirm_' . get_current_user_id();
	}

	/** @param mixed $pending Stored one-time decision context. */
	private function matches_disable_confirmation( $pending, $module_id, $token ) {
		return is_array( $pending ) && is_string( $pending['module_id'] ?? null ) &&
			is_string( $pending['token'] ?? null ) && '' !== $token &&
			$pending['module_id'] === $module_id && hash_equals( $pending['token'], $token );
	}

	/** Render the only actionable confirmation, immediately before module grid. */
	private function render_disable_decision( $module, $token, $lang ) {
		$primary   = 'fa' === $lang ? 'fa' : 'en';
		$secondary = 'fa' === $primary ? 'en' : 'fa';
		$copy      = array(
			'title'   => __( 'Confirm module disable', 'persian-gravityforms' ),
			'risk'    => __( 'Safe non-use could not be established. Disabling this module could affect existing forms or functionality.', 'persian-gravityforms' ),
			'pending' => __( 'No module state has changed yet. Confirm only if you accept the possible consequences.', 'persian-gravityforms' ),
			'confirm' => __( 'Confirm disable', 'persian-gravityforms' ),
			'cancel'  => __( 'Cancel and return', 'persian-gravityforms' ),
		);
		?>
		<section class="pgr-panel pgr-module-decision" aria-labelledby="pgr-module-decision-title">
			<h2 id="pgr-module-decision-title"><?php echo esc_html( $copy['title'] ); ?></h2>
			<h3 dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'label_' . $primary ] ); ?></h3>
			<p class="pgr-module-card__secondary" dir="<?php echo 'fa' === $secondary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'label_' . $secondary ] ); ?></p>
			<p dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'description_' . $primary ] ); ?></p>
			<p class="pgr-module-card__secondary" dir="<?php echo 'fa' === $secondary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'description_' . $secondary ] ); ?></p>
			<code dir="ltr"><?php echo esc_html( $module['id'] ); ?></code>
			<?php if ( 'jalali_presentation' === $module['id'] ) { ?>
				<p class="pgr-module-card__feature" dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( 'fa' === $primary ? 'تگ‌های اختصاصی جلالی برای تأییدیه، اعلان و Query String؛ مثال آماده و فهرست کامل در Help همین ماژول.' : 'Explicit Jalali Merge Tags for confirmations, notifications and qualified query strings; full copyable guide in this module’s Help.' ); ?> <code dir="ltr">{pgr_jdate_created_date}</code></p>
			<?php } ?>
			<div class="notice notice-warning inline" role="alert">
				<p><?php echo esc_html( $copy['risk'] ); ?></p>
				<p><?php echo esc_html( $copy['pending'] ); ?></p>
			</div>
			<div class="pgr-actions pgr-module-decision__actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pgr_module_toggle">
					<input type="hidden" name="module_id" value="<?php echo esc_attr( $module['id'] ); ?>">
					<input type="hidden" name="enable" value="0">
					<input type="hidden" name="confirm" value="1">
					<input type="hidden" name="confirm_token" value="<?php echo esc_attr( $token ); ?>">
					<?php wp_nonce_field( 'pgr_module_toggle' ); ?>
					<button type="submit" class="button button-primary"><?php echo esc_html( $copy['confirm'] ); ?></button>
				</form>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'pgr_cancel_confirm', $token, admin_url( 'admin.php?page=' . PGR_Admin::MENU_SLUG ) ) ); ?>"><?php echo esc_html( $copy['cancel'] ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::HELP_SLUG . '&lang=' . $lang . '#' . $module['help_topic'] ) ); ?>"><?php esc_html_e( 'Help', 'persian-gravityforms' ); ?></a>
			</div>
		</section>
		<?php
	}

	/** Render concise System Status including module state. */
	public function render_status_page() {
		if ( ! current_user_can( PGR_Admin::CAPABILITY ) ) {
			return;
		}

		$gf = class_exists( 'GFForms' );
		?>
		<div class="wrap pgr-admin">
			<h1><?php esc_html_e( 'System Status', 'persian-gravityforms' ); ?></h1>
			<div class="pgr-card-grid">
				<?php $this->status_card( 'PersianGravity', PGR_VERSION, 'AVAILABLE' ); ?>
				<?php $this->status_card( 'WordPress', get_bloginfo( 'version' ), 'AVAILABLE' ); ?>
				<?php $this->status_card( 'PHP', PHP_VERSION, 'AVAILABLE' ); ?>
				<?php $this->status_card( 'Gravity Forms', $gf ? GFForms::$version : __( 'Not available', 'persian-gravityforms' ), $gf ? 'AVAILABLE' : 'WARNING' ); ?>
				<?php
				foreach ( PGR_Module_Registry::all() as $module ) {
					$enabled = PGR_Module_Registry::is_enabled( $module['id'] );
					$this->status_card(
						$module['label_en'],
						$enabled ? __( 'Enabled', 'persian-gravityforms' ) : __( 'Disabled', 'persian-gravityforms' ),
						$enabled ? 'AVAILABLE' : 'DISABLED'
					);
				}

				$profile_count = sprintf(
					/* translators: %d: number of effective scanner profiles. */
					__( '%d effective profiles', 'persian-gravityforms' ),
					count( PGR_Scanner_Profile_Registry::all() )
				);
				$this->status_card( __( 'Profile Registry', 'persian-gravityforms' ), $profile_count, 'AVAILABLE' );
				?>
			</div>
			<?php $this->render_gravity_flow_compatibility_status(); ?>
		</div>
		<?php
	}

	/** Render reporting-only Gravity Flow compatibility diagnostics. */
	public function render_gravity_flow_compatibility_status() {
		if ( ! class_exists( 'PGR_Gravity_Flow_Compatibility_Diagnostics', false ) ) {
			return;
		}

		$capabilities = PGR_Gravity_Flow_Compatibility_Diagnostics::capabilities();
		$snapshot     = PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot();
		?>
		<section class="pgr-panel pgr-compatibility-status">
			<h2 id="pgr-gravity-flow-compatibility-heading"><?php echo esc_html__( 'Gravity Flow compatibility diagnostics', 'persian-gravityforms' ); ?></h2>
			<p class="pgr-status-value"><?php echo esc_html__( 'Informational maintenance evidence only. These observations do not enable or disable compatibility.', 'persian-gravityforms' ); ?></p>
			<div class="pgr-compatibility-status__table-scroll" role="region" aria-labelledby="pgr-gravity-flow-compatibility-heading" tabindex="0">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Capability', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'State', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Reason', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Observed Gravity Flow', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Last observed', 'persian-gravityforms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $capabilities as $capability_id => $label ) : ?>
						<?php
						$record      = $snapshot[ $capability_id ];
						$state       = (string) $record['state'];
						$reason_id   = (string) $record['reason_id'];
						$summary     = (string) $record['summary'];
						$host        = is_string( $record['host_version'] ) && '' !== $record['host_version'] ? $record['host_version'] : __( 'Not observed', 'persian-gravityforms' );
						$observed_at = is_int( $record['observed_at'] ) ? gmdate( 'Y-m-d H:i:s \\U\\T\\C', $record['observed_at'] ) : __( 'Not observed', 'persian-gravityforms' );
						?>
						<tr>
							<td><strong><?php echo esc_html( $label ); ?></strong><br><code class="pgr-ltr"><?php echo esc_html( $capability_id ); ?></code></td>
							<td><span class="pgr-badge" data-state="<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $state ); ?></span></td>
							<td><?php echo esc_html( $summary ); ?><br><code class="pgr-ltr"><?php echo esc_html( $reason_id ); ?></code></td>
							<td><code class="pgr-ltr"><?php echo esc_html( $host ); ?></code></td>
							<td><code class="pgr-ltr"><?php echo esc_html( $observed_at ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</section>
		<?php
	}

	/** Render local, version-controlled bilingual Help Center. */
	public function render_help_page() {
		if ( ! current_user_can( PGR_Admin::CAPABILITY ) ) {
			return;
		}

		$lang     = $this->help_language();
		$topics   = PGR_Help_Catalog::all();
		$examples = PGR_Help_Catalog::examples();
		?>
		<div class="wrap pgr-admin pgr-help">
			<h1><?php esc_html_e( 'Help & Documentation', 'persian-gravityforms' ); ?></h1>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Documentation language', 'persian-gravityforms' ); ?>">
				<a class="nav-tab <?php echo 'fa' === $lang ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::HELP_SLUG . '&lang=fa' ) ); ?>">فارسی</a>
				<a class="nav-tab <?php echo 'en' === $lang ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::HELP_SLUG . '&lang=en' ) ); ?>">English</a>
			</nav>
			<div class="pgr-help__layout" dir="<?php echo 'fa' === $lang ? 'rtl' : 'ltr'; ?>">
				<nav class="pgr-panel pgr-help__toc">
					<strong><?php esc_html_e( 'Contents', 'persian-gravityforms' ); ?></strong>
					<ul>
						<?php foreach ( $topics as $id => $translations ) { ?>
							<li><a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $translations[ $lang ]['title'] ); ?></a></li>
						<?php } ?>
					</ul>
				</nav>
				<main class="pgr-help__content">
					<?php foreach ( $topics as $id => $translations ) { ?>
						<section class="pgr-panel" id="<?php echo esc_attr( $id ); ?>">
							<h2><?php echo esc_html( $translations[ $lang ]['title'] ); ?></h2>
							<?php echo wp_kses_post( $translations[ $lang ]['body'] ); ?>
							<?php if ( isset( $examples[ $id ][ $lang ] ) ) { ?>
								<?php $this->render_worked_example( $examples[ $id ][ $lang ], $lang ); ?>
							<?php } ?>
						</section>
					<?php } ?>
				</main>
			</div>
		</div>
		<?php
	}


	/** Render one escaped example and keyboard-copyable text samples. */
	private function render_worked_example( $example, $lang ) {
		$labels = 'fa' === $lang
			? array(
				'title'         => 'مثال عملی گام‌به‌گام',
				'scenario'      => 'سناریو',
				'prerequisites' => 'پیش‌نیاز',
				'steps'         => 'مراحل تنظیم و اجرا',
				'samples'       => 'نمونه‌های قابل کپی',
				'expected'      => 'نتیجه مورد انتظار',
				'verification'  => 'روش بررسی',
				'notes'         => 'نکات و محدودیت‌ها',
				'copy'          => 'کپی',
				'success'       => 'کپی شد.',
				'failure'       => 'کپی خودکار ممکن نشد؛ متن را دستی انتخاب و کپی کنید.',
			)
			: array(
				'title'         => 'Step-by-step worked example',
				'scenario'      => 'Scenario',
				'prerequisites' => 'Prerequisites',
				'steps'         => 'Configure and try',
				'samples'       => 'Copy-ready samples',
				'expected'      => 'Expected result',
				'verification'  => 'How to verify',
				'notes'         => 'Notes and limits',
				'copy'          => 'Copy',
				'success'       => 'Copied.',
				'failure'       => 'Copy failed; select and copy the text manually.',
			);
		?>
		<div class="pgr-help-example" aria-label="<?php echo esc_attr( $labels['title'] ); ?>">
			<h3><?php echo esc_html( $labels['title'] ); ?></h3>
			<?php foreach ( array( 'scenario', 'prerequisites' ) as $section ) { ?>
				<h4><?php echo esc_html( $labels[ $section ] ); ?></h4>
				<p><?php echo esc_html( $example[ $section ] ); ?></p>
			<?php } ?>
			<h4><?php echo esc_html( $labels['steps'] ); ?></h4>
			<ol>
				<?php foreach ( $example['steps'] as $step ) { ?>
					<li><?php echo esc_html( $step ); ?></li>
				<?php } ?>
			</ol>
			<h4><?php echo esc_html( $labels['samples'] ); ?></h4>
			<?php foreach ( $example['samples'] as $sample ) { ?>
				<div class="pgr-help-sample">
					<div class="pgr-help-sample__header">
						<strong><?php echo esc_html( $sample['label'] ); ?></strong>
						<button type="button" class="button button-small" data-pgr-copy="1" data-pgr-copy-success="<?php echo esc_attr( $labels['success'] ); ?>" data-pgr-copy-failure="<?php echo esc_attr( $labels['failure'] ); ?>" aria-label="<?php echo esc_attr( $labels['copy'] . ': ' . $sample['label'] ); ?>"><?php echo esc_html( $labels['copy'] ); ?></button>
					</div>
					<pre class="pgr-help-sample__code" dir="auto" tabindex="0"><code><?php echo esc_html( $sample['value'] ); ?></code></pre>
					<span class="pgr-help-sample__status" role="status" aria-live="polite" aria-atomic="true"></span>
				</div>
			<?php } ?>
			<?php foreach ( array( 'expected', 'verification', 'notes' ) as $section ) { ?>
				<h4><?php echo esc_html( $labels[ $section ] ); ?></h4>
				<p><?php echo esc_html( $example[ $section ] ); ?></p>
			<?php } ?>
		</div>
		<?php
	}

	/** Add concise native contextual Help for the current product screen. */
	public function add_contextual_help() {
		$screen = get_current_screen();
		if ( ! $screen || ! isset( $this->page_topics[ $screen->id ] ) ) {
			return;
		}

		$topic_id = $this->page_topics[ $screen->id ];
		$lang     = $this->primary_language();
		$topic    = PGR_Help_Catalog::get( $topic_id, $lang );
		if ( ! $topic ) {
			return;
		}

		$link = admin_url( 'admin.php?page=' . self::HELP_SLUG . '&lang=' . $lang . '#' . $topic_id );
		$screen->add_help_tab(
			array(
				'id'      => 'pgr-context-help',
				'title'   => __( 'PersianGravity Help', 'persian-gravityforms' ),
				'content' => '<p>' . esc_html( wp_strip_all_tags( $topic['summary'] ) ) . '</p><p><a href="' . esc_url( $link ) . '">' . esc_html__( 'Open full documentation', 'persian-gravityforms' ) . '</a></p>',
			)
		);
	}

	/** Process one consequential module-state write. */
	public function handle_module_toggle() {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'persian-gravityforms' ) );
		}
		if ( ! current_user_can( PGR_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change PersianGravity modules.', 'persian-gravityforms' ) );
		}

		check_admin_referer( 'pgr_module_toggle' );
		$module_id = isset( $_POST['module_id'] ) ? sanitize_key( wp_unslash( $_POST['module_id'] ) ) : '';
		$enable    = isset( $_POST['enable'] ) && '1' === (string) wp_unslash( $_POST['enable'] );
		$confirm   = isset( $_POST['confirm'] ) && '1' === (string) wp_unslash( $_POST['confirm'] );
		$token     = isset( $_POST['confirm_token'] ) ? sanitize_key( wp_unslash( $_POST['confirm_token'] ) ) : '';

		if ( ! PGR_Module_Registry::exists( $module_id ) ) {
			$this->redirect( array( 'pgr_module_notice' => 'invalid' ) );
		}

		if ( ! $enable ) {
			// Always reinspect: a confirmation ticket is never evidence of non-use.
			$usage = PGR_Module_Usage::inspect( $module_id );
			if ( PGR_Module_Usage::USED === $usage['status'] ) {
				$this->redirect(
					array(
						'pgr_module_notice' => 'blocked',
						'pgr_module'        => $module_id,
						'pgr_usage_count'   => $usage['count'],
					)
				);
			}
			if ( $confirm ) {
				$pending = get_transient( $this->disable_confirmation_key() );
				if ( ! $this->matches_disable_confirmation( $pending, $module_id, $token ) ||
					! PGR_Module_Registry::is_enabled( $module_id ) ) {
					$this->redirect( array( 'pgr_module_notice' => 'invalid' ) );
				}
				// One-time intent; even a reinspection that now says UNUSED never
				// promotes a stale token to ongoing permission.
				delete_transient( $this->disable_confirmation_key() );
			} elseif ( PGR_Module_Usage::UNKNOWN === $usage['status'] ) {
				$token = wp_generate_uuid4();
				if ( ! set_transient(
					$this->disable_confirmation_key(),
					array(
						'module_id' => $module_id,
						'token'     => $token,
					),
					self::CONFIRM_TTL
				) ) {
					$this->redirect( array( 'pgr_module_notice' => 'invalid' ) );
				}
				$this->redirect(
					array(
						'pgr_module_notice'  => 'confirm',
						'pgr_module'         => $module_id,
						'pgr_confirm_module' => $module_id,
						'pgr_confirm_token'  => $token,
					)
				);
			}
		}

		PGR_Module_Registry::set_enabled( $module_id, $enable );
		$this->redirect(
			array(
				'pgr_module_notice' => $enable ? 'enabled' : 'disabled',
				'pgr_module'        => $module_id,
			)
		);
	}

	/** @param array<string,mixed> $module Module metadata. */
	private function module_card( $module, $lang ) {
		$id        = $module['id'];
		$enabled   = PGR_Module_Registry::is_enabled( $id );
		$primary   = 'fa' === $lang ? 'fa' : 'en';
		$secondary = 'fa' === $primary ? 'en' : 'fa';
		?>
		<article class="pgr-card pgr-module-card" role="listitem">
			<div class="pgr-module-card__details">
				<div class="pgr-module-card__heading">
					<h2 id="pgr-module-name-<?php echo esc_attr( $id ); ?>" dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'label_' . $primary ] ); ?></h2>
					<p class="pgr-module-card__secondary" dir="<?php echo 'fa' === $secondary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'label_' . $secondary ] ); ?></p>
				</div>
				<p dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'description_' . $primary ] ); ?></p>
				<p class="pgr-module-card__secondary" dir="<?php echo 'fa' === $secondary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'description_' . $secondary ] ); ?></p>
				<code dir="ltr"><?php echo esc_html( $id ); ?></code>
				<?php if ( 'jalali_presentation' === $id ) { ?>
					<p class="pgr-module-card__feature" dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( 'fa' === $primary ? 'تگ‌های اختصاصی جلالی برای تأییدیه، اعلان و Query String؛ مثال آماده و فهرست کامل در Help همین ماژول.' : 'Explicit Jalali Merge Tags for confirmations, notifications and qualified query strings; full copyable guide in this module’s Help.' ); ?> <code dir="ltr">{pgr_jdate_created_date}</code></p>
				<?php } ?>
			</div>
			<div class="pgr-module-card__controls">
				<span class="pgr-badge" data-state="<?php echo $enabled ? 'AVAILABLE' : 'DISABLED'; ?>"><?php echo esc_html( $enabled ? __( 'Enabled', 'persian-gravityforms' ) : __( 'Disabled', 'persian-gravityforms' ) ); ?></span>
				<div class="pgr-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="pgr_module_toggle">
						<input type="hidden" name="module_id" value="<?php echo esc_attr( $id ); ?>">
						<input type="hidden" name="enable" value="<?php echo $enabled ? '0' : '1'; ?>">
						<?php wp_nonce_field( 'pgr_module_toggle' ); ?>
						<button id="pgr-module-toggle-<?php echo esc_attr( $id ); ?>" type="submit" class="button" aria-labelledby="pgr-module-toggle-<?php echo esc_attr( $id ); ?> pgr-module-name-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $enabled ? __( 'Disable', 'persian-gravityforms' ) : __( 'Enable', 'persian-gravityforms' ) ); ?></button>
					</form>
					<a id="pgr-module-help-<?php echo esc_attr( $id ); ?>" class="button" aria-labelledby="pgr-module-help-<?php echo esc_attr( $id ); ?> pgr-module-name-<?php echo esc_attr( $id ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::HELP_SLUG . '&lang=' . $lang . '#' . $module['help_topic'] ) ); ?>"><?php esc_html_e( 'Help', 'persian-gravityforms' ); ?></a>
				</div>
			</div>
		</article>
		<?php
	}

	private function render_notice( $decision = null ) {
		$notice = $this->query_key( 'pgr_module_notice' );
		$id     = $this->query_key( 'pgr_module' );
		$module = PGR_Module_Registry::get( $id );
		$label  = $module ? $module['label_en'] : __( 'Module', 'persian-gravityforms' );

		if ( '' === $notice ) {
			return;
		}

		$class   = 'notice-info';
		$message = '';

		if ( 'enabled' === $notice ) {
			$class = 'notice-success';
			/* translators: %s: module label. */
			$message = sprintf( __( '%s has been enabled.', 'persian-gravityforms' ), $label );
		} elseif ( 'disabled' === $notice ) {
			$class = 'notice-success';
			/* translators: %s: module label. */
			$message = sprintf( __( '%s has been disabled.', 'persian-gravityforms' ), $label );
		} elseif ( 'blocked' === $notice ) {
			$class = 'notice-error';
			$count = isset( $_GET['pgr_usage_count'] ) ? absint( $_GET['pgr_usage_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: 1: module label, 2: number of forms using the module. */
			$message = sprintf( __( '%1$s is used by %2$d form(s) and cannot be disabled safely.', 'persian-gravityforms' ), $label, $count );
		} elseif ( 'confirm' === $notice && ! $decision ) {
			$class   = 'notice-warning';
			$message = __( 'This disable confirmation is no longer valid. Start a new request from the module card.', 'persian-gravityforms' );
		} elseif ( 'invalid' === $notice ) {
			$class   = 'notice-error';
			$message = __( 'The requested module state change was invalid.', 'persian-gravityforms' );
		}

		if ( $message ) {
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	private function status_card( $label, $value, $state ) {
		echo '<section class="pgr-card"><h2>' . esc_html( $label ) . '</h2><p>' . esc_html( $value ) . '</p><span class="pgr-badge" data-state="' . esc_attr( $state ) . '">' . esc_html( $state ) . '</span></section>';
	}

	private function remember( $hook, $topic ) {
		if ( is_string( $hook ) && $hook ) {
			$this->page_hooks[]         = $hook;
			$this->page_topics[ $hook ] = $topic;
			add_action( 'load-' . $hook, array( $this, 'add_contextual_help' ) );
		}
	}

	private function primary_language() {
		return 0 === strpos( strtolower( (string) get_user_locale() ), 'fa' ) ? 'fa' : 'en';
	}

	private function help_language() {
		$lang = $this->query_key( 'lang' );
		return in_array( $lang, array( 'fa', 'en' ), true ) ? $lang : $this->primary_language();
	}

	private function query_key( $key ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only UI selectors, never state authority.
		// GET selectors are presentation data, never an authority; reject
		// malformed arrays rather than passing them into sanitize_key().
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] )
			? sanitize_key( wp_unslash( $_GET[ $key ] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . PGR_Admin::MENU_SLUG ) ) );
		exit;
	}
}
