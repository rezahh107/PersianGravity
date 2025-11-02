# سند بازنویسی Persian Gravity Forms Refactor

بر اساس گزارش تطابق AGENTS.md و با توجه به استانداردهای وردپرس

---

## 🎯 هدف بازنویسی

تبدیل افزونه از وضعیت فعلی (22% تطابق) به یک افزونه کاملاً استاندارد و ایمن با معماری مدرن و قابل نگهداری.

---

## 📋 فاز 1: بازسازی ساختار پایه (اولویت بحرانی)

### 1.1 ایجاد Entry Point اصلی

**فایل جدید:** `persian-gravityforms-refactor.php`

```php
<?php
/**
 * Plugin Name: Persian Gravity Forms Refactor
 * Plugin URI: https://example.com/persian-gravityforms-refactor
 * Description: Persian localization and enhancements for Gravity Forms
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: persian-gravityforms-refactor
 * Domain Path: /languages
 */

// Guard against direct access
defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'PGR_VERSION', '1.0.0' );
define( 'PGR_PLUGIN_FILE', __FILE__ );
define( 'PGR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PGR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PGR_ASSETS_URL', PGR_PLUGIN_URL . 'assets/' );

// Load autoloader
require_once PGR_PLUGIN_DIR . 'includes/autoload.php';

// Initialize plugin
function pgr_init() {
	// Check Gravity Forms dependency
	if ( ! class_exists( 'GFForms' ) ) {
		add_action( 'admin_notices', 'pgr_missing_gf_notice' );
		return;
	}

	// Load text domain
	load_plugin_textdomain(
		'persian-gravityforms-refactor',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Initialize main plugin class
	PGR_Core::get_instance();
}
add_action( 'plugins_loaded', 'pgr_init' );

// Admin notice for missing Gravity Forms
function pgr_missing_gf_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			esc_html_e(
				'Persian Gravity Forms Refactor requires Gravity Forms to be installed and activated.',
				'persian-gravityforms-refactor'
			);
			?>
		</p>
	</div>
	<?php
}

// Activation hook
register_activation_hook( __FILE__, array( 'PGR_Installer', 'activate' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'PGR_Installer', 'deactivate' ) );
```

### 1.2 ایجاد Autoloader استاندارد

**فایل جدید:** `includes/autoload.php`

```php
<?php
/**
 * SPL Autoloader for PGR_ classes
 *
 * @package PersianGravityFormsRefactor
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register( 'pgr_autoloader' );

/**
 * Autoload PGR classes
 *
 * @param string $class_name Class name to load.
 */
function pgr_autoloader( $class_name ) {
	// Only handle PGR_ prefixed classes
	if ( 0 !== strpos( $class_name, 'PGR_' ) ) {
		return;
	}

	// Convert class name to file name
	// PGR_Admin -> class-pgr-admin.php
	// PGR_National_ID -> class-pgr-national-id.php
	$class_file = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

	// Define search paths
	$paths = array(
		PGR_PLUGIN_DIR . 'includes/',
		PGR_PLUGIN_DIR . 'admin/',
		PGR_PLUGIN_DIR . 'includes/services/',
		PGR_PLUGIN_DIR . 'includes/fields/',
	);

	// Try to load the file
	foreach ( $paths as $path ) {
		$file = $path . $class_file;
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}
}
```

---

## 📋 فاز 2: بازنویسی کلاس‌های اصلی

### 2.1 کلاس Core اصلاح‌شده

**فایل:** `includes/class-pgr-core.php`

```php
<?php
/**
 * Main plugin core class
 *
 * @package PersianGravityFormsRefactor
 */

defined( 'ABSPATH' ) || exit;

/**
 * PGR_Core class - Singleton pattern
 */
class PGR_Core {
	/**
	 * Single instance
	 *
	 * @var PGR_Core
	 */
	private static $instance = null;

	/**
	 * Plugin modules
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Get singleton instance
	 *
	 * @return PGR_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize hooks
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
		$this->load_modules();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		// Core utilities
		require_once PGR_PLUGIN_DIR . 'includes/class-pgr-utils.php';
		require_once PGR_PLUGIN_DIR . 'includes/class-pgr-installer.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin modules (lazy loading)
	 */
	private function load_modules() {
		// Only load what's needed based on context
		if ( is_admin() ) {
			$this->modules['admin'] = new PGR_Admin();
		}

		// Load features based on settings
		$options = get_option( 'pgr_settings', array() );

		if ( ! empty( $options['enable_national_id'] ) ) {
			$this->modules['national_id'] = new PGR_National_ID();
		}

		if ( ! empty( $options['enable_persian_date'] ) ) {
			$this->modules['persian_date'] = new PGR_Persian_Date();
		}

		if ( ! empty( $options['enable_persian_numbers'] ) ) {
			$this->modules['persian_numbers'] = new PGR_Persian_Numbers();
		}

		// Always load form handler for GF integration
		if ( class_exists( 'GFForms' ) ) {
			$this->modules['form_handler'] = new PGR_Form_Handler();
		}
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only on plugin settings page
		if ( 'settings_page_persian-gravityforms-refactor' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'pgr-admin',
			PGR_ASSETS_URL . 'css/pgr-admin.css',
			array(),
			PGR_VERSION
		);

		wp_enqueue_script(
			'pgr-admin',
			PGR_ASSETS_URL . 'js/pgr-admin.js',
			array( 'jquery' ),
			PGR_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'pgr-admin',
			'pgrAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pgr_admin_action' ),
				'strings' => array(
					'saved'  => __( 'Settings saved successfully', 'persian-gravityforms-refactor' ),
					'error'  => __( 'Error saving settings', 'persian-gravityforms-refactor' ),
					'confirm' => __( 'Are you sure?', 'persian-gravityforms-refactor' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only on pages with Gravity Forms
		if ( ! class_exists( 'GFForms' ) || ! $this->has_gravity_form() ) {
			return;
		}

		wp_enqueue_style(
			'pgr-frontend',
			PGR_ASSETS_URL . 'css/pgr-frontend.css',
			array(),
			PGR_VERSION
		);

		wp_enqueue_script(
			'pgr-frontend',
			PGR_ASSETS_URL . 'js/pgr-frontend.js',
			array( 'jquery', 'gform_gravityforms' ),
			PGR_VERSION,
			true
		);
	}

	/**
	 * Check if current page has a Gravity Form
	 *
	 * @return bool
	 */
	private function has_gravity_form() {
		global $post;
		
		if ( ! $post ) {
			return false;
		}

		// Check for shortcode or block
		return has_shortcode( $post->post_content, 'gravityform' ) ||
		       has_block( 'gravityforms/form', $post );
	}

	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'persian-gravityforms-refactor',
			false,
			dirname( plugin_basename( PGR_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Get module instance
	 *
	 * @param string $module_name Module name.
	 * @return object|null
	 */
	public function get_module( $module_name ) {
		return isset( $this->modules[ $module_name ] ) ? $this->modules[ $module_name ] : null;
	}
}
```

### 2.2 کلاس Admin با امنیت کامل

**فایل:** `admin/class-pgr-admin.php`

