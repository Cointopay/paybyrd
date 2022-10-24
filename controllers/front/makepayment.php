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
class PaybyrdMakepaymentModuleFrontController extends ModuleFrontController
{
	public function postProcess()
    {
        $id_order = $_GET['id_order'];
        if (!empty($id_order)) {
            $this->generatePayment($id_order);
        } else {
            die('Invalid Order ID.');
        }
    }
    public function generatePayment($id_order)
    {
		$api_key = Configuration::get('PAYBYRD_API_KEY');
		if (Configuration::get('PAYBYRD_TEST_MODE')) {
			$api_key = Configuration::get('PAYBYRD_TEST_API_KEY');
		}
		
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://gateway.paybyrd.com/api/v2/paybylink",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => json_encode(array(
		    "isPreAuth" => false,
			"Amount" => (float)$_GET['amount'],
			"SellerEmailAddress" => Configuration::get('PS_SHOP_EMAIL'),
			"shopperEmailAddress" => $_GET['customerEmail'],
			"OrderRef" => $id_order
		)),
		  CURLOPT_HTTPHEADER => array(
			"accept: application/json",
			"cache-control: no-cache",
			"content-type: application/json",
			"x-api-key: {$api_key}"
		  ),
		));

		$resJson = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  $response = json_decode($resJson, true);
			if (isset($response['paybyLink'])){
				 Tools::redirect($response['paybyLink']);
			} elseif (isset($response['links'])){
				Tools::redirect($response['links']['href']);
			}  elseif (isset($response['message'])){
				echo $response['message'];
			} else {
				echo $resJson;
			}
		}
        die();
    }
}
