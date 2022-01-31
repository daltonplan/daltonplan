<?php

declare(strict_types=1);

namespace account;

use DB\SQL;

use frame\db;
use frame\session;

abstract class remember
{
    const latest = 0;
    const table = 'remember';

    const id = db::id;
    const user = 'user';
    const selector = 'selector';
    const token = 'token';
    const expires = 'expires';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = remember::id . ','
        . remember::user . ','
        . remember::selector . ','
        . remember::token . ','
        . remember::expires . ','
        . remember::version . ','
        . remember::created . ','
        . remember::updated;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . remember::table . '` (
                `' . remember::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . remember::user . '` int(11) NOT NULL,
                `' . remember::selector . '` varchar(12) NOT NULL,
                `' . remember::token . '` varchar(64) NOT NULL,
                `' . remember::expires . '` datetime NOT NULL,
                `' . remember::version . '` int(11) NOT NULL DEFAULT ' . remember::latest . ',
                `' . remember::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . remember::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . remember::id . '`) USING BTREE,
                KEY `' . remember::selector . '` (`' . remember::selector . '`) USING BTREE,
                KEY `' . remember::user . '` (`' . remember::user . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? remember::all : $fields) . ' FROM ' . db::prefix() . remember::table . ' ';;
    }

    static function get_list(SQL $sql, string $selector, string $date): array
    {
        $get = remember::select(remember::id . ','
            . remember::user . ','
            . remember::token) . 'WHERE '
            . remember::selector . '=? AND ('
            . remember::expires . '>?)';

        return $sql->exec($get, array(
            1 => $selector,
            2 => $date
        ));
    }

    static function get_list_expires(SQL $sql, int $user, string $date): array
    {
        $get = remember::select(remember::id) . 'WHERE ' . remember::user . '=? AND (' . remember::expires . '<?)';

        return $sql->exec($get, array(
            1 => $user,
            2 => $date
        ));
    }

    // --- action

    static function insert(\Base $fw, int $user_id, string $selector, string $authenticator): void
    {
        $insert = 'INSERT INTO ' . db::prefix() . remember::table . ' ('
            . remember::user . ','
            . remember::selector . ','
            . remember::token . ','
            . remember::expires . ','
            . remember::version . ') VALUES (?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $user_id,
            2 => $selector,
            3 => hash('sha256', $authenticator),
            4 => date('Y-m-d\TH:i:s', time() + session::cookie_duration),
            5 => remember::latest,
        ));
    }

    static function update(\Base $fw, int $id, string $selector, string $authenticator): void
    {
        $update = 'UPDATE ' . db::prefix() . remember::table . ' SET '
            . remember::selector . '=?,'
            . remember::token . '=?,'
            . remember::expires . '=? WHERE '
            . remember::id . '=?';

        $fw->db->exec($update, array(
            1 => $selector,
            2 => hash('sha256', $authenticator),
            3 => date('Y-m-d\TH:i:s', time() + session::cookie_duration),
            4 => $id,
        ));
    }

    static function delete_selector(\Base $fw, string $selector): void
    {
        $delete = 'DELETE FROM ' . db::prefix() . remember::table . ' WHERE ' . remember::selector . '=?';
        $fw->db->exec($delete, $selector);
    }

    static function delete_id(\Base $fw, int $id): void
    {
        $delete = 'DELETE FROM ' . db::prefix() . remember::table . ' WHERE ' . remember::id . '=?';
        $fw->db->exec($delete, $id);
    }
}
