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
class PaybyrdPaymentModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

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

        if (
            !$cart->id or $cart->id_customer == 0 ||
            $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 ||
            !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $baseURL = Context::getContext()->shop->getBaseURL(true);
        $customer = new Customer($cart->id_customer);
        $total = (float)($cart->getOrderTotal(true, Cart::BOTH));
		$amount = $cart->getOrderTotal(true, Cart::BOTH);
		$operationId = sha1($cart->id . $apiKey);

        $redirectUrl = $this->context->link->getModuleLink('paybyrd', 'validation', array(
            'id_cart' => $cart->id,
			'id_module' => $this->module->id,
			'id_order' => $this->module->currentOrder,
            'key' => $customer->secure_key,
            'operationId' => $operationId
        ), true);
		
        $this->module->validateOrder(
			$cart->id,
			Configuration::get('PS_OS_COD_VALIDATION'),
			$total,
			$this->module->displayName,
			null,
			array(),
			Context::getContext()->currency->id,
			false,
			$customer->secure_key
		);
        

        
		$orderObj = new Order($this->module->currentOrder);

        $body = [
            'isoAmount' => round((float)$amount * 100),
            'currency' => Context::getContext()->currency->iso_code,
            'orderRef' => $orderObj->reference,
            'shopper' => array(
                'email' => $customer->email,
                'firstName' => $customer->firstname,
                'lastName' => $customer->lastname
            ),
            'orderOptions' => array(
                'redirectUrl' => $redirectUrl
            ),
            'paymentOptions' => array(
                'useSimulated' => !!$this->test_mode
            )
        ];

        $context = stream_context_create([
            'http' => array(
                'method'  => 'POST',
                'content' => json_encode($body),
                'header'  => array(
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "X-Api-Key: " . $apiKey
                )
            )
        ]);

        $result = Tools::file_get_contents('https://gateway.paybyrd.com/api/v2/orders', false, $context);
        $response = json_decode($result);

        if ($response ? $response->orderId : false) {

        }

        $this->context->smarty->assign([
            'checkoutKey' => $response ? $response->checkoutKey : '',
            'orderId' => $response ? $response->orderId : '',
            'redirectUrl' => $redirectUrl,
            'failureRedirectUrl' => $baseURL . 'index.php?controller=order&step=1'
        ]);

        $this->setTemplate('module:paybyrd/views/templates/front/payment.tpl');
    }
}
