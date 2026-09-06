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
	 * Register field-owned Form Editor settings.
	 *
	 * @return void
	 */
	public static function register_editor_hooks() {
		add_action( 'gform_field_advanced_settings', array( __CLASS__, 'render_scanner_setting' ), 10, 2 );
	}

	/**
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Structured Scanner', 'persian-gravityforms' );
	}

	/**
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return false;
	}

	/**
	 * @return array
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
	 * Render profile and output mapping controls.
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

		$profiles = PGR_Scanner_Profile_Registry::all();
		?>
		<li class="pgr_scanner_setting field_setting">
			<label for="pgr_scanner_profile" class="section_label">
				<?php esc_html_e( 'Scanner profile', 'persian-gravityforms' ); ?>
			</label>
			<select id="pgr_scanner_profile" onchange="PGRScannerEditor.setProfile(this.value);">
				<option value=""><?php esc_html_e( 'Select a profile', 'persian-gravityforms' ); ?></option>
				<?php foreach ( $profiles as $profile_id => $profile ) : ?>
					<option value="<?php echo esc_attr( $profile_id ); ?>"><?php echo esc_html( $profile['label'] ); ?></option>
				<?php endforeach; ?>
			</select>

			<fieldset class="pgr-scanner-editor-mappings">
				<legend class="section_label"><?php esc_html_e( 'Output mapping', 'persian-gravityforms' ); ?></legend>
				<?php foreach ( $profiles as $profile ) : ?>
					<?php foreach ( $profile['outputs'] as $output_key => $output_label ) : ?>
						<div class="pgr-scanner-editor-mapping-row" data-pgr-output="<?php echo esc_attr( $output_key ); ?>">
							<label for="pgr_scanner_mapping_<?php echo esc_attr( $output_key ); ?>">
								<code><?php echo esc_html( $output_key ); ?></code>
								<span class="screen-reader-text"><?php echo esc_html( $output_label ); ?></span>
								<span aria-hidden="true">→</span>
							</label>
							<select
								id="pgr_scanner_mapping_<?php echo esc_attr( $output_key ); ?>"
								data-pgr-scanner-mapping="<?php echo esc_attr( $output_key ); ?>"
								onchange="PGRScannerEditor.setMapping('<?php echo esc_js( $output_key ); ?>', this.value);"
							></select>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</fieldset>

			<div id="pgr_scanner_mapping_warning" class="notice notice-warning inline" role="status" hidden><p></p></div>
			<p class="description">
				<?php esc_html_e( 'Structured Scanner v0.1 can populate Single Line Text and Hidden fields. Outputs may remain unmapped.', 'persian-gravityforms' ); ?>
			</p>
		</li>
		<?php
	}

	/**
	 * Initialize Form Editor defaults, target choices, and warnings.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$profiles = array();
		foreach ( PGR_Scanner_Profile_Registry::all() as $profile_id => $profile ) {
			$profiles[ $profile_id ] = array_keys( $profile['outputs'] );
		}

		$messages = array(
			'notMapped' => esc_html__( 'Not mapped', 'persian-gravityforms' ),
			/* translators: 1: target field ID, 2: target field label. */
			'fieldOption' => esc_html__( 'Field %1$s — %2$s', 'persian-gravityforms' ),
			/* translators: %s: configured target field ID. */
			'invalidTarget'     => esc_html__( 'Field %s — missing or unsupported', 'persian-gravityforms' ),
			'invalidProfile'    => esc_html__( 'Select a valid Scanner profile.', 'persian-gravityforms' ),
			'invalidMapping'    => esc_html__( 'Scanner mapping metadata is invalid.', 'persian-gravityforms' ),
			'missingTarget'     => esc_html__( 'A configured Scanner target field no longer exists.', 'persian-gravityforms' ),
			'unsupportedTarget' => esc_html__( 'A configured Scanner target is not a supported Single Line Text or Hidden field.', 'persian-gravityforms' ),
			'duplicateTarget'   => esc_html__( 'Two Scanner outputs cannot map to the same destination field.', 'persian-gravityforms' ),
			'selfTarget'        => esc_html__( 'Structured Scanner cannot target itself.', 'persian-gravityforms' ),
		);

		$title    = wp_json_encode( $this->get_form_editor_field_title() );
		$default  = wp_json_encode( PGR_Scanner_Profile_Registry::SAYAD_V01 );
		$profiles = wp_json_encode( $profiles );
		$messages = wp_json_encode( $messages );

		return "
			function SetDefaultValues_pgr_structured_scanner(field) {
				field.label = {$title};
				field.scanner_profile = {$default};
				field.scanner_mappings = [];
			}
			(function($) {
				'use strict';
				var profiles = {$profiles};
				var messages = {$messages};
				var field = null;
				var form = null;

				function outputs() {
					return field && Array.isArray(profiles[field.scanner_profile]) ? profiles[field.scanner_profile] : [];
				}
				function mappingObject() {
					if (!field || !Array.isArray(field.scanner_mappings)) {
						return null;
					}
					var keys = outputs();
					if (field.scanner_mappings.length > keys.length) {
						return null;
					}
					var map = {};
					keys.forEach(function(key, index) {
						var value = field.scanner_mappings[index];
						if (value !== undefined && value !== null && value !== '') {
							map[key] = value;
						}
					});
					return map;
				}
				function fieldMap() {
					var map = {};
					var fields = form && Array.isArray(form.fields) ? form.fields : [];
					fields.forEach(function(item) {
						if (item && item.id) {
							map[String(item.id)] = item;
						}
					});
					return map;
				}
				function supported(item) {
					return item && (item.type === 'text' || item.type === 'hidden') && item.displayOnly !== true;
				}
				function format(template, first, second) {
					return String(template).replace('%1\$s', first).replace('%2\$s', second).replace('%s', first);
				}
				function addWarning(warnings, message) {
					if (warnings.indexOf(message) === -1) {
						warnings.push(message);
					}
				}
				function optionExists(select, value) {
					var exists = false;
					select.find('option').each(function() {
						if (String($(this).val()) === String(value)) {
							exists = true;
						}
					});
					return exists;
				}
				function fillSelect(select, selected, fields) {
					select.empty().append($('<option>', { value: '', text: messages.notMapped }));
					Object.keys(fields).forEach(function(id) {
						if (!supported(fields[id])) {
							return;
						}
						select.append($('<option>', {
							value: id,
							text: format(messages.fieldOption, id, fields[id].label || fields[id].adminLabel || fields[id].type)
						}));
					});
					if (selected && !optionExists(select, selected)) {
						select.append($('<option>', {
							value: selected,
							text: format(messages.invalidTarget, selected),
							selected: true
						}));
					}
					select.val(selected || '');
				}
				function validate(map, fields) {
					var warnings = [];
					var keys = outputs();
					var used = {};
					if (!field || !Array.isArray(profiles[field.scanner_profile])) {
						addWarning(warnings, messages.invalidProfile);
					}
					if (map === null) {
						addWarning(warnings, messages.invalidMapping);
						map = {};
					}
					keys.forEach(function(key) {
						var raw = map[key];
						if (raw === undefined || raw === null || raw === '') {
							return;
						}
						var id = String(raw).trim();
						if (!/^[1-9]\d*$/.test(id)) {
							addWarning(warnings, messages.invalidMapping);
						} else if (String(field.id) === id) {
							addWarning(warnings, messages.selfTarget);
						} else {
							if (used[id]) {
								addWarning(warnings, messages.duplicateTarget);
							}
							used[id] = true;
							if (!fields[id]) {
								addWarning(warnings, messages.missingTarget);
							} else if (!supported(fields[id])) {
								addWarning(warnings, messages.unsupportedTarget);
							}
						}
					});
					var box = $('#pgr_scanner_mapping_warning');
					box.prop('hidden', warnings.length === 0);
					box.find('p').text(warnings.join(' '));
				}
				function refresh() {
					if (!field) {
						return;
					}
					var keys = outputs();
					var map = mappingObject();
					var fields = fieldMap();
					$('#pgr_scanner_profile').val(field.scanner_profile || '');
					$('[data-pgr-scanner-mapping]').each(function() {
						var select = $(this);
						var key = String(select.data('pgr-scanner-mapping'));
						var active = keys.indexOf(key) !== -1;
						select.closest('[data-pgr-output]').prop('hidden', !active);
						fillSelect(select, map && map[key] ? String(map[key]) : '', fields);
					});
					validate(map, fields);
				}
				window.PGRScannerEditor = {
					setProfile: function(value) {
						if (!field) {
							return;
						}
						SetFieldProperty('scanner_profile', value);
						SetFieldProperty('scanner_mappings', []);
						field.scanner_profile = value;
						field.scanner_mappings = [];
						refresh();
					},
					setMapping: function(key, value) {
						if (!field) {
							return;
						}
						var keys = outputs();
						var map = mappingObject() || {};
						if (value === '') {
							delete map[key];
						} else {
							map[key] = value;
						}
						var stored = keys.map(function(output) {
							return Object.prototype.hasOwnProperty.call(map, output) ? map[output] : '';
						});
						SetFieldProperty('scanner_mappings', stored);
						field.scanner_mappings = stored;
						refresh();
					}
				};
				$(document).on('gform_load_field_settings', function(event, currentField, currentForm) {
					if (!currentField || currentField.type !== 'pgr_structured_scanner') {
						return;
					}
					field = currentField;
					form = currentForm || window.form || null;
					refresh();
				});
			})(jQuery);
		";
	}

	/**
	 * Render transient Scanner controls with configuration-only data attributes.
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

		if ( false === $mappings_json ) {
			$mappings_json = 'null';
		}
		if ( false === $targets_json ) {
			$targets_json = '[]';
		}

		return sprintf(
			'<div class="ginput_container ginput_container_pgr_structured_scanner pgr-structured-scanner" data-pgr-structured-scanner="1" data-pgr-status="ready" data-pgr-scanner-id="%1$d" data-pgr-profile="%2$s" data-pgr-mappings="%3$s" data-pgr-targets="%4$s" data-pgr-message-processing="%5$s" data-pgr-message-success="%6$s" data-pgr-message-invalid="%7$s" data-pgr-message-segment-count="%8$s"><input type="text" class="pgr-structured-scanner__capture" data-pgr-scanner-capture="1" autocomplete="off" autocapitalize="off" spellcheck="false" dir="ltr" aria-label="%9$s"%10$s /><div class="pgr-structured-scanner__actions"><button type="button" class="pgr-structured-scanner__focus" data-pgr-scanner-focus="1"%10$s>%11$s</button><p class="pgr-structured-scanner__status" data-pgr-scanner-status="1" role="status" aria-live="polite" aria-atomic="true">%12$s</p></div></div>',
			absint( $this->id ),
			esc_attr( is_string( $this->scanner_profile ) ? $this->scanner_profile : '' ),
			esc_attr( $mappings_json ),
			esc_attr( $targets_json ),
			esc_attr__( 'Processing scan.', 'persian-gravityforms' ),
			esc_attr__( 'Fields populated. You can scan again.', 'persian-gravityforms' ),
			esc_attr__( 'Scan could not be applied. Check the scanner input or field mapping.', 'persian-gravityforms' ),
			/* translators: 1: expected segment count, 2: received segment count. */
			esc_attr__( 'Expected %1$d segments; received %2$d.', 'persian-gravityforms' ),
			esc_attr__( 'Scanner input', 'persian-gravityforms' ),
			$disabled,
			esc_html__( 'Activate scanner', 'persian-gravityforms' ),
			esc_html__( 'Scanner ready.', 'persian-gravityforms' )
		);
	}

	/**
	 * Structured Scanner never contributes data to Entry/detail/merge/export surfaces.
	 *
	 * @return string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		unset( $value, $form, $input_name, $lead_id, $lead );
		return '';
	}

	/**
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		unset( $value, $currency, $use_text, $format, $media );
		return '';
	}

	/**
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		unset( $value, $entry, $field_id, $columns, $form );
		return '';
	}

	/**
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		unset( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br );
		return '';
	}

	/**
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		unset( $entry, $input_id, $use_text, $is_csv );
		return '';
	}

	/**
	 * @return array<string,string>|null
	 */
	private function runtime_mappings() {
		$outputs = PGR_Scanner_Profile_Registry::output_keys( $this->scanner_profile );

		if ( empty( $outputs ) || ! is_array( $this->scanner_mappings ) || count( $this->scanner_mappings ) > count( $outputs ) ) {
			return null;
		}

		$mappings = array();
		foreach ( $this->scanner_mappings as $index => $target_id ) {
			if ( ! is_int( $index ) || ! isset( $outputs[ $index ] ) ) {
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

	/**
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
