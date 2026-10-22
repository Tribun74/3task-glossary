<?php
/**
 * Plugin Name: 3task Glossary – Dictionary, Wiki & Knowledge Base
 * Plugin URI: https://www.3task.de/3task-glossary-pro/
 * Description: Create glossaries, dictionaries & knowledge bases using WordPress pages. A-Z navigation, knowledge base mode with categories, table of contents and search, auto-linking, dark mode. No database, just pages.
 * Version: 2.4.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: 3task
 * Author URI: https://www.3task.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 3task-glossary
 * Domain Path: /languages
 *
 * @package 3Task_Glossary
 * @version 2.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent conflict with old simple-seo-glossary plugin.
// Check for the specific old plugin file, not just the class name.
if ( defined( 'AZGL_PLUGIN_FILE' ) && strpos( AZGL_PLUGIN_FILE, '3task-glossary' ) === false ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>3task Glossary:</strong> ';
        echo esc_html__( 'Please deactivate the old "Simple SEO Glossary" plugin. Both plugins cannot run simultaneously.', '3task-glossary' );
        echo '</p></div>';
    } );
    return;
}

// Plugin constants (with defined checks for safety).
if ( ! defined( 'AZGL_VERSION' ) ) {
    define( 'AZGL_VERSION', '2.4.0' );
}
if ( ! defined( 'AZGL_PLUGIN_FILE' ) ) {
    define( 'AZGL_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'AZGL_PLUGIN_DIR' ) ) {
    define( 'AZGL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
require_once AZGL_PLUGIN_DIR . 'includes/class-content.php';
require_once AZGL_PLUGIN_DIR . 'includes/class-kb.php';
if ( ! defined( 'AZGL_PLUGIN_URL' ) ) {
    define( 'AZGL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'AZGL_PLUGIN_BASENAME' ) ) {
    define( 'AZGL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Limits for Lite version (no functional limits for WordPress.org compliance).
if ( ! defined( 'AZGL_MAX_GLOSSARIES' ) ) {
    define( 'AZGL_MAX_GLOSSARIES', 999 );
}
if ( ! defined( 'AZGL_MAX_ENTRIES' ) ) {
    define( 'AZGL_MAX_ENTRIES', 999999 );
}
if ( ! defined( 'AZGL_SUPPORTER_MAX_GLOSSARIES' ) ) {
    define( 'AZGL_SUPPORTER_MAX_GLOSSARIES', 999 );
}
if ( ! defined( 'AZGL_SUPPORTER_MAX_ENTRIES' ) ) {
    define( 'AZGL_SUPPORTER_MAX_ENTRIES', 999999 );
}

/**
 * Main Plugin Class
 *
 * @since 2.0.0
 */
final class AZ_Glossary_Lite {

    /**
     * Plugin instance.
     *
     * @var AZ_Glossary_Lite
     */
    private static $instance = null;

    /**
     * Options handler.
     *
     * @var AZ_Glossary_Options
     */
    public $options;

    /**
     * Admin handler.
     *
     * @var AZ_Glossary_Admin
     */
    public $admin;

    /**
     * Frontend handler.
     *
     * @var AZ_Glossary_Frontend
     */
    public $frontend;

    /**
     * Linker handler.
     *
     * @var AZ_Glossary_Linker
     */
    public $linker;

