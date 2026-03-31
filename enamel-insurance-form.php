<?php
/**
 * Plugin Name: Enamel Insurance Form
 * Plugin URI:  https://enameldentistry.com
 * Description: Insurance verification and lead capture form for Enamel Dentistry
 * Version:     1.0.6
 * Author:      Enamel Dentistry
 * Author URI:  https://enameldentistry.com
 * License:     GPL-2.0+
 * Text Domain: enamel-insurance-form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'ENAMEL_IF_VERSION', '1.0.6' );
define( 'ENAMEL_IF_PATH',    plugin_dir_path( __FILE__ ) );
define( 'ENAMEL_IF_URL',     plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------
require_once ENAMEL_IF_PATH . 'includes/class-slack-notifier.php';
require_once ENAMEL_IF_PATH . 'includes/class-admin-settings.php';

// ---------------------------------------------------------------------------
// Config helper
// ---------------------------------------------------------------------------
/**
 * Retrieve a plugin configuration value.
 * Priority: PHP constant → wp_options entry.
 *
 * @param string $key  One of: SLACK_WEBHOOK_URL
 * @return string
 */
function enamel_if_config( $key ) {
    if ( defined( $key ) ) {
        return constant( $key );
    }

    $settings = get_option( 'enamel_if_settings', array() );
    $map = array(
        'SLACK_WEBHOOK_URL' => 'slack_webhook_url',
    );

    if ( isset( $map[ $key ] ) && isset( $settings[ $map[ $key ] ] ) ) {
        return $settings[ $map[ $key ] ];
    }

    return '';
}

/**
 * Get the accepted insurance list from WP options.
 * Returns a flat array of insurance name strings.
 */
function enamel_if_get_insurance_list() {
    $settings = get_option( 'enamel_if_settings', array() );
    $raw      = isset( $settings['insurance_list'] ) ? $settings['insurance_list'] : '';

    if ( empty( $raw ) ) {
        return array();
    }

    $lines = explode( "\n", $raw );
    $list  = array();
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( $line !== '' ) {
            $list[] = $line;
        }
    }
    return $list;
}

// ---------------------------------------------------------------------------
// Shortcode flag — track whether the shortcode appears on the current page
// ---------------------------------------------------------------------------
$enamel_if_shortcode_rendered = false;

// ---------------------------------------------------------------------------
// Shortcode
// ---------------------------------------------------------------------------
add_shortcode( 'enamel_insurance_form', 'enamel_if_render_shortcode' );

function enamel_if_render_shortcode( $atts ) {
    global $enamel_if_shortcode_rendered;
    $enamel_if_shortcode_rendered = true;

    $locations = enamel_if_locations();

    ob_start();
    include ENAMEL_IF_PATH . 'templates/form.php';
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// Enqueue assets (only on pages that contain the shortcode)
// ---------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'enamel_if_enqueue_assets' );

function enamel_if_enqueue_assets() {
    global $enamel_if_shortcode_rendered;

    // We need to enqueue early but the shortcode flag is set during content
    // rendering. Use the has_shortcode() trick on the current post content.
    $load = false;

    if ( $enamel_if_shortcode_rendered ) {
        $load = true;
    } elseif ( is_singular() ) {
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'enamel_insurance_form' ) ) {
            $load = true;
        }
    }

    if ( ! $load ) {
        // Register anyway so localize works if shortcode is in a widget/block
        // that renders before wp_footer; we'll enqueue on demand via footer hook.
        wp_register_style(
            'enamel-insurance-form',
            ENAMEL_IF_URL . 'assets/css/enamel-form.css',
            array(),
            ENAMEL_IF_VERSION
        );
        wp_register_script(
            'enamel-insurance-form',
            ENAMEL_IF_URL . 'assets/js/enamel-form.js',
            array(),
            ENAMEL_IF_VERSION,
            true
        );
        return;
    }

    wp_enqueue_style(
        'enamel-insurance-form',
        ENAMEL_IF_URL . 'assets/css/enamel-form.css',
        array(),
        ENAMEL_IF_VERSION
    );

    wp_enqueue_script(
        'enamel-insurance-form',
        ENAMEL_IF_URL . 'assets/js/enamel-form.js',
        array(),
        ENAMEL_IF_VERSION,
        true
    );

    wp_localize_script(
        'enamel-insurance-form',
        'enamelIF',
        array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'enamel_if_nonce' ),
        )
    );
}

