# uhifadhi/module-contracts

The contract a [uhifadhi](https://github.com/uhifadhilabs) module declares itself with.

## What it is

Two interfaces and a trait. `ModuleProviderInterface` is how a bundle announces itself to a host
as a module — the catalogue metadata and the route it enters on. `Entity\UserInterface` is how a
module's records point at a person without depending on whoever owns accounts.

MIT — public on purpose, so built-in and installed modules implement it identically and the host
registers both through one seam.

## Installation

```bash
composer require uhifadhi/module-contracts
```

There is nothing to configure and no bundle to register: this package is interfaces, and the
host you install into already knows what to do with an implementation of them.

## Getting started

Implement `ModuleProviderInterface` once, in the bundle that is your module:

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

Then tag it. The host autoconfigures every implementation with `uhifadhi.module` and its
catalogue seed ingests them — but a module shipped as a *reusable bundle* is **not**
autoconfigured, so tag `uhifadhi.module` explicitly in your extension.

Where a record needs a person on it, type-hint the contract rather than an account class:

```php
// src/Entity/Sighting.php (your bundle)
use Uhifadhi\ModuleContracts\Entity\UserInterface;

#[ORM\ManyToOne(targetEntity: UserInterface::class)]
private ?UserInterface $recordedBy = null;
```

`uhifadhi/team-module` prepends the `resolve_target_entities` line that points it at a real
class, so an installation writes no doctrine configuration of its own.

## Development

```bash
composer install
composer check   # cs + phpstan (max) + phpunit
```

## Learn more

- **[What a contract is](docs/what-is-a-contract.md)** — what the word means here, and why some
  contracts live in this package while others live with the bundle they describe.
- **[Building a uhifadhi module](docs/module-development.md)** — the full guide from
  `composer.json` to a Flex recipe, written for someone building a custom module against these
  contracts.
- **[`ModuleProviderInterface`](docs/module-provider.md)** — the full metadata surface, and the
  base-versus-installable tier that decides whether your module arrives switched on.
- **[`Entity\UserInterface`](docs/user-interface.md)** — the seven questions it asks, and the rule
  that whoever provides the entity states the resolution.
- **[Why one package?](docs/why-one-package.md)** — why the registration seam and the data-shape
  contracts ship together, and the test that would split them.
- **[Area-scoped authority](docs/area-scoped-authority.md)** — *design + architecture, decisions
  open* — how a department's scope becomes the boundary of a person's authority, which permissions
  carry an area, and how a module would declare its permission's scope.

## License

MIT. See [LICENSE](LICENSE).
