<?php

namespace Kitgenix\PDF_Invoicing\Modules\Email;

use Kitgenix\PDF_Invoicing\Core\ModuleInterface;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\DocumentTypes;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\PdfGenerator;
use Kitgenix\PDF_Invoicing\Modules\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class EmailModule implements ModuleInterface {

    public const TEST_EMAIL_ACTION = 'kitgenix_pdf_send_test_email';

    protected PdfGenerator $pdf;

    /**
     * @var string[]
     */
    protected array $temp_files = [];

    public function __construct( PdfGenerator $pdf ) {
        $this->pdf = $pdf;
    }

    public function get_id(): string {
        return 'email';
    }

    public function register(): void {
        add_filter(
            'woocommerce_email_attachments',
            [ $this, 'add_email_attachments' ],
            10,
            4
        );

        add_action( 'shutdown', [ $this, 'cleanup_temp_files' ] );

        if ( is_admin() ) {
            add_action( 'admin_post_' . self::TEST_EMAIL_ACTION, [ $this, 'handle_test_email_send' ] );
        }
    }

    /**
     * Human labels for supported WooCommerce email workflows.
     *
     * @return array<string,string>
     */
    public static function get_supported_email_labels(): array {
        return [
            'customer_processing_order' => __( 'Customer processing order (customer)', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'customer_completed_order'  => __( 'Customer completed order (customer)', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'customer_refunded_order'   => __( 'Customer refunded order (customer)', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            'new_order'                 => __( 'New order (admin)', 'kitgenix-pdf-invoicing-for-woocommerce' ),
        ];
    }

    /**
     * Build a preview payload for one WooCommerce email workflow and order.
     *
    * @param \WC_Email|mixed|null $email
     * @return array<string,mixed>
     */
    public function get_email_preview_data( string $email_id, \WC_Order $order, $email = null ): array {
        $email_context = $this->resolve_email_context( $email_id, $email );
        $documents     = [];

        foreach ( $this->get_email_document_types( $email_id, $order, $email_context ) as $doc_type ) {
            $documents[] = $this->get_document_preview_state( $email_id, $doc_type, $order, $email_context );
        }

        $eligible_documents = array_values(
            array_filter(
                $documents,
                static function ( array $document ): bool {
                    return ! empty( $document['eligible'] );
                }
            )
        );

        return [
            'email_id'            => $email_id,
            'email_label'         => $this->get_email_label( $email_id, $email_context ),
            'documents'           => $documents,
            'eligible_documents'  => $eligible_documents,
        ];
    }

    /**
     * Attach PDFs to WooCommerce emails based on settings.
     *
     * @param string[]         $attachments
     * @param string           $email_id
     * @param \WC_Order|mixed  $order
     * @param \WC_Email|mixed  $email
     *
     * @return string[]
     */
    public function add_email_attachments( array $attachments, string $email_id, $order, $email ): array {
        if ( ! $order instanceof \WC_Order ) {
            return $attachments;
        }

        $preview = $this->get_email_preview_data( $email_id, $order, $email );

        foreach ( $preview['eligible_documents'] as $document ) {
            $path = $this->pdf->generate_document_to_file( $order->get_id(), (string) $document['type'] );

            if ( $path && file_exists( $path ) ) {
                $attachments[]      = $path;
                $this->temp_files[] = $path;
            }
        }

        return $attachments;
    }

    /**
     * Send a plain test email with the configured PDF attachments.
     *
    * @return array<string,mixed>|\WP_Error
     */
    public function send_test_email( string $recipient_email, string $email_id, \WC_Order $order ) {
        $recipient_email = sanitize_email( $recipient_email );
        if ( '' === $recipient_email || ! is_email( $recipient_email ) ) {
            return new \WP_Error(
                'invalid_email',
                __( 'Enter a valid recipient email address for the test message.', 'kitgenix-pdf-invoicing-for-woocommerce' )
            );
        }

        $preview = $this->get_email_preview_data( $email_id, $order );

        if ( empty( $preview['documents'] ) ) {
            return new \WP_Error(
                'no_documents',
                __( 'No PDF documents are currently mapped to that WooCommerce email workflow.', 'kitgenix-pdf-invoicing-for-woocommerce' )
            );
        }

        $attachments      = [];
        $sent_documents   = [];
        $failed_documents = [];
        $skipped_documents = [];

        foreach ( $preview['documents'] as $document ) {
            if ( empty( $document['eligible'] ) ) {
                $skipped_documents[] = $document;
                continue;
            }

            $path = $this->pdf->generate_document_to_file( $order->get_id(), (string) $document['type'] );
            if ( $path && file_exists( $path ) ) {
                $attachments[]      = $path;
                $this->temp_files[] = $path;
                $sent_documents[]   = $document;
            } else {
                $failed_documents[] = $document;
            }
        }

        if ( empty( $attachments ) ) {
            return new \WP_Error(
                'no_attachments',
                __( 'No PDF attachments could be generated for the selected order and email workflow.', 'kitgenix-pdf-invoicing-for-woocommerce' )
            );
        }

        $sent_labels = array_map(
            static function ( array $document ): string {
                return (string) $document['label'];
            },
            $sent_documents
        );

        $message_lines = [
            __( 'This is a test email sent from Kitgenix PDF Invoicing for WooCommerce.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            sprintf(
                /* translators: %s: WooCommerce email workflow label. */
                __( 'WooCommerce email workflow: %s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                $preview['email_label']
            ),
            sprintf(
                /* translators: %s: WooCommerce order number. */
                __( 'Order number: %s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                $order->get_order_number()
            ),
            sprintf(
                /* translators: %s: comma-separated document labels. */
                __( 'Attached documents: %s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                implode( ', ', $sent_labels )
            ),
            __( 'This tool sends the configured PDF attachments only. It does not trigger the live WooCommerce customer/admin email template.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
        ];

        if ( ! empty( $skipped_documents ) ) {
            $message_lines[] = sprintf(
                /* translators: %s: comma-separated document labels. */
                __( 'Skipped for this order: %s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                implode( ', ', array_map( static function ( array $document ): string { return (string) $document['label']; }, $skipped_documents ) )
            );
        }

        if ( ! empty( $failed_documents ) ) {
            $message_lines[] = sprintf(
                /* translators: %s: comma-separated document labels. */
                __( 'Failed to generate: %s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                implode( ', ', array_map( static function ( array $document ): string { return (string) $document['label']; }, $failed_documents ) )
            );
        }

        $subject = sprintf(
            /* translators: %s: WooCommerce order number. */
            __( 'Kitgenix PDF test email for order #%s', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            $order->get_order_number()
        );

        $sent = wp_mail(
            $recipient_email,
            $subject,
            implode( "\n\n", $message_lines ),
            [ 'Content-Type: text/plain; charset=UTF-8' ],
            $attachments
        );

        $this->delete_temp_files( $attachments );

        if ( ! $sent ) {
            return new \WP_Error(
                'send_failed',
                __( 'WordPress could not send the test email. Check your site mail configuration and try again.', 'kitgenix-pdf-invoicing-for-woocommerce' )
            );
        }

        return [
            'recipient_email'  => $recipient_email,
            'email_label'      => $preview['email_label'],
            'sent_documents'   => $sent_documents,
            'skipped_documents'=> $skipped_documents,
            'failed_documents' => $failed_documents,
        ];
    }

    public function handle_test_email_send(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kitgenix-pdf-invoicing-for-woocommerce' ) );
        }

        check_admin_referer( 'kitgenix_pdf_test_email_send' );

        $order_id         = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        $email_id         = isset( $_POST['email_id'] ) ? sanitize_key( wp_unslash( $_POST['email_id'] ) ) : '';
        $recipient_email  = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) ) : '';

        $redirect_args = [
            'email_preview_order_id' => $order_id,
            'email_preview_email_id' => $email_id,
            'email_test_recipient'   => $recipient_email,
        ];

        if ( $order_id < 1 ) {
            $this->redirect_with_settings_notice(
                'error',
                'kitgenix_pdf_email_test_order_missing',
                __( 'Enter a valid WooCommerce order ID before sending a test email.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                $redirect_args
            );
        }

        if ( '' === $email_id ) {
            $this->redirect_with_settings_notice(
                'error',
                'kitgenix_pdf_email_test_email_missing',
                __( 'Choose a WooCommerce email workflow before sending a test email.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                $redirect_args
            );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order instanceof \WC_Order ) {
            $this->redirect_with_settings_notice(
                'error',
                'kitgenix_pdf_email_test_order_invalid',
                __( 'The selected WooCommerce order could not be found.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                $redirect_args
            );
        }

        $result = $this->send_test_email( $recipient_email, $email_id, $order );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_settings_notice(
                'error',
                'kitgenix_pdf_email_test_failed',
                $result->get_error_message(),
                $redirect_args
            );
        }

        $document_labels = implode(
            ', ',
            array_map(
                static function ( array $document ): string {
                    return (string) $document['label'];
                },
                $result['sent_documents']
            )
        );

        $message = sprintf(
            /* translators: 1: recipient email address, 2: document labels. */
            __( 'Test email sent to %1$s with: %2$s.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
            $recipient_email,
            $document_labels
        );

        if ( ! empty( $result['skipped_documents'] ) ) {
            $message .= ' ' . sprintf(
                /* translators: %s: comma-separated document labels. */
                __( 'Skipped for this order: %s.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                implode( ', ', array_map( static function ( array $document ): string { return (string) $document['label']; }, $result['skipped_documents'] ) )
            );
        }

        if ( ! empty( $result['failed_documents'] ) ) {
            $message .= ' ' . sprintf(
                /* translators: %s: comma-separated document labels. */
                __( 'Failed to generate: %s.', 'kitgenix-pdf-invoicing-for-woocommerce' ),
                implode( ', ', array_map( static function ( array $document ): string { return (string) $document['label']; }, $result['failed_documents'] ) )
            );
        }

        $this->redirect_with_settings_notice(
            'updated',
            'kitgenix_pdf_email_test_sent',
            $message,
            $redirect_args
        );
    }

    /**
     * Delete any generated temp files at the end of the request.
     */
    public function cleanup_temp_files(): void {
        $this->delete_temp_files( $this->temp_files );
        $this->temp_files = [];
    }

    /**
    * @param \WC_Email|mixed|null $email
     * @return string[]
     */
    protected function get_email_document_types( string $email_id, \WC_Order $order, $email = null ): array {
        $settings = Settings::get_all();
        $map_raw  = isset( $settings['email_attachments'] ) && is_array( $settings['email_attachments'] )
            ? $settings['email_attachments']
            : [];

        $full_map = [];
        foreach ( $map_raw as $mail_id => $docs ) {
            if ( ! is_array( $docs ) ) {
                continue;
            }

            $enabled_docs = [];
            foreach ( $docs as $doc_type => $enabled ) {
                if ( ! empty( $enabled ) ) {
                    $enabled_docs[] = $doc_type;
                }
            }

            if ( $enabled_docs ) {
                $full_map[ $mail_id ] = $enabled_docs;
            }
        }

        $map = (array) apply_filters(
            'kitgenix_pdf_email_document_map',
            $full_map,
            $email_id,
            $order,
            $email
        );

        if ( empty( $map[ $email_id ] ) || ! is_array( $map[ $email_id ] ) ) {
            return [];
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map( 'strval', $map[ $email_id ] ),
                    static function ( string $doc_type ): bool {
                        return in_array( $doc_type, DocumentTypes::all(), true );
                    }
                )
            )
        );
    }

    /**
    * @param \WC_Email|mixed|null $email
     * @return array<string,mixed>
     */
    protected function get_document_preview_state( string $email_id, string $doc_type, \WC_Order $order, $email = null ): array {
        $attach_enabled = (bool) apply_filters(
            'kitgenix_pdf_email_attach_document',
            true,
            $email_id,
            $doc_type,
            $order,
            $email
        );

        $eligible = $attach_enabled && $this->is_document_available_for_order( $doc_type, $order );
        $reason   = '';

        if ( ! $attach_enabled ) {
            $reason = __( 'Disabled by a filter or custom email attachment rule.', 'kitgenix-pdf-invoicing-for-woocommerce' );
        } elseif ( DocumentTypes::CREDIT_NOTE === $doc_type ) {
            $refunds      = $order->get_refunds();
            $refund_count = is_array( $refunds ) ? count( $refunds ) : 0;
            if ( $refund_count < 1 ) {
                $reason = __( 'This order has no refunds, so a credit note is not available.', 'kitgenix-pdf-invoicing-for-woocommerce' );
            }
        }

        if ( '' === $reason && ! $eligible ) {
            $reason = __( 'This document is currently unavailable for the selected order.', 'kitgenix-pdf-invoicing-for-woocommerce' );
        }

        return [
            'type'     => $doc_type,
            'label'    => DocumentTypes::get_label( $doc_type ),
            'eligible' => $eligible,
            'reason'   => $reason,
        ];
    }

    protected function is_document_available_for_order( string $doc_type, \WC_Order $order ): bool {
        if ( ! in_array( $doc_type, DocumentTypes::all(), true ) ) {
            return false;
        }

        if ( DocumentTypes::CREDIT_NOTE === $doc_type ) {
            $refunds      = $order->get_refunds();
            $refund_count = is_array( $refunds ) ? count( $refunds ) : 0;
            if ( $refund_count < 1 ) {
                return false;
            }
        }

        return (bool) apply_filters(
            'kitgenix_pdf_document_enabled',
            true,
            $order,
            $doc_type
        );
    }

    /**
      * @param \WC_Email|mixed|null $email
      * @return \WC_Email|mixed|null
     */
    protected function resolve_email_context( string $email_id, $email = null ) {
          if ( $email instanceof \WC_Email ) {
            return $email;
        }

        if ( ! function_exists( 'WC' ) ) {
            return null;
        }

        $woocommerce = WC();
        if ( ! $woocommerce || ! method_exists( $woocommerce, 'mailer' ) ) {
            return null;
        }

        $mailer = $woocommerce->mailer();
        if ( ! $mailer || ! method_exists( $mailer, 'get_emails' ) ) {
            return null;
        }

        foreach ( (array) $mailer->get_emails() as $wc_email ) {
            if ( $wc_email instanceof \WC_Email && isset( $wc_email->id ) && (string) $wc_email->id === $email_id ) {
                return $wc_email;
            }
        }

        return null;
    }

    /**
      * @param \WC_Email|mixed|null $email
     */
    protected function get_email_label( string $email_id, $email = null ): string {
          if ( $email instanceof \WC_Email && method_exists( $email, 'get_title' ) ) {
            $label = trim( (string) $email->get_title() );
            if ( '' !== $label ) {
                return $label;
            }
        }

        $labels = self::get_supported_email_labels();

        return $labels[ $email_id ] ?? $email_id;
    }

    protected function redirect_with_settings_notice( string $type, string $code, string $message, array $redirect_args = [] ): void {
        add_settings_error( Settings::OPTION_KEY, $code, $message, $type );
        set_transient( 'settings_errors', get_settings_errors(), 30 );

        $redirect_url = add_query_arg(
            array_merge(
                [
                    'page' => 'kitgenix-pdf-invoicing-settings',
                    'tab'  => 'emails',
                ],
                array_filter(
                    $redirect_args,
                    static function ( $value ) {
                        return '' !== (string) $value && null !== $value;
                    }
                )
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * @param string[] $paths
     */
    protected function delete_temp_files( array $paths ): void {
        foreach ( $paths as $path ) {
            if ( is_string( $path ) && '' !== $path && file_exists( $path ) ) {
                wp_delete_file( $path );
            }
        }

        if ( empty( $paths ) ) {
            return;
        }

        $this->temp_files = array_values(
            array_filter(
                $this->temp_files,
                static function ( string $candidate ) use ( $paths ): bool {
                    return ! in_array( $candidate, $paths, true );
                }
            )
        );
    }
}
