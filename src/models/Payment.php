<?php
/**
 * Commerce Opayo plugin for Craft CMS 3.x
 *
 * Opayo payment gateway for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2021 Web DNA
 */

namespace webdna\commerce\opayo\models;

use webdna\commerce\opayo\Opayo;

use Craft;
use craft\base\Model;

use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;

/**
 * @author    Web DNA
 * @package   CommerceOpayo
 * @since     1.0.0
 */
class Payment extends BasePaymentForm
{
    /**
     * @var string credit card reference
     */
    public $nonce;
    public $token;
    public $sessionKey;
    public $type;
    public $firstName;
    public $lastName;
    public $number;
    public $expiry;
    public $cvv;
    public $paymentMethod;
    public $default;

    //public $amount;

    /**
     * @inheritdoc
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource): void
    {
        $this->token = $paymentSource->token;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        if (empty($this->nonce) || empty($this->token)) {
            return parent::rules();
        }

        return [];
    }
}
