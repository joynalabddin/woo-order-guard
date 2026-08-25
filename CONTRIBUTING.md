# Contributing

WooCommerce Order Guard-এর contribution স্বাগত। নতুন feature বা bug fix শুরু করার আগে issue-তে problem statement, expected behavior এবং compatibility impact লিখুন। Security vulnerability public issue-তে প্রকাশ করবেন না; [SECURITY.md](SECURITY.md) অনুসরণ করুন।

## Pull request requirements

Pull request-এ পরিবর্তনের উদ্দেশ্য, implementation summary, WordPress/PHP/WooCommerce compatibility, security impact এবং test result লিখুন। নতুন user-facing setting হলে README ও [docs/USER-GUIDE.md](docs/USER-GUIDE.md) আপডেট করুন। নতুন hook, service বা data field হলে [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) আপডেট করুন।

## Before submitting

```bash
php -l woo-order-guard.php
php -l includes/class-djog-license.php
node --check assets/frontend.js
git diff --check
```

Real customer data, production credentials, seller product key, signing secret, private license endpoint বা generated database export commit করা যাবে না। ZIP build-এ `.git` directory বাদ দিতে হবে এবং top-level directory `woo-order-guard` রাখতে হবে।

## Review expectations

Code WordPress coding conventions অনুসরণ করবে, input sanitize করবে, output escape করবে, admin actions-এ nonce ও capability check রাখবে এবং remote API request-এ bounded timeout ও graceful failure রাখবে। Documentation ছাড়া behavior change merge করা হবে না।
