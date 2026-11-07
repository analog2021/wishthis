## Unreleased

### Added

-   Textfield for public wishlist url (#220)
-   SQLite support (#67)
-   PHP 8.4 support
-   "Return to admin" while using "Login as"
-   PHP 8.5 support
-   A "Spoilers" page for wishlist owners (in the wishlist options menu), showing which wishes have been fulfilled or claimed after an "Are you sure?" confirmation. Fulfilled wishes can be marked as still needed from there
-   A "Sharing lists & fulfilling wishes" section on the home page, explaining how to remember a shared wishlist and where to find it
-   Amazon product details when adding a wish: title, description, picture and price are read from the product page
-   Amazon pictures are saved in full size (1500 pixels), even when the address of the small picture was pasted

### Changed

-   Updated translations from transifex
-   The "Insufficient power" page says "Admin permissions required - access denied." instead of showing power numbers
-   The installer no longer runs while `src/config/config.php` exists, since installing overwrites it and deletes all data
-   Adding a wish: details are looked up as soon as a link is pasted, with a picture preview. If the title or picture can't be found, the dialog stays open and says why (e.g. the shop blocks automatic lookups), so they can be filled in before adding
-   Prices are found on all shops which publish them (linked data, Open Graph product tags, microdata), not only on Amazon
-   Adding a wish fetches the shop page once and reuses what was found, instead of requesting it about seven times

### Deprecated

-   Nothing

### Removed

-   The news section on the home page
-   The "URL mismatch" dialog when adding a wish. The link is kept exactly as pasted, so options like size or colour are never replaced by the address the shop redirected wishthis's server to. This also removes the API request which changed the link of all wishes with the same URL

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
-   Wish links with several parameters (e.g. `?th=1&psc=1`) breaking: the Visit button pointed to `&amp;psc=1`, and every save in the edit dialog encoded the `&` again, so options like size or colour got lost
-   Pictures being left out because the image server refuses requests from servers (e.g. 429), although browsers can show them
-   The "Insufficient power" page not showing the required power
-   An endless redirect loop for URLs containing encoded characters (such as `%3C`)
-   The wishlist selection on "My lists" closing immediately when clicking the wishlist name
-   The installer writing the sample database credentials to `config.php` when a crawler or browser preview visited during installation
-   The installer corrupting `config.php` when a database value starts with a digit (e.g. a password like `2166...`)
-   An SQL error on the profile page with MySQL/MariaDB
-   An auto-filled picture being wrapped in quotes when editing a wish
-   Long titles being cut in the middle of a character or HTML entity (e.g. ending in `&com`)
-   The message about a shop blocking automatic lookups being titled "Success"

### Security

-   Logging out did not invalidate the session, so the old session cookie could still be used to log in
-   Any logged-in user could rename or delete other users' wishlists and add or edit wishes on them. The API now checks that the wishlist or wish belongs to the user. Only the owner may set any wish status; everybody else may only claim an available wish
-   Wishes that had been fulfilled or claimed, and their status, could be read through the API by people other than the owner
