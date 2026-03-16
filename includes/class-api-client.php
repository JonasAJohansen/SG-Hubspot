<?php
/**
 * Håndterer HTTP-kommunikasjon mot HubSpot.
 *
 * Konfig-prioritet:
 *   1. wp-config.php-konstanter (HS_WOOS_ENDPOINT, HS_WOOS_API_TOKEN)
 *   2. wp_options (satt via admin-siden)
 */

defined( 'ABSPATH' ) || exit;

class HS_WooS_Api_Client {

    private static function get_endpoint(): string {
        if ( defined( 'HS_WOOS_ENDPOINT' ) ) {
            return HS_WOOS_ENDPOINT;
        }
        return (string) get_option( 'hs_woos_endpoint', 'https://joinevent.no/mock/?route=ingest' );
    }

    private static function get_token(): string {
        if ( defined( 'HS_WOOS_API_TOKEN' ) ) {
            return HS_WOOS_API_TOKEN;
        }
        return (string) get_option( 'hs_woos_api_token', '' );
    }

    /**
     * @param array $payload
     * @return array|WP_Error
     */
    public static function send_order( array $payload ) {

        $response = wp_remote_post(
            self::get_endpoint(),
            [
                'timeout'     => 15,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . self::get_token(),
                ],
                'body'        => wp_json_encode( $payload ),
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return new WP_Error(
                'hs_api_http_error',
                sprintf( 'HTTP %d: %s', $status_code, wp_strip_all_tags( wp_remote_retrieve_body( $response ) ) ),
                [ 'status' => $status_code ]
            );
        }

        return [ 'ok' => true, 'response_body' => wp_remote_retrieve_body( $response ) ];
    }
}
