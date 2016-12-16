<?php

namespace PayumTW\Ecpay;

use Http\Message\MessageFactory;
use Payum\Core\HttpClientInterface;
use PayumTW\Ecpay\Bridge\Ecpay\Device;
use PayumTW\Ecpay\Bridge\Ecpay\IsCollection;
use PayumTW\Ecpay\Bridge\Ecpay\LogisticsType;
use PayumTW\Ecpay\Bridge\Ecpay\EcpayLogistics;
use PayumTW\Ecpay\Bridge\Ecpay\LogisticsSubType;
use PayumTW\Ecpay\Bridge\Ecpay\Temperature;
use PayumTW\Ecpay\Bridge\Ecpay\Distance;
use PayumTW\Ecpay\Bridge\Ecpay\Specification;
use PayumTW\Ecpay\Bridge\Ecpay\ScheduledPickupTime;

class EcpayLogisticsApi extends Api
{
    /**
     * $client.
     *
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * MessageFactory.
     *
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * $options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * $api.
     *
     * @var \PayumTW\Ecpay\Bridge\Ecpay\EcpayLogistics
     */
    protected $api;

    /**
     * @var array
     */
    protected $code = [];

    /**
     * @param array               $options
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     *
     * @throws \Payum\Core\Exception\InvalidArgumentException if an option is invalid
     */
    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory, EcpayLogistics $api = null)
    {
        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
        $this->api = is_null($api) === true ? new EcpayLogistics() : $api;
        $this->api->HashKey = $this->options['HashKey'];
        $this->api->HashIV = $this->options['HashIV'];
        $this->api->Send['MerchantID'] = $this->options['MerchantID'];
    }

    /**
     * getApiEndpoint.
     *
     * @return string
     */
    public function getApiEndpoint($name = 'AioCheckOut')
    {
        return $this->api->ServiceURL;
    }

    /**
     * createTransaction.
     *
     * @param array $params
     * @param mixed $request
     *
     * @return array
     */
    public function createCvsMapTransaction(array $params)
    {
        $this->api->Send = array_merge($this->api->Send, [
            'ServerReplyURL' => '',
            'MerchantTradeNo' => '',
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'LogisticsSubType' => LogisticsSubType::UNIMART,
            'IsCollection' => IsCollection::NO,
            'Device' => $this->isMobile() ? Device::MOBILE : Device::PC,
        ]);

        $this->api->Send = array_replace(
            $this->api->Send,
            array_intersect_key($params, $this->api->Send)
        );

        return $this->api->CvsMap();
    }

    /**
     * createTransaction.
     *
     * @param array $params
     * @param mixed $request
     *
     * @return array
     */
    public function createTransaction(array $params)
    {
        if ($params['GoodsAmount'] === 0) {
            return $this->createCvsMapTransaction($params);
        }

        $this->api->Send = array_merge($this->api->Send, [
            'MerchantTradeNo' => '',
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'LogisticsType' => '',
            'LogisticsSubType' => LogisticsSubType::UNIMART,
            'GoodsAmount' => 0,
            'CollectionAmount' => 0,
            'IsCollection' => IsCollection::NO,
            'GoodsName' => '',
            'SenderName' => '',
            'SenderPhone' => '',
            'SenderCellPhone' => '',
            'ReceiverName' => '',
            'ReceiverPhone' => '',
            'ReceiverCellPhone' => '',
            'ReceiverEmail' => '',
            'TradeDesc' => '',
            'ServerReplyURL' => '',
            'LogisticsC2CReplyURL' => '',
            'Remark' => '',
            'PlatformID' => '',
        ]);

        $this->api->SendExtend = [];

        $this->api->Send = array_replace(
            $this->api->Send,
            array_intersect_key($params, $this->api->Send)
        );

        if (empty($this->api->Send['LogisticsType']) === true) {
            $this->api->Send['LogisticsType'] = LogisticsType::CVS;
            switch ($this->api->Send['LogisticsSubType']) {
                case LogisticsSubType::TCAT:
                    $this->api->Send['LogisticsType'] = LogisticsType::HOME;
                    break;
            }
        }

        if ($this->api->Send['IsCollection'] === IsCollection::NO) {
            $this->api->Send['CollectionAmount'] = 0;
        } elseif (isset($this->api->Send['CollectionAmount']) === false) {
            $this->api->Send['CollectionAmount'] = (int) $this->api->Send['GoodsAmount'];
        }

        switch ($this->api->Send['LogisticsType']) {
            case LogisticsType::HOME:
                $this->api->SendExtend = array_merge($this->api->SendExtend, [
                    'SenderZipCode' => '',
                    'SenderAddress' => '',
                    'ReceiverZipCode' => '',
                    'ReceiverAddress' => '',
                    'Temperature' => '',
                    'Distance' => '',
                    'Specification' => '',
                    'ScheduledDeliveryTime' => '',
                ]);
                break;
            case LogisticsType::CVS:
                $this->api->SendExtend = array_merge($this->api->SendExtend, [
                    'ReceiverStoreID' => '',
                    'ReturnStoreID' => '',
                ]);
                break;
        }

        $this->api->SendExtend = array_replace(
            $this->api->SendExtend,
            array_intersect_key($params, $this->api->SendExtend)
        );

        return $this->api->BGCreateShippingOrder();
    }

    /**
     * refundTransaction.
     *
     * @param  array $params
     *
     * @return array
     */
    public function refundTransaction($params)
    {
        if ($params['LogisticsSubType'] === LogisticsSubType::TCAT) {
            // 宅配逆物流訂單產生
            $method = 'CreateHomeReturnOrder';
            $supportedParams = [
                'AllPayLogisticsID'     => '',
                'LogisticsSubType'      => '',
                'ServerReplyURL'        => '',
                'SenderName'            => '',
                'SenderPhone'           => '',
                'SenderCellPhone'       => '',
                'SenderZipCode'         => '',
                'SenderAddress'         => '',
                'ReceiverName'          => '',
                'ReceiverPhone'         => '',
                'ReceiverCellPhone'     => '',
                'ReceiverZipCode'       => '',
                'ReceiverAddress'       => '',
                'GoodsAmount'           => '',
                'GoodsName'             => '',
                'Temperature'           => Temperature::ROOM,
                'Distance'              => Distance::SAME,
                'Specification'         => Specification::CM_60,
                'ScheduledPickupTime'   => ScheduledPickupTime::UNLIMITED,
                'ScheduledDeliveryTime' => '',
                'Remark'                => '',
                'PlatformID'            => '',
            ];
        } else if (isset($params['LogisticsSubType']) === true){
            // LogisticsSubType::FAMILY = 'FAMI'; // 全家
            // LogisticsSubType::UNIMART = 'UNIMART'; // 統一超商
            // LogisticsSubType::FAMILY_C2C = 'FAMIC2C'; // 全家店到店
            // LogisticsSubType::UNIMART_C2C = 'UNIMARTC2C'; // 統一超商寄貨便

            // 超商取貨逆物流訂單(全家超商B2C).
            $method = 'CreateFamilyB2CReturnOrder';
            $supportedParams = [
                'AllPayLogisticsID' => '',
                'ServerReplyURL'    => '',
                'GoodsName'         => '',
                'GoodsAmount'       => 0,
                'SenderName'        => '',
                'SenderPhone'       => '',
                'Remark'            => '',
                'Quantity'          => '',
                'Cost'              => '',
                'PlatformID'        => '',
            ];
        } else {
            // 全家逆物流核帳(全家超商B2C).
            $method = 'CheckFamilyB2CLogistics';
            $supportedParams = [
                'RtnMerchantTradeNo' => '',
                'PlatformID'         => '',
            ];
        }

        $this->api->Send = array_merge($this->api->Send, $supportedParams);

        $this->api->Send = array_replace(
            $this->api->Send,
            array_intersect_key($params, $this->api->Send)
        );

        return call_user_func_array([$this->api, $method]);
    }

    /**
     * getTransactionData.
     *
     * @param mixed $params
     *
     * @return array
     */
    public function getTransactionData($params)
    {
        $details = [];
        if (empty($params['response']) === false) {
            $details = $params['response'];
        } else {
            $details = $params;
        }

        return $details;
    }
}