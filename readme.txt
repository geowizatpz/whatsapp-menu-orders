=== WhatsApp Menu & Orders ===
Contributors: localfooddelivery
Tags: restaurant, food ordering, whatsapp, menu, elementor
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A one-page food menu with extras and per-item notes that sends the finished order to WhatsApp. No checkout, no payment gateway.

== Description ==

WhatsApp Menu & Orders turns one page into a complete ordering experience for a small restaurant:

* A fast, mobile-first menu with categories, photos, prices and sold-out states.
* Option groups per item ("Size", "Salsa", "Extras") with price adjustments and required choices.
* A special-requests note on every single item.
* A cart drawer that collects name, phone, pickup or delivery, address, time and payment method.
* One button that opens WhatsApp with the whole order already written out.
* Cash or bank transfer. No checkout, no card data, no payment gateway.
* Every order is logged in WordPress, emailed to the restaurant and exportable to CSV.
* Opening hours, delivery fee, free-delivery threshold and minimum order, all configurable.
* Three currencies (USD, EUR, MXN) and an English/Spanish interface.
* Works with Elementor (free): a dedicated widget with style controls, plus CSS variables so the whole design can be changed from Elementor or a stylesheet.

= How ordering works =

The customer fills the cart, presses "Send order on WhatsApp", and WhatsApp opens with a message like:

*New order* WFO-7K3M
————————————
2× Casa Burger — 185.00
   + Bacon (35.00)
   · no onions
————————————
Subtotal: 405.00
Delivery: 35.00
*Total: 440.00*
Payment: Bank transfer
Type: Delivery

The restaurant replies in the same WhatsApp thread with the confirmation and, for bank transfers, the account details are also shown on the confirmation screen. No account, no checkout, no plugin talks to the WhatsApp API.

== Installation ==

1. Upload the `whatsapp-menu-orders` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **Food Orders → Settings** and add your WhatsApp number (international format, digits only) and your currency.
4. Add categories and items under **Food Orders → Menu Items**. Set a price and, optionally, option groups.
5. Add the **Food Menu & WhatsApp Order** widget to a page with Elementor, or use the `[wfo_menu]` shortcode.
6. Set the page as the front page if you want a one-page site.

== Frequently Asked Questions ==

= Do I need Elementor Pro? =
No. The widget and its style controls work with free Elementor. Elementor Pro's Custom CSS works too, but the plugin also has its own Custom CSS box under Settings → Design and supports plain CSS variables.

= Can I change the design without code? =
Yes. The widget's Style tab controls colours, radius, spacing and typography. For anything else, use Settings → Design → Custom CSS, or Additional CSS in the customizer. See `docs/DESIGN.md` for the token and class reference.

= Are payments handled by the plugin? =
No. Payment happens between the customer and the restaurant: cash on delivery/pickup, or a bank transfer with the receipt sent in the WhatsApp chat. The bank details are configurable text.

= Can I have the menu in two languages? =
Yes. The interface ships in English and Spanish (Settings → General → Language). Each item also has optional Spanish name and description fields, so one menu can serve both languages.

= Is the order stored if WhatsApp fails? =
Yes, when "Save orders" is on the order is stored before the customer leaves the page, and the restaurant also gets an email. The WhatsApp link is on the confirmation screen as a fallback.

== Screenshots ==

1. The one-page menu with categories and cards.
2. The item dialog with options and a special-requests note.
3. The cart drawer with pickup/delivery and payment choice.
4. The confirmation screen with the order code and payment instructions.
5. Settings → Ordering with fees, minimum order and opening hours.

== Changelog ==

= 0.2.2 =
* New "Delivery fee label" setting shown instead of "Free" when there is no flat fee.
* The first line of the delivery note is bold.
* The map sits above the address field, with "Use my location" on the map.

= 0.2.1 =
* Larger text across the menu, cart and confirmation.
* Removed the dot from the closed banner.
* New "Note under the bank details" setting, highlighted in the confirmation.
* The delivery note keeps its line breaks.

= 0.2.0 =
* Setting to preselect delivery (or pickup) in the cart; the preselected type is shown first.
* Optional Google Maps pin for delivery addresses. The WhatsApp message and the saved order get a Google Maps link to the pin.

= 0.1.0 =
* First release: menu, option groups, per-item notes, cart, WhatsApp handoff, order log, CSV export, opening hours, fees, USD/EUR/MXN, English/Spanish, Elementor widget.
