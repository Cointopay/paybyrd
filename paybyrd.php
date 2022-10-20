<?php

/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Paybyrd extends PaymentModule
{
    protected $html = '';
    protected $postErrors = array();

    public function __construct()
    {
        $this->module_key = 'f68e87aee8a7bb2g90b024t210a2687a';
        $this->name = 'paybyrd';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.1';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Therightsw.com';
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        $config = Configuration::getMultiple(
            array(
                'PAYBYRD_API_KEY',
                'PAYBYRD_TEST_API_KEY',
                'PAYBYRD_TEST_MODE',
                'PAYBYRD_HOOK_URL',
                'PAYBYRD_HOOK_USER',
                'PAYBYRD_HOOK_PASSWORD',
            )
        );

        $this->api_key = $config['PAYBYRD_API_KEY'];
        $this->test_api_key = $config['PAYBYRD_TEST_API_KEY'];
        $this->test_mode = $config['PAYBYRD_TEST_MODE'];
        $this->hook_url = $config['PAYBYRD_HOOK_URL'];
        $this->hook_user = $config['PAYBYRD_HOOK_USER'];
        $this->hook_password = $config['PAYBYRD_HOOK_PASSWORD'];

        parent::__construct();

        $this->displayName = $this->l('Paybyrd');
        $this->description = $this->l('Accept payments for your shop with Paybyrd');
        $this->confirmUninstall = $this->l('Are you sure you want to delete Paybyrd?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        return true;
    }

    protected function postProcess()
    {
        if (!Tools::getValue('PAYBYRD_API_KEY')) {
            $this->html .= $this->displayError($this->l('API Key is required.'));
        } else if (!Tools::getValue('PAYBYRD_TEST_API_KEY')) {
            $this->html .= $this->displayError($this->l('Test API Key is required.'));
        } else if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('PAYBYRD_API_KEY', Tools::getValue('PAYBYRD_API_KEY'));
            Configuration::updateValue('PAYBYRD_TEST_API_KEY', Tools::getValue('PAYBYRD_TEST_API_KEY'));
            Configuration::updateValue('PAYBYRD_TEST_MODE', Tools::getValue('PAYBYRD_TEST_MODE'));
            Configuration::updateValue('PAYBYRD_HOOK_URL', Tools::getValue('PAYBYRD_HOOK_URL'));
            Configuration::updateValue('PAYBYRD_HOOK_USER', Tools::getValue('PAYBYRD_HOOK_USER'));
            Configuration::updateValue('PAYBYRD_HOOK_PASSWORD', Tools::getValue('PAYBYRD_HOOK_PASSWORD'));

            $this->html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
        }
    }

    public function renderForm()
    {
        $fieldsForm = array();

        $fieldsForm[0]['form'] = [
            'description' => 'Please configure API keys for both payment environments i.e. Production and Test.',
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('API Key'),
                    'name' => 'PAYBYRD_API_KEY',
                    'size' => 255,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Test API Key'),
                    'name' => 'PAYBYRD_TEST_API_KEY',
                    'size' => 255,
                    'required' => true
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Enable test mode'),
                    'name' => 'PAYBYRD_TEST_MODE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('ENABLED'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('DISABLED'),
                        ]
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $apiKey = Tools::getValue('PAYBYRD_API_KEY', Configuration::get('PAYBYRD_API_KEY'));
        $webhookUrl = Tools::getValue('PAYBYRD_HOOK_URL', Configuration::get('PAYBYRD_HOOK_URL'));

        if ($apiKey) {
            if (empty($webhookUrl)) {
                $baseURL = Context::getContext()->shop->getBaseURL(true);

                $context = stream_context_create(array(
                    'http' => array(
                        'method'  => 'POST',
                        'content' => json_encode([
                            'url' => $baseURL . 'module/paybyrd/webhook',
                        ]),
                        'header'  => array(
                            "Content-Type: application/json",
                            "Accept: application/json",
                            "X-Api-Key: " . $this->api_key
                        )
                    )
                ));

                $result = Tools::file_get_contents('https://gateway.paybyrd.com/api/v2/webhooks', false, $context);

                $response = json_decode($result);

                Configuration::updateValue('PAYBYRD_HOOK_URL', $response ? $response->url : null);
                Configuration::updateValue('PAYBYRD_HOOK_USER', $response ? $response->user : null);
                Configuration::updateValue('PAYBYRD_HOOK_PASSWORD', $response ? $response->password : null);
            }
        } else {
            Configuration::updateValue('PAYBYRD_HOOK_URL', null);
            Configuration::updateValue('PAYBYRD_HOOK_USER', null);
            Configuration::updateValue('PAYBYRD_HOOK_PASSWORD', null);
        }

        $testOrderStateKey = array_search(
            'Paybyrd Test OK',
            array_column(OrderState::getOrderStates($this->context->language->id), 'name')
        );

        $languages = Language::getLanguages(true);

        if (!$testOrderStateKey) {
            $orderStateObj = new OrderState();
            $orderStateObj->module_name = $this->displayName;
            $orderStateObj->color = '#4169E1';
            $orderStateObj->unremovable = 1;
            foreach ($languages as $language) {
                $orderStateObj->name[$language['id_lang']] = 'Paybyrd Test OK';
            }
            $orderStateObj->add();
        }

        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm($fieldsForm);
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        } else {
            $this->html .= '<br />';
        }

        $this->html .= $this->renderForm();

        return $this->html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) return;
        if (!$this->checkCurrency($params['cart'])) return;

        return [$this->getPaybyrdPayment()];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) return;

        return $this->fetch('module:paybyrd/views/templates/front/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currencyOrder = new Currency($cart->id_currency);
        $currencies = $this->getCurrency($cart->id_currency);

        if (is_array($currencies)) {
            foreach ($currencies as $currency) {
                if ($currencyOrder->id == $currency['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PAYBYRD_API_KEY' => Tools::getValue('PAYBYRD_API_KEY', Configuration::get('PAYBYRD_API_KEY')),
            'PAYBYRD_TEST_API_KEY' => Tools::getValue('PAYBYRD_TEST_API_KEY', Configuration::get('PAYBYRD_TEST_API_KEY')),
            'PAYBYRD_TEST_MODE' => Tools::getValue('PAYBYRD_TEST_MODE', Configuration::get('PAYBYRD_TEST_MODE')),
            'PAYBYRD_HOOK_URL' => Tools::getValue('PAYBYRD_HOOK_URL', Configuration::get('PAYBYRD_HOOK_URL')),
            'PAYBYRD_HOOK_USER' => Tools::getValue('PAYBYRD_HOOK_USER', Configuration::get('PAYBYRD_HOOK_USER')),
            'PAYBYRD_HOOK_PASSWORD' => Tools::getValue('PAYBYRD_HOOK_PASSWORD', Configuration::get('PAYBYRD_HOOK_PASSWORD')),
        );
    }

    public function getPaybyrdPayment()
    {
        $paybyrdOption = new PaymentOption();
        $paybyrdOption->setCallToActionText($this->l('Pay with Paybyrd'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:paybyrd/views/templates/front/payment_infos.tpl')
            );

        return $paybyrdOption;
    }
}
