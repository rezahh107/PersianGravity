<?php
/**
 * PersianGravity 4.2 product admin shell and Module Manager.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Product_Admin {
	const HELP_SLUG = 'persian-gravityforms-help';

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
		$this->remember(
			add_menu_page(
				__( 'Persian Gravity', 'persian-gravityforms' ),
				__( 'Persian Gravity', 'persian-gravityforms' ),
				PGR_Admin::CAPABILITY,
				PGR_Admin::MENU_SLUG,
				array( $this, 'render_overview_page' ),
				'dashicons-forms',
				58
			),
			'module-manager'
		);

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

	/** Render Overview / Module Manager. */
	public function render_overview_page() {
		if ( ! current_user_can( PGR_Admin::CAPABILITY ) ) {
			return;
		}

		$lang    = $this->primary_language();
		$confirm = $this->query_key( 'pgr_confirm_module' );
		?>
		<div class="wrap pgr-admin">
			<div class="pgr-admin__header">
				<div>
					<h1><?php esc_html_e( 'Persian Gravity', 'persian-gravityforms' ); ?></h1>
					<p class="pgr-admin__lead"><?php esc_html_e( 'Manage the bounded Persian/Iranian capabilities that participate in Gravity Forms runtime.', 'persian-gravityforms' ); ?></p>
				</div>
				<span class="pgr-badge" data-state="AVAILABLE"><?php echo esc_html( PGR_VERSION ); ?></span>
			</div>
			<?php $this->render_notice(); ?>
			<div class="pgr-card-grid pgr-module-grid">
				<?php
				foreach ( PGR_Module_Registry::all() as $module ) {
					$this->module_card( $module, $lang, $confirm );
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
		</div>
		<?php
	}

	/** Render local, version-controlled bilingual Help Center. */
	public function render_help_page() {
		if ( ! current_user_can( PGR_Admin::CAPABILITY ) ) {
			return;
		}

		$lang   = $this->help_language();
		$topics = PGR_Help_Catalog::all();
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
						</section>
					<?php } ?>
				</main>
			</div>
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

		if ( ! PGR_Module_Registry::exists( $module_id ) ) {
			$this->redirect( array( 'pgr_module_notice' => 'invalid' ) );
		}

		if ( ! $enable ) {
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
			if ( PGR_Module_Usage::UNKNOWN === $usage['status'] && ! $confirm ) {
				$this->redirect(
					array(
						'pgr_module_notice'  => 'confirm',
						'pgr_module'         => $module_id,
						'pgr_confirm_module' => $module_id,
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
	private function module_card( $module, $lang, $confirm ) {
		$id        = $module['id'];
		$enabled   = PGR_Module_Registry::is_enabled( $id );
		$primary   = 'fa' === $lang ? 'fa' : 'en';
		$secondary = 'fa' === $primary ? 'en' : 'fa';
		?>
		<article class="pgr-card pgr-module-card">
			<div class="pgr-module-card__heading">
				<div>
					<h2 dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'label_' . $primary ] ); ?></h2>
					<p class="pgr-module-card__secondary" dir="<?php echo 'fa' === $secondary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'label_' . $secondary ] ); ?></p>
				</div>
				<span class="pgr-badge" data-state="<?php echo $enabled ? 'AVAILABLE' : 'DISABLED'; ?>"><?php echo esc_html( $enabled ? __( 'Enabled', 'persian-gravityforms' ) : __( 'Disabled', 'persian-gravityforms' ) ); ?></span>
			</div>
			<p dir="<?php echo 'fa' === $primary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'description_' . $primary ] ); ?></p>
			<p class="pgr-module-card__secondary" dir="<?php echo 'fa' === $secondary ? 'rtl' : 'ltr'; ?>"><?php echo esc_html( $module[ 'description_' . $secondary ] ); ?></p>
			<code dir="ltr"><?php echo esc_html( $id ); ?></code>
			<?php if ( $enabled && $confirm === $id ) { ?>
				<div class="pgr-warning-note" role="alert"><?php esc_html_e( 'Current use cannot be determined confidently. Disabling may affect existing forms. Confirm only if you accept that risk.', 'persian-gravityforms' ); ?></div>
			<?php } ?>
			<div class="pgr-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pgr_module_toggle">
					<input type="hidden" name="module_id" value="<?php echo esc_attr( $id ); ?>">
					<input type="hidden" name="enable" value="<?php echo $enabled ? '0' : '1'; ?>">
					<?php if ( $enabled && $confirm === $id ) { ?>
						<input type="hidden" name="confirm" value="1">
					<?php } ?>
					<?php wp_nonce_field( 'pgr_module_toggle' ); ?>
					<button type="submit" class="button"><?php echo esc_html( $enabled ? __( 'Disable', 'persian-gravityforms' ) : __( 'Enable', 'persian-gravityforms' ) ); ?></button>
				</form>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::HELP_SLUG . '&lang=' . $lang . '#' . $module['help_topic'] ) ); ?>"><?php esc_html_e( 'Help', 'persian-gravityforms' ); ?></a>
			</div>
		</article>
		<?php
	}

	private function render_notice() {
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
		} elseif ( 'confirm' === $notice ) {
			$class   = 'notice-warning';
			$message = __( 'Current use could not be determined confidently. Review the module card and confirm again to disable it.', 'persian-gravityforms' );
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
		return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private function redirect( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . PGR_Admin::MENU_SLUG ) ) );
		exit;
	}
}
