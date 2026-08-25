# WooCommerce Order Guard developer guide

এই document plugin maintainers, contributors এবং independent seller release প্রস্তুতকারীদের জন্য। লক্ষ্য হলো ছোট, readable, WordPress-native এবং নিরাপদ পরিবর্তন রাখা।

## Development environment

Development-এর জন্য WordPress 7.0+, PHP 8.3+, WooCommerce 9.x+ এবং একটি disposable test site ব্যবহার করুন। Block Checkout test করতে WooCommerce 11.x বা current supported release-এ Store API request পরীক্ষা করুন। Production customer data দিয়ে development test করা যাবে না।

## Coding rules

PHP file শুরুতে `ABSPATH` guard রাখুন। Admin action-এ capability check এবং nonce check বাধ্যতামূলক। User input-এর জন্য `wp_unslash()`, `sanitize_text_field()`, `sanitize_textarea_field()`, `sanitize_email()` এবং bounded numeric sanitization ব্যবহার করুন। HTML output-এর জন্য context অনুযায়ী `esc_html()`, `esc_attr()`, `esc_url()` অথবা `wp_kses_post()` ব্যবহার করুন।

Dynamic SQL-এ `$wpdb->prepare()` ব্যবহার করুন। Remote request-এ bounded timeout, response code check, JSON validation এবং failure fallback রাখুন। Customer identifiers log করার আগে mask করুন। Seller token, signing secret, product key, site credential বা production API secret কখনো repository-তে commit করবেন না।

## Existing hooks

| Hook | Location | উদ্দেশ্য |
|---|---|---|
| `woocommerce_after_checkout_validation` | main class | Classic checkout-এর server-side validation |
| `woocommerce_store_api_checkout_errors` | main class | Block Checkout validation |
| `rest_request_before_callbacks` | main class | Store API request guard |
| `rest_pre_dispatch` | main class | Store API checkout order creation-এর আগে guard |
| `woocommerce_checkout_create_order` | main class | normalized phone ও plugin version meta |
| `woocommerce_check_cart_items` | main class | excluded product cart error |
| `djog_daily_cleanup` | main class | masked log retention cleanup |
| `djog_daily_license_check` | license class | paid license status refresh |

## License states

License client তিনটি practical state সমর্থন করে। `free` state local Free/Demo activation-এর জন্য এবং কোনো key বা API লাগে না। `active` state seller-issued paid product key-এর সফল activation বোঝায়। `invalid`, `expired` বা `unknown` state seller response বা remote failure-এর ফল। `DJOG_CUSTOM_LICENSE_REQUIRED` false থাকলে core protection license ছাড়াই চলে; true থাকলে configured seller API-এর active paid license প্রয়োজন।

## Settings changes

নতুন setting যোগ করার সময় চারটি জায়গা আপডেট করুন: `defaults()`, settings HTML, `save_settings()` sanitizer এবং README/user guide। Default value safe, bounded এবং backward-compatible রাখুন। Existing option upgrade-এর জন্য `DJOG_DB_VERSION` bump করুন; activation/migration logic idempotent হতে হবে।

## Independent license client

`includes/class-djog-license.php`-এর API request seller-controlled HTTPS endpoint-এ যায়। Client-side code product-key validity নিজে সিদ্ধান্ত নেয় না; seller server response validate করে state cache করে। `activate`, `check` এবং `deactivate` action-এর response-এ `success`, `status` এবং `message` বাধ্যতামূলক রাখুন। Production service-এ activation-limit policy, product ID matching, secret secrecy, request authentication, audit log ও rate limiting থাকতে হবে।

`DJOG_LICENSE_REQUIRED` default false রাখা হয়েছে, যাতে endpoint configure না করা development/demo site ভেঙে না যায়। Paid release-এর ক্ষেত্রে seller endpoint পরীক্ষার পরে `DJOG_CUSTOM_LICENSE_REQUIRED` true করুন। Key generation ও API contract-এর জন্য [LICENSE-SETUP.md](../LICENSE-SETUP.md) দেখুন।

## Before submitting

```bash
php -l woo-order-guard.php
php -l includes/class-djog-license.php
node --check assets/frontend.js
git diff --check
```

Static scan-এ unexpected remote call, obfuscation function, secret-like value এবং unwanted branding খুঁজুন:

```bash
grep -RInE 'eval\(|shell_exec\(|passthru\(|curl_exec\(' . --exclude-dir=.git
```

Live test-এ একটি disposable product এবং synthetic customer data ব্যবহার করুন। Invalid phone, duplicate phone, duplicate email, IP signal, whitelist, rate limit, excluded product, classic checkout, Block Checkout এবং license API failure আলাদা test case হিসেবে চালান। Test শেষে synthetic order/product, temporary gateway এবং test options cleanup করুন।

## Release process

Version header, `DJOG_VERSION`, `DJOG_DB_VERSION`, README release note এবং changelog একই version-এ আনুন। PHP/JS lint ও `git diff --check` চালান। `git grep` দিয়ে seller secret, real product key, credentials ও unwanted branding পরীক্ষা করুন। `.git` বাদ দিয়ে top-level `woo-order-guard` directory সহ ZIP তৈরি করুন। ZIP-এর content list, version header ও SHA-256 checksum যাচাই করুন। WordPress staging site-এ ZIP update, activation, Free/Demo mode, settings, dashboard ও checkout regression test করুন।

## Pull request standard

Pull request description-এ problem, implementation, security impact, backward compatibility, test commands এবং documentation changes লিখুন। নতুন feature বা behavior change-এর সঙ্গে reproducible test steps এবং documentation update থাকতে হবে।
