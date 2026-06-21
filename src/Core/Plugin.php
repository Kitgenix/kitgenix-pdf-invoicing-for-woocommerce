<?php

namespace Kitgenix\PDF_Invoicing\Core;

use Kitgenix\PDF_Invoicing\Modules\Admin\AdminModule;
use Kitgenix\PDF_Invoicing\Modules\Frontend\FrontendModule;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\InvoicingModule;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\PdfGenerator;
use Kitgenix\PDF_Invoicing\Modules\Invoicing\TemplateRenderer;
use Kitgenix\PDF_Invoicing\Modules\Settings\SettingsModule;
use Kitgenix\PDF_Invoicing\Modules\Email\EmailModule;

defined( 'ABSPATH' ) || exit;

class Plugin {

    public function init(): void {

        $renderer      = new TemplateRenderer();
        $pdf_generator = new PdfGenerator( $renderer );
        $email_module  = new EmailModule( $pdf_generator );

        $manager = new ModuleManager();

        $modules = [
            new InvoicingModule( $pdf_generator ),
            new FrontendModule( $pdf_generator ),
            new AdminModule( $pdf_generator ),
            $email_module,
            new SettingsModule( $email_module ),
        ];

        /**
         * Filter the modules so other plugins (or Pro) can add/remove modules.
         *
         * @param ModuleInterface[] $modules
         */
        $modules = apply_filters( 'kitgenix_pdf_invoicing_modules', $modules );

        foreach ( $modules as $module ) {
            if ( $module instanceof ModuleInterface ) {
                $manager->add_module( $module );
            }
        }

        $manager->boot();
    }
}
