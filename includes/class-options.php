<?php
/**
 * 3task Glossary Options Handler
 *
 * Manages all plugin options.
 *
 * @package 3Task_Glossary
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Options Class
 *
 * @since 2.0.0
 */
class AZ_Glossary_Options {

    /**
     * Option name in database.
     *
     * @var string
     */
    const OPTION_NAME = 'azgl_options';

    /**
     * Cached options.
     *
     * @var array|null
     */
    private $options = null;

    /**
     * Default options.
     *
     * @var array
     */
    private $defaults = array(
        // Glossary settings.
        'glossaries'       => array(),

        // Linking settings.
        'linking_mode'     => 'selective', // disabled, selective, all.
        'link_first_only'  => true,
        'link_class'       => 'glossary-term',

        // Display settings.
        'nav_style'        => 'buttons', // buttons, pills, minimal.
        'inactive_letters' => 'accessible', // show, accessible, hidden, none.
        'color_scheme'     => 'emerald', // emerald, ocean, sunset, berry, slate, auto.
        'dark_mode'        => 'auto', // auto, light, dark.

        // Cache settings.
        'cache_enabled'    => true,

        // Supporter mode.
        'show_credit_link' => false,
        'credit_link_url'  => 'https://www.3task.de',

        // Language setting.
        'plugin_language'  => 'auto', // auto, en_US, de_DE.
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_options();
    }

    /**
     * Load options from database.
     */
    private function load_options() {
        $this->options = get_option( self::OPTION_NAME, array() );

        // Merge with defaults.
        $this->options = wp_parse_args( $this->options, $this->defaults );
    }

    /**
     * Get a single option value.
     *
     * @param string $key     Option key.
     * @param mixed  $default Default value if not set.
     * @return mixed Option value.
     */
    public function get( $key, $default = null ) {
        if ( null === $this->options ) {
            $this->load_options();
        }

        if ( isset( $this->options[ $key ] ) ) {
            return $this->options[ $key ];
        }

        if ( null !== $default ) {
            return $default;
        }

        return isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null;
    }

    /**
     * Get all options.
     *
     * @return array All options.
     */
    public function get_all() {
        if ( null === $this->options ) {
            $this->load_options();
        }
        return $this->options;
    }

    /**
     * Set a single option value.
     *
     * @param string $key   Option key.
     * @param mixed  $value Option value.
     * @return bool True on success, false on failure.
     */
    public function set( $key, $value ) {
        if ( null === $this->options ) {
            $this->load_options();
        }

        $this->options[ $key ] = $value;
        return $this->save();
    }

    /**
     * Update multiple options.
     *
     * @param array $options Key-value pairs of options to update.
     * @return bool True on success, false on failure.
     */
    public function update( $options ) {
        if ( null === $this->options ) {
            $this->load_options();
        }

        $this->options = wp_parse_args( $options, $this->options );
        return $this->save();
    }

    /**
     * Delete an option.
     *
     * @param string $key Option key.
     * @return bool True on success, false on failure.
     */
    public function delete( $key ) {
        if ( null === $this->options ) {
            $this->load_options();
        }

        if ( isset( $this->options[ $key ] ) ) {
            unset( $this->options[ $key ] );
            return $this->save();
        }

        return false;
    }

    /**
     * Save options to database.
     *
     * @return bool True on success, false on failure.
     */
    private function save() {
        return update_option( self::OPTION_NAME, $this->options );
    }

    /**
     * Reset options to defaults.
     *
     * @return bool True on success, false on failure.
     */
    public function reset() {
        $this->options = $this->defaults;
        return $this->save();
    }

    /**
     * Get defaults.
     *
     * @return array Default options.
     */
    public function get_defaults() {
        return $this->defaults;
    }

    /**
     * Add a glossary.
     *
     * @param int $page_id WordPress page ID.
     * @return bool True on success, false on failure.
     */
    public function add_glossary( $page_id ) {
        $glossaries = $this->get( 'glossaries', array() );

        // Check if already exists.
        foreach ( $glossaries as $glossary ) {
            if ( isset( $glossary['page_id'] ) && absint( $glossary['page_id'] ) === absint( $page_id ) ) {
                return false; // Already exists.
            }
        }

        // Add new glossary.
        $glossaries[] = array(
            'page_id'    => absint( $page_id ),
            'navigation' => true,
            'active'     => true,
            'added'      => current_time( 'mysql' ),
        );

        return $this->set( 'glossaries', $glossaries );
    }

    /**
     * Remove a glossary.
     *
     * @param int $page_id WordPress page ID.
     * @return bool True on success, false on failure.
     */
    public function remove_glossary( $page_id ) {
        $glossaries = $this->get( 'glossaries', array() );
        $page_id    = absint( $page_id );

        $filtered = array_filter(
            $glossaries,
            function ( $glossary ) use ( $page_id ) {
                return isset( $glossary['page_id'] ) && absint( $glossary['page_id'] ) !== $page_id;
            }
        );

        // Re-index array.
        $filtered = array_values( $filtered );

        // Clear cache for this glossary.
        azgl()->clear_glossary_cache( $page_id );

        return $this->set( 'glossaries', $filtered );
    }

