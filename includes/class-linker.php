<?php
/**
 * 3task Glossary Auto-Linker
 *
 * Automatically links glossary terms within content.
 *
 * @package 3Task_Glossary
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Linker Class
 *
 * @since 2.0.0
 */
class AZ_Glossary_Linker {

    /**
     * Options handler.
     *
     * @var AZ_Glossary_Options
     */
    private $options;

    /**
     * Cached entries per glossary.
     *
     * @var array
     */
    private $entries_cache = array();

    /**
     * Already linked terms (for "first only" mode).
     *
     * @var array
     */
    private $linked_terms = array();

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
        // Process content after frontend (priority 20).
        add_filter( 'the_content', array( $this, 'process_content' ), 20 );

        // Clear cache when page is updated.
        add_action( 'save_post_page', array( $this, 'maybe_clear_cache' ), 10, 2 );
    }

    /**
     * Process content and auto-link glossary terms.
     *
     * @param string $content Post content.
     * @return string Processed content.
     */
    public function process_content( $content ) {
        // Only on frontend single pages.
        if ( is_admin() || ! is_singular( 'page' ) ) {
            return $content;
        }

        // Check if linking is enabled.
        $linking_mode = $this->options->get( 'linking_mode', 'selective' );
        if ( 'disabled' === $linking_mode ) {
            return $content;
        }

        global $post;
        if ( ! $post ) {
            return $content;
        }

        // Find which glossary this content belongs to.
        $glossary_id = $this->get_glossary_for_post( $post->ID );
        if ( ! $glossary_id ) {
            return $content;
        }

        // Get entries for this glossary.
        $entries = $this->get_linkable_entries( $glossary_id );
        if ( empty( $entries ) ) {
            return $content;
        }

        // Reset linked terms for this content.
        $this->linked_terms = array();

        // Get linking options.
        $link_first_only = $this->options->get( 'link_first_only', true );
        $link_class      = $this->options->get( 'link_class', 'glossary-term' );
        $link_class      = sanitize_html_class( $link_class );

        // Process each entry.
        foreach ( $entries as $entry ) {
            // Don't link current post.
            if ( $entry['id'] === $post->ID ) {
                continue;
            }

            // Already linked? (if first_only is active).
            if ( $link_first_only && in_array( $entry['title'], $this->linked_terms, true ) ) {
                continue;
            }

            // Replace term in content.
            $content = $this->replace_term( $content, $entry, $link_class, $link_first_only );
        }

        return $content;
    }

    /**
     * Get glossary ID for a given post.
     *
     * @param int $post_id Post ID.
     * @return int|null Glossary ID or null.
     */
    private function get_glossary_for_post( $post_id ) {
        // Check if this is a glossary page.
        $glossary = $this->options->get_glossary( $post_id );
        if ( $glossary && ! empty( $glossary['active'] ) ) {
            return $post_id;
        }

        // Check if this is a child of a glossary page.
        $post = get_post( $post_id );
        if ( $post && $post->post_parent ) {
            $parent_glossary = $this->options->get_glossary( $post->post_parent );
            if ( $parent_glossary && ! empty( $parent_glossary['active'] ) ) {
                return $post->post_parent;
            }
        }

        return null;
    }

    /**
     * Get linkable entries for a glossary.
     *
     * Entries are sorted by title length (longest first) to prevent
     * partial matches when a shorter term is part of a longer one.
     *
     * @param int $glossary_id Glossary page ID.
     * @return array Entries with id, title, url.
     */
    private function get_linkable_entries( $glossary_id ) {
        // Check memory cache.
        if ( isset( $this->entries_cache[ $glossary_id ] ) ) {
            return $this->entries_cache[ $glossary_id ];
        }

        // Check transient cache.
        $cache_key = 'azgl_linker_' . $glossary_id;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && $this->options->get( 'cache_enabled', true ) ) {
            $this->entries_cache[ $glossary_id ] = $cached;
            return $cached;
        }

        // Get entries from main plugin.
        $raw_entries = azgl()->get_glossary_entries( $glossary_id );
        if ( empty( $raw_entries ) ) {
            return array();
        }

        // Prepare entries.
        $entries = array();
        foreach ( $raw_entries as $entry ) {
            $entries[] = array(
                'id'    => $entry->ID,
                'title' => $entry->post_title,
                'url'   => get_permalink( $entry->ID ),
            );
        }

        // Sort by title length (longest first).
        usort(
            $entries,
            function ( $a, $b ) {
                return mb_strlen( $b['title'] ) - mb_strlen( $a['title'] );
            }
        );

        // Cache in memory.
        $this->entries_cache[ $glossary_id ] = $entries;

        // Cache in transient.
        if ( $this->options->get( 'cache_enabled', true ) ) {
            set_transient( $cache_key, $entries, HOUR_IN_SECONDS );
        }

        return $entries;
    }

    /**
     * Replace term in content with link.
     *
     * Uses regex to find terms but avoids:
     * - Terms inside HTML tags
     * - Terms inside existing links
     * - Terms inside headings (h1-h6)
     * - Terms inside script/style tags
     *
     * @param string $content    Content to process.
     * @param array  $entry      Entry data (id, title, url).
     * @param string $link_class CSS class for link.
     * @param bool   $first_only Link first occurrence only.
     * @return string Processed content.
     */
    private function replace_term( $content, $entry, $link_class, $first_only ) {
        $term = preg_quote( $entry['title'], '~' );

        // Build pattern that avoids replacing inside tags/links/headings.
        // This pattern matches the term as a whole word, case-insensitive.
        $pattern = '~'
            . '(?<![<\w\-])'           // Not after < or word char or hyphen.
            . '(?![^<]*>)'             // Not inside a tag.
            . '\b(' . $term . ')\b'    // Whole word match.
            . '(?![^<]*</a>)'          // Not before closing </a>.
            . '(?![^<]*</h[1-6]>)'     // Not in headings.
            . '~iu';                   // Case-insensitive, Unicode.

        // Build replacement link.
        $replacement = '<a href="' . esc_url( $entry['url'] ) . '" '
                     . 'class="' . esc_attr( $link_class ) . '" '
                     . 'title="' . esc_attr( $entry['title'] ) . '">'
                     . '$1</a>';

        // Replace (once or all).
        $limit       = $first_only ? 1 : -1;
        $new_content = preg_replace( $pattern, $replacement, $content, $limit, $count );

        // Remember linked terms.
        if ( $count > 0 ) {
            $this->linked_terms[] = $entry['title'];
        }

        return null !== $new_content ? $new_content : $content;
    }

    /**
     * Maybe clear cache when a page is updated.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function maybe_clear_cache( $post_id, $post ) {
        // Check if this is a glossary page.
        $glossary = $this->options->get_glossary( $post_id );
        if ( $glossary ) {
            $this->clear_cache( $post_id );
            return;
        }

        // Check if parent is a glossary.
        if ( $post->post_parent ) {
            $parent_glossary = $this->options->get_glossary( $post->post_parent );
            if ( $parent_glossary ) {
                $this->clear_cache( $post->post_parent );
            }
        }
    }

    /**
     * Clear cache for a specific glossary.
     *
     * @param int $glossary_id Glossary page ID.
     */
    public function clear_cache( $glossary_id ) {
        delete_transient( 'azgl_linker_' . $glossary_id );
        unset( $this->entries_cache[ $glossary_id ] );

        // Also clear main glossary cache.
        azgl()->clear_glossary_cache( $glossary_id );
    }

    /**
     * Clear all linker caches.
     */
    public function clear_all_caches() {
        $glossaries = $this->options->get( 'glossaries', array() );
        foreach ( $glossaries as $glossary ) {
            if ( isset( $glossary['page_id'] ) ) {
                $this->clear_cache( $glossary['page_id'] );
            }
        }
        $this->entries_cache = array();
    }
}
