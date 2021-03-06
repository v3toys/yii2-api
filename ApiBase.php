<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.07.2016
 */
namespace v3toys\yii2\api;

use skeeks\cms\helpers\StringHelper;
use v3toys\yii2\api\models\ApiResponseError;
use v3toys\yii2\api\models\ApiResponseOk;
use yii\base\Component;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * Class ApiBase
 * @property string $version read-only
 *
 * @see http://www.v3toys.ru/index.php?nid=api
 * @package v3toys\yii2\api
 */
abstract class ApiBase extends Component
{
    /**
     * версия api, на настоящий момент 0.4
     */
    const VERSION = '0.4';

    /**
     * @var string
     */
    public $url = 'https://v3toys.ru/pear-www/Kiwi_Shop/api.php';

    /**
     * @var null|string ключ аффилиата в системе V3Project, если он передается то контроль доступа происходит по IP+"affiliate_key"
     */
    public $affiliate_key = '';




    /**
     * @var int set timeout to 15 seconds for the case server is not responding
     */
    public $timeout = 30;

    /**
     * @var int
     */
    public $maxRedirects = 2;

    /**
     * @param array $data
     * @param string $method
     *
     * @return ApiResponseOk|ApiResponseError
     */
    private function _send(array $data, $method = 'post')
    {
        $client = new Client([
            'requestConfig' => [
                'format' => Client::FORMAT_JSON
            ]
        ]);

        $request = $client->createRequest()
                ->setMethod($method)
                ->setUrl($this->url)
                ->addHeaders(['Content-type' => 'application/json'])
                ->addHeaders(['user-agent' => 'JSON-RPC PHP Client'])
                ->setData($data)
                ->setOptions([
                    'timeout' => $this->timeout,
                    'maxRedirects' => $this->maxRedirects,
                ]);

        $response= $request->send();

        //Нам сказали это всегда json. А... нет, все мы люди, бывает и не json )
        try
        {
            $dataResponse = (array) Json::decode($response->content);
        } catch (\Exception $e)
        {
            \Yii::error("Json api response error: " . $e->getMessage() . ". Response: \n{$response->content}", self::className());
            //Лайф хак, вдруг разработчики апи оставили var dump
            if ($pos = strpos($response->content, "{"))
            {
                $content = StringHelper::substr($response->content, $pos, StringHelper::strlen($response->content));

                try
                {
                    $dataResponse = (array) Json::decode($content);
                } catch (\Exception $e)
                {
                    \Yii::error("Api response error: " . $response->content, self::className());
                }
            }
        }


        if (!$response->isOk)
        {
            \Yii::error($response->content, self::className());
            $responseObject = new ApiResponseError($dataResponse);
        } else
        {
            $responseObject = new ApiResponseOk($dataResponse);
        }

        $responseObject->request = $request;
        $responseObject->response = $response;
        $responseObject->statusCode = $response->statusCode;

        return $responseObject;
    }

    /**
     * @param $method           вызываемый метод, список приведен далее
     * @param array $params     параметры соответствующие методу запроса
     *
     * @return ApiResponseError|ApiResponseOk
     */
    public function send($method, array $params = [])
    {
        $request = [
            'v'                 => static::VERSION,
            'affiliate_key'     => $this->affiliate_key,
            'method'            => $method,
            "params"            => $params
        ];

        return $this->_send($request);
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return static::VERSION;
    }
}