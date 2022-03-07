<?php
/**
 * Commerce Opayo plugin for Craft CMS 3.x
 *
 * Opayo payment gateway for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2021 Web DNA
 */

namespace webdna\commerce\opayo\gateways;

use webdna\commerce\opayo\Opayo;
use webdna\commerce\opayo\models\Payment;
use webdna\commerce\opayo\responses\PaymentResponse;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\Plan as BasePlan;
use craft\commerce\base\PlanInterface;
//use craft\commerce\base\SubscriptionGateway as BaseGateway;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\TransactionException;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\models\Currency;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\models\subscriptions\CancelSubscriptionForm as BaseCancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template as TemplateHelper;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use craft\db\Query;
use craft\db\Command;
use yii\base\Exception;
use yii\base\NotSupportedException;
use GuzzleHttp;

/**
 * @author    Web DNA
 * @package   CommerceOpayo
 * @since     1.0.0
 */
class Gateway extends BaseGateway
{
    // Public Properties
    // =========================================================================

    public $apiUrl = '';

    public $vendorName;

    public $key;

    public $password;

    public $testMode = false;

    public $opayoPaymentType;

    private $merchantSessionKey;

    private $sendCartInfo = false;

    private $client;

    private $gateway;

    private $customer;

    // Public Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('commerce', 'Opayo');
    }

    public function init()
    {
        parent::init();

        $this->client = new GuzzleHttp\Client();

        if ($this->testMode) {
            $this->apiUrl = 'https://pi-test.sagepay.com/api/v1/';
        } else {
            $this->apiUrl = 'https://pi-live.sagepay.com/api/v1/';
        }
    }

    public function setMerchantSessionKey($value)
    {
    }

    public function setSendCartInfo($value)
    {
    }
    
    public function getJs()
    {
        return $this->apiUrl . 'js/sagepay.js';
    }

    public function getToken()
    {
        if ($this->merchantSessionKey) {
            return $this->merchantSessionKey;
        }

        $response = $this->api('merchant-session-keys', [
            'vendorName' => Craft::parseEnv($this->vendorName),
        ]);

        if (isset($response['merchantSessionKey'])) {
            $this->merchantSessionKey = $response['merchantSessionKey'];
            return $this->merchantSessionKey;
        } else {
            // throw error
        }
    }

    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce-opayo/settings', ['gateway' => $this]);
    }

    public function getPaymentFormHtml(array $params = [])
    {
        $defaults = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel(),
        ];

        $params = array_merge($defaults, $params);

        // If there's no order passed, add the current cart if we're not messing around in backend.
        if (!isset($params['order']) && !Craft::$app->getRequest()->getIsCpRequest()) {
            $billingAddress = Commerce::getInstance()
                ->getCarts()
                ->getCart()
                ->getBillingAddress();

            if (!$billingAddress) {
                $billingAddress = Commerce::getInstance()
                    ->getCustomers()
                    ->getCustomer()
                    ->getPrimaryBillingAddress();
            }
        } else {
            $billingAddress = $params['order']->getBillingAddress();
        }

        if ($billingAddress) {
            $params['billingAddress'] = $billingAddress;
        }

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerJsFile($this->apiUrl . 'js/sagepay.js');

        $html = $view->renderTemplate('commerce-opayo/cpPaymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
    {
    }

    public function deletePaymentSource($token): bool
    {
        return true;
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new Payment();
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        try {
            $order = $transaction->getOrder();
            $request = Craft::$app->getRequest();

            //Craft::dd($form);
            $state = null;
            if (isset($order->billingAddress->state)) {
                $state = (object) $order->billingAddress->state;
            }

            $data = [
                'transactionType' => $this->opayoPaymentType,
                'paymentMethod' => [
                    'card' => [
                        'merchantSessionKey' => $form->sessionKey,
                        'cardIdentifier' => $form->nonce,
                    ],
                ],
                'vendorTxCode' => StringHelper::substr($transaction->hash, 0, 40),
                'amount' => intval(round($transaction->paymentAmount * 100)),
                'currency' => $transaction->currency,
                'description' => $order->reference,
                'customerFirstName' => StringHelper::substr($order->billingAddress->firstName, 0, 20),
                'customerLastName' => StringHelper::substr($order->billingAddress->lastName, 0, 20),
                'customerEmail' => StringHelper::substr($order->email, 0, 80),
                'customerPhone' => StringHelper::substr($order->billingAddress->phone, 0, 19),
                'billingAddress' => [
                    'address1' => StringHelper::substr($order->billingAddress->address1, 0, 50),
                    'city' => StringHelper::substr($order->billingAddress->city, 0, 40),
                    'postalCode' => StringHelper::substr($order->billingAddress->zipCode, 0, 10),
                    'country' => $order->billingAddress->countryIso,
                    'state' => isset($state) ? $state->abbreviation : null,
                ],
                'apply3DSecure' => 'UseMSPSetting',
                'strongCustomerAuthentication' => [
                    'notificationURL' => UrlHelper::actionUrl('commerce/payments/complete-payment?commerceTransactionHash=' . $transaction->hash),
                    'browserAcceptHeader' => $request->getHeaders()->get('accept'),
                    'browserJavascriptEnabled' => false,
                    'browserLanguage' => Craft::$app->language,
                    'browserUserAgent' => $request->getUserAgent(),
                    'challengeWindowSize' => 'FullScreen',
                    'transType' => 'GoodsAndServicePurchase',
                ],
            ];

            if ($ip = $request->getUserIp(FILTER_FLAG_IPV4)) {
                $data['strongCustomerAuthentication']['browserIP'] = $ip;
            }

            if ($request->isCpRequest) {
                //$data['apply3DSecure'] = "Disable";
                //$data['strongCustomerAuthentication']['threeDSExemptionIndicator'] =
                $data['entryMethod'] = 'TelephoneOrder';
            }

            Opayo::log($data);

            //Craft::dd($data);
            $response = $this->api('transactions', $data);

            //Craft::dd($response);
            $paymentResponse = new PaymentResponse($response);
            //$paymentResponse->setProcessing(true);
            $paymentResponse->setTransactionHash($transaction->hash);

            return $paymentResponse;
        } catch (\Exception $exception) {
            $message = $exception->getMessage();

            if ($message) {
                Opayo::error($message);
                throw new PaymentException($message);
            }

            Opayo::error('The payment could not be processed (' . get_class($exception) . ')');
            throw new PaymentException('The payment could not be processed (' . get_class($exception) . ')');
        }
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        $transactionId = Craft::$app->getRequest()->getParam('threeDSSessionData');
        $cres = Craft::$app->getRequest()->getParam('cres');
        $pares = Craft::$app->getRequest()->getParam('PaRes');
        $md = Craft::$app->getRequest()->getParam('MD');

        if ($pares) {
            $response = $this->api('transactions/' . $md . '/3d-secure', [
                'paRes' => $pares,
            ]);
            $response = $this->api('transactions/' . $md, [], 'GET');
        }
        if ($cres) {
            $response = $this->api('transactions/' . $transactionId . '/3d-secure-challenge', [
                'threeDSSessionData' => $transactionId,
                'cRes' => $cres,
            ]);
        }

        return new PaymentResponse($response);
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        //Craft::dd($transaction);
        try {
            $response = $this->api('transactions', [
                'transactionType' => 'Refund',
                'vendorTxCode' => $transaction->hash,
                'referenceTransactionId' => $transaction->reference,
                'amount' => intval($transaction->paymentAmount * 100),
                'currency' => $transaction->currency,
                'description' => $transaction->note != '' ? $transaction->note : '-',
            ]);

            return new PaymentResponse($response);
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function processWebHook(): WebResponse
    {
    }

    public function supportsAuthorize(): bool
    {
        return false;
    }

    public function supportsCapture(): bool
    {
        return false;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsPurchase(): bool
    {
        return true;
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    private function api($endpoint, $payload = [], $method = 'POST')
    {
        $response = $this->client->request($method, $this->apiUrl . $endpoint, [
            'auth' => [Craft::parseEnv($this->key), Craft::parseEnv($this->password)],
            'json' => $payload,
        ]);
        $responseBody = json_decode($response->getBody(), true);
        return $responseBody;
    }
}
