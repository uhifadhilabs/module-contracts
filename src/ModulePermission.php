<?php

declare(strict_types=1);

namespace Uhifadhi\ModuleContracts;

/**
 * One granular permission a module DECLARES to the host — never grants.
 *
 * The host folds declared permissions into its own catalogue (permission matrix,
 * voter), so an admin can assign them to positions; uninstalling the module
 * removes the declaration and the permission disappears with it. A declaration
 * carries no role and no default holders: installing a module must never hand
 * any existing user a new power. Enforcement direction stays clean both ways —
 * the module checks its own routes for the value it declares, and the host
 * alone decides who holds it.
 */
final readonly class ModulePermission
{
    /**
     * @param string $value    the attribute checked at the module's routes,
     *                         namespaced by convention (e.g. "verification.tiebreak")
     * @param string $umbrella catalogue group label shown in the host's
     *                         permission matrix (e.g. "Verification")
     * @param string $action   short action label within the umbrella (e.g. "Tiebreak")
     */
    public function __construct(
        public string $value,
        public string $umbrella,
        public string $action,
    ) {
    }
}