// Late enqueue fallback: if shortcode rendered inside a block/widget after
// wp_enqueue_scripts fired, ensure scripts are in the footer.
add_action( 'wp_footer', 'enamel_if_late_enqueue', 1 );

function enamel_if_late_enqueue() {
    global $enamel_if_shortcode_rendered;

    if ( ! $enamel_if_shortcode_rendered ) {
        return;
    }

    if ( ! wp_style_is( 'enamel-insurance-form', 'enqueued' ) ) {
        wp_enqueue_style( 'enamel-insurance-form' );
    }

    if ( ! wp_script_is( 'enamel-insurance-form', 'enqueued' ) ) {
        wp_enqueue_script( 'enamel-insurance-form' );
        wp_localize_script(
            'enamel-insurance-form',
            'enamelIF',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'enamel_if_nonce' ),
            )
        );
    }
}

// ---------------------------------------------------------------------------
// AJAX: get insurances for a location
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_enamel_get_insurances',        'enamel_if_ajax_get_insurances' );
add_action( 'wp_ajax_nopriv_enamel_get_insurances', 'enamel_if_ajax_get_insurances' );

function enamel_if_ajax_get_insurances() {
    check_ajax_referer( 'enamel_if_nonce', 'nonce' );

    $location = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';

    if ( empty( $location ) ) {
        wp_send_json_error( array( 'message' => 'Location is required.' ) );
    }

    if ( ! in_array( $location, enamel_if_locations(), true ) ) {
        wp_send_json_error( array( 'message' => 'Invalid location.' ) );
    }

    $insurances = enamel_if_get_insurance_list();

    if ( empty( $insurances ) ) {
        wp_send_json_error( array( 'message' => 'Insurance list has not been configured yet.' ) );
    }

    // Sort alphabetically for a clean dropdown
    natcasesort( $insurances );

    wp_send_json_success( array( 'insurances' => array_values( $insurances ) ) );
}

// ---------------------------------------------------------------------------
// AJAX: submit form
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_enamel_submit_form',        'enamel_if_ajax_submit_form' );
add_action( 'wp_ajax_nopriv_enamel_submit_form', 'enamel_if_ajax_submit_form' );

