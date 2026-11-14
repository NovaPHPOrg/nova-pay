<?php

declare(strict_types=1);

namespace nova\plugin\pay;

use nova\framework\exception\AppExitException;
use nova\plugin\cookie\Session;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpException;

/**
 * 支付 SDK：对接支付站创建订单、查询状态、通知用户。
 *
 * 后台配置页由 PayAdminTpl 挂载；API 由 PayPluginManager 注册。
 */
class Pay
{
    const string CONFIG_TPL = ROOT_PATH . DS . 'nova' . DS . 'plugin' . DS . 'pay' . DS . 'tpl' . DS . 'config';
    private PayConfig $config;
    const int PAYMENT_METHOD_AFD = 5;
    public function __construct()
    {
        $this->config = new PayConfig();
    }

    /**
     * 创建支付订单
     *
     * 该方法会构建订单参数，生成签名，并通过HTTP请求发送到支付平台创建订单。
     * 请求使用form表单格式，包含时间戳和签名验证。
     *
     * @param float  $original_price 订单原价（元），必须大于0，由商户提交的商品原始价格
     * @param string $product_name   商品名称，将显示在支付页面上的商品描述
     * @param int    $payment_method 支付方式，对应OrderModel中定义的支付方式常量，如：
     *                               - PAYMENT_METHOD_WECHAT_APP: 微信APP支付
     *                               - PAYMENT_METHOD_ALIPAY_APP: 支付宝APP支付
     *                               - PAYMENT_METHOD_ALIPAY: 支付宝网页支付
     * @param string $notify_url     异步通知链接，支付平台通知商户支付结果的URL，必须是有效的URL
     * @param string $return_url     同步通知链接，用户支付完成后页面跳转的URL，必须是有效的URL
     * @param array  $extra_param    附加业务参数，商户可以传递自定义参数，将在支付回调时原样返回
     * @param string $email          支付用户邮箱（可选），用于之后调用 sendMessage 发送邮件
     *
     * @return array 返回支付平台响应的订单数据，包含订单号、支付链接等信息
     *
     * @throws HttpException 当HTTP请求失败或响应状态码不是200时抛出
     * @throws PayException
     * @example
     * $pay = new Pay();
     * $result = $pay->createOrder(
     *     99.99,                                    // 原价
     *     12345,                                    // 商户ID
     *     "高级会员服务",                            // 商品名称
     *     1,                                        // 支付方式
     *     "https://example.com/notify",            // 异步通知URL
     *     "https://example.com/return",            // 同步通知URL
     *     "user_id=123&order_type=premium"        // 附加参数
     * );
     */
    public function createOrder(
        float  $original_price,
        string $product_name,
        int    $payment_method = 0,
        string $notify_url = "",
        string $return_url = "",
        array  $extra_param = [],
        string $email = ""
    ): array {
        // 构建订单参数
        $orderData = [
            'original_price' => $original_price,
            'merchant_id' => $this->config->client_id,
            'product_name' => $product_name,
            'payment_method' => $payment_method,
            'notify_url' => $notify_url,
            'return_url' => $return_url,
            'extra_param' => json_encode($extra_param),
            'email' => $email,
        ];

        $hash = md5(json_encode($orderData));

        $order = Session::getInstance()->get("order_" . $hash);
        if (!empty($order)) {
            return $order;
        }

        $orderData['t'] = time();

        // 使用SignUtils生成签名
        $signedData = SignUtils::sign($orderData, $this->config->client_secret);

        try {
            // 使用HttpClient发起form表单请求
            $response = HttpClient::init($this->config->url)
                ->post($signedData, 'form')
                ->send('/create');

            // 解析响应
            $responseData = json_decode($response->getBody(), true);

            if ($response->getHttpCode() !== 200) {
                throw new PayException('创建订单失败: HTTP ' . $response->getHttpCode());
            }

            if ($responseData['code'] != 200) {
                throw new PayException($responseData['msg']);
            }
            $order = $responseData['data'];
            Session::getInstance()->set("order_" . $hash, $order, 300);
            return $order;
        } catch (HttpException|PayException|AppExitException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PayException('创建订单时发生错误: ' . $e->getMessage());
        }
    }

    /**
     * @throws PayException
     */
    public function state(string $order_id)
    {
        $orderData = [
            'merchant_id' => $this->config->client_id,
            'order_id' => $order_id,
        ];
        $orderData['t'] = time();
        $signedData = SignUtils::sign($orderData, $this->config->client_secret);

        try {
            // 使用HttpClient发起form表单请求
            $response = HttpClient::init($this->config->url)
                ->post($signedData, 'form')
                ->send('/state');

            // 解析响应
            $responseData = json_decode($response->getBody(), true);

            if ($response->getHttpCode() !== 200) {
                throw new PayException('查询订单失败: HTTP ' . $response->getHttpCode());
            }

            if ($responseData['code'] != 200) {
                throw new PayException($responseData['msg']);
            }
            return $responseData['data'];
        } catch (HttpException|PayException $e) {
            throw new PayException('检查订单时发生错误: ' . $e->getMessage());
        }
    }

    /**
     * 请求支付站向该订单的支付用户发通知。
     *
     * SDK 只传内容：标题、发件人名字、纯文本、HTML。
     * 订单下单时传了 email 就发邮件（没有则跳过），再交给对应支付渠道自行推送。
     *
     * @return array{mail: bool, channel: bool}
     * @throws PayException
     * @throws HttpException
     */
    public function sendMessage(
        string $order_id,
        string $title,
        string $from_name,
        string $text,
        string $html
    ): array {
        $payload = [
            'merchant_id' => $this->config->client_id,
            'order_id' => $order_id,
            'title' => $title,
            'from_name' => $from_name,
            'text' => $text,
            'html' => $html,
            't' => time(),
        ];
        $signed = SignUtils::sign($payload, $this->config->client_secret);

        try {
            $response = HttpClient::init($this->config->url)
                ->post($signed, 'form')
                ->send('/message');
            $responseData = json_decode($response->getBody(), true);

            if ($response->getHttpCode() !== 200) {
                throw new PayException('用户通知失败: HTTP ' . $response->getHttpCode());
            }
            if (($responseData['code'] ?? 0) != 200) {
                throw new PayException((string)($responseData['msg'] ?? '用户通知失败'));
            }

            /** @var array{mail?: bool, channel?: bool} $data */
            $data = is_array($responseData['data'] ?? null) ? $responseData['data'] : [];

            return [
                'mail' => (bool)($data['mail'] ?? false),
                'channel' => (bool)($data['channel'] ?? false),
            ];
        } catch (HttpException|PayException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PayException('用户通知时发生错误: ' . $e->getMessage());
        }
    }

    /**
     * @throws SignException
     */
    public function checkSign(array $data): bool
    {
        // 1. 验证时间戳
        $currentTime = time();
        $timeWindow = 300; // 5分钟的时间窗口

        $t = $data['t'] ?? 0;

        if ($t <= 0) {
            throw new SignException("时间戳不能为空");
        }

        if (abs($currentTime - $t) > $timeWindow) {
            throw new SignException("请求已过期，请检查系统时间");
        }

        if (!SignUtils::checkSign($data, $this->config->client_secret)) {
            throw new SignException("签名验证失败");
        }
        return true;
    }
}
