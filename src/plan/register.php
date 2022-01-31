<?php

declare(strict_types=1);

namespace plan;

use DB\SQL;

use frame\base;
use frame\db;

abstract class register
{
    const latest = 0;
    const table = 'register';
    const list = 'register_list';

    const id = db::id;
    const user = 'user';
    const register = 'register';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = register::id . ','
        . register::user . ','
        . register::register . ','
        . register::version . ','
        . register::created . ','
        . register::updated;

    static function create(\Base $fw, string $prefix): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $prefix . register::table . '` (
                `' . register::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . register::user . '` int(11) NOT NULL,
                `' . register::register . '` timestamp NULL DEFAULT NULL,
                `' . register::version . '` int(11) NOT NULL DEFAULT ' . register::latest . ',
                `' . register::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . register::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . register::id . '`) USING BTREE,
                UNIQUE KEY `' . register::user . '` (`' . register::user . '`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    static function delete(\Base $fw, string $prefix): void
    {
        $sql = 'DROP TABLE `' . $prefix . register::table . '`;';
        $fw->db->exec($sql);
    }

    // ---

    // --- query

    static function select(int $plan, string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? register::all : $fields) . ' FROM ' . db::plan_prefix($plan) . register::table . ' ';
    }

    static function get(SQL $sql, int $plan, int $id, string $fields = ''): array
    {
        $get = register::select($plan, $fields) . 'WHERE ' . register::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_user(SQL $sql, int $plan, int $user, string $fields = ''): array
    {
        $get = register::select($plan, $fields) . 'WHERE ' . register::user . '=?';
        return base::first($sql->exec($get, $user));
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_deleted = 2;
    const event_updated = 3;

    static function event_insert(\Base $fw, int $plan_id, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::register,
            $id,
            $command,
            $event,
            register::get($fw->db, $plan_id, $id, $fields),
            register::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        int $plan,
        int $user,
        string|null $register
    ): int {
        $insert = 'INSERT INTO ' . db::plan_prefix($plan) . register::table . ' ('
            . register::user . ','
            . register::register . ','
            . register::version . ') VALUES (?,?,?)';

        $fw->db->exec($insert, array(
            1 => $user,
            2 => $register,
            3 => register::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        register::event_insert($fw, $plan, $id, $command, register::event_created);

        return $id;
    }

    static function action_delete(\Base $fw, int $command, int $plan, int $register): void
    {
        register::event_insert($fw, $plan, $register, $command, register::event_deleted);

        $delete = 'DELETE FROM ' . db::plan_prefix($plan) . register::table . ' WHERE ' . register::id . '=?';
        $fw->db->exec($delete, $register);
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $plan,
        int $id,
        string|null $register
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . register::table . ' SET '
            . register::register . '=? WHERE '
            . register::id . '=?';

        $fw->db->exec($update, array(
            1 => $register,
            2 => $id,
        ));

        register::event_insert($fw, $plan, $id, $command, register::event_updated, register::register);
    }

    // --- command

}
