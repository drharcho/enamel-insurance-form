<?php
/**
 * WordPress admin settings page for Enamel Insurance Form.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Enamel_Admin_Settings {

    const OPTION_KEY = 'enamel_if_settings';
    const PAGE_SLUG  = 'enamel-insurance-form';
    const SECTION    = 'enamel_if_main_section';

    public function __construct() {
        add_action( 'admin_menu',       array( $this, 'add_menu_page' ) );
        add_action( 'admin_init',       array( $this, 'register_settings' ) );
        add_action( 'admin_post_enamel_if_clear_cache', array( $this, 'handle_clear_cache' ) );
    }

    // -----------------------------------------------------------------------
    // Menu
    // -----------------------------------------------------------------------

    public function add_menu_page() {
        add_options_page(
            'Enamel Insurance Form',
            'Enamel Insurance Form',
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    // -----------------------------------------------------------------------
    // Settings registration
    // -----------------------------------------------------------------------

    public function register_settings() {
        register_setting(
            self::PAGE_SLUG,
            self::OPTION_KEY,
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );

        add_settings_section(
            self::SECTION,
            'Integration Settings',
            array( $this, 'render_section_intro' ),
            self::PAGE_SLUG
        );

        $fields = $this->get_fields();
        foreach ( $fields as $field ) {
            add_settings_field(
                $field['id'],
                $field['label'],
                array( $this, 'render_field' ),
                self::PAGE_SLUG,
                self::SECTION,
                $field
            );
        }

        add_settings_section(
            'enamel_if_booking_section',
            'Booking URLs by Location',
            array( $this, 'render_booking_section_intro' ),
            self::PAGE_SLUG
        );

        foreach ( $this->get_booking_fields() as $field ) {
            add_settings_field(
                $field['id'],
                $field['label'],
                array( $this, 'render_booking_field' ),
                self::PAGE_SLUG,
                'enamel_if_booking_section',
                $field
            );
        }
    }

    /**
     * Sanitize all settings on save.
     *
     * @param  array $input
     * @return array
     */
    public function sanitize_settings( $input ) {
        $clean = array();

        $clean['slack_webhook_url'] = isset( $input['slack_webhook_url'] )
            ? esc_url_raw( trim( $input['slack_webhook_url'] ) )
            : '';

        // Sanitize insurance list: strip tags, normalise line endings
        if ( isset( $input['insurance_list'] ) ) {
            $raw   = str_replace( "\r\n", "\n", $input['insurance_list'] );
            $lines = explode( "\n", $raw );
            $safe  = array();
            foreach ( $lines as $line ) {
                $line = sanitize_text_field( trim( $line ) );
                if ( $line !== '' ) {
                    $safe[] = $line;
                }
            }
            $clean['insurance_list'] = implode( "\n", $safe );
        } else {
            $clean['insurance_list'] = '';
        }

        foreach ( $this->get_booking_fields() as $field ) {
            $key = $field['option_key'];
            if ( strpos( $key, 'phone_' ) === 0 ) {
                $clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( trim( $input[ $key ] ) ) : '';
            } else {
                $clean[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( trim( $input[ $key ] ) ) : '';
            }
        }

        return $clean;
    }

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------

    public function render_booking_section_intro() {
        echo '<p style="color:#555;max-width:600px;">Set the online booking URL and phone number for each location. '
           . 'When a patient\'s insurance is accepted, they\'ll see "Book Online" and "Call Now" buttons linking to the correct office.</p>';
    }

    public function render_booking_field( $field ) {
        $settings = get_option( self::OPTION_KEY, array() );
        $key      = $field['option_key'];
        $value    = isset( $settings[ $key ] ) && $settings[ $key ] !== '' ? $settings[ $key ] : '';
        $type     = isset( $field['type'] ) ? $field['type'] : 'text';
        $default  = isset( $field['default'] ) ? $field['default'] : '';

        printf(
            '<input type="%s" name="%s[%s]" value="%s" class="regular-text" placeholder="%s">',
            esc_attr( $type ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value !== '' ? $value : $default ),
            esc_attr( $default )
        );
        if ( $value === '' && $default !== '' ) {
            echo '<p class="description" style="color:#7D55C7;">&#10003; Default value pre-loaded from your location data. Override above to change.</p>';
        }
    }

    public function render_section_intro() {
        echo '<p style="color:#555;max-width:600px;">Configure integrations for the Enamel Insurance Form. '
           . 'Values set as PHP constants in <code>wp-config.php</code> take priority over fields saved here and '
           . 'will be shown as locked.</p>';
    }

    public function render_field( $field ) {
        $settings = get_option( self::OPTION_KEY, array() );
        $key      = $field['option_key'];
        $value    = isset( $settings[ $key ] ) ? $settings[ $key ] : $field['default'];
        $const    = strtoupper( $field['id'] );
        $locked   = ( $const === $field['id'] ) ? false : defined( $const );

        // Only the SLACK_WEBHOOK_URL can be locked via constant
        if ( 'SLACK_WEBHOOK_URL' === $field['id'] && defined( 'SLACK_WEBHOOK_URL' ) ) {
            echo '<input type="text" value="(set via PHP constant)" disabled class="regular-text" '
               . 'style="color:#888;background:#f5f5f5;cursor:not-allowed;">';
            echo '<p class="description" style="color:#7D55C7;font-weight:500;">&#128274; Overridden by constant <code>SLACK_WEBHOOK_URL</code> in wp-config.php</p>';
            return;
        }

        if ( 'textarea' === $field['type'] ) {
            $is_insurance = ( $key === 'insurance_list' );
            printf(
                '<textarea name="%s[%s]" rows="%d" class="large-text%s" style="%s">%s</textarea>',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                $is_insurance ? 16 : 8,
                $is_insurance ? '' : ' code',
                $is_insurance ? 'font-size:13px;line-height:1.6;' : 'font-family:monospace;font-size:12px;',
                esc_textarea( $value )
            );
        } else {
            printf(
                '<input type="text" name="%s[%s]" value="%s" class="regular-text">',
                esc_attr( self::OPTION_KEY ),
                esc_attr( $key ),
                esc_attr( $value )
            );
        }

        if ( ! empty( $field['description'] ) ) {
            echo '<p class="description">' . wp_kses_post( $field['description'] ) . '</p>';
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = get_option( self::OPTION_KEY, array() );
        ?>
        <div class="wrap" style="max-width:760px;">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <span style="display:inline-block;background:#7D55C7;color:#fff;border-radius:8px;padding:4px 12px;font-size:14px;font-weight:700;letter-spacing:0.5px;">ENAMEL</span>
                Insurance Form &mdash; Settings
            </h1>

            <?php settings_errors( self::OPTION_KEY ); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( self::PAGE_SLUG );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( 'Save Settings' );
                ?>
            </form>

            <hr style="margin:32px 0;">

            <h2>Shortcode Usage</h2>
            <p>Place the following shortcode on any page or post to display the insurance check form:</p>
            <code style="display:inline-block;background:#f4f2fa;border:1px solid #EDE9FF;padding:8px 16px;border-radius:6px;font-size:15px;color:#231942;">[enamel_insurance_form]</code>

            <hr style="margin:32px 0;">

            <h2>Monthly Insurance List Update</h2>
            <p>Each month, paste the updated accepted insurance plan names into the <strong>Accepted Insurance Plans</strong> field above — one plan per line — then click <strong>Save Settings</strong>. The form will reflect the new list immediately.</p>

            <hr style="margin:32px 0;">

            <h2>PHP Constant (optional)</h2>
            <p>You can lock the webhook URL out of the database by adding this to <code>wp-config.php</code>:</p>
            <pre style="background:#231942;color:#EDE9FF;padding:20px;border-radius:8px;font-size:13px;overflow-x:auto;line-height:1.7;">define( 'SLACK_WEBHOOK_URL', 'https://your-openclaw-or-slack-webhook-url' );</pre>
        </div>
        <?php
    }

    // -----------------------------------------------------------------------
    // Cache clear handler
    // -----------------------------------------------------------------------

    public function handle_clear_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }

        check_admin_referer( 'enamel_if_clear_cache' );

        global $wpdb;

        // Delete all transients with our prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_enamel_ins_%',
                '_transient_timeout_enamel_ins_%'
            )
        );

        add_settings_error(
            self::OPTION_KEY,
            'cache_cleared',
            'Insurance cache cleared successfully.',
            'success'
        );

        set_transient( 'settings_errors', get_settings_errors(), 30 );

        wp_redirect( add_query_arg(
            array(
                'page'             => self::PAGE_SLUG,
                'settings-updated' => 'true',
            ),
            admin_url( 'options-general.php' )
        ) );
        exit;
    }

    // -----------------------------------------------------------------------
    // Field definitions
    // -----------------------------------------------------------------------

    private function get_booking_fields() {
        $locations = array(
            'South Lamar'  => array( 'slug' => 'south_lamar',  'url' => 'https://enamel.subscribili.com/appointments?locid=1667206608814bb935', 'phone' => '(512) 717-5315' ),
            'Parmer Park'  => array( 'slug' => 'parmer_park',  'url' => 'https://enamel.subscribili.com/appointments?locid=1667206608975d97b8', 'phone' => '(512) 572-0215' ),
            'Lantana'      => array( 'slug' => 'lantana',      'url' => 'https://enamel.subscribili.com/appointments?locid=1667206608b3c6ef63', 'phone' => '(512) 648-6115' ),
            'Saltillo'     => array( 'slug' => 'saltillo',     'url' => 'https://enamel.subscribili.com/appointments?locid=1667207221a259e44e', 'phone' => '(512) 649-7510' ),
            'Domain'       => array( 'slug' => 'domain',       'url' => 'https://enamel.subscribili.com/appointments?locid=16672072212d957aa2', 'phone' => '(512) 646-0815' ),
            'McKinney'     => array( 'slug' => 'mckinney',     'url' => 'https://enamel.subscribili.com/appointments?locid=16711668866ea8284e', 'phone' => '(469) 663-0515' ),
            'Manor'        => array( 'slug' => 'manor',        'url' => 'https://enamel.subscribili.com/appointments?locid=16790711557db1e93b', 'phone' => '(512) 982-1272' ),
            'at the Grove' => array( 'slug' => 'at_the_grove', 'url' => 'https://enamel.subscribili.com/appointments?locid=166720722184ee6bb5', 'phone' => '(512) 884-5658' ),
            'Easton Park'  => array( 'slug' => 'easton_park',  'url' => 'https://enamel.subscribili.com/appointments?locid=17574429568b39744e', 'phone' => '(512) 489-4015' ),
            'Leander'      => array( 'slug' => 'leander',      'url' => 'https://enamel.subscribili.com/appointments?locid=17574454288bc0d53c', 'phone' => '(512) 337-3415' ),
        );

        $fields = array();
        foreach ( $locations as $label => $data ) {
            $slug = $data['slug'];
            $fields[] = array(
                'id'          => 'booking_url_' . $slug,
                'label'       => 'Enamel Dentistry ' . $label . ' — Book Online URL',
                'option_key'  => 'booking_url_' . $slug,
                'type'        => 'text',
                'default'     => $data['url'],
                'placeholder' => $data['url'],
            );
            $fields[] = array(
                'id'          => 'phone_' . $slug,
                'label'       => 'Enamel Dentistry ' . $label . ' — Phone Number',
                'option_key'  => 'phone_' . $slug,
                'type'        => 'text',
                'default'     => $data['phone'],
                'placeholder' => $data['phone'],
            );
        }
        return $fields;
    }

    private function get_fields() {
        return array(
            array(
                'id'          => 'SLACK_WEBHOOK_URL',
                'label'       => 'Slack / OpenClaw Webhook URL',
                'option_key'  => 'slack_webhook_url',
                'type'        => 'text',
                'default'     => '',
                'description' => 'Webhook endpoint URL. Every new lead submission will POST here as a Slack Block Kit payload.',
            ),
            array(
                'id'          => 'insurance_list',
                'label'       => 'Accepted Insurance Plans',
                'option_key'  => 'insurance_list',
                'type'        => 'textarea',
                'default'     => '',
                'description' => 'Paste your accepted insurance plans here — one plan per line. Update this list each month. The form autocomplete and acceptance check both use this list.',
            ),
        );
    }
}
