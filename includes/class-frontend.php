<?php
/**
 * 3task Glossary Frontend Handler
 *
 * Renders A-Z navigation, entry lists, and optional credit link.
 *
 * @package 3Task_Glossary
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend Class
 *
 * @since 2.0.0
 */
class AZ_Glossary_Frontend {

    /**
     * Options handler.
     *
     * @var AZ_Glossary_Options
     */
    private $options;

    /**
     * Constructor.
     *
     * @param AZ_Glossary_Options $options Options handler.
     */
    public function __construct( $options ) {
        $this->options = $options;
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_filter( 'the_content', array( $this, 'process_content' ), 15 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_head', array( $this, 'output_schema_markup' ) );
    }

    /**
     * Output Schema.org DefinedTerm markup for glossary entries.
     *
     * @since 2.3.0
     */
    public function output_schema_markup() {
        if ( ! is_singular( 'page' ) ) {
            return;
        }

        // Check if schema is enabled.
        if ( ! $this->options->get( 'enable_schema', true ) ) {
            return;
        }

        global $post;
        if ( ! $post || ! $post->post_parent ) {
            return;
        }

        // Check if this is a glossary entry (child of glossary page).
        $parent_glossary = $this->options->get_glossary( $post->post_parent );
        if ( ! $parent_glossary || empty( $parent_glossary['active'] ) ) {
            return;
        }

        $parent_page = get_post( $post->post_parent );
        $description = get_the_excerpt( $post );
        if ( empty( $description ) ) {
            $description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
        }

        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'DefinedTerm',
            'name'             => get_the_title( $post ),
            'description'      => $description,
            'url'              => get_permalink( $post ),
            'inDefinedTermSet' => array(
                '@type' => 'DefinedTermSet',
                'name'  => $parent_page ? $parent_page->post_title : '',
                'url'   => $parent_page ? get_permalink( $parent_page ) : '',
            ),
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_assets() {
        if ( ! is_singular( 'page' ) ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        // Check if this is a glossary page or child of one.
        $is_glossary = $this->options->is_glossary_page( $post->ID );
        $is_entry    = $post->post_parent && $this->options->is_glossary_page( $post->post_parent );

        if ( ! $is_glossary && ! $is_entry ) {
            return;
        }

        wp_enqueue_style(
            'azgl-frontend',
            AZGL_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            AZGL_VERSION
        );

        // Add color scheme and dark mode classes.
        $color_scheme = $this->options->get( 'color_scheme', 'emerald' );
        $dark_mode    = $this->options->get( 'dark_mode', 'auto' );

        // Inline CSS for color scheme variables.
        $inline_css = $this->get_color_scheme_css( $color_scheme, $dark_mode );
        wp_add_inline_style( 'azgl-frontend', $inline_css );

        // Load frontend JS for Print functionality (v2.3.0).
        if ( $is_glossary ) {
            wp_enqueue_script(
                'azgl-frontend',
                AZGL_PLUGIN_URL . 'assets/js/frontend.js',
                array(),
                AZGL_VERSION,
                true
            );
        }
    }

    /**
     * Process content.
     *
     * @param string $content Post content.
     * @return string Processed content.
     */
    public function process_content( $content ) {
        // Only on frontend single pages.
        if ( is_admin() || ! is_singular( 'page' ) ) {
            return $content;
        }

        global $post;
        if ( ! $post ) {
            return $content;
        }

        // Check if this is a glossary page.
        $glossary = $this->options->get_glossary( $post->ID );
        if ( $glossary && ! empty( $glossary['active'] ) ) {
            return $this->render_glossary_page( $post->ID, $glossary, $content );
        }

        // Check if this is a child of a glossary page.
        if ( $post->post_parent ) {
            $parent_glossary = $this->options->get_glossary( $post->post_parent );
            if ( $parent_glossary && ! empty( $parent_glossary['active'] ) ) {
                return $this->render_entry_page( $post->post_parent, $parent_glossary, $content );
            }
        }

        return $content;
    }

    /**
     * Render glossary page with A-Z navigation.
     *
     * @param int    $page_id  Glossary page ID.
     * @param array  $glossary Glossary settings.
     * @param string $content  Original content.
     * @return string Modified content.
     */
    private function render_glossary_page( $page_id, $glossary, $content ) {
        $entries = azgl()->get_glossary_entries( $page_id );

        if ( empty( $entries ) ) {
            $output = '<div class="azgl-empty-glossary">';
            $output .= '<p>' . esc_html__( 'No glossary entries yet. Create child pages under this page to add entries.', '3task-glossary' ) . '</p>';
            $output .= '</div>';
            return $content . $output;
        }

        $alphabetic_list = $this->get_alphabetic_list( $entries );
        $nav_style       = $this->options->get( 'nav_style', 'buttons' );
        $inactive_mode   = $this->options->get( 'inactive_letters', 'accessible' );
        $color_scheme    = $this->options->get( 'color_scheme', 'emerald' );

        // Migrate old boolean values.
        if ( true === $inactive_mode || '1' === $inactive_mode ) {
            $inactive_mode = 'show';
        } elseif ( false === $inactive_mode || '' === $inactive_mode ) {
            $inactive_mode = 'none';
        }

        $output = '';

        // A-Z Navigation.
        if ( ! empty( $glossary['navigation'] ) ) {
            $output .= $this->render_navigation( $alphabetic_list, $nav_style, $color_scheme, $inactive_mode );
        }

        // Entry list.
        $output .= '<div class="azgl-entries azgl-scheme-' . esc_attr( $color_scheme ) . '">';

        foreach ( $alphabetic_list as $letter => $letter_entries ) {
            if ( empty( $letter_entries ) ) {
                continue;
            }

            $output .= '<section class="azgl-section" id="azgl-' . esc_attr( strtolower( $letter ) ) . '">';
            $output .= '<h2 class="azgl-letter">' . esc_html( $letter ) . '</h2>';
            $output .= '<ul class="azgl-list">';

            foreach ( $letter_entries as $entry ) {
                $output .= '<li class="azgl-item">';
                $output .= '<a href="' . esc_url( $entry['url'] ) . '">' . esc_html( $entry['title'] ) . '</a>';
                $output .= '</li>';
            }

            $output .= '</ul>';
            $output .= '</section>';
        }

        $output .= '</div>';

        // Action buttons (Print) - only if enabled.
        if ( $this->options->get( 'enable_print', true ) ) {
            $output .= '<div class="azgl-action-buttons">';
            $output .= '<button type="button" class="azgl-print-btn">';
            $output .= '<span class="btn-icon">🖨️</span>';
            $output .= esc_html__( 'Print Glossary', '3task-glossary' );
            $output .= '</button>';
            $output .= '</div>';
        }

        // Credit link (only if supporter mode enabled).
        $output .= $this->render_credit_link();

        return $content . $output;
    }

    /**
     * Render entry page with navigation back to glossary.
     *
     * @param int    $glossary_id Glossary page ID.
     * @param array  $glossary    Glossary settings.
     * @param string $content     Original content.
     * @return string Modified content.
     */
    private function render_entry_page( $glossary_id, $glossary, $content ) {
        global $post;
        $output = '';

        // Add sub-navigation.
        if ( ! empty( $glossary['navigation'] ) ) {
            $entries         = azgl()->get_glossary_entries( $glossary_id );
            $alphabetic_list = $this->get_alphabetic_list( $entries );
            $nav_style       = $this->options->get( 'nav_style', 'buttons' );
            $color_scheme    = $this->options->get( 'color_scheme', 'emerald' );

            $output .= $this->render_sub_navigation( $glossary_id, $alphabetic_list, $nav_style, $color_scheme );
        }

        $after_content = '';

        // Reverse Links (v2.3.0).
        $after_content .= $this->render_reverse_links( $post->ID );

        return $output . $content . $after_content;
    }

    /**
     * Render reverse links section showing posts that link to this term.
     *
     * @since 2.3.0
     * @param int $post_id Post ID.
     * @return string HTML.
     */
    private function render_reverse_links( $post_id ) {
        global $wpdb;

        $post_url = get_permalink( $post_id );
        $post_url_relative = wp_parse_url( $post_url, PHP_URL_PATH );

        // Search for posts that contain a link to this term.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance optimized query.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type IN ('post', 'page')
                AND ID != %d
                AND (post_content LIKE %s OR post_content LIKE %s)
                LIMIT 10",
                $post_id,
                '%' . $wpdb->esc_like( $post_url ) . '%',
                '%' . $wpdb->esc_like( $post_url_relative ) . '%'
            )
        );

        if ( empty( $results ) ) {
            return '';
        }

        $output  = '<div class="azgl-reverse-links">';
        $output .= '<h4><span>🔗</span> ' . esc_html__( 'This term is mentioned in:', '3task-glossary' ) . '</h4>';
        $output .= '<ul>';

        foreach ( $results as $result ) {
            $type_label = 'page' === $result->post_type ? __( 'Page', '3task-glossary' ) : __( 'Post', '3task-glossary' );
            $output    .= '<li>';
            $output    .= '<a href="' . esc_url( get_permalink( $result->ID ) ) . '">' . esc_html( $result->post_title ) . '</a>';
            $output    .= '<span class="post-type">' . esc_html( $type_label ) . '</span>';
            $output    .= '</li>';
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render A-Z navigation.
     *
     * @param array  $alphabetic_list Alphabetic list of entries.
     * @param string $nav_style       Navigation style.
     * @param string $color_scheme    Color scheme.
     * @param string $inactive_mode   Inactive letters display mode (accessible, hidden, show, none).
     * @return string HTML.
     */
    private function render_navigation( $alphabetic_list, $nav_style, $color_scheme, $inactive_mode ) {
        $output  = '<nav class="azgl-nav azgl-nav-' . esc_attr( $nav_style ) . ' azgl-scheme-' . esc_attr( $color_scheme ) . '" ';
        $output .= 'role="navigation" aria-label="' . esc_attr__( 'Alphabetical navigation', '3task-glossary' ) . '">';

        foreach ( $alphabetic_list as $letter => $entries ) {
            if ( ! empty( $entries ) ) {
                // Active letter - always a link.
                $output .= '<a href="#azgl-' . esc_attr( strtolower( $letter ) ) . '" class="azgl-nav-letter">';
                $output .= esc_html( $letter );
                $output .= '</a>';
            } else {
                // Inactive letter - render based on accessibility mode.
                $output .= $this->render_inactive_letter( $letter, $inactive_mode );
            }
        }

        $output .= '</nav>';

        return $output;
    }

    /**
     * Render an inactive letter based on accessibility mode.
     *
     * @param string $letter        The letter to render.
     * @param string $inactive_mode Display mode (accessible, hidden, show, none).
     * @return string HTML.
     */
    private function render_inactive_letter( $letter, $inactive_mode ) {
        switch ( $inactive_mode ) {
            case 'accessible':
                // Screen reader announces "X, no entries" - best for accessibility.
                return '<span class="azgl-nav-letter azgl-nav-inactive" aria-label="' .
                    /* translators: %s: Letter that has no glossary entries */
                    esc_attr( sprintf( __( '%s, no entries', '3task-glossary' ), $letter ) ) .
                    '" role="text">' . esc_html( $letter ) . '</span>';

            case 'hidden':
                // Visible but hidden from screen readers.
                return '<span class="azgl-nav-letter azgl-nav-inactive" aria-hidden="true">' .
                    esc_html( $letter ) . '</span>';

            case 'show':
                // Legacy mode - visual only, may cause accessibility issues.
                return '<span class="azgl-nav-letter azgl-nav-inactive">' .
                    esc_html( $letter ) . '</span>';

            case 'none':
            default:
                // Don't render inactive letters at all.
                return '';
        }
    }

    /**
     * Render sub-navigation for entry pages.
     *
     * @param int    $glossary_id     Glossary page ID.
     * @param array  $alphabetic_list Alphabetic list of entries.
     * @param string $nav_style       Navigation style.
     * @param string $color_scheme    Color scheme.
     * @return string HTML.
     */
    private function render_sub_navigation( $glossary_id, $alphabetic_list, $nav_style, $color_scheme ) {
        $permalink = get_permalink( $glossary_id );

        $output  = '<nav class="azgl-nav azgl-nav-sub azgl-nav-' . esc_attr( $nav_style ) . ' azgl-scheme-' . esc_attr( $color_scheme ) . '">';
        $output .= '<a href="' . esc_url( $permalink ) . '" class="azgl-back-link">';
        $output .= '<span class="dashicons dashicons-arrow-left-alt2"></span> ';
        $output .= esc_html__( 'Back to Glossary', '3task-glossary' );
        $output .= '</a>';
        $output .= '<span class="azgl-nav-separator">|</span>';

        foreach ( $alphabetic_list as $letter => $entries ) {
            if ( ! empty( $entries ) ) {
                $output .= '<a href="' . esc_url( $permalink ) . '#azgl-' . esc_attr( strtolower( $letter ) ) . '" class="azgl-nav-letter">';
                $output .= esc_html( $letter );
                $output .= '</a>';
            }
        }

        $output .= '</nav>';

        return $output;
    }

    /**
     * Get alphabetic list from entries.
     *
     * @param array $entries Glossary entries.
     * @return array Alphabetic list.
     */
    private function get_alphabetic_list( $entries ) {
        // Initialize alphabet.
        $letters = array_merge( array( '#' ), range( 'A', 'Z' ) );
        $list    = array_fill_keys( $letters, array() );

        // Sort entries by first letter.
        foreach ( $entries as $entry ) {
            $initial          = $this->get_initial( $entry->post_title );
            $list[ $initial ][] = array(
                'id'    => $entry->ID,
                'title' => $entry->post_title,
                'url'   => get_permalink( $entry->ID ),
            );
        }

        return $list;
    }

    /**
     * Get initial letter with umlaut handling.
     *
     * @param string $string Input string.
     * @return string Initial letter.
     */
    private function get_initial( $string ) {
        $string = trim( $string );

        if ( empty( $string ) ) {
            return '#';
        }

        // German umlaut replacements.
        $replacements = array(
            'Ä' => 'A',
            'ä' => 'A',
            'Ö' => 'O',
            'ö' => 'O',
            'Ü' => 'U',
            'ü' => 'U',
            'ß' => 'S',
        );

        $first_char = mb_substr( $string, 0, 1, 'UTF-8' );

        if ( isset( $replacements[ $first_char ] ) ) {
            return $replacements[ $first_char ];
        }

        $first_char = strtoupper( $first_char );

        if ( preg_match( '/^[A-Z]$/', $first_char ) ) {
            return $first_char;
        }

        return '#';
    }

    /**
     * Render optional credit link.
     *
     * Only shown if user explicitly opts in via supporter mode.
     *
     * @return string HTML.
     */
    private function render_credit_link() {
        if ( ! azgl()->is_supporter() ) {
            return '';
        }

        $credit_url = $this->options->get( 'credit_link_url', 'https://www.3task.de' );

        return '<p class="azgl-credit">' .
               '<small>' . esc_html__( 'Glossary by', '3task-glossary' ) . ' ' .
               '<a href="' . esc_url( $credit_url ) . '" target="_blank" rel="noopener">3task</a>' .
               '</small></p>';
    }

    /**
     * Get color scheme CSS variables.
     *
     * @param string $scheme    Color scheme name.
     * @param string $dark_mode Dark mode setting.
     * @return string CSS.
     */
    private function get_color_scheme_css( $scheme, $dark_mode ) {
        $schemes = $this->get_color_schemes();
        $colors  = isset( $schemes[ $scheme ] ) ? $schemes[ $scheme ] : $schemes['emerald'];

        // Auto scheme: use theme colors if available.
        if ( 'auto' === $scheme ) {
            $css = ':root {
                --azgl-primary: var(--wp--preset--color--primary, #10b981);
                --azgl-hover: var(--wp--preset--color--secondary, #059669);
                --azgl-light: var(--wp--preset--color--tertiary, #d1fae5);
                --azgl-dark-text: var(--wp--preset--color--contrast, #065f46);
            }';
        } else {
            $css = ':root {
                --azgl-primary: ' . $colors['primary'] . ';
                --azgl-hover: ' . $colors['hover'] . ';
                --azgl-light: ' . $colors['light'] . ';
                --azgl-dark-text: ' . $colors['dark-text'] . ';
            }';
        }

        // Dark mode.
        $dark_css = '
            --azgl-bg: #1e293b;
            --azgl-bg-inactive: #334155;
            --azgl-text: #f1f5f9;
            --azgl-text-muted: #94a3b8;
            --azgl-border: #475569;
        ';

        if ( 'dark' === $dark_mode ) {
            $css .= ':root {' . $dark_css . '}';
        } elseif ( 'auto' === $dark_mode ) {
            $css .= '@media (prefers-color-scheme: dark) {:root {' . $dark_css . '}}';
        }

        return $css;
    }

    /**
     * Get available color schemes.
     *
     * Lite: 2 schemes (Emerald, Slate)
     * Pro: 6 schemes + Custom colors
     *
     * @return array Color schemes.
     */
    private function get_color_schemes() {
        return array(
            'emerald' => array(
                'primary'   => '#10b981',
                'hover'     => '#059669',
                'light'     => '#d1fae5',
                'dark-text' => '#065f46',
            ),
            'slate'   => array(
                'primary'   => '#64748b',
                'hover'     => '#475569',
                'light'     => '#f1f5f9',
                'dark-text' => '#334155',
            ),
        );
    }
}
