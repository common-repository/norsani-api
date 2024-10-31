=== Norsani API ===
Contributors: joun007
Requires at least: 3.3
Tested up to: 5.2.2
Requires PHP: 7.2.0
Stable tag: 1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Norsani API

== Description ==

API to connect with <a href="https://codecanyon.net/item/norsani-multivendor-food-ordering-system/22255486">Norsani</a>

You will need to use this pluign if you have build the Norsani mobile applications for your website.
Users who do not use Norsani should not use this plugin unless you are developing a Mobile app for Norsani.

<a href="https://mahmud-hamid.gitbook.io/norsani/developers/norsani-api">API Documentation for developers</a>

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress

No additional installation steps are required.
The plugin also do not have settings or any other screen in the WordPress admin it will work silently connecting with your Norsani Apps.

== Changelog ==
= 1.3 =
* Fixed vendor menu timing delay.
= 1.2 =
* Fixed issue with vendor get data if site is multi-language.
= 1.1 =
* Extended Braintree code to support drop-in.
* Fixed static order type on checkout.
= 1.0 =
* Separated total tax information from total cart value.
* Added float val for the item price for calculation on mobile.
* Added default order type on app load.
= 0.10 =
* Fixed delivery calculations on cart and chekout.
= 0.9 =
* Added compatibility with new features in Norsani.
* Fixed cart and checkout calculations.
= 0.8 =
* Updated translation files.
= 0.7 =
* Added compatibility with integrating Braintree Payments.
* Fixed error messages.
* Fixed file paths.
= 0.6 =
* Changed the API load until Norsani plugin is loaded.
* Fixed vendor type option on startup.
= 0.5 =
* Improved user input sanitizing.
= 0.4 =
* Fixed important search result error.
= 0.3 =
* Fixed order type filter function.
* Added required to the keyword parameter in search endpoint.
= 0.2 =
* Allowed order type to be null to get all vendors.
* Added json.encoded to the returned locality options data.
* Added required for the getproduct endpoint id.
* Added required for the getvendor endpoint id.
* Added required for the getorder endpoint id.
* Order types values now will be translatable.
* Modified images sizes over all endpoints to improve performance.
= 0.1 =
* First release.