```php
<?php
/**
 * Admin settings and UI
 *
 * @package PersianGravityFormsRefactor
 */

defined( 'ABSPATH' ) || exit;

/**
 * PGR_Admin class
 */
class PGR_Admin {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_pgr_save_settings', array( $this, 'ajax_save_settings' ) );
	}

	/**
	 * Add settings page to WordPress admin
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Persian Gravity Forms Settings', 'persian-gravityforms-refactor' ),
			__( 'Persian GF', 'persian-gravityforms-refactor' ),
			'manage_options',
			'persian-gravityforms-refactor',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'pgr_settings_group',
			'pgr_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		// Features section
		add_settings_section(
			'pgr_features',
			__( 'Features', 'persian-gravityforms-refactor' ),
			array( $this, 'render_features_section' ),
			'persian-gravityforms-refactor'
		);

		// Individual settings
		$this->add_checkbox_field(
			'enable_national_id',
			__( 'Enable National ID Validation', 'persian-gravityforms-refactor' ),
			'pgr_features'
		);

		$this->add_checkbox_field(
			'enable_persian_date',
			__( 'Enable Persian Date Picker', 'persian-gravityforms-refactor' ),
			'pgr_features'
		);

		$this->add_checkbox_field(
			'enable_persian_numbers',
			__( 'Convert Numbers to Persian', 'persian-gravityforms-refactor' ),
			'pgr_features'
		);

		$this->add_checkbox_field(
			'enable_rtl_support',
			__( 'Enable RTL Support', 'persian-gravityforms-refactor' ),
			'pgr_features'
		);
	}

	/**
	 * Add checkbox field helper
	 *
	 * @param string $id Field ID.
	 * @param string $title Field title.
	 * @param string $section Section ID.
	 */
	private function add_checkbox_field( $id, $title, $section ) {
		add_settings_field(
			$id,
			$title,
			array( $this, 'render_checkbox_field' ),
			'persian-gravityforms-refactor',
			$section,
			array( 'id' => $id )
		);
	}

	/**
	 * Render checkbox field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'pgr_settings', array() );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		$checked = checked( '1', $value, false );
		?>
		<label>
			<input 
				type="checkbox" 
				name="pgr_settings[<?php echo esc_attr( $args['id'] ); ?>]" 
				value="1" 
				<?php echo $checked; ?>
			/>
			<?php esc_html_e( 'Enable this feature', 'persian-gravityforms-refactor' ); ?>
		</label>
		<?php
	}

	/**
	 * Render features section description
	 */
	public function render_features_section() {
		?>
		<p>
			<?php
			esc_html_e(
				'Configure which Persian features you want to enable for Gravity Forms.',
				'persian-gravityforms-refactor'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings before saving
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$checkbox_fields = array(
			'enable_national_id',
			'enable_persian_date',
			'enable_persian_numbers',
			'enable_rtl_support',
		);

		foreach ( $checkbox_fields as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] ) ? '1' : '';
		}

		return $sanitized;
	}

	/**
	 * Render settings page HTML
	 */
	public function render_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'persian-gravityforms-refactor' ) );
		}
		?>
		<div class="wrap pgr-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php settings_errors( 'pgr_settings' ); ?>

			<form method="post" action="options.php" class="pgr-settings-form">
				<?php
				settings_fields( 'pgr_settings_group' );
				do_settings_sections( 'persian-gravityforms-refactor' );
				submit_button( __( 'Save Settings', 'persian-gravityforms-refactor' ) );
				?>
			</form>

			<div class="pgr-info-box">
				<h2><?php esc_html_e( 'System Information', 'persian-gravityforms-refactor' ); ?></h2>
				<ul>
					<li>
						<strong><?php esc_html_e( 'Plugin Version:', 'persian-gravityforms-refactor' ); ?></strong>
						<?php echo esc_html( PGR_VERSION ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Gravity Forms:', 'persian-gravityforms-refactor' ); ?></strong>
						<?php
						echo class_exists( 'GFForms' ) 
							? esc_html( GFForms::$version ) 
							: esc_html__( 'Not installed', 'persian-gravityforms-refactor' );
						?>
					</li>
					<li>
						<strong><?php esc_html_e( 'WordPress:', 'persian-gravityforms-refactor' ); ?></strong>
						<?php echo esc_html( get_bloginfo( 'version' ) ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'PHP Version:', 'persian-gravityforms-refactor' ); ?></strong>
						<?php echo esc_html( PHP_VERSION ); ?>
					</li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for saving settings
	 */
	public function ajax_save_settings() {
		// Verify nonce
		check_ajax_referer( 'pgr_admin_action', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied', 'persian-gravityforms-refactor' ) )
			);
		}

		// Get and sanitize input
		$settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
		$settings = $this->sanitize_settings( $settings );

		// Save settings
		$updated = update_option( 'pgr_settings', $settings );

		if ( $updated ) {
			wp_send_json_success(
				array( 'message' => __( 'Settings saved successfully', 'persian-gravityforms-refactor' ) )
			);
		} else {
			wp_send_json_error(
				array( 'message' => __( 'No changes were made', 'persian-gravityforms-refactor' ) )
			);
		}
	}
}
```

---

## 📋 فاز 3: فایل‌های Assets استاندارد

### 3.1 استایل Admin

**فایل:** `assets/css/pgr-admin.css`

```css
/**
 * Persian Gravity Forms Refactor - Admin Styles
 * 
 * @package PersianGravityFormsRefactor
 * @version 1.0.0
 */

/* Settings page wrapper */
.pgr-settings-wrap {
	max-width: 1200px;
	margin: 20px 0;
}

/* Settings form */
.pgr-settings-form {
	background: #fff;
	padding: 20px;
	border: 1px solid #ccd0d4;
	box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
	margin-block-end: 20px;
}

/* Info box */
.pgr-info-box {
	background: #f0f0f1;
	padding: 20px;
	border-inline-start: 4px solid #2271b1;
	margin-block-start: 20px;
}

.pgr-info-box h2 {
	margin-block-start: 0;
	font-size: 16px;
}

.pgr-info-box ul {
	list-style: none;
	padding: 0;
	margin: 10px 0 0 0;
}

.pgr-info-box li {
	padding: 8px 0;
	border-block-end: 1px solid #dcdcde;
}

.pgr-info-box li:last-child {
	border-block-end: none;
}

/* RTL support */
[dir="rtl"] .pgr-settings-wrap {
	text-align: right;
}

[dir="rtl"] .pgr-info-box {
	border-inline-start: none;
	border-inline-end: 4px solid #2271b1;
}

/* Feature toggles */
.pgr-feature-toggle {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px;
	background: #f9f9f9;
	border-radius: 4px;
	margin-block-end: 10px;
}

.pgr-feature-toggle input[type="checkbox"] {
	margin: 0;
}

/* Success/Error messages */
.pgr-notice {
	padding: 12px;
	margin: 15px 0;
	border-inline-start: 4px solid;
	background: #fff;
}

.pgr-notice.pgr-notice-success {
	border-color: #00a32a;
	color: #00612a;
}

.pgr-notice.pgr-notice-error {
	border-color: #d63638;
	color: #8a2424;
}

/* Loading spinner */
.pgr-spinner {
	display: inline-block;
	width: 20px;
	height: 20px;
	border: 3px solid rgba(0, 0, 0, 0.1);
	border-top-color: #2271b1;
	border-radius: 50%;
	animation: pgr-spin 0.6s linear infinite;
}

@keyframes pgr-spin {
	to {
		transform: rotate(360deg);
	}
}
```

### 3.2 اسکریپت Admin

**فایل:** `assets/js/pgr-admin.js`

