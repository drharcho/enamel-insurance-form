<?php
/**
 * Template: Insurance Check Form
 *
 * Available variables:
 *   $locations  array  List of location name strings
 *
 * Supports multiple instances on the same page via a static counter.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

static $enamel_if_instance = 0;
$enamel_if_instance++;
$n = $enamel_if_instance;
?>
<div class="enamel-form-wrapper" data-enamel-instance="<?php echo $n; ?>">
    <div class="enamel-form-card">

        <!-- Header -->
        <div class="enamel-form-header">
            <h2>Check Your Insurance</h2>
            <p>Find out if we accept your insurance at your nearest location.</p>
        </div>

        <!-- Form body -->
        <div class="enamel-form-body">
            <form id="enamel-insurance-form-<?php echo $n; ?>" class="enamel-insurance-form" novalidate>

                <!-- Name -->
                <div class="enamel-form-field">
                    <label for="enamel-name-<?php echo $n; ?>" class="enamel-label">Full Name</label>
                    <input
                        type="text"
                        id="enamel-name-<?php echo $n; ?>"
                        name="name"
                        class="enamel-input"
                        placeholder="Jane Smith"
                        autocomplete="name"
                    >
                    <span class="enamel-field-error" id="enamel-name-error-<?php echo $n; ?>" aria-live="polite"></span>
                </div>

                <!-- Phone -->
                <div class="enamel-form-field">
                    <label for="enamel-phone-<?php echo $n; ?>" class="enamel-label">Phone Number</label>
                    <input
                        type="tel"
                        id="enamel-phone-<?php echo $n; ?>"
                        name="phone"
                        class="enamel-input"
                        placeholder="(512) 555-0100"
                        autocomplete="tel"
                    >
                    <span class="enamel-field-error" id="enamel-phone-error-<?php echo $n; ?>" aria-live="polite"></span>
                </div>

                <!-- Email -->
                <div class="enamel-form-field">
                    <label for="enamel-email-<?php echo $n; ?>" class="enamel-label">Email Address</label>
                    <input
                        type="email"
                        id="enamel-email-<?php echo $n; ?>"
                        name="email"
                        class="enamel-input"
                        placeholder="jane@example.com"
                        autocomplete="email"
                    >
                    <span class="enamel-field-error" id="enamel-email-error-<?php echo $n; ?>" aria-live="polite"></span>
                </div>

                <!-- Location -->
                <div class="enamel-form-field">
                    <label for="enamel-location-<?php echo $n; ?>" class="enamel-label">Location</label>
                    <select id="enamel-location-<?php echo $n; ?>" name="location" class="enamel-input enamel-select">
                        <option value="">— Select a location —</option>
                        <?php foreach ( $locations as $loc ) : ?>
                            <option value="<?php echo esc_attr( $loc ); ?>"><?php echo esc_html( $loc ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="enamel-field-error" id="enamel-location-error-<?php echo $n; ?>" aria-live="polite"></span>
                </div>

                <!-- Insurance combobox -->
                <div class="enamel-form-field">
                    <label for="enamel-insurance-input-<?php echo $n; ?>" class="enamel-label">Insurance Provider</label>
                    <div class="enamel-combobox enamel-combobox--disabled" id="insurance-combobox-<?php echo $n; ?>">
                        <input
                            type="text"
                            id="enamel-insurance-input-<?php echo $n; ?>"
                            class="enamel-input enamel-combobox-input"
                            placeholder="Select a location first&hellip;"
                            autocomplete="off"
                            disabled
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="enamel-insurance-listbox-<?php echo $n; ?>"
                        >
                        <button
                            type="button"
                            class="enamel-combobox-toggle"
                            aria-label="Toggle insurance list"
                            disabled
                        >&#9660;</button>
                        <ul
                            class="enamel-combobox-list"
                            id="enamel-insurance-listbox-<?php echo $n; ?>"
                            role="listbox"
                            aria-label="Insurance providers"
                            style="display:none;"
                        ></ul>
                    </div>
                    <input type="hidden" name="insurance" id="enamel-insurance-value-<?php echo $n; ?>">
                    <span class="enamel-field-error" id="enamel-insurance-error-<?php echo $n; ?>" aria-live="polite"></span>
                    <div id="enamel-other-insurance-wrap-<?php echo $n; ?>" style="display:none;margin-top:10px;">
                        <input type="text" id="enamel-other-insurance-<?php echo $n; ?>" name="insurance_other" class="enamel-input"
                            placeholder="Please describe your insurance plan…" autocomplete="off">
                        <span class="enamel-field-error" id="enamel-other-insurance-error-<?php echo $n; ?>" aria-live="polite"></span>
                    </div>
                </div>

                <!-- Nonce -->
                <input type="hidden" class="enamel-nonce" value="<?php echo esc_attr( wp_create_nonce( 'enamel_if_nonce' ) ); ?>">

                <!-- Submit -->
                <div class="enamel-form-field enamel-form-field--submit">
                    <button type="submit" id="enamel-submit-btn-<?php echo $n; ?>" class="enamel-submit-btn">
                        <span class="enamel-btn-label">Check My Insurance</span>
                        <span class="enamel-btn-spinner" aria-hidden="true" style="display:none;"></span>
                    </button>
                    <span class="enamel-field-error" id="enamel-submit-error-<?php echo $n; ?>" aria-live="polite"></span>
                </div>

            </form>

            <!-- Result area -->
            <div id="enamel-form-result-<?php echo $n; ?>" class="enamel-result" style="display:none;" aria-live="polite"></div>
        </div><!-- /.enamel-form-body -->

    </div><!-- /.enamel-form-card -->
</div><!-- /.enamel-form-wrapper -->
