<?php
require "InvoiceSDK/RestClient.php";
require "InvoiceSDK/common/SETTINGS.php";
require "InvoiceSDK/common/ORDER.php";
require "InvoiceSDK/CREATE_TERMINAL.php";
require "InvoiceSDK/CREATE_PAYMENT.php";

class Invoice_PaymentSystemDriver extends AMI_PaymentSystemDriver
{
    protected $driverName = 'Invoice';

    /**
     * Get checkout button HTML form.
     *
     * @param  array  &$aRes          Will contain "error" (error description, 'Success by default') and "errno" (error code, 0 by default). "forms" will contain a created form
     * @param  array  $aData          The data list for button generation
     * @param  bool   $bAutoRedirect  If form autosubmit required (directly from checkout page)
     * @return bool  TRUE if form is generated, FALSE otherwise
     */
    public function getPayButton(array &$aRes, array $aData, $bAutoRedirect = false){
        $this->log("getPayButton");
        $res =true;
        $aTemplateData = array(); // "return" => $this->classFunctionality->parameters["DEFAULT_RETURN_ADDRESS"]);
        if(is_array($aData)){
            $aTemplateData = array_merge($aTemplateData, $aData);
        }
        $aRes['error'] = 'Success';
        $aRes['errno'] = 0;

        $data = $aData;

        foreach(Array("return", "cancel", "description", "button_name", "payment_url") as $fldName){
            $data[$fldName] = htmlspecialchars($data[$fldName]);
        }

        foreach($data as $key => $value){
            $aData["hiddens"] .= "<input type=\"hidden\" name=\"$key\" value=\"$value\">\r\n";
        }
        $aData["button"] = trim($aData["button"]);
        if(!empty($aData["button"]))
        {
            $aData["_button_html"] =1;
        }
        if(!$res)
        {
            $aData["disabled"] ="disabled";
        }

        try {
            $payment = $this->createPayment($aData, $aData['amount'], $aData['order_id']);
            $aTemplateData['payment_url'] = $payment;
        } catch (Exception $e) {
            $aRes["errno"] = 1;
            $aRes["error"] = $e->getMessage();
            $res =false;
        }


        return parent::getPayButton($aRes, $aTemplateData, $bAutoRedirect);
    }

    /**
     * Get the form that will be autosubmitted to payment system. This step is required for some shooping cart actions.
     *
     * @param array $aData The data list for button generation
     * @param array &$aRes Will contain "error" (error description, 'Success by default') and "errno" (error code, 0 by default). "forms" will contain a created form
     * @return bool  TRUE if form is generated, FALSE otherwise
     * @throws Exception
     */
    public function getPayButtonParams(array $aData, array &$aRes){
        $this->log("getPayButtonParams");
        $aTemplateData = $aData;
        $aRes["error"] = "Success";
        $aRes["errno"] = 0;

        if(empty($aTemplateData["merchant_id"])){
            $aRes["errno"] = 1;
            $aRes["error"] = "merchant purse is missed";
            return false;
        }else if(empty($aTemplateData["amount"])){
            $aRes["errno"] = 3;
            $aRes["error"] = "amount is missed";
            return false;
        }



        $aTemplateData['signatureValue'] = md5(
            $aData['merchant_id'] . ':' .
            $aData['amount']  . ':' .
            $aData['order_id']  . ':' .
            $aData['password1']  . ':shpitem_number=' . $aData['order_id']
        );



        return parent::getPayButtonParams($aTemplateData, $aRes);
    }

    /**
     * Verify the order from user back link. In success case 'accepted' status will be setup for order.
     *
     * @param  array $aGet        HTTP GET variables
     * @param  array $aPost       HTTP POST variables
     * @param  array &$aRes       Reserved array reference
     * @param  array $aCheckData  Data that provided in driver configuration
     * @param  array $aOrderData  Order data that contains such fields as id, total, order_date, status
     * @return bool  TRUE if order is correct, FALSE otherwise
     */
    public function payProcess(array $aGet, array $aPost, array &$aRes, array $aCheckData, array $aOrderData){
        $this->log("payProcess");
        $status ='fail';
        if(!@is_array($aGet))
            $aGet =Array();
        if(!@is_array($aPost))
            $aPost =Array();
        $aParams =array_merge($aGet, $aPost);
        if(!empty($aParams['status']))
            $status =$aParams['status'];

        return ($status == "ok");
    }