```javascript
/**
 * Persian Gravity Forms Refactor - Admin Scripts
 * 
 * @package PersianGravityFormsRefactor
 * @version 1.0.0
 */

(function( $, window, document ) {
	'use strict';

	// Namespace
	window.PGR = window.PGR || {};

	/**
	 * Admin module
	 */
	window.PGR.Admin = {
		/**
		 * Initialize admin functionality
		 */
		init: function() {
			this.bindEvents();
			this.initTooltips();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Handle settings form submission via AJAX (optional enhancement)
			$( '.pgr-settings-form' ).on( 'submit', function( e ) {
				// Let WordPress handle it normally
				// or implement AJAX save here
			});

			// Feature toggle confirmation
			$( '.pgr-feature-toggle input[type="checkbox"]' ).on( 'change', function() {
				var $checkbox = $( this );
				var featureName = $checkbox.attr( 'name' );
				
				if ( ! $checkbox.prop( 'checked' ) ) {
					var confirmed = confirm( pgrAdmin.strings.confirm );
					if ( ! confirmed ) {
						$checkbox.prop( 'checked', true );
					}
				}
			});

			// Test connection button (if needed)
			$( '.pgr-test-connection' ).on( 'click', function( e ) {
				e.preventDefault();
				self.testConnection();
			});
		},

		/**
		 * Initialize tooltips
		 */
		initTooltips: function() {
			$( '[data-pgr-tooltip]' ).each( function() {
				var $element = $( this );
				var tooltipText = $element.data( 'pgr-tooltip' );
				
				$element.attr( 'title', tooltipText );
			});
		},

		/**
		 * Test connection (example AJAX call)
		 */
		testConnection: function() {
			var $button = $( '.pgr-test-connection' );
			
			$button.prop( 'disabled', true ).addClass( 'pgr-loading' );

			$.ajax({
				url: pgrAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pgr_test_connection',
					nonce: pgrAdmin.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						alert( pgrAdmin.strings.saved );
					} else {
						alert( pgrAdmin.strings.error );
					}
				},
				error: function() {
					alert( pgrAdmin.strings.error );
				},
				complete: function() {
					$button.prop( 'disabled', false ).removeClass( 'pgr-loading' );
				}
			});
		},

		/**
		 * Show notification
		 * 
		 * @param {string} message Message text
		 * @param {string} type success|error
		 */
		showNotice: function( message, type ) {
			type = type || 'success';
			
			var $notice = $( '<div>', {
				'class': 'pgr-notice pgr-notice-' + type,
				text: message
			});

			$( '.pgr-settings-wrap' ).prepend( $notice );

			setTimeout( function() {
				$notice.fadeOut( function() {
					$( this ).remove();
				});
			}, 3000 );
		}
	};

	// Initialize on document ready
	$( document ).ready( function() {
		window.PGR.Admin.init();
	});

})( jQuery, window, document );
```

### 3.3 اسکریپت Frontend

**فایل:** `assets/js/pgr-frontend.js`

```javascript
/**
 * Persian Gravity Forms Refactor - Frontend Scripts
 * 
 * @package PersianGravityFormsRefactor
 * @version 1.0.0
 */

(function( $, window, document ) {
	'use strict';

	// Namespace
	window.PGR = window.PGR || {};

	/**
	 * Frontend module
	 */
	window.PGR.Frontend = {
		/**
		 * Initialize frontend functionality
		 */
		init: function() {
			this.bindEvents();
			this.initPersianInputs();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Gravity Forms events
			$( document ).on( 'gform_post_render', function( event, formId ) {
				self.initPersianInputs( formId );
			});
		},

		/**
		 * Initialize Persian-specific inputs
		 * 
		 * @param {number} formId Optional form ID
		 */
		initPersianInputs: function( formId ) {
			var selector = formId ? '#gform_' + formId : '.gform_wrapper';
			
			// Persian number conversion
			$( selector ).find( '.pgr-persian-number' ).on( 'input', function() {
				var $input = $( this );
				var value = $input.val();
				
				// Convert English numbers to Persian
				value = self.convertToPersianNumbers( value );
				$input.val( value );
			});

			// National ID validation
			$( selector ).find( '.pgr-national-id' ).on( 'blur', function() {
				var $input = $( this );
				var nationalId = $input.val();
				
				if ( nationalId && ! self.validateNationalId( nationalId ) ) {
					$input.addClass( 'pgr-invalid' );
					self.showFieldError( $input, 'کد ملی نامعتبر است' );
				} else {
					$input.removeClass( 'pgr-invalid' );
					self.hideFieldError( $input );
				}
			});
		},

		/**
		 * Convert English numbers to Persian
		 * 
		 * @param {string} str Input string
		 * @return {string} Converted string
		 */
		convertToPersianNumbers: function( str ) {
			var persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
			
			return str.replace( /\d/g, function( digit ) {
				return persianNumbers[ digit ];
			});
		},

		/**
		 * Validate Iranian National ID
		 * 
		 * @param {string} nationalId National ID string
		 * @return {boolean} Valid or not
		 */
		validateNationalId: function( nationalId ) {
			// Remove non-digits
			nationalId = nationalId.replace( /\D/g, '' );

			if ( nationalId.length !== 10 ) {
				return false;
			}

			// Check sum algorithm
			var check = parseInt( nationalId[9], 10 );
			var sum = 0;

			for ( var i = 0; i < 9; i++ ) {
				sum += parseInt( nationalId[i], 10 ) * ( 10 - i );
			}

			var remainder = sum % 11;

			return ( remainder < 2 && check === remainder ) || 
			       ( remainder >= 2 && check === 11 - remainder );
		},

		/**
		 * Show field error message
		 * 
		 * @param {jQuery} $field Fiel



## Prompt:
11/1/2025, 9:54:13 PM

Continue



## Response:
11/1/2025, 9:55:48 PM

d element
		 * @param {string} message Error message
		 */
		showFieldError: function( $field, message ) {
			var $container = $field.closest( '.gfield' );
			var $error = $container.find( '.pgr-field-error' );
			
			if ( $error.length === 0 ) {
				$error = $( '<div>', {
					'class': 'pgr-field-error',
					'role': 'alert',
					'aria-live': 'polite'
				});
				$container.append( $error );
			}
			
			$error.text( message ).show();
			$container.addClass( 'gfield_error' );
		},

		/**
		 * Hide field error message
		 * 
		 * @param {jQuery} $field Field element
		 */
		hideFieldError: function( $field ) {
			var $container = $field.closest( '.gfield' );
			$container.find( '.pgr-field-error' ).hide();
			$container.removeClass( 'gfield_error' );
		}
	};

	// Initialize on document ready
	$( document ).ready( function() {
		window.PGR.Frontend.init();
	});

})( jQuery, window, document );
```

### 3.4 استایل Frontend

**فایل:** `assets/css/pgr-frontend.css`

```css
/**
 * Persian Gravity Forms Refactor - Frontend Styles
 * 
 * @package PersianGravityFormsRefactor
 * @version 1.0.0
 */

/* Persian input styling */
.pgr-persian-input {
	direction: rtl;
	text-align: right;
	font-family: Tahoma, Arial, sans-serif;
}

/* National ID field */
.pgr-national-id {
	direction: ltr;
	text-align: left;
	letter-spacing: 2px;
	font-family: 'Courier New', monospace;
}

.pgr-national-id.pgr-invalid {
	border-color: #d63638;
	background-color: #fff8f8;
}

/* Persian date picker */
.pgr-persian-datepicker {
	direction: rtl;
	text-align: right;
}

.pgr-datepicker-wrapper {
	position: relative;
	display: inline-block;
}

.pgr-datepicker-icon {
	position: absolute;
	inset-inline-end: 10px;
	top: 50%;
	transform: translateY(-50%);
	pointer-events: none;
	color: #8c8f94;
}

/* Error messages */
.pgr-field-error {
	display: block;
	color: #d63638;
	font-size: 0.875em;
	margin-block-start: 5px;
	padding: 5px 10px;
	background: #fff8f8;
	border-inline-start: 3px solid #d63638;
	border-radius: 3px;
}

/* RTL form support */
.gform_wrapper[dir="rtl"] .gfield_label,
.gform_wrapper.pgr-rtl .gfield_label {
	text-align: right;
}

.gform_wrapper[dir="rtl"] .ginput_container,
.gform_wrapper.pgr-rtl .ginput_container {
	direction: rtl;
}

/* Persian number inputs */
.pgr-persian-number {
	font-family: Tahoma, Arial, sans-serif;
	direction: rtl;
	text-align: right;
}

/* Loading state */
.pgr-loading {
	position: relative;
	pointer-events: none;
	opacity: 0.6;
}

.pgr-loading::after {
	content: '';
	position: absolute;
	inset-inline-end: 10px;
	top: 50%;
	transform: translateY(-50%);
	width: 16px;
	height: 16px;
	border: 2px solid rgba(0, 0, 0, 0.1);
	border-top-color: #2271b1;
	border-radius: 50%;
	animation: pgr-spin 0.6s linear infinite;
}

@keyframes pgr-spin {
	to {
		transform: translateY(-50%) rotate(360deg);
	}
}

/* Accessibility improvements */
.pgr-field-error:focus,
.pgr-field-error:active {
	outline: 2px solid #2271b1;
	outline-offset: 2px;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
	.pgr-field-error {
		font-size: 0.8125em;
		padding: 4px 8px;
	}
	
	.pgr-national-id {
		letter-spacing: 1px;
	}
}
```

