<?php
/**
 * Plugin Name: WooCommerce Create User from Guest Orders
 * Plugin URI: https://jellum.com
 * Description: Allows administrators to manually convert guest orders into registered customer accounts.
 * Version: 1.0.0
 * Author: Lasse Jellum
 * Author URI: https://jellum.com
 * Text Domain: woocommerce-create-user-from-guest
 * Domain Path: /languages
 * WC requires at least: 4.0.0
 * WC tested up to: 8.0.0
 *
 * @package WooCommerce_Create_User_From_Guest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'WCUFG_VERSION', '1.0.0' );
define( 'WCUFG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCUFG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCUFG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class WC_Create_User_From_Guest {

    /**
     * The single instance of the class.
     *
     * @var WC_Create_User_From_Guest
     */
    protected static $_instance = null;

    /**
     * Main instance.
     *
     * @return WC_Create_User_From_Guest
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        // Check if WooCommerce is active.
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_not_active_notice' ) );
            return;
        }

        // Initialize the plugin.
        add_action( 'plugins_loaded', array( $this, 'init' ) );

        // Register activation hook.
        register_activation_hook( __FILE__, array( $this, 'activate' ) );

        // Register deactivation hook.
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Initializes the plugin.
     */
    public function init() {
        // Load plugin text domain.
        load_plugin_textdomain( 'woocommerce-create-user-from-guest', false, dirname( WCUFG_PLUGIN_BASENAME ) . '/languages' );

        // Admin hooks.
        if ( is_admin() ) {
            // Add button to the order screen for guest orders.
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_create_user_button' ), 10, 1 );
            
            // Handle the create user action.
            add_action( 'admin_init', array( $this, 'handle_create_user_action' ) );
            
            // Load admin scripts.
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

            // Add admin notices.
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        }
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    public function is_woocommerce_active() {
        return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
    }

    /**
     * Display WooCommerce not active notice.
     */
    public function woocommerce_not_active_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e( 'WooCommerce Create User from Guest requires WooCommerce to be installed and active.', 'woocommerce-create-user-from-guest' ); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Activation tasks if needed.
        // For now, just check WooCommerce is active.
        if ( ! $this->is_woocommerce_active() ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( esc_html__( 'Please install and activate WooCommerce before activating this plugin.', 'woocommerce-create-user-from-guest' ) );
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Cleanup tasks if needed.
    }

    /**
     * Enqueue admin scripts.
     */
    public function enqueue_admin_scripts( $hook ) {
        global $post;
        
        // Only on WooCommerce order edit screens.
        if ( 'post.php' === $hook && isset( $post ) && 'shop_order' === $post->post_type ) {
            wp_enqueue_script(
                'wcufg-admin-confirm',
                WCUFG_PLUGIN_URL . 'assets/admin-confirm.js',
                array( 'jquery' ),
                WCUFG_VERSION,
                true
            );
        }
    }

    /**
     * Add create user button on guest orders.
     *
     * @param WC_Order $order WooCommerce order object.
     */
    public function add_create_user_button( $order ) {
        // Only show on guest orders.
        if ( 0 !== $order->get_user_id() ) {
            return;
        }

        $order_id = $order->get_id();
        $nonce = wp_create_nonce( 'wcufg_create_user_' . $order_id );
        $create_user_url = admin_url( 'post.php?post=' . $order_id . '&action=edit&wcufg_create_user=1&_wpnonce=' . $nonce );
        
        // Add JavaScript to modify the existing customer field
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Find the customer field label
                var $customerLabel = $('.wc-customer-user label[for="customer_user"]');
                
                if ($customerLabel.length) {
                    // Add our button to the existing label
                    $customerLabel.append(' <a href="<?php echo esc_url( $create_user_url ); ?>" class="wcufg-create-user-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Create User from Guest', 'woocommerce-create-user-from-guest' ); ?></a>');
                }
            });
        </script>
        <?php
    }

    /**
     * Handle the create user action.
     */
    public function handle_create_user_action() {
        // Check if we should process user creation.
        if ( ! isset( $_GET['wcufg_create_user'] ) || ! isset( $_GET['post'] ) ) {
            return;
        }

        $order_id = intval( $_GET['post'] );
        
        // Verify nonce.
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wcufg_create_user_' . $order_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'woocommerce-create-user-from-guest' ) );
        }

        // Check permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'woocommerce-create-user-from-guest' ) );
        }

        // Get the order.
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( esc_html__( 'Invalid order.', 'woocommerce-create-user-from-guest' ) );
        }

        // If this is not a guest order, don't proceed.
        if ( 0 !== $order->get_user_id() ) {
            wp_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit&user_exists=1' ) );
            exit;
        }

        // Get email from order.
        $email = $order->get_billing_email();
        if ( empty( $email ) ) {
            wp_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit&no_email=1' ) );
            exit;
        }

        // Check if user already exists.
        if ( email_exists( $email ) ) {
            wp_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit&email_exists=1' ) );
            exit;
        }

        // Check if we should send email.
        $send_email = isset( $_GET['send_email'] ) && '1' === $_GET['send_email'];

        // Create the user.
        $user_id = $this->create_user_from_order( $order, $send_email );
        
        if ( is_wp_error( $user_id ) ) {
            wp_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit&create_failed=1' ) );
            exit;
        }

        // Find and reassign other guest orders with the same email.
        $reassigned_count = $this->reassign_guest_orders( $email, $user_id, $order_id );

        // Redirect back to the order with success.
        $redirect_url = add_query_arg( 
            array(
                'post'             => $order_id,
                'action'           => 'edit',
                'user_created'     => '1',
                'email_status'     => $send_email ? 'sent' : 'skipped',
                'reassigned_orders' => $reassigned_count,
            ),
            admin_url( 'post.php' )
        );

        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Create a new user from order data.
     *
     * @param WC_Order $order WooCommerce order object.
     * @param bool     $send_email Whether to send welcome email.
     * @return int|WP_Error User ID or error.
     */
    public function create_user_from_order( $order, $send_email = false ) {
        // Get user data from order.
        $email = $order->get_billing_email();
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        // Generate username from first and last name
        $username = $this->generate_username_from_name( $first_name, $last_name );
        
        // Generate a random password.
        $password = wp_generate_password();
        
        // Create the user.
        $user_id = wp_insert_user( array(
            'user_login'    => $username,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'display_name'  => $first_name . ' ' . $last_name,
            'role'          => 'customer',
        ) );
        
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }
        
        // Save billing address fields
        $billing_fields = array(
            'billing_first_name'    => $order->get_billing_first_name(),
            'billing_last_name'     => $order->get_billing_last_name(),
            'billing_company'       => $order->get_billing_company(),
            'billing_address_1'     => $order->get_billing_address_1(),
            'billing_address_2'     => $order->get_billing_address_2(),
            'billing_city'          => $order->get_billing_city(),
            'billing_postcode'      => $order->get_billing_postcode(),
            'billing_country'       => $order->get_billing_country(),
            'billing_state'         => $order->get_billing_state(),
            'billing_phone'         => $order->get_billing_phone(),
            'billing_email'         => $order->get_billing_email(),
        );
        
        // Save shipping address fields
        $shipping_fields = array(
            'shipping_first_name'   => $order->get_shipping_first_name(),
            'shipping_last_name'    => $order->get_shipping_last_name(),
            'shipping_company'      => $order->get_shipping_company(),
            'shipping_address_1'    => $order->get_shipping_address_1(),
            'shipping_address_2'    => $order->get_shipping_address_2(),
            'shipping_city'         => $order->get_shipping_city(),
            'shipping_postcode'     => $order->get_shipping_postcode(),
            'shipping_country'      => $order->get_shipping_country(),
            'shipping_state'        => $order->get_shipping_state(),
            'shipping_phone'        => $order->get_shipping_phone(),
        );
        
        // Save all user meta
        foreach ( $billing_fields as $key => $value ) {
            if ( ! empty( $value ) ) {
                update_user_meta( $user_id, $key, $value );
            }
        }
        
        foreach ( $shipping_fields as $key => $value ) {
            if ( ! empty( $value ) ) {
                update_user_meta( $user_id, $key, $value );
            }
        }
    
    // Update the order's user ID.
    $order->set_customer_id( $user_id );
    $order->save();
    
    // Add order note.
    $order->add_order_note(
        $send_email
        ? esc_html__( 'Customer account created with billing and shipping information. Login details sent to customer.', 'woocommerce-create-user-from-guest' )
        : esc_html__( 'Customer account created with billing and shipping information. Login credentials not sent.', 'woocommerce-create-user-from-guest' )
    );
    
    // Maybe send account email.
    if ( $send_email ) {
        wp_new_user_notification( $user_id, null, 'user' );
    }
    
    return $user_id;
}
    
    /**
     * Generate a username from first and last name.
     * 
     * @param string $first_name First name.
     * @param string $last_name Last name.
     * @return string Sanitized username.
     */
    private function generate_username_from_name( $first_name, $last_name ) {
        // Convert to lowercase
        $first = strtolower( trim( $first_name ) );
        $last = strtolower( trim( $last_name ) );
        
        // Replace Norwegian characters
        $replacements = array(
            'æ' => 'ae',
            'ø' => 'oe',
            'å' => 'aa',
            'Æ' => 'e',
            'Ø' => 'oe',
            'Å' => 'aa'
        );
        
        $first = str_replace( array_keys( $replacements ), array_values( $replacements ), $first );
        $last = str_replace( array_keys( $replacements ), array_values( $replacements ), $last );
        
        // Create base username
        $username = sanitize_user( $first . '.' . $last, true );
        
        // Ensure username is valid
        if ( empty( $username ) ) {
            // Fallback to email if name doesn't produce valid username
            $email_parts = explode( '@', $email );
            $username = sanitize_user( $email_parts[0], true );
        }
        
        // Ensure username is unique
        $counter = 1;
        $new_username = $username;
        
        while ( username_exists( $new_username ) ) {
            $new_username = $username . $counter;
            $counter++;
        }
        
        return $new_username;
    }

    /**
     * Reassign guest orders to a user.
     *
     * @param string $email User email.
     * @param int    $user_id User ID.
     * @param int    $current_order_id Current order ID to exclude.
     * @return int Number of orders reassigned.
     */
    public function reassign_guest_orders( $email, $user_id, $current_order_id ) {
        // Find guest orders with the same email.
        $query = new WC_Order_Query( array(
            'limit'          => -1,
            'customer'       => 0, // Guest orders.
            'billing_email'  => $email,
            'return'         => 'ids',
            'exclude'        => array( $current_order_id ),
        ) );
        
        $order_ids = $query->get_orders();
        
        // If no other orders, return 0.
        if ( empty( $order_ids ) ) {
            return 0;
        }
        
        // Reassign each order.
        $reassigned = 0;
        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->set_customer_id( $user_id );
                $order->save();
                
                // Add order note.
                $order->add_order_note(
                    esc_html__( 'Order reassigned to customer account during guest order conversion.', 'woocommerce-create-user-from-guest' )
                );
                
                $reassigned++;
            }
        }
        
        return $reassigned;
    }

    /**
     * Display admin notices.
     */
    public function admin_notices() {
        global $post;
        
        // Only on order edit page.
        if ( ! $post || 'shop_order' !== $post->post_type ) {
            return;
        }
        
        // Handle error messages.
        if ( isset( $_GET['email_exists'] ) ) {
            $this->display_notice( __( 'User with this email already exists. No new account created.', 'woocommerce-create-user-from-guest' ), 'error' );
        }
        
        if ( isset( $_GET['no_email'] ) ) {
            $this->display_notice( __( 'Order has no billing email. Cannot create account.', 'woocommerce-create-user-from-guest' ), 'error' );
        }
        
        if ( isset( $_GET['create_failed'] ) ) {
            $this->display_notice( __( 'User account creation failed. Please try again.', 'woocommerce-create-user-from-guest' ), 'error' );
        }
        
        if ( isset( $_GET['user_exists'] ) ) {
            $this->display_notice( __( 'This order is already assigned to a user account.', 'woocommerce-create-user-from-guest' ), 'error' );
        }
        
        // Handle success message.
        if ( isset( $_GET['user_created'] ) ) {
            $email_status = isset( $_GET['email_status'] ) ? $_GET['email_status'] : 'skipped';
            $reassigned = isset( $_GET['reassigned_orders'] ) ? intval( $_GET['reassigned_orders'] ) : 0;
            
            $message = sprintf(
                __( '✅ User account created and assigned to the order. %s other guest orders reassigned. Email was %s.', 'woocommerce-create-user-from-guest' ),
                $reassigned,
                'sent' === $email_status ? __( 'sent', 'woocommerce-create-user-from-guest' ) : __( 'not sent', 'woocommerce-create-user-from-guest' )
            );
            
            $this->display_notice( $message, 'success' );
        }
    }

    /**
     * Display admin notice.
     *
     * @param string $message Message to display.
     * @param string $type Notice type (error, warning, success, info).
     */
    private function display_notice( $message, $type = 'info' ) {
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }
}

// Initialize the plugin.
function wcufg_init() {
    return WC_Create_User_From_Guest::instance();
}

// Start the plugin.
wcufg_init();