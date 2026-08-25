# WooCommerce Order Guard user guide

এই guide store owner, support staff এবং WooCommerce administrator-এর জন্য। Plugin install করার পর প্রথমে WooCommerce active আছে নিশ্চিত করুন, তারপর **Order Guard → Settings** খুলুন।

## প্রথম দিনের setup

প্রথমে **Enable checkout protection** চালু করুন। Bangladesh COD store হলে **Require valid Bangladeshi mobile format** চালু রাখুন। Phone signal এবং email signal দিয়ে শুরু করা নিরাপদ; shared-office বা mobile-network environment-এ IP signal false positive তৈরি করলে IP check বন্ধ রাখা যেতে পারে।

Protection window হিসেবে 1,440 minutes দিয়ে শুরু করলে গত ২৪ ঘণ্টার matching order count হয়। Store-এর daily order volume বেশি হলে window কমানো যায়। `Maximum matching orders` সাধারণত `1` দিয়ে শুরু করুন; repeat customer business model থাকলে whitelist ব্যবহার করুন।

## Trusted customer list

Whitelisted phone numbers এক লাইনে একটি করে দিন। Plugin `+8801XXXXXXXXX`, `8801XXXXXXXXX` এবং `01XXXXXXXXX` একই normalized format-এ তুলনা করে। Whitelisted email lowercase করে তুলনা করা হয়। Trusted customer-কে whitelist করার আগে store order history ও internal verification check করুন।

## Customer message design

Duplicate/fake order message-এ `{{window}}` দিয়ে configured block window, `{{reason}}` দিয়ে matching signal, এবং `{{phone}}` দিয়ে normalized phone value বসে। Customer privacy রক্ষার জন্য production message-এ full phone number দেখানোর বদলে generic message ব্যবহার করার পরামর্শ দেওয়া হয়।

```text
দুঃখিত, এই তথ্য দিয়ে সাম্প্রতিক একটি অর্ডার পাওয়া গেছে। অনুগ্রহ করে {{window}} মিনিট পরে চেষ্টা করুন অথবা আমাদের সাথে যোগাযোগ করুন।
```

Invalid phone message সংক্ষিপ্ত, polite এবং actionable রাখুন। এটি checkout error হিসেবে customer দেখবে; এখানে customer service contact বা support page link যোগ করা যায়।

## Dashboard operations

Dashboard-এর **Total blocked** সব masked event-এর সংখ্যা, **Blocked today** UTC day অনুযায়ী বর্তমান দিনের সংখ্যা, এবং **Last 7 days** গত সাত দিনের activity দেখায়। **Security analytics** গত ৩০ দিনে reason breakdown দেখায়। Customer identifier masked হওয়ায় dashboard operational trend দেখায়, raw customer data নয়।

**Export CSV** ব্যবহার করে internal audit বা support review করা যায়। CSV export administrator capability-এর অধীনে থাকে। **Clear logs** সব security log মুছে দেয়; এটি irreversible action হওয়ায় আগে export নেওয়া উচিত। **Run retention cleanup** configured retention period-এর চেয়ে পুরোনো log সরায়।

## Product exclusion

যে product online checkout-এর জন্য temporarily unavailable, preorder-only, phone-confirmation-only অথবা restricted, তার product ID **Excluded product IDs**-এ দিন। Comma অথবা নতুন line দিয়ে একাধিক ID দেওয়া যায়। Product catalog visibility থেকে hide করার পাশাপাশি cart checkout-এ error দেওয়া হয়। Variation product হলে parent ও relevant variation ID business flow অনুযায়ী যাচাই করুন।

## License modes

Pluginটি Envato-এর বাইরে direct sales বা agency sales-এর জন্য দুইটি mode দেয়। **Free/Demo mode**-এ কোনো license key লাগে না; official site, development বা demo store-এ **Order Guard → License → Use Free/Demo mode** চাপুন।

**Paid mode**-এ আপনার seller license server থেকে issue করা product key লাগে। Customer **Order Guard → License** খুলে key paste করে **Activate paid license** চাপবে। Product key encrypted local option-এ রাখা হয় এবং seller API current domain, product ID ও plan/expiry অনুযায়ী activation status ফেরত দেয়।

Paid edition-এর জন্য seller API configure করার পরে `DJOG_CUSTOM_LICENSE_REQUIRED` `true` করুন। Lifetime plan-এ expiry না থাকলেও activation limit থাকতে পারে; lifetime মানে unlimited domain নয়। Domain migration বা reset seller dashboard-এর controlled policy অনুযায়ী করুন।

## Recommended maintenance

সপ্তাহে অন্তত একবার dashboard analytics দেখে blocked reason review করুন। False positive বাড়লে IP signal বা window adjust করুন এবং verified customer whitelist করুন। Privacy notice অনুযায়ী log retention কমিয়ে দিন। WordPress, PHP ও WooCommerce আপডেটের আগে database এবং plugin backup রাখুন।

## Quick checklist

| Check | Expected state |
|---|---|
| WooCommerce | Active and compatible |
| Protection | Active on dashboard |
| Mobile validation | Store policy অনুযায়ী configured |
| Signals | Phone/email/IP business context অনুযায়ী |
| Whitelist | Verified customers only |
| Retention | Privacy notice-এর সঙ্গে সামঞ্জস্যপূর্ণ |
| License | Free/Demo অথবা active paid key |
| Logs | Masked, exportable, periodically cleaned |
