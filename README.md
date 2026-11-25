# WhatsApp Menu & Orders

A WordPress plugin for small restaurants that take orders over WhatsApp. Customers browse a one-page menu, fill a cart, and press one button. WhatsApp opens with the full order already written out, and the restaurant confirms in the same chat.

There's no checkout and no payment gateway. Customers pay in cash or by bank transfer, as agreed in the chat.

## What it does

- Mobile-first menu with categories, photos, prices and a sold-out state
- Option groups per item (size, sauce, extras) with price changes and required choices
- A note field on every item ("no onions")
- Cart with name, phone, pickup or delivery, address, time and payment method
- Optional Google Maps pin so the customer can mark their delivery spot
- Delivery fee, free-delivery threshold, minimum order and opening hours
- A closed notice outside opening hours
- Every order is saved in WordPress, emailed to the restaurant and exportable to CSV
- USD, EUR and MXN, with English and Spanish interfaces
- Elementor widget with style controls (free Elementor is enough), plus a `[wfo_menu]` shortcode

## What the message looks like

```
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
```

The plugin only builds a `wa.me` link. It doesn't call the WhatsApp API, and you don't need a WhatsApp Business account.

## Install

1. Download this repository as a ZIP, or clone it into `wp-content/plugins/whatsapp-menu-orders`.
2. Activate **WhatsApp Menu & Orders** in WordPress.
3. Under **Food Orders → Settings**, enter your WhatsApp number (international format, digits only) and your currency.
4. Add categories and dishes under **Food Orders → Menu Items**.
5. Put the **Food Menu & WhatsApp Order** widget on a page with Elementor, or use the `[wfo_menu]` shortcode.

Requires WordPress 6.4+ and PHP 7.4+.

## Styling

Change colors, radius, spacing and fonts in the widget's Style tab, in **Settings → Design → Custom CSS**, or with CSS variables in your theme. [docs/DESIGN.md](docs/DESIGN.md) lists the tokens and class names.

## WP-CLI

```bash
wp wfo status
wp wfo orders --status=handled --limit=50
wp wfo prune --days=90
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
