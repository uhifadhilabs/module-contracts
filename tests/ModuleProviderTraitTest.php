<?php

declare(strict_types=1);

namespace Uhifadhi\ModuleContracts\Tests;

use PHPUnit\Framework\TestCase;
use Uhifadhi\ModuleContracts\ModulePermission;
use Uhifadhi\ModuleContracts\ModuleProviderInterface;
use Uhifadhi\ModuleContracts\ModuleProviderTrait;

/**
 * The contract's only behaviour is the defaults trait: a provider that defines
 * just the three required methods is a valid ModuleProviderInterface with the
 * documented common-case defaults.
 */
final class ModuleProviderTraitTest extends TestCase
{
    private function minimalProvider(): ModuleProviderInterface
    {
        return new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'example';
            }

            public function name(): string
            {
                return 'Example';
            }

            public function category(): string
            {
                return 'pressure';
            }
        };
    }

    public function testTraitSuppliesTheCommonCaseDefaults(): void
    {
        $provider = $this->minimalProvider();

        self::assertSame('example', $provider->slug());
        self::assertSame('Example', $provider->name());
        self::assertSame('pressure', $provider->category());
        self::assertSame('live', $provider->status());
        self::assertNull($provider->dataSource());
        self::assertFalse($provider->pinned());
        self::assertFalse($provider->base(), 'A module is installable until it says otherwise — base is the exception.');
        self::assertSame(0, $provider->position());
        self::assertNull($provider->icon());
        self::assertNull($provider->entryRoute(), 'A generically-rendered module has no own entry route.');
        self::assertSame([], $provider->permissions(), 'Most modules gate nothing beyond what the host already does.');
    }

    public function testAModuleDeclaresItsPermissionsWithoutGrantingThem(): void
    {
        $provider = new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'example';
            }

            public function name(): string
            {
                return 'Example';
            }

            public function category(): string
            {
                return 'pressure';
            }

            public function permissions(): array
            {
                return [new ModulePermission('example.review', 'Example', 'Review')];
            }
        };

        $permissions = $provider->permissions();
        self::assertCount(1, $permissions);
        self::assertSame('example.review', $permissions[0]->value);
        self::assertSame('Example', $permissions[0]->umbrella);
        self::assertSame('Review', $permissions[0]->action);
    }

    public function testOverridesWinOverDefaults(): void
    {
        $provider = new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'sightings';
            }

            public function name(): string
            {
                return 'Sightings';
            }

            public function category(): string
            {
                return 'biodiversity';
            }

            public function entryRoute(): ?string // @phpstan-ignore return.unusedType (signature must match the interface)
            {
                return 'sightings_area';
            }

            public function icon(): ?string // @phpstan-ignore return.unusedType (signature must match the interface)
            {
                return 'binoculars';
            }
        };

        self::assertSame('sightings_area', $provider->entryRoute(), 'A module owning its pages returns a route name.');
        self::assertSame('binoculars', $provider->icon());
        self::assertSame('live', $provider->status(), 'Untouched defaults still apply.');
    }

    /**
     * The base tier: platform machinery other surfaces already depend on says
     * so, and the host seeds it active rather than parked.
     */
    public function testABaseModuleSaysSo(): void
    {
        $provider = new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'map';
            }

            public function name(): string
            {
                return 'Map';
            }

            public function category(): string
            {
                return 'operations';
            }

            public function base(): bool
            {
                return true;
            }
        };

        self::assertTrue($provider->base());
        self::assertFalse($provider->pinned(), 'Base is about the initial state, pinned is about the ordering — they are separate.');
    }
}
