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

/**
 * @since 1.5.0
 */
class PaybyrdValidationModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $config = Configuration::getMultiple([
            'PAYBYRD_API_KEY',
            'PAYBYRD_TEST_API_KEY',
            'PAYBYRD_TEST_MODE',
        ]);

        $this->api_key = $config['PAYBYRD_API_KEY'];
        $this->test_api_key = $config['PAYBYRD_TEST_API_KEY'];
        $this->test_mode = $config['PAYBYRD_TEST_MODE'];

        $apiKey = $this->test_mode
            ? $this->test_api_key
            : $this->api_key;

        $context = stream_context_create([
            'http' => array(
                'method'  => 'GET',
                'header'  => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "X-Api-Key: " . $apiKey
                )
            )
        ]);

        $result = Tools::file_get_contents(
            'https://gateway.paybyrd.com/api/v2/orders/' . Tools::getValue('orderId'),
            false,
            $context
        );

        $response = json_decode($result);

        $idLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');

        $idCart = Tools::getValue('id_cart');
        $cart = new Cart($idCart, $idLangDefault);
        $order = Order::getByCartId($idCart);
        $customer = new Customer($cart->id_customer);

        if (
            !$cart->id or $cart->id_customer == 0 ||
            $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 ||
            !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $operationId = Tools::getValue('operationId');
        $isOrderValid = sha1($idCart . $apiKey) === $operationId;

        $indexTestOrderState = array_search(
            'Paybyrd Test OK',
            array_column(OrderState::getOrderStates($this->context->language->id), 'name')
        );

        $testOrderState = OrderState::getOrderStates($this->context->language->id)[$indexTestOrderState]['id_order_state'];

        if ($isOrderValid && $response) {
            if (
                strtolower($response->status) === 'paid' ||
                strtolower($response->status) === 'acquirersuccess' ||
                strtolower($response->status) === 'success'
            ) {
                if ($this->test_mode) {
                    $order->setCurrentState($testOrderState);
                } else {
                    $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
                }

                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key
                );
            }
        }

        Tools::redirect('index.php?controller=order&step=1');
    }
}
