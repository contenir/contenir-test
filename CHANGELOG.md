# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `Container\ArrayContainer`: a PSR-11 container over services and factories, a factory's service built once, with
  `ServiceNotFoundException` and `ServiceFailedException`.
- `Clock\FixedClock`: a PSR-20 clock stopped at one moment, with `FixedClock::at()` for a time in a timezone.
- `Log\RecordingLogger`: a PSR-3 logger keeping every record, read back by level.
- `Trait\TemporaryDirectoryTrait`: a scratch directory for each test under a umask of 022, removed afterwards with
  any read-only directories or symlinks left in it.
- `Database\SqliteSchema`: a MySQL schema dump translated for SQLite.
- `Database\SqliteDatabase` and `Trait\SqliteDatabaseTrait`: an in-memory SQLite database for each test, empty or
  holding a MySQL schema's tables, with a helper to insert rows.
