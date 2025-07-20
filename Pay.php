<?php

declare(strict_types=1);

namespace nova\plugin\pay;

use nova\framework\core\StaticRegister;

use function nova\framework\dump;

use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;
use nova\plugin\cookie\Session;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpException;
use nova\plugin\notify\Notify;

class Pay extends StaticRegister
{
    private PayConfig $config;
    const int PAYMENT_METHOD_ALIPAY = 2;      // 支付宝扫码（当面付）支付
    const int PAYMENT_METHOD_WECHAT_APP = 3;  // 微信APP(赞赏码)支付
    const int PAYMENT_METHOD_ALIPAY_APP = 4;  // 支付宝APP（收款码）支付
    public function __construct()
    {
        $this->config = new PayConfig();
        Session::getInstance()->start();
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
        array  $extra_param = []
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
     * @throws HttpException
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
        } catch (HttpException|PayException|AppExitException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PayException('创建订单时发生错误: ' . $e->getMessage());
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

    const string CONFIG_TPL = ROOT_PATH . DS . 'nova' . DS . 'plugin' . DS . 'pay' . DS . 'tpl' . DS . 'pay';

    public static function registerInfo(): void
    {
        // 添加路由前事件监听器
        EventManager::addListener("route.before", function ($event, &$data) {
            // 检查必要的依赖类是否存在
            if (!class_exists('\nova\plugin\cookie\Session') || !class_exists('\nova\plugin\login\LoginManager')) {
                return;
            }

            // 检查用户是否已登录
            if (!\nova\plugin\login\LoginManager::getInstance()->checkLogin()) {
                return;
            }

            if ($data == "/pay/config") {
                // 创建Webhook配置对象
                $payConfig = new PayConfig();

                // GET请求：返回配置信息
                if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                    throw new AppExitException(Response::asJson([
                        'code' => 200,
                        'data' => get_object_vars($payConfig),
                    ]));
                }
                // POST请求：保存配置信息
                else {
                    // dump($_POST);
                    // $data = $_POST;
                    // 更新配置参数，使用POST数据或保持默认值
                    $payConfig->url = $_POST['url'] ?? $payConfig->url;
                    $payConfig->client_id = $_POST['client_id'] ?? $payConfig->client_id;
                    $payConfig->client_secret = ($_POST['client_secret'] ?? $payConfig->client_secret);

                    throw new AppExitException(Response::asJson([
                        'code' => 200,
                        'msg' => '支付配置保存成功'
                    ]));
                }
            }
        });
    }
}
