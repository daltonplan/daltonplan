<?php

declare(strict_types=1);

namespace core;

use DB\SQL;

use frame\base;
use frame\db;
use frame\session;

abstract class lab_subject
{
    const latest = 0;
    const table = 'lab_subject';

    const id = db::id;
    const lab = 'lab';
    const subject = 'subject';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = lab_subject::id . ','
        . lab_subject::lab . ','
        . lab_subject::subject . ','
        . lab_subject::version . ','
        . lab_subject::created . ','
        . lab_subject::updated;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . lab_subject::table . '` (
                `' . lab_subject::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . lab_subject::lab . '` int(11) NOT NULL,
                `' . lab_subject::subject . '` int(11) NOT NULL,
                `' . lab_subject::version . '` int(11) NOT NULL DEFAULT ' . lab_subject::latest . ',
                `' . lab_subject::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . lab_subject::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . lab_subject::id . '`) USING BTREE,
                UNIQUE KEY `' . lab_subject::subject . '` (`' . lab_subject::lab . '`,`' . lab_subject::subject . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? lab_subject::all : $fields) . ' FROM ' . db::prefix() . lab_subject::table . ' ';
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = lab_subject::select($fields) . 'WHERE ' . lab_subject::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_list_by_lab(SQL $sql, int $lab, string $fields = ''): array
    {
        $get = lab_subject::select($fields) . 'WHERE ' . lab_subject::lab . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . lab_subject::created;
        else
            $get .= ' ORDER BY ' . lab_subject::updated . ' DESC';

        return $sql->exec($get, $lab);
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_deleted = 2;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::lab_subject,
            $id,
            $command,
            $event,
            lab_subject::get($fw->db, $id, $fields),
            lab_subject::event_revision
        );
    }

    // --- action

    static function action_insert(\Base $fw, int $command, int $lab, int $subject): int
    {
        $insert = 'INSERT INTO ' . db::prefix() . lab_subject::table . ' ('
            . lab_subject::lab . ','
            . lab_subject::subject . ','
            . lab_subject::version . ') VALUES (?,?,?)';

        $fw->db->exec($insert, array(
            1 => $lab,
            2 => $subject,
            3 => lab_subject::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        lab_subject::event_insert($fw, $id, $command, lab_subject::event_created);

        return $id;
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        lab_subject::event_insert($fw, $id, $command, lab_subject::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . lab_subject::table . ' WHERE ' . lab_subject::id . '=?';

        $fw->db->exec($delete, $id);
    }
}
