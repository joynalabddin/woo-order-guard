<?php
/**
 * DevJoynal licensing client for WooCommerce Order Guard.
 *
 * The client talks to a seller-controlled license service. Seller secrets must
 * remain on that service and must never be shipped inside the plugin ZIP.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DevJoynal_DJOG_License {
    private static ?self $instance = null;
    private string $option = 'djog_license';

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ], 20 );
        add_action( 'admin_notices', [ $this, 'admin_notice' ] );
        add_action( 'admin_post_djog_activate_license', [ $this, 'activate_license' ] );
        add_action( 'admin_post_djog_enable_free_mode', [ $this, 'enable_free_mode' ] );
        add_action( 'admin_post_djog_deactivate_license', [ $this, 'deactivate_license' ] );
        add_action( 'admin_post_djog_refresh_license', [ $this, 'refresh_license' ] );
        add_action( 'djog_daily_license_check', [ $this, 'scheduled_check' ] );
        register_activation_hook( DJOG_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( DJOG_FILE, [ $this, 'deactivate' ] );
    }

    public function activate(): void {
        if ( ! wp_next_scheduled( 'djog_daily_license_check' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'djog_daily_license_check' );
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'djog_daily_license_check' );
    }

    public function admin_menu(): void {
        add_submenu_page(
            'djog-dashboard',
            'License',
            'License',
            'manage_woocommerce',
            'djog-license',
            [ $this, 'page' ]
        );
    }

    public function page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'woo-order-guard' ) );
        }
        $state = $this->state();
        $configured = $this->configured();
        $free_mode = $this->is_free_mode();
        $message = isset( $_GET['djog_license_message'] ) ? sanitize_text_field( wp_unslash( $_GET['djog_license_message'] ) ) : '';
        $notice_type = 'error' === ( $_GET['djog_license_type'] ?? '' ) ? 'notice-error' : 'notice-success';
        ?>
        <div class="wrap djog-wrap">
            <div class="djog-header"><div><span class="djog-kicker">DEVJOYNAL LICENSE</span><h1>Product license</h1><p>Choose Free/Demo mode or activate a paid product key.</p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=djog-dashboard' ) ); ?>">View dashboard</a></div>
            <?php if ( $message ) : ?><div class="notice <?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>
            <div class="djog-grid">
                <div class="djog-panel">
                    <h2>License status</h2>
                    <div class="djog-license-status <?php echo esc_attr( $this->status_class( $state ) ); ?>"><strong><?php echo esc_html( $this->status_label( $state ) ); ?></strong><span><?php echo esc_html( $state['message'] ?? 'No license has been activated on this site.' ); ?></span></div>
                    <dl class="djog-license-meta"><dt>Product</dt><dd><?php echo esc_html( DJOG_LICENSE_PRODUCT ); ?></dd><dt>Domain</dt><dd><?php echo esc_html( ! empty( $state['domain'] ) ? $state['domain'] : $this->domain() ); ?></dd><dt>Last checked</dt><dd><?php echo esc_html( ! empty( $state['last_checked'] ) ? $state['last_checked'] : 'Never' ); ?></dd><dt>Activation limit</dt><dd><?php echo esc_html( ! empty( $state['activation_limit'] ) ? $state['activation_limit'] : 'Configured by license service' ); ?></dd></dl>
                    <?php if ( $free_mode ) : ?><p class="description">Free/Demo mode is active on this domain. No product key is required.</p><?php elseif ( ! $configured ) : ?><p class="description">Paid license service is not configured. You can use Free/Demo mode now, or define <code>DJOG_CUSTOM_LICENSE_API_URL</code> and <code>DJOG_CUSTOM_LICENSE_PRODUCT_ID</code> in <code>wp-config.php</code> for paid activation.</p><?php endif; ?>
                </div>
                <div class="djog-panel">
                    <h2>Product activation</h2>
                    <p>Use the product key issued by your seller. The raw key is encrypted at rest and is never displayed after saving.</p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="djog_activate_license">
                        <?php wp_nonce_field( 'djog_activate_license' ); ?>
                        <label class="djog-field">Product license key<input type="password" name="license_key" autocomplete="off" required placeholder="XXXX-XXXX-XXXX-XXXX"></label>
                        <button class="button button-primary button-large" type="submit" <?php disabled( ! $configured ); ?>>Activate paid license</button>
                    </form>
                    <?php if ( ! $free_mode && $this->has_license_key() ) : ?>
                        <div class="djog-license-actions"><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=djog_refresh_license' ), 'djog_refresh_license' ) ); ?>">Refresh status</a> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=djog_deactivate_license' ), 'djog_deactivate_license' ) ); ?>" onclick="return confirm('Deactivate this site license?');">Deactivate site</a></div>
                    <?php endif; ?>
                    <div class="djog-license-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="djog_enable_free_mode"><?php wp_nonce_field( 'djog_enable_free_mode' ); ?><button class="button" type="submit">Use Free/Demo mode</button></form></div>
                </div>
            </div>
            <div class="djog-panel"><h2>How licensing works</h2><p>Free/Demo mode activates the plugin locally without a product key. Paid mode sends only the product key, product identifier, current domain and version to your configured seller license service. Seller secrets stay on that service. If the license service is temporarily unavailable, the plugin keeps the last active state during the configured grace period and never makes checkout depend on a live remote request.</p><p class="djog-footer">Developer: <strong>Joynal Abdin</strong> · <a href="https://devjoynal.com" target="_blank" rel="noopener">DevJoynal</a></p></div>
        </div>
        <?php
    }

    public function admin_notice(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! $this->configured() || ! DJOG_LICENSE_REQUIRED || $this->is_active() || $this->is_free_mode() || wp_doing_ajax() ) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>WooCommerce Order Guard:</strong> license is not active for this site. <a href="' . esc_url( admin_url( 'admin.php?page=djog-license' ) ) . '">Open License settings</a>.</p></div>';
    }

    public function activate_license(): void {
        $this->guard_request( 'djog_activate_license' );
        $license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
        if ( ! preg_match( '/^[a-z0-9-]{8,128}$/i', $license_key ) ) {
            $this->redirect( 'Enter a valid product license key.', 'error' );
        }
        $result = $this->request( 'activate', $license_key );
        $this->redirect( $result['message'], $result['success'] ? 'success' : 'error' );
    }

    public function enable_free_mode(): void {
        $this->guard_request( 'djog_enable_free_mode' );
        $current_key = $this->stored_license_key();
        if ( $current_key !== '' && $this->configured() ) {
            $this->request( 'deactivate', $current_key );
        }
        $this->update_state( [
            'status' => 'free',
            'mode' => 'free',
            'message' => 'Free/Demo mode is active on this domain.',
            'domain' => $this->domain(),
            'last_checked' => current_time( 'mysql', true ),
            'expires_at' => '',
            'encrypted_license_key' => '',
            'encrypted_purchase_code' => '',
            'license_hash' => '',
        ] );
        $this->redirect( 'Free/Demo mode is now active. No license key is required.', 'success' );
    }

    public function deactivate_license(): void {
        $this->guard_request( 'djog_deactivate_license' );
        $license_key = $this->stored_license_key();
        if ( $license_key !== '' && $this->configured() ) {
            $this->request( 'deactivate', $license_key );
        }
        delete_option( $this->option );
        $this->redirect( 'This site license has been deactivated.', 'success' );
    }

    public function refresh_license(): void {
        $this->guard_request( 'djog_refresh_license' );
        $license_key = $this->stored_license_key();
        $result = $license_key !== '' ? $this->request( 'check', $license_key ) : [ 'success' => false, 'message' => 'No product key is stored on this site.' ];
        $this->redirect( $result['message'], $result['success'] ? 'success' : 'error' );
    }

    public function scheduled_check(): void {
        $license_key = $this->stored_license_key();
        if ( $license_key !== '' && $this->configured() ) {
            $this->request( 'check', $license_key );
        }
    }

    private function request( string $action, string $license_key ): array {
        if ( ! $this->configured() ) {
            return [ 'success' => false, 'message' => 'License service is not configured.' ];
        }
        $response = wp_safe_remote_post( DJOG_LICENSE_API_URL, [
            'timeout' => 12,
            'headers' => [ 'Accept' => 'application/json', 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode( [
                'api_version' => '1',
                'action' => $action,
                'product_id' => DJOG_LICENSE_PRODUCT_ID,
                'license_key' => $license_key,
                'domain' => $this->domain(),
                'site_url' => home_url( '/' ),
                'plugin_version' => DJOG_VERSION,
                'wordpress_version' => get_bloginfo( 'version' ),
                'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
            ] ),
            'user-agent' => 'WooCommerce Order Guard/' . DJOG_VERSION . '; ' . home_url( '/' ),
        ] );
        if ( is_wp_error( $response ) ) {
            return $this->remote_failure( $response->get_error_message() );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( $code === 429 ) {
            return $this->remote_failure( 'License service rate limit reached. Please try again later.' );
        }
        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            return $this->remote_failure( 'License service returned an invalid response.' );
        }
        if ( empty( $data['success'] ) ) {
            $message = sanitize_text_field( $data['message'] ?? 'License activation was rejected.' );
            $this->update_state( [ 'status' => 'invalid', 'message' => $message, 'last_checked' => current_time( 'mysql', true ) ] );
            return [ 'success' => false, 'message' => $message ];
        }
        $state = [
            'status' => sanitize_key( $data['status'] ?? 'active' ),
            'message' => sanitize_text_field( $data['message'] ?? 'License is active.' ),
            'domain' => $this->domain(),
            'last_checked' => current_time( 'mysql', true ),
            'expires_at' => sanitize_text_field( $data['expires_at'] ?? '' ),
            'activation_limit' => absint( $data['activation_limit'] ?? 0 ),
            'activations_used' => absint( $data['activations_used'] ?? 0 ),
            'grace_until' => time() + ( 14 * DAY_IN_SECONDS ),
            'encrypted_license_key' => $this->encrypt( $license_key ),
            'encrypted_purchase_code' => '',
            'license_hash' => hash_hmac( 'sha256', $license_key, wp_salt( 'auth' ) ),
        ];
        $this->update_state( $state );
        return [ 'success' => true, 'message' => $state['message'] ];
    }

    private function remote_failure( string $message ): array {
        $state = $this->state();
        if ( $this->is_active() && ! empty( $state['grace_until'] ) && time() < (int) $state['grace_until'] ) {
            $state['message'] = 'License service temporarily unavailable; active grace period is in effect.';
            $state['last_checked'] = current_time( 'mysql', true );
            $this->update_state( $state );
            return [ 'success' => true, 'message' => $state['message'] ];
        }
        $this->update_state( [ 'status' => 'unknown', 'message' => $message, 'last_checked' => current_time( 'mysql', true ) ] );
        return [ 'success' => false, 'message' => $message ];
    }

    private function configured(): bool {
        return DJOG_LICENSE_API_URL !== '' && DJOG_LICENSE_PRODUCT_ID !== '';
    }

    private function state(): array {
        $state = get_option( $this->option, [] );
        return is_array( $state ) ? $state : [];
    }

    private function update_state( array $changes ): void {
        update_option( $this->option, array_merge( $this->state(), $changes ), false );
    }

    private function stored_license_key(): string {
        $state = $this->state();
        $encrypted = (string) ( $state['encrypted_license_key'] ?? $state['encrypted_purchase_code'] ?? '' );
        return $this->decrypt( $encrypted );
    }

    private function has_license_key(): bool {
        return $this->stored_license_key() !== '';
    }

    public function is_active(): bool {
        $state = $this->state();
        return ( $state['status'] ?? '' ) === 'active' && ( empty( $state['expires_at'] ) || strtotime( (string) $state['expires_at'] ) > time() );
    }

    public function is_free_mode(): bool {
        return ( $this->state()['mode'] ?? '' ) === 'free' || ( $this->state()['status'] ?? '' ) === 'free';
    }

    private function status_label( array $state ): string {
        if ( $this->is_active() ) {
            return 'Active';
        }
        if ( $this->is_free_mode() ) {
            return 'Free/Demo';
        }
        return ucfirst( sanitize_key( $state['status'] ?? 'inactive' ) );
    }

    private function status_class( array $state ): string {
        return $this->is_active() || $this->is_free_mode() ? 'is-active' : ( ( $state['status'] ?? '' ) === 'unknown' ? 'is-unknown' : 'is-inactive' );
    }

    private function domain(): string {
        $host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        return is_string( $host ) ? strtolower( $host ) : '';
    }

    private function guard_request( string $action ): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'woo-order-guard' ), 403 );
        }
        check_admin_referer( $action );
    }

    private function redirect( string $message, string $type ): void {
        wp_safe_redirect( add_query_arg( [ 'page' => 'djog-license', 'djog_license_message' => $message, 'djog_license_type' => $type ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function encryption_key(): string {
        return hash( 'sha256', wp_salt( 'auth' ) . '|' . home_url( '/' ), true );
    }

    private function encrypt( string $value ): string {
        if ( $value === '' || ! function_exists( 'openssl_encrypt' ) ) {
            return '';
        }
        $iv = random_bytes( 16 );
        $cipher = openssl_encrypt( $value, 'AES-256-CBC', $this->encryption_key(), OPENSSL_RAW_DATA, $iv );
        return $cipher === false ? '' : base64_encode( $iv . $cipher );
    }

    private function decrypt( string $value ): string {
        if ( $value === '' || ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }
        $raw = base64_decode( $value, true );
        if ( ! is_string( $raw ) || strlen( $raw ) <= 16 ) {
            return '';
        }
        $plain = openssl_decrypt( substr( $raw, 16 ), 'AES-256-CBC', $this->encryption_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
        return is_string( $plain ) ? $plain : '';
    }
}

DevJoynal_DJOG_License::instance();
