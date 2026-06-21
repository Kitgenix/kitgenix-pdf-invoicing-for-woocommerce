<?php

namespace Kitgenix\PDF_Invoicing\Modules\Settings;

use Kitgenix\PDF_Invoicing\Core\ModuleInterface;
use Kitgenix\PDF_Invoicing\Modules\Email\EmailModule;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentDisplay;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes;

defined( 'ABSPATH' ) || exit;

class SettingsModule implements ModuleInterface {

    /**
     * Hook suffix returned by add_submenu_page(), used to scope admin asset loading.
     *
     * @var string
     */
    private $page_hook = '';

    private ?EmailModule $email_module = null;

    public function __construct( ?EmailModule $email_module = null ) {
        $this->email_module = $email_module;
    }

    public function get_id(): string {
        return 'settings';
    }

    public function register(): void {
        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
            add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
            add_action( 'admin_post_kitgenix_pdf_invoicing_clear_event_log', [ $this, 'handle_clear_event_log' ] );
            add_action( 'update_option_' . Settings::OPTION_KEY, [ $this, 'on_settings_saved' ], 10, 3 );
        }
    }

    private function is_settings_screen_now(): bool {
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen ) {
                if ( $this->page_hook && $screen->id === $this->page_hook ) {
                    return true;
                }
                if ( ! $this->page_hook && $screen->id === 'kitgenix_page_kitgenix-pdf-invoicing-settings' ) {
                    return true;
                }
            }
        }

        /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gating for notice placement; capability check happens before this method is used. */
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        return ( $page === 'kitgenix-pdf-invoicing-settings' );
    }

    public function render_admin_notices(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! $this->is_settings_screen_now() ) {
            return;
        }

        if ( function_exists( 'settings_errors' ) ) {
            settings_errors();
        }
    }

    public function add_menu_page(): void {
        if ( function_exists( '\\kitgenix_ensure_admin_menu' ) ) {
            \kitgenix_ensure_admin_menu();
        }

        $this->page_hook = (string) add_submenu_page(
            'kitgenix',
            __( 'PDF Invoicing Settings', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            __( 'PDF Invoicing', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'manage_woocommerce',
            'kitgenix-pdf-invoicing-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_assets( string $hook_suffix ): void {
        // Primary check via hook suffix, with a GET-based fallback for some WP setups.
        if ( $this->page_hook && $hook_suffix !== $this->page_hook ) {
            // Only allow reading URL params for authorized users — this is
            // a non-actionable UI helper, not form processing.
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- non-actionable UI helper; capability check above ensures only admins can read this. */
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            if ( ! $page || 'kitgenix-pdf-invoicing-settings' !== $page ) {
                return;
            }

        } elseif ( ! $this->page_hook && 'kitgenix_page_kitgenix-pdf-invoicing-settings' !== $hook_suffix ) {
            // Only allow reading URL params for authorized users — this is
            // a non-actionable UI helper, not form processing. Checking
            // capabilities here satisfies security scanners that warn when
            // superglobals are read without nonce verification.
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- non-actionable UI helper; capability check above ensures only admins can read this. */
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            if ( ! $page || 'kitgenix-pdf-invoicing-settings' !== $page ) {
                return;
            }
        }

        wp_enqueue_media();

        if ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL' ) ) {
            $base_url = KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL;
        } elseif ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE' ) ) {
            $base_url = plugin_dir_url( KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE );
        } else {
            $base_url = plugin_dir_url( __FILE__ );
        }

        if ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_DIR' ) ) {
            $base_dir = KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_DIR;
        } elseif ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE' ) ) {
            $base_dir = plugin_dir_path( KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_FILE );
        } else {
            $base_dir = plugin_dir_path( dirname( __DIR__, 3 ) . '/kitgenix-pdf-invoicing-for-woocommerce.php' );
        }

        $version = defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_VERSION' ) ? KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_VERSION : '1.0.5';
        $logo_media_js_file = trailingslashit( $base_dir ) . 'assets/js/admin-logo-media.js';
        $admin_css_file = trailingslashit( $base_dir ) . 'assets/css/admin.css';
        $tabs_js_file = trailingslashit( $base_dir ) . 'assets/js/kitgenix-admin-tabs.js';
        $settings_js_file = trailingslashit( $base_dir ) . 'assets/js/admin-settings.js';
        $logo_media_js_ver = file_exists( $logo_media_js_file ) ? (string) filemtime( $logo_media_js_file ) : $version;
        $admin_css_ver = file_exists( $admin_css_file ) ? (string) filemtime( $admin_css_file ) : $version;
        $tabs_js_ver = file_exists( $tabs_js_file ) ? (string) filemtime( $tabs_js_file ) : $version;
        $settings_js_ver = file_exists( $settings_js_file ) ? (string) filemtime( $settings_js_file ) : $version;

        $handle = 'kitgenix-pdf-invoicing-admin-logo-media';

        wp_enqueue_script(
            $handle,
            $base_url . 'assets/js/admin-logo-media.js',
            [ 'media-editor', 'media-upload' ],
            $logo_media_js_ver,
            true
        );

        // Color picker + custom admin styles for a modern settings UI.
        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_style( 'kitgenix-admin-ui' );

        wp_enqueue_style(
            'kitgenix-pdf-invoicing-admin-settings',
            $base_url . 'assets/css/admin.css',
            [ 'kitgenix-admin-ui' ],
            $admin_css_ver
        );

        wp_enqueue_script(
            'kitgenix-admin-tabs',
            $base_url . 'assets/js/kitgenix-admin-tabs.js',
            [],
            $tabs_js_ver,
            true
        );

        wp_enqueue_script(
            'kitgenix-pdf-invoicing-admin-settings',
            $base_url . 'assets/js/admin-settings.js',
            [ 'wp-color-picker', 'jquery' ],
            $settings_js_ver,
            true
        );

    }

    public function register_settings(): void {
        register_setting(
            'kitgenix_pdf_invoicing_for_woocommerce_',
            Settings::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => Settings::get_all(),
            ]
        );

        // Section 1: Company & document details.
        add_settings_section(
            'kitgenix_pdf_invoicing_main',
            __( 'Company & Document Details', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Configure the company details, logo, and basic invoice document settings.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        // Section 1b: Brand & styling options.
        add_settings_section(
            'kitgenix_pdf_invoicing_brand',
            __( 'Brand & Styling', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Adjust the colors used in your PDF documents so they match your store branding.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        add_settings_section(
            'kitgenix_pdf_invoicing_designer',
            __( 'Visual Template Designer', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Shape the bundled PDF layouts with no-code controls for alignment, spacing, panels, tables, totals, and footer presentation.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        add_settings_section(
            'kitgenix_pdf_invoicing_numbering',
            __( 'Numbering & Compliance', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Control prefixes, token-based numbering rules, reset periods, and fiscal-year aware sequences for issued documents.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        add_settings_section(
            'kitgenix_pdf_invoicing_localization',
            __( 'Localization, Tax & Legal Formatting', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Apply preset legal-formatting packs for cross-border stores, choose inclusive or exclusive tax presentation, set a document date format, and override tax terminology without editing templates.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        add_settings_section(
            'kitgenix_pdf_invoicing_fields',
            __( 'Field & Line-Item Controls', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Choose which order fields, fulfilment references, notes, and line-item details are rendered on your PDF documents.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        add_settings_section(
            'kitgenix_pdf_invoicing_document_rules',
            __( 'Document Generation Rules', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Decide which documents are available, which order statuses they apply to, and whether they require a paid or unpaid order state.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        // Section 2: Email attachments.
        add_settings_section(
            'kitgenix_pdf_invoicing_email',
            __( 'Email Attachments', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            function () {
                echo '<p>' . esc_html__( 'Choose which PDF documents to attach to specific WooCommerce emails.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
            },
            'kitgenix-pdf-invoicing-settings'
        );

        // Fields for section 1.
        $this->add_field(
            'company_name',
            __( 'Company name', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_company_name' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'company_address',
            __( 'Company address', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_company_address' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'company_email',
            __( 'Company email', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_company_email' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'company_phone',
            __( 'Company phone', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_company_phone' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'tax_id',
            __( 'Tax / VAT ID', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_tax_id' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'invoice_prefix',
            __( 'Invoice prefix', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_invoice_prefix' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'receipt_prefix',
            __( 'Receipt prefix', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_receipt_prefix' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'credit_note_prefix',
            __( 'Credit note prefix', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_credit_note_prefix' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'packing_slip_prefix',
            __( 'Packing slip prefix', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_packing_slip_prefix' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'invoice_number_format',
            __( 'Invoice number format', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_invoice_number_format' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'receipt_number_format',
            __( 'Receipt number format', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_receipt_number_format' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'packing_slip_number_format',
            __( 'Packing slip number format', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_packing_slip_number_format' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'credit_note_number_format',
            __( 'Credit note number format', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_credit_note_number_format' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'number_sequence_reset',
            __( 'Sequence reset period', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_number_sequence_reset' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'number_sequence_padding',
            __( 'Sequence padding', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_number_sequence_padding' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'fiscal_year_start_month',
            __( 'Fiscal year start month', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_fiscal_year_start_month' ],
            'kitgenix_pdf_invoicing_numbering'
        );

        $this->add_field(
            'localization_pack',
            __( 'Localization pack', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_localization_pack' ],
            'kitgenix_pdf_invoicing_localization'
        );

        $this->add_field(
            'tax_display_mode',
            __( 'Tax display mode', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_tax_display_mode' ],
            'kitgenix_pdf_invoicing_localization'
        );

        $this->add_field(
            'document_date_format',
            __( 'Document date format', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_document_date_format' ],
            'kitgenix_pdf_invoicing_localization'
        );

        $this->add_field(
            'show_currency_code',
            __( 'Show ISO currency code', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_currency_code' ],
            'kitgenix_pdf_invoicing_localization'
        );

        $this->add_field(
            'tax_label_override',
            __( 'Tax label override', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_tax_label_override' ],
            'kitgenix_pdf_invoicing_localization'
        );

        $this->add_field(
            'tax_registration_label_override',
            __( 'Tax registration label override', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_tax_registration_label_override' ],
            'kitgenix_pdf_invoicing_localization'
        );

        $this->add_field(
            'show_item_sku',
            __( 'Show SKU', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_item_sku' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_item_meta',
            __( 'Show item meta', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_item_meta' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_item_tax',
            __( 'Show line-item tax', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_item_tax' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_payment_method',
            __( 'Show payment method', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_payment_method' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_transaction_id',
            __( 'Show transaction ID', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_transaction_id' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_shipping_method',
            __( 'Show shipping method', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_shipping_method' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_tax_totals',
            __( 'Show tax totals', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_tax_totals' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_customer_note',
            __( 'Show customer note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_customer_note' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'show_internal_note',
            __( 'Show latest internal note', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_show_internal_note' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'custom_order_fields',
            __( 'Custom order fields', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_custom_order_fields' ],
            'kitgenix_pdf_invoicing_fields'
        );

        $this->add_field(
            'document_generation_rules',
            __( 'Availability rules', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_document_generation_rules' ],
            'kitgenix_pdf_invoicing_document_rules'
        );

        $this->add_field(
            'logo_id',
            __( 'Logo', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_logo_id' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'footer_text',
            __( 'Footer text', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_footer_text' ],
            'kitgenix_pdf_invoicing_main'
        );

        $this->add_field(
            'document_notes',
            __( 'Document notes', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_document_notes' ],
            'kitgenix_pdf_invoicing_main'
        );

        // Fields for brand & styling.
        $this->add_field(
            'template_style',
            __( 'Template style', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_template_style' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'primary_color',
            __( 'Primary color', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_primary_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'accent_color',
            __( 'Accent color', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_accent_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'text_color',
            __( 'Text color', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_text_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'muted_text_color',
            __( 'Muted text color', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_muted_text_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'border_color',
            __( 'Border color', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_border_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'table_header_bg',
            __( 'Table header background', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_table_header_bg' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'background_color',
            __( 'Document background', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_background_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'footer_bg_color',
            __( 'Footer background', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_footer_bg_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'footer_text_color',
            __( 'Footer text color', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_footer_text_color' ],
            'kitgenix_pdf_invoicing_brand'
        );

        $this->add_field(
            'designer_header_alignment',
            __( 'Header alignment', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_designer_header_alignment' ],
            'kitgenix_pdf_invoicing_designer'
        );

        $this->add_field(
            'designer_logo_scale',
            __( 'Logo scale', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_designer_logo_scale' ],
            'kitgenix_pdf_invoicing_designer'
        );

        $this->add_field(
            'designer_density',
            __( 'Content density', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_designer_density' ],
            'kitgenix_pdf_invoicing_designer'
        );

        $this->add_field(
            'designer_panel_style',
            __( 'Panel style', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_designer_panel_style' ],
            'kitgenix_pdf_invoicing_designer'
        );

        $this->add_field(
            'designer_table_style',
            __( 'Order table style', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_designer_table_style' ],
            'kitgenix_pdf_invoicing_designer'
        );

        $this->add_field(
            'designer_totals_emphasis',
            __( 'Totals emphasis', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_designer_totals_emphasis' ],
            'kitgenix_pdf_invoicing_designer'
        );

        $this->add_field(
            'designer_footer_alignment',
            __( 'Footer alignment', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_designer_footer_alignment' ],
            'kitgenix_pdf_invoicing_designer'
        );

        // Field for section 2: email attachments grid.
        $this->add_field(
            'email_attachments',
            __( 'Attach documents to emails', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            [ $this, 'field_email_attachments' ],
            'kitgenix_pdf_invoicing_email'
        );
    }

    protected function add_field( string $id, string $label, callable $callback, string $section_id ): void {
        add_settings_field(
            $id,
            $label,
            $callback,
            'kitgenix-pdf-invoicing-settings',
            $section_id,
            [
                'label_for' => $id,
            ]
        );
    }

    /**
     * Sanitize entire settings array.
     */
    public function sanitize_settings( $input ): array {
        $output   = [];
        $input    = is_array( $input ) ? $input : [];
        $defaults = Settings::get_all();

        // When processing settings via the WP Settings API ensure the request
        // originates from a privileged admin and carries a valid nonce.
        // Important: the Settings API nonce action is "{$option_group}-options"
        // and should only be required when this sanitize callback is invoked
        // as part of the options.php form submission (programmatic updates to
        // the option should not be blocked by missing POST data).
        $option_group = 'kitgenix_pdf_invoicing_for_woocommerce_';
        $posted_group = isset( $_POST['option_page'] ) ? sanitize_key( wp_unslash( $_POST['option_page'] ) ) : '';

        if ( is_admin() && $posted_group === $option_group ) {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return $defaults;
            }

            if ( ! isset( $_POST['_wpnonce'] ) ) {
                return $defaults;
            }

            $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
            if ( ! wp_verify_nonce( $nonce, $option_group . '-options' ) ) {
                return $defaults;
            }
        }

        $output['company_name']    = sanitize_text_field( $input['company_name'] ?? $defaults['company_name'] );
        $output['company_address'] = wp_kses_post( $input['company_address'] ?? $defaults['company_address'] );
        $output['company_email']   = sanitize_email( $input['company_email'] ?? $defaults['company_email'] );
        $output['company_phone']   = sanitize_text_field( $input['company_phone'] ?? $defaults['company_phone'] );
        $output['tax_id']          = sanitize_text_field( $input['tax_id'] ?? $defaults['tax_id'] );
        $style = isset( $input['template_style'] ) ? sanitize_key( (string) $input['template_style'] ) : (string) ( $defaults['template_style'] ?? 'standard' );
        $allowed_styles = [ 'standard', 'simple', 'modern', 'business' ];
        $output['template_style'] = in_array( $style, $allowed_styles, true ) ? $style : 'standard';
        $designer_header_alignment = isset( $input['designer_header_alignment'] ) ? sanitize_key( (string) $input['designer_header_alignment'] ) : (string) $defaults['designer_header_alignment'];
        $designer_logo_scale       = isset( $input['designer_logo_scale'] ) ? sanitize_key( (string) $input['designer_logo_scale'] ) : (string) $defaults['designer_logo_scale'];
        $designer_density          = isset( $input['designer_density'] ) ? sanitize_key( (string) $input['designer_density'] ) : (string) $defaults['designer_density'];
        $designer_panel_style      = isset( $input['designer_panel_style'] ) ? sanitize_key( (string) $input['designer_panel_style'] ) : (string) $defaults['designer_panel_style'];
        $designer_table_style      = isset( $input['designer_table_style'] ) ? sanitize_key( (string) $input['designer_table_style'] ) : (string) $defaults['designer_table_style'];
        $designer_totals_emphasis  = isset( $input['designer_totals_emphasis'] ) ? sanitize_key( (string) $input['designer_totals_emphasis'] ) : (string) $defaults['designer_totals_emphasis'];
        $designer_footer_alignment = isset( $input['designer_footer_alignment'] ) ? sanitize_key( (string) $input['designer_footer_alignment'] ) : (string) $defaults['designer_footer_alignment'];

        $output['designer_header_alignment'] = in_array( $designer_header_alignment, [ 'left', 'center', 'right' ], true ) ? $designer_header_alignment : 'left';
        $output['designer_logo_scale']       = in_array( $designer_logo_scale, [ 'small', 'medium', 'large' ], true ) ? $designer_logo_scale : 'medium';
        $output['designer_density']          = in_array( $designer_density, [ 'compact', 'comfortable', 'spacious' ], true ) ? $designer_density : 'comfortable';
        $output['designer_panel_style']      = in_array( $designer_panel_style, [ 'minimal', 'boxed', 'tinted' ], true ) ? $designer_panel_style : 'minimal';
        $output['designer_table_style']      = in_array( $designer_table_style, [ 'clean', 'striped', 'grid' ], true ) ? $designer_table_style : 'clean';
        $output['designer_totals_emphasis']  = in_array( $designer_totals_emphasis, [ 'standard', 'boxed', 'highlight' ], true ) ? $designer_totals_emphasis : 'standard';
        $output['designer_footer_alignment'] = in_array( $designer_footer_alignment, [ 'left', 'center', 'right' ], true ) ? $designer_footer_alignment : 'left';
        $output['invoice_prefix']  = sanitize_text_field( $input['invoice_prefix'] ?? $defaults['invoice_prefix'] );
        $output['receipt_prefix']  = sanitize_text_field( $input['receipt_prefix'] ?? $defaults['receipt_prefix'] );
        $output['packing_slip_prefix'] = sanitize_text_field( $input['packing_slip_prefix'] ?? $defaults['packing_slip_prefix'] );
        $output['credit_note_prefix'] = sanitize_text_field( $input['credit_note_prefix'] ?? $defaults['credit_note_prefix'] );
        $output['invoice_number_format'] = sanitize_text_field( $input['invoice_number_format'] ?? $defaults['invoice_number_format'] );
        $output['receipt_number_format'] = sanitize_text_field( $input['receipt_number_format'] ?? $defaults['receipt_number_format'] );
        $output['packing_slip_number_format'] = sanitize_text_field( $input['packing_slip_number_format'] ?? $defaults['packing_slip_number_format'] );
        $output['credit_note_number_format'] = sanitize_text_field( $input['credit_note_number_format'] ?? $defaults['credit_note_number_format'] );

        $sequence_reset = isset( $input['number_sequence_reset'] ) ? sanitize_key( (string) $input['number_sequence_reset'] ) : (string) $defaults['number_sequence_reset'];
        $allowed_sequence_reset = [ 'none', 'calendar_year', 'fiscal_year' ];
        $output['number_sequence_reset'] = in_array( $sequence_reset, $allowed_sequence_reset, true ) ? $sequence_reset : 'none';

        $sequence_padding = isset( $input['number_sequence_padding'] ) ? absint( $input['number_sequence_padding'] ) : (int) $defaults['number_sequence_padding'];
        if ( $sequence_padding < 1 ) {
            $sequence_padding = 1;
        }
        if ( $sequence_padding > 12 ) {
            $sequence_padding = 12;
        }
        $output['number_sequence_padding'] = $sequence_padding;

        $fiscal_year_start_month = isset( $input['fiscal_year_start_month'] ) ? absint( $input['fiscal_year_start_month'] ) : (int) $defaults['fiscal_year_start_month'];
        if ( $fiscal_year_start_month < 1 || $fiscal_year_start_month > 12 ) {
            $fiscal_year_start_month = 1;
        }
        $output['fiscal_year_start_month'] = $fiscal_year_start_month;

        $allowed_localization_packs = array_keys( DocumentDisplay::get_localization_pack_options() );
        $localization_pack = isset( $input['localization_pack'] ) ? sanitize_key( (string) $input['localization_pack'] ) : (string) $defaults['localization_pack'];
        $output['localization_pack'] = in_array( $localization_pack, $allowed_localization_packs, true ) ? $localization_pack : 'standard';

        $tax_display_mode = isset( $input['tax_display_mode'] ) ? sanitize_key( (string) $input['tax_display_mode'] ) : (string) $defaults['tax_display_mode'];
        $output['tax_display_mode'] = in_array( $tax_display_mode, [ 'exclusive', 'inclusive' ], true ) ? $tax_display_mode : 'exclusive';

        $output['document_date_format'] = sanitize_text_field( $input['document_date_format'] ?? $defaults['document_date_format'] );
        $output['tax_label_override'] = sanitize_text_field( $input['tax_label_override'] ?? $defaults['tax_label_override'] );
        $output['tax_registration_label_override'] = sanitize_text_field( $input['tax_registration_label_override'] ?? $defaults['tax_registration_label_override'] );

        foreach ( [
            'show_currency_code',
            'show_item_sku',
            'show_item_meta',
            'show_item_tax',
            'show_payment_method',
            'show_transaction_id',
            'show_shipping_method',
            'show_tax_totals',
            'show_customer_note',
            'show_internal_note',
        ] as $boolean_setting ) {
            $output[ $boolean_setting ] = ! empty( $input[ $boolean_setting ] );
        }

        $custom_order_fields = isset( $input['custom_order_fields'] ) ? (string) $input['custom_order_fields'] : (string) $defaults['custom_order_fields'];
        $custom_order_lines  = preg_split( '/\r\n|\r|\n/', $custom_order_fields );
        $sanitized_custom_order_lines = [];
        if ( is_array( $custom_order_lines ) ) {
            foreach ( $custom_order_lines as $custom_order_line ) {
                $custom_order_line = sanitize_text_field( (string) $custom_order_line );
                if ( '' !== trim( $custom_order_line ) ) {
                    $sanitized_custom_order_lines[] = $custom_order_line;
                }
            }
        }
        $output['custom_order_fields'] = implode( "\n", $sanitized_custom_order_lines );

        $output['logo_id']         = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : (int) $defaults['logo_id'];
        $output['footer_text']     = wp_kses_post( $input['footer_text'] ?? $defaults['footer_text'] );

        $output['document_notes'] = wp_kses_post( $input['document_notes'] ?? $defaults['document_notes'] );

        // Brand / styling colors.
        $output['primary_color']    = sanitize_hex_color( $input['primary_color'] ?? $defaults['primary_color'] );
        $output['accent_color']     = sanitize_hex_color( $input['accent_color'] ?? $defaults['accent_color'] );
        $output['text_color']       = sanitize_hex_color( $input['text_color'] ?? $defaults['text_color'] );
        $output['muted_text_color'] = sanitize_hex_color( $input['muted_text_color'] ?? $defaults['muted_text_color'] );
        $output['border_color']     = sanitize_hex_color( $input['border_color'] ?? $defaults['border_color'] );
        $output['table_header_bg']  = sanitize_hex_color( $input['table_header_bg'] ?? $defaults['table_header_bg'] );
        $output['background_color'] = sanitize_hex_color( $input['background_color'] ?? $defaults['background_color'] );
        $output['footer_bg_color']   = sanitize_hex_color( $input['footer_bg_color'] ?? $defaults['footer_bg_color'] );
        $output['footer_text_color'] = sanitize_hex_color( $input['footer_text_color'] ?? $defaults['footer_text_color'] );

        if ( ! array_key_exists( 'document_generation_rules', $input ) ) {
            $output['document_generation_rules'] = ( isset( $defaults['document_generation_rules'] ) && is_array( $defaults['document_generation_rules'] ) )
                ? $defaults['document_generation_rules']
                : Settings::get_default_document_generation_rules();
        } else {
            $default_document_rules = Settings::get_default_document_generation_rules();
            $input_document_rules   = is_array( $input['document_generation_rules'] ?? null ) ? (array) $input['document_generation_rules'] : [];
            $document_rule_output   = [];

            foreach ( $default_document_rules as $document_type => $rule_defaults ) {
                $rule_input = isset( $input_document_rules[ $document_type ] ) && is_array( $input_document_rules[ $document_type ] )
                    ? (array) $input_document_rules[ $document_type ]
                    : [];

                $status_parts = preg_split( '/[\s,]+/', (string) ( $rule_input['allowed_statuses'] ?? $rule_defaults['allowed_statuses'] ) );
                $allowed_statuses = [];
                if ( is_array( $status_parts ) ) {
                    foreach ( $status_parts as $status_part ) {
                        $status_part = sanitize_key( str_replace( 'wc-', '', (string) $status_part ) );
                        if ( '' !== $status_part ) {
                            $allowed_statuses[] = $status_part;
                        }
                    }
                }

                $payment_requirement = isset( $rule_input['payment_requirement'] ) ? sanitize_key( (string) $rule_input['payment_requirement'] ) : (string) $rule_defaults['payment_requirement'];
                if ( ! in_array( $payment_requirement, [ 'any', 'paid', 'unpaid' ], true ) ) {
                    $payment_requirement = 'any';
                }

                $document_rule_output[ $document_type ] = [
                    'enabled'             => ! empty( $rule_input['enabled'] ),
                    'allowed_statuses'    => implode( ',', array_values( array_unique( $allowed_statuses ) ) ),
                    'payment_requirement' => $payment_requirement,
                ];
            }

            $output['document_generation_rules'] = $document_rule_output;
        }

        // Email attachments: sanitize checkboxes.
        // IMPORTANT: this settings page is split into multiple <form> elements
        // (tabs). When saving a tab that does NOT contain the email attachments
        // matrix, `email_attachments` will not be posted at all.
        //
        // In that case, preserve the currently stored mapping instead of
        // resetting everything to false.
        if ( ! array_key_exists( 'email_attachments', $input ) ) {
            $output['email_attachments'] = ( isset( $defaults['email_attachments'] ) && is_array( $defaults['email_attachments'] ) )
                ? $defaults['email_attachments']
                : Settings::get_default_email_attachments();

            return $output;
        }

        $default_email_map = Settings::get_default_email_attachments();
        $document_types    = DocumentTypes::all();
        $input_map         = is_array( $input['email_attachments'] ?? null ) ? (array) $input['email_attachments'] : [];

        $email_output = [];

        foreach ( $default_email_map as $email_id => $doc_defaults ) {
            $email_output[ $email_id ] = [];

            $doc_input = isset( $input_map[ $email_id ] ) && is_array( $input_map[ $email_id ] )
                ? $input_map[ $email_id ]
                : [];

            foreach ( $document_types as $doc_type ) {
                $raw = $doc_input[ $doc_type ] ?? '';
                // Checkbox style – presence means "on".
                $email_output[ $email_id ][ $doc_type ] = ! empty( $raw );
            }
        }

        $output['email_attachments'] = $email_output;

        return $output;
    }

    /**
     * Individual field renderers.
     */

    public function field_company_name(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="company_name"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[company_name]"
               value="<?php echo esc_attr( $settings['company_name'] ); ?>"
               class="regular-text" />
        <?php
    }

    public function field_company_address(): void {
        $settings = Settings::get_all();
        ?>
        <textarea id="company_address"
                  name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[company_address]"
                  rows="3"
                  class="large-text"><?php echo esc_textarea( $settings['company_address'] ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Shown as the “From” address on documents. Line breaks are preserved.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_company_email(): void {
        $settings = Settings::get_all();
        ?>
        <input type="email"
               id="company_email"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[company_email]"
               value="<?php echo esc_attr( $settings['company_email'] ); ?>"
               class="regular-text" />
        <?php
    }

    public function field_company_phone(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="company_phone"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[company_phone]"
               value="<?php echo esc_attr( $settings['company_phone'] ); ?>"
               class="regular-text" />
        <?php
    }

    public function field_tax_id(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="tax_id"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[tax_id]"
               value="<?php echo esc_attr( $settings['tax_id'] ); ?>"
               class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Tax/VAT registration number shown on invoices and credit notes.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_template_style(): void {
        $settings = Settings::get_all();

        $current = isset( $settings['template_style'] ) ? sanitize_key( (string) $settings['template_style'] ) : 'standard';
        $styles  = [
            'standard'  => __( 'Standard', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'simple'    => __( 'Simple', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'modern'    => __( 'Modern', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'business'  => __( 'Business', 'kitgenix-pdf-invoicing-for-woocommerce' ),
        ];
        ?>
        <select id="template_style" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[template_style]">
            <?php foreach ( $styles as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Choose the base PDF template pack to use for invoices, receipts, packing slips and credit notes.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_invoice_prefix(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="invoice_prefix"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[invoice_prefix]"
               value="<?php echo esc_attr( $settings['invoice_prefix'] ); ?>"
               class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Optional prefix added before the invoice number (e.g. KG-, INV-2025-).', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_receipt_prefix(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="receipt_prefix"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[receipt_prefix]"
               value="<?php echo esc_attr( $settings['receipt_prefix'] ); ?>"
               class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Optional prefix added before the receipt number (falls back to invoice prefix if empty).', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_credit_note_prefix(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="credit_note_prefix"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[credit_note_prefix]"
               value="<?php echo esc_attr( $settings['credit_note_prefix'] ); ?>"
               class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Prefix used for credit notes (default: CN-).', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    protected function render_number_format_field( string $id, string $description ): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="<?php echo esc_attr( $id ); ?>"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $id ); ?>]"
               value="<?php echo esc_attr( $settings[ $id ] ?? '' ); ?>"
               class="large-text code" />
        <p class="description"><?php echo esc_html( $description ); ?></p>
        <p class="description"><?php esc_html_e( 'Supported tokens: {prefix}, {order_number}, {order_id}, {sequence}, {refund_sequence}, {year}, {yy}, {month}, {day}, {country}, {billing_country}, {shipping_country}, {fiscal_year}, {fiscal_year_short}, {fiscal_year_start}, {fiscal_year_end}. Issued documents keep their stored number even if you change this later.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    public function field_packing_slip_prefix(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="packing_slip_prefix"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[packing_slip_prefix]"
               value="<?php echo esc_attr( $settings['packing_slip_prefix'] ); ?>"
               class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Prefix used when packing slip formats include {prefix}. Default: PS-.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_invoice_number_format(): void {
        $this->render_number_format_field(
            'invoice_number_format',
            __( 'Default: {prefix}{order_number}. Use {sequence} when you want a resettable compliance counter instead of the WooCommerce order number.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_receipt_number_format(): void {
        $this->render_number_format_field(
            'receipt_number_format',
            __( 'Default: {prefix}{order_number}. You can add country or fiscal-year tokens for region-specific receipt rules.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_packing_slip_number_format(): void {
        $this->render_number_format_field(
            'packing_slip_number_format',
            __( 'Default: {prefix}{order_number}. Useful when fulfilment teams need packing slips with their own numbering scheme.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_credit_note_number_format(): void {
        $this->render_number_format_field(
            'credit_note_number_format',
            __( 'Default: {prefix}{order_number}-{refund_sequence}. Switch to {sequence} if you need a dedicated compliance counter for credit notes.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_number_sequence_reset(): void {
        $settings = Settings::get_all();
        $current  = isset( $settings['number_sequence_reset'] ) ? sanitize_key( (string) $settings['number_sequence_reset'] ) : 'none';
        $options  = [
            'none'          => __( 'Never reset', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'calendar_year' => __( 'Reset each calendar year', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'fiscal_year'   => __( 'Reset each fiscal year', 'kitgenix-pdf-invoicing-for-woocommerce' ),
        ];
        ?>
        <select id="number_sequence_reset" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[number_sequence_reset]">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( 'Only affects number formats that include the {sequence} token.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    public function field_number_sequence_padding(): void {
        $settings = Settings::get_all();
        $value    = isset( $settings['number_sequence_padding'] ) ? absint( $settings['number_sequence_padding'] ) : 4;
        ?>
        <input type="number"
               id="number_sequence_padding"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[number_sequence_padding]"
               min="1"
               max="12"
               value="<?php echo esc_attr( (string) $value ); ?>"
               class="small-text" />
        <p class="description"><?php esc_html_e( 'Pads the {sequence} token with leading zeros. Example: 0001.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    public function field_fiscal_year_start_month(): void {
        $settings = Settings::get_all();
        $current  = isset( $settings['fiscal_year_start_month'] ) ? absint( $settings['fiscal_year_start_month'] ) : 1;
        ?>
        <select id="fiscal_year_start_month" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[fiscal_year_start_month]">
            <?php for ( $month = 1; $month <= 12; $month++ ) : ?>
                <option value="<?php echo esc_attr( (string) $month ); ?>" <?php selected( $current, $month ); ?>>
                    <?php echo esc_html( wp_date( 'F', mktime( 0, 0, 0, $month, 1, 2000 ) ) ); ?>
                </option>
            <?php endfor; ?>
        </select>
        <p class="description"><?php esc_html_e( 'Used for {fiscal_year} tokens and fiscal-year sequence resets.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    protected function render_checkbox_field( string $id, string $description ): void {
        $settings = Settings::get_all();
        $checked  = ! empty( $settings[ $id ] );
        ?>
        <label for="<?php echo esc_attr( $id ); ?>">
            <input type="checkbox"
                   id="<?php echo esc_attr( $id ); ?>"
                   name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $id ); ?>]"
                   value="1"
                   <?php checked( $checked ); ?> />
            <?php echo esc_html__( 'Enabled', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </label>
        <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php
    }

    public function field_show_item_sku(): void {
        $this->render_checkbox_field(
            'show_item_sku',
            __( 'Display product SKUs beneath line items on invoices, receipts, and packing slips.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_show_item_meta(): void {
        $this->render_checkbox_field(
            'show_item_meta',
            __( 'Display WooCommerce item meta such as variations, add-ons, and product options under each line item.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_show_item_tax(): void {
        $this->render_checkbox_field(
            'show_item_tax',
            __( 'Add per-line tax amounts beneath item descriptions when you need more detailed tax visibility.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_show_payment_method(): void {
        $this->render_checkbox_field(
            'show_payment_method',
            __( 'Show the WooCommerce payment method in document order-data sections.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_show_transaction_id(): void {
        $this->render_checkbox_field(
            'show_transaction_id',
            __( 'Show the payment gateway transaction ID when one is available.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_show_shipping_method(): void {
        $this->render_checkbox_field(
            'show_shipping_method',
            __( 'Show the selected shipping method. Useful for packing slips and fulfilment paperwork.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_show_tax_totals(): void {
        $this->render_checkbox_field(
            'show_tax_totals',
            __( 'Keep tax/VAT rows visible in totals tables. Disable if you want cleaner consumer-style documents.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_localization_pack(): void {
        $this->render_select_field(
            'localization_pack',
            DocumentDisplay::get_localization_pack_options(),
            __( 'Choose a preset pack for legal document titles and tax terminology. For example, Australia GST switches invoices to “Tax Invoice” and credit notes to “Adjustment Note”.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_tax_display_mode(): void {
        $this->render_select_field(
            'tax_display_mode',
            [
                'exclusive' => __( 'Tax exclusive (B2B style)', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'inclusive' => __( 'Tax inclusive (consumer style)', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Controls whether invoices, receipts, and credit-note amounts use excluding-tax or including-tax values in line-item and totals tables.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_document_date_format(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="document_date_format"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[document_date_format]"
               value="<?php echo esc_attr( (string) ( $settings['document_date_format'] ?? '' ) ); ?>"
               class="regular-text code"
               placeholder="d/m/Y" />
        <p class="description"><?php esc_html_e( 'Optional PHP/WordPress date format for generated documents. Leave empty to use the WooCommerce date format. Examples: d/m/Y, m/d/Y, Y-m-d.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    public function field_show_currency_code(): void {
        $this->render_checkbox_field(
            'show_currency_code',
            __( 'Append the ISO currency code (for example EUR, USD, GBP, AUD) after formatted amounts for cross-border clarity.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_tax_label_override(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="tax_label_override"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[tax_label_override]"
               value="<?php echo esc_attr( (string) ( $settings['tax_label_override'] ?? '' ) ); ?>"
               class="regular-text"
               placeholder="VAT / GST / Sales Tax" />
        <p class="description"><?php esc_html_e( 'Optional. Override the pack tax term used in labels such as “Price (Excl. VAT)” or tax rows. Useful for translated labels like TVA, MwSt, IVA, or GST.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    public function field_tax_registration_label_override(): void {
        $settings = Settings::get_all();
        ?>
        <input type="text"
               id="tax_registration_label_override"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[tax_registration_label_override]"
               value="<?php echo esc_attr( (string) ( $settings['tax_registration_label_override'] ?? '' ) ); ?>"
               class="regular-text"
               placeholder="VAT Number / ABN / GST Number" />
        <p class="description"><?php esc_html_e( 'Optional. Override the company tax-registration label shown next to your stored tax/VAT ID.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    public function field_show_customer_note(): void {
        $this->render_checkbox_field(
            'show_customer_note',
            __( 'Show the order customer note on generated PDFs when one exists.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_show_internal_note(): void {
        $this->render_checkbox_field(
            'show_internal_note',
            __( 'Show the latest private WooCommerce order note. Enable only if you are comfortable exposing internal notes on generated PDFs.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_custom_order_fields(): void {
        $settings = Settings::get_all();
        ?>
        <textarea id="custom_order_fields"
                  name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[custom_order_fields]"
                  rows="5"
                  class="large-text code"><?php echo esc_textarea( $settings['custom_order_fields'] ?? '' ); ?></textarea>
        <p class="description"><?php esc_html_e( 'Add one WooCommerce order meta key per line using meta_key|Label. Example: _tracking_number|Tracking Number or _warehouse_pick_ref|Warehouse Pick Ref.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <p class="description"><?php esc_html_e( 'These rows are shown in the document order-data section when the chosen meta value exists on the order.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
        <?php
    }

    public function field_document_generation_rules(): void {
        $settings       = Settings::get_all();
        $stored_rules   = isset( $settings['document_generation_rules'] ) && is_array( $settings['document_generation_rules'] )
            ? $settings['document_generation_rules']
            : [];
        $default_rules  = Settings::get_default_document_generation_rules();
        $document_types = array_keys( $default_rules );
        ?>
        <table class="widefat striped kitgenix-pdf-email-attachments-table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Document', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
                <th class="kitgenix-align-center"><?php esc_html_e( 'Enabled', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Allowed order statuses', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Payment requirement', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $document_types as $document_type ) : ?>
                <?php
                $rule = isset( $stored_rules[ $document_type ] ) && is_array( $stored_rules[ $document_type ] )
                    ? wp_parse_args( $stored_rules[ $document_type ], $default_rules[ $document_type ] )
                    : $default_rules[ $document_type ];
                $status_value = (string) ( $rule['allowed_statuses'] ?? '' );
                $payment_rule = isset( $rule['payment_requirement'] ) ? sanitize_key( (string) $rule['payment_requirement'] ) : 'any';
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( DocumentTypes::get_label( $document_type ) ); ?></strong>
                    </td>
                    <td class="kitgenix-align-center">
                        <input type="checkbox"
                               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[document_generation_rules][<?php echo esc_attr( $document_type ); ?>][enabled]"
                               value="1"
                               <?php checked( ! empty( $rule['enabled'] ) ); ?> />
                    </td>
                    <td>
                        <input type="text"
                               class="regular-text"
                               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[document_generation_rules][<?php echo esc_attr( $document_type ); ?>][allowed_statuses]"
                               value="<?php echo esc_attr( $status_value ); ?>"
                               placeholder="processing,completed" />
                        <p class="description"><?php esc_html_e( 'Optional comma-separated WooCommerce statuses without the wc- prefix. Leave empty to allow all statuses.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                    </td>
                    <td>
                        <select name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[document_generation_rules][<?php echo esc_attr( $document_type ); ?>][payment_requirement]">
                            <option value="any" <?php selected( $payment_rule, 'any' ); ?>><?php esc_html_e( 'Any payment state', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></option>
                            <option value="paid" <?php selected( $payment_rule, 'paid' ); ?>><?php esc_html_e( 'Paid orders only', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></option>
                            <option value="unpaid" <?php selected( $payment_rule, 'unpaid' ); ?>><?php esc_html_e( 'Unpaid orders only', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></option>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php esc_html_e( 'These rules are enforced for admin previews, batch exports, email attachments, and any document generated through the shared PDF pipeline.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_document_notes(): void {
        $settings = Settings::get_all();
        ?>
        <textarea id="document_notes"
                  name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[document_notes]"
                  rows="3"
                  class="large-text"><?php echo esc_textarea( $settings['document_notes'] ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional notes shown on documents (invoices, receipts).', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    public function field_logo_id(): void {
        $settings = Settings::get_all();
        $logo_id  = isset( $settings['logo_id'] ) ? (int) $settings['logo_id'] : 0;
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
        $remove_class = 'button-link-delete kitgenix-pdf-invoicing-for-woocommerce-logo-remove';
        if ( ! $logo_id ) {
            $remove_class .= ' kitgenix-is-hidden';
        }
        ?>
        <div class="kitgenix-pdf-invoicing-for-woocommerce-logo-field">
            <input
                type="hidden"
                id="logo_id"
                class="kitgenix-pdf-invoicing-for-woocommerce-logo-id-field"
                name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[logo_id]"
                value="<?php echo esc_attr( $logo_id ); ?>"
            />

            <div class="kitgenix-pdf-invoicing-for-woocommerce-logo-preview">
                <?php if ( $logo_url ) : ?>
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="">
                <?php endif; ?>
            </div>

            <button
                type="button"
                class="button kitgenix-pdf-invoicing-for-woocommerce-logo-select"
                data-frame-title="<?php esc_attr_e( 'Select logo', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>"
                data-frame-button="<?php esc_attr_e( 'Use this logo', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>"
            >
                <?php esc_html_e( 'Select logo', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
            </button>

            <button
                type="button"
                class="<?php echo esc_attr( $remove_class ); ?>"
            >
                <?php esc_html_e( 'Remove logo', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
            </button>

            <p class="description">
                <?php esc_html_e( 'Choose a logo image from your media library. It will be shown on your documents.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
            </p>
        </div>
        <?php
    }

    public function field_footer_text(): void {
        $settings = Settings::get_all();
        ?>
        <textarea id="footer_text"
                  name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[footer_text]"
                  rows="3"
                  class="large-text"><?php echo esc_textarea( $settings['footer_text'] ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional footer text, e.g. terms, thank you message, or bank details.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    /**
     * Color picker fields.
     */

    protected function render_color_field( string $id, string $description = '' ): void {
        $settings = Settings::get_all();
        $value    = $settings[ $id ] ?? '';
        ?>
        <input type="text"
               id="<?php echo esc_attr( $id ); ?>"
               name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $id ); ?>]"
               value="<?php echo esc_attr( $value ); ?>"
               class="kitgenix-pdf-invoicing-for-woocommerce-color-field"
               data-default-color="<?php echo esc_attr( $value ); ?>" />
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    protected function render_select_field( string $id, array $options, string $description = '' ): void {
        $settings = Settings::get_all();
        $value    = isset( $settings[ $id ] ) ? sanitize_key( (string) $settings[ $id ] ) : '';
        ?>
        <select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $id ); ?>]">
            <?php foreach ( $options as $option_value => $option_label ) : ?>
                <option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
                    <?php echo esc_html( $option_label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function field_primary_color(): void {
        $this->render_color_field(
            'primary_color',
            __( 'Used for main headings and key labels.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_accent_color(): void {
        $this->render_color_field(
            'accent_color',
            __( 'Used for accent lines and subtle highlights.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_text_color(): void {
        $this->render_color_field(
            'text_color',
            __( 'Main body text color.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_muted_text_color(): void {
        $this->render_color_field(
            'muted_text_color',
            __( 'Secondary labels and small text.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_border_color(): void {
        $this->render_color_field(
            'border_color',
            __( 'Borders around tables and sections.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_table_header_bg(): void {
        $this->render_color_field(
            'table_header_bg',
            __( 'Background for table headers.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_background_color(): void {
        $this->render_color_field(
            'background_color',
            __( 'Overall document background color.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_footer_bg_color(): void {
        $this->render_color_field(
            'footer_bg_color',
            __( 'Background colour used in the document footer.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_footer_text_color(): void {
        $this->render_color_field(
            'footer_text_color',
            __( 'Text colour used in the document footer.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_designer_header_alignment(): void {
        $this->render_select_field(
            'designer_header_alignment',
            [
                'left'   => __( 'Left aligned', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'center' => __( 'Centered', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'right'  => __( 'Right aligned', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Align the logo block, company header, and document title without editing a template file.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_designer_logo_scale(): void {
        $this->render_select_field(
            'designer_logo_scale',
            [
                'small'  => __( 'Small', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'medium' => __( 'Medium', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'large'  => __( 'Large', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Scale the document logo for compact invoices or more brand-led layouts.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_designer_density(): void {
        $this->render_select_field(
            'designer_density',
            [
                'compact'     => __( 'Compact', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'comfortable' => __( 'Comfortable', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'spacious'    => __( 'Spacious', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Control table spacing, note padding, and overall document density for the bundled layouts.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_designer_panel_style(): void {
        $this->render_select_field(
            'designer_panel_style',
            [
                'minimal' => __( 'Minimal', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'boxed'   => __( 'Boxed', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'tinted'  => __( 'Tinted', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Change the presentation of address, order-data, and notes panels without touching PHP templates.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_designer_table_style(): void {
        $this->render_select_field(
            'designer_table_style',
            [
                'clean'   => __( 'Clean', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'striped' => __( 'Striped rows', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'grid'    => __( 'Grid borders', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Restyle the main order-items table for a cleaner invoice, zebra rows, or a full grid look.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_designer_totals_emphasis(): void {
        $this->render_select_field(
            'designer_totals_emphasis',
            [
                'standard'  => __( 'Standard', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'boxed'     => __( 'Boxed totals panel', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'highlight' => __( 'Highlight final total', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Draw more or less attention to the totals area without creating a custom template override.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    public function field_designer_footer_alignment(): void {
        $this->render_select_field(
            'designer_footer_alignment',
            [
                'left'   => __( 'Left aligned', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'center' => __( 'Centered', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                'right'  => __( 'Right aligned', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            ],
            __( 'Align footer text for legal copy, payment details, or a cleaner branded finish.', 'kitgenix-pdf-invoicing-for-woocommerce' )
        );
    }

    /**
     * Email attachments matrix: email ID × document type checkboxes.
     */
    public function field_email_attachments(): void {
        $settings      = Settings::get_all();
        $current_map   = isset( $settings['email_attachments'] ) && is_array( $settings['email_attachments'] )
            ? $settings['email_attachments']
            : [];
        $default_map   = Settings::get_default_email_attachments();
        $document_types = DocumentTypes::all();

        // Merge current with defaults so new doc types/emails appear automatically.
        foreach ( $default_map as $email_id => $doc_defaults ) {
            if ( ! isset( $current_map[ $email_id ] ) || ! is_array( $current_map[ $email_id ] ) ) {
                $current_map[ $email_id ] = $doc_defaults;
            } else {
                $current_map[ $email_id ] = wp_parse_args( $current_map[ $email_id ], $doc_defaults );
            }
        }

        $email_labels = EmailModule::get_supported_email_labels();
        ?>
        <table class="widefat striped kitgenix-pdf-email-attachments-table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Email', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
                <?php foreach ( $document_types as $doc_type ) : ?>
                    <th class="kitgenix-align-center">
                        <?php echo esc_html( DocumentTypes::get_label( $doc_type ) ); ?>
                    </th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $default_map as $email_id => $doc_defaults ) : ?>
                <tr>
                    <td>
                        <?php echo esc_html( $email_labels[ $email_id ] ?? $email_id ); ?>
                    </td>
                    <?php foreach ( $document_types as $doc_type ) : ?>
                        <?php
                        $checked = ! empty( $current_map[ $email_id ][ $doc_type ] );
                        ?>
                        <td class="kitgenix-align-center">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[email_attachments][<?php echo esc_attr( $email_id ); ?>][<?php echo esc_attr( $doc_type ); ?>]"
                                   value="1"
                                   <?php checked( $checked ); ?>
                            />
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php esc_html_e( 'Tick which documents should be attached to each email. You can still override this with filters if needed.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
        </p>
        <?php
    }

    /**
     * @return array{order_id:int,email_id:string,recipient_email:string}
     */
    protected function get_email_test_tools_state(): array {
        $email_labels     = EmailModule::get_supported_email_labels();
        $default_email_id = array_key_first( $email_labels );
        $recipient_email  = $this->get_default_test_email_recipient();
        $order_id         = 0;
        $email_id         = is_string( $default_email_id ) ? $default_email_id : 'customer_processing_order';

        /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state for preview UI. */
        if ( isset( $_GET['email_preview_order_id'] ) ) {
            /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state for preview UI. */
            $order_id = absint( wp_unslash( $_GET['email_preview_order_id'] ) );
        }

        /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state for preview UI. */
        if ( isset( $_GET['email_preview_email_id'] ) ) {
            /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state for preview UI. */
            $requested_email_id = sanitize_key( wp_unslash( $_GET['email_preview_email_id'] ) );
            if ( isset( $email_labels[ $requested_email_id ] ) ) {
                $email_id = $requested_email_id;
            }
        }

        /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state for preview UI. */
        if ( isset( $_GET['email_test_recipient'] ) ) {
            /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state for preview UI. */
            $requested_recipient = sanitize_email( wp_unslash( $_GET['email_test_recipient'] ) );
            if ( '' !== $requested_recipient ) {
                $recipient_email = $requested_recipient;
            }
        }

        return [
            'order_id'        => $order_id,
            'email_id'        => $email_id,
            'recipient_email' => $recipient_email,
        ];
    }

    protected function get_default_test_email_recipient(): string {
        $current_user_email = '';
        if ( function_exists( 'wp_get_current_user' ) ) {
            $current_user = wp_get_current_user();
            if ( $current_user instanceof \WP_User ) {
                $current_user_email = (string) $current_user->user_email;
            }
        }

        if ( '' !== $current_user_email && is_email( $current_user_email ) ) {
            return $current_user_email;
        }

        return sanitize_email( (string) get_option( 'admin_email', '' ) );
    }

    protected function get_document_preview_action( string $document_type ): string {
        return in_array( $document_type, DocumentTypes::all(), true )
            ? DocumentTypes::get_admin_stream_action( $document_type )
            : '';
    }

    protected function get_document_preview_url( int $order_id, string $document_type ): string {
        $action = $this->get_document_preview_action( $document_type );
        if ( '' === $action ) {
            return '';
        }

        return add_query_arg(
            [
                'action'   => $action,
                'order_id' => $order_id,
                'nonce'    => wp_create_nonce( 'kitgenix_admin_pdf' ),
            ],
            admin_url( 'admin-post.php' )
        );
    }

    protected function render_email_test_tools(): void {
        if ( ! $this->email_module instanceof EmailModule ) {
            return;
        }

        $state        = $this->get_email_test_tools_state();
        $email_labels = EmailModule::get_supported_email_labels();
        $preview      = null;
        $preview_error = '';

        if ( $state['order_id'] > 0 ) {
            $order = wc_get_order( $state['order_id'] );

            if ( ! $order instanceof \WC_Order ) {
                $preview_error = __( 'The selected WooCommerce order could not be found.', 'kitgenix-pdf-invoicing-for-woocommerce' );
            } else {
                $preview = $this->email_module->get_email_preview_data( $state['email_id'], $order );
            }
        }
        ?>
        <div class="kitgenix-pdf-invoicing-for-woocommerce-section-card" style="margin-top:24px;">
            <strong><?php esc_html_e( 'Email Preview & Test Send', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></strong>
            <p class="description">
                <?php esc_html_e( 'Preview the PDF documents currently mapped to a WooCommerce email for a specific order, then send those same attachments to a safe inbox before rolling the workflow out live.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
            </p>

            <form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get">
                <input type="hidden" name="page" value="kitgenix-pdf-invoicing-settings" />
                <input type="hidden" name="tab" value="emails" />

                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="kitgenix-email-preview-order-id"><?php esc_html_e( 'Order ID', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   min="1"
                                   step="1"
                                   id="kitgenix-email-preview-order-id"
                                   name="email_preview_order_id"
                                   value="<?php echo esc_attr( (string) $state['order_id'] ); ?>"
                                   class="small-text" />
                            <p class="description"><?php esc_html_e( 'Enter the WooCommerce order you want to inspect.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="kitgenix-email-preview-email-id"><?php esc_html_e( 'WooCommerce email', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></label>
                        </th>
                        <td>
                            <select id="kitgenix-email-preview-email-id" name="email_preview_email_id">
                                <?php foreach ( $email_labels as $email_id => $label ) : ?>
                                    <option value="<?php echo esc_attr( $email_id ); ?>" <?php selected( $state['email_id'], $email_id ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="kitgenix-email-preview-recipient"><?php esc_html_e( 'Test recipient', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   id="kitgenix-email-preview-recipient"
                                   name="email_test_recipient"
                                   value="<?php echo esc_attr( $state['recipient_email'] ); ?>"
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e( 'This address is only used by the test-send action below.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Preview email attachments', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'secondary', 'preview_email_attachments', false ); ?>
            </form>

            <p class="description">
                <?php esc_html_e( 'Preview links open the live admin PDF stream and may generate or archive the selected document if it has not already been issued for that order.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>
            </p>

            <?php if ( '' !== $preview_error ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( $preview_error ); ?></p></div>
            <?php endif; ?>

            <?php if ( is_array( $preview ) ) : ?>
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: WooCommerce order number, 2: email workflow label. */
                            __( 'Previewing order #%1$s against %2$s.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                            $state['order_id'],
                            (string) $preview['email_label']
                        )
                    );
                    ?>
                </p>

                <?php if ( empty( $preview['documents'] ) ) : ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e( 'No PDF documents are currently mapped to that WooCommerce email workflow. Update the attachment matrix above and save changes first.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="widefat striped kitgenix-pdf-email-attachments-table">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Document', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
                            <th><?php esc_html_e( 'Preview', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $preview['documents'] as $document ) : ?>
                            <?php $preview_url = ! empty( $document['eligible'] ) ? $this->get_document_preview_url( $state['order_id'], (string) $document['type'] ) : ''; ?>
                            <tr>
                                <td><?php echo esc_html( (string) $document['label'] ); ?></td>
                                <td>
                                    <?php
                                    echo esc_html(
                                        ! empty( $document['eligible'] )
                                            ? __( 'Ready to preview and attach.', 'kitgenix-pdf-invoicing-for-woocommerce' )
                                            : ( (string) $document['reason'] ?: __( 'Unavailable for this order.', 'kitgenix-pdf-invoicing-for-woocommerce' ) )
                                    );
                                    ?>
                                </td>
                                <td>
                                    <?php if ( '' !== $preview_url ) : ?>
                                        <a class="button button-secondary" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open preview', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                                    <?php else : ?>
                                        <span class="description"><?php esc_html_e( 'No preview available', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ( ! empty( $preview['eligible_documents'] ) ) : ?>
                        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-top:20px;">
                            <input type="hidden" name="action" value="<?php echo esc_attr( EmailModule::TEST_EMAIL_ACTION ); ?>" />
                            <input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $state['order_id'] ); ?>" />
                            <input type="hidden" name="email_id" value="<?php echo esc_attr( $state['email_id'] ); ?>" />
                            <?php wp_nonce_field( 'kitgenix_pdf_test_email_send' ); ?>

                            <table class="form-table" role="presentation">
                                <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="kitgenix-email-test-recipient-send"><?php esc_html_e( 'Send test email to', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="email"
                                               id="kitgenix-email-test-recipient-send"
                                               name="recipient_email"
                                               value="<?php echo esc_attr( $state['recipient_email'] ); ?>"
                                               class="regular-text"
                                               required />
                                        <p class="description"><?php esc_html_e( 'The test email sends the configured PDF attachments only, so you can validate the document set without triggering the live WooCommerce email template.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                                    </td>
                                </tr>
                                </tbody>
                            </table>

                            <?php submit_button( __( 'Send test email with PDFs', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'primary', 'send_test_email', false ); ?>
                        </form>
                    <?php else : ?>
                        <div class="notice notice-warning inline">
                            <p><?php esc_html_e( 'No mapped PDF documents are currently eligible for this order, so the test-send action is unavailable.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_settings_page(): void {
        $ver = defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_VERSION' ) ? (string) KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_VERSION : '1.1.2';
        $social_base = ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL' ) ? (string) KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL : plugin_dir_url( __FILE__ ) ) . 'assets/images/social-media/';

        $default_tab = 'settings';
        /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- non-actionable UI helper */
        if ( isset( $_GET['tab'] ) ) {
            /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- non-actionable UI helper */
            $maybe_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
            if ( in_array( $maybe_tab, [ 'settings', 'branding', 'emails', 'support', 'log' ], true ) ) {
                $default_tab = $maybe_tab;
            }
        }

        $base_tab_url = admin_url( 'admin.php?page=kitgenix-pdf-invoicing-settings' );
        ?>
        <div class="wrap kitgenix-admin-app kitgenix-pdf-invoicing-for-woocommerce-pdf-settings" data-kitgenix-tabs data-kitgenix-default-tab="<?php echo esc_attr( $default_tab ); ?>">

            <div class="kitgenix-pdf-invoicing-for-woocommerce-settings-intro kitgenix-settings-header">
                <div class="kitgenix-settings-header-row">
                    <div class="kitgenix-settings-header-main">
                        <div class="kitgenix-settings-brand">
                            <img class="kitgenix-settings-logo" src="<?php echo esc_url( ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL' ) ? KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL : plugin_dir_url( __FILE__ ) ) . 'assets/images/logos/kitgenix-favicon-purple.svg' ); ?>" alt="<?php echo esc_attr__( 'Kitgenix', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>" />
                            <h1 class="kitgenix-pdf-invoicing-for-woocommerce-admin-title"><?php echo esc_html__( 'Kitgenix PDF Invoicing for WooCommerce', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h1>
                        </div>
                        <p><?php echo esc_html__( 'Generate clean, branded PDF documents for your WooCommerce orders and control which documents are attached to emails.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        <div class="kitgenix-settings-meta">
                            <span class="kitgenix-settings-version" aria-label="Plugin version">v<?php echo esc_html( $ver ); ?></span>
                        </div>
                    </div>

                    <div class="kitgenix-settings-header-actions">
                        <div class="kitgenix-intro-links kitgenix-pdf-invoicing-for-woocommerce-intro-links">
                            <a href="<?php echo esc_url( 'https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/documentation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Documentation', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                            <a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/kitgenix-pdf-invoicing-for-woocommerce/reviews/#new-post' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Review Plugin', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                            <a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/kitgenix-pdf-invoicing-for-woocommerce/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Support Request', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                            <a href="<?php echo esc_url( 'https://buymeacoffee.com/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Support Kitgenix', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                        </div>

                        <?php if ( ! empty( $social_base ) ) : ?>
                            <div class="kitgenix-social-links kitgenix-social-links--icons">
                                <a href="https://kitgenix.com" target="_blank" rel="noopener noreferrer" aria-label="Website" title="Website"><img src="<?php echo esc_url( $social_base . 'globe-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Website</span></a>
                                <a href="https://www.facebook.com/groups/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook Community" title="Facebook Community"><img src="<?php echo esc_url( $social_base . 'facebook-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Facebook Community</span></a>
                                <a href="https://www.facebook.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><img src="<?php echo esc_url( $social_base . 'facebook-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Facebook</span></a>
                                <a href="https://www.instagram.com/kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><img src="<?php echo esc_url( $social_base . 'instagram-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Instagram</span></a>
                                <a href="https://www.youtube.com/@Kitgenix" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><img src="<?php echo esc_url( $social_base . 'youtube-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">YouTube</span></a>
                                <a href="https://www.reddit.com/r/Kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Reddit" title="Reddit"><img src="<?php echo esc_url( $social_base . 'reddit-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">Reddit</span></a>
                                <a href="https://www.linkedin.com/company/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><img src="<?php echo esc_url( $social_base . 'linkedin-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">LinkedIn</span></a>
                                <a href="https://x.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="X" title="X"><img src="<?php echo esc_url( $social_base . 'x-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">X</span></a>
                                <a href="https://www.tiktok.com/@kitgenix" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok"><img src="<?php echo esc_url( $social_base . 'tiktok-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">TikTok</span></a>
                                <a href="https://github.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub"><img src="<?php echo esc_url( $social_base . 'github-solid.svg' ); ?>" alt="" width="13" height="13" aria-hidden="true" /><span class="screen-reader-text">GitHub</span></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <h2 class="nav-tab-wrapper kitgenix-nav-tabs" aria-label="Settings navigation">
                <a class="nav-tab <?php echo ( 'settings' === $default_tab ) ? 'nav-tab-active' : ''; ?> kitgenix-tab-trigger" href="<?php echo esc_url( $base_tab_url . '&tab=settings#kitgenix-tab-settings' ); ?>" data-kitgenix-tab="settings"><?php echo esc_html__( 'Settings', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                <a class="nav-tab <?php echo ( 'branding' === $default_tab ) ? 'nav-tab-active' : ''; ?> kitgenix-tab-trigger" href="<?php echo esc_url( $base_tab_url . '&tab=branding#kitgenix-tab-branding' ); ?>" data-kitgenix-tab="branding"><?php echo esc_html__( 'Brand & Styling', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                <a class="nav-tab <?php echo ( 'emails' === $default_tab ) ? 'nav-tab-active' : ''; ?> kitgenix-tab-trigger" href="<?php echo esc_url( $base_tab_url . '&tab=emails#kitgenix-tab-emails' ); ?>" data-kitgenix-tab="emails"><?php echo esc_html__( 'Email Attachments', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                <a class="nav-tab <?php echo ( 'support' === $default_tab ) ? 'nav-tab-active' : ''; ?> kitgenix-tab-trigger" href="<?php echo esc_url( $base_tab_url . '&tab=support#kitgenix-tab-support' ); ?>" data-kitgenix-tab="support"><?php echo esc_html__( 'Support', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                <a class="nav-tab <?php echo ( 'log' === $default_tab ) ? 'nav-tab-active' : ''; ?> kitgenix-tab-trigger" href="<?php echo esc_url( $base_tab_url . '&tab=log#kitgenix-tab-log' ); ?>" data-kitgenix-tab="log"><?php echo esc_html__( 'Log', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
            </h2>

            <div class="kitgenix-settings-layout">
                <div class="kitgenix-settings-content" id="kitgenix-settings-content" tabindex="-1">

            <?php
            $page = 'kitgenix-pdf-invoicing-settings';
            $render_section = static function ( string $page_slug, string $section_id ): void {
                global $wp_settings_sections;
                if ( empty( $wp_settings_sections[ $page_slug ] ) || empty( $wp_settings_sections[ $page_slug ][ $section_id ] ) ) {
                    return;
                }

                $section = $wp_settings_sections[ $page_slug ][ $section_id ];
                echo '<strong>' . esc_html( (string) ( $section['title'] ?? '' ) ) . '</strong>';
                if ( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
                    call_user_func( $section['callback'], $section );
                }
                echo '<table class="form-table" role="presentation">';
                do_settings_fields( $page_slug, $section_id );
                echo '</table>';
            };
            ?>

            <div id="kitgenix-tab-settings" class="kitgenix-pdf-invoicing-for-woocommerce-section-card" data-section data-kitgenix-tab-panel="settings" <?php echo ( 'settings' !== $default_tab ) ? 'hidden="hidden" style="display:none" aria-hidden="true"' : 'aria-hidden="false"'; ?>>
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'kitgenix_pdf_invoicing_for_woocommerce_' );
                    $render_section( $page, 'kitgenix_pdf_invoicing_main' );
                    $render_section( $page, 'kitgenix_pdf_invoicing_numbering' );
                    $render_section( $page, 'kitgenix_pdf_invoicing_localization' );
                    $render_section( $page, 'kitgenix_pdf_invoicing_fields' );
                    submit_button();
                    ?>
                </form>
            </div>

            <div id="kitgenix-tab-branding" class="kitgenix-pdf-invoicing-for-woocommerce-section-card" data-section data-kitgenix-tab-panel="branding" <?php echo ( 'branding' !== $default_tab ) ? 'hidden="hidden" style="display:none" aria-hidden="true"' : 'aria-hidden="false"'; ?>>
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'kitgenix_pdf_invoicing_for_woocommerce_' );
                    $render_section( $page, 'kitgenix_pdf_invoicing_brand' );
                    $render_section( $page, 'kitgenix_pdf_invoicing_designer' );
                    submit_button();
                    ?>
                </form>
            </div>

            <div id="kitgenix-tab-emails" class="kitgenix-pdf-invoicing-for-woocommerce-section-card" data-section data-kitgenix-tab-panel="emails" <?php echo ( 'emails' !== $default_tab ) ? 'hidden="hidden" style="display:none" aria-hidden="true"' : 'aria-hidden="false"'; ?>>
                <form action="options.php" method="post">
                    <?php
                    settings_fields( 'kitgenix_pdf_invoicing_for_woocommerce_' );
                    $render_section( $page, 'kitgenix_pdf_invoicing_email' );
                    submit_button();
                    ?>
                </form>

                <?php $this->render_email_test_tools(); ?>
            </div>

            <div id="kitgenix-tab-support" class="kitgenix-pdf-invoicing-for-woocommerce-section-card kitgenix-pdf-invoicing-for-woocommerce-support-page kitgenix-support-page" data-section data-kitgenix-tab-panel="support" <?php echo ( 'support' !== $default_tab ) ? 'hidden="hidden" style="display:none" aria-hidden="true"' : 'aria-hidden="false"'; ?>>
                <?php
                $kitgenix_pdf_invoicing_for_woocommerce_donate_once_url     = 'https://buymeacoffee.com/kitgenix';
                $kitgenix_pdf_invoicing_for_woocommerce_monthly_support_url = 'https://buymeacoffee.com/kitgenix/membership';
                $kitgenix_pdf_invoicing_for_woocommerce_plugin_page_url     = 'https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/';
                $kitgenix_pdf_invoicing_for_woocommerce_review_url          = 'https://wordpress.org/support/plugin/kitgenix-pdf-invoicing-for-woocommerce/reviews/#new-post';
                $kitgenix_pdf_invoicing_for_woocommerce_support_url         = 'https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/support';
                $kitgenix_pdf_invoicing_for_woocommerce_copy_onclick        = "if(window.navigator&&navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(" . wp_json_encode( $kitgenix_pdf_invoicing_for_woocommerce_plugin_page_url ) . ");}else{window.prompt(" . wp_json_encode( __( 'Copy plugin link:', 'kitgenix-pdf-invoicing-for-woocommerce' ) ) . ", " . wp_json_encode( $kitgenix_pdf_invoicing_for_woocommerce_plugin_page_url ) . ");}return false;";
                $kitgenix_pdf_invoicing_for_woocommerce_monthly_options     = [
                    [ 'label' => __( 'Kitgenix Supporter (£4/month)', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                    [ 'label' => __( 'Kitgenix Plus (£8/month)', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                    [ 'label' => __( 'Kitgenix Pro Supporter (£19/month)', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                    [ 'label' => __( 'Kitgenix Agency (£37/month)', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                    [ 'label' => __( 'Kitgenix Partner (£75/month)', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                    [ 'label' => __( 'Kitgenix YouTube Sponsor (£730/month)', 'kitgenix-pdf-invoicing-for-woocommerce' ), 'url' => 'https://buymeacoffee.com/kitgenix/membership' ],
                ];
                $kitgenix_pdf_invoicing_for_woocommerce_metrics = (array) get_option( 'kitgenix_pdf_invoicing_for_woocommerce_metrics', [] );
                $kitgenix_pdf_invoicing_for_woocommerce_total   = isset( $kitgenix_pdf_invoicing_for_woocommerce_metrics['documents_total'] ) ? (int) $kitgenix_pdf_invoicing_for_woocommerce_metrics['documents_total'] : 0;
                $kitgenix_pdf_invoicing_for_woocommerce_by_type = ( isset( $kitgenix_pdf_invoicing_for_woocommerce_metrics['documents_by_type'] ) && is_array( $kitgenix_pdf_invoicing_for_woocommerce_metrics['documents_by_type'] ) ) ? (array) $kitgenix_pdf_invoicing_for_woocommerce_metrics['documents_by_type'] : [];
                $kitgenix_pdf_invoicing_for_woocommerce_invoices = isset( $kitgenix_pdf_invoicing_for_woocommerce_by_type['invoice'] ) ? (int) $kitgenix_pdf_invoicing_for_woocommerce_by_type['invoice'] : 0;
                $kitgenix_pdf_invoicing_for_woocommerce_impact_cards = [
                    [
                        'label' => __( 'Invoices generated', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                        'value' => number_format_i18n( $kitgenix_pdf_invoicing_for_woocommerce_invoices ),
                        'meta'  => __( 'Automated WooCommerce invoices already created by the plugin.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    ],
                    [
                        'label' => __( 'Total PDF documents', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                        'value' => number_format_i18n( $kitgenix_pdf_invoicing_for_woocommerce_total ),
                        'meta'  => __( 'All generated order documents currently tracked in metrics.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    ],
                ];
                $kitgenix_pdf_invoicing_for_woocommerce_meaning_points = [
                    __( 'Your store is already using automated invoice generation instead of manual document handling.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    __( 'The invoice count shows how often the plugin is supporting your order workflow directly.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    __( 'Total PDF documents reflect the wider document workload being handled behind the scenes.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                ];
                $kitgenix_pdf_invoicing_for_woocommerce_support_points = [
                    __( 'Compatibility updates for new WordPress / WooCommerce releases', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    __( 'Bug fixes, edge-case testing, and better document-generation coverage', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    __( 'Security hardening and ongoing performance improvements', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    __( 'Documentation upgrades and faster, clearer support responses', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                ];
                $kitgenix_pdf_invoicing_for_woocommerce_trust_points = [
                    __( 'No paid features locked behind donations', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    __( 'No tracking or invasive upsells', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                    __( 'Support is always optional, and genuinely appreciated.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                ];
                ?>
                <div class="kitgenix-support-shell">
                    <section class="kitgenix-support-hero">
                        <div class="kitgenix-support-hero__copy">
                            <span class="kitgenix-support-eyebrow"><?php esc_html_e( 'Help keep Kitgenix independent', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></span>
                            <h2 class="kitgenix-support-heading"><?php esc_html_e( 'Support Kitgenix', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h2>
                            <p class="description kitgenix-support-intro"><?php esc_html_e( 'We try to keep Kitgenix plugins lightweight, privacy-friendly, and free for everyone. If PDF Invoicing for WooCommerce saves you admin time or helps prevent manual invoicing busywork, please consider supporting Kitgenix. Your support directly funds ongoing development, testing, and maintenance so we can keep features open and updates frequent.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        </div>
                        <div class="kitgenix-support-hero__aside">
                            <p class="kitgenix-support-kicker"><?php esc_html_e( 'Support this plugin', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                            <div class="kitgenix-support-actions">
                                <a class="button button-primary" href="<?php echo esc_url( $kitgenix_pdf_invoicing_for_woocommerce_donate_once_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy Me a Coffee', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                                <a class="button button-secondary" href="<?php echo esc_url( $kitgenix_pdf_invoicing_for_woocommerce_monthly_support_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Become a member', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                            </div>
                            <p class="kitgenix-support-note"><?php esc_html_e( 'Via Buy Me a Coffee. Cancel anytime.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        </div>
                    </section>

                    <section class="kitgenix-support-section kitgenix-support-section--feature">
                        <div class="kitgenix-support-section__header">
                            <h3 class="kitgenix-support-subheading"><?php esc_html_e( 'Your site impact', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'These stats show how PDF Invoicing for WooCommerce is currently working on your site:', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        </div>
                        <div class="kitgenix-support-metric-grid">
                            <?php foreach ( $kitgenix_pdf_invoicing_for_woocommerce_impact_cards as $kitgenix_pdf_invoicing_for_woocommerce_impact_card ) : ?>
                                <div class="kitgenix-support-stat">
                                    <span class="kitgenix-support-stat__label"><?php echo esc_html( $kitgenix_pdf_invoicing_for_woocommerce_impact_card['label'] ); ?></span>
                                    <strong class="kitgenix-support-stat__value"><?php echo esc_html( $kitgenix_pdf_invoicing_for_woocommerce_impact_card['value'] ); ?></strong>
                                    <span class="kitgenix-support-stat__meta"><?php echo esc_html( $kitgenix_pdf_invoicing_for_woocommerce_impact_card['meta'] ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="kitgenix-support-grid">
                        <section class="kitgenix-support-section">
                            <h3 class="kitgenix-support-subheading"><?php esc_html_e( 'Support options', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'Buy Me a Coffee: A quick way to say thanks and help fund the next round of improvements.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                            <p class="description"><?php esc_html_e( 'A membership helps keep development consistent if PDF Invoicing for WooCommerce is part of your day-to-day order admin.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                            <div class="kitgenix-support-chip-list">
                                <?php foreach ( $kitgenix_pdf_invoicing_for_woocommerce_monthly_options as $kitgenix_pdf_invoicing_for_woocommerce_monthly_option ) : ?>
                                    <?php
                                    $monthly_label = (string) $kitgenix_pdf_invoicing_for_woocommerce_monthly_option['label'];
                                    $monthly_name  = $monthly_label;
                                    $monthly_price = '';
                                    if ( preg_match( '/^(.*)\(([^)]+)\)$/u', $monthly_label, $monthly_parts ) ) {
                                        $monthly_name  = trim( $monthly_parts[1] );
                                        $monthly_price = trim( $monthly_parts[2] );
                                    }
                                    ?>
                                    <a class="kitgenix-support-chip" href="<?php echo esc_url( $kitgenix_pdf_invoicing_for_woocommerce_monthly_option['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                        <span class="kitgenix-support-chip__name"><?php echo esc_html( $monthly_name ); ?></span>
                                        <?php if ( '' !== $monthly_price ) : ?>
                                            <span class="kitgenix-support-chip__price"><?php echo esc_html( $monthly_price ); ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="kitgenix-support-section">
                            <h3 class="kitgenix-support-subheading"><?php esc_html_e( 'What this means', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
                            <ul class="kitgenix-support-list">
                                <?php foreach ( $kitgenix_pdf_invoicing_for_woocommerce_meaning_points as $kitgenix_pdf_invoicing_for_woocommerce_meaning_point ) : ?>
                                    <li><?php echo esc_html( $kitgenix_pdf_invoicing_for_woocommerce_meaning_point ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>

                        <section class="kitgenix-support-section kitgenix-support-section--soft">
                            <h3 class="kitgenix-support-subheading"><?php esc_html_e( 'What your support helps with', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
                            <ul class="kitgenix-support-list">
                                <?php foreach ( $kitgenix_pdf_invoicing_for_woocommerce_support_points as $kitgenix_pdf_invoicing_for_woocommerce_support_point ) : ?>
                                    <li><?php echo esc_html( $kitgenix_pdf_invoicing_for_woocommerce_support_point ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>

                        <section class="kitgenix-support-section">
                            <h3 class="kitgenix-support-subheading"><?php esc_html_e( 'Not in a position to donate?', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'No worries - you can still massively help:', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                            <p class="description"><?php esc_html_e( 'Reviews help others discover the plugin and keep the project sustainable. Sharing the plugin with stores that want cleaner WooCommerce invoices and sending clear issue reports both help move the product forward.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                            <div class="kitgenix-support-actions">
                                <a class="button button-secondary" href="<?php echo esc_url( $kitgenix_pdf_invoicing_for_woocommerce_review_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a WordPress.org review', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                                <button type="button" class="button button-secondary" onclick="<?php echo esc_attr( $kitgenix_pdf_invoicing_for_woocommerce_copy_onclick ); ?>"><?php esc_html_e( 'Copy plugin link', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></button>
                                <a class="button button-secondary" href="<?php echo esc_url( $kitgenix_pdf_invoicing_for_woocommerce_support_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open support / feature request', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                            </div>
                        </section>

                        <section class="kitgenix-support-section kitgenix-support-section--full">
                            <h3 class="kitgenix-support-subheading"><?php esc_html_e( 'A small note on trust & privacy', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h3>
                            <ul class="kitgenix-support-list">
                                <?php foreach ( $kitgenix_pdf_invoicing_for_woocommerce_trust_points as $kitgenix_pdf_invoicing_for_woocommerce_trust_point ) : ?>
                                    <li><?php echo esc_html( $kitgenix_pdf_invoicing_for_woocommerce_trust_point ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="kitgenix-support-footer-note"><?php esc_html_e( 'Thank you for supporting Kitgenix.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                        </section>
                    </div>
                </div>
            </div>

            <div id="kitgenix-tab-log" class="kitgenix-pdf-invoicing-for-woocommerce-section-card" data-section data-kitgenix-tab-panel="log" <?php echo ( 'log' !== $default_tab ) ? 'hidden="hidden" style="display:none" aria-hidden="true"' : 'aria-hidden="false"'; ?>>
                <?php $this->render_log_tab(); ?>
            </div>

                </div>
                <?php $this->render_sidebar(); ?>
            </div>
        </div>
        <?php
    }

    private function render_sidebar(): void {
        $social_base = ( defined( 'KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL' ) ? (string) KITGENIX_PDF_INVOICING_FOR_WOOCOMMERCE_URL : plugin_dir_url( __FILE__ ) ) . 'assets/images/social-media/';
        ?>
        <aside class="kitgenix-settings-sidebar" aria-label="<?php echo esc_attr__( 'Help and links', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?>">
            <div class="kitgenix-sidebar-card">
                <h2><?php echo esc_html__( 'Need Help?', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h2>
                <p><?php echo esc_html__( 'Open the documentation for setup guidance or send us a support request if you need help configuring the plugin.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                <div class="kitgenix-sidebar-actions">
                    <a class="button button-secondary" href="<?php echo esc_url( 'https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/documentation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Documentation', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                    <a class="button button-primary" href="<?php echo esc_url( 'https://kitgenix.com/plugins/kitgenix-pdf-invoicing-for-woocommerce/support' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Request Support', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                </div>
            </div>

            <div class="kitgenix-sidebar-card">
                <h2><?php echo esc_html__( 'Visit Our Official Facebook Group', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h2>
                <p><?php echo esc_html__( 'Join the Kitgenix community to ask questions, share feedback, and keep up with product updates.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                <div class="kitgenix-sidebar-actions">
                    <a class="button button-secondary" href="<?php echo esc_url( 'https://www.facebook.com/groups/kitgenix' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Join Group', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></a>
                </div>
            </div>

            <div class="kitgenix-sidebar-card">
                <h2><?php echo esc_html__( 'Follow Us', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></h2>
                <p><?php echo esc_html__( 'Keep up with new releases, tutorials, and product news across our channels.', 'kitgenix-pdf-invoicing-for-woocommerce' ); ?></p>
                <div class="kitgenix-sidebar-social-grid">
                    <a class="kitgenix-sidebar-social-link" href="https://kitgenix.com" target="_blank" rel="noopener noreferrer" aria-label="Website" title="Website"><img src="<?php echo esc_url( $social_base . 'globe-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.facebook.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook"><img src="<?php echo esc_url( $social_base . 'facebook-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.instagram.com/kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram"><img src="<?php echo esc_url( $social_base . 'instagram-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.youtube.com/@Kitgenix" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube"><img src="<?php echo esc_url( $social_base . 'youtube-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.reddit.com/r/Kitgenix/" target="_blank" rel="noopener noreferrer" aria-label="Reddit" title="Reddit"><img src="<?php echo esc_url( $social_base . 'reddit-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.linkedin.com/company/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" title="LinkedIn"><img src="<?php echo esc_url( $social_base . 'linkedin-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://x.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="X" title="X"><img src="<?php echo esc_url( $social_base . 'x-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://www.tiktok.com/@kitgenix" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok"><img src="<?php echo esc_url( $social_base . 'tiktok-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                    <a class="kitgenix-sidebar-social-link" href="https://github.com/kitgenix" target="_blank" rel="noopener noreferrer" aria-label="GitHub" title="GitHub"><img src="<?php echo esc_url( $social_base . 'github-solid.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true" /></a>
                </div>
            </div>
        </aside>
        <?php
    }

    private function render_log_tab(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['kitgenix_log_cleared'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Event log cleared.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p></div>';
        }

        $clear_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=kitgenix_pdf_invoicing_clear_event_log' ),
            'kitgenix_pdf_invoicing_clear_event_log'
        );

        echo '<div class="kitgenix-settings-section">';
        echo '<h2>' . esc_html__( 'Activity Log', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'A record of recent plugin events. Entries show the timestamp, context, outcome, and a plain-English note. IP addresses and sensitive data are never stored here.', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</p>';
        echo '<textarea class="large-text code" rows="20" readonly>' . esc_textarea( Event_Log::get_log_text() ) . '</textarea>';
        echo '<p>';
        echo '<a href="' . esc_url( $clear_url ) . '" class="button button-secondary" onclick="return confirm(\'' . esc_js( __( 'Clear all log entries?', 'kitgenix-pdf-invoicing-for-woocommerce' ) ) . '\')">' . esc_html__( 'Clear log', 'kitgenix-pdf-invoicing-for-woocommerce' ) . '</a>';
        echo '</p>';
        echo '</div>';
    }

    public function handle_clear_event_log(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'kitgenix-pdf-invoicing-for-woocommerce' ), 403 );
        }
        check_admin_referer( 'kitgenix_pdf_invoicing_clear_event_log' );
        Event_Log::clear();
        wp_safe_redirect( admin_url( 'admin.php?page=kitgenix-pdf-invoicing-settings&tab=log&kitgenix_log_cleared=1' ) );
        exit;
    }

    public function on_settings_saved( mixed $old_value, mixed $new_value, string $option ): void {
        Event_Log::record( 'settings-saved', 'success', __( 'Plugin settings were saved via the admin settings page.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
    }
}
