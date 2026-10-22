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
        add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );
        add_action( 'deleted_post', array( $this, 'clear_all_caches' ) );
        add_action( 'updated_post_meta', array( $this, 'meta_updated' ), 10, 4 );
        add_action( 'added_post_meta', array( $this, 'meta_updated' ), 10, 4 );
        add_action( 'deleted_post_meta', array( $this, 'meta_updated' ), 10, 4 );
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

        $content = AZGL_Content::link( $content, $entries, $link_class, $link_first_only, false, 0, $post->ID );

        return $content;
    }

    /**
     * Get glossary ID for a given post.
     *
     * @param int $post_id Post ID.
     * @return int|null Glossary ID or null.
     */
    private function get_glossary_for_post( $post_id ) {
        // Knowledge bases are no link source (their entries are categories).
        if ( AZGL_KB::root_of( $post_id ) ) {
            return null;
        }

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
        if ( ! AZGL_Content::public_page( $glossary_id ) ) { return array(); }
        // Check memory cache.
        if ( $this->options->get( 'cache_enabled', true ) && isset( $this->entries_cache[ $glossary_id ] ) ) {
            return array_values( array_filter( $this->entries_cache[ $glossary_id ], static function ( $entry ) { return AZGL_Content::public_page( $entry['id'] ); } ) );
        }

        // Check transient cache.
        $cache_key = 'azgl_linker_' . $glossary_id;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && $this->options->get( 'cache_enabled', true ) ) {
            $this->entries_cache[ $glossary_id ] = $cached;
            return array_values( array_filter( $cached, static function ( $entry ) { return AZGL_Content::public_page( $entry['id'] ); } ) );
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
        return AZGL_Content::link( $content, array( $entry ), $link_class, $first_only );
    }

    /**
     * Clear caches of the old and new parent when a page moves or changes.
     *
     * @param int     $id     Post ID.
     * @param WP_Post $after  Post after the update.
     * @param WP_Post $before Post before the update.
     */
    public function post_updated( $id, $after, $before ) {
        foreach ( array_unique( array( $id, $before->post_parent, $after->post_parent ) ) as $parent ) {
            if ( $parent ) { $this->clear_cache( $parent ); }
        }
    }

    /**
     * Clear the parent cache when synonyms change.
     *
     * @param int    $meta_id Meta ID.
     * @param int    $post_id Post ID.
     * @param string $key     Meta key.
     * @param mixed  $value   Meta value.
     */
    public function meta_updated( $meta_id, $post_id, $key, $value ) {
        if ( in_array( $key, array( '_azgl_synonyms', '_azgp_synonyms' ), true ) ) {
            $post = get_post( $post_id );
            if ( $post && $post->post_parent ) { $this->clear_cache( $post->post_parent ); }
        }
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
     * Determine whether the configured linker would link a source page to a target entry.
     *
     * @param WP_Post $source    Source page.
     * @param int     $target_id Target entry ID.
     * @return bool
     */
    public function links_to( $source, $target_id ) {
        $mode = $this->options->get( 'linking_mode', 'selective' );
        if ( 'disabled' === $mode || ! AZGL_Content::public_page( $source->ID ) ) { return false; }
        if ( 'page' !== $source->post_type ) { return false; }
        $ids = (array) $this->get_glossary_for_post( $source->ID );
        $entries = array();
        foreach ( $ids as $id ) { if ( $id ) { $entries = array_merge( $entries, $this->get_linkable_entries( $id ) ); } }
        $html = AZGL_Content::link( $source->post_content, $entries, 'glossary-term', $this->options->get( 'link_first_only', true ), false, 0, $source->ID );
        return false !== strpos( $html, 'data-azgl-entry="' . absint( $target_id ) . '"' );
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
