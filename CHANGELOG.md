# Changelog

এই project-এর গুরুত্বপূর্ণ পরিবর্তনগুলো এখানে সংরক্ষিত হয়। Versioning semantic release style অনুসরণ করে।

## [1.3.0] — Flexible independent licensing

- License key ছাড়াই local Free/Demo mode activation যোগ করা হয়েছে।
- Seller-issued paid product-key activation flow যোগ হয়েছে।
- Product ID ও domain-bound seller API contract আপডেট হয়েছে।
- Lifetime plan ও founder access-এর security guidance যোগ হয়েছে।

## [1.2.0] — Independent licensing client

- Customer-side paid product-key license UI যোগ করা হয়েছে।
- Activation, status refresh এবং site deactivation action যোগ হয়েছে।
- Current domain, product ID, item ID এবং plugin version seller service-এ পাঠানোর contract যুক্ত হয়েছে।
- Product key local option-এ encrypted অবস্থায় রাখা হয়েছে; raw key admin screen-এ দেখানো হয় না।
- Remote service outage-এর জন্য 14-day active grace period যুক্ত হয়েছে।
- Daily license status check এবং license-required enforcement flag যুক্ত হয়েছে।
- `LICENSE-SETUP.md` এবং seller API contract documentation যোগ হয়েছে।

## [1.1.0] — Operations and analytics

- Blocked retry cooldown যোগ হয়েছে।
- Daily masked security-log cleanup এবং manual cleanup যুক্ত হয়েছে।
- Last-seven-days এবং last-thirty-days reason analytics dashboard-এ যুক্ত হয়েছে।
- Excluded product IDs catalog visibility ও checkout enforcement-এ যুক্ত হয়েছে।
- Responsive admin analytics cards এবং frontend stylesheet যোগ হয়েছে।

## [1.0.2] — Store API hardening

- WooCommerce Store API pre-dispatch guard যোগ হয়েছে।
- Block Checkout-এ visible validation alert যোগ হয়েছে।
- Invalid phone value order creation-এর আগে reject করা হয়।

## [1.0.1] — Block Checkout validation

- Store API checkout validation integration যোগ হয়েছে।
- Classic checkout ও Block Checkout-এর validation behavior সামঞ্জস্য করা হয়েছে।

## [1.0.0] — Initial release

- Bangladesh mobile normalization ও validation।
- Phone, email ও IP-based duplicate/fake-order matching।
- Trusted phone/email whitelist।
- Configurable window, statuses, messages ও frontend appearance।
- Masked security log, CSV export, privacy exporter/eraser এবং HPOS compatibility।