function enamel_if_ajax_submit_form() {
    check_ajax_referer( 'enamel_if_nonce', 'nonce' );

    // --- Sanitize inputs ---
    $name            = isset( $_POST['name'] )            ? sanitize_text_field( wp_unslash( $_POST['name'] ) )            : '';
    $phone           = isset( $_POST['phone'] )           ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )           : '';
    $email           = isset( $_POST['email'] )           ? sanitize_email( wp_unslash( $_POST['email'] ) )                : '';
    $location        = isset( $_POST['location'] )        ? sanitize_text_field( wp_unslash( $_POST['location'] ) )        : '';
    $insurance       = isset( $_POST['insurance'] )       ? sanitize_text_field( wp_unslash( $_POST['insurance'] ) )       : '';
    $insurance_other = isset( $_POST['insurance_other'] ) ? sanitize_text_field( wp_unslash( $_POST['insurance_other'] ) ) : '';

    // --- Basic server-side validation ---
    $errors = array();
    if ( empty( $name ) )      { $errors[] = 'Name is required.'; }
    if ( empty( $phone ) )     { $errors[] = 'Phone is required.'; }
    if ( empty( $email ) || ! is_email( $email ) ) { $errors[] = 'A valid email is required.'; }
    if ( empty( $location ) || ! in_array( $location, enamel_if_locations(), true ) ) {
        $errors[] = 'A valid location is required.';
    }
    if ( empty( $insurance ) ) { $errors[] = 'Insurance is required.'; }

    if ( ! empty( $errors ) ) {
        wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
    }

    // --- Handle "Other" insurance ---
    $is_other = ( $insurance === 'Other' );
    $insurance_label = $is_other ? ( ! empty( $insurance_other ) ? $insurance_other : 'Other' ) : $insurance;

    // --- Check insurance against the admin-managed list ---
    $accepted = false;
    if ( ! $is_other ) {
        $insurance_list = enamel_if_get_insurance_list();
        foreach ( $insurance_list as $item ) {
            if ( strcasecmp( trim( $item ), trim( $insurance ) ) === 0 ) {
                $accepted = true;
                break;
            }
        }
    }

    // --- Slack / OpenClaw notification ---
    $slack_webhook = enamel_if_config( 'SLACK_WEBHOOK_URL' );
    if ( ! empty( $slack_webhook ) ) {
        $notifier = new Enamel_Slack_Notifier();
        $notifier->notify( array(
            'name'      => $name,
            'phone'     => $phone,
            'email'     => $email,
            'location'  => $location,
            'insurance' => $insurance_label,
            'accepted'  => $is_other ? 'other' : $accepted,
        ) );
    }

    // --- Build response message ---
    if ( $is_other ) {
        $message = 'Our team will be reaching out to you shortly to talk about your insurance plan.';
    } elseif ( $accepted ) {
        $message = sprintf(
            'Great news! We accept %s at %s. We\'ll be in touch soon to schedule your appointment!',
            esc_html( $insurance_label ),
            esc_html( $location )
        );
    } else {
        $message = sprintf(
            'We don\'t currently list %s at %s, but our team will reach out to explore your options.',
            esc_html( $insurance_label ),
            esc_html( $location )
        );
    }

    wp_send_json_success( array(
        'accepted'    => $accepted,
        'other'       => $is_other,
        'message'     => $message,
        'booking_url' => enamel_if_get_booking_url( $location ),
        'phone'       => enamel_if_get_phone( $location ),
    ) );
}

// ---------------------------------------------------------------------------
// Location slug + booking helpers
// ---------------------------------------------------------------------------
function enamel_if_location_slug( $location ) {
    $map = array(
        'Enamel Dentistry South Lamar'  => 'south_lamar',
        'Enamel Dentistry Parmer Park'  => 'parmer_park',
        'Enamel Dentistry Lantana'      => 'lantana',
        'Enamel Dentistry Saltillo'     => 'saltillo',
        'Enamel Dentistry Domain'       => 'domain',
        'Enamel Dentistry McKinney'     => 'mckinney',
        'Enamel Dentistry Manor'        => 'manor',
        'Enamel Dentistry at the Grove' => 'at_the_grove',
        'Enamel Dentistry Easton Park'  => 'easton_park',
        'Enamel Dentistry Leander'      => 'leander',
    );
    return isset( $map[ $location ] ) ? $map[ $location ] : '';
}

function enamel_if_get_booking_url( $location ) {
    $slug     = enamel_if_location_slug( $location );
    $settings = get_option( 'enamel_if_settings', array() );
    if ( ! empty( $settings[ 'booking_url_' . $slug ] ) ) {
        return $settings[ 'booking_url_' . $slug ];
    }
    // Built-in defaults from Subscribili links
    $defaults = array(
        'south_lamar'  => 'https://enamel.subscribili.com/appointments?locid=1667206608814bb935',
        'parmer_park'  => 'https://enamel.subscribili.com/appointments?locid=1667206608975d97b8',
        'lantana'      => 'https://enamel.subscribili.com/appointments?locid=1667206608b3c6ef63',
        'saltillo'     => 'https://enamel.subscribili.com/appointments?locid=1667207221a259e44e',
        'domain'       => 'https://enamel.subscribili.com/appointments?locid=16672072212d957aa2',
        'at_the_grove' => 'https://enamel.subscribili.com/appointments?locid=166720722184ee6bb5',
        'mckinney'     => 'https://enamel.subscribili.com/appointments?locid=16711668866ea8284e',
        'manor'        => 'https://enamel.subscribili.com/appointments?locid=16790711557db1e93b',
        'easton_park'  => 'https://enamel.subscribili.com/appointments?locid=17574429568b39744e',
        'leander'      => 'https://enamel.subscribili.com/appointments?locid=17574454288bc0d53c',
    );
    return isset( $defaults[ $slug ] ) ? $defaults[ $slug ] : '';
}

