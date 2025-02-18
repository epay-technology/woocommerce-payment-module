<?php

/**
 * Copyright (c) 2017. All rights reserved ePay A/S.
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    ePay Payment Solutions
 * @copyright ePay Payment Solutions (https://epay.dk) (http://www.epay.dk)
 * @license   ePay Payment Solutions
 */
// class Epay_Payment_Soap extends epay_payment_api {
class Epay_Payment_Soap {

    private $pwd = '';
    private $apikey = false;
    private $posid = false;
	private $client = null;
	private $isSubscription = false;
	private $proxy;
    private $epay_payment_api;

	/**
	 * Constructor
	 *
	 * @param mixed $pwd
	 * @param bool $subscription
	 */
	public function __construct( $pwd = '', $subscription = false,  $apikey = false, $posid = false) {
		$this->pwd            = $pwd;
        $this->apikey         = $apikey;
        $this->posid          = $posid;
		$this->isSubscription = $subscription;
		$this->proxy          = new WP_HTTP_Proxy();
		$options              = array();
		$service_url          = $this->isSubscription ?
			'https://ssl.ditonlinebetalingssystem.dk/remote/subscription.asmx?WSDL' :
			'https://ssl.ditonlinebetalingssystem.dk/remote/payment.asmx?WSDL';

		if ( $this->proxy->is_enabled() && $this->proxy->send_through_proxy( $service_url ) ) {
			$options['proxy_host'] = $this->proxy->host();
			$options['proxy_port'] = $this->proxy->port();

			if ( $this->proxy->use_authentication() ) {
				$options['proxy_login']    = $this->proxy->username();
				$options['proxy_password'] = $this->proxy->password();
			}
		}

		$this->client = new SoapClient(
			$service_url,
			$options
		);

        $this->epay_payment_api = new epay_payment_api($this->apikey, $this->posid);
	}

