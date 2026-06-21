<?php

namespace Kitgenix\PDF_Invoicing\Core;

defined( 'ABSPATH' ) || exit;

interface ModuleInterface {

    /**
     * Unique ID for this module.
     */
    public function get_id(): string;

    /**
     * Register hooks, filters, etc.
     */
    public function register(): void;
}
