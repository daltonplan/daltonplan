<?php

declare(strict_types=1);

namespace domain;

use frame\db;
use frame\log;
use frame\session;

abstract class view
{
    const latest = 0;
    const table = 'view';

    const id = db::id;
    const user = 'user';
    const url = 'url';
    const ip = 'ip';
    const re = 're';
    const ag = 'ag';
    const version = db::version;
    const created = db::created;

    const all = view::id . ','
        . view::user  . ','
        . view::url . ','
        . view::ip . ','
        . view::re . ','
        . view::ag . ','
        . view::version . ','
        . view::created;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . view::table . '` (
                `' . view::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . view::user . '` int(11) NOT NULL,
                `' . view::url . '` varchar(255) NOT NULL,
                `' . view::ip . '` varchar(255) NOT NULL,
                `' . view::re . '` varchar(255) DEFAULT NULL,
                `' . view::ag . '` varchar(255) NOT NULL,
                `' . view::version . '` int(11) NOT NULL DEFAULT 0,
                `' . view::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`' . view::id . '`) USING BTREE,
                KEY `' . view::user . '` (`' . view::user . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // --- action

    static function insert(\Base $fw, int $user_id): void
    {
        if (!db::ready($fw))
            return;

        $insert = 'INSERT INTO ' . db::prefix() . view::table . ' ('
            . view::user . ','
            . view::url . ','
            . view::ip . ','
            . view::re . ','
            . view::ag . ') VALUES (?,?,?,?,?)';

        try {
            $fw->db->exec($insert, array(
                1 => $user_id,
                2 => $fw->get('url'),
                3 => $fw->get('ip'),
                4 => $fw->get('re'),
                5 => $fw->get('ag'),
            ));
        } catch (\PDOException $e) {
            log::error($fw)->write('domain\view::insert - error: ' . $e->getCode() . ' - ' . $e->getMessage());
            session::reset($fw);
        }
    }
}
