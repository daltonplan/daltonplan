<?php

declare(strict_types=1);

namespace domain;

use frame\db;
use frame\session;

abstract class command
{
    const latest = 0;
    const table = 'command';

    const id = db::id;
    const cmd = 'cmd';
    const user = 'user';
    const result = 'result';
    const payload = 'payload';
    const revision = 'revision';
    const version = db::version;
    const created = db::created;

    const all = command::id . ','
        . command::cmd  . ','
        . command::user . ','
        . command::result . ','
        . command::payload . ','
        . command::version . ','
        . command::created;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . command::table . '` (
                `' . command::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . command::cmd . '` int(11) NOT NULL,
                `' . command::user . '` int(11) NOT NULL,
                `' . command::result . '` int(11) NOT NULL DEFAULT 0,
                `' . command::payload . '` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin CHECK (json_valid(`' . command::payload . '`)),
                `' . command::revision . '` int(11) NOT NULL DEFAULT 0,
                `' . command::version . '` int(11) NOT NULL DEFAULT 0,
                `' . command::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`' . command::id . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // --- action

    static function begin(\Base $fw, int $cmd): int
    {
        $fw->db->begin();

        $insert = 'INSERT INTO ' . db::prefix() . command::table . ' ('
            . command::cmd . ','
            . command::user . ','
            . command::revision . ','
            . command::version . ') VALUES (?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $cmd,
            2 => session::get_user_id($fw),
            3 => \cmd::revision,
            4 => command::latest,
        ));

        return \frame\db::get_last_inserted_id($fw->db);
    }

    static function begin_input(\Base $fw, int $cmd, array $payload): int
    {
        $fw->db->begin();

        $insert = 'INSERT INTO ' . db::prefix() . command::table . ' ('
            . command::cmd . ','
            . command::user . ','
            . command::payload . ','
            . command::revision . ','
            . command::version . ') VALUES (?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $cmd,
            2 => session::get_user_id($fw),
            3 => json_encode($payload),
            4 => \cmd::revision,
            5 => command::latest,
        ));

        return \frame\db::get_last_inserted_id($fw->db);
    }

    static function end(\Base $fw, int $id, int $result = 1): void
    {
        $update = 'UPDATE ' . db::prefix() . command::table . ' SET '
            . command::result . '=? WHERE '
            . command::id . '=?';

        $fw->db->exec($update, array(
            1 => $result,
            2 => $id,
        ));

        $fw->db->commit();
    }

    // overrides user
    static function end_user(\Base $fw, int $id, int $user, int $result = 1): void
    {
        $update = 'UPDATE ' . db::prefix() . command::table . ' SET '
            . command::result . '=?,'
            . command::user . '=? WHERE '
            . command::id . '=?';

        $fw->db->exec($update, array(
            1 => $result,
            2 => $user,
            3 => $id,
        ));

        $fw->db->commit();
    }

    // overrides cmd
    static function end_cmd(\Base $fw, int $id, int $cmd, int $result = 1): void
    {
        $update = 'UPDATE ' . db::prefix() . command::table . ' SET '
            . command::result . '=?,'
            . command::cmd . '=? WHERE '
            . command::id . '=?';

        $fw->db->exec($update, array(
            1 => $result,
            2 => $cmd,
            3 => $id,
        ));

        $fw->db->commit();
    }
}
