<?php
/**
 * Google Sheets API v4 integration for Enamel Insurance Form.
 * Authenticates via Service Account JWT (RS256) — no Composer required.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Enamel_Google_Sheets {

    /**
     * @var string Google Sheets spreadsheet ID.
     */
    private $sheet_id;

    /**
     * @var array Parsed service account credentials.
     */
    private $credentials;

    /**
     * @param string $service_account_json  Raw JSON string from service account file.
     * @param string $sheet_id              Google Sheets spreadsheet ID.
     */
    public function __construct( $service_account_json, $sheet_id ) {
        $this->sheet_id    = $sheet_id;
        $this->credentials = json_decode( $service_account_json, true );
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Return a sorted array of insurance provider names for a given location.
     *
     * @param  string          $location
     * @return string[]|WP_Error
     */
    public function get_insurances_for_location( $location ) {
        $cache_key = 'enamel_ins_' . md5( strtolower( $location ) );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $range    = 'A:B';
        $url      = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
            rawurlencode( $this->sheet_id ),
            rawurlencode( $range )
        );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== (int) $code ) {
            return new WP_Error(
                'sheets_api_error',
                sprintf( 'Google Sheets API returned HTTP %d: %s', $code, $body )
            );
        }

        $data = json_decode( $body, true );
        if ( ! isset( $data['values'] ) || ! is_array( $data['values'] ) ) {
            return new WP_Error( 'sheets_parse_error', 'Could not parse Google Sheets response.' );
        }

        $rows      = $data['values'];
        $insurances = array();
        $first_row  = true;

        foreach ( $rows as $row ) {
            // Skip header row
            if ( $first_row ) {
                $first_row = false;
                continue;
            }

            // Must have at least two columns
            if ( ! isset( $row[0], $row[1] ) ) {
                continue;
            }

            if ( strtolower( trim( $row[0] ) ) === strtolower( trim( $location ) ) ) {
                $provider = trim( $row[1] );
                if ( $provider !== '' ) {
                    $insurances[] = $provider;
                }
            }
        }

        // Remove duplicates and sort
        $insurances = array_unique( $insurances );
        sort( $insurances );

        set_transient( $cache_key, $insurances, HOUR_IN_SECONDS );

        return $insurances;
    }

    /**
     * Check whether a specific insurance is accepted at a location.
     *
     * @param  string $location
     * @param  string $insurance
     * @return bool|WP_Error
     */
    public function check_insurance( $location, $insurance ) {
        $insurances = $this->get_insurances_for_location( $location );

        if ( is_wp_error( $insurances ) ) {
            return $insurances;
        }

        foreach ( $insurances as $provider ) {
            if ( strtolower( trim( $provider ) ) === strtolower( trim( $insurance ) ) ) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // JWT / OAuth
    // -----------------------------------------------------------------------

    /**
     * Obtain a Google OAuth2 access token using a service account JWT.
     * Caches the token in a transient for 55 minutes.
     *
     * @return string|WP_Error
     */
    private function get_access_token() {
        $transient_key = 'enamel_gsheets_token_' . md5( $this->credentials['client_email'] ?? '' );
        $cached        = get_transient( $transient_key );

        if ( false !== $cached ) {
            return $cached;
        }

        if ( empty( $this->credentials['client_email'] ) || empty( $this->credentials['private_key'] ) ) {
            return new WP_Error( 'missing_credentials', 'Service account client_email or private_key is missing.' );
        }

        $jwt = $this->build_jwt(
            $this->credentials['client_email'],
            $this->credentials['private_key']
        );

        if ( is_wp_error( $jwt ) ) {
            return $jwt;
        }

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== (int) $code || empty( $data['access_token'] ) ) {
            return new WP_Error(
                'token_exchange_failed',
                sprintf( 'OAuth token exchange failed (HTTP %d): %s', $code, $body )
            );
        }

        $token = $data['access_token'];

        // Cache for 55 minutes (token is valid 60 min)
        set_transient( $transient_key, $token, 55 * MINUTE_IN_SECONDS );

        return $token;
    }

    /**
     * Build a signed RS256 JWT for Google service account authentication.
     *
     * @param  string $client_email
     * @param  string $private_key  PEM-encoded private key.
     * @return string|WP_Error
     */
    private function build_jwt( $client_email, $private_key ) {
        $now = time();

        // Header
        $header = $this->base64url_encode( wp_json_encode( array(
            'alg' => 'RS256',
            'typ' => 'JWT',
        ) ) );

        // Claims
        $claims = $this->base64url_encode( wp_json_encode( array(
            'iss'   => $client_email,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ) ) );

        $signing_input = $header . '.' . $claims;

        // Sign
        $signature = '';
        $result    = openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 );

        if ( ! $result ) {
            return new WP_Error( 'jwt_sign_failed', 'Failed to sign JWT with service account private key.' );
        }

        return $signing_input . '.' . $this->base64url_encode( $signature );
    }

    /**
     * Base64URL encode (RFC 4648 §5) — replaces +, /, strips =.
     *
     * @param  string $data
     * @return string
     */
    private function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }
}
