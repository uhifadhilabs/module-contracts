# uhifadhi/module-contracts

The contract a [uhifadhi](https://github.com/uhifadhilabs) module declares itself with.
MIT — public on purpose, so built-in modules and installable module bundles implement it
identically and the host registers both through one seam.

## `ModuleProviderInterface`

Catalogue metadata (`slug`, `name`, `category`, `status`, `dataSource`, `pinned`, `position`,
`icon`, `core`) plus the one capability beyond the legacy catalogue: **`entryRoute()`** — return `null`
to render through the host's generic module page (what every built-in does today), or a route
name to own your pages (the host links with the area's uuid).

One implementation = one module (the per-area capability shown in an area's module grid). By
convention a bundle provides exactly one module named after itself; whatever lives *inside* a
module (a sightings module's "surveys", for instance) is the module's own internal concern and never
appears in this contract.

```php
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

### Installable and base

`base()` is the one tier distinction. An **installable** module (`base()` is `false`, the default)
is seeded into the catalogue *parked*: installed, but switched on per area by an admin. A **base**
module is seeded *active* in every area, because other surfaces already depend on it — the map
platform is the first, and a host without it does not have fewer features, it has broken screens.

Base means "on by default", not "cannot be turned off": the host's Customize page still governs an
area's modules. Same contract, same bundle shape, same seams — the tier is a default, not a
different kind of thing.

The word is **base**, and it used to be "core". "Core" marks a thing as important without saying
what it is, and it is the word a codebase reaches for twice — once for the runtime at the centre,
once for what ships by default. Those get separate words here: the runtime is the **seam**
(`uhifadhi/seam-module`, what your provider registers with), and the always-present tier of
ordinary modules is **base**. "Base" also names the real test — a base module is one whose absence
makes the installation *not uhifadhi*.

## Why one package?

Symfony ships its contracts split per domain — `symfony/cache-contracts`,
`symfony/event-dispatcher-contracts` — because outsiders consume single domains: Monolog
wants the log interfaces and nothing else, and making it drag in the cache ones would be rude.

This package does not split, because its consumer species is singular: **modules**. A module
needs the registration seam simply to exist — that is what makes it a module rather than a
bundle — so nobody wants the overview-contribution interfaces *without* registration. Split
packages here would always be installed together, and two packages that never travel apart are
a boundary drawn in the wrong place. The test is whether either half has an independent life,
and here neither does.

The day that stops being true, split. If a consumer appears that is not a module — an external
tool that wants only the data-shape contracts and has no interest in registering with a host —
then that domain has an audience of its own and has earned its own package. Not before.

## Building a module

See **[docs/module-development.md](docs/module-development.md)** — the full guide from `composer.json`
to a Flex recipe, written for someone building a custom module against these contracts.

## Development

```bash
composer install
composer check   # cs + phpstan (max) + phpunit
```
