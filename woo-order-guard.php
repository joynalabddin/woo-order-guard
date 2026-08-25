<?php
/**
 * Plugin Name: WooCommerce Order Guard by DevJoynal
 * Plugin URI:  https://devjoynal.com
 * Description: Bangladesh-ready fake, duplicate and multiple-order protection for WooCommerce with phone normalization, IP/email rules, whitelist, styled messages and audit logs.
 * Version:     1.0.2
 * Author:      Joynal Abdin
 * Author URI:  https://devjoynal.com
 * License:     GPL-2.0-or-later
 * Update URI:  https://github.com/joynalabddin/woo-order-guard
 * Requires PHP: 8.3
 * Requires at least: 7.0
 * Requires Plugins: woocommerce
 * Text Domain: woo-order-guard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DJOG_VERSION', '1.1.0' );
define( 'DJOG_DB_VERSION', '1.1.0' );
define( 'DJOG_FILE', __FILE__ );
define( 'DJOG_DIR', plugin_dir_path( __FILE__ ) );
define( 'DJOG_URL', plugin_dir_url( __FILE__ ) );

final class DevJoynal_Woo_Order_Guard {
    private static ?self $instance = null;
    private string $table;
    private string $option = 'djog_settings';

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'djog_security_logs';

        register_activation_hook( DJOG_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( DJOG_FILE, [ $this, 'deactivate' ] );

        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
        add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
        add_action( 'djog_daily_cleanup', [ $this, 'cleanup_logs' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );
        add_action( 'admin_notices', [ $this, 'dependency_notice' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( DJOG_FILE ), [ $this, 'action_links' ] );

        add_action( 'admin_post_djog_save_settings', [ $this, 'save_settings' ] );
        add_action( 'admin_post_djog_clear_logs', [ $this, 'clear_logs' ] );
        add_action( 'admin_post_djog_cleanup_logs', [ $this, 'manual_cleanup_logs' ] );
        add_action( 'admin_post_djog_export_logs', [ $this, 'export_logs' ] );

        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_classic_checkout' ], 10, 2 );
        add_action( 'woocommerce_store_api_checkout_errors', [ $this, 'validate_store_api_checkout' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order', [ $this, 'attach_order_meta' ], 10, 2 );

        add_action( 'admin_init', [ $this, 'privacy_policy_content' ] );
        add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
        add_filter( 'rest_request_before_callbacks', [ $this, 'validate_store_api_request' ], 10, 3 );
        add_filter( 'rest_pre_dispatch', [ $this, 'validate_store_api_pre_dispatch' ], 10, 3 );
        add_filter( 'woocommerce_product_is_visible', [ $this, 'respect_product_visibility' ], 10, 2 );
        add_action( 'woocommerce_check_cart_items', [ $this, 'validate_excluded_products_cart' ] );
    }

    public function declare_hpos_compatibility(): void {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DJOG_FILE, true );
        }
    }

    public function activate(): void {
        $this->create_table();
        update_option( 'djog_db_version', DJOG_DB_VERSION, false );
        update_option( $this->option, wp_parse_args( get_option( $this->option, [] ), $this->defaults() ), false );
        if ( ! wp_next_scheduled( 'djog_daily_cleanup' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'djog_daily_cleanup' );
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook( 'djog_daily_cleanup' );
    }

    public function cleanup_logs(): void {
        global $wpdb;
        $days = max( 1, min( 3650, absint( $this->settings()['log_retention_days'] ?? 90 ) ) );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE created_at < %s", $cutoff ) );
    }

    public function maybe_upgrade(): void {
        if ( get_option( 'djog_db_version' ) !== DJOG_DB_VERSION ) {
            $this->activate();
        }
    }

    private function create_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_key CHAR(64) NOT NULL DEFAULT '',
            reason VARCHAR(40) NOT NULL DEFAULT '',
            phone VARCHAR(32) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY reason_created (reason, created_at),
            KEY phone_created (phone, created_at),
            KEY email_created (email, created_at),
            KEY ip_created (ip_address, created_at),
            KEY event_key (event_key)
        ) {$charset};";
        dbDelta( $sql );
    }

    private function defaults(): array {
        return [
            'enabled'              => 'yes',
            'validate_mobile'      => 'yes',
            'mobile_mode'          => 'bd',
            'check_phone'          => 'yes',
            'check_email'          => 'yes',
            'check_ip'             => 'yes',
            'window_minutes'       => 1440,
            'max_orders'           => 1,
            'statuses'             => [ 'pending', 'processing', 'on-hold', 'completed' ],
            'whitelist_phones'     => '',
            'whitelist_emails'     => '',
            'error_message'        => 'দুঃখিত, এই তথ্য দিয়ে সাম্প্রতিক একটি অর্ডার পাওয়া গেছে। অনুগ্রহ করে {{window}} মিনিট পরে চেষ্টা করুন অথবা আমাদের সাথে যোগাযোগ করুন।',
            'invalid_phone_message' => 'অনুগ্রহ করে সঠিক ১১ ডিজিটের বাংলাদেশি মোবাইল নম্বর দিন।',
            'icon'                 => 'shield',
            'text_color'           => '#172033',
            'background_color'     => '#fff7ed',
            'border_color'         => '#f97316',
            'border_radius'        => 12,
            'font_size'            => 15,
            'log_retention_days'   => 90,
            'blocked_retry_cooldown' => 60,
            'excluded_product_ids' => '',
        ];
    }

    private function settings(): array {
        $stored = get_option( $this->option, [] );
        return wp_parse_args( is_array( $stored ) ? $stored : [], $this->defaults() );
    }

    private function bool_setting( string $key ): bool {
        return 'yes' === (string) ( $this->settings()[ $key ] ?? 'no' );
    }

    public function dependency_notice(): void {
        if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>WooCommerce Order Guard:</strong> WooCommerce must be installed and active before checkout protection can run.</p></div>';
    }

    public function action_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=djog-settings' ) ) . '">Settings</a>' );
        return $links;
    }

    public function admin_menu(): void {
        add_menu_page(
            'Order Guard',
            'Order Guard',
            'manage_woocommerce',
            'djog-dashboard',
            [ $this, 'dashboard_page' ],
            'dashicons-shield-alt',
            56
        );
        add_submenu_page( 'djog-dashboard', 'Dashboard & Logs', 'Dashboard & Logs', 'manage_woocommerce', 'djog-dashboard', [ $this, 'dashboard_page' ] );
        add_submenu_page( 'djog-dashboard', 'Protection Settings', 'Settings', 'manage_woocommerce', 'djog-settings', [ $this, 'settings_page' ] );
    }

    public function admin_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'toplevel_page_djog-dashboard', 'order-guard_page_djog-settings' ], true ) ) {
            return;
        }
        wp_enqueue_style( 'djog-admin', DJOG_URL . 'assets/admin.css', [], DJOG_VERSION );
        wp_enqueue_script( 'djog-admin', DJOG_URL . 'assets/admin.js', [], DJOG_VERSION, true );
    }

    public function frontend_assets(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
            return;
        }
        wp_enqueue_style( 'djog-frontend', DJOG_URL . 'assets/frontend.css', [], DJOG_VERSION );
        wp_add_inline_style( 'djog-frontend', $this->frontend_css() );
        wp_enqueue_script( 'djog-frontend', DJOG_URL . 'assets/frontend.js', [ 'jquery' ], DJOG_VERSION, true );
        wp_localize_script( 'djog-frontend', 'djogFrontend', [
            'phoneField' => '#billing_phone, #shipping_phone',
            'message'    => esc_html( $this->settings()['invalid_phone_message'] ),
        ] );
    }

    private function frontend_css(): string {
        $s = $this->settings();
        $color = sanitize_hex_color( $s['text_color'] ) ?: '#172033';
        $bg = sanitize_hex_color( $s['background_color'] ) ?: '#fff7ed';
        $border = sanitize_hex_color( $s['border_color'] ) ?: '#f97316';
        $radius = max( 0, min( 32, absint( $s['border_radius'] ) ) );
        $size = max( 12, min( 24, absint( $s['font_size'] ) ) );
        return ".djog-checkout-error{color:{$color};background:{$bg};border-left:4px solid {$border};border-radius:{$radius}px;font-size:{$size}px;padding:14px 16px;margin:12px 0;line-height:1.55}.djog-checkout-error__icon{margin-right:8px;font-weight:700}.djog-phone-invalid{border-color:{$border}!important;box-shadow:0 0 0 1px {$border}}";
    }

    public function validate_classic_checkout( array $data, WP_Error $errors ): void {
        if ( ! $this->should_protect() ) {
            return;
        }
        $this->validate_payload( $data, $errors );
    }

    public function validate_store_api_pre_dispatch( $response, WP_REST_Server $server, WP_REST_Request $request ) {
        if ( ! $this->should_protect() || 'POST' !== strtoupper( $request->get_method() ) || ! preg_match( '#^/wc/store/v[0-9]+/checkout$#', $request->get_route() ) ) {
            return $response;
        }

        $params  = $request->get_json_params();
        $params  = is_array( $params ) && ! empty( $params ) ? $params : $request->get_params();
        $billing = is_array( $params['billing_address'] ?? null ) ? $params['billing_address'] : [];
        $errors  = new WP_Error();
        $this->validate_payload(
            [
                'billing_phone' => (string) ( $billing['phone'] ?? $params['billing_phone'] ?? '' ),
                'billing_email' => (string) ( $billing['email'] ?? $params['billing_email'] ?? '' ),
            ],
            $errors
        );

        return $errors->has_errors() ? $errors : $response;
    }

    public function validate_store_api_request( $response, array $handler, WP_REST_Request $request ) {
        if ( ! $this->should_protect() || 'POST' !== strtoupper( $request->get_method() ) || ! str_contains( $request->get_route(), '/wc/store/' ) || ! str_ends_with( $request->get_route(), '/checkout' ) ) {
            return $response;
        }

        $params  = $request->get_json_params();
        $billing = is_array( $params['billing_address'] ?? null ) ? $params['billing_address'] : [];
        $errors  = new WP_Error();
        $this->validate_payload(
            [
                'billing_phone' => (string) ( $billing['phone'] ?? '' ),
                'billing_email' => (string) ( $billing['email'] ?? '' ),
            ],
            $errors
        );

        return $errors->has_errors() ? $errors : $response;
    }

    public function validate_store_api_checkout( WP_Error $errors, WP_REST_Request $request ): void {
        if ( ! $this->should_protect() ) {
            return;
        }
        $params = $request->get_json_params();
        $billing = is_array( $params['billing_address'] ?? null ) ? $params['billing_address'] : [];
        $shipping = is_array( $params['shipping_address'] ?? null ) ? $params['shipping_address'] : [];
        $data = array_merge( $shipping, $billing );
        $this->validate_payload( $data, $errors );
    }

    private function should_protect(): bool {
        return $this->bool_setting( 'enabled' ) && class_exists( 'WooCommerce' );
    }

    public function validate_excluded_products_cart(): void {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }
        foreach ( WC()->cart->get_cart() as $item ) {
            $product_id = absint( $item['variation_id'] ?? $item['product_id'] ?? 0 );
            if ( $this->is_product_excluded( $product_id ) ) {
                wc_add_notice( esc_html__( 'This product is temporarily unavailable for online checkout.', 'woo-order-guard' ), 'error' );
                break;
            }
        }
    }

    public function respect_product_visibility( $visible, $product ): bool {
        if ( ! $visible || ! $product instanceof WC_Product || ! $this->is_product_excluded( $product->get_id() ) ) {
            return (bool) $visible;
        }
        return false;
    }

    private function is_product_excluded( int $product_id ): bool {
        $ids = preg_split( '/[\\s,]+/', (string) ( $this->settings()['excluded_product_ids'] ?? '' ) ) ?: [];
        return in_array( $product_id, array_map( 'absint', array_filter( $ids ) ), true );
    }

    private function validate_payload( array $data, WP_Error $errors ): void {
        $phone = $this->normalize_phone( (string) ( $data['billing_phone'] ?? $data['phone'] ?? $data['shipping_phone'] ?? '' ) );
        $email = strtolower( sanitize_email( (string) ( $data['billing_email'] ?? $data['email'] ?? '' ) ) );
        $ip = $this->client_ip();

        if ( $this->bool_setting( 'validate_mobile' ) && ! $this->valid_bd_phone( $phone ) ) {
            $this->add_checkout_error( $errors, $this->settings()['invalid_phone_message'], 'invalid_phone', $phone, $email, $ip );
            return;
        }

        if ( $this->is_whitelisted( $phone, $email ) ) {
            return;
        }

        $cooldown_key = $this->cooldown_key( $phone, $email, $ip );
        if ( get_transient( 'djog_cooldown_' . $cooldown_key ) ) {
            $this->add_checkout_error( $errors, 'Too many recent checkout attempts. Please wait a moment and try again.', 'rate_limit', $phone, $email, $ip );
            return;
        }

        $matches = $this->find_matches( $phone, $email, $ip );
        if ( empty( $matches ) ) {
            return;
        }

        $reasons = array_values( array_unique( array_column( $matches, 'reason' ) ) );
        $reason = implode( ', ', $reasons );
        $message = strtr( (string) $this->settings()['error_message'], [
            '{{window}}' => (string) absint( $this->settings()['window_minutes'] ),
            '{{reason}}' => $reason,
            '{{phone}}'  => esc_html( $phone ),
        ] );
        $this->add_checkout_error( $errors, $message, $reason, $phone, $email, $ip );
    }

    private function add_checkout_error( WP_Error $errors, string $message, string $reason, string $phone, string $email, string $ip ): void {
        $display = '<div class="djog-checkout-error"><span class="djog-checkout-error__icon" aria-hidden="true">🛡</span>' . wp_kses_post( $message ) . '</div>';
        $errors->add( 'djog_' . sanitize_key( $reason ), $display );
        $this->log_event( $reason, $phone, $email, $ip );
        $cooldown = max( 0, min( HOUR_IN_SECONDS, absint( $this->settings()['blocked_retry_cooldown'] ?? 60 ) ) );
        if ( $cooldown > 0 ) {
            set_transient( 'djog_cooldown_' . $this->cooldown_key( $phone, $email, $ip ), 1, $cooldown );
        }
    }

    private function cooldown_key( string $phone, string $email, string $ip ): string {
        return hash( 'sha256', $phone . '|' . $email . '|' . $ip );
    }

    private function valid_bd_phone( string $phone ): bool {
        return 11 === strlen( $phone ) && 1 === preg_match( '/^01[3-9][0-9]{8}$/', $phone );
    }

    private function normalize_phone( string $phone ): string {
        $digits = preg_replace( '/\D+/', '', $phone ) ?: '';
        if ( str_starts_with( $digits, '880' ) ) {
            $digits = '0' . substr( $digits, 3 );
        } elseif ( str_starts_with( $digits, '00880' ) ) {
            $digits = '0' . substr( $digits, 5 );
        }
        return substr( $digits, 0, 20 );
    }

    private function client_ip(): string {
        $raw = isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
        $ip = filter_var( $raw, FILTER_VALIDATE_IP );
        return $ip ? (string) $ip : '';
    }

    private function is_whitelisted( string $phone, string $email ): bool {
        $s = $this->settings();
        $phones = preg_split( '/\R+/', (string) $s['whitelist_phones'] ) ?: [];
        foreach ( $phones as $item ) {
            if ( $phone !== '' && $phone === $this->normalize_phone( $item ) ) {
                return true;
            }
        }
        $emails = preg_split( '/\R+/', strtolower( (string) $s['whitelist_emails'] ) ) ?: [];
        return $email !== '' && in_array( $email, array_filter( array_map( 'sanitize_email', $emails ) ), true );
    }

    private function find_matches( string $phone, string $email, string $ip ): array {
        $s = $this->settings();
        $since = gmdate( 'Y-m-d H:i:s', time() - ( absint( $s['window_minutes'] ) * MINUTE_IN_SECONDS ) );
        $matches = [];

        if ( $this->bool_setting( 'check_phone' ) && $phone !== '' ) {
            $matches = array_merge( $matches, $this->order_matches( 'phone', $phone, $since ) );
        }
        if ( $this->bool_setting( 'check_email' ) && $email !== '' ) {
            $matches = array_merge( $matches, $this->order_matches( 'email', $email, $since ) );
        }
        if ( $this->bool_setting( 'check_ip' ) && $ip !== '' ) {
            $matches = array_merge( $matches, $this->order_matches( 'ip', $ip, $since ) );
        }
        return $matches;
    }

    private function order_matches( string $type, string $value, string $since ): array {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return [];
        }
        $orders = wc_get_orders( [
            'limit'        => max( 1, absint( $this->settings()['max_orders'] ) ),
            'status'       => array_map( static fn( $status ) => 'wc-' . sanitize_key( $status ), (array) $this->settings()['statuses'] ),
            'date_created' => '>=' . $since,
            'return'       => 'objects',
        ] );
        $matches = [];
        foreach ( $orders as $order ) {
            $matches[] = $this->match_order( $order, $type, $value );
        }
        return array_values( array_filter( $matches ) );
    }

    private function match_order( WC_Order $order, string $type, string $value ): ?array {
        $same = false;
        if ( 'phone' === $type ) {
            $same = $this->normalize_phone( (string) $order->get_billing_phone() ) === $value;
        } elseif ( 'email' === $type ) {
            $same = strtolower( (string) $order->get_billing_email() ) === $value;
        } elseif ( 'ip' === $type ) {
            $same = (string) $order->get_customer_ip_address() === $value;
        }
        return $same ? [ 'reason' => $type, 'order_id' => $order->get_id() ] : null;
    }

    public function attach_order_meta( WC_Order $order, array $data ): void {
        $phone = $this->normalize_phone( (string) $order->get_billing_phone() );
        if ( $phone !== '' ) {
            $order->update_meta_data( '_djog_normalized_phone', $phone );
        }
        $order->update_meta_data( '_djog_guard_version', DJOG_VERSION );
    }

    private function log_event( string $reason, string $phone, string $email, string $ip ): void {
        global $wpdb;
        $event = hash( 'sha256', $reason . '|' . $phone . '|' . $email . '|' . $ip . '|' . gmdate( 'Y-m-d H:i' ) );
        if ( get_transient( 'djog_log_' . $event ) ) {
            return;
        }
        set_transient( 'djog_log_' . $event, 1, MINUTE_IN_SECONDS );
        $wpdb->insert( $this->table, [
            'event_key'  => $event,
            'reason'     => sanitize_text_field( $reason ),
            'phone'      => $this->mask_value( $phone ),
            'email'      => $this->mask_email( $email ),
            'ip_address' => $this->mask_ip( $ip ),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'created_at' => current_time( 'mysql', true ),
        ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
    }

    private function mask_value( string $value ): string {
        $len = strlen( $value );
        return $len > 4 ? substr( $value, 0, 3 ) . str_repeat( '*', max( 1, $len - 5 ) ) . substr( $value, -2 ) : $value;
    }

    private function mask_email( string $email ): string {
        if ( ! is_email( $email ) ) {
            return '';
        }
        [ $local, $domain ] = explode( '@', $email, 2 );
        return substr( $local, 0, 2 ) . '***@' . $domain;
    }

    private function mask_ip( string $ip ): string {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $parts = explode( '.', $ip );
            $parts[3] = '0';
            return implode( '.', $parts );
        }
        return $ip !== '' ? 'masked' : '';
    }

    public function manual_cleanup_logs(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'woo-order-guard' ), 403 );
        }
        check_admin_referer( 'djog_cleanup_logs' );
        $this->cleanup_logs();
        wp_safe_redirect( admin_url( 'admin.php?page=djog-dashboard&cleaned=1' ) );
        exit;
    }

    public function dashboard_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'woo-order-guard' ) );
        }
        global $wpdb;
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
        $today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE created_at >= %s", gmdate( 'Y-m-d 00:00:00' ) ) );
        $rows = $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 50" );
        $week = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE created_at >= %s", gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) ) ) );
        $reasons = $wpdb->get_results( $wpdb->prepare( "SELECT reason, COUNT(*) AS total FROM {$this->table} WHERE created_at >= %s GROUP BY reason ORDER BY total DESC", gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) ) ) );
        ?>
        <div class="wrap djog-wrap">
            <div class="djog-header"><div><span class="djog-kicker">DEVJOYNAL SECURITY</span><h1>WooCommerce Order Guard</h1><p>Fake, duplicate and multiple-order protection for modern WooCommerce stores.</p></div><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=djog-settings' ) ); ?>">Protection settings</a></div>
            <div class="djog-cards"><div class="djog-card"><span>Total blocked</span><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong></div><div class="djog-card"><span>Blocked today</span><strong><?php echo esc_html( number_format_i18n( $today ) ); ?></strong></div><div class="djog-card"><span>Last 7 days</span><strong><?php echo esc_html( number_format_i18n( $week ) ); ?></strong></div><div class="djog-card"><span>Protection</span><strong class="djog-status <?php echo $this->bool_setting( 'enabled' ) ? 'is-on' : 'is-off'; ?>"><?php echo $this->bool_setting( 'enabled' ) ? 'Active' : 'Paused'; ?></strong></div></div>
            <div class="djog-panel"><div class="djog-panel-heading"><div><h2>Security analytics</h2><p>Recent protection activity grouped by reason.</p></div><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=djog_cleanup_logs' ), 'djog_cleanup_logs' ) ); ?>">Run retention cleanup</a></div><div class="djog-reason-grid"><?php if ( empty( $reasons ) ) : ?><p>No blocked events in the last 30 days.</p><?php else : foreach ( $reasons as $reason_row ) : ?><div class="djog-reason-card"><span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $reason_row->reason ) ) ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $reason_row->total ) ); ?></strong></div><?php endforeach; endif; ?></div></div>
            <div class="djog-panel"><div class="djog-panel-heading"><div><h2>Security log</h2><p>Personal identifiers are masked before storage.</p></div><div><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=djog_export_logs' ), 'djog_export_logs' ) ); ?>">Export CSV</a> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=djog_clear_logs' ), 'djog_clear_logs' ) ); ?>" onclick="return confirm('Clear all security logs?');">Clear logs</a></div></div>
            <table class="widefat striped djog-table"><thead><tr><th>Date (UTC)</th><th>Reason</th><th>Phone</th><th>Email</th><th>IP</th></tr></thead><tbody>
            <?php if ( empty( $rows ) ) : ?><tr><td colspan="5">No blocked checkout attempts have been recorded yet.</td></tr><?php else : foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row->created_at ); ?></td><td><span class="djog-badge"><?php echo esc_html( ucfirst( $row->reason ) ); ?></span></td><td><?php echo esc_html( $row->phone ); ?></td><td><?php echo esc_html( $row->email ); ?></td><td><?php echo esc_html( $row->ip_address ); ?></td></tr><?php endforeach; endif; ?>
            </tbody></table></div>
            <p class="djog-footer">Built by <a href="https://devjoynal.com" target="_blank" rel="noopener">Joynal Abdin · DevJoynal</a> · <a href="https://github.com/joynalabddin/woo-order-guard" target="_blank" rel="noopener">GitHub</a></p>
        </div>
        <?php
    }

    public function settings_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'woo-order-guard' ) );
        }
        $s = $this->settings();
        ?>
        <div class="wrap djog-wrap"><div class="djog-header"><div><span class="djog-kicker">CONFIGURATION</span><h1>Protection settings</h1><p>Configure rules without editing theme or checkout code.</p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=djog-dashboard' ) ); ?>">View dashboard</a></div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="djog-settings-form"><input type="hidden" name="action" value="djog_save_settings"><?php wp_nonce_field( 'djog_save_settings' ); ?>
        <div class="djog-grid"><div class="djog-panel"><h2>Core protection</h2><label class="djog-toggle"><input type="checkbox" name="enabled" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>><span>Enable checkout protection</span></label><label class="djog-toggle"><input type="checkbox" name="validate_mobile" value="yes" <?php checked( $s['validate_mobile'], 'yes' ); ?>><span>Require valid Bangladeshi mobile format</span></label><label class="djog-field">Block window (minutes)<input type="number" name="window_minutes" min="1" max="10080" value="<?php echo esc_attr( $s['window_minutes'] ); ?>"><small>Orders within this period are checked against the same customer signals.</small></label><label class="djog-field">Maximum matching orders<input type="number" name="max_orders" min="1" max="20" value="<?php echo esc_attr( $s['max_orders'] ); ?>"><small>Number of recent orders that triggers protection.</small></label><label class="djog-field">Blocked retry cooldown (seconds)<input type="number" name="blocked_retry_cooldown" min="0" max="3600" value="<?php echo esc_attr( $s['blocked_retry_cooldown'] ); ?>"><small>Temporarily slows repeated blocked checkout attempts from the same signals.</small></label><fieldset><legend>Signals to check</legend><label><input type="checkbox" name="check_phone" value="yes" <?php checked( $s['check_phone'], 'yes' ); ?>> Phone</label><label><input type="checkbox" name="check_email" value="yes" <?php checked( $s['check_email'], 'yes' ); ?>> Email</label><label><input type="checkbox" name="check_ip" value="yes" <?php checked( $s['check_ip'], 'yes' ); ?>> IP address</label></fieldset><fieldset><legend>Order statuses counted</legend><?php foreach ( [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled' ] as $status ) : ?><label><input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, (array) $s['statuses'], true ) ); ?>> <?php echo esc_html( ucfirst( $status ) ); ?></label><?php endforeach; ?></fieldset></div>
        <div class="djog-panel"><h2>Trusted customers</h2><label class="djog-field">Whitelisted phone numbers<textarea name="whitelist_phones" rows="6" placeholder="One number per line"><?php echo esc_textarea( $s['whitelist_phones'] ); ?></textarea><small>Accepted formats are normalized automatically, including +8801XXXXXXXXX.</small></label><label class="djog-field">Whitelisted email addresses<textarea name="whitelist_emails" rows="6" placeholder="One email per line"><?php echo esc_textarea( $s['whitelist_emails'] ); ?></textarea></label><label class="djog-field">Excluded product IDs<textarea name="excluded_product_ids" rows="4" placeholder="101, 202 or one ID per line"><?php echo esc_textarea( $s['excluded_product_ids'] ); ?></textarea><small>Products listed here are hidden from catalog visibility and blocked from checkout.</small></label><h2>Customer messages</h2><label class="djog-field">Duplicate/fake order message<textarea name="error_message" rows="5"><?php echo esc_textarea( $s['error_message'] ); ?></textarea><small>Placeholders: {{window}}, {{reason}}, {{phone}}</small></label><label class="djog-field">Invalid phone message<textarea name="invalid_phone_message" rows="4"><?php echo esc_textarea( $s['invalid_phone_message'] ); ?></textarea></label><div class="djog-preview"><strong>Live preview</strong><div class="djog-checkout-error"><span class="djog-checkout-error__icon">🛡</span><span id="djog-preview-text"><?php echo esc_html( $s['error_message'] ); ?></span></div></div></div></div>
        <div class="djog-panel"><h2>Message appearance</h2><div class="djog-fields-row"><label class="djog-field">Text color<input type="color" name="text_color" value="<?php echo esc_attr( $s['text_color'] ); ?>"></label><label class="djog-field">Background<input type="color" name="background_color" value="<?php echo esc_attr( $s['background_color'] ); ?>"></label><label class="djog-field">Border<input type="color" name="border_color" value="<?php echo esc_attr( $s['border_color'] ); ?>"></label><label class="djog-field">Radius (px)<input type="number" name="border_radius" min="0" max="32" value="<?php echo esc_attr( $s['border_radius'] ); ?>"></label><label class="djog-field">Font size (px)<input type="number" name="font_size" min="12" max="24" value="<?php echo esc_attr( $s['font_size'] ); ?>"></label><label class="djog-field">Log retention (days)<input type="number" name="log_retention_days" min="1" max="3650" value="<?php echo esc_attr( $s['log_retention_days'] ); ?>"><small>Old masked security logs are removed by the daily cleanup task.</small></label></div></div><p><button class="button button-primary button-large" type="submit">Save protection settings</button></p></form><p class="djog-footer">Developer: <strong>Joynal Abdin</strong> · <a href="https://devjoynal.com" target="_blank" rel="noopener">DevJoynal</a></p></div>
        <?php
    }

    public function save_settings(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'woo-order-guard' ), 403 );
        }
        check_admin_referer( 'djog_save_settings' );
        $raw = wp_unslash( $_POST );
        $defaults = $this->defaults();
        $clean = [
            'enabled' => isset( $raw['enabled'] ) ? 'yes' : 'no',
            'validate_mobile' => isset( $raw['validate_mobile'] ) ? 'yes' : 'no',
            'mobile_mode' => 'bd',
            'check_phone' => isset( $raw['check_phone'] ) ? 'yes' : 'no',
            'check_email' => isset( $raw['check_email'] ) ? 'yes' : 'no',
            'check_ip' => isset( $raw['check_ip'] ) ? 'yes' : 'no',
            'window_minutes' => max( 1, min( 10080, absint( $raw['window_minutes'] ?? $defaults['window_minutes'] ) ) ),
            'max_orders' => max( 1, min( 20, absint( $raw['max_orders'] ?? $defaults['max_orders'] ) ) ),
            'blocked_retry_cooldown' => max( 0, min( 3600, absint( $raw['blocked_retry_cooldown'] ?? $defaults['blocked_retry_cooldown'] ) ) ),
            'statuses' => array_values( array_intersect( [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled' ], array_map( 'sanitize_key', (array) ( $raw['statuses'] ?? [] ) ) ) ),
            'whitelist_phones' => sanitize_textarea_field( $raw['whitelist_phones'] ?? '' ),
            'whitelist_emails' => sanitize_textarea_field( strtolower( (string) ( $raw['whitelist_emails'] ?? '' ) ) ),
            'excluded_product_ids' => preg_replace( '/[^0-9,\\s]/', '', (string) ( $raw['excluded_product_ids'] ?? '' ) ),
            'error_message' => wp_kses_post( $raw['error_message'] ?? $defaults['error_message'] ),
            'invalid_phone_message' => wp_kses_post( $raw['invalid_phone_message'] ?? $defaults['invalid_phone_message'] ),
            'icon' => 'shield',
            'text_color' => sanitize_hex_color( $raw['text_color'] ?? '' ) ?: $defaults['text_color'],
            'background_color' => sanitize_hex_color( $raw['background_color'] ?? '' ) ?: $defaults['background_color'],
            'border_color' => sanitize_hex_color( $raw['border_color'] ?? '' ) ?: $defaults['border_color'],
            'border_radius' => max( 0, min( 32, absint( $raw['border_radius'] ?? $defaults['border_radius'] ) ) ),
            'font_size' => max( 12, min( 24, absint( $raw['font_size'] ?? $defaults['font_size'] ) ) ),
            'log_retention_days' => max( 1, min( 3650, absint( $raw['log_retention_days'] ?? $defaults['log_retention_days'] ) ) ),
        ];
        update_option( $this->option, $clean, false );
        wp_safe_redirect( add_query_arg( [ 'page' => 'djog-settings', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function clear_logs(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'woo-order-guard' ), 403 );
        }
        check_admin_referer( 'djog_clear_logs' );
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table}" );
        wp_safe_redirect( admin_url( 'admin.php?page=djog-dashboard&cleared=1' ) );
        exit;
    }

    public function export_logs(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'woo-order-guard' ), 403 );
        }
        check_admin_referer( 'djog_export_logs' );
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT created_at, reason, phone, email, ip_address FROM {$this->table} ORDER BY id DESC", ARRAY_A );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=woo-order-guard-logs-' . gmdate( 'Y-m-d' ) . '.csv' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'Created UTC', 'Reason', 'Phone', 'Email', 'IP' ] );
        foreach ( $rows as $row ) {
            fputcsv( $out, $row );
        }
        fclose( $out );
        exit;
    }

    public function privacy_policy_content(): void {
        if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
            wp_add_privacy_policy_content( 'WooCommerce Order Guard', '<p>This plugin stores masked phone, email and IP information in the site database when a checkout is blocked. It does not transmit checkout data to DevJoynal, GitHub or another external service.</p>' );
        }
    }

    public function register_exporter( array $exporters ): array {
        $exporters['woo-order-guard'] = [ 'exporter_friendly_name' => 'WooCommerce Order Guard', 'callback' => [ $this, 'privacy_exporter' ] ];
        return $exporters;
    }

    public function privacy_exporter( string $email_address, int $page = 1 ): array {
        global $wpdb;
        $email = $this->mask_email( sanitize_email( $email_address ) );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT created_at, reason, phone, email, ip_address FROM {$this->table} WHERE email = %s LIMIT 100", $email ), ARRAY_A );
        $data = [];
        foreach ( $rows as $row ) {
            foreach ( $row as $key => $value ) {
                $data[] = [ 'name' => 'Order Guard ' . ucfirst( str_replace( '_', ' ', $key ) ), 'value' => (string) $value ];
            }
        }
        return [ 'data' => $data, 'done' => true ];
    }

    public function register_eraser( array $erasers ): array {
        $erasers['woo-order-guard'] = [ 'eraser_friendly_name' => 'WooCommerce Order Guard', 'callback' => [ $this, 'privacy_eraser' ] ];
        return $erasers;
    }

    public function privacy_eraser( string $email_address, int $page = 1 ): array {
        global $wpdb;
        $email = $this->mask_email( sanitize_email( $email_address ) );
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE email = %s", $email ) );
        if ( $count > 0 ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table} WHERE email = %s", $email ) );
        }
        return [ 'items_removed' => $count, 'items_retained' => 0, 'messages' => [ 'Matching masked Order Guard logs were removed.' ], 'done' => true ];
    }
}

DevJoynal_Woo_Order_Guard::instance();
