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
class PaybyrdWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $config = Configuration::getMultiple([
            'PAYBYRD_API_KEY',
            'PAYBYRD_TEST_API_KEY',
            'PAYBYRD_TEST_MODE',
            'PAYBYRD_HOOK_USER',
            'PAYBYRD_HOOK_PASSWORD',
        ]);

        $this->api_key = $config['PAYBYRD_API_KEY'];
        $this->test_api_key = $config['PAYBYRD_TEST_API_KEY'];
        $this->test_mode = $config['PAYBYRD_TEST_MODE'];
        $this->hook_user = $config['PAYBYRD_HOOK_USER'];
        $this->hook_password = $config['PAYBYRD_HOOK_PASSWORD'];

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

        $webhook = json_decode(Tools::file_get_contents('php://input'), true);

        $result = Tools::file_get_contents(
            'https://gateway.paybyrd.com/api/v2/orders/' . $webhook['orderId'],
            false,
            $context,
        );

        $response = json_decode($result);

        $idCart = Tools::substr($response ? $response->orderRef : 0, 3);
        $order = Order::getByCartId($idCart);

        $headers = getallheaders();
        $authHeader = Tools::substr($headers['Authorization'], 6);
        $authHeader = $this->decode($authHeader);

        $user = $this->hook_user . ':' . $this->hook_password;

        if ($authHeader === $user && $response) {
            if (
                strtolower($response->status) === 'paid' ||
                strtolower($response->status) === 'acquirersuccess' ||
                strtolower($response->status) === 'success'
            ) {
                $testOrderStateKey = array_search(
                    'Paybyrd Test OK',
                    array_column(OrderState::getOrderStates($this->context->language->id), 'name')
                );

                $testOrderState = OrderState::getOrderStates($this->context->language->id)[$testOrderStateKey]['id_order_state'];

                if ($this->test_mode) $order->setCurrentState($testOrderState);
                else $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
            }

            if (strtolower($response->status) === 'refunded') $order->setCurrentState((int)Configuration::get('PS_OS_REFUND'));
        }

        die();
    }

    public function decode($string)
    {
        $i = 0;
        $decoded = '';
        $base64chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
        $padd = 0;

        if (Tools::substr($string, -2) == '==') {
            $string = substr_replace($string, 'AA', -2);
            $padd = 2;
        } elseif (Tools::substr($string, -1) == '=') {
            $string = substr_replace($string, 'A', -1);
            $padd = 1;
        }

        while ($i < Tools::strlen($string)) {
            $d = 0;

            for ($j = 0; $j <= 3; $j++) {
                $d += strpos($base64chars, $string[$i]) << (18 - $j * 6);
                $i++;
            }

            $decoded .= chr(($d >> 16) & 255);
            $decoded .= chr(($d >> 8) & 255);
            $decoded .= chr($d & 255);
        }

        $decoded  = Tools::substr($decoded, 0, Tools::strlen($decoded) - $padd);

        return $decoded;
    }
}