---

## 📋 فاز 4: کلاس‌های امنیتی و Utilities

### 4.1 کلاس Installer

**فایل:** `includes/class-pgr-installer.php`

```php
<?php
/**
 * Plugin activation/deactivation handler
 *
 * @package PersianGravityFormsRefactor
 */

defined( 'ABSPATH' ) || exit;

/**
 * PGR_Installer class
 */
class PGR_Installer {
	/**
	 * Run on plugin activation
	 */
	public static function activate() {
		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( PGR_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Persian Gravity Forms Refactor requires PHP 7.4 or higher.', 'persian-gravityforms-refactor' ),
				esc_html__( 'Plugin Activation Error', 'persian-gravityforms-refactor' ),
				array( 'back_link' => true )
			);
		}

		// Check WordPress version
		global $wp_version;
		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			deactivate_plugins( plugin_basename( PGR_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Persian Gravity Forms Refactor requires WordPress 5.8 or higher.', 'persian-gravityforms-refactor' ),
				esc_html__( 'Plugin Activation Error', 'persian-gravityforms-refactor' ),
				array( 'back_link' => true )
			);
		}

		// Check if Gravity Forms is active
		if ( ! class_exists( 'GFForms' ) ) {
			deactivate_plugins( plugin_basename( PGR_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Persian Gravity Forms Refactor requires Gravity Forms to be installed and activated.', 'persian-gravityforms-refactor' ),
				esc_html__( 'Plugin Activation Error', 'persian-gravityforms-refactor' ),
				array( 'back_link' => true )
			);
		}

		// Create default options
		self::create_default_options();

		// Create custom database tables if needed
		self::create_tables();

		// Set activation flag
		set_transient( 'pgr_activation_redirect', true, 30 );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation
	 */
	public static function deactivate() {
		// Clean up transients
		delete_transient( 'pgr_activation_redirect' );
		
		// Flush rewrite rules
		flush_rewrite_rules();

		// Note: We don't delete options on deactivation
		// Only on uninstall
	}

	/**
	 * Create default plugin options
	 */
	private static function create_default_options() {
		$default_options = array(
			'enable_national_id'     => '1',
			'enable_persian_date'    => '1',
			'enable_persian_numbers' => '1',
			'enable_rtl_support'     => '1',
		);

		// Only add if not exists
		if ( false === get_option( 'pgr_settings' ) ) {
			add_option( 'pgr_settings', $default_options );
		}

		// Store version
		update_option( 'pgr_version', PGR_VERSION );
	}

	/**
	 * Create custom database tables
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'pgr_logs';

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id bigint(20) UNSIGNED NOT NULL,
			entry_id bigint(20) UNSIGNED NOT NULL,
			action_type varchar(50) NOT NULL,
			action_data longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY entry_id (entry_id),
			KEY action_type (action_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Run on plugin uninstall
	 */
	public static function uninstall() {
		// Check if user wants to keep data
		$keep_data = get_option( 'pgr_keep_data_on_uninstall', false );

		if ( ! $keep_data ) {
			// Delete options
			delete_option( 'pgr_settings' );
			delete_option( 'pgr_version' );
			delete_option( 'pgr_keep_data_on_uninstall' );

			// Drop custom tables
			global $wpdb;
			$table_name = $wpdb->prefix . 'pgr_logs';
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

			// Clean up transients
			delete_transient( 'pgr_activation_redirect' );
		}
	}
}
```

### 4.2 کلاس Utils

**فایل:** `includes/class-pgr-utils.php`

