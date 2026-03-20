<?php
/**
 * Template: Insurance Check Form
 *
 * Available variables:
 *   $locations  array  List of location name strings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="enamel-form-wrapper">
    <div class="enamel-form-card">

        <!-- Header -->
        <div class="enamel-form-header">
            <h2>Check Your Insurance</h2>
            <p>Find out if we accept your insurance at your nearest location.</p>
        </div>

        <!-- Form body -->
        <div class="enamel-form-body">
            <form id="enamel-insurance-form" novalidate>

                <!-- Name -->
                <div class="enamel-form-field">
                    <label for="enamel-name" class="enamel-label">Full Name</label>
                    <input
                        type="text"
                        id="enamel-name"
                        name="name"
                        class="enamel-input"
                        placeholder="Jane Smith"
                        autocomplete="name"
                    >
                    <span class="enamel-field-error" id="enamel-name-error" aria-live="polite"></span>
                </div>

                <!-- Phone -->
                <div class="enamel-form-field">
                    <label for="enamel-phone" class="enamel-label">Phone Number</label>
                    <input
                        type="tel"
                        id="enamel-phone"
                        name="phone"
                        class="enamel-input"
                        placeholder="(512) 555-0100"
                        autocomplete="tel"
                    >
                    <span class="enamel-field-error" id="enamel-phone-error" aria-live="polite"></span>
                </div>

                <!-- Email -->
                <div class="enamel-form-field">
                    <label for="enamel-email" class="enamel-label">Email Address</label>
                    <input
                        type="email"
                        id="enamel-email"
                        name="email"
                        class="enamel-input"
                        placeholder="jane@example.com"
                        autocomplete="email"
                    >
                    <span class="enamel-field-error" id="enamel-email-error" aria-live="polite"></span>
                </div>

                <!-- Location -->
                <div class="enamel-form-field">
                    <label for="enamel-location" class="enamel-label">Location</label>
                    <select id="enamel-location" name="location" class="enamel-input enamel-select">
                        <option value="">— Select a location —</option>
                        <?php foreach ( $locations as $loc ) : ?>
                            <option value="<?php echo esc_attr( $loc ); ?>"><?php echo esc_html( $loc ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="enamel-field-error" id="enamel-location-error" aria-live="polite"></span>
                </div>

                <!-- Insurance combobox -->
                <div class="enamel-form-field">
                    <label for="enamel-insurance-input" class="enamel-label">Insurance Provider</label>
                    <div class="enamel-combobox enamel-combobox--disabled" id="insurance-combobox">
                        <input
                            type="text"
                            id="enamel-insurance-input"
                            class="enamel-input enamel-combobox-input"
                            placeholder="Select a location first&hellip;"
                            autocomplete="off"
                            disabled
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-controls="enamel-insurance-listbox"
                        >
                        <button
                            type="button"
                            class="enamel-combobox-toggle"
                            aria-label="Toggle insurance list"
                            disabled
                        >&#9660;</button>
                        <ul
                            class="enamel-combobox-list"
                            id="enamel-insurance-listbox"
                            role="listbox"
                            aria-label="Insurance providers"
                            style="display:none;"
                        ></ul>
                    </div>
                    <input type="hidden" name="insurance" id="enamel-insurance-value">
                    <span class="enamel-field-error" id="enamel-insurance-error" aria-live="polite"></span>
                </div>

                <!-- Nonce -->
                <input type="hidden" id="enamel-nonce" value="<?php echo esc_attr( wp_create_nonce( 'enamel_if_nonce' ) ); ?>">

                <!-- Submit -->
                <div class="enamel-form-field enamel-form-field--submit">
                    <button type="submit" id="enamel-submit-btn" class="enamel-submit-btn">
                        <span class="enamel-btn-label">Check My Insurance</span>
                        <span class="enamel-btn-spinner" aria-hidden="true" style="display:none;"></span>
                    </button>
                    <span class="enamel-field-error" id="enamel-submit-error" aria-live="polite"></span>
                </div>

            </form>

            <!-- Result area -->
            <div id="enamel-form-result" class="enamel-result" style="display:none;" aria-live="polite"></div>
        </div><!-- /.enamel-form-body -->

    </div><!-- /.enamel-form-card -->
</div><!-- /.enamel-form-wrapper -->
