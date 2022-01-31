<?php

declare(strict_types=1);

namespace frame;

class version
{
    const last_update = '2022-01-31';

    const nr = '0.30';

    const year = '2022';
    const release = 0;

    // preview -> alpha -> beta -> rc -> release -> rev++

    const stage = 'beta';
    const rev = 1;

    static function set_info(\Base $fw): void
    {
        $fw->set('version_nr', version::nr);
        $fw->set('last_update', version::last_update);

        $release = '';
        if (version::release > 0)
            $release = '.' . version::release;

        $rev = '';
        if (version::rev > 1)
            $rev = ' ' . version::rev;

        $version_info = version::year . $release;
        if (version::stage !== 'release')
            $version_info = version::year . $release . ' ' . version::stage . $rev;

        $fw->set('version_info', $version_info);
        $fw->set('version', $version_info . ' - v' . version::nr);
    }
}
