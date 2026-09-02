# Building a uhifadhi module

This guide is for someone **outside** the uhifadhi team building a module against the public
contracts. It is written from the extractions the platform's own modules went through, so where
the platform has a rough edge the guide says so rather than describing the version we wish
existed.

The running example is a fictional **Sightings** module — wildlife observations recorded in an
area — because a made-up module can be shown end to end without pretending a real deployment
works some particular way.

## Contents

- [1. Naming](#1-naming)
- [2. The scaffold](#2-the-scaffold)
- [3. The module seam](#3-the-module-seam)

---

## 1. Naming

A capability is a **module** to users and a **bundle** to Symfony. Each word stays in its own
layer, and the namespace names the domain and argues with nobody.

| Layer | Rule | Sightings |
|---|---|---|
| Repo + composer name (uhifadhi-exclusive) | `<vendor>/<name>-module` | `acme/sightings-module` |
| Repo + composer name (generic Symfony package) | `<vendor>/<name>-bundle` | — |
| PHP namespace | the DOMAIN, no meta-word | `Acme\Sightings\Entity\Sighting` |
| Bundle class (the one Symfony plug) | `<Vendor><Name>Bundle` | `Acme\Sightings\AcmeSightingsBundle` |
| Config alias (`extensionAlias`) | the bare domain word | `sightings:` |
| Service ids | prefixed with the alias | `sightings.observation_repository` |
| DB tables | prefixed with the domain word | `sightings_observation` |
| App UI / catalogue | module | the "Sightings" module tile |

Three consequences worth stating:

- **"module" appears only in the package name and the UI.** Never in the namespace, never in a
  class name. A class called `SightingsModuleService` is a smell.
- **The package name is never parsed.** Symfony Flex registers a bundle because the package
  declares `"type": "symfony-bundle"`, and it registers the bundle *class*. So a `-module`
  package registers exactly like a `-bundle` one.
- **`extensionAlias` must be set explicitly.** Without it, `AcmeSightingsBundle` derives the
  alias `acme_sightings`, and your users write `acme_sightings:` in YAML forever. Set
  `protected string $extensionAlias = 'sightings';`.

Platform machinery follows the same rule even when it contributes no catalogue tile of its own.

---

## 2. The scaffold

A module is an ordinary reusable Symfony bundle. Nothing here is uhifadhi-specific except the
contracts dependency.

```
sightings-module/
├── .github/workflows/ci.yml
├── .php-cs-fixer.dist.php
├── .gitignore
├── LICENSE
├── README.md
├── composer.json
├── phpstan.dist.neon
├── phpunit.dist.xml
├── assets/                 # importmap JavaScript (chapter to come)
├── config/services.php     # static wiring
├── public/                 # stylesheets, vendor scripts, images
├── src/
│   ├── AcmeSightingsBundle.php
│   ├── DependencyInjection/SightingsConfiguration.php
│   ├── Entity/ Repository/ Service/ Controller/
│   └── Module/SightingsModuleProvider.php
├── templates/
└── tests/{Unit,Integration,Functional}/
```

### composer.json

```json
{
    "name": "acme/sightings-module",
    "type": "symfony-bundle",
    "require": {
        "php": ">=8.4",
        "symfony/config": "^7.3 || ^8.0",
        "symfony/dependency-injection": "^7.3 || ^8.0",
        "symfony/framework-bundle": "^7.3 || ^8.0",
        "symfony/http-kernel": "^7.3 || ^8.0",
        "uhifadhilabs/module-contracts": "^0.1"
    },
    "autoload": { "psr-4": { "Acme\\Sightings\\": "src/" } },
    "autoload-dev": { "psr-4": { "Acme\\Sightings\\Tests\\": "tests/" } },
    "scripts": {
        "cs:check": "php-cs-fixer fix --dry-run --diff",
        "phpstan": "phpstan analyse --no-progress --memory-limit=1G",
        "test": "phpunit",
        "check": ["@cs:check", "@phpstan", "@test"]
    }
}
```

`composer check` is the whole standard: style, static analysis at PHPStan **max**, tests. If your
CI runs one command, run that one.

Put anything you only need for tests in `require-dev`, and be honest about `suggest`: a bundle
that needs `symfony/asset-mapper` to serve its scripts but works without it should say so there
rather than hard-requiring it.

### The bundle class

Extend `AbstractBundle` — it collapses the old Extension + Configuration + Bundle triangle into
one file.

```php
final class AcmeSightingsBundle extends AbstractBundle
{
    protected string $extensionAlias = 'sightings';

    public function configure(DefinitionConfigurator $definition): void
    {
        SightingsConfiguration::define($definition->rootNode());
    }

    public function prependExtension(ContainerConfigurator $c, ContainerBuilder $b): void
    {
        // zero-config: map your own entities, register your asset paths
    }

    public function loadExtension(array $config, ContainerConfigurator $c, ContainerBuilder $b): void
    {
        $c->import('../config/services.php');
        // …config-driven definitions
    }
}
```

**Zero-config persistence.** Map your own entity directory in `prependExtension()` so no host ever
writes a `doctrine.orm.mappings` block for your tables:

```php
if ($builder->hasExtension('doctrine')) {
    $container->extension('doctrine', ['orm' => ['mappings' => [
        'AcmeSightings' => [
            'type' => 'attribute',
            'dir' => __DIR__.'/Entity',
            'prefix' => 'Acme\\Sightings\\Entity',
            'is_bundle' => false,
        ],
    ]]]);
}
```

### Explicit DI, always

Symfony's own [bundle best practices](https://symfony.com/doc/current/bundles/best_practices.html)
are not advice here, they are the rule:

> Services should not use autowiring or autoconfiguration. Instead, all services should be defined
> explicitly.

> If the bundle defines services, they must be prefixed with the bundle alias.

So `config/services.php` — **PHP, not YAML**, because a reusable bundle must not force
`symfony/yaml` onto its hosts, and FQCN references stay refactor-safe and PHPStan-checked:

```php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('sightings.observation_repository', ObservationRepository::class)
        ->args([service('doctrine')]);
};
```

Keep **static** wiring in that file and **config-driven** wiring in `loadExtension()`, where the
processed configuration is in hand.

Two consequences of not being autoconfigured that bite everyone once:

- **Tags do not apply themselves.** The host calls `registerForAutoconfiguration()` for its seam
  interfaces, which only fires for autoconfigured services. Your services are not. Every tag —
  `uhifadhi.module` included — is written by hand in `loadExtension()`.
- **Controllers need no base class**, but their service id must be the FQCN and the service must
  be `->public()`, because that is how the router resolves a controller named by `#[Route]`.

### The config tree

Put it in its own class with a **static** `define()` so it is testable with a plain `Processor`
and shared verbatim by `configure()`:

```php
final class SightingsConfiguration
{
    public static function define(NodeDefinition|ArrayNodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('The sightings root node must be an array node.');
        }

        $root->children()
            ->scalarNode('module_category')->defaultValue('biodiversity')->cannotBeEmpty()->end()
            ->booleanNode('dev_tools')->defaultFalse()->end()
        ->end();
    }
}
```

Rules that have earned themselves:

- **Leave the tree closed.** An invented key must fail loudly. Never `ignoreExtraKeys()`.
- **Default to a description, not a vendor.** If a value names a third party, ask whether the
  default could be true of every deployment instead.
- **Do not `validate()` a node that normally holds an env placeholder.** `%env(...)%` has no value
  at compile time. Validate the values a human types (a url template), and handle a missing secret
  honestly at runtime instead.
- **Reading your own config during `prepend()`** is possible but not handed to you: build the tree
  and run `new Processor()->process($tree->buildTree(), $builder->getExtensionConfig($alias))`.

---

## 3. The module seam

A module declares itself to the host by implementing `ModuleProviderInterface` and tagging the
service `uhifadhi.module`. That is the entire registration protocol — no host code changes, no
central list to edit.

```php
use UhifadhiLabs\ModuleContracts\ModuleProviderInterface;
use UhifadhiLabs\ModuleContracts\ModuleProviderTrait;

final class SightingsModuleProvider implements ModuleProviderInterface
{
    use ModuleProviderTrait; // defaults for everything optional

    public function __construct(private readonly string $category) {}

    public function slug(): string        { return 'sightings'; }
    public function name(): string        { return 'Sightings'; }
    public function category(): string    { return $this->category; }
    public function icon(): string        { return 'binoculars'; }  // a Lucide name
    public function entryRoute(): ?string { return 'sightings_area'; }
}
```

And in `loadExtension()`:

```php
$services->set('sightings.module_provider', SightingsModuleProvider::class)
    ->args([$category])
    ->tag('uhifadhi.module');
```

### One bundle, one module

By convention a bundle provides exactly **one** module, named after itself. Whatever lives *inside*
the module — Sightings' surveys, its species list, its exports — is the module's own concern and
never appears in this contract. If you find yourself wanting two providers, you probably want two
bundles.

### The catalogue and per-area install

The host keeps a catalogue of modules and, separately, a per-area record of which are switched on.
Its seed command reads every tagged provider and upserts a catalogue row by `slug()`; then it
backfills each area with any module it does not yet have. The seed is idempotent and
**create-only** for the per-area rows: re-running it never touches an area's existing on/off state
or ordering. Uninstalling your bundle stops it being seeded.

Two fields the host coerces rather than trusts: `category()` and `status()` are matched against the
host's own enums, and anything unrecognised falls back to a safe default. A typo in your module
cannot break the seed for everyone else — but it also will not be reported to you, so check the
tile.

### Core vs installable

`core()` decides only the **initial per-area state**:

| | `core()` | Seeded per area as | For |
|---|---|---|---|
| Installable (default) | `false` | parked — an admin switches it on | a capability an area may not want |
| Core | `true` | active | platform machinery other surfaces already depend on |

Say `true` only when a host without you would not have *fewer features* but *broken screens* — the
map platform is the first module in the platform to qualify, because patrol plates, incident
plates, the area overview and the zones editor all import its assets.

**Be clear about what core does not mean.** It does not lock the module on: the host's Customize
page can still switch a core module off for an area, and doing so unloads nothing and takes no
assets away. It is a default, and only a default. That is the honest state of the seam today;
enforcement, if it is ever wanted, is a later ruling.

### Permissions

A module **declares** permissions; it never grants them:

```php
public function permissions(): array
{
    return [new ModulePermission('sightings.verify', 'Sightings', 'Verify')];
}
```

The host folds declarations into its permission matrix so an admin can assign them to positions,
and they disappear when the module is uninstalled. A declaration carries no role and no default
holders — **installing a module must never hand an existing user a new power**. Enforcement stays
clean in both directions: you check the value at your own routes, the host alone decides who holds
it.

### Rendering: generic page or your own

`entryRoute()` returning `null` means the host renders your module through its generic module page.
Returning a route name means you own your pages, and the host links to it with the area's uuid:
`path(entryRoute(), {uuid: area.uuid})`. Your route therefore has to accept a `uuid` parameter and
resolve the area itself.