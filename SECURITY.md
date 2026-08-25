# Security policy

## Scope

WooCommerce Order Guard checkout validation, masked security logs, admin actions, privacy integrations, license client এবং seller API boundary-এর security issue এই policy-এর আওতাভুক্ত।

## Supported versions

Default branch-এর latest release হলো supported version। Security fix প্রকাশের পরে পুরোনো copy update করা উচিত। Current compatibility baseline হলো WordPress 7.0+, PHP 8.3+ এবং WooCommerce 9.x+।

## Reporting a vulnerability

Suspected vulnerability public GitHub issue-এ প্রকাশ করবেন না। [devjoynal.com](https://devjoynal.com)-এ দেওয়া private contact channel ব্যবহার করে Joynal Abdin-কে জানান। Password, API key, database credential, private URL বা working exploit public channel-এ পাঠাবেন না।

যতটা সম্ভব plugin version, WordPress version, WooCommerce version, concise reproduction path এবং redacted log/screenshot দিন। Customer personal information report করার আগে সম্পূর্ণ remove করুন।

| Report field | কী দিতে হবে |
|---|---|
| Affected version | কোন version বা commit প্রভাবিত |
| Environment | WordPress, PHP, WooCommerce ও hosting details |
| Reproduction | Minimal reproducible steps |
| Impact | Data disclosure, privilege escalation, checkout bypass বা অন্য প্রভাব |
| Mitigation | থাকলে safe workaround বা patch suggestion |

## Security design

Admin actions capability check এবং WordPress nonce দিয়ে protected। Dynamic database values prepared query-তে যায়। Checkout logs-এ phone, email এবং IPv4 masked করা হয়। Retention cleanup পুরোনো records সরায়। Privacy exporter ও eraser WordPress Privacy Tools-এর সঙ্গে যুক্ত। Customer-facing output context অনুযায়ী escaped।

License client-এর seller signing secret বা private API credential customer plugin-এ থাকে না। Product key encrypted local option-এ রাখা হয় এবং seller service HTTPS endpoint-এ verify করে। Seller license service-এ request authentication, product ID validation, activation-limit policy, rate limiting এবং audit trail থাকা উচিত।

## Secret handling

কখনো repository-তে commit করবেন না:

- Seller signing secret বা private API credential
- Real product key
- WordPress admin credential
- Seller API private key
- Customer export বা production database
- Production license endpoint-এর signing secret

যদি কোনো secret accidentally commit হয়, শুধু git history থেকে মুছবেন না; সঙ্গে সঙ্গে secret revoke/rotate করুন।
