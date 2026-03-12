<?php
/**
 * 3task Glossary Sidebar Widget
 *
 * Displays A-Z glossary navigation in widget areas.
 *
 * @package 3Task_Glossary
 * @since 2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Glossary Widget Class
 *
 * @since 2.3.0
 */
class AZ_Glossary_Widget extends WP_Widget {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            'azgl_glossary_widget',
            __( '3task Glossary', '3task-glossary' ),
            array(
                'description'                 => __( 'Display A-Z glossary navigation in your sidebar.', '3task-glossary' ),
                'customize_selective_refresh' => true,
            )
        );
    }

    /**
     * Output the widget content.
     *
     * @param array $args     Widget arguments.
     * @param array $instance Widget instance settings.
     */
    public function widget( $args, $instance ) {
        $title       = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Glossary', '3task-glossary' );
        $glossary_id = ! empty( $instance['glossary_id'] ) ? absint( $instance['glossary_id'] ) : 0;
        $show_count  = ! empty( $instance['show_count'] );
        $display     = ! empty( $instance['display'] ) ? $instance['display'] : 'az';

        // Get glossary.
        if ( 0 === $glossary_id ) {
            // Use first active glossary.
            $options    = azgl()->get_options();
            $glossaries = $options->get( 'glossaries', array() );
            foreach ( $glossaries as $glossary ) {
                if ( ! empty( $glossary['active'] ) ) {
                    $glossary_id = absint( $glossary['page_id'] );
                    break;
                }
            }
        }

        if ( 0 === $glossary_id ) {
            return; // No glossary found.
        }

        $entries = azgl()->get_glossary_entries( $glossary_id );
        if ( empty( $entries ) ) {
            return; // No entries.
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by theme.
        echo $args['before_widget'];

        if ( $title ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by theme.
            echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title ) ) . $args['after_title'];
        }

        echo '<div class="azgl-widget">';

        if ( 'az' === $display ) {
            $this->render_az_navigation( $entries, $glossary_id );
        } elseif ( 'recent' === $display ) {
            $this->render_recent_terms( $entries, $show_count ? 10 : 5 );
        } elseif ( 'popular' === $display ) {
            $this->render_popular_terms( $glossary_id, $show_count ? 10 : 5 );
        }

        echo '</div>';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget args are escaped by theme.
        echo $args['after_widget'];
    }

    /**
     * Render A-Z navigation.
     *
     * @param array $entries     Glossary entries.
     * @param int   $glossary_id Glossary page ID.
     */
    private function render_az_navigation( $entries, $glossary_id ) {
        $letters = array();
        foreach ( $entries as $entry ) {
            $first_letter = $this->get_first_letter( $entry['title'] );
            if ( ! isset( $letters[ $first_letter ] ) ) {
                $letters[ $first_letter ] = 0;
            }
            ++$letters[ $first_letter ];
        }
        ksort( $letters );

        $glossary_url = get_permalink( $glossary_id );

        echo '<div class="azgl-widget-az">';
        foreach ( range( 'A', 'Z' ) as $letter ) {
            $count = isset( $letters[ $letter ] ) ? $letters[ $letter ] : 0;
            $url   = $glossary_url . '#azgl-' . strtolower( $letter );

            if ( $count > 0 ) {
                echo '<a href="' . esc_url( $url ) . '" class="azgl-widget-letter azgl-widget-letter-active" title="' . esc_attr( $count . ' ' . _n( 'entry', 'entries', $count, '3task-glossary' ) ) . '">';
                echo esc_html( $letter );
                echo '</a>';
            } else {
                echo '<span class="azgl-widget-letter azgl-widget-letter-inactive">' . esc_html( $letter ) . '</span>';
            }
        }
        echo '</div>';

        echo '<p class="azgl-widget-link"><a href="' . esc_url( $glossary_url ) . '">' . esc_html__( 'View full glossary →', '3task-glossary' ) . '</a></p>';
    }

    /**
     * Render recent terms.
     *
     * @param array $entries Glossary entries.
     * @param int   $limit   Number of terms to show.
     */
    private function render_recent_terms( $entries, $limit = 5 ) {
        // Sort by date (newest first) - entries are already sorted by title.
        usort(
            $entries,
            function ( $a, $b ) {
                return strtotime( $b['date'] ) - strtotime( $a['date'] );
            }
        );

        $entries = array_slice( $entries, 0, $limit );

        echo '<ul class="azgl-widget-list">';
        foreach ( $entries as $entry ) {
            echo '<li><a href="' . esc_url( $entry['url'] ) . '">' . esc_html( $entry['title'] ) . '</a></li>';
        }
        echo '</ul>';
    }

    /**
     * Render popular terms (most viewed - placeholder, uses alphabetical for now).
     *
     * @param int $glossary_id Glossary page ID.
     * @param int $limit       Number of terms to show.
     */
    private function render_popular_terms( $glossary_id, $limit = 5 ) {
        $entries = azgl()->get_glossary_entries( $glossary_id );
        $entries = array_slice( $entries, 0, $limit );

        echo '<ul class="azgl-widget-list">';
        foreach ( $entries as $entry ) {
            echo '<li><a href="' . esc_url( $entry['url'] ) . '">' . esc_html( $entry['title'] ) . '</a></li>';
        }
        echo '</ul>';
    }

    /**
     * Get first letter of a string, handling umlauts.
     *
     * @param string $str Input string.
     * @return string First letter (uppercase).
     */
    private function get_first_letter( $str ) {
        $str   = trim( $str );
        $first = mb_strtoupper( mb_substr( $str, 0, 1, 'UTF-8' ), 'UTF-8' );

        // Map umlauts to base letters.
        $umlaut_map = array(
            'Ä' => 'A',
            'Ö' => 'O',
            'Ü' => 'U',
            'ß' => 'S',
        );

        return isset( $umlaut_map[ $first ] ) ? $umlaut_map[ $first ] : $first;
    }

    /**
     * Output the widget settings form.
     *
     * @param array $instance Current widget settings.
     * @return void
     */
    public function form( $instance ) {
        $title       = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Glossary', '3task-glossary' );
        $glossary_id = ! empty( $instance['glossary_id'] ) ? absint( $instance['glossary_id'] ) : 0;
        $display     = ! empty( $instance['display'] ) ? $instance['display'] : 'az';

        $options    = azgl()->get_options();
        $glossaries = $options->get( 'glossaries', array() );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', '3task-glossary' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'glossary_id' ) ); ?>">
                <?php esc_html_e( 'Glossary:', '3task-glossary' ); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'glossary_id' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'glossary_id' ) ); ?>">
                <option value="0"><?php esc_html_e( '— First active glossary —', '3task-glossary' ); ?></option>
                <?php foreach ( $glossaries as $glossary ) : ?>
                    <?php
                    $page = get_post( absint( $glossary['page_id'] ) );
                    if ( ! $page ) {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_attr( $glossary['page_id'] ); ?>"
                        <?php selected( $glossary_id, absint( $glossary['page_id'] ) ); ?>>
                        <?php echo esc_html( $page->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'display' ) ); ?>">
                <?php esc_html_e( 'Display:', '3task-glossary' ); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'display' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'display' ) ); ?>">
                <option value="az" <?php selected( $display, 'az' ); ?>>
                    <?php esc_html_e( 'A-Z Navigation', '3task-glossary' ); ?>
                </option>
                <option value="recent" <?php selected( $display, 'recent' ); ?>>
                    <?php esc_html_e( 'Recent Terms', '3task-glossary' ); ?>
                </option>
                <option value="popular" <?php selected( $display, 'popular' ); ?>>
                    <?php esc_html_e( 'Popular Terms', '3task-glossary' ); ?>
                </option>
            </select>
        </p>
        <?php
    }

    /**
     * Save widget settings.
     *
     * @param array $new_instance New settings.
     * @param array $old_instance Previous settings.
     * @return array Sanitized settings.
     */
    public function update( $new_instance, $old_instance ) {
        $instance                = array();
        $instance['title']       = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['glossary_id'] = ! empty( $new_instance['glossary_id'] ) ? absint( $new_instance['glossary_id'] ) : 0;
        $instance['display']     = ! empty( $new_instance['display'] ) ? sanitize_key( $new_instance['display'] ) : 'az';
        $instance['show_count']  = ! empty( $new_instance['show_count'] );

        return $instance;
    }
}

/**
 * Register the widget.
 */
function azgl_register_widget() {
    register_widget( 'AZ_Glossary_Widget' );
}
add_action( 'widgets_init', 'azgl_register_widget' );
