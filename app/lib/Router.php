<?php
/**
 * 路由分发: s=/controller/action
 */
class Router
{
    public static function dispatch($route, $adminPrefix = false)
    {
        $route = trim((string)$route, '/');
        if ($route === '') $route = 'home/index';
        $parts = explode('/', $route);
        $controller = preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]);
        $action = isset($parts[1]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $parts[1]) : 'index';
        if ($controller === '') $controller = 'home';
        if ($action === '') $action = 'index';

        $ctrlClass = ucfirst($controller) . 'Controller';
        if ($adminPrefix && strtolower($controller) !== 'admin') {
            $ctrlClass = 'AdminController';
            $action = $controller;
        }
        $methodName = 'action' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($action))));

        if (!class_exists($ctrlClass)) {
            http_response_code(404);
            exit('页面不存在(控制器未找到)');
        }
        $ctrl = new $ctrlClass();
        if (method_exists($ctrl, 'before')) {
            if ($ctrl->before($methodName) === false) return;
        }
        if (!method_exists($ctrl, $methodName)) {
            http_response_code(404);
            exit('页面不存在(方法未找到)');
        }
        call_user_func([$ctrl, $methodName]);
    }
}
