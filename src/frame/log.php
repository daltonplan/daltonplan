<?php

declare(strict_types=1);

namespace frame;

class log
{
    static function setup(\Base $fw): void
    {
        log::set_logger($fw);
        log::set_info($fw);
    }

    static function trace(\Base $fw): \Log
    {
        return $fw->logger_trace;
    }

    static function debug(\Base $fw): \Log
    {
        return $fw->logger_debug;
    }

    static function info(\Base $fw): \Log
    {
        return $fw->logger_info;
    }

    static function warn(\Base $fw): \Log
    {
        return $fw->logger_warn;
    }

    static function error(\Base $fw): \Log
    {
        return $fw->logger_error;
    }

    static function critical(\Base $fw): \Log
    {
        return $fw->logger_critical;
    }

    static function roles(\Base $fw): \Log
    {
        return $fw->logger_roles;
    }

    static function set_logger(\Base $fw): void
    {
        $fw->logger_trace = new \Log('trace.log');
        $fw->logger_debug = new \Log('debug.log');
        $fw->logger_info = new \Log('info.log');
        $fw->logger_warn = new \Log('warn.log');
        $fw->logger_error = new \Log('error.log');
        $fw->logger_critical = new \Log('critical.log');
        $fw->logger_roles = new \Log('roles.log');
    }

    static function set_info(\Base $fw): void
    {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $fw->set('url', $url);

        $ip = $_SERVER['REMOTE_ADDR'];
        $fw->set('ip', $ip);
        $re = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $fw->set('re', $re);
        $ag = $_SERVER['HTTP_USER_AGENT'];
        $fw->set('ag', $ag);
    }
}
