## Unreleased

### Added

-   Textfield for public wishlist url (#220)
-   SQLite support (#67)
-   PHP 8.4 support
-   "Return to admin" while using "Login as"

### Changed

-   Updated translations from transifex
-   The "Insufficient power" page says "Admin permissions required - access denied." instead of showing power numbers
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
-   "Login as" reporting success without switching to the selected user
-   The "Insufficient power" page not showing the required power
-   An endless redirect loop for URLs containing encoded characters (such as `%3C`)

### Security

-   Logging out did not invalidate the session, so the old session cookie could still be used to log in
