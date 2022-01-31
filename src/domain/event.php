<?php

declare(strict_types=1);

namespace domain;

use frame\db;
use frame\log;
use frame\session;

abstract class event
{
    const latest = 0;
    const table = 'event';

    const id = db::id;
    const type = 'type';
    const target = 'target';
    const command = 'command';
    const event = 'event';
    const user = 'user';
    const payload = 'payload';
    const revision = 'revision';
    const version = db::version;
    const created = db::created;

    const all = event::id . ','
        . event::type  . ','
        . event::target . ','
        . event::command . ','
        . event::event . ','
        . event::user . ','
        . event::payload . ','
        . event::version . ','
        . event::created;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . event::table . '` (
                `' . event::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . event::type . '` int(11) NOT NULL,
                `' . event::target . '` int(11) NOT NULL,
                `' . event::command . '` int(11) NOT NULL,
                `' . event::event . '` int(11) NOT NULL,
                `' . event::user . '` int(11) NOT NULL,
                `' . event::payload . '` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`' . event::payload . '`)),
                `' . event::revision . '` int(11) NOT NULL DEFAULT 0,
                `' . event::version . '` int(11) NOT NULL,
                `' . event::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`' . event::id . '`),
                KEY `' . event::type . '` (`' . event::type . '`) USING BTREE,
                KEY `' . event::target . '` (`' . event::target . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // --- action

    static function insert(
        \Base $fw,
        int $type,
        int $target,
        int $command,
        int $event,
        array $payload,
        int $revision
    ): void {
        if (empty($payload)) {
            log::error($fw)->write('event::insert - no payload - type: ' . $type
                . ' - target: ' . $target
                . ' - command: ' . $command
                . ' - event: ' . $event
                . ' - revision: ' . $revision);
            return;
        }

        if (session::proxy($fw))
            $payload['proxy'] = session::get_proxy($fw);

        $insert = 'INSERT INTO ' . db::prefix() . event::table . ' ('
            . event::type . ','
            . event::target . ','
            . event::command . ','
            . event::event . ','
            . event::user . ','
            . event::payload . ','
            . event::revision . ','
            . event::version . ') VALUES (?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $type,
            2 => $target,
            3 => $command,
            4 => $event,
            5 => session::get_user_id($fw),
            6 => json_encode($payload),
            7 => $revision,
            8 => event::latest,
        ));
    }
}
