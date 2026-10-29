## Unreleased

### Added

-   Textfield for public wishlist url (#220)
-   SQLite support (#67)
-   PHP 8.4 support

### Changed

-   Updated translations from transifex
-   The installer no longer runs while `src/config/config.php` exists, since installing overwrites it and deletes all data

### Deprecated

-   Nothing

### Removed

-   Nothing

### Fixed

-   An error when attempting to send an email (such as requesting new wishes) when the instance does not have MJML API keys configured (#215)
-   Outdated transifex project link (#231)
-   Apache RewriteRules not working (56bbc6d)
-   Installations from v1.2.3 and older being sent to the installer after updating, because their `config.php` has no `DATABASE_ENGINE`

### Security

-   Nothing