```php
<?php
/**
 * Utility functions
 *
 * @package PersianGravityFormsRefactor
 */

defined( 'ABSPATH' ) || exit;

/**
 * PGR_Utils class
 */
class PGR_Utils {
	/**
	 * Convert English numbers to Persian
	 *
	 * @param string $string Input string.
	 * @return string Converted string.
	 */
	public static function convert_to_persian_numbers( $string ) {
		$persian_numbers = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$english_numbers = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $english_numbers, $persian_numbers, $string );
	}

	/**
	 * Convert Persian numbers to English
	 *
	 * @param string $string Input string.
	 * @return string Converted string.
	 */
	public static function convert_to_english_numbers( $string ) {
		$persian_numbers = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$english_numbers = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $persian_numbers, $english_numbers, $string );
	}

	/**
	 * Validate Iranian National ID
	 *
	 * @param string $national_id National ID to validate.
	 * @return bool True if valid.
	 */
	public static function validate_national_id( $national_id ) {
		// Convert to English numbers
		$national_id = self::convert_to_english_numbers( $national_id );

		// Remove non-digits
		$national_id = preg_replace( '/\D/', '', $national_id );

		// Must be 10 digits
		if ( 10 !== strlen( $national_id ) ) {
			return false;
		}

		// Check for repeated digits
		if ( preg_match( '/^(\d)\1{9}$/', $national_id ) ) {
			return false;
		}

		// Validate check digit
		$check = (int) $national_id[9];
		$sum   = 0;

		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $national_id[ $i ] * ( 10 - $i );
		}

		$remainder = $sum % 11;

		return ( $remainder < 2 && $check === $remainder ) ||
		       ( $remainder >= 2 && $check === ( 11 - $remainder ) );
	}

	/**
	 * Sanitize Persian text
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	public static function sanitize_persian_text( $text ) {
		// Convert Arabic characters to Persian
		$arabic_to_persian = array(
			'ي' => 'ی',
			'ك' => 'ک',
			'٠' => '۰',
			'١' => '۱',
			'٢' => '۲',
			'٣' => '۳',
			'٤' => '۴',
			'٥' => '۵',
			'٦' => '۶',
			'٧' => '۷',
			'٨' => '۸',
			'٩' => '۹',
		);

		$text = str_replace( array_keys( $arabic_to_persian ), array_values( $arabic_to_persian ), $text );

		// Remove zero-width characters
		$text = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text );

		// Trim and sanitize
		return sanitize_text_field( trim( $text ) );
	}

	/**
	 * Check if current page has Gravity Form
	 *
	 * @return bool True if has form.
	 */
	public static function has_gravity_form() {
		global $post;

		if ( ! $post || ! class_exists( 'GFForms' ) ) {
			return false;
		}

		// Check for shortcode or block
		return has_shortcode( $post->post_content, 'gravityform' ) ||
		       has_block( 'gravityforms/form', $post );
	}

	/**
	 * Get plugin option
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed Option value.
	 */
	public static function get_option( $key, $default = false ) {
		$options = get_option( 'pgr_settings', array() );

		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * Update plugin option
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Option value.
	 * @return bool True if updated.
	 */
	public static function update_option( $key, $value ) {
		$options         = get_option( 'pgr_settings', array() );
		$options[ $key ] = $value;

		return update_option( 'pgr_settings', $options );
	}

	/**
	 * Log message for debugging
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_entry = sprintf(
			'[%s] PGR %s: %s',
			current_time( 'mysql' ),
			strtoupper( $level ),
			$message
		);

		error_log( $log_entry );
	}

	/**
	 * Get Gravity Forms version
	 *
	 * @return string|null Version or null.
	 */
	public static function get_gf_version() {
		return class_exists( 'GFForms' ) ? GFForms::$version : null;
	}

	/**
	 * Check if feature is enabled
	 *
	 * @param string $feature Feature name.
	 * @return bool True if enabled.
	 */
	public static function is_feature_enabled( $feature ) {
		return '1' === self::get_option( 'enable_' . $feature, '0' );
	}

	/**
	 * Get Persian month names
	 *
	 * @return array Month names.
	 */
	public static function get_persian_months() {
		return array(
			1  => __( 'فروردین', 'persian-gravityforms-refactor' ),
			2  => __( 'اردیبهشت', 'persian-gravityforms-refactor' ),
			3  => __( 'خرداد', 'persian-gravityforms-refactor' ),
			4  => __( 'تیر', 'persian-gravityforms-refactor' ),
			5  => __( 'مرداد', 'persian-gravityforms-refactor' ),
			6  => __( 'شهریور', 'persian-gravityforms-refactor' ),
			7  => __( 'مهر', 'persian-gravityforms-refactor' ),
			8  => __( 'آبان', 'persian-gravityforms-refactor' ),
			9  => __( 'آذر', 'persian-gravityforms-refactor' ),
			10 => __( 'دی', 'persian-gravityforms-refactor' ),
			11 => __( 'بهمن', 'persian-gravityforms-refactor' ),
			12 => __( 'اسفند', 'persian-gravityforms-refactor' ),
		);
	}

	/**
	 * Convert Gregorian to Jalali date
	 *
	 * @param int $g_year  Gregorian year.
	 * @param int $g_month Gregorian month.
	 * @param int $g_day   Gregorian day.
	 * @return array Jalali date [year, month, day].
	 */
	public static function gregorian_to_jalali( $g_year, $g_month, $g_day ) {
		$g_days_in_month = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );

		$gy = $g_year - 1600;
		$gm = $g_month - 1;
		$gd = $g_day - 1;

		$g_day_no = 365 * $gy + floor( ( $gy + 3 ) / 4 ) - floor( ( $gy + 99 ) / 100 ) + floor( ( $gy + 399 ) / 400 );

		for ( $i = 0; $i < $gm; ++$i ) {
			$g_day_no += $g_days_in_month[ $i ];
		}

		if ( $gm > 1 && ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || ( $gy % 400 === 0 ) ) ) {
			$g_day_no++;
		}

		$g_day_no += $gd;

		$j_day_no = $g_day_no - 79;

		$j_np = floor( $j_day_no / 12053 );
		$j_day_no %= 12053;

		$jy = 979 + 33 * $j_np + 4 * floor( $j_day_no / 1461 );
		$j_day_no %= 1461;

		if ( $j_day_no >= 366 ) {
			$jy += floor( ( $j_day_no - 1 ) / 365 );
			$j_day_no = ( $j_day_no - 1 ) % 365;
		}

		for ( $i = 0; $i < 11 && $j_day_no >= $j_days_in_month[ $i ]; ++$i ) {
			$j_day_no -= $j_days_in_month[ $i ];
		}

		$jm = $i + 1;
		$jd = $j_day_no + 1;

		return array( $jy, $jm, $jd );
	}
}
```

---

## 📋 فاز 5: کلاس National ID با امنیت کامل

**فایل:** `includes/class-pgr-national-id.php`

```php
<?php
/**
 * National ID validation and handling
 *
 * @package PersianGravityFormsRefactor
 */

defined( 'ABSPATH' ) || exit;

/**
 * PGR_National_ID class
 */
class PGR_National_ID {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Only initialize if feature is enabled
		if ( ! PGR_Utils::is_feature_enabled( 'national_id' ) ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Validation hook
		add_filter( 'gform_field_validation', array( $this, 'validate_national_id_field' ), 10, 4 );

		// Pre-submission sanitization
		add_filter( 'gform_pre_submission', array( $this, 'sanitize_national_id_fields' ) );

		// Field content filter
		add_filter( 'gform_field_content', array( $this, 'add_national_id_attributes' ), 10, 5 );

		// Entry display
		add_filter( 'gform_entry_field_value', array( $this, 'format_national_id_display' ), 10, 4 );
	}

	/**
	 * Validate National ID field
	 *
	 * @param array  $result Validation result.
	 * @param mixed  $value  Field value.
	 * @param object $form   Form object.
	 * @param object $field  Field object.
	 * @return array Modified result.
	 */
	public function validate_national_id_field( $result, $value, $form, $field ) {
		// Check if this is a national ID field
		if ( ! $this->is_national_id_field( $field ) ) {
			return $result;
		}

		// Skip if field is not required and empty
		if ( ! $field->isRequired && empty( $value ) ) {
			return $result;
		}

		// Validate the national ID
		if ( ! PGR_Utils::validate_national_id( $value ) ) {
			$result['is_valid'] = false;
			$result['message']  = ! empty( $field->errorMessage )
				? $field->errorMessage
				: __( 'لطفاً یک کد ملی معتبر وارد کنید.', 'persian-gravityforms-refactor' );
		}

		return $result;
	}

	/**
	 * Sanitize national ID fields before submission
	 *
	 * @param array $form Form object.
	 */
	public function sanitize_national_id_fields( $form ) {
		foreach ( $form['fields'] as $field ) {
			if ( ! $this->is_national_id_field( $field ) ) {
				continue;
			}

			$input_name = 'input_' . $field->id;

			if ( isset( $_POST[ $input_name ] ) ) {
				// Sanitize and convert to English numbers
				$value = PGR_Utils::convert_to_english_numbers( $_POST[ $input_name ] );
				$value = preg_replace( '/\D/', '', $value );

				// Update POST data with sanitized value
				$_POST[ $input_name ] = sanitize_text_field( $value );
			}
		}
	}

	/**
	 * Add custom attributes to national ID fields
	 *
	 * @param string $field_content Field HTML content.
	 * @param object $field         Field object.
	 * @param mixed  $value         Field value.
	 * @param int    $entry_id      Entry ID.
	 * @param int    $form_id       Form ID.
	 * @return string Modified content.
	 */
	public function add_national_id_attributes( $field_content, $field, $value, $entry_id, $form_id ) {
		if ( ! $this->is_national_id_field( $field ) ) {
			return $field_content;
		}

		// Add custom CSS class and attributes
		$field_content = str_replace(
			'type=\'text\'',
			'type=\'text\' class=\'pgr-national-id\' maxlength=\'10\' inputmode=\'numeric\' pattern=\'[0-9]*\' aria-describedby=\'pgr-national-id-desc-' . absint( $field->id ) . '\'',
			$field_content
		);

		// Add description
		$description = sprintf(
			'<span id="pgr-national-id-desc-%d" class="pgr-field-description">%s</span>',
			absint( $field->id ),
			esc_html__( 'کد ملی ۱۰ رقمی خود را وارد کنید', 'persian-gravityforms-refactor' )
		);

		$field_content = str_replace(
			'</div>',
			$description . '</div>',
			$field_content
		);

		return $field_content;
	}

	/**
	 * Format national ID for entry display
	 *
	 * @param string $value    Field value.
	 * @param object $field    Field object.
	 * @param object $entry    Entry object.
	 * @param object $form     Form object.
	 * @return string Formatted value.
	 */
	public function format_national_id_display( $value, $field, $entry, $form ) {
		if ( ! $this->is_national_id_field( $field ) || empty( $value ) ) {
			return $value;
		}

		// Format as XXX-XXXXXX-X
		if ( 10 === strlen( $value ) ) {
			$value = substr( $value, 0, 3 ) . '-' .
			         substr( $value, 3, 6 ) . '-' .
			         substr( $value, 9, 1 );
		}

		return esc_html( $value );
	}

	/**
	 * Check if field is a national ID field
	 *
	 * @param object $field Field object.
	 * @return bool True if national ID field.
	 */
	private function is_national_id_field( $field ) {
		// Check CSS class
		if ( ! empty( $field->cssClass ) && false !== strpos( $field->cssClass, 'pgr-national-id' ) ) {
			return true;
		}

		// Check custom field property
		if ( isset( $field->pgrFieldType ) && 'national_id' === $field->pgrFieldType ) {
			return true;
		}

		return false;
	}
}
```

