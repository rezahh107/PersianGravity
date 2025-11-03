<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PGR_Admin {

    const OPTION = 'pgr_settings';

    public function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'register' ) );
        add_action( 'wp_ajax_pgr_check_nid', array( $this, 'ajax_check_nid' ) );
    }

    public function menu() {
        add_options_page(
            __( 'Persian Gravity Forms', 'persian-gravityforms-refactor' ),
            __( 'Persian GF', 'persian-gravityforms-refactor' ),
            'manage_options',
            'pgr-settings',
            array( $this, 'render' )
        );
    }

    public function register() {
        register_setting( 'pgr_settings_group', self::OPTION );

        add_settings_section(
            'pgr_defaults',
            __( 'Defaults for new fields', 'persian-gravityforms-refactor' ),
            function(){
                echo '<p>'. esc_html__( 'These defaults apply when you add a new National ID field in Gravity Forms.', 'persian-gravityforms-refactor' ) .'</p>';
            },
            'pgr_settings'
        );

        add_settings_field(
            'default_force_english',
            __( 'Force English digits by default', 'persian-gravityforms-refactor' ),
            array( $this, 'checkbox_cb' ),
            'pgr_settings',
            'pgr_defaults',
            array( 'key' => 'default_force_english' )
        );

        add_settings_field(
            'enable_nid_validation',
            __( 'Enable live validation by default', 'persian-gravityforms-refactor' ),
            array( $this, 'checkbox_cb' ),
            'pgr_settings',
            'pgr_defaults',
            array( 'key' => 'enable_nid_validation' )
        );

        add_settings_section(
            'pgr_tools',
            __( 'Tools', 'persian-gravityforms-refactor' ),
            function(){
                echo '<p>'. esc_html__( 'Quick tools for testing and compatibility.', 'persian-gravityforms-refactor' ) .'</p>';
            },
            'pgr_settings'
        );

        add_settings_field(
            'tool_nid_tester',
            __( 'National ID Tester', 'persian-gravityforms-refactor' ),
            array( $this, 'nid_tester_cb' ),
            'pgr_settings',
            'pgr_tools'
        );
    }

    public function checkbox_cb( $args ) {
        $opts = get_option( self::OPTION, array() );
        $key  = $args['key'];
        $val  = ! empty( $opts[ $key ] ) ? 1 : 0;
        echo '<label><input type="checkbox" name="'. esc_attr( self::OPTION ) .'['. esc_attr( $key ) .']" value="1" '. checked( 1, $val, false ) .' /> ';
        echo esc_html__( 'Enabled', 'persian-gravityforms-refactor' ) .'</label>';
    }

    public function nid_tester_cb() {
        $nonce = wp_create_nonce( 'pgr_check_nid' );
        ?>
        <div id="pgr-nid-tester">
            <input type="text" id="pgr-nid" value="" class="regular-text" placeholder="<?php esc_attr_e('Enter 10-digit National ID', 'persian-gravityforms-refactor'); ?>" />
            <button type="button" class="button" id="pgr-nid-check"><?php esc_html_e('Check', 'persian-gravityforms-refactor'); ?></button>
            <span id="pgr-nid-result" style="margin-left:8px;"></span>
        </div>
        <script>
        (function(){
            var btn = document.getElementById('pgr-nid-check');
            if(!btn){return;}
            btn.addEventListener('click', function(){
                var nid = document.getElementById('pgr-nid').value || '';
                var res = document.getElementById('pgr-nid-result');
                res.textContent = '<?php echo esc_js( __( 'Checking...', 'persian-gravityforms-refactor' ) ); ?>';
                var fd = new FormData();
                fd.append('action', 'pgr_check_nid');
                fd.append('nid', nid);
                fd.append('_wpnonce', '<?php echo esc_js( $nonce ); ?>');
                fetch(ajaxurl, {method:'POST', body: fd}).then(r=>r.json()).then(function(j){
                    res.textContent = j && j.valid ? '<?php echo esc_js( __( 'Valid', 'persian-gravityforms-refactor' ) ); ?>' : '<?php echo esc_js( __( 'Invalid', 'persian-gravityforms-refactor' ) ); ?>';
                }).catch(function(){
                    res.textContent = '<?php echo esc_js( __( 'Error', 'persian-gravityforms-refactor' ) ); ?>';
                });
            });
        })();
        </script>
        <?php
    }

    public function render() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Persian Gravity Forms', 'persian-gravityforms-refactor' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'pgr_settings_group' );
                do_settings_sections( 'pgr_settings' );
                submit_button();
                ?>
            </form>
            <hr />
            <p><small><?php echo esc_html( sprintf( __( 'Version %s', 'persian-gravityforms-refactor' ), PGR_VERSION ) ); ?></small></p>
        </div>
        <?php
    }

    public function ajax_check_nid() {
        check_ajax_referer( 'pgr_check_nid' );
        $nid = isset( $_POST['nid'] ) ? sanitize_text_field( wp_unslash( $_POST['nid'] ) ) : '';
        $valid = false;
        if ( class_exists( 'PGR_Utils' ) ) {
            $valid = PGR_Utils::is_valid_iran_national_id( $nid );
        }
        wp_send_json( array( 'valid' => (bool) $valid ) );
    }
}
