<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Opayo for Craft Commerce icon"></p>

<h1 align="center">Opayo for Craft Commerce</h1>

This plugin provides a [Opyao](https://www.opayo.co.uk/) integration for [Craft Commerce](https://craftcms.com/commerce).

## Requirements

This plugin requires Craft 4.0 and Craft Commerce 4.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for Opayo for Craft Commerce”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/commerce-opayo

# tell Craft to install the plugin
./craft install/plugin commerce-opayo
```

## Setup

To add an Opayo payment gateway, go to Commerce → Settings → Gateways, create a new gateway.

> **Tip:** The Vendor, Integration Key and Integration Password gateway settings can be set to environment variables. See [Environmental Configuration](https://docs.craftcms.com/v3/config/environments.html) in the Craft docs to learn more about that.

### Using the legacy basket format

Example implementation

```twig
<form method="POST" id="opayo-form">
       {{ csrfInput() }}
       {% set gateway = craft.commerce.gateways.getGatewayByHandle('opayo') %}
       <script src="{{ gateway.getJs() }}"></script>
       <input type="hidden" name="nonce">
       <input type="hidden" name="sessionKey" value="{{ gateway.token }}">

       <input type="text" id="name" value="" autocomplete="off" required>
       <input type="text" id="cardnumber" value="" autocomplete="off" required>
       <input type="text" id="expiry" placeholder="MMYY" value="" autocomplete="off" required>
       <input type="text" id="cvv" value="" autocomplete="off" required>

       <button type="submit">Submit</button>
</form>
```

```js
function paymentFormSubmit(e) {
        e.preventDefault();
        // disable submit button
        e.target.querySelector('button[type="submit"]').disabled = true;

        sagepayOwnForm({
                merchantSessionKey: e.target.querySelector('input[name="sessionKey"]').value
        }).tokeniseCardDetails({
                cardDetails: {
                        cardholderName: e.target.querySelector('[id="name"]').value,
                        cardNumber: e.target.querySelector('[id="cardnumber"]').value,
                        expiryDate: e.target.querySelector('[id="expiry"]').value,
                        securityCode: e.target.querySelector('[id="cvv"]').value,
                },
                onTokenised: function(result) {
                        if (result.success) {
                                e.target.querySelector('[name="nonce"]').value = result.cardIdentifier;
                                e.target.removeEventListener('submit', paymentFormSubmit);
                                e.target.submit();
                        } else {
                                alert(result.errors.join(', '));
                        }
                }
        });
}
document.getElementById('opayo-form').addEventListener('submit', paymentFormSubmit);
```
