<?php
/**
 * Email sending via Resend API for Enamel Insurance Form.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Enamel_Email_Sender {

    /**
     * Send a branded confirmation email to the patient.
     *
     * @param  string $to_email   Patient email address.
     * @param  string $to_name    Patient full name.
     * @param  string $location   Enamel Dentistry location name.
     * @param  string $insurance  Insurance provider name.
     * @param  bool   $accepted   Whether the insurance is accepted.
     * @return true|WP_Error
     */
    public function send_patient_confirmation( $to_email, $to_name, $location, $insurance, $accepted ) {
        $api_key    = enamel_if_config( 'EMAIL_API_KEY' );
        $from_email = enamel_if_config( 'FROM_EMAIL' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_api_key', 'Resend API key is not configured.' );
        }

        if ( empty( $from_email ) ) {
            $from_email = 'noreply@enameldentistry.com';
        }

        $subject = 'Your Insurance Check Result — Enamel Dentistry';
        $html    = $this->build_html( $to_name, $location, $insurance, $accepted );

        $payload = array(
            'from'    => 'Enamel Dentistry <' . $from_email . '>',
            'to'      => array( $to_name . ' <' . $to_email . '>' ),
            'subject' => $subject,
            'html'    => $html,
        );

        $response = wp_remote_post( 'https://api.resend.com/emails', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'resend_api_error',
                sprintf( 'Resend API returned HTTP %d: %s', $code, $body )
            );
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build the branded HTML email body.
     *
     * @param  string $to_name
     * @param  string $location
     * @param  string $insurance
     * @param  bool   $accepted
     * @return string
     */
    private function build_html( $to_name, $location, $insurance, $accepted ) {
        $first_name = esc_html( strstr( $to_name, ' ', true ) ?: $to_name );
        $location   = esc_html( $location );
        $insurance  = esc_html( $insurance );

        if ( $accepted ) {
            $result_color   = '#7D55C7';
            $result_bg      = '#EDE9FF';
            $result_icon    = '&#10003;';
            $result_label   = 'Insurance Accepted';
            $headline       = 'Great news, ' . $first_name . '!';
            $body_text      = 'We accept <strong>' . $insurance . '</strong> at <strong>' . $location . '</strong>. '
                            . 'Our team will be reaching out shortly to help you schedule your first appointment. '
                            . 'We can\'t wait to meet you!';
            $cta_text       = '';
        } else {
            $result_color   = '#E56B10';
            $result_bg      = '#fff8f5';
            $result_icon    = '&#9432;';
            $result_label   = 'Insurance Not Currently Listed';
            $headline       = 'Thanks for reaching out, ' . $first_name . '.';
            $body_text      = 'We don\'t currently list <strong>' . $insurance . '</strong> at <strong>' . $location . '</strong>, '
                            . 'but don\'t worry — our team will personally reach out to you to explore your options and find the '
                            . 'best path forward. We\'re committed to making dental care accessible for everyone.';
            $cta_text       = '';
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Insurance Check Result &mdash; Enamel Dentistry</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f2fa;font-family:Helvetica,Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f2fa;padding:32px 0;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(35,25,66,0.10);">

          <!-- Header -->
          <tr>
            <td style="background-color:#231942;padding:32px 40px;text-align:center;">
              <h1 style="margin:0 0 6px 0;font-family:Montserrat,Helvetica,Arial,sans-serif;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">
                Enamel Dentistry
              </h1>
              <p style="margin:0;font-family:Rubik,Helvetica,Arial,sans-serif;font-size:13px;font-weight:400;color:rgba(255,255,255,0.65);letter-spacing:1.5px;text-transform:uppercase;">
                Insurance Verification
              </p>
            </td>
          </tr>

          <!-- Result badge -->
          <tr>
            <td style="padding:0 40px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background-color:<?php echo $result_bg; ?>;border-left:4px solid <?php echo $result_color; ?>;border-radius:0 8px 8px 0;padding:16px 20px;margin-top:32px;display:block;">
                    <p style="margin:0;font-family:Montserrat,Helvetica,Arial,sans-serif;font-size:14px;font-weight:700;color:<?php echo $result_color; ?>;letter-spacing:0.5px;">
                      <?php echo $result_icon; ?>&nbsp;&nbsp;<?php echo $result_label; ?>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:28px 40px 0 40px;">
              <h2 style="margin:0 0 14px 0;font-family:Montserrat,Helvetica,Arial,sans-serif;font-size:20px;font-weight:700;color:#231942;">
                <?php echo $headline; ?>
              </h2>
              <p style="margin:0 0 20px 0;font-family:Rubik,Helvetica,Arial,sans-serif;font-size:15px;font-weight:400;color:#3d3558;line-height:1.65;">
                <?php echo $body_text; ?>
              </p>
            </td>
          </tr>

          <!-- Details table -->
          <tr>
            <td style="padding:0 40px 28px 40px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9f7fe;border-radius:10px;overflow:hidden;">
                <tr>
                  <td style="padding:14px 20px;border-bottom:1px solid #EDE9FF;">
                    <span style="font-family:Rubik,Helvetica,Arial,sans-serif;font-size:11px;font-weight:500;color:#7D55C7;text-transform:uppercase;letter-spacing:1px;">Location</span><br>
                    <span style="font-family:Rubik,Helvetica,Arial,sans-serif;font-size:15px;font-weight:400;color:#231942;"><?php echo $location; ?></span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:14px 20px;">
                    <span style="font-family:Rubik,Helvetica,Arial,sans-serif;font-size:11px;font-weight:500;color:#7D55C7;text-transform:uppercase;letter-spacing:1px;">Insurance Provider</span><br>
                    <span style="font-family:Rubik,Helvetica,Arial,sans-serif;font-size:15px;font-weight:400;color:#231942;"><?php echo $insurance; ?></span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <?php if ( $accepted ) : ?>
          <!-- CTA button for accepted -->
          <tr>
            <td style="padding:0 40px 32px 40px;text-align:center;">
              <a href="https://enameldentistry.com" style="display:inline-block;background-color:#E56B10;color:#ffffff;font-family:Montserrat,Helvetica,Arial,sans-serif;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:1px;text-decoration:none;padding:14px 32px;border-radius:8px;">
                Learn More About Us
              </a>
            </td>
          </tr>
          <?php endif; ?>

          <!-- Footer -->
          <tr>
            <td style="background-color:#231942;padding:24px 40px;text-align:center;">
              <p style="margin:0 0 6px 0;font-family:Rubik,Helvetica,Arial,sans-serif;font-size:13px;font-weight:400;color:rgba(255,255,255,0.6);">
                Questions? Call us or visit <a href="https://enameldentistry.com" style="color:#7D55C7;text-decoration:none;">enameldentistry.com</a>
              </p>
              <p style="margin:0;font-family:Rubik,Helvetica,Arial,sans-serif;font-size:11px;font-weight:300;color:rgba(255,255,255,0.35);">
                &copy; <?php echo gmdate( 'Y' ); ?> Enamel Dentistry. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
