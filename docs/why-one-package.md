# Why one package?

Why the registration seam and the data-shape contracts ship together, and the test that would
split them.

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
