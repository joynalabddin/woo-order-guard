# WooCommerce Order Guard by DevJoynal

**WooCommerce Order Guard** হলো WooCommerce store-এর জন্য তৈরি privacy-conscious fake order, duplicate order, rapid retry এবং customer-signal protection plugin। এটি বিশেষভাবে Bangladesh-focused COD store-এর জন্য phone normalization ও Bangladeshi mobile validation সমর্থন করে, আবার modern WooCommerce classic checkout, Block Checkout এবং HPOS workflow-এর সঙ্গেও কাজ করার জন্য তৈরি। Pluginটি Envato-এর বাইরে বিক্রির জন্য **Free/Demo mode** এবং seller-issued **Paid product-key mode**—দুইটিই সমর্থন করে।

**Developer:** Joynal Abdin · **Brand:** DevJoynal<br>
**Repository:** [github.com/joynalabddin/woo-order-guard](https://github.com/joynalabddin/woo-order-guard)<br>
**Website:** [devjoynal.com](https://devjoynal.com)

> বর্তমান release: **1.3.0**। এই repository-তে plugin code, documentation, security policy এবং independent seller licensing client অন্তর্ভুক্ত আছে।

## সূচিপত্র

| Section | বিষয় |
|---|---|
| [কেন এই plugin](#কেন-এই-plugin) | সমস্যার সংক্ষিপ্ত বিবরণ |
| [প্রধান feature](#প্রধান-feature) | customer protection ও admin সুবিধা |
| [কীভাবে কাজ করে](#কীভাবে-কাজ-করে) | checkout validation flow |
| [Compatibility](#compatibility) | WordPress, PHP ও WooCommerce baseline |
| [Installation](#installation) | ZIP, manual এবং update process |
| [Configuration](#configuration) | Settings screen ও recommended setup |
| [License activation](#license-activation) | Free/Demo ও paid product-key workflow |
| [Security and privacy](#security-and-privacy) | data handling, nonce ও API boundary |
| [Developer guide](#developer-guide) | hooks, files ও extension points |
| [Troubleshooting](#troubleshooting) | সাধারণ সমস্যা ও সমাধান |
| [Release history](#release-history) | version-by-version পরিবর্তন |
| [Documentation map](#documentation-map) | বিস্তারিত GitHub documents |

## Documentation map

| Document | উদ্দেশ্য |
|---|---|
| [docs/USER-GUIDE.md](docs/USER-GUIDE.md) | Store owner ও admin-এর Bengali operations guide |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Runtime flow, storage model, hooks ও licensing boundary |
| [docs/DEVELOPER-GUIDE.md](docs/DEVELOPER-GUIDE.md) | Coding rules, testing, release ও contributor workflow |
| [LICENSE-SETUP.md](LICENSE-SETUP.md) | Envato/CodeCanyon license API setup ও seller contract |
| [SECURITY.md](SECURITY.md) | Vulnerability reporting, secrets ও security design |
| [CHANGELOG.md](CHANGELOG.md) | Release-by-release history |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Pull request ও contribution standard |

## কেন এই plugin

Fake বা duplicate order সাধারণত একই phone number, email address, IP address অথবা অল্প সময়ের মধ্যে repeated checkout attempt থেকে তৈরি হয়। বিশেষ করে cash-on-delivery store-এ এই ধরনের অর্ডারের কারণে call verification, delivery cost, return-to-origin এবং staff workload বেড়ে যায়। Order Guard checkout-এর আগে configurable signal ব্যবহার করে ঝুঁকিপূর্ণ submission শনাক্ত করে এবং customer-কে store owner নির্ধারিত message দেখায়।

Pluginটি payment gateway বা customer data-কে কোনো remote analytics service-এ পাঠায় না। Checkout protection-এর মূল matching WordPress/WooCommerce database এবং local transient-এর মাধ্যমে সম্পন্ন হয়। Remote request কেবল তখনই ব্যবহৃত হয় যখন paid seller licensing client সক্রিয় করা হয়।

## প্রধান feature

### Customer ও checkout protection

| Feature | বিস্তারিত |
|---|---|
| Bangladesh mobile validation | `01XXXXXXXXX`, `+8801XXXXXXXXX`, `8801XXXXXXXXX` এবং digits-only input normalize করে valid operator prefix যাচাই করে |
| Duplicate order matching | Recent order-এর phone, email এবং customer IP configurable window-এর মধ্যে match করে |
| Multiple-order limit | Store owner কতগুলো matching order protection trigger করবে তা নির্ধারণ করতে পারেন |
| Status filtering | Pending, processing, on-hold, completed এবং cancelled status আলাদাভাবে count করা যায় |
| Rapid retry cooldown | একই normalized signal থেকে repeated blocked attempt সাময়িকভাবে slow করে |
| Whitelist | Trusted phone number ও email address protection থেকে বাদ দেওয়া যায় |
| Custom customer message | Bengali/English message, `{{window}}`, `{{reason}}` ও `{{phone}}` placeholder সহ |
| Block Checkout support | Classic checkout ও WooCommerce Store API/Block Checkout উভয় workflow-এ validation |
| Product exclusion | Selected product ID catalog visibility ও checkout থেকে block করা যায় |

### Admin ও operational feature

| Feature | বিস্তারিত |
|---|---|
| Dashboard cards | Total blocked, today, last seven days এবং active state |
| Reason analytics | গত ৩০ দিনের blocked event reason অনুযায়ী breakdown |
| Masked security log | Phone, email এবং IP masked অবস্থায় local database table-এ সংরক্ষণ |
| CSV export | Admin audit ও internal reporting-এর জন্য CSV export |
| Manual cleanup | Retention policy অনুযায়ী পুরোনো log manually remove করা যায় |
| Daily cleanup | WordPress cron দিয়ে পুরোনো masked record automatically remove |
| Live preview | Customer-facing error message ও appearance settings-এর live preview |
| HPOS compatibility | WooCommerce High-Performance Order Storage-এর জন্য compatibility declaration |
| License panel | Free/Demo activation, paid product-key activation, status refresh, domain binding ও deactivation |

## কীভাবে কাজ করে

Checkout request আসার পরে plugin প্রথমে configured product exclusion ও protection state যাচাই করে। এরপর phone value normalize করা হয়, email sanitize করা হয় এবং server-side client IP নেওয়া হয়। Mobile validation চালু থাকলে invalid Bangladesh number-এর জন্য checkout error দেওয়া হয়। Valid signal হলে whitelist check হয় এবং তারপর configured order statuses ও time window-এর মধ্যে phone, email এবং IP matching order খোঁজা হয়। Match পেলে checkout বন্ধ করে customer-facing message দেখানো হয় এবং masked event log করা হয়।

Block Checkout-এর ক্ষেত্রে plugin `woocommerce_store_api_checkout_errors` এবং Store API request pipeline-এর validation point ব্যবহার করে। Classic checkout-এর ক্ষেত্রে `woocommerce_after_checkout_validation` ব্যবহৃত হয়। ফলে order তৈরি হওয়ার আগেই validation সম্পন্ন হয়।

## Compatibility

| Component | Supported baseline | Tested environment |
|---|---:|---:|
| WordPress | 7.0+ | 7.1 |
| PHP | 8.3+ | PHP 8.3 CLI syntax check |
| WooCommerce | 9.x+ | 11.0.1 |
| Checkout | Classic ও Block Checkout | Store API validation verified |
| Storage | HPOS compatible | Compatibility declaration included |

Pluginটি WordPress বা WooCommerce replace করে না। Site owner-এর normal update process দিয়ে core platform আপডেট করতে হবে।

## Installation

### ZIP installation

1. এই repository থেকে `woo-order-guard.zip` download করুন।
2. WordPress admin-এ **Plugins → Add New Plugin → Upload Plugin** খুলুন।
3. ZIP upload করে **Install Now** চাপুন।
4. WooCommerce active আছে নিশ্চিত করে **Activate Plugin** চাপুন।
5. **Order Guard → Settings** খুলে protection rules configure করুন।

ZIP-এর top-level directory অবশ্যই `woo-order-guard` হওয়া উচিত।

### Manual installation

Repository clone করে `woo-order-guard` directory-টি `wp-content/plugins/`-এর ভিতরে copy করুন, তারপর WordPress Plugins screen থেকে plugin activate করুন।

### Update process

Update-এর আগে database ও plugin directory backup রাখুন। নতুন ZIP upload করলে WordPress existing installation replace করার confirmation দেখাবে। Update-এর পরে **Plugins** screen-এ version number এবং active state যাচাই করুন।

## Configuration

**Order Guard → Settings** screen-এ নিচের settings পাওয়া যায়।

| Setting | Recommended starting point |
|---|---|
| Enable checkout protection | `On` |
| Valid Bangladeshi mobile format | Bangladesh COD store হলে `On` |
| Block window | 1,440 minutes বা store policy অনুযায়ী |
| Maximum matching orders | সাধারণত `1` |
| Signals | Phone ও email `On`; IP business context অনুযায়ী |
| Blocked retry cooldown | 60–120 seconds |
| Counted statuses | pending, processing, on-hold এবং completed |
| Whitelisted phones/emails | Known repeat customer যোগ করুন |
| Excluded product IDs | যেসব product online order-এ allow করা হবে না |
| Log retention | Privacy policy অনুযায়ী 30–90 দিন |

Customer message-এ ব্যবহারযোগ্য placeholder হলো `{{window}}`, `{{reason}}` এবং `{{phone}}`। Customer-facing message-এ sensitive full phone বা email প্রকাশ না করার পরামর্শ দেওয়া হয়।

## License activation

Envato-এর বাইরে direct sales, agency sales বা আপনার own website-এর মাধ্যমে বিক্রির জন্য plugin-এ independent license manager রয়েছে। এতে **Free/Demo mode**-এ কোনো license key ছাড়াই plugin চালানো যায় এবং **Paid mode**-এ seller-issued product key দিয়ে domain-bound activation করা যায়।

### License ছাড়া activation

WordPress admin-এ **Order Guard → License** খুলে **Use Free/Demo mode** চাপুন। এই activation local এবং current domain-এ প্রযোজ্য। কোনো remote API বা license key লাগে না। Development, official site এবং demo environment-এর জন্য এটি ব্যবহার করুন।

```php
define( 'DJOG_CUSTOM_LICENSE_REQUIRED', false );
```

### Paid product-key activation

Production paid edition-এর জন্য seller-controlled HTTPS license API এবং product ID configure করুন:

```php
define( 'DJOG_CUSTOM_LICENSE_API_URL', 'https://licenses.example.com/v1/verify' );
define( 'DJOG_CUSTOM_LICENSE_PRODUCT_ID', 'woo-order-guard' );
define( 'DJOG_CUSTOM_LICENSE_REQUIRED', true );
```

Customer **Order Guard → License** খুলে আপনার seller server থেকে পাওয়া product key paste করে **Activate paid license** চাপবে। Plugin raw key encrypted local storage-এ রাখে, current domain পাঠায় এবং seller API-এর response অনুযায়ী active, invalid, expired বা suspended state দেখায়। Seller service one-domain, multi-domain বা lifetime plan-এর activation limit enforce করবে।

### Lifetime এবং founder access

Lifetime plan-এর অর্থ expiry date নেই; এর অর্থ unlimited domain নয়। আপনার official domain-এর জন্য founder/lifetime entitlement রাখা যায়। ব্যক্তিগত phone number বা কোনো raw secret universal key হিসেবে plugin code-এ hard-code করা যাবে না। এতে key extraction করে unauthorized activation সম্ভব হয়। Founder key seller server-এ hash হিসেবে এবং domain allowlist-এর সঙ্গে সংরক্ষণ করুন।

### Seller license service

Seller API-কে product key hash যাচাই করতে হবে, product ID মিলাতে হবে, plan/expiry/status সংরক্ষণ করতে হবে, domain activation limit enforce করতে হবে, rate limit ও audit trail রাখতে হবে এবং activation/deactivation/reset policy প্রয়োগ করতে হবে। Key generation, JSON contract, database fields ও security boundary-এর জন্য [LICENSE-SETUP.md](LICENSE-SETUP.md) দেখুন।

> এই repository-তে production license key, seller secret, database credential বা private API credential নেই। Seller API আলাদা HTTPS hosting-এ deploy করে তারপর `wp-config.php` constants configure করতে হবে।

## Security and privacy

Plugin-এর security model কয়েকটি স্তরে কাজ করে। Admin actions capability check এবং WordPress nonce দিয়ে protected। Database write WordPress API বা `$wpdb->prepare()` ব্যবহার করে। Customer-facing output escaped। Logs-এ phone, email ও IPv4 masked অবস্থায় থাকে। Daily retention cleanup পুরোনো log সরায়। Privacy exporter ও eraser integration WordPress Privacy Tools-এর সঙ্গে যুক্ত।

License client-এ seller secret plugin-এর বাইরে থাকে। Local purchase code AES-256-CBC encryption-এ site salts ও site URL-derived key দিয়ে সংরক্ষণ করা হয়; activation state-এর সঙ্গে one-way HMAC hash-ও রাখা হয়। Remote license outage-এর সময় checkout-এ live remote dependency তৈরি না করে cached active grace period ব্যবহার করা হয়।

## Developer guide

### Repository structure

```text
woo-order-guard/
├── assets/
│   ├── admin.css
│   ├── admin.js
│   ├── frontend.css
│   └── frontend.js
├── includes/
│   └── class-djog-license.php
├── LICENSE
├── LICENSE-SETUP.md
├── CHANGELOG.md
├── README.md
├── SECURITY.md
├── CONTRIBUTING.md
├── docs/
└── woo-order-guard.php
```

### Important extension points

| Hook | Purpose |
|---|---|
| `woocommerce_after_checkout_validation` | Classic checkout validation |
| `woocommerce_store_api_checkout_errors` | WooCommerce Block Checkout validation |
| `rest_pre_dispatch` | Store API pre-dispatch guard |
| `woocommerce_checkout_create_order` | Normalized phone ও plugin version order meta |
| `djog_daily_cleanup` | Daily masked log retention cleanup |
| `djog_daily_license_check` | Daily remote license status check |

### Order metadata

Valid order creation-এর সময় normalized phone `_djog_normalized_phone` এবং plugin version `_djog_guard_version` meta হিসেবে রাখা হয়। এগুলো customer-facing output-এ প্রকাশ করা উচিত নয়।

### Local quality checks

```bash
php -l woo-order-guard.php
php -l includes/class-djog-license.php
node --check assets/frontend.js
git diff --check
```

Production ZIP তৈরি করার সময় `.git` directory বাদ দিতে হবে। কোনো Envato token, API secret, real purchase code, customer export বা site credential commit করা যাবে না।

## Troubleshooting

| সমস্যা | সম্ভাব্য কারণ | সমাধান |
|---|---|---|
| Plugin activate হচ্ছে না | WooCommerce active নয় | আগে WooCommerce activate করুন |
| সব phone invalid দেখাচ্ছে | Bangladesh validation চালু, কিন্তু store international | validation setting বন্ধ বা product policy পরিবর্তন করুন |
| Duplicate block হচ্ছে না | matching signal বা status disabled | phone/email/IP এবং counted statuses পরীক্ষা করুন |
| Block Checkout-এ message নেই | cached asset বা Store API mismatch | cache clear করে checkout পুনরায় load করুন; WooCommerce version check করুন |
| Paid license button disabled | license API URL বা product ID configure করা হয়নি | `wp-config.php` constants যোগ করুন; অথবা **Use Free/Demo mode** চাপুন |
| License service unavailable | seller endpoint timeout, 429 বা invalid response | API logs পরীক্ষা করুন; active installation grace period ব্যবহার করবে |
| Product key অন্য domain-এ ব্যবহৃত | seller activation limit বা domain binding | seller reset policy অনুযায়ী পুরোনো domain deactivate বা reset করুন |
| Old logs থেকে যাচ্ছে | WP-Cron run হয়নি | Dashboard থেকে **Run retention cleanup** চালান |
| Product exclusion কাজ করছে না | wrong product বা variation ID | parent/variation ID সঠিকভাবে configure করুন |

## Release history

### 1.2.0

Independent seller licensing client, license-free Free/Demo mode, seller-issued paid product-key activation, encrypted local key storage, domain binding payload, activation/deactivation/status refresh, daily license check, 14-day remote-service grace handling এবং seller API contract documentation যুক্ত হয়েছে।

### 1.1.0

Blocked retry cooldown, daily masked-log cleanup, manual cleanup, seven-day/thirty-day dashboard analytics, excluded product enforcement, responsive analytics cards এবং frontend stylesheet যুক্ত হয়েছে।

### 1.0.2

Store API pre-dispatch guard এবং visible Block Checkout alert যুক্ত হয়েছে, যাতে invalid phone order creation-এর আগে reject হয়।

## Contributing

Bug report বা feature request দেওয়ার আগে WordPress, PHP, WooCommerce version এবং reproduction steps লিখুন। Security issue public issue হিসেবে post না করে [SECURITY.md](SECURITY.md)-এর নির্দেশনা অনুসরণ করুন। Pull request-এ নতুন code-এর সঙ্গে documentation, compatibility note এবং lint result যোগ করুন।

## License

Plugin code GNU General Public License version 2 or later-এর অধীনে বিতরণ করা হয়েছে। Seller-issued product key, license database এবং private seller API code এই public plugin code-এর অংশ নয়। Third-party asset থাকলে তার license compatibility যাচাই করা বাধ্যতামূলক।

## References

[1]: https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/checkout/ "WooCommerce Store API Checkout"
