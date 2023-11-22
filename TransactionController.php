<?php
if ($transactions) {
        $payment_mode = $this->PaymentMode->findById($transactions["GatewayAccount"]["paymentmode"]);
        $gateway_account = $this->GatewayAccount->findById($transactions["EpsTransaction"]["gateway_account_id"]);
        $seller = $this->FpxSeller->findById($gateway_account["GatewayAccount"]["fpx_seller_id"]);
        $exchange = $this->FpxExchange->findById($seller["FpxSeller"]["fpx_exchange_id"]);
        if (is_array($data)) {
            $transaction_id = $transactions["EpsTransaction"]["id"];
            $debit_authcode = $data["fpx_debitAuthCode"];
            $credit_authcode = $data["fpx_creditAuthCode"];
            if ($debit_authcode == "00" && $credit_authcode == null) {
                $status = 3;
                $error_message = "Transaction Pending!";
            } elseif ($debit_authcode == "00" && $credit_authcode == "00") {
                $status = 1;
                $error_message = "Transaction Success!";
            } elseif ($debit_authcode == "99" && $credit_authcode == null) {
                $status = 3;
                $error_message = "Transaction Pending For Authorizer To Approve!";
            } else {
                $debit_errormsg = $fpx->ReturnACError($debit_authcode);
                $credit_errormsg = $fpx->ReturnACError($credit_authcode);
                $status = 2;
                $error_message = $debit_errormsg . "|" . $credit_errormsg;
                $fpx_trans_id = $data["fpx_fpxTxnId"];
            }
        } else {
            $has_error = true;
            $this->Session->setFlash("Failed To Find The AE Message");
        }
    }
    if ($has_error) {
        $this->Session->setFlash("Failed To Find The AE Message :" . $error_message);
    } else {
        if ($status == 1) {
            $gateway_account = $this->GatewayAccount->findById($transactions["EpsTransaction"]["gateway_account_id"]);
            //$receipt = null;
            if ($transactions["EpsTransaction"]["receipt_no"] == "") {
                $receipt = $this->Receipt->doReceipt($gateway_account["Merchant"]["code"]);
                if ($receipt) {
                    $data["receipt_no"] = $receipt;
                }
            }
        }
    }
	
    if ($status == 1) {
        $data_save = array();;
        $data_save["id"] = $transaction_id;
        $data_save["gateway_id1"] = $data["fpx_fpxTxnId"];
        $data_save["gateway_id2"] = $data["fpx_debitAuthNo"];
        $data_save["buyer_bank"] = $data["fpx_buyerBankBranch"];
        $data_save["buyer_name"] = $data["fpx_buyerName"];
        $data_save["currency"] = $data["fpx_txnCurrency"];
        $data_save["eps_status"] = $status;
        $data_save['receipt_no'] = $data['receipt_no'];
        $data_save["status_code"] = "$debit_authcode|$credit_authcode";
        $data_save["status_message"] = "Debit:" . $fpx->ReturnACError($debit_authcode) . "|Credit:" . $fpx->ReturnACError($credit_authcode);
        $data_save["payment_clear_datetime"] = date("Y-m-d H:i:s");
        $data_save["comm_mode"] = 7;
		
        $this->EpsTransaction->save($data_save);
    }
    ## Add Ae Message Log ##
    $log = $this->TransactionLog->find("all", array("conditions" => array("transaction_id" => $transaction_id, "comm_mode" => 7)));
    if (!$log) {
        $data = array();
        $data["comm_mode"] = 7;
        $data["comm_string"] = $result;
        $data["transaction_id"] = $transaction_id;
        $data["timestamp"] = date("Y-m-d H:i:s");
        $this->TransactionLog->save($data);
		
    } else {
        $data = array();
        $data["id"] = $log[0]["TransactionLog"]["id"];
        $data["comm_mode"] = 7;
        $data["comm_string"] = $result;
        $data["transaction_id"] = $transaction_id;
        $data["timestamp"] = date("Y-m-d H:i:s");
        $this->TransactionLog->save($data);
    }
	
    /* ## Update to portal ##
    $transaction = $this->EpsTransaction->findById($id);
    $findMerc = $this->Merchant->findById($transaction['GatewayAccount']['merchant_id']);
    $findApi = $this->Api->findByMerchantId($findMerc['Merchant']['id']);
    $return_vars = array('TRANS_ID' => $transaction_id, 'PAYMENT_DATETIME' => date('Y-m-d H:i:s', strtotime($data['fpx_fpxTxnTime'])), 'AMOUNT' => $data['fpx_txnAmount'], 'PAYMENT_MODE' => $transaction['GatewayAccount']['paymentmode'], 'STATUS' => 1, 'STATUS_CODE' => "$debit_authcode|$credit_authcode",
    //'STATUS_MESSAGE'    => '',
    'PAYMENT_TRANS_ID' => $data['fpx_fpxTxnId'], 'APPROVAL_CODE' => $transaction['EpsTransaction']['eps_id2'], 'RECEIPT_NO' => $transaction['EpsTransaction']['receipt_no'], 'MERCHANT_ORDER_NO' => $transaction['EpsTransaction']['eps_id1'],
    //'CHECKSUM'          => $mycript,
    'MERCHANT_CODE' => $findMerc['Merchant']['code'], 'BUYER_BANK' => $transaction['EpsTransaction']['buyer_bank'], 'BUYER_NAME' => $transaction['EpsTransaction']['buyer_name'], 'BANK_CODE' => $transaction['EpsTransaction']['bank_code'],
    //'xtra_vars'         => $xtra_vars,
    );
    $url = $findApi['Api']['urlupdate'];
    $postdata = http_build_query($return_vars);
    $opts = array("http" => array("method" => "POST", "header" => "Content-type: application/x-www-form-urlencoded", "content" => $postdata), "ssl" => array("verify_peer" => false, "verify_peer_name" => false,));
    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context); */
    ## End Update to Portal ##
    $this->set("environment", $ae_value["fpx_msgToken"]);
    $this->set("page_title", "AE Message");
    $this->set("header_title", "");
}
