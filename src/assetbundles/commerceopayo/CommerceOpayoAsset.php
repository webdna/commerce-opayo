<?php
/**
 * Commerce Opayo plugin for Craft CMS 3.x
 *
 * Opayo payment gateway for Craft Commerce
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2021 Web DNA
 */

namespace webdna\commerce\opayo\assetbundles\commerceopayo;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Web DNA
 * @package   CommerceOpayo
 * @since     1.0.0
 */
class CommerceOpayoAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@webdna/commerce/opayo/assetbundles/commerceopayo/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/CommerceOpayo.js',
        ];

        $this->css = [
            'css/CommerceOpayo.css',
        ];

        parent::init();
    }
}
