<?php

declare(strict_types=1);

namespace core;

use DB\SQL;

use frame\base;
use frame\db;
use frame\session;

abstract class lab_team
{
    const latest = 0;
    const table = 'lab_team';

    const id = db::id;
    const lab = 'lab';
    const team = 'team';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = lab_team::id . ','
        . lab_team::lab . ','
        . lab_team::team . ','
        . lab_team::version . ','
        . lab_team::created . ','
        . lab_team::updated;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . lab_team::table . '` (
                `' . lab_team::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . lab_team::lab . '` int(11) NOT NULL,
                `' . lab_team::team . '` int(11) NOT NULL,
                `' . lab_team::version . '` int(11) NOT NULL DEFAULT ' . lab_team::latest . ',
                `' . lab_team::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . lab_team::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . lab_team::id . '`) USING BTREE,
                UNIQUE KEY `' . lab_team::team . '` (`' . lab_team::lab . '`,`' . lab_team::team . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? lab_team::all : $fields) . ' FROM ' . db::prefix() . lab_team::table . ' ';
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = lab_team::select($fields) . 'WHERE ' . lab_team::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_list_by_lab(SQL $sql, int $lab, string $fields = ''): array
    {
        $get = lab_team::select($fields) . 'WHERE ' . lab_team::lab . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . lab_team::created;
        else
            $get .= ' ORDER BY ' . lab_team::updated . ' DESC';

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
            \event::lab_team,
            $id,
            $command,
            $event,
            lab_team::get($fw->db, $id, $fields),
            lab_team::event_revision
        );
    }

    // --- action

    static function action_insert(\Base $fw, int $command, int $lab, int $team): int
    {
        $insert = 'INSERT INTO ' . db::prefix() . lab_team::table . ' ('
            . lab_team::lab . ','
            . lab_team::team . ','
            . lab_team::version . ') VALUES (?,?,?)';

        $fw->db->exec($insert, array(
            1 => $lab,
            2 => $team,
            3 => lab_team::latest,
        ));

        $lab_team_id = db::get_last_inserted_id($fw->db);
        lab_team::event_insert($fw, $lab_team_id, $command, lab_team::event_created);

        return $lab_team_id;
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        lab_team::event_insert($fw, $id, $command, lab_team::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . lab_team::table . ' WHERE ' . lab_team::id . '=?';

        $fw->db->exec($delete, $id);
    }
}
