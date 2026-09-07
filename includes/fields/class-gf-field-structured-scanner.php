<?php
/**
 * Non-persistent Structured Scanner controller field for Gravity Forms.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_GF_Field_Structured_Scanner extends GF_Field {

	/** @var string */
	public $type = 'pgr_structured_scanner';

	/** @var bool */
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Gravity Forms GF_Field public API property.
	public $displayOnly = true;

	/** @var string */
	public $scanner_profile = PGR_Scanner_Profile_Registry::SAYAD_V01;

	/** @var array */
	public $scanner_mappings = array();

	/**
	 * Register field-owned Form Editor hooks.
	 *
	 * @return void
	 */
	public static function register_editor_hooks() {
		add_action( 'gform_field_advanced_settings', array( __CLASS__, 'render_scanner_setting' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Load Scanner editor CSS only on the Gravity Forms Form Editor.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing used only to scope an asset enqueue.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'gf_edit_forms' !== $page ) {
			return;
		}

		wp_enqueue_style(
			'pgr-scanner-editor',
			PGR_URL . 'assets/css/pgr-scanner-editor.css',
			array(),
			PGR_VERSION
		);
	}

	/**
	 * Return the Form Editor field title.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Structured Scanner', 'persian-gravityforms' );
	}

	/**
	 * Return Form Editor button metadata.
	 *
	 * @return array<string,string>
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Structured Scanner does not support conditional logic.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return false;
	}

	/**
	 * Return the supported Form Editor settings.
	 *
	 * @return array<int,string>
	 */
	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'description_setting',
			'css_class_setting',
			'label_placement_setting',
			'pgr_scanner_setting',
		);
	}

	/**
	 * Render profile and mapping controls.
	 *
	 * @param int $position Settings position.
	 * @param int $form_id  Current form ID.
	 * @return void
	 */
	public static function render_scanner_setting( $position, $form_id ) {
		unset( $form_id );
		if ( 50 !== $position ) {
			return;
		}

		$profiles = PGR_Scanner_Profile_Registry::active();
		?>
		<li class="pgr_scanner_setting field_setting">
			<label for="pgr_scanner_profile" class="section_label"><?php esc_html_e( 'Scanner profile', 'persian-gravityforms' ); ?></label>
			<select id="pgr_scanner_profile" onchange="PGRScannerEditor.setProfile(this.value);">
				<option value=""><?php esc_html_e( 'Select a profile', 'persian-gravityforms' ); ?></option>
				<?php foreach ( $profiles as $profile_id => $profile ) : ?>
					<option value="<?php echo esc_attr( $profile_id ); ?>"><?php echo esc_html( $profile['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<fieldset class="pgr-scanner-editor-mappings">
				<legend class="section_label"><?php esc_html_e( 'Output mapping', 'persian-gravityforms' ); ?></legend>
				<div data-pgr-scanner-mapping-list="1"></div>
			</fieldset>
			<div id="pgr_scanner_mapping_warning" class="notice notice-warning inline" role="status" hidden><p></p></div>
			<p class="description"><?php esc_html_e( 'Map profile outputs to ordinary Single Line Text or Hidden fields. Outputs may remain unmapped.', 'persian-gravityforms' ); ?></p>
		</li>
		<?php
	}

	/**
	 * Return the Form Editor integration script.
	 *
	 * New mappings are stored by output key. Legacy positional arrays are read
	 * against the profile's immutable executable contract.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$profiles = array();
		foreach ( PGR_Scanner_Profile_Registry::all() as $profile_id => $profile ) {
			$profiles[ $profile_id ] = array(
				'label'   => $profile['label'],
				'enabled' => ! empty( $profile['enabled'] ),
				'outputs' => array_values(
					array_map(
						static function ( $output ) {
							return array(
								'key'   => $output['key'],
								'label' => $output['label'],
							);
						},
						$profile['outputs']
					)
				),
			);
		}

		$messages = array(
			'notMapped'          => esc_html__( 'Not mapped', 'persian-gravityforms' ),
			/* translators: 1: field ID, 2: field label. */
			'fieldOption'        => esc_html__( 'Field %1$s — %2$s', 'persian-gravityforms' ),
			/* translators: %s: field ID. */
			'invalidTarget'      => esc_html__( 'Field %s — missing or unsupported', 'persian-gravityforms' ),
			/* translators: %s: Scanner profile ID. */
			'unavailableProfile' => esc_html__( 'The selected Scanner profile is no longer available: %s', 'persian-gravityforms' ),
			'invalidMapping'     => esc_html__( 'Scanner mapping metadata is invalid.', 'persian-gravityforms' ),
			'missingTarget'      => esc_html__( 'A configured Scanner target field no longer exists.', 'persian-gravityforms' ),
			'unsupportedTarget'  => esc_html__( 'A configured Scanner target is not a supported Single Line Text or Hidden field.', 'persian-gravityforms' ),
			'duplicateTarget'    => esc_html__( 'Two Scanner outputs cannot map to the same destination field.', 'persian-gravityforms' ),
			'selfTarget'         => esc_html__( 'Structured Scanner cannot target itself.', 'persian-gravityforms' ),
			'destination'        => esc_html__( 'Destination', 'persian-gravityforms' ),
			'unavailableSuffix'  => esc_html__( 'unavailable', 'persian-gravityforms' ),
		);

		$title    = wp_json_encode( $this->get_form_editor_field_title() );
		$default  = wp_json_encode( PGR_Scanner_Profile_Registry::SAYAD_V01 );
		$profiles = wp_json_encode( $profiles );
		$messages = wp_json_encode( $messages );

		return "
			function SetDefaultValues_pgr_structured_scanner(field) { field.label = {$title}; field.scanner_profile = {$default}; field.scanner_mappings = {}; }
			(function($) {
				'use strict'; var profiles = {$profiles}; var messages = {$messages}; var field = null; var form = null;
				function profile() { return field && profiles[field.scanner_profile] ? profiles[field.scanner_profile] : null; }
				function outputs() { var current = profile(); return current && Array.isArray(current.outputs) ? current.outputs : []; }
				function outputKeys() { return outputs().map(function(output) { return output.key; }); }
				function mappingObject() {
					if (!field || !field.scanner_mappings || typeof field.scanner_mappings !== 'object') { return {}; }
					var keys = outputKeys(); var map = {};
					if (Array.isArray(field.scanner_mappings)) { if (field.scanner_mappings.length > keys.length) { return null; } keys.forEach(function(key, index) { var value = field.scanner_mappings[index]; if (value !== undefined && value !== null && value !== '') { map[key] = value; } }); return map; }
					Object.keys(field.scanner_mappings).forEach(function(key) { if (keys.indexOf(key) === -1) { map = null; return; } if (map !== null) { var value = field.scanner_mappings[key]; if (value !== undefined && value !== null && value !== '') { map[key] = value; } } }); return map;
				}
				function fieldMap() { var map = {}; var fields = form && Array.isArray(form.fields) ? form.fields : []; fields.forEach(function(item) { if (item && item.id) { map[String(item.id)] = item; } }); return map; }
				function supported(item) { return item && (item.type === 'text' || item.type === 'hidden') && item.displayOnly !== true; }
				function format(template, first, second) { return String(template).replace('%1\\$s', first).replace('%2\\$s', second).replace('%s', first); }
				function addWarning(warnings, message) { if (warnings.indexOf(message) === -1) { warnings.push(message); } }
				function optionExists(select, value) { var exists = false; select.find('option').each(function() { if (String($(this).val()) === String(value)) { exists = true; } }); return exists; }
				function fillSelect(select, selected, fields) { select.empty().append($('<option>', { value: '', text: messages.notMapped })); Object.keys(fields).forEach(function(id) { if (supported(fields[id])) { select.append($('<option>', { value: id, text: format(messages.fieldOption, id, fields[id].label || fields[id].adminLabel || fields[id].type) })); } }); if (selected && !optionExists(select, selected)) { select.append($('<option>', { value: selected, text: format(messages.invalidTarget, selected), selected: true })); } select.val(selected || ''); }
				function ensureProfileOption() { var select = $('#pgr_scanner_profile'); var id = field && field.scanner_profile ? String(field.scanner_profile) : ''; if (!id || optionExists(select, id)) { return; } var current = profiles[id]; var label = current ? current.label : id; select.append($('<option>', { value: id, text: label + ' — ' + messages.unavailableSuffix })); }
				function renderMappingRows(map, fields) { var list = $('[data-pgr-scanner-mapping-list]'); list.empty(); outputs().forEach(function(output) { var row = $('<div>', { class: 'pgr-scanner-editor-mapping-row', 'data-pgr-output': output.key }); var label = $('<label>', { class: 'pgr-scanner-editor-mapping-label' }); label.append($('<span>', { text: output.label })); label.append($('<code>', { class: 'pgr-scanner-editor-mapping-key', dir: 'ltr', text: output.key })); var select = $('<select>', { 'data-pgr-scanner-mapping': output.key, 'aria-label': messages.destination + ': ' + output.label }); fillSelect(select, map && map[output.key] ? String(map[output.key]) : '', fields); select.on('change', function() { window.PGRScannerEditor.setMapping(output.key, this.value); }); row.append(label, select); list.append(row); }); }
				function validate(map, fields) { var warnings = []; var current = profile(); var used = {}; if (!current || current.enabled !== true) { addWarning(warnings, format(messages.unavailableProfile, field && field.scanner_profile ? String(field.scanner_profile) : '')); } if (map === null) { addWarning(warnings, messages.invalidMapping); map = {}; } outputKeys().forEach(function(key) { var raw = map[key]; if (raw === undefined || raw === null || raw === '') { return; } var id = String(raw).trim(); if (!/^[1-9]\\d*$/.test(id)) { addWarning(warnings, messages.invalidMapping); } else if (String(field.id) === id) { addWarning(warnings, messages.selfTarget); } else { if (used[id]) { addWarning(warnings, messages.duplicateTarget); } used[id] = true; if (!fields[id]) { addWarning(warnings, messages.missingTarget); } else if (!supported(fields[id])) { addWarning(warnings, messages.unsupportedTarget); } } }); var box = $('#pgr_scanner_mapping_warning'); box.prop('hidden', warnings.length === 0); box.find('p').text(warnings.join(' ')); }
				function refresh() { if (!field) { return; } var map = mappingObject(); var fields = fieldMap(); ensureProfileOption(); $('#pgr_scanner_profile').val(field.scanner_profile || ''); renderMappingRows(map, fields); validate(map, fields); }
				window.PGRScannerEditor = { setProfile: function(value) { if (!field) { return; } SetFieldProperty('scanner_profile', value); SetFieldProperty('scanner_mappings', {}); field.scanner_profile = value; field.scanner_mappings = {}; refresh(); }, setMapping: function(key, value) { if (!field) { return; } var map = mappingObject() || {}; if (value === '') { delete map[key]; } else { map[key] = value; } SetFieldProperty('scanner_mappings', map); field.scanner_mappings = map; refresh(); } };
				$(document).on('gform_load_field_settings', function(event, currentField, currentForm) { if (!currentField || currentField.type !== 'pgr_structured_scanner') { return; } field = currentField; form = currentForm || window.form || null; refresh(); });
			})(jQuery);
		";
	}

	/**
	 * Render the transient Persian-first Scanner component.
	 *
	 * @param array      $form  Current form.
	 * @param string     $value Current value.
	 * @param array|null $entry Current entry.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		unset( $value, $entry );

		$runtime_mappings = $this->runtime_mappings();
		$mappings_json    = wp_json_encode( null === $runtime_mappings ? null : (object) $runtime_mappings );
		$targets_json     = wp_json_encode( $this->runtime_target_descriptors( $form ) );
		$disabled         = $this->is_form_editor() ? ' disabled="disabled"' : '';
		$profile          = PGR_Scanner_Profile_Registry::get( $this->scanner_profile );
		$is_operational   = null !== $profile && ! empty( $profile['enabled'] ) && null !== $runtime_mappings;
		$initial_status   = $is_operational ? 'ready' : 'configuration';
		$initial_message  = $is_operational ? __( 'آماده دریافت اطلاعات', 'persian-gravityforms' ) : __( 'اسکنر برای این فرم به‌درستی تنظیم نشده است. با مدیر سیستم تماس بگیرید.', 'persian-gravityforms' );
		$mappings_json    = false === $mappings_json ? 'null' : $mappings_json;
		$targets_json     = false === $targets_json ? '[]' : $targets_json;

		return sprintf(
			'<div class="ginput_container ginput_container_pgr_structured_scanner pgr-structured-scanner" dir="rtl" data-pgr-structured-scanner="1" data-pgr-status="%1$s" data-pgr-scanner-id="%2$d" data-pgr-profile="%3$s" data-pgr-mappings="%4$s" data-pgr-targets="%5$s" data-pgr-message-ready="%6$s" data-pgr-message-processing="%7$s" data-pgr-message-success="%8$s" data-pgr-message-invalid="%9$s" data-pgr-message-configuration="%10$s"><div class="pgr-structured-scanner__header"><h3 class="pgr-structured-scanner__title">%11$s</h3><span class="pgr-structured-scanner__state-badge" aria-hidden="true">%12$s</span></div><p class="pgr-structured-scanner__lead" id="pgr-scanner-help-%2$d">%13$s</p><p class="pgr-structured-scanner__privacy">%14$s</p><textarea class="pgr-structured-scanner__capture" data-pgr-scanner-capture="1" rows="1" autocomplete="off" autocapitalize="off" spellcheck="false" dir="ltr" aria-label="%15$s" aria-describedby="pgr-scanner-help-%2$d" placeholder="%16$s"%17$s></textarea><div class="pgr-structured-scanner__actions"><button type="button" class="pgr-structured-scanner__focus" data-pgr-scanner-focus="1"%17$s>%18$s</button><p class="pgr-structured-scanner__status" data-pgr-scanner-status="1" role="status" aria-live="polite" aria-atomic="true">%19$s</p></div></div>',
			esc_attr( $initial_status ),
			absint( $this->id ),
			esc_attr( is_string( $this->scanner_profile ) ? $this->scanner_profile : '' ),
			esc_attr( $mappings_json ),
			esc_attr( $targets_json ),
			esc_attr__( 'آماده دریافت اطلاعات', 'persian-gravityforms' ),
			esc_attr__( 'در حال خواندن اطلاعات…', 'persian-gravityforms' ),
			esc_attr__( 'اطلاعات چک با موفقیت خوانده شد.', 'persian-gravityforms' ),
			esc_attr__( 'اطلاعات اسکن‌شده قابل خواندن نبود. دوباره تلاش کنید.', 'persian-gravityforms' ),
			esc_attr__( 'اسکنر برای این فرم به‌درستی تنظیم نشده است. با مدیر سیستم تماس بگیرید.', 'persian-gravityforms' ),
			esc_html__( 'اسکن اطلاعات چک', 'persian-gravityforms' ),
			esc_html__( 'وضعیت', 'persian-gravityforms' ),
			esc_html__( 'اسکنر را روی کد چک بگیرید.', 'persian-gravityforms' ),
			esc_html__( 'اطلاعات خوانده‌شده در فیلدهای چک قرار می‌گیرد؛ خود کد اسکن نگهداری نمی‌شود.', 'persian-gravityforms' ),
			esc_attr__( 'ورودی اسکنر', 'persian-gravityforms' ),
			esc_attr__( 'برای اسکن آماده است', 'persian-gravityforms' ),
			$disabled,
			esc_html__( 'آماده‌سازی برای اسکن', 'persian-gravityforms' ),
			esc_html( $initial_message )
		);
	}

	/**
	 * Structured Scanner never contributes data to Entry persistence.
	 *
	 * @param mixed $value      Submitted value.
	 * @param array $form       Form.
	 * @param mixed $input_name Input name.
	 * @param mixed $lead_id    Lead ID.
	 * @param mixed $lead       Lead.
	 * @return string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		unset( $value, $form, $input_name, $lead_id, $lead );
		return '';
	}

	/** @return string */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		unset( $value, $currency, $use_text, $format, $media );
		return '';
	}

	/** @return string */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		unset( $value, $entry, $field_id, $columns, $form );
		return '';
	}

	/** @return string */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		unset( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br );
		return '';
	}

	/** @return string */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		unset( $entry, $input_id, $use_text, $is_csv );
		return '';
	}

	/**
	 * Normalize keyed mappings and legacy positional mappings for runtime use.
	 *
	 * @return array<string,string>|null
	 */
	private function runtime_mappings() {
		$outputs = PGR_Scanner_Profile_Registry::output_keys( $this->scanner_profile );
		if ( empty( $outputs ) ) {
			return null;
		}

		$stored = $this->scanner_mappings;
		if ( is_object( $stored ) ) {
			$stored = get_object_vars( $stored );
		}
		if ( ! is_array( $stored ) ) {
			return null;
		}

		if ( array_is_list( $stored ) ) {
			if ( count( $stored ) > count( $outputs ) ) {
				return null;
			}

			$mappings = array();
			foreach ( $stored as $index => $target_id ) {
				if ( ! isset( $outputs[ $index ] ) ) {
					return null;
				}
				if ( null === $target_id || '' === $target_id ) {
					continue;
				}
				if ( ! is_scalar( $target_id ) ) {
					return null;
				}
				$mappings[ $outputs[ $index ] ] = (string) $target_id;
			}
			return $mappings;
		}

		$allowed  = array_fill_keys( $outputs, true );
		$mappings = array();
		foreach ( $stored as $output_key => $target_id ) {
			if ( ! is_string( $output_key ) || ! isset( $allowed[ $output_key ] ) ) {
				return null;
			}
			if ( null === $target_id || '' === $target_id ) {
				continue;
			}
			if ( ! is_scalar( $target_id ) ) {
				return null;
			}
			$mappings[ $output_key ] = (string) $target_id;
		}

		return $mappings;
	}

	/**
	 * Return runtime target descriptors for the owning form.
	 *
	 * @param array $form Current form.
	 * @return array<int,array{id:string,type:string,displayOnly:bool}>
	 */
	private function runtime_target_descriptors( $form ) {
		$targets = array();
		foreach ( (array) rgar( $form, 'fields' ) as $field ) {
			if ( ! is_object( $field ) || empty( $field->id ) || empty( $field->type ) ) {
				continue;
			}

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms GF_Field public API property.
			$display_only = ! empty( $field->displayOnly );
			$targets[]    = array(
				'id'          => (string) absint( $field->id ),
				'type'        => (string) $field->type,
				'displayOnly' => $display_only,
			);
		}

		return $targets;
	}
}
