# Independent license management setup

WooCommerce Order Guard independent seller distribution-এর জন্য দুইটি operating mode সমর্থন করে:

| Mode | License key | Intended use |
|---|---|---|
| Free/Demo | প্রয়োজন নেই | Demo site, development, official site বা limited free edition |
| Paid | Seller-issued product key | Customer purchase, domain-bound activation ও premium distribution |

License-free mode ইচ্ছাকৃতভাবে রাখা হয়েছে। তবে একই paid key বা unrestricted premium access সবার জন্য খুলে দেওয়া উচিত নয়; এতে commercial license control নষ্ট হবে।

## Free/Demo mode

WordPress admin-এ **Order Guard → License** খুলে **Use Free/Demo mode** চাপুন। এই mode local state হিসেবে current domain-এ active হয় এবং কোনো remote API বা license key প্রয়োজন হয় না। Development/demo edition-এর জন্য `wp-config.php`-এ:

```php
define( 'DJOG_CUSTOM_LICENSE_REQUIRED', false );
```

`DJOG_CUSTOM_LICENSE_REQUIRED` false থাকলে core checkout protection license ছাড়াই কাজ করবে। Paid edition-এ server API configure করার পরে একই flag true করলে paid license active না থাকলে premium protection বন্ধ রাখা যাবে।

## Paid product-key mode

Paid customer-এর জন্য আপনার seller license server থেকে একটি cryptographically random product key issue করুন। Key-এর raw value customer একবার পাবে; server-এ raw key না রেখে HMAC/SHA-256 hash রাখা উত্তম। Customer **Order Guard → License**-এ key paste করবে। Plugin key encrypted local option-এ রাখে এবং current domain, product ID, version ও key-এর সঙ্গে seller API-তে activation request পাঠায়।

Example key format:

```text
WOG-7F4K-9Q2M-X8PA-3R6T
```

এই ধরনের key তৈরির জন্য seller server-এ cryptographically secure random generator ব্যবহার করুন:

```php
$raw = strtoupper( bin2hex( random_bytes( 16 ) ) );
$license_key = 'WOG-' . implode( '-', str_split( substr( $raw, 0, 16 ), 4 ) );
$license_hash = hash_hmac( 'sha256', $license_key, LICENSE_SERVER_SECRET );
```

## wp-config.php configuration

Seller API ready হলে WordPress site-এ নিচের constants দিন:

```php
define( 'DJOG_CUSTOM_LICENSE_API_URL', 'https://licenses.example.com/v1/verify' );
define( 'DJOG_CUSTOM_LICENSE_PRODUCT_ID', 'woo-order-guard' );
define( 'DJOG_CUSTOM_LICENSE_REQUIRED', true );
```

`DJOG_CUSTOM_LICENSE_PRODUCT_ID`-এর value আপনার license server-এর product identifier-এর সঙ্গে মিলতে হবে। পুরোনো configuration-এর `DJOG_CUSTOM_LICENSE_ITEM_ID` নামটিও backward compatibility-এর জন্য গ্রহণ করা হয়, তবে নতুন installation-এ `PRODUCT_ID` ব্যবহার করুন।

## Seller API contract

Seller endpoint-টি JSON `POST` request গ্রহণ করবে। Request-এ product key, product ID, domain এবং plugin version থাকবে:

```json
{
  "api_version": "1",
  "action": "activate",
  "product_id": "woo-order-guard",
  "license_key": "WOG-7F4K-9Q2M-X8PA-3R6T",
  "domain": "customer.example.com",
  "site_url": "https://customer.example.com/",
  "plugin_version": "1.3.1",
  "wordpress_version": "7.1",
  "woocommerce_version": "11.0.1"
}
```

সফল response:

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

Rejected response:

```json
{
  "success": false,
  "status": "invalid",
  "message": "This license key is not valid for this product or domain."
}
```

Seller API-তে নিচের action থাকা উচিত:

| Action | Purpose |
|---|---|
| `activate` | Key যাচাই করে প্রথম domain registration তৈরি করে |
| `check` | Existing activation পুনরায় যাচাই করে |
| `deactivate` | Domain registration deactivate বা reset করে |

## Seller database fields

| Field | Purpose |
|---|---|
| `license_hash` | Raw key-এর পরিবর্তে HMAC/SHA-256 hash |
| `product_id` | Product entitlement আলাদা করা |
| `domain_hash` | কোন domain activate হয়েছে তা সংরক্ষণ |
| `status` | active, inactive, expired, suspended বা invalid |
| `plan` | free, standard, pro বা lifetime |
| `activation_limit` | একই key কত domain-এ চলবে |
| `activations_used` | বর্তমান activation count |
| `expires_at` | Subscription/expiry; lifetime হলে null |
| `created_at` ও `updated_at` | Audit trail |

## Lifetime key management

Lifetime key মানে expiry date থাকবে না; এটি unlimited domain key হওয়া উচিত নয়। আপনার নিজের official domain-এর জন্য founder/lifetime entitlement রাখা যায়। Customer lifetime plan-এর ক্ষেত্রেও activation limit সাধারণত 1 domain রাখা নিরাপদ। Domain migration বা reset seller dashboard থেকে controlled action হিসেবে করা উচিত।

আপনার ব্যক্তিগত phone number-কে raw universal key হিসেবে plugin code-এ রাখা হয়নি এবং রাখা হবে না। কোনো key hard-code করলে key extraction করে যে কেউ unauthorized activation করতে পারবে। ব্যক্তিগত founder key প্রয়োজন হলে seller server-এ hash হিসেবে রাখুন এবং domain allowlist-এর সঙ্গে bind করুন।

## Security boundary

Seller secret, signing secret, database credential এবং production key database কখনো WordPress plugin ZIP বা GitHub repository-তে রাখবেন না। Plugin customer-side client; product-key validity এবং activation policy-এর source of truth হলো seller server। API-তে HTTPS, request authentication, rate limiting, bounded response, audit logging এবং replay protection ব্যবহার করুন।

## Migration and reset policy

Customer site বদলালে seller dashboard থেকে পুরোনো domain deactivate করে নতুন domain activate করুন। Automatic unlimited reset দেবেন না। Test/staging site-এর জন্য আলাদা demo mode বা separate development key রাখুন, যাতে production customer key নষ্ট না হয়।

## Operational recommendation

শুরুতে official site-এ Free/Demo mode ব্যবহার করুন। Paid sales চালু করার আগে seller API deploy করে একটি disposable test key দিয়ে activate, refresh, deactivate, expired, suspended, wrong-domain এবং API-outage cases পরীক্ষা করুন। তারপর paid edition-এ `DJOG_CUSTOM_LICENSE_REQUIRED` true করুন।
