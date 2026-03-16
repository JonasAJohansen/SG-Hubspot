<?php
defined( 'ABSPATH' ) || exit;

class HS_WooS_Admin_Log {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function register_menu(): void {
        add_submenu_page(
            'woocommerce',
            'HubSpot Sync',
            'HubSpot Sync',
            'manage_woocommerce',
            'hs-woos-log',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings(): void {

        register_setting( 'hs_woos_settings_group', 'hs_woos_endpoint', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://joinevent.no/mock/?route=ingest',
        ] );

        register_setting( 'hs_woos_settings_group', 'hs_woos_api_token', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        add_settings_section( 'hs_woos_main_section', 'API-konfigurasjon', '__return_false', 'hs-woos-log' );

        add_settings_field( 'hs_woos_endpoint', 'Endepunkt-URL',      [ __CLASS__, 'field_endpoint' ], 'hs-woos-log', 'hs_woos_main_section' );
        add_settings_field( 'hs_woos_api_token', 'API-token (Bearer)', [ __CLASS__, 'field_token' ],   'hs-woos-log', 'hs_woos_main_section' );
    }

    public static function field_endpoint(): void {
        $value = esc_attr( get_option( 'hs_woos_endpoint', '' ) );
        echo '<input type="url" name="hs_woos_endpoint" value="' . $value . '" class="regular-text" />';
    }

    public static function field_token(): void {
        $value = esc_attr( get_option( 'hs_woos_api_token', '' ) );
        echo '<input type="text" name="hs_woos_api_token" value="' . $value . '" class="regular-text" autocomplete="off" />';
        $from_constant = defined( 'HS_WOOS_API_TOKEN' ) ? ' <strong>Token leses nå fra <code>wp-config.php</code>.</strong>' : '';
        echo '<p class="description">Lagres i ren tekst i databasen. For produksjon kan du definer <code>HS_WOOS_API_TOKEN</code> som konstant i <code>wp-config.php</code>, da brukes den i stedet for denne verdien.' . $from_constant . '</p>';
    }

    public static function render_page(): void {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Ingen tilgang.', 'hs-woos' ) );
        }

        self::maybe_clear_logs();

        $error_log = (array) get_option( 'hs_woos_error_log', [] );
        $sync_log  = (array) get_option( 'hs_woos_sync_log',  [] );

        ?>
        <div class="wrap">
            <h1>HubSpot Sync</h1>

            <h2>Innstillinger</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'hs_woos_settings_group' );
                do_settings_sections( 'hs-woos-log' );
                submit_button( 'Lagre' );
                ?>
            </form>

            <hr>

            <h2>Vellykkede synker – siste <?php echo count( $sync_log ); ?></h2>

            <?php if ( empty( $sync_log ) ) : ?>
                <p>Ingen vellykkede synker registrert ennå.</p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:80px;">Ordre-ID</th>
                            <th style="width:180px;">Tidspunkt (UTC)</th>
                            <th style="width:160px;">E-post</th>
                            <th style="width:140px;">Navn</th>
                            <th style="width:100px;">Beløp</th>
                            <th>Produkter</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sync_log as $entry ) : ?>
                            <?php
                            $oid     = intval( $entry['order_id'] ?? 0 );
                            $order   = $oid ? wc_get_order( $oid ) : null;
                            $link    = $order ? $order->get_edit_order_url() : '#';
                            $payload = $entry['payload'] ?? [];
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $link ); ?>">#<?php echo esc_html( $oid ); ?></a></td>
                                <td><?php echo esc_html( $entry['timestamp'] ?? '–' ); ?></td>
                                <td><?php echo esc_html( $payload['email'] ?? '–' ); ?></td>
                                <td><?php echo esc_html( ( $payload['first_name'] ?? '' ) . ' ' . ( $payload['last_name'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( ( $payload['total'] ?? '–' ) . ' ' . ( $payload['currency'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( implode( ', ', $payload['products'] ?? [] ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=hs-woos-log&clear_sync=1' ), 'hs_woos_clear_sync' ) ); ?>"
                       class="button"
                       onclick="return confirm('Tøm suksess-logg?');">Tøm</a>
                </p>
            <?php endif; ?>

            <hr>

            <h2>Feillogg – siste <?php echo count( $error_log ); ?></h2>

            <?php if ( empty( $error_log ) ) : ?>
                <p>Ingen feil registrert.</p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:80px;">Ordre-ID</th>
                            <th style="width:220px;">Tidspunkt (UTC)</th>
                            <th style="width:140px;">Feilkode</th>
                            <th>Feilmelding</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $error_log as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $entry['order_id'] ?? '–' ); ?></td>
                                <td><?php echo esc_html( $entry['timestamp'] ?? '–' ); ?></td>
                                <td><code><?php echo esc_html( $entry['code'] ?? '–' ); ?></code></td>
                                <td><?php echo esc_html( $entry['message'] ?? '–' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=hs-woos-log&clear_errors=1' ), 'hs_woos_clear_errors' ) ); ?>"
                       class="button"
                       onclick="return confirm('Tøm feillogg?');">Tøm</a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function maybe_clear_logs(): void {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( isset( $_GET['clear_errors'] ) ) {
            check_admin_referer( 'hs_woos_clear_errors' );
            update_option( 'hs_woos_error_log', [], false );
        }

        if ( isset( $_GET['clear_sync'] ) ) {
            check_admin_referer( 'hs_woos_clear_sync' );
            update_option( 'hs_woos_sync_log', [], false );
        }
    }
}
