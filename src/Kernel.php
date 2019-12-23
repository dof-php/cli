<?php

declare(strict_types=1);

namespace DOF\CLI;

use Throwable;
use DOF\DOF;
use DOF\KernelInitializer;
use DOF\Exceptor\WritePermissionDenied;
use DOF\Util\Str;
use DOF\Util\DSL\CLIA;
use DOF\CLI\CommandManager;

final class Kernel extends KernelInitializer
{
    public function handle(string $root, array $argvs)
    {
        $this->stdin = $argvs;

        $console = $this->di(Console::class);

        try {
            DOF::init($root);

            CommandManager::load();

            // update upfiles right after kernel booted
            $this->upfiles = \count(get_included_files());
        } catch (WritePermissionDenied $th) {
            $this->unregister('shutdown', __CLASS__);

            $console->throw($th);
        } catch (Throwable $th) {
            $console->throw($th);
        }

        list($entry, $cmd, $options, $params) = CLIA::build($argvs);
        $cmd = $cmd ? \strtolower($cmd) : 'dof';
        $command = CommandManager::get($cmd);
        if (! $command) {
            $suggest = [];
            $cmds = CommandManager::getData();
            foreach ($cmds as $name => list('comment' => $comment)) {
                if (Str::contain($name, $cmd)) {
                    $suggest[] = [$name => $comment];
                }
            }

            $console->fail('COMMAND_NOT_FOUND', \compact('cmd', 'suggest'));
            return;
        }

        $class = $command['class'] ?? null;
        if ((! $class) || (! \class_exists($class))) {
            $console->exceptor('COMMAND_CLASS_NOT_EXISTS', \compact('cmd', 'class', 'method'));
            return;
        }
        $method = $command['method'] ?? null;
        if ((! $method) || (! \method_exists($class, $method))) {
            $console->exceptor('COMMAND_HANDLER_NOT_EXISTS', \compact('cmd', 'class', 'method'));
            return;
        }

        $console->setEntry($entry)->setName($cmd)->setOptions($options)->setParams($params);

        try {
            $this->di($class)->{$method}($console);
        } catch (Throwable $th) {
            $console->exceptor('COMMAND_EXECUTE_FAILED', \compact('cmd', 'class', 'method'), $th);
        }
    }
}
