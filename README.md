# WooCommerce Order Guard by DevJoynal

**WooCommerce Order Guard** হলো WooCommerce store-এর জন্য তৈরি privacy-conscious fake order, duplicate order, rapid retry এবং customer-signal protection plugin। এটি বিশেষভাবে Bangladesh-focused COD store-এর জন্য phone normalization ও Bangladeshi mobile validation সমর্থন করে, আবার modern WooCommerce classic checkout, Block Checkout এবং HPOS workflow-এর সঙ্গেও কাজ করার জন্য তৈরি।

**Developer:** Joynal Abdin · **Brand:** DevJoynal<br>
**Repository:** [github.com/joynalabddin/woo-order-guard](https://github.com/joynalabddin/woo-order-guard)<br>
**Website:** [devjoynal.com](https://devjoynal.com)

> বর্তমান release: **1.2.0**। এই repository-তে plugin code, documentation, security policy এবং Envato/CodeCanyon licensing client অন্তর্ভুক্ত আছে।

## সূচিপত্র

| Section | বিষয় |
|---|---|
| [কেন এই plugin](#কেন-এই-plugin) | সমস্যার সংক্ষিপ্ত বিবরণ |
| [প্রধান feature](#প্রধান-feature) | customer protection ও admin সুবিধা |
| [কীভাবে কাজ করে](#কীভাবে-কাজ-করে) | checkout validation flow |
| [Compatibility](#compatibility) | WordPress, PHP ও WooCommerce baseline |
| [Installation](#installation) | ZIP, manual এবং update process |
| [Configuration](#configuration) | Settings screen ও recommended setup |
| [License activation](#license-activation) | Envato purchase-code workflow |
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

Pluginটি payment gateway বা customer data-কে কোনো remote analytics service-এ পাঠায় না। Checkout protection-এর মূল matching WordPress/WooCommerce database এবং local transient-এর মাধ্যমে সম্পন্ন হয়। Remote request কেবল তখনই ব্যবহৃত হয় যখন seller Envato licensing client সক্রিয় করে।

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
| License panel | Envato purchase-code activation, status refresh, domain binding ও deactivation |

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

Envato/CodeCanyon distribution-এর জন্য plugin-এ customer-side license client রয়েছে। Purchase verification-এর জন্য seller-controlled HTTPS license service আবশ্যক। Envato Personal Token, OAuth client secret বা seller credential কখনো customer plugin ZIP, JavaScript, GitHub repository বা browser-visible code-এ রাখা যাবে না।

### Customer-side configuration

`wp-config.php`-এ WordPress stop-editing line-এর আগে নিচের constants যোগ করতে হবে:

```php
define( 'DJOG_CUSTOM_LICENSE_API_URL', 'https://licenses.example.com/v1/verify' );
define( 'DJOG_CUSTOM_LICENSE_ITEM_ID', 'YOUR_ENVATO_ITEM_ID' );
define( 'DJOG_CUSTOM_LICENSE_REQUIRED', true );
```

Development বা free/demo edition-এ `DJOG_CUSTOM_LICENSE_REQUIRED` `false` রাখা যায়। Production paid edition-এ `true` দিলে configured license API-তে active license না পাওয়া পর্যন্ত checkout protection চালু হবে না। Previously active installation-এ remote API সাময়িক unavailable হলে client 14 দিনের grace period ব্যবহার করে।

### Customer activation flow

Customer **Order Guard → License** খুলে Envato Downloads থেকে পাওয়া **Licence certificate & purchase code** paste করে **Activate license** চাপবে। Plugin encrypted local storage-এ purchase code রাখে এবং raw code admin screen-এ আর দেখায় না। Current domain, product ID, item ID ও plugin version seller API-তে পাঠানো হয়।

Envato-এর official guidance অনুযায়ী purchase code licence certificate-এ থাকে এবং সাধারণত একটি domain registration-এর জন্য ব্যবহৃত হয় [1] [2]। Staging domain ব্যবহারের policy আগে ঠিক করা উচিত।

### Seller license service

Seller API-কে purchase code Envato API-এর মাধ্যমে verify করতে হবে, returned item ID মিলিয়ে দেখতে হবে, one-domain activation enforce করতে হবে, rate limit handle করতে হবে এবং activation/deactivation policy সংরক্ষণ করতে হবে। Plugin client API contract ও JSON examples-এর জন্য [LICENSE-SETUP.md](LICENSE-SETUP.md) দেখুন।

> এই repository-তে production Envato token বা license server credential নেই। Seller API আলাদা HTTPS hosting-এ deploy করে তারপর `wp-config.php` constants configure করতে হবে।

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
├── README.md
├── SECURITY.md
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
| License button disabled | license API URL বা item ID configure করা হয়নি | `wp-config.php` constants যোগ করুন |
| License service unavailable | seller endpoint timeout, 429 বা invalid response | API logs পরীক্ষা করুন; active installation grace period ব্যবহার করবে |
| Purchase code অন্য domain-এ ব্যবহৃত | Envato one-domain policy | seller reset policy অনুযায়ী reset request বা নতুন license ব্যবহার করুন |
| Old logs থেকে যাচ্ছে | WP-Cron run হয়নি | Dashboard থেকে **Run retention cleanup** চালান |
| Product exclusion কাজ করছে না | wrong product বা variation ID | parent/variation ID সঠিকভাবে configure করুন |

## Release history

### 1.2.0

Envato/CodeCanyon-ready customer license client, encrypted local purchase-code storage, domain binding payload, activation/deactivation/status refresh, daily license check, 14-day remote-service grace handling এবং seller API contract documentation যুক্ত হয়েছে।

### 1.1.0

Blocked retry cooldown, daily masked-log cleanup, manual cleanup, seven-day/thirty-day dashboard analytics, excluded product enforcement, responsive analytics cards এবং frontend stylesheet যুক্ত হয়েছে।

### 1.0.2

Store API pre-dispatch guard এবং visible Block Checkout alert যুক্ত হয়েছে, যাতে invalid phone order creation-এর আগে reject হয়।

## Contributing

Bug report বা feature request দেওয়ার আগে WordPress, PHP, WooCommerce version এবং reproduction steps লিখুন। Security issue public issue হিসেবে post না করে [SECURITY.md](SECURITY.md)-এর নির্দেশনা অনুসরণ করুন। Pull request-এ নতুন code-এর সঙ্গে documentation, compatibility note এবং lint result যোগ করুন।

## License

Plugin code GNU General Public License version 2 or later-এর অধীনে বিতরণ করা হয়েছে। Envato Market-এর WordPress licensing choice—default split license বা 100% GPL—author account-এর item settings অনুযায়ী নির্ধারিত হবে। Third-party asset থাকলে তার license GPL-compatible কি না যাচাই করা বাধ্যতামূলক [3]।

## References

[1]: https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Envato-Market-Purchase-Code "Envato purchase code guidance"
[2]: https://help.market.envato.com/hc/en-us/articles/59610664341785-Envato-Market-Purchase-Code-Is-Already-Being-Used-On-Another-Domain-how-to-resolve-it "Envato one-domain purchase-code guidance"
[3]: https://help.author.envato.com/hc/en-us/articles/360000534626-Theme-Plugin-Licensing-Options "Envato Theme and Plugin Licensing Options"
[4]: https://build.envato.com/api/ "Envato Market API documentation"
[5]: https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/checkout/ "WooCommerce Store API Checkout"
