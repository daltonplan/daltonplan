<?php

declare(strict_types=1);

namespace account;

use frame\db;

abstract class access
{
    const latest = 0;
    const table = 'access';

    const id = db::id;
    const user = 'user';
    const command = 'command';
    const event = 'event';
    const ip = 'ip';
    const re = 're';
    const ag = 'ag';
    const version = db::version;
    const created = db::created;

    const all = access::id . ','
        . access::user . ','
        . access::command . ','
        . access::event . ','
        . access::ip . ','
        . access::re . ','
        . access::ag . ','
        . access::version . ','
        . access::created;

    // event
    const logged_in  = 1;
    const logged_out = 2;
    const remembered = 3;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . access::table . '` (
                `' . access::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . access::user . '` int(11) NOT NULL,
                `' . access::command . '` int(11) NOT NULL,
                `' . access::event . '` int(11) NOT NULL,
                `' . access::ip . '` varchar(255) NOT NULL,
                `' . access::re . '` varchar(255) DEFAULT NULL,
                `' . access::ag . '` varchar(255) NOT NULL,
                `' . access::version . '` int(11) NOT NULL DEFAULT ' . access::latest . ',
                `' . access::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`' . access::id . '`) USING BTREE,
                KEY `' . access::user . '` (`' . access::user . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // --- action

    static function insert(\Base $fw, int $command, int $user, int $event): void
    {
        $insert = 'INSERT INTO ' . db::prefix() . access::table . ' ('
            . access::user . ','
            . access::command . ','
            . access::event . ','
            . access::ip . ','
            . access::re . ','
            . access::ag . ','
            . access::version . ') VALUES (?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $user,
            2 => $command,
            3 => $event,
            4 => $fw->get('ip'),
            5 => $fw->get('re'),
            6 => $fw->get('ag'),
            7 => access::latest,
        ));
    }
}
