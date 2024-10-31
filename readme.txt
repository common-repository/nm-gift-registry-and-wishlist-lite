=== NM Gift Registry and Wishlist Lite ===
Contributors: nmerii
Tags: wishlist, gift registry, wedding, birthday, woocommerce
Requires at least: 5.6
Tested up to: 6.6.2
Requires PHP: 7.4
Stable tag: 5.4.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

An advanced and highly customizable WOOCOMMERCE gift registry and wishlist plugin that allows you to create lists for any occasion.

== Description ==

NM Gift Registry and Wishlist for WOOCOMMERCE allows customers to create and add products to all kinds of gift registries and wishlists, from birthdays to weddings, anniversaries and other occasions. It has been built as a solid gift registry plugin but one which enhances the power of wishlists when used as such. Designed with customers in mind, It provides tools needed to help them create the perfect list, get their items bought and generate sales for the store.

= Free version features =

* Create a gift registry or wishlist.
* Allow guests to create and manage wishlists.
* Add event date, description, partner's details and other profile information to the gift registry or wishlist.
* Add shipping information to the gift registry or wishlist using WooCommerce's shipping fields to blend well with the shipping setup on your site.
* Add simple, variable or grouped products directly to the gift registry or wishlist without fuss.
* Set the quantity desired of products added to the gift registry or wishlist.
* Add products from multiple gift registries or wishlists to the same cart and even add the same products to the cart as normal items.
* Track gift registry or wishlist items in the cart individually all the way up to checkout and order.
* Wishlist cart widget.
* Wishlist search widget.
* Customize the appearance and position of the add to gift registry or wishlist button.
* Customize the wishlist items table, add or remove columns as necessary, sort columns in every way.
* Social sharing for the gift registry or wishlist.
* Set the permalink where customers can view their wishlists on the frontend as you like.
* Set the permalink where customers can manage their wishlists in the WooCommerce my-account area as you like.
* Advanced search form for searching gift registries or wishlists by title, name, email and other fields.
* Multiple shortcodes for displaying and customizing every single template used by the plugin including the add to wishlist button itself.
* WooCommerce-like template system allowing plugin templates to be overridden by copying them to your theme.
* WooCommerce-style notifications and add to cart functionality for the add to wishlist action.
* WooCommerce-like API for performing CRUD actions related to the wishlist or wishlist item on the fly.
* Ability to completely modify the frontend user interface for viewing and managing wishlists to match your custom theme.
* Ajaxified actions such as add to wishlist, add wishlist item to cart, form submissions and others.
* Multiple action and filter hooks for tweaking the plugin's functionality at important steps of its functionality.
* Translation ready.

= Pro version features =

* Ability for each customer to have multiple gift registries or wishlists.
* Add multiple wishlist items to the cart at once.
* WooCommerce-like emails configurable to be sent to custom recipients and the wishlist owner at various stages such as when a wishlist is created, fulfilled and deleted, and when a wishlist item is ordered, purchased and refunded.
* Featured and background images for each gift registry or wishlist with various display styles.
* Ability to send custom messages to the gift registry or wishlist owner from the checkout page.
* Messages inbox for customers to view messages sent to them on the checkout page from their account area. Configure sending messages to customers' email.
* Settings for customers to manage the visibility and other properties of their gift registry or wishlist on the frontend.
* Ability to exclude individual wishlists from search results.
* Ability to mark an item as favourite in the wishlist and sort items by their favourite status.
* Extra settings for customizing the add to wishlist button and action completely to your liking.
* Ability to customize wishlist templates simply with the click of buttons from the admin settings page.
* Extra setting for customizing plugin functionality.
* Ability to set separate shipping methods and rates for wishlist items and ability to ship wishlist items to the wishlist owner's address.
* Ability to hide or customize the wishlist owner's shipping address on the frontend when shipping to it.
* Ability to include/exclude products from being added to the wishlist.
* Ability to include/exclude product categories from being added to the wishlist.
* Allow wishlist owners to see details of who bought items for them.

== Installation ==

Install and activate NM Gift Registry and Wishlist like any other plugin, it works right out of the box. However it is recommended you go to the settings page to familiarize yourself with the default settings and update them if you wish. Also browse the documentation to see how the plugin works in detail.

== Frequently Asked Questions ==

= Can I use NM Gift Registry and Wishlist as a gift registry plugin only =

Yes. NM Gift Registry and Wishlist is a fully-fledged gift registry plugin. It does that out of the box.

= Can I use NM Gift Registry and Wishlist as a wishlist plugin only =

Of course, it is also meant for this. NM Gift Registry and Wishlist can be used as a gift registry or wishlist plugin, and everything in between.


== Screenshots ==

1. Customizable items table - add and remove columns, change column contents.
2. Identify items from multiple lists in cart, order and checkout.
3. Add simple, variable and grouped products to the list.
4. Display WooCommerce-like add-to-list notices.
5. Wishlist page appearance.
6. View overview information in the wishlist management page.
7. Add detailed profile information and customize visibility and required status of profile fields.
8. Add shipping address the WooCommerce way.
9. Administrators have Full management control over all lists.


== Upgrade Notice ==


== Changelog ==

(Full changelog available in changelog.txt file in plugin root directory)

= 5.4.2 =
* Fix - Page jump after toast notice is displayed.

= 5.4.1 =
* Fix - Bug preventing first new wishlist being created for user if adding to wishlist on frontend on fresh install.

= 5.4 =
* Feature - Added partial compatibility with woocommerce checkout blocks.
* Fix - Prevent 'new' and 'home' from being used as wishlist titles.
* Fix - Bug preventing wishlist items in cart from having their quantities updated in the cart.
* Dev - Removed abstract class NMGR_Data extended by classes NMGR_Wishlist and NMGR_Wishlist_Item.

= 5.3.2 =
* Fix - Error on wishlist items table when product does not exist.

= 5.3.1 =
* Tweak - Improved pagination speed on wishlist items table.

= 5.3.0 =
* Fix - Dropdown items showing in raw form on page load.
* Tweak - Wishist items totals shows amount purchased including tax.

= 5.2.0 =
* Fix - Bug preventing checkout page from submitting when cart is shipping to wishlist address.

= 5.1.0 =
* Feature - Optimized wishlist items table.
* Fix - Bug preventing product details from updating in wishlist items table when changed due to cache.
* Feature - Prices in wishlist items table are displayed witho woocommerce 'wc_get_price_to_display' function.

= 5.0 =
* Fix - Billing address overwritten by custom gift registry shipping address on checkout and my account page.
* Dev - Removed deprecated functions, methods and classes.
* Dev - Deleted NMGR_Items_View class.
* Dev - Removed nmgr_wishlist_itemmeta table and code related to table.
