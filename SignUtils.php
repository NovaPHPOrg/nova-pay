<?php

declare(strict_types=1);
/*******************************************************************************
 * Copyright (c) 2022. Ankio. All Rights Reserved.
 ******************************************************************************/

/**
 * Package: app\sign
 * Class SignUtils
 * Created By ankio.
 * Date : 2023/4/28
 * Time : 14:36
 * Description : 签名工具类，用于处理数据签名和验证
 */

namespace nova\plugin\pay;

use nova\framework\core\Context;
use nova\framework\core\Logger;

/**
 * SignUtils 类
 *
 * 提供了一套安全的数据签名和验证机制
 * 使用 MD5 算法进行签名，支持调试模式下的日志记录
 */
class SignUtils
{
    /**
     * 验证签名是否正确
     *
     * @param  array  $args 需要验证的参数数组
     * @param  string $key  签名密钥
     * @return bool   签名验证结果
     */
    public static function checkSign(array $args, string $key): bool
    {
        if (!isset($args['sign'])) {
            return false;
        }

        $sign = trim($args['sign']);
        unset($args['sign']);
        return hash_equals($sign, self::getSign($args, $key));
    }

    /**
     * 生成签名字符串
     *
     * @param  array  $args      需要签名的参数数组
     * @param  string $secretKey 签名密钥
     * @return string 生成的签名
     */
    private static function getSign(array $args, string $secretKey): string
    {
        // 过滤空值
        $args = array_filter($args, function ($value) {
            return $value !== '' && $value !== null;
        });

        // 按键名ASCII码从小到大排序（字典序）
        ksort($args);

        // 构建签名字符串 stringA
        $stringA = self::formatBizQueryParaMap($args);

        // 在stringA最后拼接上 &key=密钥 得到 stringSignTemp
        $stringSignTemp = $stringA . "&key=" . $secretKey;

        // 调试模式下记录日志
        if (Context::instance()->isDebug()) {
            Logger::info("签名结构", [$stringSignTemp]);
        }

        // 对stringSignTemp进行MD5运算，再将得到的字符串所有字符转换为大写
        return strtoupper(md5($stringSignTemp));
    }

    /**
     * 格式化参数为查询字符串
     *
     * @param  array  $paraMap 参数数组
     * @return string 格式化后的查询字符串 (key1=value1&key2=value2...)
     */
    private static function formatBizQueryParaMap(array $paraMap): string
    {
        if (empty($paraMap)) {
            return '';
        }

        $pairs = [];
        foreach ($paraMap as $key => $value) {
            $pairs[] = $key . "=" . $value;
        }

        return implode('&', $pairs);
    }

    /**
     * 为数据添加签名
     *
     * @param  array  $array 需要签名的数据数组
     * @param  string $key   签名密钥
     * @return array  包含签名的数据数组
     */
    public static function sign(array $array, string $key): array
    {
        if (Context::instance()->isDebug()) {
            Logger::info("签名数据", $array);
            Logger::info("签名密钥", [$key]);
        }

        $array['sign'] = self::getSign($array, $key);
        return $array;
    }
}
