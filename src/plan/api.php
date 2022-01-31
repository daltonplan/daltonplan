<?php

declare(strict_types=1);

namespace plan;

use frame\base;
use frame\log;

class api extends \frame\app
{
    function check(\Base $fw): void
    {
        if (!\account\service::logged($fw)) {
            log::warn($fw)->write('plan::check - user not logged');
            return;
        }

        if (!$fw->exists('PARAMS.handle'))
            return;

        $data['result'] = false;

        $handle = base::trim_fw($fw, 'PARAMS.handle', \core\plan::max_handle_length);
        if (strlen($handle) >= (int)$fw->get(\core\plan::min_handle_length))
            $data['result'] = \core\plan::exists($fw->db, $handle);

        echo json_encode($data);
    }
}