    /**
     * Verify the order by payment system background responce. In success case 'confirmed' status will be setup for order.
     *
     * @param  array $aGet        HTTP GET variables
     * @param  array $aPost       HTTP POST variables
     * @param  array &$aRes       Reserved array reference
     * @param  array $aCheckData  Data that provided in driver configuration
     * @param  array $aOrderData  Order data that contains such fields as id, total, order_date, status
     * @return int  -1 - ignore post, 0 - reject(cancel) order, 1 - confirm order
     */
    public function payCallback(array $aGet, array $aPost, array &$aRes, array $aCheckData, array $aOrderData){
        $this->log("payCallback");

        $this->log("getProcessOrder");

        $postData = file_get_contents('php://input');
        $notification = json_decode($postData, true);

        $type = $notification["notification_type"];
        $id = $notification["order"]["id"];

        $signature = $notification["signature"];

        if($signature != $this->getSignature($notification["id"], $notification["status"], $aCheckData['api_key'])) {
            $this->log("Wrong signature");
            return 0;
        }

        if($type == "pay") {

            if($notification["status"] == "successful") {
                return 1;
            }
            if($notification["status"] == "error") {
                return 0;
            }
        }

        $this->log("Wrong type");
        return 0;
    }

    /**
     * Return real system order id from data that provided by payment system.
     *
     * @param  array $aGet               HTTP GET variables
     * @param  array $aPost              HTTP POST variables
     * @param  array &$aRes              Reserved array reference
     * @param  array $aAdditionalParams  Reserved array
     * @return int  Order Id
     */
    public function getProcessOrder(array $aGet, array $aPost, array &$aRes, array $aAdditionalParams){
        return parent::getProcessOrder($aGet, $aPost, $aRes, $aAdditionalParams);
    }

    /**
     * Do required operations after the payment is confirmed with payment system call.
     *
     * @param  int $orderId  Id of order in the system will be passed to this function
     * @return void
     */
    public function onPaymentConfirmed($orderId){
        header('Status: 200 OK');
        header('HTTP/1.0 200 OK');
        echo 'OK', $orderId;
        exit;
    }

    public function createTerminal($aData) {
        $request = new CREATE_TERMINAL("AmiroCMS Terminal");
        $this->log(json_encode($request));
        $response = $this->getRestClient($aData)->CreateTerminal($request);
        $this->log(json_encode($response));
        if($response == null) throw new Exception("Ошибка при создании терминала");
        if(isset($response->error)) throw new Exception("Ошибка при создании терминала(".$response->description.")");

        $this->saveTerminal($response->id);
        return $response->id;
    }

    public function checkOrCreateTerminal($aData) {
        $tid = $this->getTerminal();

        if($tid == null or empty($tid)) {
            $tid = $this->createTerminal($aData);
        }

        return $tid;
    }

    public function createPayment($aData, $amount, $id) {
        $order = new INVOICE_ORDER($amount);
        $order->id = $id;

        $settings = new SETTINGS($this->checkOrCreateTerminal($aData));
        $settings->success_url = ( ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);

        $request = new CREATE_PAYMENT($order, $settings, array());
        $response = $this->getRestClient($aData)->CreatePayment($request);

        if($response == null) throw new Exception("Ошибка при создании платежа");
        if(isset($response->error)) throw new Exception("Ошибка при создании платежа(".$response->description.")");

        return $response->payment_url;
    }

    public function getRestClient($aData) {
        return new RestClient($aData['login'], $aData['api_key']);
    }

    public function saveTerminal($id) {
        file_put_contents("invoice_tid", $id);
    }

    public function getTerminal() {
        if(!file_exists("invoice_tid")) return "";
        return file_get_contents("invoice_tid");
    }

    public function log($content){
        $file = 'invoice_log';
        $doc = fopen($file, 'a');
        file_put_contents($file, PHP_EOL . $content, FILE_APPEND);
        fclose($doc);
    }

    public function getSignature($id, $status, $key) {
        return md5($id.$status.$key);
    }
}