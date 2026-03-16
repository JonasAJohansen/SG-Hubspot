<?php
defined( 'ABSPATH' ) || exit;

class HS_WooS_Sync_Handler {

    const META_SYNCED    = '_hs_synced';
    const META_SYNCED_AT = '_hs_synced_at';

    public static function init(): void {
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'handle_order' ], 10, 1 );
    }

    public static function handle_order( int $order_id ): void {

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof WC_Abstract_Order ) {
            return;
        }

        if ( $order->get_meta( self::META_SYNCED ) === '1' ) {
            return;
        }

        $payload = self::build_payload( $order );
        $result  = HS_WooS_Api_Client::send_order( $payload );

        if ( is_wp_error( $result ) ) {
            self::handle_error( $order_id, $result );
            return;
        }

        $synced_at = gmdate( 'c' );
        $order->update_meta_data( self::META_SYNCED,    '1' );
        $order->update_meta_data( self::META_SYNCED_AT, $synced_at );
        $order->save_meta_data();

        wc_get_logger()->info(
            sprintf(
                'Ordre #%d synket til HubSpot. Payload: %s | API-svar: %s',
                $order_id,
                wp_json_encode( $payload ),
                $result['response_body'] ?? ''
            ),
            [ 'source' => 'hs-woos' ]
        );

        self::append_sync_log( $order_id, $synced_at, $payload );
    }

    private static function build_payload( WC_Abstract_Order $order ): array {

        $products = [];
        foreach ( $order->get_items() as $item ) {
            $products[] = $item->get_name();
        }

        return [
            'order_id'   => $order->get_id(),
            'email'      => $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
            'total'      => $order->get_total(),
            'currency'   => $order->get_currency(),
            'date'       => $order->get_date_created()
                                ? $order->get_date_created()->date( 'c' )
                                : gmdate( 'c' ),
            'products'   => $products,
        ];
    }

    private static function append_sync_log( int $order_id, string $synced_at, array $payload ): void {
        $log = (array) get_option( 'hs_woos_sync_log', [] );
        array_unshift( $log, [
            'order_id'  => $order_id,
            'timestamp' => $synced_at,
            'payload'   => $payload,
        ] );
        update_option( 'hs_woos_sync_log', array_slice( $log, 0, 10 ), false );
    }

    private static function handle_error( int $order_id, WP_Error $error ): void {

        wc_get_logger()->error(
            sprintf( 'Feil ved synk av ordre #%d: [%s] %s', $order_id, $error->get_error_code(), $error->get_error_message() ),
            [ 'source' => 'hs-woos' ]
        );

        $log = (array) get_option( 'hs_woos_error_log', [] );
        array_unshift( $log, [
            'order_id'  => $order_id,
            'timestamp' => gmdate( 'c' ),
            'code'      => $error->get_error_code(),
            'message'   => $error->get_error_message(),
        ] );
        update_option( 'hs_woos_error_log', array_slice( $log, 0, 10 ), false );
    }
}
