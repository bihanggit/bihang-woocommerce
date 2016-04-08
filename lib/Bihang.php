<?php
require_once(dirname(__FILE__) .'/Base.php');
class Bihang extends BihangBase{

    public static function withApiKey($key, $secret)
    {
        return new Bihang(new Bihang_ApiKeyAuthentication($key, $secret));
    }

    public static function withSimpleApiKey($key)
    {
        return new Bihang(new Bihang_SimpleApiKeyAuthentication($key));
    }

    public static function withOAuth($oauth, $tokens)
    {
        return new Bihang(new Bihang_OAuthAuthentication($oauth, $tokens));
    }

    function __construct($authentication, $tokens=null, $apiKeySecret=null) {
        parent::__construct($authentication, $tokens=null, $apiKeySecret=null);
    }

    public function addressesAddress($params=null){
        return $this->get("addresses",$params);
    }




    public function buttonsListButton($params=null){
        return $this->get("buttons",$params);
    }




    public function buttonsButton($params=null){
        return $this->post("buttons",$params);
    }




    public function createOrderButton($id,$params=null){
        return $this->post("buttons/$id/create_order",$params);
    }




    public function listOrderButton($id,$params=null){
        return $this->get("buttons/$id/orders",$params);
    }




    public function listContacts($params=null){
        return $this->get("contacts",$params);
    }




    public function testContacts($params=null){
        return $this->post("contacts/test",$params);
    }




    public function listNation($params=null){
        return $this->get("nations",$params);
    }




    public function applicationsOauth($params=null){
        return $this->get("oauth/applications",$params);
    }




    public function applicationOauth($id,$params=null){
        return $this->get("oauth/applications/$id",$params);
    }




    public function createApplicationsOauth($params=null){
        return $this->post("oauth/applications",$params);
    }




    public function listOrder($params=null){
        return $this->get("orders",$params);
    }




    public function createOrder($params=null){
        return $this->post("orders",$params);
    }




    public function detailOrder($id,$params=null){
        return $this->get("orders/$id",$params);
    }




    public function transactionDetailTransaction($id,$params=null){
        return $this->get("transactions/$id",$params);
    }




    public function payOrder4Step2Transaction($id,$params=null){
        return $this->put("transactions/$id/complete_send",$params);
    }




    public function cancelPayOrderTransaction($id,$params=null){
        return $this->put("transactions/$id/cancel_payorder",$params);
    }




    public function cancelPaymentOrderTransaction($id,$params=null){
        return $this->put("transactions/$id/cancel_send",$params);
    }




    public function cancelReceivePayOrderTransaction($id,$params=null){
        return $this->put("transactions/$id/cancel_request",$params);
    }




    public function simpleTransaction($params=null){
        return $this->get("transactions",$params);
    }




    public function sendMoneyTransaction($params=null){
        return $this->put("transactions/send_money",$params);
    }




    public function requestMoneyTransaction($params=null){
        return $this->put("transactions/request_money",$params);
    }




    public function userInfoUser($params=null){
        return $this->get("users",$params);
    }




    public function userBalanceUser($params=null){
        return $this->get("users/balance",$params);
    }




    public function registeUser($params=null){
        return $this->post("users",$params);
    }




    public function listWallet($params=null){
        return $this->get("wallets",$params);
    }




    public function deleteWallet($id,$params=null){
        return $this->delete("wallets/$id/delete",$params);
    }




    public function createWallet($params=null){
        return $this->post("wallets",$params);
    }




    public function setDefaultWallet($id,$params=null){
        return $this->put("wallets/$id/default",$params);
    }




    public function updateWallet($id,$params=null){
        return $this->put("wallets/$id/update",$params);
    }




    public function listDefaultWallet($params=null){
        return $this->get("wallets/default",$params);
    }
}