<?php

declare(strict_types=1);

namespace UhifadhiLabs\ModuleContracts\Tests;

use PHPUnit\Framework\TestCase;
use UhifadhiLabs\ModuleContracts\ModuleProviderInterface;
use UhifadhiLabs\ModuleContracts\ModuleProviderTrait;

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
        self::assertSame(0, $provider->position());
        self::assertNull($provider->icon());
        self::assertNull($provider->entryRoute(), 'A generically-rendered module has no own entry route.');
    }

    public function testOverridesWinOverDefaults(): void
    {
        $provider = new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'uhakiki';
            }

            public function name(): string
            {
                return 'Uhakiki';
            }

            public function category(): string
            {
                return 'pressure';
            }

            public function entryRoute(): ?string // @phpstan-ignore return.unusedType (signature must match the interface)
            {
                return 'uhakiki_area';
            }

            public function icon(): ?string // @phpstan-ignore return.unusedType (signature must match the interface)
            {
                return 'shield-check';
            }
        };

        self::assertSame('uhakiki_area', $provider->entryRoute(), 'A module owning its pages returns a route name.');
        self::assertSame('shield-check', $provider->icon());
        self::assertSame('live', $provider->status(), 'Untouched defaults still apply.');
    }
}
