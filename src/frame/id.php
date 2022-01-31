<?php

declare(strict_types=1);

namespace frame;

use \account\user;

class id
{
    static function gen(bool $easy_read = true): string
    {
        return id::gen_handle(user::handle_length, $easy_read);
    }

    static function gen_handle(string $fw_max_length = 'handle_length', bool $easy_read = false): string
    {
        $fw = \frame\base::get();

        $max_length = $fw->get($fw_max_length);
        return id::gen_max($max_length, $easy_read);
    }

    static function gen_max(int $max_length, bool $easy_read = true): string
    {
        $possible_keys = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        if ($easy_read)
            $possible_keys = '123456789BCDFGHKLMNPQRTVWXYZ';

        $keys_length = strlen($possible_keys);
        $str = "";

        $i = 0;
        while ($i < $max_length) {
            $rand = mt_rand(1, $keys_length - 1);
            $str .= $possible_keys[$rand];
            $i++;
        }
        return $str;
    }
}
