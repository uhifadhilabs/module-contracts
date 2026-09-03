# Building a uhifadhi module

This guide is for someone **outside** the uhifadhi team building a module against the public
contracts. It is written from the extractions the platform's own modules went through, so where
the platform has a rough edge the guide says so rather than describing the version we wish
existed.

The running example is a fictional **Sightings** module — wildlife observations recorded in an
area — because a made-up module can be shown end to end without pretending a real deployment
works some particular way.

New to the word "contract" as this platform uses it? Read
**[What a contract is](what-is-a-contract.md)** first — it is one page, and everything in
chapter 3 assumes it.

**Reading the examples.** Every code block opens with a comment naming the file it belongs in,
relative to the root of whatever it belongs to — your bundle unless the comment says otherwise.
Where a block belongs somewhere else, the comment says which: `(the host application)`,
`(your recipe repository)`. Files that do not exist yet are named the same way, because the path
is the instruction: it says where you create it.

## Contents

- [1. Naming](#1-naming)
- [2. The scaffold](#2-the-scaffold)
- [3. The module seam](#3-the-module-seam)
- [4. Shipping importmap assets from a bundle](#4-shipping-importmap-assets-from-a-bundle)
- [5. Templates and Twig namespaces](#5-templates-and-twig-namespaces)
- [6. Contributing to the overview](#6-contributing-to-the-overview)
- [7. Testing](#7-testing)
- [8. CI](#8-ci)
- [9. Flex recipe and activation](#9-flex-recipe-and-activation)

---

## 1. Naming

A capability is a **module** to users and a **bundle** to Symfony. Each word stays in its own
layer, and the namespace names the domain and argues with nobody.

| Layer | Rule | Sightings |
|---|---|---|
| Composer name (uhifadhi-exclusive) | `<vendor>/<name>-module` | `your-vendor/sightings-module` |
| Composer name (generic Symfony package) | `<vendor>/<name>-bundle` | — |
| PHP namespace | `<Vendor>\<Domain>\` — the composer vendor, then the DOMAIN, no meta-word | `YourVendor\Sightings\Entity\Sighting` |
| Bundle class (the one Symfony plug) | `<Vendor><Domain>Bundle` | `YourVendor\Sightings\YourVendorSightingsBundle` |
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
- **`extensionAlias` must be set explicitly.** Without it, `YourVendorSightingsBundle` derives the
  alias `your_vendor_sightings`, and your users write `your_vendor_sightings:` in YAML forever. Set
  `protected string $extensionAlias = 'sightings';`.

Platform machinery follows the same rule even when it contributes no catalogue tile of its own.

### Name a package for what it does

**Capability modules take domain nouns** (`patrol`, `incident`, `roster`, `sightings`), and
**platform packages take software nouns** for what they actually do.

Metaphors are not names. A word that says what a package is *like* leaves a reader guessing what
it *is*, and on a conservation platform that guess is expensive: `Uhifadhi\Canopy\` could
plausibly draw pages or store foliage surveys, and nothing in the name settles it. Your own module
is almost certainly a domain noun already — the rule mostly bites when naming infrastructure,
which is where the temptation to be poetic is strongest.

The platform's own names make the architecture a sentence:

> **A module registers with the seam and renders in the shell.**

Three nouns recur throughout this guide, and each is one package: the **skeleton**
(`uhifadhi/uhifadhi`) that an installation is created from, the **seam**
(`uhifadhi/seam-module`) that every module registers with, and the **shell**
(`uhifadhi/shell-module`) that every module renders into.

**About `your-vendor`.** Everything else in this guide is the real uhifadhi world — the host is
uhifadhi, the seam tags are the tags, the host classes you bind to are the classes. The one
placeholder is the example module's *own* identity: replace `your-vendor` with whatever vendor
you publish under, in the package name, the PHP namespace and the bundle class alike. It is left
blank on purpose, because official modules ship as `uhifadhi/*` and a guide that told you to
publish there would be teaching you to squat a namespace that is not yours.

The first-party modules use the composer vendor `uhifadhi/` (`uhifadhi/patrol-module`,
`uhifadhi/module-contracts`, …); the repositories they are published from live under
`github.com/uhifadhilabs`. A composer vendor and a GitHub organisation are separate
namespaces and need not match — yours need match neither.

**The PHP namespace does follow the composer vendor, though**, and first-party code spells it out:
vendor `uhifadhi` ↔ namespace `Uhifadhi\<Domain>\` ↔ class `Uhifadhi<Domain>Bundle`. So
`uhifadhi/seam-module` is `Uhifadhi\Seam\` and `Uhifadhi\Seam\UhifadhiSeamBundle`;
`uhifadhi/patrol-module` is `Uhifadhi\Patrol\` and `Uhifadhi\Patrol\UhifadhiPatrolBundle`. The
GitHub organisation is not in that chain — `UhifadhiLabs\…` names nothing.

**And the application is `App\`.** A project installed from the
[uhifadhi skeleton](https://github.com/uhifadhilabs/uhifadhi) is a stock Symfony application with
the stock root, which is exactly the point: `Uhifadhi\` is reserved for platform packages, so a
class under `Uhifadhi\` is always somebody's bundle and never the host you installed it into. Bind to
`App\Entity\…` in your own examples, and see [Stubs vs contracts](#stubs-vs-contracts) for the one
place a module is allowed to write a host FQCN itself.

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
│   ├── YourVendorSightingsBundle.php
│   ├── DependencyInjection/SightingsConfiguration.php
│   ├── Entity/ Repository/ Service/ Controller/
│   └── Module/SightingsModuleProvider.php
├── templates/
└── tests/{Unit,Integration,Functional}/
```

### The folder convention

Folders under `src/` are named after **technical kinds** — `Entity`, `Repository`, `Service`,
`Controller`, `Enum`, `Command`, `Model`, `DependencyInjection`. Flat, one level, the same names
Symfony itself uses. Two of them are worth being precise about, because the line between them is
where things get misfiled.

**`src/Entity/` is what the database remembers.** These are your Doctrine-mapped classes: they have
identity, they have a persistence lifecycle, migrations track their shape, repositories query them
and the ORM hydrates them. The folder is about the contract with persistence, not about being a
"business object" — which is why an *interface* that participates in that contract belongs here
too. The seam's `AreaInterface` is the example: it holds no data of its own, but a host resolves
it to a real entity through `resolve_target_entities`, and Doctrine maps an association straight at
it. It is part of the persistence contract, so it lives with the persistence contract.

**`src/Model/` is what your code thinks in, but never persists.** Plain value objects and
read-shapes: a row a service assembles for a template, a small immutable thing you pass around
instead of an array. No ORM mapping, no repository, no migration. They are born inside a service,
live for one request, and die.

The practical test, when you are not sure: **would deleting the database lose it?** If yes, it is an
Entity. If no — because the next request recomputes it from whatever *is* stored — it is a Model.

### `src/Domain/` is banned

Not because the name is ugly, but because it is a different **philosophy**, not different content.
A `Domain/` folder groups classes *vertically*, by business concept, and holds exactly the same
classes the horizontal folders hold — just sorted the other way. Running both is double bookkeeping,
and running only the vertical one puts you in an argument, in every module, about where the seams
between concepts fall.

This product ran that experiment. It was organised into bounded contexts, and that structure was
retired in August 2026 in favour of the flat host plus module bundles you are reading about now. The
ruling that came out of it is the reason this section exists: **the module boundary already does the
domain-grouping job.** Your module *is* the domain folder — a whole package of it, with its own
composer.json, its own tests, its own release cadence and a hard edge that a directory name can only
suggest. Grouping by domain again inside it re-answers a question the package already answered.

> Modules are the domain folders; inside them, folders are technical kinds.

### composer.json

```json
# composer.json
{
    "name": "your-vendor/sightings-module",
    "type": "symfony-bundle",
    "require": {
        "php": ">=8.4",
        "symfony/config": "^7.3 || ^8.0",
        "symfony/dependency-injection": "^7.3 || ^8.0",
        "symfony/framework-bundle": "^7.3 || ^8.0",
        "symfony/http-kernel": "^7.3 || ^8.0",
        "uhifadhi/module-contracts": "^0.1"
    },
    "autoload": { "psr-4": { "YourVendor\\Sightings\\": "src/" } },
    "autoload-dev": { "psr-4": { "YourVendor\\Sightings\\Tests\\": "tests/" } },
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
// src/YourVendorSightingsBundle.php
final class YourVendorSightingsBundle extends AbstractBundle
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
// src/YourVendorSightingsBundle.php
if ($builder->hasExtension('doctrine')) {
    $container->extension('doctrine', ['orm' => ['mappings' => [
        'YourVendorSightings' => [
            'type' => 'attribute',
            'dir' => __DIR__.'/Entity',
            'prefix' => 'YourVendor\\Sightings\\Entity',
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
// config/services.php
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
// src/DependencyInjection/SightingsConfiguration.php
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
// src/Module/SightingsModuleProvider.php
use Uhifadhi\ModuleContracts\ModuleProviderInterface;
use Uhifadhi\ModuleContracts\ModuleProviderTrait;

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
// src/YourVendorSightingsBundle.php
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

### Base vs installable

`base()` decides only the **initial per-area state**:

| | `base()` | Seeded per area as | For |
|---|---|---|---|
| Installable (default) | `false` | parked — an admin switches it on | a capability an area may not want |
| Base | `true` | active | platform machinery other surfaces already depend on |

Say `true` only when a host without you would not have *fewer features* but *broken screens* — the
map platform is the first module in the platform to qualify, because patrol plates, incident
plates, the area overview and the zones editor all import its assets.

**The not-uhifadhi test.** A base module is one whose absence makes the installation *not
uhifadhi*. If a deployment without your module is still recognisably the product — poorer, but
the product — then it is installable, not base. Almost everything is installable. The current base
set is `map`, `team`, `widget` and `area` (the seam runtime and the shell are the platform itself,
a tier above modules and not subject to this question at all).

**Why "base" and not "core".** "Core" marks something as important without saying what it *is*, and
it is the word a codebase reaches for twice — once for the runtime at the centre, once for what
ships by default. This platform has both, so they get separate words: the runtime is the **seam**
(`uhifadhi/seam-module`, the package your provider registers with), and the always-present tier of
ordinary modules is **base**. "Base" also names the test above, which "core" never did — the floor
the product stands on, rather than a ranking of importance. The contract method was `core()` until
this was settled.

The word then means two different things at two levels, and they are worth keeping apart:

- **Deployment level.** Base modules are in the project template's requires, so they are present
  in every installation by definition. Nobody edits them out; wanting to remove one is not a
  configuration need, it is evidence the module was misclassified.
- **Area level.** Whether a base module can be hidden for a *particular* area is a separate and
  much smaller question. `base()` seeds it active rather than parked, and beyond that the seam
  does not currently enforce anything — the Customize page will still switch it off, and doing so
  unloads nothing and takes no assets away. Whether it *should* be allowed to is an open host
  ruling, not a settled rule this guide can hand you.

So `base()` is a default about seeding, and the not-uhifadhi test is the question you answer
before setting it.

### Permissions

A module **declares** permissions; it never grants them:

```php
// src/Module/SightingsModuleProvider.php
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
---

## 4. Shipping importmap assets from a bundle

This is the hard chapter. Everything in it comes from moving three shared scripts out of the host
application and into a bundle without a single importer changing.

### The two directories, and what each is for

| Directory | Registered | Logical path | Use for |
|---|---|---|---|
| `public/` | automatically | `bundles/yourvendorsightings/…` | stylesheets, images, **classic `<script>` files** |
| `assets/` | you prepend it | `@your-vendor/sightings-module/…` | anything imported as an ES module |

`public/` needs no configuration at all: AssetMapper registers every bundle's `public/` directory
under `bundles/<lowercased bundle class name minus "Bundle">` and content-versions what is in it.
No `assets:install`, no symlink. Relative `url()`s inside a CSS file there are rewritten, so a
vendor stylesheet's own images come along for free.

`assets/` is yours to declare, in `prependExtension()`, the way `symfony/ux-turbo` does:

```php
// src/YourVendorSightingsBundle.php
if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
    $container->extension('framework', ['asset_mapper' => ['paths' => [
        \dirname(__DIR__).'/assets' => '@your-vendor/sightings-module',
    ]]]);
}
```

Guard it on **both** conditions. `hasExtension('framework')` because you may be in a kernel that
has none; `interface_exists(AssetMapperInterface::class)` because AssetMapper is optional and a
host may have installed you for your PHP alone.

### The gotcha: a bundle cannot add importmap entries

There is no extension point. `importmap.php` is read as one file, and nothing a bundle does can
contribute an entry to it. So the contract splits in two:

- the **directory** is yours — you guarantee `@your-vendor/sightings-module/plate.js` exists and keeps
  working;
- the **import names** are three lines in the host's `importmap.php`:

```php
// importmap.php (the host application)
'sightings/plate' => ['path' => '@your-vendor/sightings-module/plate.js'],
```

Document those lines in your README, and ship them in a Flex recipe (chapter 9) so an install
writes them. The recipe hides the seam; it does not remove it.

### Name your exports as bare specifiers, not paths

This is the single most valuable habit in the chapter. When the platform's map scripts lived in the
host application they were still imported as `uhifadhi/basemaps`, never as `./assets/…`. The day
they moved into a bundle, **every importer in three repositories kept working unchanged** — only
the right-hand side of the importmap entry moved. Had they been imported by path, the extraction
would have touched every map controller in the product.

Write a test for it. A one-line sweep over your controllers asserting that none of them contains
your own namespace string is enough to keep the property true.

### Classic scripts stay in `public/`

If a library publishes a global (`window.L` and friends) it is not an importmap module, and putting
it in `assets/` gains nothing. Keep it in `public/` and link it from a layout. Publish the path as a
**class constant** rather than expecting hosts to type it:

```php
// src/YourVendorSightingsBundle.php
public const string LEAFLET_JS = 'bundles/yourvendorsightings/leaflet/leaflet.js';
```

```twig
{# templates/sightings/_base.html.twig #}
<script src="{{ asset(constant('YourVendor\\Sightings\\YourVendorSightingsBundle::LEAFLET_JS')) }}"></script>
```

A path written in a host layout *and* two other bundles' base templates is a path that eventually
differs by one character in one of them.

### Publishing configuration to the browser

Where a script needs a value the server knows — which tile provider, which feature flags — put it
on the `<body>` as one JSON data attribute rendered by a Twig function your bundle registers, and
have the script read it with a sane default when the attribute is absent:

```php
// src/Twig/SightingsExtension.php
new TwigFunction('sightings_attributes', $this->attributes(...), ['is_safe' => ['html']]);
```

Two things this earns you: the value is available before the first render rather than after a
round trip, and one document has exactly one place to read it from, so two components cannot
disagree. Two things to get right: `json_encode()` does **not** escape `"` by default, so escape
the payload yourself with `htmlspecialchars(..., ENT_QUOTES)` before marking it `is_safe` — and
emit **only** the keys the current configuration actually needs, so a secret is never printed on a
page that was not going to use it.

### Asset-idiom tests

Some defects live in JavaScript that no PHP test can execute — a cache that only remembers success,
a script that reaches for a vendor endpoint directly. If you have no JS runner, read the shipped
asset as **text** and assert on it. It is cruder than a unit test and it catches the real thing.

Keep those tests **with the asset**. When a script moves to another repository its test moves with
it, because a defect of that file must fail where someone would edit it — not two repositories away.

---

## 5. Templates and Twig namespaces

A bundle's `templates/` directory is registered automatically under a namespace derived from the
bundle class: `@YourVendorSightings/…`. Nothing to configure.

Give your bundle its **own base template** that extends the host's layout, rather than having every
page extend `layout.html.twig` directly:

```twig
{# templates/base.html.twig #}
{% extends 'layout.html.twig' %}

{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset(constant('YourVendor\\Sightings\\YourVendorSightingsBundle::STYLESHEET')) }}">
{% endblock %}
```

That way your stylesheet loads only where your module renders, and the host's stylesheet never
mentions you.

A rule the platform learned the hard way, worth inheriting: **the host's stylesheet is loaded on
your pages too.** A bare one-word class in either sheet (`.who`, `.day`, `.feed`) will eventually
collide across the repository boundary and the cascade decides who wins by load order. Qualify your
selectors with an element or a prefix, and never write a second copy of a vocabulary the host
already defines — two copies loaded in either order render differently, which is exactly what the
"same layer renders identically everywhere" rule forbids.

If your module contributes markup to a host-rendered surface, expose the stylesheet path through
`ContributesStylesheetInterface::stylesheet()` so the host can link the same sheet from a page you
do not render.

---

## 6. Contributing to the overview

An area's overview is composed from every installed module. Each contribution is a small interface
plus an explicit tag; you can implement any subset, and a module that implements none is perfectly
valid.

| Interface | Tag | Contributes |
|---|---|---|
| `OverviewContributorInterface` | `uhifadhi.overview.widget_provider` | widgets and their render context |
| `NowTileProviderInterface` | `uhifadhi.overview.now_tile` | "right now" tiles in the strip |
| `AttentionProviderInterface` | `uhifadhi.overview.attention` | items in the attention list |
| `MapLayerProviderInterface` | `uhifadhi.map.layer` | layers on the area map |
| `PulseProviderInterface` | `uhifadhi.overview.pulse` | events in the activity feed |
| `OverviewCopyProviderInterface` | `uhifadhi.overview.copy` | copy fragments for a named slot |
| `DepartmentKpiProviderInterface` | `uhifadhi.department_kpi` | a department's KPI figures |

Every one of them starts with `moduleSlug()`, and it must return the same slug your
`ModuleProviderInterface` does: that is how a contribution disappears when an area switches your
module off.

Three rules that keep this seam honest:

- **Tag explicitly, at both ends.** The tag names are published as `TAG` constants on the
  interfaces; use them. Your services are not autoconfigured, so nothing applies these for you.
- **Per-module behaviour lives in the module.** Adding a module means adding tagged provider
  classes — never editing the host's generic controller or services to special-case you. If you
  find yourself wanting to, the seam is missing something; say so rather than reaching across.
- **Ship a legend with a layer.** Every map layer a module contributes carries its own legend, and
  the same layer must render identically wherever it is drawn. A layer with a private palette is a
  layer that will read differently on two screens.

---

## 7. Testing

Tests first — the platform's modules are built that way and the seams assume it.

The pyramid, in the order a failure should reach you:

- **Unit.** The config tree (with a plain `Processor`), value objects, any pure mapping. No
  container, no database, fast.
- **Integration.** A `TestKernel` boots a real container with your bundle in it. Everything else in
  the repository rides on this.
- **Functional.** A real request against a real database, for screens.

### The TestKernel pattern

```php
// tests/TestKernel.php
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new YourVendorSightingsBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, /* … */]);
        $container->extension('doctrine', ['dbal' => ['url' => '%env(SIGHTINGS_TEST_DATABASE_URL)%']]);

        // Stand in for the host's catalogue: tagged services are private, so a
        // collector is what makes your contribution observable at all.
        $container->services()
            ->set(CollectedModules::class)
            ->args([tagged_iterator('uhifadhi.module')])
            ->public();
    }

    public function getCacheDir(): string { return sys_get_temp_dir().'/sightings-tests/cache'; }
}
```

Point `KERNEL_CLASS` at it from `phpunit.dist.xml`.

Two practical notes. **A private service cannot be fetched from a test** — expose a public alias or
a collector fixture for exactly the things you need to observe, and nothing more. And **pop the
error handler in `tearDown()`**: the framework's debug handler is registered during a kernel test
and never popped, which PHPUnit reports as risky.

```php
// tests/Integration/SightingsKernelTestCase.php
while (true) {
    $previous = set_exception_handler(static fn () => null);
    restore_exception_handler();
    if (null === $previous) { break; }
    restore_exception_handler();
}
```

### Standing in for the host

Your bundle binds to host classes it does not depend on (`Uhifadhi\Entity\AreaOfInterest`, the
widget framework). Do **not** require the host. Put minimal stand-ins under
`tests/Fixtures/Uhifadhi/…` and map them in `autoload-dev`:

```json
# composer.json
"autoload-dev": {
    "psr-4": {
        "YourVendor\\Sightings\\Tests\\": "tests/",
        "Uhifadhi\\Model\\": "tests/Fixtures/Uhifadhi/Model/"
    }
}
```

This is also why `class_exists()` is the wrong guard for a *bundle*: in your own test run those
fixture classes are autoloadable whether or not any host is present. Check `kernel.bundles` when
you mean "is this bundle in the kernel", and `class_exists()` only when you mean "did the host
application define this class".

### Stubs vs contracts

A stand-in of that kind is a **stub**, and a stub is a specific, disciplined thing: a class that
**impersonates a real owner's FQCN byte-for-byte**. Not "something like the host's area" — the host's
area, same namespace, same class name, so the seam your test exercises is the seam a real
installation exercises. Anything less and the test proves your fixture works.

Because a stub is a lie told on purpose, it is marked three ways, and all three are required:

1. **By location.** It lives under `tests/Fixtures/<the impersonated tree>` — `tests/Fixtures/Uhifadhi/Entity/AreaOfInterest.php`, `tests/Fixtures/Uhifadhi/Module/DepartmentKpi.php`. The path
   repeats the namespace, so the file's own directory names its victim.
2. **By `autoload-dev`.** The mapping is dev-only, so a stub can never load in an application. If
   the host is present, its real class is on the production autoloader and wins; if it is not, the
   stub is only ever visible to your suite.
3. **By docblock.** One paragraph, at the top: whose class this is, that it is a stub, and what the
   real one is. The next person to read it is debugging something else.

**They are debt, and the interest is real.** A stub is only as true as the day it was copied — the
owner refactors, your suite stays green, and the first thing that notices is an installation. So
stubs are retired **release by release**, replaced by an interface the owner publishes and the
consumer binds to instead.

`AreaInterface` is the model of the finished move. The seam needs the host's area and does not have
one, so it publishes `Uhifadhi\Seam\Entity\AreaInterface` — `getId()` and nothing else — maps its
own association to that, and lets the host resolve it with
`doctrine.orm.resolve_target_entities`. What used to be an impersonation of a host class is now a
contract the host satisfies; the stub that remains
(`tests/Integration/Fixtures/Uhifadhi/Entity/AreaOfInterest.php`) exists only to play the *host* end
of that resolution, which is the honest job of a stub.

Patrol's `tests/Fixtures/Uhifadhi/Module/DepartmentKpi.php` is the same shape one step earlier: a
host value object patrol builds and hands back, impersonated so the suite can build one, and waiting
for the release that publishes it as a contract. When you find yourself writing a stub, write down
which of those two states it is in.

**One consequence for renames.** A stub does not follow its own bundle. The seam's code has been
renamed twice — `UhifadhiLabs\Trunk\` → `Uhifadhi\Trunk\` → `Uhifadhi\Seam\` — and through both
the area stub stayed exactly where it was, at `Uhifadhi\Entity\…`, because it belongs to the class
it impersonates and that class never moved.

This is the rule that a find-and-replace breaks silently. Sweep a stub up with the rest and nothing
fails: the suite still passes, because a stub the bundle also renamed is just a class the bundle is
talking to itself with. It has stopped impersonating anything, and it will keep passing right up
until an installation disagrees. When you rename, the stubs are the files you skip on purpose.

### The test-database convention

One database per bundle, named `<slug>_bundle_test`, addressed by a bundle-specific env var so two
suites never collide:

```xml
<!-- phpunit.dist.xml -->
<env name="SIGHTINGS_TEST_DATABASE_URL"
     value="postgresql://app:app@127.0.0.1:5434/sightings_bundle_test?serverVersion=17&amp;charset=utf8"/>
```

If your bundle owns no entities, say so in a comment where the URL would have been and ship no
database at all — an absence that is explained is not an omission.

---

## 8. CI

One job, the same command a developer runs:

```yaml
# .github/workflows/ci.yml
on:
  push: { branches: [main] }
  pull_request:

jobs:
  check:
    strategy:
      matrix: { php: ['8.4', '8.5'] }
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '${{ matrix.php }}', coverage: none, tools: 'composer:v2' }
      - run: composer validate --strict
      - run: composer update --no-interaction --no-progress --prefer-dist
      - run: composer cs:check
      - run: composer phpstan
      - run: composer test
```

`composer update`, not `install`: a library should be tested against the dependency versions its
constraints actually allow, and the lock file is not committed. Test the **whole** supported PHP
range. If your suite needs a database, add a Postgres (or PostGIS) service and point your test env
var at it.

---

## 9. Flex recipe and activation

Installing a module should not be a checklist. A Flex recipe turns it into `composer require`.

`manifest.json` in a recipe repository:

```json
# <vendor>/<package>/<version>/manifest.json (your recipe repository)
{
    "bundles": { "YourVendor\\Sightings\\YourVendorSightingsBundle": ["all"] },
    "copy-from-recipe": { "config/": "%CONFIG_DIR%/" },
    "env": { "SIGHTINGS_API_KEY": "" }
}
```

with `config/packages/sightings.yaml` alongside it:

```yaml
# <vendor>/<package>/<version>/config/packages/sightings.yaml (your recipe repository)
sightings:
    module_category: biodiversity

when@dev:
    sightings:
        dev_tools: true
```

`dev_tools` is a convention worth copying: gate seeders, fixtures and anything that writes invented
data behind a flag the recipe enables only for `dev` and `test`, so production never grows a command
that fabricates records.

**What a recipe cannot do:** it copies files and registers bundles, but it will not merge new lines
into an existing `importmap.php`. If you ship importmap assets, the three entries from chapter 4 are
the one manual step, and your README has to say so.

Nor can it write a line that depends on names only the host knows. If your bundle maps an
association to an interface for the host to resolve (chapter 3), the recipe cannot fill in the host's
class — so ship the `doctrine.orm.resolve_target_entities` block **as a comment** in your
`config/packages/<alias>.yaml`, next to the reason, and make sure the bundle boots without it.

Then say honestly how far the host gets before it writes that line, and **test the answer** rather
than assuming it. "Boots without it" and "works without it" are different claims, and the seam's
recipe asserted the second for months while only the first was true — see
[the chokepoints](#three-chokepoints-between-composer-require-and-a-schema) below.

### The host's half of a recipe-owned file

Where the host does have to add a block, the file becomes two authors' work, and Flex has an opinion
about that: `composer recipes:install <pkg> --force` **overwrites** recipe-owned files with the
recipe's version, then tells you to sort it out with `git diff` and `git checkout -p`.

So put the host's addition in one contiguous block at the **end** of the file, under a marker saying
which half is which, and never interleave it with the recipe's text. Restoring one hunk is a review;
restoring an interleaved diff is an excavation, and the people doing it will be doing it on a day
they were trying to do something else. Tell your users this in the file itself.

### Reading what the recipe did

A recipe writes into the installing project's working tree, never into `vendor/`. Flex's closing
*"these files are yours"* line means exactly that: the copied files belong to the host now, land in
the host's history and are edited freely. So the receipt for an install is `git diff` — run it
before anything else and every line the recipe added is in front of you.

After that the ledger is `composer recipes`, which lists every installed recipe and flags the ones
with a newer version available. Naming one shows the detail:

```console
$ composer recipes uhifadhi/map-module
```

It reports the recipe version that was applied, the files it installed, and whether a newer recipe
exists. When one does, `composer recipes:update uhifadhi/map-module` patch-merges that newer version
into the project — it keeps the host's own edits where it can, rather than replacing the file
outright the way `recipes:install --force` does.

A recipe may also ship a `post-install.txt` beside its `manifest.json` in the recipe's version
directory. Flex prints it once, when the install finishes: the polite place for a module to state
what it wrote and which steps are still the operator's.

### Three chokepoints between `composer require` and a schema

These come from a real `composer create-project` of the uhifadhi skeleton, followed exactly as
written. They are here because each one is a shape any module can repeat, not because they are the
seam's particular bugs.

**1. Shipping tables without shipping the tool that creates them.** The seam contributed two
entities and required `doctrine/doctrine-bundle` and the ORM, so it looked complete. It was not: an
installed project's `bin/console list doctrine` offered `doctrine:schema:*` and no
`doctrine:migrations:*` at all, because nothing in the chain required
`doctrine/doctrine-migrations-bundle`. The ritual fell back to `schema:create`, which is the command
every deployment guide tells you never to use, and the fallback looked like a working install.

The rule: **if your bundle contributes a table, require the migration bundle.** Owning tables is
owning the need for the tool that creates them, exactly as it is owning the need for the ORM that
maps them.

The other half of that rule: **do not ship migration versions.** The tables are yours; the migration
history belongs to the installation. A vendor replaying its own versions into a host's history has
to be diffed around forever afterwards. `doctrine:migrations:diff` on the host is the honest seam.

**2. Claiming the host's resolution step is optional when it is not.** The seam's recipe said an
installation without `resolve_target_entities` simply went without the per-area half. It goes
without a schema. The association is `NOT NULL`, so everything that walks the metadata stops:

```console
$ bin/console doctrine:schema:create
In MappingException.php line 72:
  Class 'Uhifadhi\Seam\Entity\AreaInterface' does not exist
```

Booting still works, and that part is worth keeping true — a host between `composer require` and its
first entity must not be in a broken state. But "boots" was being sold as "installs".

The choice was between making the claim true and making the documentation true. A bundle can make it
true by shipping a minimal concrete target its recipe wires by default, and that was **rejected**:
it would have the seam define an area model, which is the one thing its charter says belongs to the
host, and it would put a stray table in every installation that never noticed — payable later as a
real migration on the day the host maps its own. So the documentation moved instead. The recipe now
says *required*, quotes the error verbatim so the answer is in the file the error is about, and
ships the whole step — the entity to write as well as the block to uncomment.

Generalise it as: **if your bundle cannot be schema'd until the host does something, that something
is part of installing your bundle.** Put it in the recipe comment, the README and a test.

**3. An application that squats a vendor namespace, and the mapping prefix that pays for it.**
doctrine-bundle's Flex recipe writes its `mappings` block for the `symfony/skeleton`, whose PSR-4
root is `App\`. An application that gives itself a different root — `Uhifadhi\`, say — therefore has
`src/Entity` mapped under a prefix nothing in it is in, and the failure arrives late and reads like
the developer's mistake:

```console
The class 'Uhifadhi\Entity\AreaOfInterest' was not found in the chain
configured namespaces App\Entity, Uhifadhi\Seam\Entity
```

The obvious fix is to correct the prefix in `config/packages/doctrine.yaml` and comment why it
differs from the recipe it came from. That works and is still the wrong fix, because it treats a
symptom of the actual mistake: **the application has taken the platform's namespace.** `Uhifadhi\`
is the composer vendor's, and the seam, the modules and every future first-party package are under
it. An application that also lives there makes "is this class the host's or a
bundle's?" unanswerable by reading it — a question a stub, a boundary test and a doctrine prefix all
have to answer.

So the namespace stays with the platform: a project installed from the skeleton is stock-Symfony
`App\`, `Uhifadhi\` means platform code, and the doctrine-bundle recipe's own prefix is correct with
nothing to override. The skeleton still ships the mapping block rather than leaving it to the
recipe, for the reason the symptom-fix has right: a skeleton is copied once and never updated, so a
line missing there is missing from every future installation.

Two rules follow. **Do not put your application in a vendor's namespace** — the convention costs
nothing and buys a permanent answer to "whose class is this". And **in examples for a host you do
not control, use an obvious placeholder** (`<YourRoot>\Entity\…`) rather than a
concrete root: a placeholder gets substituted, a plausible-looking one gets pasted.

**A fourth, smaller one, for anyone who keeps a licence header on `config/bundles.php`:** the bundle
configurator regenerates that file wholesale on every `composer recipes:install --force` and drops
everything above `return [` — header, `declare(strict_types=1)`, all of it. Verified again on the
create-project that proved this rename. It is a recipe-owned file with a hand-edited preamble, which
is the same two-authors problem as above; check it after any recipe reinstall, and restore the
preamble in the same commit rather than noticing three commits later.

### Check the endpoint actually answers

A private recipe endpoint fails in the one way that looks like success. Flex asks for the index,
gets a 404, falls back to an **auto-generated** recipe, and registers your bundle from its
`"type": "symfony-bundle"` — so the install looks fine and everything the recipe ships beyond the
bundle line silently never arrives. This went unnoticed across every module in this project until an
install was audited end to end.

The cause is host-based credentials. Composer attaches its GitHub token per host, and
`raw.githubusercontent.com` is not `github.com` — so an endpoint on the raw domain is fetched
anonymously and a private repository answers 404. Use the API's contents URL, which composer does
authenticate:

```jsonc
// composer.json (the host application)
"extra": {
    "symfony": {
        "endpoint": [
            "https://api.github.com/repos/<org>/recipes/contents/index.json?ref=flex/main",
            "flex://defaults"
        ]
    }
}
```

Verify rather than assume — `composer recipes` names the source of every recipe it applied:

```console
$ composer recipes
 * your-vendor/sightings-module (recipe not installed)   # endpoint reachable, recipe pending
```

A package that is silently missing from that listing, or a `composer require` that says
`From auto-generated recipe`, means the endpoint is not being reached.

### What a real install teaches that a test suite does not

Each of these was found by installing into a project created from the skeleton
(`uhifadhi/uhifadhi`) and serving a page, rather than by any bundle's own green suite.

**Your dependency constraints meet reality at the first host, not at your CI.** A bundle whose
suite runs `composer update` always resolves the newest of everything and never discovers that it
pinned a major nobody else is on. The shell asked for `symfony/ux-icons ^2.20`, the application was
on `^3.4`, and the two were simply uninstallable together — a thirty-second fix, found only because
something tried to install both. Widen a constraint to every major you actually work on, and prove
it by running your suite against each.

**Anything your markup needs at render time must ship with you or degrade.** A bundle that names
icons from a set the host happens to have installed renders holes on a host that does not, and one
that fetches them on demand needs a network at build time. If your bundle's own chrome draws four
glyphs, ship those four and register them under a prefix of your own in `prependExtension()` — never
by extending a set the host also uses, which would make your bundle decide what the host's icon
names mean.

**A bundle cannot contribute an importmap entry, so its markup and its behaviour separate.** If your
templates carry `data-controller` attributes, the Stimulus controllers behind them are the host's to
add (chapter 4). Write the markup so that a missing controller is inert rather than broken, and say
in your README which names you emit — an installation without them should render correctly and
simply not animate.

**Dependencies bring their own recipes, and those recipes ship files.** Requiring one bundle can
install several: pulling in the shell (`uhifadhi/shell-module`) brings `symfony/twig-bundle` and
`symfony/ux-icons`, and the stock Twig recipe writes a `templates/base.html.twig` that competes with
whatever frame your bundle expects pages to extend. Read what an install actually wrote before
committing it.

**A knob that gates nothing is worse than a missing feature.** Writing this chapter's config file is
where a reserved-for-later flag gets its promise spelled out for a stranger — and where it becomes
obvious that nothing reads it. Ship the keys your bundle genuinely acts on; delete the rest.

### Unreleased packages need a stability floor

While the modules are on `dev-main` and untagged, a host requiring one also needs
`"minimum-stability": "dev"` with `"prefer-stable": true`. Composer will not resolve a **transitive**
dev dependency under a stable floor: requiring `uhifadhi/seam-module` fails asking you to name a
version for `uhifadhi/module-contracts`, a package you never asked for. Root-requiring your
dependency's dependencies is not a workaround, it is a trap for the next person. This goes away the
day the packages carry tags.

Packages outside packagist also need their own `repositories` entry in the host — composer does not
inherit repositories from a dependency, so a private or VCS-hosted transitive dependency must be
named at the root as well.

### After installing

```bash
bin/console doctrine:migrations:diff      # if your module added tables
bin/console doctrine:migrations:migrate
bin/console seam:catalogue:seed          # idempotent; your module joins the catalogue
```

The `diff` is the host's, not yours — see chokepoint 1 above for why a bundle that owns tables
requires the migration bundle and ships no versions.

The seed command is namespaced to the bundle that owns it, `uhifadhi/seam-module`, and keeps
`app:seed:catalogue` as an alias because that string is written into deploy pipelines.

It is safe to run on production and safe to run twice. Your module's catalogue row is refreshed from
your provider every time — rename your module between releases and the catalogue follows, because it
upserts by slug. Each area's on/off state and ordering is created once and never revisited, so a
deploy cannot overrule an admin who parked your module, and uninstalling your bundle leaves every
area's history where it was rather than deleting it.

Then switch the module on for an area from the Customize page — unless you declared `base()`, in
which case it is already on.

**Zero modules is a working installation**, and it is worth knowing what that looks like, because it
is the state your module arrives into:

```console
$ bin/console seam:catalogue:seed
 [OK] Catalogue reconciled: 0 module(s) from 0 installed provider(s); 0 area assignment(s) backfilled.
```
