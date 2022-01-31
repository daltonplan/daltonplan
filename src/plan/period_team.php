<?php

declare(strict_types=1);

namespace plan;

use DB\SQL;

use frame\base;
use frame\db;
use frame\session;

abstract class period_team
{
    const latest = 0;
    const table = 'period_team';

    const id = db::id;
    const period = 'period';
    const team = 'team';
    const subject = 'subject';
    const lab = 'lab';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = period_team::id . ','
        . period_team::period  . ','
        . period_team::team . ','
        . period_team::subject . ','
        . period_team::lab . ','
        . period_team::version . ','
        . period_team::created . ','
        . period_team::updated;

    // lab
    const lab_none = 0;

    static function create(\Base $fw, string $prefix): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $prefix . period_team::table . '` (
                `' . period_team::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . period_team::period . '` int(11) NOT NULL,
                `' . period_team::team . '` int(11) NOT NULL,
                `' . period_team::subject . '` int(11) NOT NULL,
                `' . period_team::lab . '` int(11) NOT NULL,
                `' . period_team::version . '` int(11) NOT NULL DEFAULT ' . period_team::latest . ',
                `' . period_team::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . period_team::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . period_team::id . '`) USING BTREE,
                UNIQUE KEY `' . period_team::period . '` (`' . period_team::period . '`,`' . period_team::team . '`,`' . period_team::subject . '`,`' . period_team::lab . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    static function delete(\Base $fw, string $prefix): void
    {
        $sql = 'DROP TABLE `' . $prefix . period_team::table . '`;';
        $fw->db->exec($sql);
    }

    // ---

    // --- query

    static function select(int $plan, string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? period_team::all : $fields) . ' FROM ' . db::plan_prefix($plan) . period_team::table . ' ';
    }

    static function exists(SQL $sql, int $plan, int $period, int $team, int $subject, int $lab): bool
    {
        $get = period_team::select($plan, period_team::id) . 'WHERE '
            . period_team::period . '=? AND '
            . period_team::team . '=? AND '
            . period_team::subject . '=? AND '
            . period_team::lab . '=?';

        return !empty($sql->exec($get, array(
            1 => $period,
            2 => $team,
            3 => $subject,
            4 => $lab,
        )));
    }

    static function get(SQL $sql, int $plan, int $id, string $fields = ''): array
    {
        $get = period_team::select($plan, $fields) . 'WHERE ' . period_team::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_id(SQL $sql, int $plan, int $period, int $team, int $subject, int $lab): int
    {
        $get = period_team::select($plan, period_team::id) . 'WHERE '
            . period_team::period . '=? AND '
            . period_team::team . '=? AND '
            . period_team::subject . '=? AND '
            . period_team::lab . '=?';

        $result = $sql->exec($get, array(
            1 => $period,
            2 => $team,
            3 => $subject,
            4 => $lab,
        ));

        return empty($result) ? 0 : (int)$result[0][period_team::id];
    }

    static function get_list_by_period(SQL $sql, int $plan, int $period, string $fields = ''): array
    {
        $get = period_team::select($plan, $fields) . 'WHERE ' . period_team::period . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . period_team::created;
        else
            $get .= ' ORDER BY ' . period_team::updated . ' DESC';

        return $sql->exec($get, $period);
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_deleted = 2;

    static function event_insert(\Base $fw, int $plan_id, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::period_team,
            $id,
            $command,
            $event,
            period_team::get($fw->db, $plan_id, $id, $fields),
            period_team::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        int $plan,
        int $period,
        int $team,
        int $subject,
        int $lab
    ): int {
        $insert = 'INSERT INTO ' . db::plan_prefix($plan) . period_team::table . ' ('
            . period_team::period . ','
            . period_team::team . ','
            . period_team::subject . ','
            . period_team::lab . ','
            . period_team::version . ') VALUES (?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $period,
            2 => $team,
            3 => $subject,
            4 => $lab,
            5 => period_team::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        period_team::event_insert($fw, $plan, $id, $command, period_team::event_created);

        return $id;
    }

    static function action_delete(\Base $fw, int $command, int $plan, int $period_team): void
    {
        period_team::event_insert($fw, $plan, $period_team, $command, period_team::event_deleted);

        $delete = 'DELETE FROM ' . db::plan_prefix($plan) . period_team::table . ' WHERE ' . period_team::id . '=?';
        $fw->db->exec($delete, $period_team);
    }

    // --- command

}
