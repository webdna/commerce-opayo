<?php
/**
 * Commerce Opayo plugin for Craft CMS 3.x
 *
 * Opayo payment gateway for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2021 Web DNA
 */

namespace webdna\commerce\opayo\responses;

use webdna\commerce\opayo\Opayo;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;

/**
 * @author    Web DNA
 * @package   CommerceOpayo
 * @since     1.0.0
 */
class PaymentResponse implements RequestResponseInterface
{
    /**
     * @var
     */
    protected $data;
    /**
     * @var string
     */
    private $_redirect = '';

    private $_transactionHash;
    /**
     * @var bool
     */
    private $_processing = false;
    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = (object) $data;
        //Craft::dd($this->data);
        $this->setRedirectUrl($this->data->acsUrl ?? '');
    }
    public function setRedirectUrl(string $url)
    {
        $this->_redirect = $url;
    }
    public function setProcessing(bool $status)
    {
        $this->_processing = $status;
    }
    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        return ($this->data->status ?? '') == 'Ok';
    }
    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return $this->_processing;
    }
    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return !empty($this->_redirect);
    }
    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return 'POST';
    }
    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        $params = [
            'TermUrl' => UrlHelper::actionUrl('commerce/payments/complete-payment?commerceTransactionHash='.$this->getTransactionHash()),
            'ThreeDSNotificationURL' => UrlHelper::actionUrl('commerce/payments/complete-payment?commerceTransactionHash='.$this->getTransactionHash()),
            'threeDSSessionData' => $this->getTransactionReference(),
            'MD' => $this->getTransactionReference(),
        ];

        if (isset($this->data->cReq)) {
            $params['creq'] = $this->data->cReq;
        }
        if (isset($this->data->paReq)) {
            $params['PaReq'] = $this->data->paReq;
        }

        return $params;
    }
    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
        return $this->_redirect;
    }
    /**
     * @inheritdoc
     */
    public function setTransactionHash($ref)
    {
        $this->_transactionHash = $ref;
    }
    public function getTransactionHash(): string
    {
        return $this->_transactionHash ?? '';
    }

    public function getTransactionReference(): string
    {
        return $this->data->transactionId ?? '';
    }
    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        //paReq
        return $this->data->statusCode ?? '';
    }
    /**
     * @inheritdoc
     */
    public function getData(): mixed
    {
        return $this->data;
    }
    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        return $this->data->statusDetail ?? '';
    }
    /**
     * @inheritdoc
     */
    public function redirect(): void
    {
        $variables = [];
        $hiddenFields = '';

        // Gather all post hidden data inputs.
        foreach ($this->getRedirectData() as $key => $value) {
            $hiddenFields .= sprintf('<input type="hidden" name="%1$s" value="%2$s" />', htmlentities($key, ENT_QUOTES, 'UTF-8', false), htmlentities($value, ENT_QUOTES, 'UTF-8', false)) . "\n";
        }

        $variables['inputs'] = $hiddenFields;

        // Set the action url to the responses redirect url
        $variables['actionUrl'] = $this->getRedirectUrl();

        //Craft::dd($variables);

        // Set Craft to the site template mode
        $templatesService = Craft::$app->getView();
        $oldTemplateMode = $templatesService->getTemplateMode();
        $templatesService->setTemplateMode($templatesService::TEMPLATE_MODE_CP);

        $template = $templatesService->renderPageTemplate('commerce-opayo/redirect', $variables);

        // Restore the original template mode
        $templatesService->setTemplateMode($oldTemplateMode);

        // Send the template back to the user.
        ob_start();
        echo $template;
        Craft::$app->end();
    }
}