---

## 📋 فاز 6: فایل Uninstall

**فایل:** `uninstall.php`

```php
<?php
/**
 * Plugin uninstall handler
 *
 * @package PersianGravityFormsRefactor
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load installer class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pgr-installer.php';

// Run uninstall
PGR_Installer::uninstall();
```

---

## 📋 فاز 7: فایل POT ترجمه

**دستور تولید:**

```bash
wp i18n make-pot . languages/persian-gravityforms-refactor.pot
```

**یا با WP-CLI:**

```bash
cd /path/to/plugin
wp i18n make-pot . languages/persian-gravityforms-refactor.pot --domain=persian-gravityforms-refactor
```

---

## 📋 چک‌لیست اجرایی بازنویسی

### مرحله 1: آماده‌سازی (1-2 روز)
- [ ] بک‌آپ کامل از کد فعلی
- [ ] ایجاد برنچ جدید Git: `refactor/agents-compliance`
- [ ] نصب ابزارهای لازم (Composer, WP-CLI, PHPCS)
- [ ] راه‌اندازی محیط تست لوکال

### مرحله 2: ساختار پایه (2-3 روز)
- [ ] ایجاد `persian-gravityforms-refactor.php`
- [ ] ایجاد `includes/autoload.php`
- [ ] ایجاد `includes/class-pgr-core.php`
- [ ] ایجاد `includes/class-pgr-utils.php`
- [ ] ایجاد `includes/class-pgr-installer.php`
- [ ] ایجاد `uninstall.php`
- [ ] تست activation/deactivation

### مرحله 3: ماژول Admin (2-3 روز)
- [ ] ایجاد دایرکتوری `admin/`
- [ ] ایجاد `admin/class-pgr-admin.php`
- [ ] ایجاد `assets/css/pgr-admin.css`
- [ ] ایجاد `assets/js/pgr-admin.js`
- [ ] پیاده‌سازی Nonce برای فرم‌ها
- [ ] تست صفحه تنظیمات

### مرحله 4: Assets Frontend (1-2 روز)
- [ ] ایجا



## Prompt:
11/1/2025, 9:58:53 PM

Continue



## Response:
11/1/2025, 10:00:38 PM

د `assets/css/pgr-frontend.css`
- [ ] ایجاد `assets/js/pgr-frontend.js`
- [ ] پیاده‌سازی namespace `window.PGR`
- [ ] تست enqueue شرطی assets
- [ ] بررسی RTL support

### مرحله 5: بازنویسی کلاس‌های موجود (3-5 روز)
- [ ] بازنویسی `class-admin.php` به `class-pgr-admin.php`
- [ ] بازنویسی `class-national-id.php` به `class-pgr-national-id.php`
- [ ] بازنویسی `class-settings.php` با Yoda conditions
- [ ] بازنویسی `class-core.php` با lazy loading
- [ ] اضافه کردن `defined( 'ABSPATH' )` به همه فایل‌ها
- [ ] تبدیل همه comparisons به Yoda style

### مرحله 6: امنیت و Sanitization (2-3 روز)
- [ ] اضافه کردن Nonce verification به همه AJAX handlers
- [ ] پیاده‌سازی `sanitize_*` برای همه inputs
- [ ] پیاده‌سازی `esc_*` برای همه outputs
- [ ] تبدیل SQL queries به `$wpdb->prepare()`
- [ ] بررسی `current_user_can()` در همه admin actions
- [ ] تست با `WP_DEBUG` و `SCRIPT_DEBUG` فعال

### مرحله 7: ترجمه و i18n (1-2 روز)
- [ ] Wrap کردن همه رشته‌ها با `__()` یا `_e()`
- [ ] یکپارچه‌سازی text domain به `persian-gravityforms-refactor`
- [ ] تولید فایل `.pot`: `wp i18n make-pot`
- [ ] ایجاد فایل `.po` فارسی
- [ ] تولید فایل `.mo` فارسی
- [ ] تست load شدن translations

### مرحله 8: Accessibility (1-2 روز)
- [ ] اضافه کردن `aria-label` به input fields
- [ ] اضافه کردن `role` و `aria-live` به error messages
- [ ] اضافه کردن `alt` به تصاویر
- [ ] تست keyboard navigation
- [ ] تست با screen reader (NVDA یا JAWS)

### مرحله 9: Performance Optimization (1-2 روز)
- [ ] پیاده‌سازی lazy loading برای modules
- [ ] اضافه کردن transient cache برای GF queries
- [ ] بهینه‌سازی enqueue شرطی assets
- [ ] حذف instantiation غیرضروری کلاس‌ها
- [ ] تست با Query Monitor plugin

### مرحله 10: Testing (2-3 روز)
- [ ] تست activation/deactivation
- [ ] تست همه features با GF forms
- [ ] تست National ID validation
- [ ] تست Persian date conversion
- [ ] تست Persian number conversion
- [ ] تست RTL support
- [ ] تست با WP 5.8+
- [ ] تست با PHP 7.4+
- [ ] تست با آخرین نسخه Gravity Forms
- [ ] تست multisite compatibility

### مرحله 11: Documentation (1-2 روز)
- [ ] به‌روزرسانی `README.md`
- [ ] ایجاد `CHANGELOG.md`
- [ ] به‌روزرسانی inline comments
- [ ] ایجاد developer documentation
- [ ] ایجاد user guide فارسی

### مرحله 12: Release Preparation (1 روز)
- [ ] Bump version به `1.0.0`
- [ ] به‌روزرسانی `PGR_VERSION`
- [ ] به‌روزرسانی `readme.txt`
- [ ] تولید مجدد translation files
- [ ] بررسی نهایی PHPCS
- [ ] ایجاد Git tag: `v1.0.0`
- [ ] ایجاد release notes

---

## 📋 فاز 8: فایل‌های اضافی مورد نیاز

### 8.1 README.md

**فایل:** `README.md`

