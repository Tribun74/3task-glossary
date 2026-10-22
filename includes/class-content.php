<?php
/** Shared release component for safe glossary text linking. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'AZGL_Content', false ) ) {
final class AZGL_Content {
    public static function public_page( $post ) {
        $seen = array();
        if ( is_object( $post ) ) { $post = $post->ID; }
        if ( ! is_numeric( $post ) || (int) $post <= 0 ) { return false; }
        while ( $post = get_post( $post ) ) {
            if ( isset( $seen[ $post->ID ] ) || 'publish' !== $post->post_status || '' !== $post->post_password ) { return false; }
            $seen[ $post->ID ] = true;
            if ( ! $post->post_parent ) { return true; }
            $post = $post->post_parent;
        }
        return false;
    }
    public static function language( $id ) {
        if ( function_exists( 'pll_get_post_language' ) ) { return (string) pll_get_post_language( $id ); }
        if ( has_filter( 'wpml_element_language_code' ) ) { return (string) apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => 'post_' . get_post_type( $id ) ) ); }
        return '';
    }
    public static function synonyms( $id ) {
        $value = get_post_meta( $id, '_azgl_synonyms', true );
        if ( '' === $value ) { $value = get_post_meta( $id, '_azgp_synonyms', true ); }
        return array_values( array_filter( array_map( 'trim', is_array( $value ) ? $value : explode( ',', (string) $value ) ) ) );
    }
    public static function link( $html, $entries, $class, $first = true, $case = false, $limit = 0, $current = 0 ) {
        $trie = array();
        foreach ( $entries as $entry ) {
            if ( (int) $entry['id'] === (int) $current ) { continue; }
            foreach ( array_merge( array( $entry['title'] ), $entry['synonyms'] ?? array() ) as $term ) {
                $term = html_entity_decode( trim( $term ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                if ( '' === $term ) { continue; }
                $node =& $trie;
                foreach ( preg_split( '//u', $term, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
                    $key = $case ? $char : mb_strtolower( $char, 'UTF-8' );
                    if ( ! isset( $node[ $key ] ) ) { $node[ $key ] = array(); }
                    $node =& $node[ $key ];
                }
                if ( ! isset( $node[''] ) ) { $node[''] = $entry; }
                unset( $node );
            }
        }
        if ( ! $trie ) { return $html; }
        $blocked = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'code', 'pre', 'textarea', 'button', 'select', 'option', 'svg', 'math', 'noscript' );
        $stack = array(); $linked = array(); $count = 0;
        // WordPress wp_html_split treats a quoted > as a tag end. Keep attributes intact.
        $pattern = <<<'REGEX'
~(<!--.*?(?:-->|$)|<!\[CDATA\[.*?(?:\]\]>|$)|<(?:/?[a-z][a-z0-9:-]*|!DOCTYPE\b|\?)(?:[^>"']|"[^"]*"|'[^']*')*>)~is
REGEX;
        $parts = preg_split( $pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE );
        if ( false === $parts ) { return $html; }
        foreach ( $parts as &$part ) {
            if ( '' === $part ) { continue; }
            if ( '<' === $part[0] ) {
                if ( preg_match( '~^<(/?)([a-z][a-z0-9:-]*)\b~i', $part, $tag ) ) {
                    $name = strtolower( $tag[2] );
                    if ( in_array( $name, $blocked, true ) ) {
                        if ( '/' === $tag[1] ) {
                            if ( $stack && end( $stack ) === $name ) { array_pop( $stack ); }
                        } elseif ( ( ! in_array( $name, array( 'svg', 'math' ), true ) || ! preg_match( '~/\s*>$~', $part ) ) && ( ! $stack || ! in_array( end( $stack ), array( 'script', 'style', 'textarea' ), true ) ) ) { $stack[] = $name; }
                    }
                }
                continue;
            }
            if ( $stack || ( $limit > 0 && $count >= $limit ) ) { continue; }
            // Preserve each original entity and character in the output; only insert anchor tags.
            preg_match_all( '/&(?:#[0-9]+|#x[0-9a-f]+|[a-z][a-z0-9]+);|./isu', $part, $matches );
            $units = $matches[0]; $chars = array_map( static function ( $u ) { return html_entity_decode( $u, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }, $units );
            $out = ''; $length = count( $chars );
            for ( $i = 0; $i < $length; ) {
                $best = null; $end = $i; $node = $trie;
                if ( 0 === $i || ! preg_match( '/[\p{L}\p{N}\p{M}_-]$/u', $chars[ $i - 1 ] ) ) {
                    for ( $j = $i; $j < $length; ++$j ) {
                        $key = $case ? $chars[ $j ] : mb_strtolower( $chars[ $j ], 'UTF-8' );
                        if ( ! isset( $node[ $key ] ) ) { break; }
                        $node = $node[ $key ];
                        if ( isset( $node[''] ) && ( ! $first || ! isset( $linked[ $node['']['id'] ] ) )
                            && ( $j + 1 === $length || ! preg_match( '/^[\p{L}\p{N}\p{M}_-]/u', $chars[ $j + 1 ] ) ) ) { $best = $node['']; $end = $j + 1; }
                    }
                }
                if ( $best && ( 0 === $limit || $count < $limit ) ) {
                    $out .= '<a href="' . esc_url( $best['url'] ) . '" class="' . esc_attr( $class ) . '" data-azgl-entry="' . absint( $best['id'] ) . '">' . implode( '', array_slice( $units, $i, $end - $i ) ) . '</a>';
                    $linked[ $best['id'] ] = true; ++$count; $i = $end;
                } else { $out .= $units[ $i++ ]; }
            }
            $part = $out;
        }
        unset( $part );
        return implode( '', $parts );
    }
}
}
