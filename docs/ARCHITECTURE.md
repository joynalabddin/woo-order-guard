# WooCommerce Order Guard architecture

এই document-টি maintainers ও reviewers-এর জন্য। Plugin-এর মূল লক্ষ্য হলো checkout request order তৈরি হওয়ার আগে local rules ব্যবহার করে fake, duplicate এবং rapid retry attempt block করা।

## Runtime flow

```text
Customer checkout
      |
      v
Classic checkout / Store API request
      |
      v
Protection enabled? ---- no ----> WooCommerce continues normally
      |
     yes
      |
      v
Normalize phone + sanitize email + resolve IP
      |
      v
Valid Bangladesh phone? ---- no ----> customer error + masked event log
      |
     yes
      |
      v
Whitelist match? ----------- yes ---> WooCommerce continues normally
      |
      no
      |
      v
Cooldown transient active? -- yes ---> rate-limit error + masked event log
      |
      no
      |
      v
Query recent orders by configured signals
      |
      v
Match found? --------------- no ----> WooCommerce continues normally
      |
     yes
      |
      v
Customer error + masked log + retry cooldown
```

## Main components

| Component | Responsibility |
|---|---|
| `woo-order-guard.php` | Main plugin bootstrap, settings, checkout validation, order matching, logs, privacy tools and dashboard |
| `assets/frontend.js` | Classic/Block Checkout customer-side validation helper and visible alert |
| `assets/frontend.css` | Customer-facing alert and invalid field styles |
| `assets/admin.js` | Settings page live preview |
| `assets/admin.css` | Dashboard, settings and license interface styles |
| `includes/class-djog-license.php` | Independent seller product-key client, encrypted local state, domain payload, status refresh and grace handling |
| `LICENSE-SETUP.md` | Seller API contract, secrets boundary and customer configuration |

## Storage model

The plugin creates a table named `{prefix}djog_security_logs` with masked phone, masked email, masked IPv4, reason, event key, user-agent and UTC timestamp. The event key and one-minute transient reduce duplicate log rows from repeated browser requests. A daily WordPress cron event removes rows older than the configured retention period.

License state is stored in the `djog_license` option. When paid licensing is used, the raw product key is encrypted locally; a HMAC hash is stored for state correlation. The seller service remains the source of truth for paid product-key validity and activation limits. Free/Demo mode is a separate local state and does not require a seller service.

## Checkout matching

Phone values are converted to digits, `880` and `00880` prefixes are normalized to the Bangladesh `0` format, and the normalized value is capped at a safe length. Email is sanitized and lower-cased. The client IP is obtained from `REMOTE_ADDR`; untrusted forwarded headers are not accepted by default.

Recent order matching uses the configured time window, order statuses and maximum result count. The plugin checks phone, email and IP independently, then combines all matching reasons for the customer message and audit log. Whitelists are evaluated before duplicate matching.

## Licensing boundary

A customer plugin must not contain the seller's private signing secret or API credential. The license client sends a JSON request to a seller-controlled HTTPS endpoint. That endpoint validates the product key hash, verifies the product ID, enforces the seller's activation policy and returns a minimal activation state. See [LICENSE-SETUP.md](../LICENSE-SETUP.md) for the request/response contract.

The plugin does not hard-code a production license API URL or item ID. Seller configuration is injected through `wp-config.php` constants:

```php
define( 'DJOG_CUSTOM_LICENSE_API_URL', 'https://licenses.example.com/v1/verify' );
define( 'DJOG_CUSTOM_LICENSE_PRODUCT_ID', 'woo-order-guard' );
define( 'DJOG_CUSTOM_LICENSE_REQUIRED', true );
```

## Failure and grace behavior

Remote license requests have a bounded timeout. HTTP 429 and network failures are converted into a controlled status. If the site previously had an active license and the 14-day grace period has not elapsed, the plugin continues using the cached active state. It never makes checkout wait indefinitely for a remote service.

If the grace period has expired, the license state becomes `unknown` or `invalid`. Whether this state disables protection depends on `DJOG_CUSTOM_LICENSE_REQUIRED`. Development, official and free editions can keep that flag `false`; paid editions can set it to `true` after the seller API is ready. A local Free/Demo state remains available when license enforcement is not required.

## Extension guidelines

New checkout rules should be added inside `validate_payload()` or a dedicated helper called from it. New settings must be added to `defaults()`, rendered in `settings_page()`, sanitized in `save_settings()`, documented in README and covered by a compatibility note. New admin actions must include a capability check and a nonce. New database queries should use `$wpdb->prepare()` for dynamic values.

A feature must not send full customer identifiers to an external service. If an external integration is unavoidable, document the data fields, retention, user consent and failure behavior before implementation.
