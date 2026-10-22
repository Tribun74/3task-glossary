<?php
/**
 * 3task Glossary Uninstall
 *
 * Runs when the plugin is deleted (not deactivated).
 * Removes all plugin data from the database.
 *
 * @package 3Task_Glossary
 * @since 2.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete plugin options.
 */
$pro_installed = defined( 'AZGL_PRO' ) || is_file( WP_PLUGIN_DIR . '/3task-glossary-pro/3task-glossary-pro.php' );
if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
foreach ( get_plugins() as $plugin_data ) {
    if ( '3task-glossary-pro' === ( $plugin_data['TextDomain'] ?? '' ) ) { $pro_installed = true; break; }
}
if ( ! $pro_installed ) {
    delete_option( 'azgl_options' );
}
delete_option( 'azgl_version' );

/**
 * Delete legacy options from version 1.x.
 */
delete_option( 'az_glossary_options' );
delete_option( 'az_glossary_version' );

/**
 * Delete all plugin transients.
 *
 * Direct database query is necessary because WordPress doesn't provide
 * an API to delete transients by pattern. This only runs during uninstall.
 */
global $wpdb;

// Delete new format transients (azgl_*).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_azgl_%'
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_timeout_azgl_%'
    )
);

// Delete legacy transients (azglossary_*).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_azglossary_%'
    )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_timeout_azglossary_%'
    )
);

/**
 * Note: We do NOT delete WordPress pages that were used as glossaries.
 * The plugin only stores references to existing pages, it doesn't own them.
 * Users who delete the plugin keep their content intact.
 */
