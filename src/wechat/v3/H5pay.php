<?php
namespace Lexiner\Htpay\wechat\v3;
/** 请填写以下配置信息 */
$mchid = 'xxxxx';          //微信支付商户号 PartnerID 通过微信支付商户资料审核后邮件发送
$appid = 'xxxxx';  //公众号APPID 通过微信支付商户资料审核后邮件发送
$appKey = 'xxxxx';  //微信支付申请对应的公众号的APP Key
$apiKey = 'xxxxx';   //https://pay.weixin.qq.com 帐户中心-安全中心-API安全-APIv3密钥-设置密钥
$privateKeyPath = "";       //apiclient_key.pem的路径，通过”微信支付商户平台证书工具“生成。https://kf.qq.com/faq/161222NneAJf161222U7fARv.html
$serialNumber = 'xxxxx'; //证书序列号 https://pay.weixin.qq.com 帐户中心-安全中心-API安全-API证书-查看证书
$outTradeNo = uniqid();     //你自己的商品订单号，最小字符长度为6
$payAmount = 0.01;          //付款金额，单位:元
$orderName = '支付测试';    //订单标题
$notifyUrl = 'https://www.xxx.com/notify_v3.php';     //付款成功后的回调地址(不要有问号)
/** 配置结束 */

//①、获取用户openid
$wxPay = new H5pay($mchid, $appid, $appKey,$apiKey,$privateKeyPath,$serialNumber);
//②、统一下单
$wxPay->setOrderName($orderName); 
$wxPay->setOutTradeNo($outTradeNo);
$wxPay->setTotalFee($payAmount);
$wxPay->setNotifyUrl($notifyUrl);
$result = $wxPay->doPay();
var_dump($result);

class H5pay
{
    protected $mchid;
    protected $appid;
    protected $apiKey;
    protected $appKey;
    protected $privateKeyPath;
    protected $serialNumber;
    protected $totalFee;
    protected $outTradeNo;
    protected $orderName;
    protected $notifyUrl;
    protected $auth;
    protected $openid;
    protected $gateWay='https://api.mch.weixin.qq.com/v3';

    public function __construct($mchid, $appid, $appkey,$apikey, $privateKeyPath, $serialNumber)
    {
        $this->mchid = $mchid;
        $this->appid = $appid;
        $this->apiKey = $apikey;
        $this->appKey = $appkey;
        $this->privateKeyPath = $privateKeyPath;
        $this->serialNumber = $serialNumber;
    }

    public function setTotalFee($totalFee)
    {
        $this->totalFee = floatval($totalFee);
    }

    public function setOutTradeNo($outTradeNo)
    {
        $this->outTradeNo = $outTradeNo;
    }

    public function setOrderName($orderName)
    {
        $this->orderName = $orderName;
    }

    public function setNotifyUrl($notifyUrl)
    {
        $this->notifyUrl = $notifyUrl;
    }

    public function setOpenid($openid)
    {
        $this->openid = $openid;
    }

    /**
     * 发起支付
     */
    public function doPay()
    {
        $reqParams = array(
            'appid' => $this->appid,        //公众号或移动应用appid
            'mchid' => $this->mchid,        //商户号
            'description' => $this->orderName,     //商品描述
            'notify_url' => $this->notifyUrl,       //通知URL必须为直接可访问的URL，不允许携带查询串。
            'out_trade_no' => $this->outTradeNo,      //商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一，详见【商户订单号】。特殊规则：最小字符长度为6
            'amount'=>array(
                'total'=>intval($this->totalFee * 100), //订单总金额，单位为分
                'currency'=>'CNY', //CNY：人民币，境内商户号仅支持人民币
            ),
            'scene_info'=>array(        //支付场景描述
                'payer_client_ip'=>request()->getRealIp(),   //调用微信支付API的机器IP
                'h5_info'=>[
                    'type'=>'Wap'
                ]
            )
        );
        $reqUrl = $this->gateWay.'/pay/transactions/h5';
        $this->getAuthStr($reqUrl,$reqParams);
        $response = $this->curlPost($reqUrl,$reqParams);
        $response = json_decode($response,true);
        return $response;
    }

    private function sign($message,$signType='sha256WithRSAEncryption')
    {
        $res = $this->privateKeyPath;
        if($signType=='RSA'){
            $result = openssl_sign($message, $sign, $res,version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256);
        }else{
            if (!in_array('sha256WithRSAEncryption', openssl_get_md_methods(true))) {
                throw new \RuntimeException("当前PHP环境不支持SHA256withRSA");
            }
            $result = openssl_sign($message, $sign, $res, 'sha256WithRSAEncryption');
        }
        if (!$result) {
            throw new \UnexpectedValueException("签名验证过程发生了错误");
        }
        return base64_encode($sign);
    }

    public function curlPost($url = '', $postData = array(), $options = array())
    {
        if (is_array($postData)) {
            $postData = json_encode($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:'.$this->auth,
            'Content-Type:application/json',
            'Accept:application/json',
            'User-Agent:'.$_SERVER['HTTP_USER_AGENT']
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function getSchema()
    {
        return 'WECHATPAY2-SHA256-RSA2048';
    }

    public function getAuthStr($requestUrl,$reqParams=array())
    {
        $schema = $this->getSchema();
        $token = $this->getToken($requestUrl,$reqParams);
        $this->auth = $schema.' '.$token;
        return $this->auth;
    }

    private function getNonce()
    {
        static $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 32; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function getToken($requestUrl,$reqParams=array())
    {
        $body = $reqParams ?  json_encode($reqParams) : '';
        $nonce = $this->getNonce();
        $timestamp = time();
        $message = $this->buildMessage($nonce, $timestamp, $requestUrl,$body);
        $sign = $this->sign($message);
        $serialNo = $this->serialNumber;
        return sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->mchid, $nonce, $timestamp, $serialNo, $sign
        );
    }

    private function buildMessage($nonce, $timestamp, $requestUrl, $body = '')
    {
        $method = 'POST';
        $urlParts = parse_url($requestUrl);
        $canonicalUrl = ($urlParts['path'] . (!empty($urlParts['query']) ? "?{$urlParts['query']}" : ""));
        return strtoupper($method) . "\n" .
            $canonicalUrl . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";
    }

    /**
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        $url = $this->__CreateOauthUrlForOpenid($code);
        $res = self::curlGet($url);
        //取出openid
        $data = json_decode($res,true);
//        $this->data = $data;
        $openid = $data['openid'];
        return $openid;
    }

    public static function curlGet($url = '', $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /**
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["secret"] = $this->appKey;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     * 拼接签名字符串
     * @param array $urlObj
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign") $buff .= $k . "=" . $v . "&";
        }
        $buff = trim($buff, "&");
        return $buff;
    }
}