<?php

declare(strict_types=1);

namespace frame;

class db
{
    const id = 'id';
    const handle = 'handle';
    const version = 'version';
    const created = 'created';
    const updated = 'updated';
    const active = 'active';

    const selected = 'selected';
    const children = 'children';

    static function setup(\Base $fw): void
    {
        $host = (string)$fw->get('db_host');
        $port = (string)$fw->get('db_port');

        $name = (string)db::name($fw);

        $user = (string)$fw->get('db_user');
        $password = (string)$fw->get('db_password');

        $fw->db = db::connect($fw, $host, $name, $port, $user, $password);
    }

    static function ready(\Base $fw): bool
    {
        return $fw->exists('db');
    }

    static function name(\Base $fw): string
    {
        if ($fw->exists('SESSION.db_name'))
            return $fw->get('SESSION.db_name');

        return $fw->get('db_name');
    }

    static function prefix(): string
    {
        $fw = base::get();

        if ($fw->exists('SESSION.db_prefix'))
            return $fw->get('SESSION.db_prefix');

        return $fw->get('db_prefix');
    }

    static function plan_prefix(int $plan_id): string
    {
        return db::prefix() . (string)$plan_id . '_';
    }

    static function connect(\Base $fw, string $host, string $name, string $port, string $user, string $password): ?\DB\SQL
    {
        $db = null;

        try {
            $db = new \DB\SQL(
                'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8',
                '' . $user . '',
                '' . $password . '',
                [
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
        } catch (\PDOException $e) {
            log::error($fw)->write('frame\db::connect - error: ' . $e->getCode() . ' - ' . $e->getMessage());
            session::reset($fw);
            return null;
        }

        if ((int)$fw->get('log_db') === 0)
            $db->log(false);

        return $db;
    }

    static function get_last_inserted_id(\DB\SQL $sql): int
    {
        $get = 'SELECT LAST_INSERT_ID() AS id';
        $result = $sql->exec($get);
        return empty($result) ? 0 : (int)$result[0]['id'];
    }
}