    /**
     * Get a specific glossary by page ID.
     *
     * @param int $page_id WordPress page ID.
     * @return array|null Glossary data or null if not found.
     */
    public function get_glossary( $page_id ) {
        $glossaries = $this->get( 'glossaries', array() );
        $page_id    = absint( $page_id );

        foreach ( $glossaries as $glossary ) {
            if ( isset( $glossary['page_id'] ) && absint( $glossary['page_id'] ) === $page_id ) {
                return $glossary;
            }
        }

        return null;
    }

    /**
     * Update a glossary's settings.
     *
     * @param int   $page_id  WordPress page ID.
     * @param array $settings Settings to update.
     * @return bool True on success, false on failure.
     */
    public function update_glossary( $page_id, $settings ) {
        $glossaries = $this->get( 'glossaries', array() );
        $page_id    = absint( $page_id );
        $found      = false;

        foreach ( $glossaries as $key => $glossary ) {
            if ( isset( $glossary['page_id'] ) && absint( $glossary['page_id'] ) === $page_id ) {
                $glossaries[ $key ] = wp_parse_args( $settings, $glossary );
                $found              = true;
                break;
            }
        }

        if ( ! $found ) {
            return false;
        }

        return $this->set( 'glossaries', $glossaries );
    }

    /**
     * Check if a page is a glossary.
     *
     * @param int $page_id WordPress page ID.
     * @return bool True if page is a glossary.
     */
    public function is_glossary_page( $page_id ) {
        return null !== $this->get_glossary( $page_id );
    }

    /**
     * Get active glossaries.
     *
     * @return array Active glossaries.
     */
    public function get_active_glossaries() {
        $glossaries = $this->get( 'glossaries', array() );

        return array_filter(
            $glossaries,
            function ( $glossary ) {
                return ! empty( $glossary['active'] );
            }
        );
    }

    /**
     * Get available color schemes.
     *
     * Lite: 2 schemes (Emerald, Slate)
     * Pro: 6 schemes + Custom colors
     *
     * @return array Color schemes with labels.
     */
    public function get_color_schemes() {
        // Lite version: 2 color schemes.
        return array(
            'emerald' => __( 'Emerald', '3task-glossary' ),
            'slate'   => __( 'Slate', '3task-glossary' ),
        );
    }

    /**
     * Get available navigation styles.
     *
     * Lite: 2 styles (Buttons, Pills)
     * Pro: 3 styles + Custom CSS
     *
     * @return array Navigation styles with labels.
     */
    public function get_nav_styles() {
        // Lite version: 2 navigation styles.
        return array(
            'buttons' => __( 'Buttons', '3task-glossary' ),
            'pills'   => __( 'Pills', '3task-glossary' ),
        );
    }

    /**
     * Get available dark mode options.
     *
     * @return array Dark mode options with labels.
     */
    public function get_dark_mode_options() {
        return array(
            'auto'  => __( 'Auto (System)', '3task-glossary' ),
            'light' => __( 'Light', '3task-glossary' ),
            'dark'  => __( 'Dark', '3task-glossary' ),
        );
    }

    /**
     * Get available inactive letter display modes.
     *
     * Accessibility options for how to handle letters without entries.
     *
     * @return array Inactive letter modes with labels and descriptions.
     */
    public function get_inactive_letter_modes() {
        return array(
            'accessible' => array(
                'label'       => __( 'Accessible (Recommended)', '3task-glossary' ),
                'description' => __( 'Screen readers announce "X, no entries"', '3task-glossary' ),
            ),
            'hidden'     => array(
                'label'       => __( 'Hidden for Screen Readers', '3task-glossary' ),
                'description' => __( 'Visible but ignored by screen readers', '3task-glossary' ),
            ),
            'show'       => array(
                'label'       => __( 'Show (Visual Only)', '3task-glossary' ),
                'description' => __( 'May cause accessibility issues', '3task-glossary' ),
            ),
            'none'       => array(
                'label'       => __( 'Hide Completely', '3task-glossary' ),
                'description' => __( 'Only show letters with entries', '3task-glossary' ),
            ),
        );
    }

    /**
     * Get available linking modes.
     *
     * @return array Linking modes with labels.
     */
    public function get_linking_modes() {
        return array(
            'disabled'  => __( 'Disabled', '3task-glossary' ),
            'selective' => __( 'Selective (Glossary pages only)', '3task-glossary' ),
            'all'       => __( 'All pages (Pro only)', '3task-glossary' ),
        );
    }

    /**
     * Get available languages.
     *
     * @return array Languages with labels.
     */
    public function get_available_languages() {
        return array(
            'auto'  => __( 'Auto (WordPress)', '3task-glossary' ),
            'en_US' => 'English',
            'de_DE' => 'Deutsch',
        );
    }
}