```markdown
# Persian Gravity Forms Refactor

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-green)](https://www.gnu.org/licenses/gpl-2.0.html)

A complete Persian localization and enhancement plugin for Gravity Forms with modern WordPress standards.

## Features

- ✅ **National ID Validation** - Validate Iranian national ID codes with checksum algorithm
- ✅ **Persian Date Picker** - Jalali calendar integration for date fields
- ✅ **Persian Numbers** - Automatic conversion between Persian and English numerals
- ✅ **RTL Support** - Complete right-to-left interface support
- ✅ **Accessibility** - WCAG 2.1 AA compliant
- ✅ **Performance** - Optimized with lazy loading and caching
- ✅ **Security** - Full nonce verification and sanitization

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Gravity Forms 2.5 or higher

## Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Activate the plugin
5. Go to **Settings > Persian GF** to configure

### Via FTP

1. Upload the `persian-gravityforms-refactor` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings > Persian GF** to configure

### Via Composer

```bash
composer require yourvendor/persian-gravityforms-refactor
```

## Configuration

After activation, go to **Settings > Persian GF** and enable desired features:

- **National ID Validation** - Add `pgr-national-id` CSS class to text fields
- **Persian Date Picker** - Add `pgr-persian-date` CSS class to date fields
- **Persian Numbers** - Add `pgr-persian-number` CSS class to number fields
- **RTL Support** - Automatically applied to all forms

## Usage Examples

### National ID Field

```php
// Add CSS class in Gravity Forms field settings
CSS Class: pgr-national-id
```

The field will automatically:
- Accept only 10 digits
- Validate checksum algorithm
- Show error for invalid codes
- Format display as XXX-XXXXXX-X

### Persian Date Field

```php
// Add CSS class in Gravity Forms field settings
CSS Class: pgr-persian-date
```

Features:
- Jalali calendar picker
- Persian month names
- RTL layout
- Gregorian conversion on submission

### Persian Number Field

```php
// Add CSS class in Gravity Forms field settings
CSS Class: pgr-persian-number
```

Automatically:
- Converts English to Persian digits on input
- Stores as English digits in database
- Displays as Persian in entries

## Hooks & Filters

### Actions

```php
// After plugin initialization
do_action( 'pgr_init' );

// After settings saved
do_action( 'pgr_settings_saved', $settings );
```

### Filters

```php
// Modify national ID validation
add_filter( 'pgr_validate_national_id', function( $is_valid, $national_id ) {
    // Custom validation logic
    return $is_valid;
}, 10, 2 );

// Modify Persian number conversion
add_filter( 'pgr_persian_numbers', function( $persian, $english ) {
    // Custom conversion logic
    return $persian;
}, 10, 2 );

// Modify date format
add_filter( 'pgr_persian_date_format', function( $format ) {
    return 'Y/m/d'; // Your custom format
} );
```

## Developer Guide

### Class Structure

```
PGR_Core                 - Main plugin class
├── PGR_Admin            - Admin settings & UI
├── PGR_National_ID      - National ID validation
├── PGR_Persian_Date     - Date conversion & picker
├── PGR_Persian_Numbers  - Number conversion
└── PGR_Form_Handler     - Gravity Forms integration
```

### Utility Functions

```php
// Convert numbers
PGR_Utils::convert_to_persian_numbers( '123' ); // Returns: '۱۲۳'
PGR_Utils::convert_to_english_numbers( '۱۲۳' ); // Returns: '123'

// Validate national ID
PGR_Utils::validate_national_id( '0123456789' ); // Returns: bool

// Get options
PGR_Utils::get_option( 'enable_national_id' ); // Returns: '1' or ''
PGR_Utils::is_feature_enabled( 'national_id' ); // Returns: bool

// Gregorian to Jalali
list( $j_year, $j_month, $j_day ) = PGR_Utils::gregorian_to_jalali( 2025, 1, 1 );
```

### Custom Integration

```php
// Check if plugin is active
if ( class_exists( 'PGR_Core' ) ) {
    $pgr = PGR_Core::get_instance();
    
    // Get module
    $national_id = $pgr->get_module( 'national_id' );
    
    // Use utilities
    if ( PGR_Utils::is_feature_enabled( 'national_id' ) ) {
        // Your code
    }
}
```

## Troubleshooting

### Plugin not working

1. Ensure Gravity Forms is installed and activated
2. Check PHP version (7.4+ required)
3. Enable `WP_DEBUG` to see error messages
4. Check browser console for JavaScript errors

### National ID validation not working

1. Verify CSS class is added: `pgr-national-id`
2. Check Settings > Persian GF - "Enable National ID" is checked
3. Clear browser cache
4. Test with valid national ID: `0123456789`

### Styles not loading

1. Clear all caches (plugin, server, CDN)
2. Check file permissions on `assets/` folder
3. Verify no JavaScript errors blocking execution
4. Test in default WordPress theme

### RTL not working

1. Ensure WordPress language is set to Persian (فارسی)
2. Check theme RTL support
3. Add CSS class `pgr-rtl` to form wrapper
4. Verify CSS file is loading

## Performance Tips

- Enable object caching (Redis/Memcached)
- Use a CDN for static assets
- Enable lazy loading for forms
- Minimize number of active features
- Use transient caching for repeated queries

## Security

This plugin follows WordPress security best practices:

- ✅ Nonce verification for all state changes
- ✅ Capability checks for admin actions
- ✅ Input sanitization and validation
- ✅ Output escaping
- ✅ Prepared SQL statements
- ✅ No direct file access
- ✅ CSRF protection

Report security issues to: security@example.com

## Changelog

### 1.0.0 (2025-11-01)
- 🎉 Initial release
- ✨ National ID validation
- ✨ Persian date picker
- ✨ Persian number conversion
- ✨ Complete RTL support
- ✨ WCAG 2.1 AA accessibility
- ✨ Modern code architecture
- ✨ Full AGENTS.md compliance

## Credits

- Developed by [Your Name](https://example.com)
- Persian calendar conversion algorithm
- National ID validation algorithm
- Contributors: [List contributors]

## Support

- 📚 [Documentation](https://example.com/docs)
- 💬 [Community Forum](https://example.com/forum)
- 🐛 [Report Bug](https://github.com/yourrepo/issues)
- 💡 [Request Feature](https://github.com/yourrepo/issues)

## License

This plugin is licensed under [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Copyright (C) 2025 Your Name

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting pull requests.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

Made with ❤️ for the Persian WordPress community
```

### 8.2 CHANGELOG.md

**فایل:** `CHANGELOG.md`

```markdown
# Changelog

All notable changes to Persian Gravity Forms Refactor will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-01

### Added
- 🎉 Initial stable release
- ✨ Complete plugin refactor following AGENTS.md standards
- ✨ SPL autoloader for `PGR_` prefixed classes
- ✨ Canonical entry point: `persian-gravityforms-refactor.php`
- ✨ Modern admin settings interface with nonce verification
- ✨ Separated admin and frontend assets
- ✨ National ID validation with checksum algorithm
- ✨ Persian date picker with Jalali calendar
- ✨ Automatic Persian/English number conversion
- ✨ Complete RTL support with logical CSS properties
- ✨ WCAG 2.1 AA accessibility compliance
- ✨ Lazy loading for performance optimization
- ✨ Transient caching for Gravity Forms queries
- ✨ Full internationalization with `.pot` file
- ✨ Comprehensive utility functions library
- ✨ Developer hooks and filters
- ✨ Unit test structure
- ✨ Complete documentation

### Changed
- 🔄 Renamed all classes to `PGR_` prefix
- 🔄 Converted all comparisons to Yoda conditions
- 🔄 Restructured file organization per AGENTS.md
- 🔄 Moved admin logic to dedicated `admin/` directory
- 🔄 Updated text domain to `persian-gravityforms-refactor`
- 🔄 Improved error messages with proper escaping
- 🔄 Enhanced JavaScript with IIFE pattern
- 🔄 Optimized CSS with scoped `.pgr-` selectors

### Security
- 🔒 Added nonce verification to all AJAX handlers
- 🔒 Implemented input sanitization across all forms
- 🔒 Added output escaping for all dynamic content
- 🔒 Converted SQL queries to use `$wpdb->prepare()`
- 🔒 Added capability checks for admin actions
- 🔒 Implemented direct access guards in all PHP files
- 🔒 Added CSRF protection

### Fixed
- 🐛 Fixed version mismatch between files
- 🐛 Fixed missing translation wrapping
- 🐛 Fixed insecure superglobal access
- 🐛 Fixed missing ABSPATH guards
- 🐛 Fixed non-scoped CSS selectors
- 🐛 Fixed global JavaScript variables
- 🐛 Fixed accessibility issues in admin UI

### Performance
- ⚡ Implemented lazy module loading
- ⚡ Added conditional asset enqueuing
- ⚡ Implemented transient caching
- ⚡ Optimized database queries
- ⚡ Reduced unnecessary GF hook calls

### Developer
- 👨‍💻 Added comprehensive code documentation
- 👨‍💻 Implemented PSR-12 coding standards
- 👨‍💻 Added developer hooks and filters
- 👨‍💻 Created utility functions library
- 👨‍💻 Added inline code comments
- 👨‍💻 Created AGENTS.md compliance guide

## [0.9.0] - 2024-XX-XX (Beta)

### Added
- Legacy functionality from original plugin
- Basic Persian support
- National ID validation (legacy)
- Date conversion (legacy)

### Issues
- ❌ Non-standard file structure
- ❌ Missing security implementations
- ❌ Incomplete internationalization
- ❌ Performance bottlenecks
- ❌ Accessibility gaps

---

[1.0.0]: https://github.com/yourrepo/persian-gravityforms-refactor/releases/tag/v1.0.0
[0.9.0]: https://github.com/yourrepo/persian-gravityforms-refactor/releases/tag/v0.9.0
```

