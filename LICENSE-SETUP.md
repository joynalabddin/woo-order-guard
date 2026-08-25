# WooCommerce Order Guard licensing setup

This plugin includes a customer-side license client for Envato Market purchase-code activation. The client intentionally does **not** contain an Envato Personal Token, OAuth secret, or seller credential. Those secrets must stay on a seller-controlled HTTPS license service.

## Configure the customer plugin

Add the following constants to `wp-config.php` before the line that says `That's all, stop editing!`:

```php
define( 'DJOG_CUSTOM_LICENSE_API_URL', 'https://licenses.example.com/v1/verify' );
define( 'DJOG_CUSTOM_LICENSE_ITEM_ID', 'YOUR_ENVATO_ITEM_ID' );
define( 'DJOG_CUSTOM_LICENSE_REQUIRED', true );
```

Use `DJOG_CUSTOM_LICENSE_REQUIRED` as `false` during development or for a free/demo edition. When it is `true` and an API URL is configured, checkout protection is enabled only when the license state is active. If the remote service has a temporary outage, the client uses a 14-day grace period after a previously successful activation.

## Customer activation flow

The customer opens **Order Guard → License**, pastes the purchase code from Envato Downloads → **Licence certificate & purchase code**, and clicks **Activate license**. The plugin sends the purchase code, product ID, item ID, current domain, site URL, plugin version, WordPress version and WooCommerce version to the configured HTTPS endpoint. The raw purchase code is encrypted in the WordPress options table with a key derived from the site salts and site URL. It is never shown in the admin UI after activation.

A customer can use one purchase code on one registered domain. Staging and test domains should be planned carefully because Envato describes purchase codes as single-domain registrations. Deactivation in this client removes the local registration and calls the seller service; the seller service must decide whether and when a reset is allowed.

## Seller license service contract

The seller endpoint must accept a JSON `POST` request and return JSON. The endpoint should authenticate the request using a seller-side secret, signed request, or an equivalent mechanism. Do not rely on the customer plugin as the source of truth for purchase validity.

Request example:

```json
{
  "api_version": "1",
  "action": "activate",
  "product_id": "woo-order-guard",
  "item_id": "YOUR_ENVATO_ITEM_ID",
  "purchase_code": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "domain": "customer.example.com",
  "site_url": "https://customer.example.com/",
  "plugin_version": "1.2.0",
  "wordpress_version": "7.1",
  "woocommerce_version": "11.0.1"
}
```

Successful response example:

```json
{
  "success": true,
  "status": "active",
  "message": "License activated successfully.",
  "expires_at": "",
  "activation_limit": 1,
  "activations_used": 1
}
```

Rejected response example:

```json
{
  "success": false,
  "status": "invalid",
  "message": "This purchase code is not valid for this product or domain."
}
```

The service should validate the purchase code against the Envato API on the seller side, verify that the returned item matches `item_id`, enforce one-domain activation, store only a hash of the purchase code where possible, and rate-limit repeated requests. It should handle Envato HTTP 429 responses and honor `Retry-After` rather than retrying aggressively.

The service should support these actions:

| Action | Purpose |
|---|---|
| `activate` | Validate the purchase, register the domain if allowed, and return an active state. |
| `check` | Revalidate an existing activation without creating a duplicate registration. |
| `deactivate` | Remove or mark the domain registration according to the seller's reset policy. |

## Envato API secret boundary

The Envato Personal Token or OAuth client secret belongs only on the seller license service. Never place it in the WordPress plugin, JavaScript, ZIP, GitHub repository, `wp-config.php` distributed to customers, or browser-visible code.

## ThemeForest/Envato packaging notes

The plugin PHP is GPL-compatible and the item should be submitted under the licensing choice configured in the Envato author account. Include this document in the author package, but do not include a working seller token or production license endpoint credentials. The customer-facing plugin may contain the public endpoint URL and item identifier; all purchase verification and activation policy must remain server-side.