    /**
     * Get plugin instance (Singleton).
     *
     * @return AZ_Glossary_Lite
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private for Singleton.
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin.
     */
    private function init() {
        // Load classes.
        $this->load_classes();

        // Load text domain early.
        add_action( 'init', array( $this, 'load_textdomain' ), 1 );

        // Initialize components after plugins loaded.
        add_action( 'plugins_loaded', array( $this, 'init_components' ) );

        // Activation/deactivation hooks.
        register_activation_hook( AZGL_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( AZGL_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        $options  = get_option( 'azgl_options', array() );
        $language = isset( $options['plugin_language'] ) ? $options['plugin_language'] : 'auto';

        // If English is explicitly selected, don't load any translation (use source strings).
        if ( 'en_US' === $language ) {
            // Unload any existing translations.
            unload_textdomain( '3task-glossary' );
            return;
        }

        // If specific non-English language is set, load that language file if it exists locally.
        // Otherwise WordPress.org handles translations automatically since 4.6+.
        if ( 'auto' !== $language && ! empty( $language ) ) {
            $mofile = AZGL_PLUGIN_DIR . 'languages/simple-seo-glossary-' . $language . '.mo';
            if ( file_exists( $mofile ) ) {
                load_textdomain( '3task-glossary', $mofile );
            }
        }
    }

    /**
     * Load required classes.
     */
    private function load_classes() {
        $classes = array(
            'includes/class-options.php',
            'includes/class-admin.php',
            'includes/class-frontend.php',
            'includes/class-linker.php',
            'includes/class-widget.php',
        );

        foreach ( $classes as $class ) {
            $file = AZGL_PLUGIN_DIR . $class;
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }

    /**
     * Initialize components.
     */
    public function init_components() {
        // Pro replaces Free completely: never run both frontends (double schema and navigation).
        if ( defined( 'AZGL_PRO_VERSION' ) || class_exists( 'AZ_Glossary_Pro', false ) ) {
            return;
        }

        // Initialize options first.
        $this->options = new AZ_Glossary_Options();

        // Initialize admin.
        if ( is_admin() ) {
            $this->admin = new AZ_Glossary_Admin( $this->options );
        }

        // Initialize frontend.
        $this->frontend = new AZ_Glossary_Frontend( $this->options );

        // Initialize linker.
        $this->linker = new AZ_Glossary_Linker( $this->options );
    }

    /**
     * Notice shown while 3task Glossary Pro is active at the same time.
     */
    public static function pro_active_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>';
        esc_html_e( '3task Glossary Pro is active, so the free 3task Glossary plugin is paused. You can safely deactivate and delete the free plugin; your glossaries and settings are kept.', '3task-glossary' );
        echo '</p></div>';
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Check PHP version.
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( AZGL_PLUGIN_BASENAME );
            wp_die(
                esc_html__( '3task Glossary requires PHP 7.4 or higher.', '3task-glossary' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }

        // Check WordPress version.
        if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
            deactivate_plugins( AZGL_PLUGIN_BASENAME );
            wp_die(
                esc_html__( '3task Glossary requires WordPress 5.8 or higher.', '3task-glossary' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }

        // Set default options if not exists.
        if ( false === get_option( 'azgl_options' ) ) {
            $defaults = array(
                'glossaries'       => array(),
                'linking_mode'     => 'selective',
                'link_first_only'  => true,
                'link_class'       => 'glossary-term',
                'cache_enabled'    => true,
                'nav_style'        => 'buttons',
                'inactive_letters' => true,
                'color_scheme'     => 'emerald',
                'dark_mode'        => 'auto',
                'show_credit_link' => false,
                'credit_link_url'  => 'https://www.3task.de',
            );
            add_option( 'azgl_options', $defaults );
        }

        // Store version.
        update_option( 'azgl_version', AZGL_VERSION );

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clear transients.
        $this->clear_all_transients();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Clear all plugin transients.
     */
    private function clear_all_transients() {
        global $wpdb;

        // Delete all transients with our prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '%_transient_azgl_%'
            )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '%_transient_timeout_azgl_%'
            )
        );
    }

    /**
     * Check if Pro version is active.
     *
     * @return bool
     */
    public function is_pro() {
        return false;
    }

    /**
     * Check if Supporter mode is active.
     *
     * @return bool
     */
    public function is_supporter() {
        $options = get_option( 'azgl_options', array() );
        return ! empty( $options['show_credit_link'] );
    }

    /**
     * Get maximum allowed glossaries.
     *
     * @return int
     */
    public function get_max_glossaries() {
        return $this->is_supporter() ? AZGL_SUPPORTER_MAX_GLOSSARIES : AZGL_MAX_GLOSSARIES;
    }

    /**
     * Get maximum allowed entries per glossary.
     *
     * @return int
     */
    public function get_max_entries() {
        return $this->is_supporter() ? AZGL_SUPPORTER_MAX_ENTRIES : AZGL_MAX_ENTRIES;
    }

    /**
     * Get upgrade URL.
     *
     * @return string
     */
    public function get_upgrade_url() {
        return 'https://www.3task.de/3task-glossary-pro/';
    }

    /**
     * Get options handler (same API as Pro; the widget relies on it).
     *
     * @return AZ_Glossary_Options
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * Get all entries for a specific glossary.
     *
     * @param int $glossary_id Glossary page ID.
     * @return array Array of WP_Post objects.
     */
    public function get_glossary_entries( $glossary_id ) {
        if ( ! AZGL_Content::public_page( $glossary_id ) ) { return array(); }
        $enabled = $this->options->get( 'cache_enabled', true );
        $cache_key = 'azgl_entries_' . absint( $glossary_id );
        $entries = $enabled ? get_transient( $cache_key ) : false;
        if ( false === $entries ) {
            $entries = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'has_password' => false,
                'post_parent' => absint( $glossary_id ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
            if ( $enabled ) { set_transient( $cache_key, $entries, HOUR_IN_SECONDS ); }
        }
        return array_values( array_filter( $entries ?: array(), array( 'AZGL_Content', 'public_page' ) ) );

    }

    /**
     * Get entry count for a glossary.
     *
     * @param int $glossary_id The glossary parent page ID.
     * @return int Number of entries.
     */
    public function get_entry_count( $glossary_id ) {
        $entries = $this->get_glossary_entries( $glossary_id );
        return count( $entries );
    }

    /**
     * Clear cache for a specific glossary.
     *
     * @param int $glossary_id The glossary parent page ID.
     */
    public function clear_glossary_cache( $glossary_id ) {
        delete_transient( 'azgl_entries_' . $glossary_id );
        delete_transient( 'azgl_az_nav_' . $glossary_id );
    }
}

/**
 * Get plugin instance.
 *
 * @return AZ_Glossary_Lite
 */
if ( ! function_exists( 'azgl' ) ) {
    function azgl() {
        return AZ_Glossary_Lite::get_instance();
    }
}

// Pro active at the same time: tell the admin, independent of plugin load order.
add_action(
    'plugins_loaded',
    static function () {
        if ( defined( 'AZGL_PRO_VERSION' ) && is_admin() ) {
            add_action( 'admin_notices', array( 'AZ_Glossary_Lite', 'pro_active_notice' ) );
        }
    },
    5
);

// Initialize plugin.
azgl();
