<?php

namespace Kitgenix\PDF_Invoicing\Core;

defined( 'ABSPATH' ) || exit;

class ModuleManager {

    /**
     * @var ModuleInterface[]
     */
    protected array $modules = [];

    public function add_module( ModuleInterface $module ): void {
        $this->modules[ $module->get_id() ] = $module;
    }

    /**
     * Boot all modules (register hooks).
     */
    public function boot(): void {
        foreach ( $this->modules as $module ) {
            $module->register();
        }
    }

    /**
     * Fetch a module by ID (future use).
     */
    public function get_module( string $id ): ?ModuleInterface {
        return $this->modules[ $id ] ?? null;
    }

    /**
     * @return ModuleInterface[]
     */
    public function all(): array {
        return $this->modules;
    }
}
