# CLAUDE.md

This repository is a fork of `ccmbenchmark/ting_bundle` (upstream abandoned), published as `xavierleune/ting_bundle`.
PHP namespaces stay `CCMBenchmark\TingBundle` for backward compatibility: do not rename them.

## Tests

PHPUnit (`^11.5 || ^12.0`, 11.5 is required for PHP 8.2): run `composer test`. Tests live in `tests/Unit` and extend
`CCMBenchmark\TingBundle\Tests\Support\TestCase`.

## Copyright and license headers (Apache 2.0)

- Every PHP file carries the Apache 2.0 header block used across the repository.
- Never remove or alter existing `CCM Benchmark Group` copyright lines, in source headers or in `NOTICE`.
- Code coming from the upstream repository belongs to CCM Benchmark Group (including code written there by Xavier Leune):
  `* Copyright (C) <year of creation> CCM Benchmark Group. (http://www.ccmbenchmark.com)`
- When significantly modifying an existing file, add a line below the existing copyright:
  `* Copyright (C) <year> Xavier Leune`
- New files carry only `Copyright (C) <year> Xavier Leune`.

## Commits

Never add a `Co-Authored-By` trailer.
