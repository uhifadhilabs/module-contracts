<?php

declare(strict_types=1);

namespace Uhifadhi\ModuleContracts;

/**
 * Sensible defaults for the optional parts of {@see ModuleProviderInterface},
 * so a concrete provider usually only has to define slug(), name() and
 * category(). Override any of these to opt out of the default.
 *
 * Defaults describe the common case: a live, unpinned, generically-rendered
 * module with no provenance line and the host's default icon.
 */
trait ModuleProviderTrait
{
    public function status(): string
    {
        return 'live';
    }

    public function dataSource(): ?string
    {
        return null;
    }

    public function pinned(): bool
    {
        return false;
    }

    public function core(): bool
    {
        return false;
    }

    public function position(): int
    {
        return 0;
    }

    public function icon(): ?string
    {
        return null;
    }

    public function entryRoute(): ?string
    {
        return null;
    }

    /** @return list<ModulePermission> */
    public function permissions(): array
    {
        return [];
    }
}
