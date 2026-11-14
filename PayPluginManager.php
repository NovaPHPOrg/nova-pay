<?php

declare(strict_types=1);

namespace nova\plugin\pay;

use nova\framework\core\StaticRegister;
use nova\framework\route\RouteTrait;
use nova\plugin\login\AdminPage;
use nova\plugin\login\route\Permission;

/**
 * 支付插件启动器（对齐 MailPluginManager）。
 */
class PayPluginManager extends StaticRegister
{
    use RouteTrait;

    public function __construct()
    {
        $this->controllerNamespace = 'nova\\plugin\\pay\\controller\\';
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->getOrPost('/pay/api/config', $this->map('config', 'config'));
    }

    public static function registerInfo(): void
    {
        Permission::getInstance()->registerPermissions('支付配置', 'pay_manage', [
            'GET /pay/config',
            'ANY /pay/api*',
        ]);

        self::getInstance()->bindPrefixDispatch('/pay');
        AdminPage::bind(PayAdminTpl::getInstance());
    }
}
