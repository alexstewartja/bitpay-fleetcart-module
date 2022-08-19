# BitPay for FleetCart

![BitPay for FleetCart](https://i.ibb.co/9Y3KTrm/bitpay-fleetcart-module.png)

## About

**BitPay for FleetCart** is a module for [FleetCart](https://1.envato.market/fleetcart) which allows you to 
accept blockchain (cryptocurrency) payments on your FleetCart store, via [BitPay payment gateway](https://bitpay.com/business).

> :warning: A BitPay merchant account is required to use **BitPay for FleetCart**. If you don't already have an account, sign up
for [a test BitPay merchant account](https://test.bitpay.com/dashboard/signup) or [a production BitPay merchant account](https://bitpay.com/dashboard/signup).

## Server Requirements

In addition to the core server requirements outlined in
the [FleetCart Docs](https://docs.envaysoft.com/fleetcart/guide/installation.html#server-requirements), ensure your
server meets the following requirements:

- PHP Composer ([Install from here](https://getcomposer.org/doc/00-intro.md))

> :information_source: PHP Composer comes pre-installed on most Shared Hosting accounts.

## Install

Open a terminal in the root of your FleetCart installation and run the following command:

```shell
composer require alexstewartja/bitpay-fleetcart-module
```

Congratulations! 🎉 You've successfully installed **BitPay for FleetCart**!

## Configuration

Now that you've installed **BitPay for FleetCart**, it's time to configure it to start receiving crypto payments.

### Settings

Navigate to **Settings** > **Payment Methods** > **BitPay** in your admin panel:

![BitPay Settings Tab](https://i.ibb.co/nLLL2ZQ/31521752-c6b4decd77ac0557b44d08a872a7ea2a.png)

+ **Status**: Enable this payment method by checking this checkbox.
+ **Label** (translatable): The label for this payment method.
+ **Description** (translatable): The description for this payment method.
+ **BitPay Test**: Use BitPay's Test Environment (**test**.bitpay.com)
+ **Merchant Token**: The BitPay API Token for the `merchant` facade. [Explained here](#merchant-token)

### Merchant Token

Initially, the **Merchant Token** field won't be visible. You have to first enable the BitPay payment method by checking
the **Enable BitPay** checkbox then clicking **Save**.

Generate a new Merchant Token by clicking the `Generate Merchant Token`
button: ![Generate API Token Button](https://i.ibb.co/yXxktj2/generate-api-token-btn.png)

> :information_source: The type of **Merchant Token** you generate depends on whether you enabled use of _BitPay's Test Environment_ or not. If
you chose to enable **BitPay Test**, remember to disable it, then generate and approve a new **Merchant Token** when in
production.

After successful generation, approve your new Merchant Token by clicking the link provided. The link will take you to
your BitPay merchant dashboard to confirm approval. After approving, you will be ready to start accepting crypto payments
with **BitPay for FleetCart**!

![BitPay Settings Tab - Merchant Token](https://i.ibb.co/XWMYfrn/31521765-607d5684c55aceb2450785cb3150200f.png)

> :warning: Note that the token approval link will expire **24 hours** after the time of generation. Learn more about BitPay API
Tokens [here](https://support.bitpay.com/hc/en-us/articles/115003001183-How-do-I-pair-my-client-and-create-a-token-)

You may then dismiss the token approval message by clicking the `I've approved my merchant token` button.

That's it! You've successfully installed and configured **BitPay for FleetCart**! You can now accept over a dozen cryptocurrencies
from hundreds of crypto wallets on your FleetCart store!

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email iamalexstewart@gmail.com instead of using the issue tracker.

## Credits

- [Alex Stewart](https://github.com/alexstewartja)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.