<?php

declare(strict_types=1);

namespace UhifadhiLabs\ModuleContracts;

/**
 * How a uhifadhi module declares itself to the host — its catalogue metadata plus
 * an optional own entry route. One implementation = one module (the per-area
 * capability shown in an area's module grid). By convention a bundle provides
 * exactly one module named after itself; anything the module contains (a sightings
 * module's "surveys", say) is the module's OWN internal concern and invisible here.
 *
 * Built-in modules and installable module bundles implement this identically, so
 * the host can register both through one seam. `entryRoute()` is the one new
 * capability over the legacy catalogue: return null to render through the host's
 * generic module page, or a route name to own your pages.
 *
 * The defaults for the optional methods live in {@see ModuleProviderTrait}; a
 * typical provider only defines slug(), name() and category().
 */
interface ModuleProviderInterface
{
    /**
     * Stable identity + routing key: lowercase letters only, unique across
     * modules (e.g. "forest", "sightings").
     */
    public function slug(): string;

    /** Human display name shown in the grid and sub-nav (e.g. "Forest loss", "Sightings"). */
    public function name(): string;

    /**
     * Catalogue category the host files this module under — a category value
     * string the host understands, such as "flux", "pressure" or
     * "biodiversity" (the host maps + validates it, falling back to a default
     * for anything it does not recognise).
     */
    public function category(): string;

    /** Lifecycle status string the host understands, e.g. "live" or "template". */
    public function status(): string;

    /** Short provenance line for the tile (e.g. "Hansen GFC"), or null. */
    public function dataSource(): ?string;

    /** Whether the module is pinned (always-on, first) in an area — e.g. the hub. */
    public function pinned(): bool;

    /**
     * CORE, i.e. platform machinery rather than a capability an area opts into.
     *
     * An installable module is INSTALLED but not switched on: the host seeds it
     * into the catalogue parked, and an admin enables it per area. That is right
     * for a capability an area may not want, and wrong for a module other
     * surfaces already depend on — a map platform, say, whose absence does not
     * mean "fewer features" but "four broken screens". A core module is seeded
     * ACTIVE in every area instead.
     *
     * The word means "on by default", not "cannot be turned off": the host's
     * Customize page still governs an area's modules, and installing or removing
     * the bundle still governs whether the module exists at all. Nothing about
     * being core makes a module a different KIND of thing — same contract, same
     * bundle shape, same seams. It is a default, and only a default.
     *
     * Almost every module answers false (the trait's default). Say true only when
     * a host would be broken without you.
     */
    public function core(): bool;

    /** Ordering hint within the catalogue; lower sorts first. */
    public function position(): int;

    /** Lucide icon name for the tile/nav, or null for the host default. */
    public function icon(): ?string;

    /**
     * Null = the module renders through the host's generic module page
     * (the legacy behaviour every built-in uses). A route name = the module
     * owns its pages; the host links to it with the area's uuid, i.e.
     * path(entryRoute(), {uuid: area.uuid}).
     */
    public function entryRoute(): ?string;

    /**
     * The granular permissions this module DECLARES — the host folds them into
     * its permission catalogue so admins can assign them, and they vanish with
     * the module on uninstall. Declaring is all a module may do: it never
     * grants, maps to roles, or names default holders (see ModulePermission).
     * Most modules declare none.
     *
     * @return list<ModulePermission>
     */
    public function permissions(): array;
}