function enamel_if_get_phone( $location ) {
    $slug     = enamel_if_location_slug( $location );
    $settings = get_option( 'enamel_if_settings', array() );
    if ( ! empty( $settings[ 'phone_' . $slug ] ) ) {
        return $settings[ 'phone_' . $slug ];
    }
    // Built-in defaults
    $defaults = array(
        'south_lamar'  => '(512) 717-5315',
        'parmer_park'  => '(512) 572-0215',
        'lantana'      => '(512) 648-6115',
        'saltillo'     => '(512) 649-7510',
        'domain'       => '(512) 646-0815',
        'at_the_grove' => '(512) 884-5658',
        'mckinney'     => '(469) 663-0515',
        'manor'        => '(512) 982-1272',
        'easton_park'  => '(512) 489-4015',
        'leander'      => '(512) 337-3415',
    );
    return isset( $defaults[ $slug ] ) ? $defaults[ $slug ] : '';
}

// ---------------------------------------------------------------------------
// Locations helper
// ---------------------------------------------------------------------------
function enamel_if_locations() {
    return array(
        'Enamel Dentistry South Lamar',
        'Enamel Dentistry Parmer Park',
        'Enamel Dentistry Lantana',
        'Enamel Dentistry Saltillo',
        'Enamel Dentistry Domain',
        'Enamel Dentistry McKinney',
        'Enamel Dentistry Manor',
        'Enamel Dentistry at the Grove',
        'Enamel Dentistry Easton Park',
        'Enamel Dentistry Leander',
    );
}

// ---------------------------------------------------------------------------
// Auto-updater — checks GitHub releases for new versions
// ---------------------------------------------------------------------------
add_filter( 'pre_set_site_transient_update_plugins', 'enamel_if_check_for_update' );

function enamel_if_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }

    $plugin_slug = plugin_basename( __FILE__ );
    $api_url     = 'https://api.github.com/repos/drharcho/enamel-insurance-form/releases/latest';

    $response = wp_remote_get( $api_url, array(
        'headers' => array(
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
        ),
        'timeout' => 10,
    ) );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }

    $release = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $release['tag_name'] ) ) {
        return $transient;
    }

    $latest_version = ltrim( $release['tag_name'], 'v' );

    if ( version_compare( $latest_version, ENAMEL_IF_VERSION, '>' ) ) {
        // Use the uploaded zip asset if available, fall back to zipball
        $package = $release['zipball_url'];
        if ( ! empty( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( pathinfo( $asset['name'], PATHINFO_EXTENSION ) === 'zip' ) {
                    $package = $asset['browser_download_url'];
                    break;
                }
            }
        }

        $transient->response[ $plugin_slug ] = (object) array(
            'slug'        => dirname( $plugin_slug ),
            'plugin'      => $plugin_slug,
            'new_version' => $latest_version,
            'url'         => 'https://github.com/drharcho/enamel-insurance-form',
            'package'     => $package,
        );
    }

    return $transient;
}

// ---------------------------------------------------------------------------
// Auto-updater: force correct folder name on install/update
// ---------------------------------------------------------------------------
add_filter( 'upgrader_source_selection', 'enamel_if_fix_update_folder', 10, 4 );

function enamel_if_fix_update_folder( $source, $remote_source, $upgrader, $extra ) {
    if ( ! isset( $extra['plugin'] ) || strpos( $extra['plugin'], 'enamel-insurance-form' ) === false ) {
        return $source;
    }

    $correct = trailingslashit( $remote_source ) . 'enamel-insurance-form/';

    if ( $source !== $correct ) {
        global $wp_filesystem;
        if ( $wp_filesystem->move( $source, $correct ) ) {
            return $correct;
        }
    }

    return $source;
}

// ---------------------------------------------------------------------------
// Admin settings
// ---------------------------------------------------------------------------
if ( is_admin() ) {
    new Enamel_Admin_Settings();
}
