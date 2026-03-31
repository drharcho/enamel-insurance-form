<?php
/**
 * Slack Block Kit notification for Enamel Insurance Form.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Enamel_Slack_Notifier {

    /**
     * Send a Slack notification about a new insurance inquiry.
     *
     * @param  array $data {
     *     @type string $name
     *     @type string $phone
     *     @type string $email
     *     @type string $location
     *     @type string $insurance
     *     @type bool   $accepted
     * }
     * @return true|WP_Error
     */
    public function notify( $data ) {
        $webhook_url = enamel_if_config( 'SLACK_WEBHOOK_URL' );

        if ( empty( $webhook_url ) ) {
            return new WP_Error( 'missing_webhook', 'Slack webhook URL is not configured.' );
        }

        $name      = isset( $data['name'] )      ? $data['name']      : '';
        $phone     = isset( $data['phone'] )     ? $data['phone']     : '';
        $email     = isset( $data['email'] )     ? $data['email']     : '';
        $location  = isset( $data['location'] )  ? $data['location']  : '';
        $insurance = isset( $data['insurance'] ) ? $data['insurance'] : '';
        $accepted  = isset( $data['accepted'] ) ? $data['accepted'] : false;
        $is_other  = ! empty( $data['other'] );

        if ( $is_other ) {
            $status_emoji = ':mag:';
            $status_label = 'Insurance Needs to Be Checked';
            $status_text  = 'This patient\'s insurance *needs to be verified* — please reach out to confirm coverage.';
            $status_color = '#E56B10';
        } elseif ( $accepted ) {
            $status_emoji = ':white_check_mark:';
            $status_label = 'Insurance Likely Accepted';
            $status_text  = 'This patient\'s insurance *is on our accepted list*. Please verify their details to confirm.';
            $status_color = '#7D55C7';
        } else {
            $status_emoji = ':x:';
            $status_label = 'Insurance Not Listed';
            $status_text  = 'This patient\'s insurance is *not currently listed* at this location. Please follow up.';
            $status_color = '#E56B10';
        }

        $blocks = array(
            // Header
            array(
                'type' => 'header',
                'text' => array(
                    'type'  => 'plain_text',
                    'text'  => ':tooth: New Insurance Inquiry',
                    'emoji' => true,
                ),
            ),
            // Patient details
            array(
                'type'   => 'section',
                'fields' => array(
                    array(
                        'type' => 'mrkdwn',
                        'text' => '*Name*' . "\n" . $this->escape( $name ),
                    ),
                    array(
                        'type' => 'mrkdwn',
                        'text' => '*Phone*' . "\n" . $this->escape( $phone ),
                    ),
                    array(
                        'type' => 'mrkdwn',
                        'text' => '*Email*' . "\n" . $this->escape( $email ),
                    ),
                    array(
                        'type' => 'mrkdwn',
                        'text' => '*Location*' . "\n" . $this->escape( $location ),
                    ),
                    array(
                        'type' => 'mrkdwn',
                        'text' => '*Insurance*' . "\n" . $this->escape( $insurance ),
                    ),
                ),
            ),
            // Status
            array(
                'type' => 'section',
                'text' => array(
                    'type'  => 'mrkdwn',
                    'text'  => $status_emoji . ' *' . $status_label . '*' . "\n" . $status_text,
                ),
            ),
            // Divider
            array(
                'type' => 'divider',
            ),
        );

        $payload = array(
            'blocks' => $blocks,
        );

        $response = wp_remote_post( $webhook_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== (int) $code ) {
            return new WP_Error(
                'slack_error',
                sprintf( 'Slack webhook returned HTTP %d: %s', $code, $body )
            );
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Escape special Slack mrkdwn characters in plain text.
     *
     * @param  string $text
     * @return string
     */
    private function escape( $text ) {
        $text = str_replace( '&', '&amp;', $text );
        $text = str_replace( '<', '&lt;', $text );
        $text = str_replace( '>', '&gt;', $text );
        return $text;
    }
}