	/**
	 * Authorize subscription
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $subscriptionid
	 * @param mixed $orderid
	 * @param mixed $amount
	 * @param mixed $currency
	 * @param mixed $instantcapture
	 * @param mixed $group
	 * @param mixed $email
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function authorize( $merchantnumber, $subscriptionid, $orderid, $amount, $currency, $instantcapture, $group, $email ) {

        if(is_numeric($subscriptionid))
        {
            try {
                $epay_params                   = array();
                $epay_params['merchantnumber'] = $merchantnumber;
                $epay_params['subscriptionid'] = $subscriptionid;
                $epay_params['orderid']        = $orderid;
                $epay_params['amount']         = (string) $amount;
                $epay_params['currency']       = $currency;
                $epay_params['instantcapture'] = $instantcapture;
                $epay_params['group']          = $group;
                $epay_params['email']          = $email;
                $epay_params['pwd']            = $this->pwd;
                $epay_params['fraud']          = 0;
                $epay_params['transactionid']  = 0;
                $epay_params['pbsresponse']    = '-1';
                $epay_params['epayresponse']   = '-1';

                $result = $this->client->authorize( $epay_params );
            } catch ( Exception $ex ) {
                throw $ex;
            }
        }
        else
        {
            $currency = Epay_Payment_Helper::get_iso_code( $currency, false );
            $instantcapture = ($instantcapture ? "NO_VOID" : "OFF");

            $result_json = $this->epay_payment_api->authorize($subscriptionid, $amount, $currency, (string) $orderid, $instantcapture, $orderid, Epay_Payment_Helper::get_epay_payment_callback_url( $orderid ));
            $result_arr = json_decode($result_json, true);

            if(is_array($result_arr))
            {
                $result = new stdClass;

                if($result_arr['transaction']['state'] == "PENDING")
                {
                    $result->authorizeResult = true;
                    $result->pbsResponse = 0;
                }
                else
                {
                    $result->authorizeResult = false;
                    $result->pbsResponse = -1004;
                    $result->epayresponse = -1;
                }
            }
        }

		return $result;
	}

	/**
	 * Delete subscription
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $subscriptionid
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function delete_subscription( $merchantnumber, $subscriptionid ) {

        if(is_numeric($transactionid))
        {
            try {
                $epay_params                   = array();
                $epay_params['merchantnumber'] = $merchantnumber;
                $epay_params['subscriptionid'] = $subscriptionid;
                $epay_params['pwd']            = $this->pwd;
                $epay_params['epayresponse']   = '-1';

                $result = $this->client->deletesubscription( $epay_params );
            } catch ( Exception $ex ) {
                throw $ex;
            }
        }
        else
        {
            $result = $this->epay_payment_api->delete_subscription($subscriptionid);

            $result = new stdClass;

            if($result)
            {
                $result->deletesubscriptionResult = true;
            }
            else
            {
                $result->deletesubscriptionResult = false;
                $result->epayresponse = -1;
            }
        }

		return $result;
	}

	/**
	 * Capture payment
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 * @param mixed $amount
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function capture( $merchantnumber, $transactionid, $amount ) {

        if(is_numeric($transactionid))
        {
            try {
                $epay_params                   = array();
                $epay_params['merchantnumber'] = $merchantnumber;
                $epay_params['transactionid']  = $transactionid;
                $epay_params['amount']         = (string) $amount;
                $epay_params['pwd']            = $this->pwd;
                $epay_params['pbsResponse']    = '-1';
                $epay_params['epayresponse']   = '-1';

                $result = $this->client->capture( $epay_params );
            } catch ( Exception $ex ) {
                throw $ex;
            }
        }
        else
        {
            $result_json = $this->epay_payment_api->capture($transactionid, $amount);
            $result_arr = json_decode($result_json, true);

            if(is_array($result_arr))
            {
                $result = new stdClass;

                if($result_arr['success'] == true)
                {
                    $result->captureResult = true;
                    $result->pbsResponse = 0;
                }
                else
                {
                    $result->captureResult = false;
                    $result->pbsResponse = -1004;
                    $result->epayresponse = -1;
                }
            }
        }

		return $result;
	}

	/**
	 * Credit payment
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 * @param mixed $amount
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function refund( $merchantnumber, $transactionid, $amount ) {

        if(is_numeric($transactionid))
        {
            try {
                $epay_params                   = array();
                $epay_params['merchantnumber'] = $merchantnumber;
                $epay_params['transactionid']  = $transactionid;
                $epay_params['amount']         = (string) $amount;
                $epay_params['pwd']            = $this->pwd;
                $epay_params['epayresponse']   = '-1';
                $epay_params['pbsresponse']    = '-1';

                $result = $this->client->credit( $epay_params );
            } catch ( Exception $ex ) {
                throw $ex;
            }
        }
        else
        {
            $result_json = $this->epay_payment_api->refund($transactionid, $amount);
            $result_arr = json_decode($result_json, true);

            if(is_array($result_arr))
            {
                $result = new stdClass;

                if($result_arr['success'] == true)
                {
                    $result->creditResult = true;
                    $result->pbsResponse = 0;
                }
                else
                {
                    $result->captureResult = false;
                    $result->pbsResponse = -1004;
                    $result->epayresponse = -1;
                }
            }
        }

		return $result;
	}

	/**
	 * Delete payment
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function delete( $merchantnumber, $transactionid ) {

        if(is_numeric($transactionid))
        {
            try {
                $epay_params                   = array();
                $epay_params['merchantnumber'] = $merchantnumber;
                $epay_params['transactionid']  = $transactionid;
                $epay_params['pwd']            = $this->pwd;
                $epay_params['epayresponse']   = '-1';

                $result = $this->client->delete( $epay_params );
            } catch ( Exception $ex ) {
                throw $ex;
            }
        }
        else
        {
            $result_json = $this->epay_payment_api->void($transactionid);
            $result_arr = json_decode($result_json, true);

            if(is_array($result_arr))
            {
                $result = new stdClass;

                if($result_arr['success'] == true)
                {
                    $result->deleteResult = true;
                }
                else
                {
                    $result->deleteResult = false;
                    $result->epayresponse = -1;
                }
            }
        }

		return $result;
	}

	/**
	 * Get an ePay transaction
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get_transaction( $merchantnumber, $transactionid ) {

        if(is_numeric($transactionid))
        {
            try {
                $epay_params                   = array();
                $epay_params['merchantnumber'] = $merchantnumber;
                $epay_params['transactionid']  = $transactionid;
                $epay_params['pwd']            = $this->pwd;
                $epay_params['epayresponse']   = '-1';

                $result = $this->client->gettransaction( $epay_params );
            } catch ( Exception $ex ) {
                throw $ex;
            }
        }
        else        
        {
            $result_json = $this->epay_payment_api->payment_info($transactionid);
            $result_obj = json_decode($result_json);
            
            $result = new stdClass;

            if($result_obj->transaction->state == "SUCCESS")
            {
                $result->gettransactionResult = true;
                $result->transactionInformation = $result_obj;
            }
            else
            {
                $result->gettransactionResult = false;
                $result->epayresponse = -1;
            }
        }
		return $result;
	}

	/**
	 * Get The ePay error message based on epay response code
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $epay_response_code
	 *
	 * @return mixed
	 */
	public function get_epay_error( $merchantnumber, $epay_response_code ) {
		$res = 'Unable to lookup errorcode';
		try {
			$epay_params                     = array();
			$epay_params['merchantnumber']   = $merchantnumber;
			$epay_params['pwd']              = $this->pwd;
			$epay_params['language']         = Epay_Payment_Helper::get_language_code( get_locale() );
			$epay_params['epayresponsecode'] = $epay_response_code;
			$epay_params['epayresponse']     = '-1';

			$result = $this->client->getEpayError( $epay_params );

			if ( $result->getEpayErrorResult == 'true' ) {
				$res = $result->epayresponsestring ?? $result->epayResponseString ?? "Unknown error";
			}
		} catch ( Exception $ex ) {
			$res .= ' ' . $ex->getMessage();
		}

		return $res;
	}

	/**
	 * Get The PBS error message based on pbs response code
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $pbs_response_code
	 *
	 * @return mixed
	 */
	public function get_pbs_error( $merchantnumber, $pbs_response_code ) {
		$res = 'Unable to lookup errorcode';
		try {
			$epay_params                   = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['language']       = Epay_Payment_Helper::get_language_code( get_locale() );
			if ( $this->isSubscription ) {
				$epay_params['pbsResponseCode'] = $pbs_response_code;
			} else {
				$epay_params['pbsresponsecode'] = $pbs_response_code;
			}
			$epay_params['pwd']          = $this->pwd;
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->getPbsError( $epay_params );

			if ( $result->getPbsErrorResult == 'true' ) {
				if ( array_key_exists( 'pbsResponeString', $result ) ) {
					$res = $result->pbsResponeString;
				} else {
					$res = $result->pbsresponestring;
				}
			}
		} catch ( Exception $ex ) {
			$res .= ' ' . $ex->getMessage();
		}

		return $res;
	}
}
