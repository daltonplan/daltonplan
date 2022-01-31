<?php

declare(strict_types=1);

namespace frame;

class password
{
    static function pin(\Base $fw): int
    {
        $max = $fw->get('user_pin_length');
        return password::pin_max($max);
    }

    static function pin_max(int $max_length): int
    {
        $min = '';
        $max = '';

        for ($i = 0; $i < $max_length; $i++) {
            $min .= '1';
            $max .= '9';
        }

        return mt_rand(intval($min), intval($max));
    }

    static function hash(\Base $fw, string $password): string
    {
        $pepper = $fw->get('pw_pepper');
        $cost = $fw->get('pw_hash_cost');
        $pwd_peppered = hash_hmac("sha256", $password, $pepper);
        return password_hash($pwd_peppered, PASSWORD_DEFAULT, array("cost" => $cost));
    }

    static function hash_pin(\Base $fw, int $password): string
    {
        return password::hash($fw, strval($password));
    }

    static function check(\Base $fw, string $password, string $hash): bool
    {
        $pepper = $fw->get('pw_pepper');
        $pwd_peppered = hash_hmac("sha256", $password, $pepper);
        return password_verify($pwd_peppered, $hash);
    }

    static function needs_rehash(\Base $fw, string $hash): bool
    {
        $cost = $fw->get('pw_hash_cost');
        return password_needs_rehash($hash, PASSWORD_DEFAULT, array("cost" => $cost));
    }

    function calc_cost(\Base $fw): void
    {
        if (!$fw->get('test'))
            return;

        $time_target = 0.2;

        $cost = 9;
        do {
            $cost++;
            $start = microtime(true);
            password_hash("test", PASSWORD_DEFAULT, ["cost" => $cost]);
            $end = microtime(true);
        } while (($end - $start) < $time_target);

        echo "Appropriate cost found: " . $cost;
    }

    function calc_pwd(\Base $fw): void
    {
        if (!$fw->get('test'))
            return;

        $password = $fw->get('PARAMS.password');
        $pwd_hashed = password::hash($fw, $password);

        echo "Hash: " . $pwd_hashed;
    }
}
