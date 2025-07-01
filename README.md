# EDD PipraPay Gateway

Adds **PipraPay** as a payment gateway for [Easy Digital Downloads](https://easydigitaldownloads.com/).

## Plugin Details

- **Plugin Name:** EDD PipraPay
- **Plugin URI:** https://piprapay.com
- **Author:** PipraPay
- **Version:** 1.0.0
- **Requires:** WordPress 5.0+, PHP 7.4+
- **Tested up to:** WordPress 6.5

## Features

- Seamless integration with Easy Digital Downloads
- Supports BDT currency
- Webhook verification and payment confirmation
- Custom metadata support
- Simple admin configuration

## Installation

1. Upload the plugin folder to `/wp-content/plugins/edd-piprapay/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Downloads > Settings > Payments**
4. Enable **PipraPay** as a payment gateway
5. Enter your:
   - PipraPay API URL
   - API Key
   - Currency (e.g., `BDT`)

## Usage

- On checkout, users can select PipraPay and will be redirected to the payment gateway.
- Upon successful payment, they are redirected back to your website.
- A webhook ensures automatic order verification.

Make sure your server supports HTTPS and the webhook endpoint is accessible.

## License

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Support

For issues or feature requests, open a GitHub issue or contact us at [support@piprapay.com](mailto:support@piprapay.com).