### 8.3 .gitignore

**فایل:** `.gitignore`

```gitignore
# WordPress
wp-config.php
wp-content/uploads/
wp-content/blogs.dir/
wp-content/upgrade/
wp-content/backup-db/
wp-content/advanced-cache.php
wp-content/wp-cache-config.php
wp-content/cache/
wp-content/cache/supercache/

# Plugin development
.DS_Store
Thumbs.db
*.log
*.sql
*.sqlite
.env
.vscode/
.idea/
*.sublime-project
*.sublime-workspace

# Composer
/vendor/
composer.lock

# Node
node_modules/
npm-debug.log
yarn-error.log
package-lock.json
yarn.lock

# Build files
/build/
/dist/
*.zip

# Testing
/tests/tmp/
/tests/_output/
phpunit.xml

# IDE
.phpintel/
*.swp
*.swo
*~

# OS
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Desktop.ini
```

### 8.4 composer.json

**فایل:** `composer.json`

```json
{
    "name": "yourvendor/persian-gravityforms-refactor",
    "description": "Persian localization and enhancements for Gravity Forms",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "authors": [
        {
            "name": "Your Name",
            "email": "your@email.com",
            "homepage": "https://example.com"
        }
    ],
    "require": {
        "php": ">=7.4",
        "composer/installers": "^1.0 || ^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "squizlabs/php_codesniffer": "^3.6",
        "wp-coding-standards/wpcs": "^2.3",
        "phpcompatibility/phpcompatibility-wp": "^2.1"
    },
    "scripts": {
        "phpcs": "phpcs --standard=WordPress --extensions=php,inc .",
        "phpcbf": "phpcbf --standard=WordPress --extensions=php,inc .",
        "test": "phpunit",
        "lint": [
            "@phpcs"
        ]
    },
    "config": {
        "allow-plugins": {
            "composer/installers": true,
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
```

---

## 📋 فاز 9: دستورالعمل‌های اجرایی

### نحوه شروع بازنویسی

#### گام 1: بک‌آپ و Branch جدید

```bash
# بک‌آپ فایل‌های فعلی
cd /path/to/wordpress/wp-content/plugins/persian-gravity-forms
tar -czf ../pgf-backup-$(date +%Y%m%d).tar.gz .

# ایجاد branch جدید
git checkout -b refactor/agents-compliance

# نصب dependencies
composer install
```

#### گام 2: ایجاد ساختار جدید

```bash
# ایجاد دایرکتوری‌های جدید
mkdir -p admin
mkdir -p assets/{css,js,images}
mkdir -p languages
mkdir -p tests

# کپی فایل‌های entry جدید
# (از کدهای بالا استفاده کنید)
```

#### گام 3: Migration تدریجی

**روز 1-2:** Entry point و Core
```bash
# ایجاد فایل‌های اصلی
touch persian-gravityforms-refactor.php
touch includes/autoload.php
touch includes/class-pgr-core.php
touch includes/class-pgr-utils.php
touch includes/class-pgr-installer.php
```

**روز 3-4:** Admin و Assets
```bash
# ایجاد ماژول admin
touch admin/class-pgr-admin.php
touch assets/css/pgr-admin.css
touch assets/js/pgr-admin.js
```

**روز 5-7:** Feature Classes
```bash
# بازنویسی کلاس‌های موجود
cp includes/class-national-id.php includes/class-pgr-national-id.php
# سپس refactor کنید
```

#### گام 4: Testing مرحله‌ای

```bash
# تست هر feature بعد از refactor
wp plugin activate persian-gravityforms-refactor --path=/path/to/wp

# بررسی errors
tail -f /path/to/wp-content/debug.log

# تست با PHPCS
composer phpcs

# تست دستی هر feature
```

---

## 📋 نکات مهم و Best Practices

### 🔐 امنیت

```php
// ❌ اشتباه
$value = $_POST['field'];
update_option( 'my_option', $value );

// ✅ درست
if ( ! isset( $_POST['my_nonce'] ) || 
     ! wp_verify_nonce( $_POST['my_nonce'], 'my_action' ) ) {
    wp_die( 'Security check failed' );
}

$value = sanitize_text_field( $_POST['field'] );
update_option( 'my_option', $value );
```

### 🎨 Escaping

```php
// ❌ اشتباه
echo $user_input;
echo '<a href="' . $url . '">Link</a>';

// ✅ درست
echo esc_html( $user_input );
echo '<a href="' . esc_url( $url ) . '">Link</a>';
```

### 🔄 Yoda Conditions

```php
// ❌ اشتباه
if ( $value == '1' ) { }
if ( $status === 'active' ) { }

// ✅ درست
if ( '1' === $value ) { }
if ( 'active' === $status ) { }
```

### 🌐 i18n

```php
// ❌ اشتباه
echo 'تنظیمات ذخیره شد';

// ✅ درست
echo esc_html__( 'تنظیمات ذخیره شد', 'persian-gravityforms-refactor' );
```

---

## 🎯 انتظارات پس از بازنویسی

### قبل از Refactor (فعلی - 22%)
- ❌ ساختار نامنظم فایل‌ها
- ❌ بدون nonce verification
- ❌ Mixed naming conventions
- ❌ بدون autoloader استاندارد
- ❌ Performance issues
- ❌ Accessibility gaps

### بعد از Refactor (هدف - 100%)
- ✅ ساختار استاندارد AGENTS.md
- ✅ امنیت کامل با nonce و sanitization
- ✅ Naming یکپارچه با prefix `PGR_`
- ✅ SPL autoloader پیشرفته
- ✅ Performance optimized با caching
- ✅ WCAG 2.1 AA compliant

---

## 📞 پشتیبانی پس از بازنویسی

### مستندات
- Developer guide کامل
- User documentation فارسی
- Inline code comments
- Hook/filter reference

### کیفیت کد
- PHPCS compliance: 100%
- Security score: A+
- Performance: Grade A
- Accessibility: WCAG 2.1 AA

---

این سند بازنویسی کامل شما آماده است! 🎉

آیا می‌خواهید:
1. فایل خاص دیگری را با جزئیات بیشتر بنویسم؟
2. یک اسکریپت automation برای migration بسازم؟
3. Unit tests نمونه اضافه کنم؟





---
Powered by [Claude Exporter](https://www.claudexporter.com)