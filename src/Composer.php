<?php

declare(strict_types=1);

namespace DOF\CLI;

use Exception;
use DOF\Convention;
use DOF\Util\Exceptor;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;

// https://getcomposer.org/doc/articles/scripts.md
final class Composer
{
    const VENDOR = 'dof-php/cli';

    public static function postPackageUpdate(PackageEvent $event)
    {
        if (Composer::VENDOR !== $event->getOperation()->getPackage()->getName()) {
            return;
        }
        
        // TODO
    }

    public static function postPackageInstall(PackageEvent $event)
    {
        if (Composer::VENDOR !== $event->getOperation()->getPackage()->getName()) {
            return;
        }

        // TODO
    }

    public static function postUpdateCMD(Event $event)
    {
        Composer::postInstallCMD($event);
    }

    public static function postInstallCMD(Event $event)
    {
        $cliBooter = \join(DIRECTORY_SEPARATOR, [\dirname($event->getComposer()->getConfig()->get('vendor-dir')), Convention::FILE_CLI_BOOTER]);
        if (\is_file($cliBooter)) {
            return;
        }

        $status = @copy(\join(DIRECTORY_SEPARATOR, [\dirname(\dirname(__FILE__)), 'tpl', 'cli-booter']), $cliBooter);
        if (false === $status) {
            throw new Exceptor('INSTALL_CLI_BOOTER_FAILED', \compact('cliBooter'));
        }
    }
}
