# CPay Credit Card Payment Gateway For WordPress

`CPay Credit Card Payment Gateway` is a plugin of WordPress written in PHP.

# Quickstart

## Prerequisites
- WordPress (version 6.1.1+)
- WooCommerce Plugin
- Host, MerchantID and SecurityKey (from CPay)

## Install

Refer to [`CPay Crypto Payment Gateway` # Install ](https://github.com/cpayfinance/cpay-crypto-gateway-wp/blob/main/README.md#install)
### By Git

1. sign in the server of WorPress

2. enter directory of WordPress, by running command `cd /path/to/wordpress/wp-content/plugins/`.

3. fetch the codes, by running command `git clone https://github.com/cpayfinance/cpay-credit-card-gateway-wp.git`.

### or By Downloading

1. download zip from the endpoint (`https://github.com/cpayfinance/cpay-credit-card-gateway-wp/archive/refs/heads/main.zip`)

2. upload the zip to directory of WorPress on the server, unzip it.

## Config
1. sign in admin's dashboard of WordPress

2. click `plugins` of the navigation bar on the left, and select the `installed plugins` item.

3. plugin of `CPay Credit Card Payment Gateway` will be shown under the `unacticated` tag, activate it.

4. click `WooCommerce` of the navigation bar on the left, and select the `settings` item.

5. select tab of `payment`, enable it and click `CPay Credit Card Payment Gateway` into the setting page.

6. set `MerchantID` and `SecurityKey` (required), `Title` and `Description` are optional, save the setting finally.

---
After installing and setting, users will be shown payment option of `CPay Credit Card Payment Gateway` on the page of `checkout order`
