<?php
/**
 * Shared release component: knowledge base mode for a glossary.
 *
 * Identical file in 3task Glossary and 3task Glossary Pro (like class-content.php).
 * Structure: KB home page > category pages (direct children) > articles (grandchildren).
 * Direct children without children of their own are listed under "General".
 *
 * @package 3Task_Glossary
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AZGL_KB', false ) ) {

    /**
     * Knowledge base mode.
     */
    final class AZGL_KB {

        /**
         * Plugin URL and version of the plugin that booted the KB (Free or Pro).
         *
         * @var array
         */
        private static $boot = array();

        /**
         * Register hooks once. Called by the frontend of Free or Pro.
         *
         * @param string $plugin_url Plugin base URL (with trailing slash).
         * @param string $version    Plugin version for asset URLs.
         */
        public static function boot( $plugin_url, $version ) {
            if ( self::$boot ) {
                return;
            }
            self::$boot = array( 'url' => $plugin_url, 'version' => $version );
            add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        }

        /**
         * Styles and rating script for KB pages. Called by the frontend after its own stylesheet.
         *
         * @param string $after_style Handle of the stylesheet with the color variables.
         */
        public static function enqueue_assets( $after_style ) {
            if ( ! self::$boot ) {
                return;
            }
            wp_enqueue_style( 'azgl-kb', self::$boot['url'] . 'assets/css/kb.css', array( $after_style ), self::$boot['version'] );
            if ( self::is_article( get_queried_object_id() ) ) {
                wp_enqueue_script( 'azgl-kb', self::$boot['url'] . 'assets/js/kb.js', array(), self::$boot['version'], true );
                wp_localize_script(
                    'azgl-kb',
                    'azglKb',
                    array(
                        'url'    => rest_url( 'azgl/v1/kb-vote' ),
                        'thanks' => __( 'Thank you for your feedback!', '3task-glossary' ),
                    )
                );
            }
        }

        /**
         * REST route for the "helpful" rating.
         */
        public static function register_routes() {
            register_rest_route(
                'azgl/v1',
                '/kb-vote',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( __CLASS__, 'rest_vote' ),
                    'permission_callback' => '__return_true', // Public, anonymous rating without personal data.
                    'args'                => array(
                        'article' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
                        'vote'    => array( 'required' => true, 'type' => 'string', 'enum' => array( 'yes', 'no' ) ),
                    ),
                )
            );
        }

        /**
         * Count one vote. Stores only two counters per article; no IP, no user agent, no cookie.
         *
         * @param WP_REST_Request $request Request.
         * @return WP_REST_Response|WP_Error
         */
        public static function rest_vote( $request ) {
            $id = absint( $request['article'] );
            if ( ! self::is_article( $id ) ) {
                return new WP_Error( 'azgl_kb_not_found', __( 'Article not found.', '3task-glossary' ), array( 'status' => 404 ) );
            }
            $key = 'yes' === $request['vote'] ? '_azgl_kb_yes' : '_azgl_kb_no';
            update_post_meta( $id, $key, absint( get_post_meta( $id, $key, true ) ) + 1 );
            $response = new WP_REST_Response( array( 'ok' => true ) );
            $response->header( 'Cache-Control', 'no-store' );
            return $response;
        }

        /**
         * Save mode and breadcrumb switch of one glossary. Caller checks nonce and capability.
         *
         * @param int    $page_id     Glossary page ID.
         * @param string $mode        glossary or kb.
         * @param bool   $breadcrumbs Show breadcrumbs in KB mode.
         * @return bool
         */
        public static function save_settings( $page_id, $mode, $breadcrumbs ) {
            $options = get_option( 'azgl_options', array() );
            foreach ( (array) ( $options['glossaries'] ?? array() ) as $key => $glossary ) {
                if ( isset( $glossary['page_id'] ) && absint( $glossary['page_id'] ) === absint( $page_id ) ) {
                    $options['glossaries'][ $key ]['mode']           = 'kb' === $mode ? 'kb' : 'glossary';
                    $options['glossaries'][ $key ]['kb_breadcrumbs'] = (bool) $breadcrumbs;
                    update_option( 'azgl_options', $options );
                    return true;
                }
            }
            return false;
        }

        /**
         * Admin table cell: mode switch for one glossary (posts azgl_action=save_glossary_mode).
         *
         * @param array  $glossary   Glossary settings.
         * @param string $nonce_html Nonce field of the calling admin page.
         * @return string
         */
        public static function admin_cell( $glossary, $nonce_html ) {
            $id    = absint( $glossary['page_id'] );
            $is_kb = 'kb' === ( $glossary['mode'] ?? '' );
            $out   = '<form method="post" class="azgl-inline-form azgl-mode-form">' . $nonce_html;
            $out  .= '<input type="hidden" name="azgl_action" value="save_glossary_mode"><input type="hidden" name="page_id" value="' . $id . '">';
            $out  .= '<label class="screen-reader-text" for="azgl-mode-' . $id . '">' . esc_html__( 'Mode', '3task-glossary' ) . '</label>';
            $out  .= '<span style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">';
            $out  .= '<select id="azgl-mode-' . $id . '" name="azgl_mode">';
            $out  .= '<option value="glossary"' . selected( ! $is_kb, true, false ) . '>' . esc_html__( 'A-Z glossary', '3task-glossary' ) . '</option>';
            $out  .= '<option value="kb"' . selected( $is_kb, true, false ) . '>' . esc_html__( 'Knowledge base', '3task-glossary' ) . '</option></select>';
            if ( $is_kb ) {
                $out .= '<label style="white-space:nowrap;"><input type="checkbox" name="azgl_kb_breadcrumbs" value="1"' . checked( ! isset( $glossary['kb_breadcrumbs'] ) || ! empty( $glossary['kb_breadcrumbs'] ), true, false ) . '> ' . esc_html__( 'Breadcrumbs', '3task-glossary' ) . '</label>';
            } else {
                $out .= '<input type="hidden" name="azgl_kb_breadcrumbs" value="1">';
            }
            $out .= '<button type="submit" class="button button-small">' . esc_html__( 'Save', '3task-glossary' ) . '</button></span>';
            if ( $is_kb ) {
                $stats = self::stats( $id );
                $out  .= '<p class="description">';
                /* translators: 1: number of categories, 2: number of articles, 3: "helpful" votes, 4: "not helpful" votes. */
                $out  .= esc_html( sprintf( __( '%1$d categories, %2$d articles. Rated helpful: %3$d, not helpful: %4$d.', '3task-glossary' ), $stats['categories'], $stats['articles'], $stats['yes'], $stats['no'] ) );
                $out  .= '</p>';
            }
            return $out . '</form>';
        }

        /**
         * Counts of a KB for the admin overview.
         *
         * @param int $root_id KB home page ID.
         * @return array categories, articles, yes, no.
         */
        public static function stats( $root_id ) {
            $stats = array( 'categories' => 0, 'articles' => 0, 'yes' => 0, 'no' => 0 );
            foreach ( self::tree( $root_id ) as $category ) {
                $stats['categories'] += $category['id'] ? 1 : 0;
                foreach ( $category['articles'] as $article ) {
                    $rating = self::rating( $article->ID );
                    ++$stats['articles'];
                    $stats['yes'] += $rating['yes'];
                    $stats['no']  += $rating['no'];
                }
            }
            return $stats;
        }

        /**
         * Vote counters of an article.
         *
         * @param int $post_id Article ID.
         * @return array yes and no.
         */
        public static function rating( $post_id ) {
            return array(
                'yes' => absint( get_post_meta( $post_id, '_azgl_kb_yes', true ) ),
                'no'  => absint( get_post_meta( $post_id, '_azgl_kb_no', true ) ),
            );
        }

        /**
         * Whether a page is a public KB article (not the home page, not a category).
         *
         * @param int $post_id Page ID.
         * @return bool
         */
        public static function is_article( $post_id ) {
            $post = get_post( $post_id );
            $root = $post && 'page' === $post->post_type && AZGL_Content::public_page( $post ) ? self::root_of( $post->ID ) : 0;
            if ( ! $root || $root === $post->ID ) {
                return false;
            }
            return $post->post_parent !== $root || ! self::children( $post->ID );
        }

        /**
         * Glossary settings for a page, or null.
         *
         * @param int $page_id Page ID.
         * @return array|null
         */
        private static function glossary( $page_id ) {
            $options = get_option( 'azgl_options', array() );
            foreach ( (array) ( $options['glossaries'] ?? array() ) as $glossary ) {
                if ( isset( $glossary['page_id'] ) && absint( $glossary['page_id'] ) === absint( $page_id ) ) {
                    return $glossary;
                }
            }
            return null;
        }

        /**
         * Whether a glossary page runs in knowledge base mode.
         *
         * @param int $page_id Page ID.
         * @return bool
         */
        public static function is_kb( $page_id ) {
            $glossary = self::glossary( $page_id );
            return $glossary && ! empty( $glossary['active'] ) && 'kb' === ( $glossary['mode'] ?? '' );
        }

        /**
         * KB home page ID for a page inside a knowledge base (home, category or article), else 0.
         *
         * @param int $post_id Page ID.
         * @return int
         */
        public static function root_of( $post_id ) {
            $post = get_post( $post_id );
            for ( $depth = 0; $post && $depth < 3; ++$depth ) {
                if ( self::is_kb( $post->ID ) ) {
                    return AZGL_Content::public_page( $post->ID ) ? $post->ID : 0;
                }
                $post = $post->post_parent ? get_post( $post->post_parent ) : null;
            }
            return 0;
        }

        /**
         * Render any KB page: home, category or article.
         *
         * @param int    $post_id Page ID.
         * @param string $content Page content.
         * @return string
         */
        public static function render( $post_id, $content ) {
            $root = self::root_of( $post_id );
            if ( ! $root ) {
                return $content;
            }
            if ( $root === (int) $post_id ) {
                return self::render_home( $root, $content );
            }
            $crumbs = self::breadcrumbs_on( $root ) ? self::render_breadcrumbs( $post_id ) : '';
            $post   = get_post( $post_id );
            $kids   = $post->post_parent === $root ? self::children( $post_id ) : array();
            if ( $kids ) {
                return $crumbs . $content . self::render_list( $kids );
            }
            $toc = self::toc( $content );
            $out = $crumbs;
            if ( count( $toc['items'] ) >= 2 ) {
                $out .= '<nav class="azgl-kb-toc" aria-label="' . esc_attr__( 'Contents', '3task-glossary' ) . '"><p class="azgl-kb-toc-title">' . esc_html__( 'Contents', '3task-glossary' ) . '</p><ol>';
                $open_item = false;
                $open_sub  = false;
                foreach ( $toc['items'] as $item ) {
                    $link = '<a class="azgl-kb-toc-link" href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';
                    if ( 3 === $item['level'] && $open_item ) {
                        $out     .= $open_sub ? '' : '<ol>';
                        $open_sub = true;
                        $out     .= '<li class="azgl-kb-toc-3">' . $link . '</li>';
                        continue;
                    }
                    $out      .= ( $open_sub ? '</ol>' : '' ) . ( $open_item ? '</li>' : '' ) . '<li class="azgl-kb-toc-2">' . $link;
                    $open_sub  = false;
                    $open_item = true;
                }
                $out .= ( $open_sub ? '</ol>' : '' ) . ( $open_item ? '</li>' : '' ) . '</ol></nav>';
            }
            $out    .= $toc['html'];
            $related = array_filter( self::children( $post->post_parent ), static function ( $page ) use ( $post, $root ) {
                return $page->ID !== $post->ID && ( $post->post_parent !== $root || ! self::children( $page->ID ) );
            } );
            if ( $related ) {
                $out .= '<aside class="azgl-kb-related"><h2>' . esc_html__( 'Related articles', '3task-glossary' ) . '</h2><ul>';
                foreach ( array_slice( $related, 0, 5 ) as $page ) {
                    $out .= '<li><a class="azgl-kb-related-link" href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a></li>';
                }
                $out .= '</ul></aside>';
            }
            $out .= '<div class="azgl-kb-rating" data-azgl-article="' . absint( $post_id ) . '"><p class="azgl-kb-rating-question">' . esc_html__( 'Was this article helpful?', '3task-glossary' ) . '</p>';
            $out .= '<button type="button" class="azgl-kb-vote" data-azgl-vote="yes">' . esc_html__( 'Yes', '3task-glossary' ) . '</button> ';
            $out .= '<button type="button" class="azgl-kb-vote" data-azgl-vote="no">' . esc_html__( 'No', '3task-glossary' ) . '</button></div>';
            return $out;
        }

        /**
         * Whether breadcrumbs are switched on for a KB (default on).
         *
         * @param int $root KB home page ID.
         * @return bool
         */
        private static function breadcrumbs_on( $root ) {
            $glossary = self::glossary( $root );
            return ! isset( $glossary['kb_breadcrumbs'] ) || ! empty( $glossary['kb_breadcrumbs'] );
        }

        /**
         * Whether an SEO plugin that prints its own breadcrumb schema is active.
         *
         * @return bool
         */
        private static function seo_plugin_active() {
            return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) || defined( 'AIOSEO_VERSION' );
        }

        /**
         * Breadcrumb trail from the KB home down to the page.
         *
         * @param int $post_id Page ID.
         * @return array List of title and url.
         */
        private static function trail( $post_id ) {
            $root  = self::root_of( $post_id );
            $trail = array();
            for ( $post = get_post( $post_id ); $post; $post = $post->post_parent ? get_post( $post->post_parent ) : null ) {
                array_unshift( $trail, array( 'title' => get_the_title( $post ), 'url' => get_permalink( $post ) ) );
                if ( $post->ID === $root ) {
                    break;
                }
            }
            return $trail;
        }

        /**
         * Visible breadcrumbs.
         *
         * @param int $post_id Page ID.
         * @return string
         */
        private static function render_breadcrumbs( $post_id ) {
            $trail = self::trail( $post_id );
            $last  = count( $trail ) - 1;
            $out   = '<nav class="azgl-kb-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', '3task-glossary' ) . '"><ol>';
            foreach ( $trail as $i => $crumb ) {
                $out .= $i === $last
                    ? '<li class="azgl-kb-crumb" aria-current="page">' . esc_html( $crumb['title'] ) . '</li>'
                    : '<li class="azgl-kb-crumb"><a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['title'] ) . '</a></li>';
            }
            return $out . '</ol></nav>';
        }

        /**
         * Print BreadcrumbList JSON-LD for a KB page (not on the home page, not next to an SEO plugin).
         *
         * @param int $post_id Page ID.
         */
        public static function print_schema( $post_id ) {
            $root = self::root_of( $post_id );
            if ( ! $root || $root === (int) $post_id || ! self::breadcrumbs_on( $root ) || self::seo_plugin_active() ) {
                return;
            }
            $trail = self::trail( $post_id );
            $items = array();
            foreach ( $trail as $i => $crumb ) {
                $item = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb['title'] );
                if ( $i < count( $trail ) - 1 ) {
                    $item['item'] = $crumb['url'];
                }
                $items[] = $item;
            }
            $schema = array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items );
            echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
        }

        /**
         * Article list of a category page.
         *
         * @param WP_Post[] $articles Articles.
         * @return string
         */
        private static function render_list( $articles ) {
            $out = '<ul class="azgl-kb-articles">';
            foreach ( $articles as $article ) {
                $out .= '<li><a class="azgl-kb-article" href="' . esc_url( get_permalink( $article ) ) . '">' . esc_html( get_the_title( $article ) ) . '</a></li>';
            }
            return $out . '</ul>';
        }

        /**
         * Public child pages, ordered by page order, then title.
         *
         * @param int|int[] $parents Parent page ID(s).
         * @return WP_Post[]
         */
        private static function children( $parents ) {
            $parents = array_filter( array_map( 'absint', (array) $parents ) );
            if ( ! $parents ) {
                return array();
            }
            $pages = get_posts(
                array(
                    'post_type'       => 'page',
                    'post_status'     => 'publish',
                    'has_password'    => false,
                    'post_parent__in' => $parents,
                    'posts_per_page'  => -1,
                    'orderby'         => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
                )
            );
            return array_values( array_filter( $pages, array( 'AZGL_Content', 'public_page' ) ) );
        }

        /**
         * Category tree of a knowledge base.
         *
         * @param int $root_id KB home page ID.
         * @return array List of categories: id (0 for General), title, url, articles (WP_Post[]).
         */
        public static function tree( $root_id ) {
            if ( ! AZGL_Content::public_page( $root_id ) ) {
                return array();
            }
            $top      = self::children( $root_id );
            $by_child = array();
            foreach ( self::children( wp_list_pluck( $top, 'ID' ) ) as $page ) {
                $by_child[ $page->post_parent ][] = $page;
            }
            $tree    = array();
            $general = array();
            foreach ( $top as $page ) {
                if ( empty( $by_child[ $page->ID ] ) ) {
                    $general[] = $page;
                    continue;
                }
                $tree[] = array(
                    'id'       => $page->ID,
                    'title'    => get_the_title( $page ),
                    'url'      => get_permalink( $page ),
                    'articles' => $by_child[ $page->ID ],
                );
            }
            if ( $general ) {
                $tree[] = array(
                    'id'       => 0,
                    'title'    => __( 'General', '3task-glossary' ),
                    'url'      => '',
                    'articles' => $general,
                );
            }
            return $tree;
        }

        /**
         * Table of contents from H2/H3 headings; adds missing anchor IDs.
         *
         * @param string $html Article HTML.
         * @return array html (with anchors) and items (level, id, text).
         */
        public static function toc( $html ) {
            $pattern = '~<h([23])\b([^>]*)>(.*?)</h\1>~is';
            preg_match_all( '~<h[1-6]\b[^>]*\bid\s*=\s*["\']([^"\']+)["\']~i', $html, $existing );
            $used   = array_fill_keys( $existing[1], true );
            $items  = array();
            $number = 0;
            $html   = preg_replace_callback(
                $pattern,
                static function ( $m ) use ( &$used, &$items, &$number ) {
                    ++$number;
                    $text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $m[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
                    if ( '' === $text ) {
                        return $m[0];
                    }
                    if ( preg_match( '~\bid\s*=\s*["\']([^"\']+)["\']~i', $m[2], $own ) ) {
                        $items[] = array( 'level' => (int) $m[1], 'id' => $own[1], 'text' => $text );
                        return $m[0];
                    }
                    $base = sanitize_title( $text );
                    if ( '' === $base || false !== strpos( $base, '%' ) ) {
                        $base = 'azgl-toc-' . $number;
                    }
                    $id = $base;
                    for ( $i = 2; isset( $used[ $id ] ); ++$i ) {
                        $id = $base . '-' . $i;
                    }
                    $used[ $id ] = true;
                    $items[]     = array( 'level' => (int) $m[1], 'id' => $id, 'text' => $text );
                    return '<h' . $m[1] . $m[2] . ' id="' . esc_attr( $id ) . '">' . $m[3] . '</h' . $m[1] . '>';
                },
                $html
            );
            return array( 'html' => $html, 'items' => $items );
        }

        /**
         * Search the articles of a KB in title and text. Title hits first, then KB order.
         *
         * @param int    $root_id KB home page ID.
         * @param string $query   Search term.
         * @param int    $limit   Maximum number of hits.
         * @return WP_Post[]
         */
        public static function search( $root_id, $query, $limit = 20 ) {
            $query = trim( (string) $query );
            if ( '' === $query ) {
                return array();
            }
            $title = array();
            $text  = array();
            foreach ( self::tree( $root_id ) as $category ) {
                foreach ( $category['articles'] as $article ) {
                    if ( false !== mb_stripos( get_the_title( $article ), $query, 0, 'UTF-8' ) ) {
                        $title[] = $article;
                    } elseif ( false !== mb_stripos( html_entity_decode( wp_strip_all_tags( $article->post_content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $query, 0, 'UTF-8' ) ) {
                        $text[] = $article;
                    }
                }
            }
            return array_slice( array_merge( $title, $text ), 0, max( 1, (int) $limit ) );
        }

        /**
         * Render the KB home page.
         *
         * @param int    $root_id KB home page ID.
         * @param string $content Page content.
         * @return string
         */
        public static function render_home( $root_id, $content ) {
            $options = get_option( 'azgl_options', array() );
            $scheme  = $options['color_scheme'] ?? 'emerald';
            // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public read-only search form; the term is only compared and escaped on output.
            $query = isset( $_GET['azgl_kb_s'] ) ? trim( mb_substr( wp_unslash( (string) $_GET['azgl_kb_s'] ), 0, 100 ) ) : '';
            // phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $out   = '<div class="azgl-kb azgl-scheme-' . esc_attr( $scheme ) . '">';
            $out  .= '<form class="azgl-kb-search" role="search" method="get" action="' . esc_url( get_permalink( $root_id ) ) . '">';
            $out  .= '<label class="screen-reader-text" for="azgl-kb-s-' . absint( $root_id ) . '">' . esc_html__( 'Search articles', '3task-glossary' ) . '</label>';
            $out  .= '<input type="search" id="azgl-kb-s-' . absint( $root_id ) . '" name="azgl_kb_s" value="' . esc_attr( $query ) . '" placeholder="' . esc_attr__( 'Search articles', '3task-glossary' ) . '">';
            $out  .= '<button type="submit">' . esc_html__( 'Search', '3task-glossary' ) . '</button></form>';
            if ( '' !== $query ) {
                $hits = self::search( $root_id, $query );
                /* translators: %s: search term. */
                $out .= '<section class="azgl-kb-results"><h2>' . esc_html( sprintf( __( 'Results for "%s"', '3task-glossary' ), $query ) ) . '</h2>';
                if ( $hits ) {
                    $out .= '<ul>';
                    foreach ( $hits as $hit ) {
                        $out .= '<li><a class="azgl-kb-result" href="' . esc_url( get_permalink( $hit ) ) . '">' . esc_html( get_the_title( $hit ) ) . '</a></li>';
                    }
                    $out .= '</ul>';
                } else {
                    $out .= '<p class="azgl-kb-no-results">' . esc_html__( 'No articles found.', '3task-glossary' ) . '</p>';
                }
                $out .= '</section>';
            }
            $out .= '<div class="azgl-kb-categories">';
            foreach ( self::tree( $root_id ) as $category ) {
                $count = count( $category['articles'] );
                $title = $category['url'] ? '<a href="' . esc_url( $category['url'] ) . '">' . esc_html( $category['title'] ) . '</a>' : esc_html( $category['title'] );
                $out  .= '<section class="azgl-kb-category">';
                $out  .= '<h2 class="azgl-kb-category-title">' . $title . '</h2>';
                /* translators: %d: number of articles in a knowledge base category. */
                $out .= '<span class="azgl-kb-count">' . esc_html( sprintf( _n( '%d article', '%d articles', $count, '3task-glossary' ), $count ) ) . '</span>';
                $out .= self::render_list( $category['articles'] ) . '</section>';
            }
            return $content . $out . '</div></div>';
        }
    }
}
