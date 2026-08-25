# WooCommerce Order Guard by DevJoynal

**WooCommerce Order Guard** is a lightweight, privacy-conscious protection plugin for stores that need to reduce fake, duplicate and rapid repeat orders. It is designed for modern WooCommerce stores, including Bangladesh-focused COD businesses, and is maintained under the **DevJoynal** brand by **Joynal Abdin**.

## What it does

The plugin validates Bangladesh mobile numbers, normalizes common formats such as `017XXXXXXXX` and `+88017XXXXXXXX`, and checks recent WooCommerce orders against configurable phone, email and customer-IP signals. Store owners can define the protection window, counted order statuses, maximum matching orders, trusted phone numbers and trusted email addresses. When a checkout is blocked, the customer sees a configurable message with a styled shield presentation.

The admin area includes a dashboard, masked security logs, CSV export, log clearing, seven-day and thirty-day reason analytics, manual retention cleanup, settings for protection signals, custom messages, colors, radius and font size, and a live message preview. The plugin declares compatibility with WooCommerce High-Performance Order Storage and supports classic checkout validation plus WooCommerce Block Checkout validation through the Store API request pipeline and the Blocks validation store. Administrators can also configure retry cooldowns and exclude selected product IDs from catalog visibility and checkout.

## Requirements

| Component | Supported baseline |
|---|---|
| WordPress | 7.0 or newer |
| PHP | 8.3 or newer |
| WooCommerce | 9.x or newer |
| License | GPL-2.0-or-later |

The target baseline is WordPress 7.0 or newer with PHP 8.3 or newer. The plugin does not replace WordPress or WooCommerce; those components should be updated from the site's normal update screen.

## Release notes

Version 1.1.0 adds configurable blocked-retry cooldowns, daily masked-log retention cleanup, manual cleanup, seven-day/thirty-day dashboard analytics, excluded-product enforcement, a real frontend stylesheet, and additional responsive admin cards. The earlier Store API pre-dispatch guard and visible Block Checkout alert remain active.

## Installation

Download or clone the repository, copy the `woo-order-guard` directory into `wp-content/plugins/`, activate WooCommerce first, then activate **WooCommerce Order Guard by DevJoynal** from Plugins. Open **Order Guard → Settings** to enable rules and configure the trusted-customer list.

For a ZIP installation, create a ZIP whose top-level directory is `woo-order-guard`, then upload it at **Plugins → Add New Plugin → Upload Plugin**.

## Privacy and security

The plugin stores only masked phone, email and IPv4 values in its security log. It does not send checkout data to DevJoynal, GitHub or another external service. Administrators should configure the retention period and retry cooldown appropriate to their privacy notice and operations, and should use WordPress's Privacy Tools when responding to data requests. A daily WordPress cron task removes records older than the configured retention period. All admin actions use capability checks and WordPress nonces, database writes use `$wpdb` placeholders or APIs, and customer-facing data is escaped before output.

## Developer branding

**Joynal Abdin**  
**DevJoynal**  
Website: [devjoynal.com](https://devjoynal.com)  
GitHub: [github.com/joynalabddin](https://github.com/joynalabddin)

## License

This project is distributed under the GNU General Public License version 2 or later. See [LICENSE](LICENSE).
