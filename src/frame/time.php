<?php

declare(strict_types=1);

namespace frame;

class time
{
    const week_day = 'week_day';

    static function get(\Base $fw, null|string $time = null): int
    {
        if ($time !== null)
            return strtotime($time) + $fw->get('server_timezone');

        return time() + $fw->get('server_timezone');
    }

    static function get_for_db(\Base $fw): string
    {
        return time::to_db_date_time(time::get($fw));
    }

    static function to_db_date_time(int $time): string
    {
        return date('Y-m-d H:i:s', $time);
    }

    static function to_db_date_time_str(string $date_time): string
    {
        return date('Y-m-d H:i:s', strtotime($date_time));
    }

    static function to_db_date(int $time): string
    {
        return date('Y-m-d', $time);
    }

    static function to_db_time(int $time): string
    {
        return date('H:i:s', $time);
    }

    static function to_db_time_short(int $time): string
    {
        return date("H:i", $time);
    }

    static function to_ui_date_time(string $time): string
    {
        return date('m/d/Y h:i A', strtotime($time));
    }

    static function get_only_date_for_db(\Base $fw): string
    {
        return time::to_db_date(time::get($fw));
    }

    static function get_only_time_for_db(\Base $fw): string
    {
        return time::to_db_time(time::get($fw));
    }

    // ---

    static function get_date_format(\Base $fw): string
    {
        return $fw->get('DICT.date_format');
    }

    static function get_format_date(\Base $fw, int $time): string
    {
        return date(time::get_date_format($fw), $time);
    }

    static function to_date(\Base $fw, string $time): string
    {
        return time::get_format_date($fw, strtotime($time));
    }

    // ---

    static function get_time_format(\Base $fw): string
    {
        return $fw->get('DICT.time_format');
    }

    static function get_format_time(\Base $fw, int $time): string
    {
        return date(time::get_time_format($fw), $time);
    }

    static function to_time(\Base $fw, string $time): string
    {
        return time::get_format_time($fw, strtotime($time));
    }

    // ---

    static function get_date_time_format(\Base $fw): string
    {
        return $fw->get('DICT.date_time_format');
    }

    static function get_format(\Base $fw, int $time): string
    {
        return date(time::get_date_time_format($fw), $time);
    }

    static function to_date_time(\Base $fw, string $time): string
    {
        return time::get_format($fw, strtotime($time));
    }

    // ---

    static function get_request_time(\Base $fw): string
    {
        $minutes = (int)$fw->get('request_minutes');
        return $minutes . ' minutes';
    }

    static function get_request_time_limit(\Base $fw): string
    {
        $now = time::get($fw);

        $substraction = '-' . time::get_request_time($fw);
        $limit = strtotime($substraction, $now);
        return time::to_db_date_time($limit);
    }

    static function request_time_expired(\Base $fw, string $check): bool
    {
        return time::get($fw) >= strtotime('+' . time::get_request_time($fw), strtotime($check));
    }

    static function request_time_active(\Base $fw, array $requests): bool
    {
        foreach ($requests as $request) {
            if (!\frame\time::request_time_expired($fw, $request['updated']))
                return true;
        }

        return false;
    }

    static function get_request_time_expired(\Base $fw, array $requests): array
    {
        foreach ($requests as $request) {
            if (\frame\time::request_time_expired($fw, $request['updated']))
                return $request;
        }

        return array();
    }

    static function set(\Base $fw): void
    {
        $time = time::get($fw);

        $fw->set('date_time', date('m/d/Y h:i A', $time));

        if ($fw->exists('DICT.date_time_format'))
            $fw->set('date_time_lang', time::get_format($fw, $time));

        $fw->set('hour', date('H', $time));
    }

    const mo = "mo";
    const tu = "tu";
    const we = "we";
    const th = "th";
    const fr = "fr";

    const monday = 'monday';
    const tuesday = 'tuesday';
    const wednesday = 'wednesday';
    const thursday = 'thursday';
    const friday = 'friday';

    static function weekdays_short(): array
    {
        return array(
            1 => time::mo,
            2 => time::tu,
            3 => time::we,
            4 => time::th,
            5 => time::fr,
        );
    }

    static function weekdays_full(): array
    {
        return array(
            1 => time::monday,
            2 => time::tuesday,
            3 => time::wednesday,
            4 => time::thursday,
            5 => time::friday,
        );
    }

    static function get_week_day(string $date): int
    {
        return (int)date('w', strtotime($date));
    }

    static function set_week_day(\Base $fw, string $date): void
    {
        $fw->set(time::week_day, time::get_week_day($date));
    }

    static function same_week(\Base $fw, int $time_a, int $time_b): bool
    {
        $monday_a = strtotime('monday this week', $time_a);
        $monday_b = strtotime('monday this week', $time_b);
        return $monday_a === $monday_b;
    }
}
