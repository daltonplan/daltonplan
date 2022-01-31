<?php

declare(strict_types=1);

namespace frame;

class lang
{
    static function setup(\Base $fw): void
    {
        $languages = $fw->get('LANGUAGE');
        $current = reset(explode(',', $languages));

        if ($current === 'de-DE')
            $current = 'de';
        else if ($current === 'en-US')
            $current = 'en';

        $fw->set('lang', $current);

        if ($fw->exists('COOKIE.dp_lang')) {
            $lang = $fw->get('COOKIE.dp_lang');

            if (($lang === 'de') || ($lang === 'en')) {
                $fw->set('LANGUAGE', $lang);
                $fw->set('lang', $lang);
            } else
                $fw->clear('COOKIE.dp_lang');
        }
    }
}
