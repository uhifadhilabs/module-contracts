# uhifadhilabs/module-contracts

The contract a [uhifadhi](https://github.com/uhifadhilabs) module declares itself with.
MIT — public on purpose, so built-in modules and installable module bundles implement it
identically and the host registers both through one seam.

## `ModuleProviderInterface`

Catalogue metadata (`slug`, `name`, `category`, `status`, `dataSource`, `pinned`, `position`,
`icon`) plus the one capability beyond the legacy catalogue: **`entryRoute()`** — return `null`
to render through the host's generic module page (what every built-in does today), or a route
name to own your pages (the host links with the area's uuid).

One implementation = one module (the per-area capability shown in an area's module grid). By
convention a bundle provides exactly one module named after itself; whatever lives *inside* a
module (a sightings module's "surveys", for instance) is the module's own internal concern and never
appears in this contract.

```php
use UhifadhiLabs\ModuleContracts\ModuleProviderInterface;
use UhifadhiLabs\ModuleContracts\ModuleProviderTrait;

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
ingests them.

## Development

```bash
composer install
composer check   # cs + phpstan (max) + phpunit
```
