<?php

declare(strict_types=1);

namespace nova\plugin\pay;

use nova\framework\core\Instance;
use nova\framework\http\Request;
use nova\framework\http\Response;

use function nova\framework\route;

use nova\framework\route\Route;
use nova\plugin\login\AdminPageInterface;
use nova\plugin\tpl\ViewResponse;

/**
 * 支付配置后台页面（AdminPage）。
 */
class PayAdminTpl extends Instance implements AdminPageInterface
{
    public function registerRouter(string $model, string $controller): void
    {
        $default = route($model, $controller, 'init');
        Route::getInstance()
            ->get('/pay/config', $default);
    }

    public function route(ViewResponse $view, Request $request): ?Response
    {
        if ($request->getPath() !== '/pay/config') {
            return null;
        }

        return $view->asTpl(Pay::CONFIG_TPL);
    }

    public function menu(): array
    {
        return [
            'title' => '支付配置',
            'icon' => 'payments',
            'url' => '/pay/config',
            'pjax' => true,
        ];
    }
}
