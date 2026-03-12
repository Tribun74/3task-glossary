<?php
/**
 * 3task Glossary Admin Handler
 *
 * Manages admin interface and settings.
 *
 * @package 3Task_Glossary
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Class
 *
 * @since 2.0.0
 */
class AZ_Glossary_Admin {

    /**
     * Options handler.
     *
     * @var AZ_Glossary_Options
     */
    private $options;

    /**
     * Current tab.
     *
     * @var string
     */
    private $current_tab = 'glossaries';

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
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
        add_action( 'save_post_page', array( $this, 'clear_cache_on_save' ), 10, 1 );
        add_filter( 'plugin_action_links_' . AZGL_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( '3task Glossary', '3task-glossary' ),
            __( '3task Glossary', '3task-glossary' ),
            'manage_options',
            'az-glossary',
            array( $this, 'render_admin_page' ),
            'dashicons-book-alt',
            30
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_az-glossary' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'azgl-admin',
            AZGL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AZGL_VERSION
        );

        wp_enqueue_script(
            'azgl-admin',
            AZGL_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            AZGL_VERSION,
            true
        );

        wp_localize_script(
            'azgl-admin',
            'azglAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'azgl_admin_nonce' ),
                'strings' => array(
                    'confirmRemove' => __( 'Are you sure you want to remove this glossary?', '3task-glossary' ),
                    'saved'         => __( 'Settings saved.', '3task-glossary' ),
                    'error'         => __( 'An error occurred.', '3task-glossary' ),
                ),
            )
        );
    }

    /**
     * Handle admin actions.
     */
    public function handle_admin_actions() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reading for tab display
        if ( isset( $_GET['page'] ) && 'az-glossary' === $_GET['page'] && isset( $_GET['tab'] ) ) {
            $this->current_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Handle form submissions.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process_form_submission()
        if ( isset( $_POST['azgl_action'] ) ) {
            $this->process_form_submission();
        }
    }

    /**
     * Process form submission.
     */
    private function process_form_submission() {
        // Verify nonce.
        if ( ! isset( $_POST['azgl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['azgl_nonce'] ) ), 'azgl_admin_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', '3task-glossary' ) );
        }

        // Check capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', '3task-glossary' ) );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated in handle_admin_actions()
        $action = isset( $_POST['azgl_action'] ) ? sanitize_key( wp_unslash( $_POST['azgl_action'] ) ) : '';

        switch ( $action ) {
            case 'add_glossary':
                $this->handle_add_glossary();
                break;
            case 'remove_glossary':
                $this->handle_remove_glossary();
                break;
            case 'save_settings':
                $this->handle_save_settings();
                break;
            case 'save_design':
                $this->handle_save_design();
                break;
            case 'save_supporter':
                $this->handle_save_supporter();
                break;
            case 'save_language':
                $this->handle_save_language();
                break;
        }
    }

    /**
     * Handle add glossary.
     *
     * Nonce verification happens in process_form_submission().
     */
    private function handle_add_glossary() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_form_submission()
        $has_page_id = isset( $_POST['page_id'] );
        $page_id     = $has_page_id ? absint( $_POST['page_id'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( ! $has_page_id ) {
            $this->redirect_with_message( 'error', __( 'No page selected.', '3task-glossary' ) );
            return;
        }

        if ( 0 === $page_id ) {
            $this->redirect_with_message( 'error', __( 'Invalid page selected.', '3task-glossary' ) );
            return;
        }

        $glossaries = $this->options->get( 'glossaries', array() );

        $result = $this->options->add_glossary( $page_id );

        if ( $result ) {
            $this->redirect_with_message( 'success', __( 'Glossary added successfully.', '3task-glossary' ) );
        } else {
            $this->redirect_with_message( 'error', __( 'This page is already a glossary.', '3task-glossary' ) );
        }
    }

    /**
     * Handle remove glossary.
     *
     * Nonce verification happens in process_form_submission().
     */
    private function handle_remove_glossary() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_form_submission()
        $has_page_id = isset( $_POST['page_id'] );
        $page_id     = $has_page_id ? absint( $_POST['page_id'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( ! $has_page_id ) {
            $this->redirect_with_message( 'error', __( 'No glossary specified.', '3task-glossary' ) );
            return;
        }

        $result = $this->options->remove_glossary( $page_id );

        if ( $result ) {
            $this->redirect_with_message( 'success', __( 'Glossary removed successfully.', '3task-glossary' ) );
        } else {
            $this->redirect_with_message( 'error', __( 'Could not remove glossary.', '3task-glossary' ) );
        }
    }

    /**
     * Handle save settings.
     *
     * Nonce verification happens in process_form_submission().
     */
    private function handle_save_settings() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_form_submission()
        $settings = array(
            'linking_mode'    => isset( $_POST['linking_mode'] ) ? sanitize_key( wp_unslash( $_POST['linking_mode'] ) ) : 'selective',
            'link_first_only' => isset( $_POST['link_first_only'] ),
            'link_class'      => isset( $_POST['link_class'] ) ? sanitize_html_class( wp_unslash( $_POST['link_class'] ) ) : 'glossary-term',
            'cache_enabled'   => isset( $_POST['cache_enabled'] ),
            // v2.3.0 Advanced Features.
            'enable_schema' => isset( $_POST['enable_schema'] ),
            'enable_print'  => isset( $_POST['enable_print'] ),
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Validate linking_mode.
        $valid_modes = array( 'disabled', 'selective' );
        if ( ! in_array( $settings['linking_mode'], $valid_modes, true ) ) {
            $settings['linking_mode'] = 'selective';
        }

        $this->options->update( $settings );
        $this->redirect_with_message( 'success', __( 'Settings saved.', '3task-glossary' ), 'settings' );
    }

    /**
     * Handle save design settings.
     *
     * Nonce verification happens in process_form_submission().
     */
    private function handle_save_design() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_form_submission()
        $settings = array(
            'nav_style'        => isset( $_POST['nav_style'] ) ? sanitize_key( wp_unslash( $_POST['nav_style'] ) ) : 'buttons',
            'inactive_letters' => isset( $_POST['inactive_letters'] ) ? sanitize_key( wp_unslash( $_POST['inactive_letters'] ) ) : 'accessible',
            'color_scheme'     => isset( $_POST['color_scheme'] ) ? sanitize_key( wp_unslash( $_POST['color_scheme'] ) ) : 'emerald',
            'dark_mode'        => isset( $_POST['dark_mode'] ) ? sanitize_key( wp_unslash( $_POST['dark_mode'] ) ) : 'auto',
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Validate nav_style.
        $valid_styles = array_keys( $this->options->get_nav_styles() );
        if ( ! in_array( $settings['nav_style'], $valid_styles, true ) ) {
            $settings['nav_style'] = 'buttons';
        }

        // Validate inactive_letters.
        $valid_inactive = array_keys( $this->options->get_inactive_letter_modes() );
        if ( ! in_array( $settings['inactive_letters'], $valid_inactive, true ) ) {
            $settings['inactive_letters'] = 'accessible';
        }

        // Validate color_scheme.
        $valid_schemes = array_keys( $this->options->get_color_schemes() );
        if ( ! in_array( $settings['color_scheme'], $valid_schemes, true ) ) {
            $settings['color_scheme'] = 'emerald';
        }

        // Validate dark_mode.
        $valid_modes = array_keys( $this->options->get_dark_mode_options() );
        if ( ! in_array( $settings['dark_mode'], $valid_modes, true ) ) {
            $settings['dark_mode'] = 'auto';
        }

        $this->options->update( $settings );
        $this->redirect_with_message( 'success', __( 'Design settings saved.', '3task-glossary' ), 'design' );
    }

    /**
     * Handle save supporter settings.
     *
     * Nonce verification happens in process_form_submission().
     */
    private function handle_save_supporter() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_form_submission()
        $settings = array(
            'show_credit_link' => isset( $_POST['show_credit_link'] ),
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $this->options->update( $settings );
        $this->redirect_with_message( 'success', __( 'Supporter settings saved.', '3task-glossary' ), 'support' );
    }

    /**
     * Handle save language settings.
     *
     * Nonce verification happens in process_form_submission().
     */
    private function handle_save_language() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process_form_submission()
        $language = isset( $_POST['plugin_language'] ) ? sanitize_key( wp_unslash( $_POST['plugin_language'] ) ) : 'auto';

        // Validate language.
        $valid_languages = array_keys( $this->options->get_available_languages() );
        if ( ! in_array( $language, $valid_languages, true ) ) {
            $language = 'auto';
        }

        $this->options->set( 'plugin_language', $language );
        $this->redirect_with_message( 'success', __( 'Language saved. Please reload the page to see changes.', '3task-glossary' ), 'settings' );
    }

    /**
     * Redirect with message.
     *
     * @param string $type    Message type (success/error).
     * @param string $message Message text.
     * @param string $tab     Tab to redirect to.
     */
    private function redirect_with_message( $type, $message, $tab = 'glossaries' ) {
        set_transient( 'azgl_admin_message', array(
            'type'    => $type,
            'message' => $message,
        ), 30 );

        wp_safe_redirect( admin_url( 'admin.php?page=az-glossary&tab=' . $tab ) );
        exit;
    }

    /**
     * Clear cache on page save.
     *
     * @param int $post_id Post ID.
     */
    public function clear_cache_on_save( $post_id ) {
        $page = get_post( $post_id );
        if ( ! $page ) {
            return;
        }

        // Clear cache if this is a glossary page.
        if ( $this->options->is_glossary_page( $post_id ) ) {
            azgl()->clear_glossary_cache( $post_id );
        }

        // Clear cache if this is a child of a glossary page.
        if ( $page->post_parent && $this->options->is_glossary_page( $page->post_parent ) ) {
            azgl()->clear_glossary_cache( $page->post_parent );
        }
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function add_plugin_links( $links ) {
        $custom_links = array(
            '<a href="' . esc_url( admin_url( 'admin.php?page=az-glossary' ) ) . '">' . esc_html__( 'Settings', '3task-glossary' ) . '</a>',
            '<a href="' . esc_url( azgl()->get_upgrade_url() ) . '" target="_blank" style="color:#46b450;font-weight:bold;">' . esc_html__( 'Go Pro', '3task-glossary' ) . '</a>',
        );
        return array_merge( $custom_links, $links );
    }

    /**
     * Render admin page.
     */
    public function render_admin_page() {
        // Tab configuration with icons (Unicode emojis for DSGVO compliance).
        $tabs = array(
            'dashboard'  => array(
                'label' => __( 'Dashboard', '3task-glossary' ),
                'icon'  => '📊',
            ),
            'glossaries' => array(
                'label' => __( 'Glossaries', '3task-glossary' ),
                'icon'  => '📚',
                'count' => count( $this->options->get( 'glossaries', array() ) ),
            ),
            'settings'   => array(
                'label' => __( 'Settings', '3task-glossary' ),
                'icon'  => '⚙️',
            ),
            'design'     => array(
                'label' => __( 'Design', '3task-glossary' ),
                'icon'  => '🎨',
            ),
            'help'       => array(
                'label' => __( 'Help', '3task-glossary' ),
                'icon'  => '❓',
            ),
        );

        // Get current tab.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading for tab display
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        if ( ! array_key_exists( $current_tab, $tabs ) ) {
            $current_tab = 'dashboard';
        }

        // Show admin message if exists.
        $message = get_transient( 'azgl_admin_message' );
        if ( $message ) {
            delete_transient( 'azgl_admin_message' );
        }
        ?>
        <div class="wrap azgl-admin">
            <!-- Animated Gradient Header -->
            <div class="azgl-admin-header">
                <div class="azgl-admin-header-content">
                    <div class="azgl-admin-header-left">
                        <div class="azgl-admin-icon">📚</div>
                        <div class="azgl-admin-title-text">
                            <h1><?php echo esc_html__( '3task Glossary', '3task-glossary' ); ?></h1>
                            <div class="azgl-admin-title-meta">
                                <span class="azgl-version"><?php echo esc_html( 'v' . AZGL_VERSION ); ?></span>
                                <span class="azgl-status-dot"><?php esc_html_e( 'Active', '3task-glossary' ); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="azgl-admin-header-right">
                        <a href="https://wordpress.org/support/plugin/3task-glossary/" target="_blank" class="azgl-header-btn">
                            <span>📖</span>
                            <?php esc_html_e( 'Docs', '3task-glossary' ); ?>
                        </a>
                        <a href="https://wordpress.org/support/plugin/3task-glossary/" target="_blank" class="azgl-header-btn">
                            <span>💬</span>
                            <?php esc_html_e( 'Support', '3task-glossary' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ( $message ) : ?>
                <div class="notice notice-<?php echo esc_attr( $message['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( $message['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <nav class="azgl-tabs nav-tab-wrapper">
                <?php foreach ( $tabs as $tab_id => $tab_data ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=az-glossary&tab=' . $tab_id ) ); ?>"
                       class="nav-tab <?php echo esc_attr( $current_tab === $tab_id ? 'nav-tab-active' : '' ); ?>">
                        <span class="tab-icon"><?php echo esc_html( $tab_data['icon'] ); ?></span>
                        <?php echo esc_html( $tab_data['label'] ); ?>
                        <?php if ( ! empty( $tab_data['count'] ) && $tab_data['count'] > 0 ) : ?>
                            <span class="tab-badge"><?php echo esc_html( $tab_data['count'] ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="azgl-tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'dashboard':
                        $this->render_dashboard_tab();
                        break;
                    case 'glossaries':
                        $this->render_glossaries_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'design':
                        $this->render_design_tab();
                        break;
                    case 'help':
                        $this->render_help_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard tab with stats and quick actions.
     *
     * @since 2.3.0
     */
    private function render_dashboard_tab() {
        $glossaries   = $this->options->get( 'glossaries', array() );
        $total_terms  = 0;
        $active_count = 0;

        foreach ( $glossaries as $glossary ) {
            $total_terms += azgl()->get_entry_count( absint( $glossary['page_id'] ) );
            if ( ! empty( $glossary['active'] ) ) {
                ++$active_count;
            }
        }

        // Check if user is new (no glossaries yet).
        $is_new_user = empty( $glossaries );
        ?>

        <?php if ( $is_new_user ) : ?>
            <!-- Onboarding Banner for New Users -->
            <div class="azgl-onboarding">
                <span class="azgl-onboarding-icon">👋</span>
                <div class="azgl-onboarding-content">
                    <h3><?php esc_html_e( 'Welcome to 3task Glossary!', '3task-glossary' ); ?></h3>
                    <p><?php esc_html_e( 'Create your first glossary in just 2 steps: Add a parent page, then create child pages for each term.', '3task-glossary' ); ?></p>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=az-glossary&tab=glossaries' ) ); ?>" class="azgl-onboarding-btn">
                    <span>✨</span>
                    <?php esc_html_e( 'Create First Glossary', '3task-glossary' ); ?>
                </a>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="azgl-stats-grid">
            <div class="azgl-stat-card stat-purple">
                <div class="azgl-stat-header">
                    <span class="azgl-stat-icon">📚</span>
                    <?php if ( count( $glossaries ) > 0 ) : ?>
                        <span class="azgl-stat-trend">+<?php echo esc_html( count( $glossaries ) ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="azgl-stat-value"><?php echo esc_html( count( $glossaries ) ); ?></div>
                <div class="azgl-stat-label"><?php esc_html_e( 'Glossaries', '3task-glossary' ); ?></div>
            </div>

            <div class="azgl-stat-card stat-green">
                <div class="azgl-stat-header">
                    <span class="azgl-stat-icon">📝</span>
                    <?php if ( $total_terms > 0 ) : ?>
                        <span class="azgl-stat-trend">+<?php echo esc_html( $total_terms ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="azgl-stat-value"><?php echo esc_html( $total_terms ); ?></div>
                <div class="azgl-stat-label"><?php esc_html_e( 'Terms', '3task-glossary' ); ?></div>
            </div>

            <div class="azgl-stat-card stat-blue">
                <div class="azgl-stat-header">
                    <span class="azgl-stat-icon">✅</span>
                </div>
                <div class="azgl-stat-value"><?php echo esc_html( $active_count ); ?></div>
                <div class="azgl-stat-label"><?php esc_html_e( 'Active', '3task-glossary' ); ?></div>
            </div>

            <div class="azgl-stat-card stat-orange">
                <div class="azgl-stat-header">
                    <span class="azgl-stat-icon">🔗</span>
                    <span class="azgl-stat-trend trend-neutral"><?php esc_html_e( 'v2.3', '3task-glossary' ); ?></span>
                </div>
                <div class="azgl-stat-value">—</div>
                <div class="azgl-stat-label"><?php esc_html_e( 'Reverse Links', '3task-glossary' ); ?></div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="azgl-content-grid">
            <!-- Left Column: Glossary List -->
            <div class="azgl-card">
                <h2>
                    <span class="card-icon">📚</span>
                    <?php esc_html_e( 'Your Glossaries', '3task-glossary' ); ?>
                </h2>

                <?php if ( ! empty( $glossaries ) ) : ?>
                    <?php foreach ( array_slice( $glossaries, 0, 5 ) as $glossary ) : ?>
                        <?php
                        $page_id     = absint( $glossary['page_id'] );
                        $page        = get_post( $page_id );
                        $entry_count = azgl()->get_entry_count( $page_id );
                        $initial     = $page ? strtoupper( substr( $page->post_title, 0, 1 ) ) : '?';
                        ?>
                        <div class="azgl-glossary-item">
                            <div class="azgl-glossary-avatar"><?php echo esc_html( $initial ); ?></div>
                            <div class="azgl-glossary-info">
                                <h3 class="azgl-glossary-title"><?php echo $page ? esc_html( $page->post_title ) : esc_html__( 'Unknown', '3task-glossary' ); ?></h3>
                                <span class="azgl-glossary-meta">
                                    <?php if ( ! empty( $glossary['active'] ) ) : ?>
                                        ● <?php esc_html_e( 'Active', '3task-glossary' ); ?>
                                    <?php else : ?>
                                        ○ <?php esc_html_e( 'Inactive', '3task-glossary' ); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="azgl-glossary-count"><?php echo esc_html( $entry_count ); ?></div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ( count( $glossaries ) > 5 ) : ?>
                        <p style="text-align: center; margin-top: 16px;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=az-glossary&tab=glossaries' ) ); ?>">
                                <?php
                                printf(
                                    /* translators: %d: number of additional glossaries */
                                    esc_html__( 'View all %d glossaries →', '3task-glossary' ),
                                    count( $glossaries )
                                );
                                ?>
                            </a>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p style="color: var(--azgl-text-muted); text-align: center; padding: 40px 0;">
                        <?php esc_html_e( 'No glossaries yet. Create your first one!', '3task-glossary' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Right Column: Quick Actions + Feature Showcase -->
            <div>
                <!-- Quick Actions -->
                <div class="azgl-card">
                    <h2>
                        <span class="card-icon">⚡</span>
                        <?php esc_html_e( 'Quick Actions', '3task-glossary' ); ?>
                    </h2>
                    <div class="azgl-quick-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=az-glossary&tab=glossaries' ) ); ?>" class="azgl-quick-action">
                            <div class="azgl-quick-action-icon">✨</div>
                            <span class="azgl-quick-action-label"><?php esc_html_e( 'Add Glossary', '3task-glossary' ); ?></span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=az-glossary&tab=design' ) ); ?>" class="azgl-quick-action">
                            <div class="azgl-quick-action-icon">🎨</div>
                            <span class="azgl-quick-action-label"><?php esc_html_e( 'Customize Design', '3task-glossary' ); ?></span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=az-glossary&tab=settings' ) ); ?>" class="azgl-quick-action">
                            <div class="azgl-quick-action-icon">⚙️</div>
                            <span class="azgl-quick-action-label"><?php esc_html_e( 'Settings', '3task-glossary' ); ?></span>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=az-glossary&tab=help' ) ); ?>" class="azgl-quick-action">
                            <div class="azgl-quick-action-icon">📖</div>
                            <span class="azgl-quick-action-label"><?php esc_html_e( 'Documentation', '3task-glossary' ); ?></span>
                        </a>
                    </div>
                </div>

                <!-- Feature Showcase - v2.3 Features -->
                <div class="azgl-feature-showcase">
                    <div class="azgl-feature-showcase-header">
                        <span class="azgl-feature-showcase-badge"><?php esc_html_e( 'v2.3 Features', '3task-glossary' ); ?></span>
                    </div>
                    <h3><?php esc_html_e( 'Included in Free', '3task-glossary' ); ?></h3>
                    <div class="azgl-feature-tags">
                        <span class="azgl-feature-tag">
                            <span class="tag-icon">🔍</span>
                            <?php esc_html_e( 'Schema.org SEO', '3task-glossary' ); ?>
                        </span>
                        <span class="azgl-feature-tag">
                            <span class="tag-icon">🔗</span>
                            <?php esc_html_e( 'Reverse Links', '3task-glossary' ); ?>
                        </span>
                        <span class="azgl-feature-tag">
                            <span class="tag-icon">🖨️</span>
                            <?php esc_html_e( 'Print Styles', '3task-glossary' ); ?>
                        </span>
                        <span class="azgl-feature-tag">
                            <span class="tag-icon">📦</span>
                            <?php esc_html_e( 'Sidebar Widget', '3task-glossary' ); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support & Pro Section -->
        <div class="azgl-support-pro-grid">
            <!-- Supporter Backlink Card - Psychologically Optimized -->
            <div class="azgl-card azgl-supporter-card">
                <div class="azgl-supporter-header">
                    <img src="<?php echo esc_url( AZGL_PLUGIN_URL . 'assets/images/icon-128x128.png' ); ?>" alt="3task Glossary" class="azgl-supporter-logo">
                    <div>
                        <h2><?php esc_html_e( 'Become a Supporter', '3task-glossary' ); ?></h2>
                        <p class="azgl-supporter-subtitle"><?php esc_html_e( 'Join website owners who give back to open source', '3task-glossary' ); ?></p>
                    </div>
                </div>

                <!-- Reciprocity: Show what they GOT for free -->
                <div class="azgl-value-box">
                    <div class="azgl-value-header">
                        <span class="azgl-value-icon">🎁</span>
                        <span class="azgl-value-title"><?php esc_html_e( 'What you get for FREE:', '3task-glossary' ); ?></span>
                    </div>
                    <div class="azgl-value-items">
                        <span class="azgl-value-item">✓ <?php esc_html_e( 'Unlimited Glossaries', '3task-glossary' ); ?></span>
                        <span class="azgl-value-item">✓ <?php esc_html_e( 'Schema.org SEO', '3task-glossary' ); ?></span>
                        <span class="azgl-value-item">✓ <?php esc_html_e( 'Sidebar Widget', '3task-glossary' ); ?></span>
                        <span class="azgl-value-item">✓ <?php esc_html_e( '6 Color Themes', '3task-glossary' ); ?></span>
                        <span class="azgl-value-item">✓ <?php esc_html_e( 'Dark Mode', '3task-glossary' ); ?></span>
                    </div>
                    <p class="azgl-value-worth"><?php esc_html_e( 'Worth $99+ — yours free, forever.', '3task-glossary' ); ?></p>
                </div>

                <!-- The Ask - Framed as giving back -->
                <div class="azgl-give-back">
                    <p class="azgl-give-back-text">
                        <strong><?php esc_html_e( 'Give back with one click:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Add a tiny "Powered by" link to help others discover this plugin.', '3task-glossary' ); ?>
                    </p>

                    <form method="post" class="azgl-supporter-form">
                        <?php wp_nonce_field( 'azgl_admin_action', 'azgl_nonce' ); ?>
                        <input type="hidden" name="azgl_action" value="save_supporter">

                        <label class="azgl-supporter-toggle">
                            <input type="checkbox" name="show_credit_link" value="1" <?php checked( $this->options->get( 'show_credit_link', false ) ); ?>>
                            <span class="azgl-toggle-slider"></span>
                            <span class="azgl-toggle-label">
                                <?php if ( $this->options->get( 'show_credit_link', false ) ) : ?>
                                    <span class="azgl-supporter-badge">🏆 <?php esc_html_e( 'Supporter', '3task-glossary' ); ?></span>
                                <?php else : ?>
                                    <?php esc_html_e( 'Yes, I want to support!', '3task-glossary' ); ?>
                                <?php endif; ?>
                            </span>
                        </label>

                        <div class="azgl-supporter-preview">
                            <span class="azgl-preview-label"><?php esc_html_e( 'Appears as:', '3task-glossary' ); ?></span>
                            <span class="azgl-preview-link">Powered by <a href="#">3task Glossary</a></span>
                        </div>

                        <button type="submit" class="button button-primary azgl-supporter-btn-save">
                            <?php
                            if ( $this->options->get( 'show_credit_link', false ) ) {
                                esc_html_e( '✓ You\'re a Supporter!', '3task-glossary' );
                            } else {
                                esc_html_e( 'Become a Supporter', '3task-glossary' );
                            }
                            ?>
                        </button>
                    </form>
                </div>

                <!-- Social Proof + Alternatives -->
                <div class="azgl-supporter-footer">
                    <div class="azgl-supporter-alternatives">
                        <span><?php esc_html_e( 'Other ways to help:', '3task-glossary' ); ?></span>
                        <a href="https://wordpress.org/support/plugin/3task-glossary/reviews/#new-post" target="_blank" class="azgl-alt-action">
                            <span>⭐</span> <?php esc_html_e( '5-Star Review', '3task-glossary' ); ?>
                        </a>
                        <a href="https://wordpress.org/support/plugin/3task-glossary/" target="_blank" class="azgl-alt-action">
                            <span>💬</span> <?php esc_html_e( 'Answer Questions', '3task-glossary' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Go Pro Card -->
            <div class="azgl-card azgl-pro-card">
                <div class="azgl-pro-badge"><?php esc_html_e( 'PRO', '3task-glossary' ); ?></div>
                <h2><?php esc_html_e( 'Unlock More Power', '3task-glossary' ); ?></h2>
                <p class="azgl-pro-subtitle"><?php esc_html_e( 'Take your glossary to the next level', '3task-glossary' ); ?></p>

                <ul class="azgl-pro-features">
                    <li><span class="pro-check">✓</span> <?php esc_html_e( 'Site-wide Auto-Linking', '3task-glossary' ); ?></li>
                    <li><span class="pro-check">✓</span> <?php esc_html_e( 'Hover Tooltips', '3task-glossary' ); ?></li>
                    <li><span class="pro-check">✓</span> <?php esc_html_e( 'Search Function', '3task-glossary' ); ?></li>
                    <li><span class="pro-check">✓</span> <?php esc_html_e( 'CSV Import/Export', '3task-glossary' ); ?></li>
                    <li><span class="pro-check">✓</span> <?php esc_html_e( 'View Statistics', '3task-glossary' ); ?></li>
                    <li><span class="pro-check">✓</span> <?php esc_html_e( 'Priority Support', '3task-glossary' ); ?></li>
                </ul>

                <a href="<?php echo esc_url( azgl()->get_upgrade_url() ); ?>" target="_blank" class="azgl-pro-btn">
                    <?php esc_html_e( 'Learn More About Pro', '3task-glossary' ); ?>
                    <span>→</span>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render glossaries tab.
     */
    private function render_glossaries_tab() {
        $glossaries = $this->options->get( 'glossaries', array() );
        ?>
        <div class="azgl-card">
            <h2><?php esc_html_e( 'Your Glossaries', '3task-glossary' ); ?></h2>

            <div class="azgl-limits-info">
                <p>
                    <?php
                    printf(
                        /* translators: %d: current number of glossaries */
                        esc_html__( 'You have %d glossary/glossaries configured.', '3task-glossary' ),
                        count( $glossaries )
                    );
                    ?>
                </p>
            </div>

            <?php if ( ! empty( $glossaries ) ) : ?>
                <table class="wp-list-table widefat fixed striped azgl-glossaries-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Page', '3task-glossary' ); ?></th>
                            <th><?php esc_html_e( 'Entries', '3task-glossary' ); ?></th>
                            <th><?php esc_html_e( 'Status', '3task-glossary' ); ?></th>
                            <th><?php esc_html_e( 'Actions', '3task-glossary' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $glossaries as $glossary ) : ?>
                            <?php
                            $page_id     = absint( $glossary['page_id'] );
                            $page        = get_post( $page_id );
                            $entry_count = azgl()->get_entry_count( $page_id );
                            ?>
                            <tr>
                                <td>
                                    <?php if ( $page ) : ?>
                                        <strong><?php echo esc_html( $page->post_title ); ?></strong>
                                        <div class="row-actions">
                                            <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank">
                                                <?php esc_html_e( 'View', '3task-glossary' ); ?>
                                            </a>
                                            |
                                            <a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>">
                                                <?php esc_html_e( 'Edit', '3task-glossary' ); ?>
                                            </a>
                                        </div>
                                    <?php else : ?>
                                        <em><?php esc_html_e( 'Page not found', '3task-glossary' ); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    printf(
                                        /* translators: %d: number of glossary entries */
                                        esc_html__( '%d entries', '3task-glossary' ),
                                        absint( $entry_count )
                                    );
                                    ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $glossary['active'] ) ) : ?>
                                        <span class="azgl-status azgl-status-active"><?php esc_html_e( 'Active', '3task-glossary' ); ?></span>
                                    <?php else : ?>
                                        <span class="azgl-status azgl-status-inactive"><?php esc_html_e( 'Inactive', '3task-glossary' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" class="azgl-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove this glossary?', '3task-glossary' ) ); ?>');">
                                        <?php wp_nonce_field( 'azgl_admin_action', 'azgl_nonce' ); ?>
                                        <input type="hidden" name="azgl_action" value="remove_glossary">
                                        <input type="hidden" name="page_id" value="<?php echo esc_attr( $page_id ); ?>">
                                        <button type="submit" class="button button-small button-link-delete">
                                            <?php esc_html_e( 'Remove', '3task-glossary' ); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="azgl-no-glossaries">
                    <?php esc_html_e( 'No glossaries configured yet. Select a page below to create your first glossary.', '3task-glossary' ); ?>
                </p>
            <?php endif; ?>

            <div class="azgl-add-glossary">
                <h3><?php esc_html_e( 'Add New Glossary', '3task-glossary' ); ?></h3>
                <p class="description">
                    <?php esc_html_e( 'Select a page to use as a glossary. Child pages of this page will become glossary entries.', '3task-glossary' ); ?>
                </p>
                <form method="post">
                    <?php wp_nonce_field( 'azgl_admin_action', 'azgl_nonce' ); ?>
                    <input type="hidden" name="azgl_action" value="add_glossary">
                    <p>
                        <?php
                        // phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Small array of excluded pages
                        wp_dropdown_pages(
                            array(
                                'name'              => 'page_id',
                                'show_option_none'  => esc_html__( '— Select Page —', '3task-glossary' ),
                                'option_none_value' => 0,
                                'exclude'           => array_map( 'absint', array_column( $glossaries, 'page_id' ) ),
                            )
                        );
                        // phpcs:enable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                        ?>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Add Glossary', '3task-glossary' ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <div class="azgl-card azgl-how-it-works">
            <h2><?php esc_html_e( 'How It Works', '3task-glossary' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Create a WordPress page that will serve as your glossary (e.g., "Glossary" or "Terms").', '3task-glossary' ); ?></li>
                <li><?php esc_html_e( 'Add the page above as a glossary.', '3task-glossary' ); ?></li>
                <li><?php esc_html_e( 'Create child pages under your glossary page - each child page becomes a glossary entry.', '3task-glossary' ); ?></li>
                <li><?php esc_html_e( 'The A-Z navigation and entry list will be automatically displayed on your glossary page.', '3task-glossary' ); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render settings tab.
     */
    private function render_settings_tab() {
        $linking_mode    = $this->options->get( 'linking_mode', 'selective' );
        $link_first_only = $this->options->get( 'link_first_only', true );
        $link_class      = $this->options->get( 'link_class', 'glossary-term' );
        $cache_enabled   = $this->options->get( 'cache_enabled', true );
        $plugin_language = $this->options->get( 'plugin_language', 'auto' );
        $languages       = $this->options->get_available_languages();
        // v2.3.0 Advanced Features.
        $enable_schema = $this->options->get( 'enable_schema', true );
        $enable_print  = $this->options->get( 'enable_print', true );
        ?>
        <div class="azgl-card">
            <h2><?php esc_html_e( 'Language Settings', '3task-glossary' ); ?></h2>

            <form method="post">
                <?php wp_nonce_field( 'azgl_admin_action', 'azgl_nonce' ); ?>
                <input type="hidden" name="azgl_action" value="save_language">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="plugin_language"><?php esc_html_e( 'Plugin Language', '3task-glossary' ); ?></label>
                        </th>
                        <td>
                            <div class="azgl-language-selector">
                                <?php foreach ( $languages as $value => $label ) : ?>
                                    <label class="azgl-language-option <?php echo esc_attr( $plugin_language === $value ? 'selected' : '' ); ?>">
                                        <input type="radio" name="plugin_language" value="<?php echo esc_attr( $value ); ?>"
                                            <?php checked( $plugin_language, $value ); ?>>
                                        <span class="azgl-language-flag">
                                            <?php if ( 'en_US' === $value ) : ?>
                                                🇬🇧
                                            <?php elseif ( 'de_DE' === $value ) : ?>
                                                🇩🇪
                                            <?php else : ?>
                                                🌐
                                            <?php endif; ?>
                                        </span>
                                        <span class="azgl-language-label"><?php echo esc_html( $label ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">
                                <?php esc_html_e( 'Choose the language for the plugin interface. "Auto" uses your WordPress site language.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Language', '3task-glossary' ); ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="azgl-card">
            <h2><?php esc_html_e( 'Linking Settings', '3task-glossary' ); ?></h2>

            <form method="post">
                <?php wp_nonce_field( 'azgl_admin_action', 'azgl_nonce' ); ?>
                <input type="hidden" name="azgl_action" value="save_settings">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="linking_mode"><?php esc_html_e( 'Auto-Linking', '3task-glossary' ); ?></label>
                        </th>
                        <td>
                            <select name="linking_mode" id="linking_mode">
                                <option value="disabled" <?php selected( $linking_mode, 'disabled' ); ?>>
                                    <?php esc_html_e( 'Disabled', '3task-glossary' ); ?>
                                </option>
                                <option value="selective" <?php selected( $linking_mode, 'selective' ); ?>>
                                    <?php esc_html_e( 'Glossary pages only', '3task-glossary' ); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Automatically link glossary terms within your content.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Link First Occurrence Only', '3task-glossary' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="link_first_only" value="1" <?php checked( $link_first_only ); ?>>
                                <?php esc_html_e( 'Only link the first occurrence of each term per page', '3task-glossary' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="link_class"><?php esc_html_e( 'Link CSS Class', '3task-glossary' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="link_class" id="link_class"
                                   value="<?php echo esc_attr( $link_class ); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e( 'CSS class added to linked terms for custom styling.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Performance', '3task-glossary' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Caching', '3task-glossary' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cache_enabled" value="1" <?php checked( $cache_enabled ); ?>>
                                <?php esc_html_e( 'Enable caching for better performance', '3task-glossary' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Caches glossary entries for 1 hour. Disable for testing.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2>
                    <?php esc_html_e( 'Advanced Features', '3task-glossary' ); ?>
                    <span class="azgl-feature-showcase-badge" style="font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 100px; background: linear-gradient(135deg, #6366f1, #d946ef); color: #fff; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 10px; vertical-align: middle;"><?php esc_html_e( 'NEW in v2.3', '3task-glossary' ); ?></span>
                </h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Schema.org SEO', '3task-glossary' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_schema" value="1" <?php checked( $enable_schema ); ?>>
                                <?php esc_html_e( 'Add DefinedTerm schema markup to glossary entries', '3task-glossary' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Helps Google understand your glossary terms. Can improve search visibility and enable rich results.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Print Button', '3task-glossary' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_print" value="1" <?php checked( $enable_print ); ?>>
                                <?php esc_html_e( 'Show "Print Glossary" button on glossary pages', '3task-glossary' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Allows visitors to print a clean, formatted version of your glossary. Great for educational use.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Settings', '3task-glossary' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render design tab.
     */
    private function render_design_tab() {
        $nav_style        = $this->options->get( 'nav_style', 'buttons' );
        $inactive_letters = $this->options->get( 'inactive_letters', 'accessible' );
        $color_scheme     = $this->options->get( 'color_scheme', 'emerald' );
        $dark_mode        = $this->options->get( 'dark_mode', 'auto' );

        // Migrate old boolean value to new string format.
        if ( true === $inactive_letters || '1' === $inactive_letters ) {
            $inactive_letters = 'show';
        } elseif ( false === $inactive_letters || '' === $inactive_letters ) {
            $inactive_letters = 'none';
        }

        $nav_styles     = $this->options->get_nav_styles();
        $inactive_modes = $this->options->get_inactive_letter_modes();
        $color_schemes  = $this->options->get_color_schemes();
        $dark_modes     = $this->options->get_dark_mode_options();
        ?>
        <div class="azgl-card">
            <h2><?php esc_html_e( 'Design Settings', '3task-glossary' ); ?></h2>

            <form method="post">
                <?php wp_nonce_field( 'azgl_admin_action', 'azgl_nonce' ); ?>
                <input type="hidden" name="azgl_action" value="save_design">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nav_style"><?php esc_html_e( 'Navigation Style', '3task-glossary' ); ?></label>
                        </th>
                        <td>
                            <select name="nav_style" id="nav_style">
                                <?php foreach ( $nav_styles as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $nav_style, $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Choose the style for the A-Z letter navigation.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="inactive_letters"><?php esc_html_e( 'Inactive Letters', '3task-glossary' ); ?></label>
                        </th>
                        <td>
                            <select name="inactive_letters" id="inactive_letters">
                                <?php foreach ( $inactive_modes as $value => $mode ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $inactive_letters, $value ); ?>>
                                        <?php echo esc_html( $mode['label'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php
                                $current_mode = isset( $inactive_modes[ $inactive_letters ] ) ? $inactive_modes[ $inactive_letters ] : $inactive_modes['accessible'];
                                echo esc_html( $current_mode['description'] );
                                ?>
                            </p>
                            <p class="description" style="margin-top:8px;color:#666;">
                                <span class="dashicons dashicons-universal-access" style="font-size:14px;"></span>
                                <?php esc_html_e( 'Accessibility: "Accessible" mode is recommended for screen reader compatibility.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="color_scheme"><?php esc_html_e( 'Color Scheme', '3task-glossary' ); ?></label>
                        </th>
                        <td>
                            <div class="azgl-color-schemes">
                                <?php foreach ( $color_schemes as $value => $label ) : ?>
                                    <label class="azgl-color-option azgl-color-<?php echo esc_attr( $value ); ?>">
                                        <input type="radio" name="color_scheme" value="<?php echo esc_attr( $value ); ?>"
                                            <?php checked( $color_scheme, $value ); ?>>
                                        <span class="azgl-color-preview"></span>
                                        <span class="azgl-color-label"><?php echo esc_html( $label ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dark_mode"><?php esc_html_e( 'Dark Mode', '3task-glossary' ); ?></label>
                        </th>
                        <td>
                            <select name="dark_mode" id="dark_mode">
                                <?php foreach ( $dark_modes as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $dark_mode, $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Auto detects user system preference.', '3task-glossary' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Design', '3task-glossary' ); ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="azgl-card">
            <h2><?php esc_html_e( 'Preview', '3task-glossary' ); ?></h2>
            <div class="azgl-preview" id="azgl-preview">
                <!-- A-Z Navigation Preview -->
                <div class="azgl-nav azgl-nav-<?php echo esc_attr( $nav_style ); ?> azgl-scheme-<?php echo esc_attr( $color_scheme ); ?>">
                    <?php foreach ( range( 'A', 'Z' ) as $letter ) : ?>
                        <?php
                        $is_active = in_array( $letter, array( 'A', 'B', 'C', 'G', 'M', 'S' ), true );
                        $classes   = array( 'azgl-nav-letter' );
                        if ( ! $is_active ) {
                            $classes[] = 'azgl-nav-inactive';
                        }
                        ?>
                        <span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"><?php echo esc_html( $letter ); ?></span>
                    <?php endforeach; ?>
                </div>

                <!-- Entry List Preview -->
                <div class="azgl-entries azgl-scheme-<?php echo esc_attr( $color_scheme ); ?>">
                    <section class="azgl-section">
                        <h3 class="azgl-letter">A</h3>
                        <ul class="azgl-list">
                            <li class="azgl-item"><a href="#">API Reference</a></li>
                            <li class="azgl-item"><a href="#">Authentication</a></li>
                        </ul>
                    </section>
                    <section class="azgl-section">
                        <h3 class="azgl-letter">B</h3>
                        <ul class="azgl-list">
                            <li class="azgl-item"><a href="#">Backend</a></li>
                        </ul>
                    </section>
                    <section class="azgl-section">
                        <h3 class="azgl-letter">C</h3>
                        <ul class="azgl-list">
                            <li class="azgl-item"><a href="#">Cache</a></li>
                            <li class="azgl-item"><a href="#">Configuration</a></li>
                            <li class="azgl-item"><a href="#">Custom Post Type</a></li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render help tab.
     */
    private function render_help_tab() {
        ?>
        <div class="azgl-card">
            <h2><?php esc_html_e( 'Getting Started', '3task-glossary' ); ?></h2>

            <div class="azgl-help-section">
                <h3><?php esc_html_e( 'Creating Your First Glossary', '3task-glossary' ); ?></h3>
                <ol>
                    <li>
                        <strong><?php esc_html_e( 'Create a parent page:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Go to Pages → Add New and create a page (e.g., "Glossary").', '3task-glossary' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Register as glossary:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Go to 3task Glossary → Glossaries and select your page.', '3task-glossary' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Add entries:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Create child pages under your glossary page. Each child page becomes an entry.', '3task-glossary' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Done!', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Visit your glossary page to see the A-Z navigation and entry list.', '3task-glossary' ); ?>
                    </li>
                </ol>
            </div>

            <div class="azgl-help-section">
                <h3><?php esc_html_e( 'Adding Glossary Entries', '3task-glossary' ); ?></h3>
                <p>
                    <?php esc_html_e( 'To add entries to your glossary:', '3task-glossary' ); ?>
                </p>
                <ol>
                    <li><?php esc_html_e( 'Go to Pages → Add New', '3task-glossary' ); ?></li>
                    <li><?php esc_html_e( 'Enter the term as the page title', '3task-glossary' ); ?></li>
                    <li><?php esc_html_e( 'Write the definition in the page content', '3task-glossary' ); ?></li>
                    <li>
                        <strong><?php esc_html_e( 'Important:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Set the "Parent Page" to your glossary page in the Page Attributes box', '3task-glossary' ); ?>
                    </li>
                    <li><?php esc_html_e( 'Publish the page', '3task-glossary' ); ?></li>
                </ol>
            </div>

            <div class="azgl-help-section">
                <h3><?php esc_html_e( 'Customization', '3task-glossary' ); ?></h3>
                <p>
                    <?php esc_html_e( 'Customize the appearance in the Design tab:', '3task-glossary' ); ?>
                </p>
                <ul>
                    <li>
                        <strong><?php esc_html_e( 'Navigation Style:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Choose between Buttons, Pills, or Minimal style.', '3task-glossary' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Color Scheme:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Select from 6 beautiful color schemes or auto-match your theme.', '3task-glossary' ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Dark Mode:', '3task-glossary' ); ?></strong>
                        <?php esc_html_e( 'Auto-detect system preference or force light/dark mode.', '3task-glossary' ); ?>
                    </li>
                </ul>
            </div>
        </div>

        <div class="azgl-card">
            <h2><?php esc_html_e( 'Support & Resources', '3task-glossary' ); ?></h2>

            <div class="azgl-support-links">
                <a href="https://wordpress.org/support/plugin/simple-seo-glossary/" target="_blank" class="azgl-support-link">
                    <span class="dashicons dashicons-sos"></span>
                    <span><?php esc_html_e( 'Support Forum', '3task-glossary' ); ?></span>
                </a>
                <a href="https://wordpress.org/plugins/simple-seo-glossary/#faq" target="_blank" class="azgl-support-link">
                    <span class="dashicons dashicons-editor-help"></span>
                    <span><?php esc_html_e( 'FAQ', '3task-glossary' ); ?></span>
                </a>
                <a href="https://wordpress.org/support/plugin/simple-seo-glossary/reviews/#new-post" target="_blank" class="azgl-support-link">
                    <span class="dashicons dashicons-star-filled"></span>
                    <span><?php esc_html_e( 'Leave a Review', '3task-glossary' ); ?></span>
                </a>
            </div>
        </div>

        <div class="azgl-card">
            <h2><?php esc_html_e( 'System Information', '3task-glossary' ); ?></h2>

            <table class="azgl-system-info">
                <tr>
                    <th><?php esc_html_e( 'Plugin Version', '3task-glossary' ); ?></th>
                    <td><?php echo esc_html( AZGL_VERSION ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'WordPress Version', '3task-glossary' ); ?></th>
                    <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'PHP Version', '3task-glossary' ); ?></th>
                    <td><?php echo esc_html( PHP_VERSION ); ?></td>
                </tr>
            </table>
        </div>

        <div class="azgl-card azgl-help-pro-card">
            <h2>
                <span style="background: linear-gradient(135deg, var(--azgl-gradient-start), var(--azgl-gradient-end)); color: #fff; padding: 4px 12px; border-radius: 100px; font-size: 12px; margin-right: 10px;">PRO</span>
                <?php esc_html_e( 'Need More Features?', '3task-glossary' ); ?>
            </h2>

            <p><?php esc_html_e( '3task Glossary Pro unlocks powerful features for professional glossaries:', '3task-glossary' ); ?></p>

            <div class="azgl-help-pro-grid">
                <div class="azgl-help-pro-feature">
                    <span class="azgl-help-pro-icon">🔗</span>
                    <div>
                        <strong><?php esc_html_e( 'Site-Wide Auto-Linking', '3task-glossary' ); ?></strong>
                        <p><?php esc_html_e( 'Automatically link glossary terms in all your posts and pages.', '3task-glossary' ); ?></p>
                    </div>
                </div>
                <div class="azgl-help-pro-feature">
                    <span class="azgl-help-pro-icon">💬</span>
                    <div>
                        <strong><?php esc_html_e( 'Hover Tooltips', '3task-glossary' ); ?></strong>
                        <p><?php esc_html_e( 'Show term definitions on hover without leaving the page.', '3task-glossary' ); ?></p>
                    </div>
                </div>
                <div class="azgl-help-pro-feature">
                    <span class="azgl-help-pro-icon">🔍</span>
                    <div>
                        <strong><?php esc_html_e( 'Search Function', '3task-glossary' ); ?></strong>
                        <p><?php esc_html_e( 'Let visitors search within your glossary instantly.', '3task-glossary' ); ?></p>
                    </div>
                </div>
                <div class="azgl-help-pro-feature">
                    <span class="azgl-help-pro-icon">📊</span>
                    <div>
                        <strong><?php esc_html_e( 'Statistics & CSV', '3task-glossary' ); ?></strong>
                        <p><?php esc_html_e( 'View analytics and import/export via CSV.', '3task-glossary' ); ?></p>
                    </div>
                </div>
            </div>

            <a href="<?php echo esc_url( azgl()->get_upgrade_url() ); ?>" target="_blank" class="azgl-pro-btn" style="margin-top: 20px;">
                <?php esc_html_e( 'Learn More About Pro', '3task-glossary' ); ?>
                <span>→</span>
            </a>
        </div>
        <?php
    }
}
