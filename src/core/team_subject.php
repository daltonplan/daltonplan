<?php

declare(strict_types=1);

namespace core;

use DB\SQL;

use frame\base;
use frame\db;
use frame\session;

abstract class team_subject
{
    const latest = 0;
    const table = 'team_subject';

    const id = db::id;
    const team = 'team';
    const subject = 'subject';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = team_subject::id . ','
        . team_subject::team . ','
        . team_subject::subject . ','
        . team_subject::version . ','
        . team_subject::created . ','
        . team_subject::updated;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . team_subject::table . '` (
                `' . team_subject::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . team_subject::team . '` int(11) NOT NULL,
                `' . team_subject::subject . '` int(11) NOT NULL,
                `' . team_subject::version . '` int(11) NOT NULL DEFAULT ' . team_subject::latest . ',
                `' . team_subject::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . team_subject::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . team_subject::id . '`) USING BTREE,
                UNIQUE KEY `' . team_subject::subject . '` (`' . team_subject::team . '`,`' . team_subject::subject . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // ---

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? team_subject::all : $fields) . ' FROM ' . db::prefix() . team_subject::table . ' ';
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = team_subject::select($fields) . 'WHERE ' . team_subject::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_list_by_team(SQL $sql, int $team, string $fields = ''): array
    {
        $get = team_subject::select($fields) . 'WHERE ' . team_subject::team . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . team_subject::created;
        else
            $get .= ' ORDER BY ' . team_subject::updated . ' DESC';

        return $sql->exec($get, $team);
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_deleted = 2;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::team_subject,
            $id,
            $command,
            $event,
            team_subject::get($fw->db, $id, $fields),
            team_subject::event_revision
        );
    }

    // --- action

    static function action_insert(\Base $fw, int $command, int $team, int $subject): int
    {
        $insert = 'INSERT INTO ' . db::prefix() . team_subject::table . ' ('
            . team_subject::team . ','
            . team_subject::subject . ','
            . team_subject::version . ') VALUES (?,?,?)';

        $fw->db->exec($insert, array(
            1 => $team,
            2 => $subject,
            3 => team_subject::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        team_subject::event_insert($fw, $id, $command, team_subject::event_created);

        return $id;
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        team_subject::event_insert($fw, $id, $command, team_subject::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . team_subject::table . ' WHERE ' . team_subject::id . '=?';

        $fw->db->exec($delete, $id);
    }

    // --- command

    static function command_assign(\Base $fw, string $handle, array $subjects): void
    {
        $command = \cmd::begin($fw, \cmd::team_assign_subject);

        $team = team::get_by_handle($fw->db, $handle, team::id . ',' . team::active);
        if (empty($team))
            return;

        if ((int)$team[team::active] !== team::active_on)
            return;

        $team_id = (int)$team[team::id];

        $result = 0;

        $result += team::command_assign_subjects($fw, $command, $team_id, $subjects);

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }
}
