<?php

declare(strict_types=1);

namespace nova\plugin\pay\controller;

use nova\framework\http\Response;
use nova\plugin\login\controller\BaseAPIController;
use nova\plugin\pay\PayConfig;

class Config extends BaseAPIController
{
    public function config(): Response
    {
        $payConfig = new PayConfig();

        if ($this->request->isGet()) {
            return Response::asJson([
                'code' => 200,
                'data' => get_object_vars($payConfig),
            ]);
        }

        $payConfig->url = (string)$this->request->post('url', $payConfig->url);
        $payConfig->client_id = (string)$this->request->post('client_id', $payConfig->client_id);
        $payConfig->client_secret = (string)$this->request->post('client_secret', $payConfig->client_secret);

        return Response::asJson([
            'code' => 200,
            'msg' => '支付配置保存成功',
        ]);
    }
}
