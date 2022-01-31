<?php

declare(strict_types=1);

namespace frame;

class base
{
    const max_handle_length = 'max_handle_length';
    const repo = 'repo';

    static function get(): \Base
    {
        return \Base::instance();
    }

    static function check_handle(string $handle): bool
    {
        $regex = '~[a-zA-Z0-9\-]+~';
        return preg_match($regex, $handle) === 1;
    }

    static function redirect(\Base $fw): void
    {
        $fw->reroute('/');
    }

    static function return(\Base $fw): void
    {
        $re = $fw->get('re');
        if ($re !== '')
            $fw->reroute($re);
        else
            base::redirect($fw);
    }

    static function empty(): void
    {
        echo \Template::instance()->render('empty.htm');
    }

    static function fetch_empty(): void
    {
        echo \Template::instance()->render('fetch/empty.htm');
    }

    static function fetch_no_permission(): void
    {
        echo \Template::instance()->render('fetch/no_permission.htm');
    }

    static function fetch_no_content(): void
    {
        echo \Template::instance()->render('fetch/no_content.htm');
    }

    static function fetch_soon(): void
    {
        echo \Template::instance()->render('fetch/soon.htm');
    }

    static function render(string $template): void
    {
        echo \Template::instance()->render($template);
    }

    static function trim(string $text, int $max_length): string
    {
        return mb_strimwidth($text, 0, $max_length);
    }

    static function trim_fw_max(\Base $fw, string $fw_id, int $max_length): string
    {
        return base::trim($fw->get($fw_id), $max_length);
    }

    static function trim_fw(\Base $fw, string $fw_id, string $fw_max_value): string
    {
        $max_length = (int)$fw->get($fw_max_value);
        return base::trim_fw_max($fw, $fw_id, $max_length);
    }

    static function first(array $result): array
    {
        return empty($result) ? array() : $result[0];
    }

    static function filter_selected(array $list): array
    {
        $result = array();

        foreach ($list as $item) {
            if ($item[db::selected] === true)
                $result[] = $item;
        }

        return $result;
    }

    static function hide_user(): bool
    {
        return base::get()->exists('hide_user_handle');
    }

    static function get_hide_user(): string
    {
        return base::get()->get('hide_user_handle');
    }

    static function check_hide_user(string $handle): bool
    {
        if (!base::hide_user())
            return false;

        return base::get_hide_user() === $handle;
    }

    static function get_repo_name(string $name): string
    {
        return base::repo . '.' . $name;
    }

    static function get_repo(\Base $fw, string $name): array
    {
        $repo_name = base::get_repo_name($name);

        if (!$fw->exists($repo_name))
            $fw->set($repo_name, array());

        return (array)$fw->get($repo_name);
    }

    static function repo_item(string $repo, int $id): string
    {
        return base::get_repo_name($repo) . '.' . $id;
    }
}
