<?php

declare(strict_types=1);

namespace DOF\CLI;

use DOF\DMN;
use DOF\INI;
use DOF\Convention;
use DOF\Traits\Manager;
use DOF\CLI\Command;
use DOF\CLI\Exceptor\CLIExceptor;
use DOF\Util\IS;
use DOF\Util\FS;
use DOF\Util\DOF;
use DOF\Util\Arr;
use DOF\Util\Str;
use DOF\Util\Annotation;

final class CommandManager
{
    use Manager;

    public static function init()
    {
        CommandManager::addSystem(Command::class);

        foreach (INI::vendorGet() as $vendor => $item) {
            if ($cmds = ($item['cmd'] ?? [])) {
                CommandManager::addVendor($vendor, $cmds);
            }
        }

        foreach (DMN::list() as $domain => $dir) {
            if (\is_dir($_dir = FS::path($dir, Convention::DIR_COMMAND))) {
                CommandManager::addDomain($domain, $_dir);
            }
        }
    }

    protected static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type)
    {
        $class = $ofClass['namespace'] ?? null;
        $cmdPrefix = $ofClass['doc']['CMD'] ?? null;
        $commentGroup = $ofClass['doc']['DESC'] ?? null;
        $optionGroup = $ofClass['doc']['OPTION'] ?? [];
        $argvGroup = $ofClass['doc']['ARGV'] ?? [];

        foreach ($ofMethods as $method => $ofMethod) {
            $cmds = [];
            if ($main = ($ofMethod['doc']['CMD'] ?? null)) {
                $cmds[] = \strtolower($cmdPrefix ? ($main = \join('.', [$cmdPrefix, $main])) : $main);
            }
            if ($aliases = ($ofMethod['doc']['ALIAS'] ?? [])) {
                if (! $main) {
                    throw new CLIExceptor('CMD_ALIAS_WITHOUT_MAIN', \compact('class', 'method', 'alias'));
                }
                $cmds = Arr::union($cmds, $aliases);
            }
            foreach ($cmds as $cmd) {
                $comment = $ofMethod['doc']['DESC'] ?? null;
                if (! $comment) {
                    throw new CLIExceptor('EMPTY_COMMAND_DESCRIPTION', \compact('class', 'method'));
                }
                $comment = ($commentGroup ? \join(': ', [$commentGroup, $comment]) : $comment);
                $comment = \in_array($cmd, $aliases) ? "Alias of `{$main}`" : $comment;
                $options = \array_merge($optionGroup, ($ofMethod['doc']['OPTION'] ?? []));
                $argvs = Arr::union($argvGroup, ($ofMethod['doc']['ARGV'] ?? []));
                $current = \compact('class', 'method', 'comment', 'options', 'argvs');
                if ($conflict = (self::$data[$cmd] ?? false)) {
                    throw new CLIExceptor('DUPLICATE_COMMAND', \compact('current', 'conflict'));
                }

                self::$data[$cmd] = $current;

                switch ($type) {
                    case Convention::SRC_DOMAIN:
                        self::$domain[$cmd] = DMN::name($class);
                        break;
                    case Convention::SRC_SYSTEM:
                        self::$system[$cmd] = \count(self::$data) - 1;
                        break;
                    case Convention::SRC_VENDOR:
                        self::$vendor[$cmd] = self::vendor($class);
                        break;
                }
            }
        }
    }

    public static function __annotationValueALIAS(
        string $alias,
        string $namespace,
        &$multiple,
        &$strict,
        array $ext
    ) {
        $multiple = true;

        return [\strtolower($alias)];
    }

    public static function __annotationValueOPTION(
        string $option,
        string $namespace,
        &$multiple,
        &$strict,
        array $ext
    ) {
        $multiple = 'isolate';

        $notes = $ext['NOTES'] ?? null;
        if (IS::empty($notes)) {
            throw new CLIExceptor('EMPTY_OPTION_DESCRIPTION', \compact('option', 'namespace'));
        }

        return [$option => [
            'NOTES' => $notes,
            'DEFAULT' => $ext['DEFAULT'] ?? null,
        ]];
    }

    public static function __annotationValueARGV(
        string $arg,
        string $namespace,
        &$multiple,
        &$strict,
        $ext
    ) {
        $multiple = 'isolate';

        $notes = $ext['NOTES'] ?? null;
        if (IS::empty($notes)) {
            throw new CLIExceptor('EMPTY_ARGV_DESCRIPTION', \compact('arg', 'namespace'));
        }

        return [$arg => $notes];
    }
}
