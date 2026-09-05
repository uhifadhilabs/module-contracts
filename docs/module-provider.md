# `ModuleProviderInterface`

The interface a bundle implements to declare itself a uhifadhi module, and the one tier
distinction the platform draws between modules.

## What the interface asks for

Catalogue metadata (`slug`, `name`, `category`, `status`, `dataSource`, `pinned`, `position`,
`icon`, `core`) plus the one capability beyond the legacy catalogue: **`entryRoute()`** — return `null`
to render through the host's generic module page (what every built-in does today), or a route
name to own your pages (the host links with the area's uuid).

One implementation = one module (the per-area capability shown in an area's module grid). By
convention a bundle provides exactly one module named after itself; whatever lives *inside* a
module (a sightings module's "surveys", for instance) is the module's own internal concern and never
appears in this contract.

```php
// src/Module/SightingsModuleProvider.php (your bundle)
use Uhifadhi\ModuleContracts\ModuleProviderInterface;
use Uhifadhi\ModuleContracts\ModuleProviderTrait;

final class SightingsModuleProvider implements ModuleProviderInterface
{
    use ModuleProviderTrait; // defaults for the optional methods

    public function slug(): string     { return 'sightings'; }
    public function name(): string     { return 'Sightings'; }
    public function category(): string { return 'biodiversity'; }
    public function entryRoute(): ?string { return 'sightings_area'; }
}
```

The host autoconfigures every implementation (tag `uhifadhi.module`) and its catalogue seed
ingests them. A module shipped as a *reusable bundle* is **not** autoconfigured — tag
`uhifadhi.module` explicitly in your extension.

## First, the tier: capability or infrastructure

A provider is how a **capability** module (patrol, incident) joins the platform — a capability an
area may not want, so an admin governs it per area from a catalogue tile. **Infrastructure** modules
(area, map, widget, storage, team) are the other tier: machinery every relevant screen already
relies on, installed-means-on everywhere, never a per-area choice. Infrastructure contributes **no**
provider — nothing to catalogue, nothing to ledger — and is guaranteed present by the composer graph
instead. So if you are reading this to write a provider, you are (almost certainly) writing a
capability module. The full test is in
[module-development.md](module-development.md#two-tiers-infrastructure-and-capability).

## Installable and base (capability modules)

Within the capability tier, `base()` decides the initial per-area state. An **installable** module
(`base()` is `false`, the default) is seeded into the catalogue *parked*: installed, but switched on
per area by an admin. A **ships-on** module (`base()` is `true`) is seeded *active* in every area.

`base()` means "on by default", not "cannot be turned off": the host's Customize page still governs
an area's modules, and this is a distinction *among capability modules only* — it is not how
infrastructure is expressed (infrastructure has no provider to call it on). Map was seeded active
with `base()` until it was reclassified as infrastructure and its provider removed.

## Why "base" and not "core"

The word is **base**, and it used to be "core". "Core" marks a thing as important without saying
what it is, and it is the word a codebase reaches for twice — once for the runtime at the centre,
once for what ships by default. Those get separate words here: the runtime is the **seam**
(`uhifadhi/seam-module`, what your provider registers with), and the always-present tier of
ordinary modules is **base**. "Base" also names the real test — a base module is one whose absence
makes the installation *not uhifadhi*.
