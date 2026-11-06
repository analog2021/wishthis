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
-   Adding a wish: details are looked up as soon as a link is pasted, with a picture preview. If the title or picture can't be found, the dialog stays open and says why (e.g. the shop blocks automatic lookups), so they can be filled in before adding
-   Prices are found on all shops which publish them (linked data, Open Graph product tags, microdata), not only on Amazon

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
-   Wishes from shops behind a bot check (e.g. Cloudflare) getting the title "Just a moment..."; wishthis now says that the details need to be added manually
-   Error pages (e.g. "Page not found") being used as wish details
-   The "Details not filled in" warning showing although the title and image were already added
-   Editing a wish's URL and clicking Save right away removing the URL
-   Pictures being left out because the image server refuses requests from servers (e.g. 429), although browsers can show them
-   The "Insufficient power" page not showing the required power
-   An endless redirect loop for URLs containing encoded characters (such as `%3C`)
-   The wishlist selection on "My lists" closing immediately when clicking the wishlist name

### Security

-   Logging out did not invalidate the session, so the old session cookie could still be used to log in
