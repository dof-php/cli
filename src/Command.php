<?php

declare(strict_types=1);

namespace DOF\CLI;

use DOF\DOF;
use DOF\ETC;
use DOF\ENV;
use DOF\INI;
use DOF\DMN;
use DOF\I18N;
use DOF\Convention;
use DOF\CLI\Color;
use DOF\Util\Reflect;
use DOF\Util\IS;
use DOF\Util\FS;
use DOF\Util\Str;
use DOF\Util\Arr;
use DOF\Util\Format;

final class Command
{
    const VENDORS = [
        Convention::FILE_BOOT => [Convention::DIR_BOOT, Convention::SRC_VENDOR],
        Convention::FILE_BOOT_CMD => [Convention::DIR_SETTING, Convention::SRC_VENDOR],
    ];
    
    /**
     * @CMD(dof)
     * @Desc(The default command of DOF CLI )
     * @Option(help){notes=Print dof cli help message}
     * @Option(version){notes=Get dof framework version string}
     * @Option(root){notes=Get dof framework root}
     */
    public function dof($console)
    {
        if ($console->hasOption('help')) {
            return $this->help($console);
        }
        if ($console->hasOption('version')) {
            return $this->version($console);
        }
        if ($console->hasOption('root')) {
            return $this->root($console);
        }

        $console->line(\join('  ', [
            $console->render('(c)', Color::WARNING),
            $console->render('DOF PHP', Color::TITLE),
            $console->render(DOF::VERSION, Color::INFO),
        ]), 1, true);
    }

    /**
     * @CMD(version)
     * @Desc(Get version of DOF)
     */
    public function version($console)
    {
        $console->line(DOF::VERSION);
    }

    /**
     * @CMD(help)
     * @Desc(Print help message of a command)
     * @Argv(1){notes=The command name used to print help message}
     */
    public function help($console)
    {
        $cmd = $console->getParams()[0] ?? null;
        if (! $cmd) {
            $console->line($console->render('Usage: php dof {COMMAND} [--options ...] [[--] arguments ...]', Color::TITLE), 1, true);
            return;
        }

        $cmd = \strtolower($cmd);
        $attr = CommandManager::get($cmd);
        if (! $attr) {
            $console->error("COMMAND_NOT_EXIST: {$cmd}");
            return;
        }

        $console->line($console->render("Usage: php dof {$cmd} [--options ...] [[--] arguments ...]", Color::TITLE), 1, true);
        \extract($attr);

        $console->line($console->render('* Command: ', Color::TITLE).$console->render($cmd, Color::SUCCESS));
        $console->line();
        $console->line('* Description: '.$comment);
        $console->line();
        $console->title('* Options: ');
        $options['ascii'] = [
            'NOTES' => 'Whether display command output as plain ascii text',
            'DEFAULT' => 'false',
        ];
        foreach ($options as $option => $_attr) {
            \extract($_attr);
            $default = $DEFAULT ?: 'NULL';
            $console->line(
                $console->render("\t--{$option}\t", Color::SUCCESS)
                    .$console->render($NOTES, Color::INFO)
                    .$console->render("\t(Default: {$default})", 'CYAN')
            );
        }
        $console->line();
        $console->title('* Arguments: ');

        foreach ($argvs as $order => $desc) {
            $console->line(
                $console->render("\t#{$order}\t", Color::SUCCESS).$console->render($desc, Color::INFO)
            );
        }

        $console->line();
        $console->line('* Class: '.$class);
        $console->line('* Method: '.$method);

        $reflector = Reflect::parseClassMethod($class, $method);
        $console->line('* File: '.($reflector['file'] ?? Reflect::getNamespaceFile($class)));
        $console->line('* Line: '.($reflector['line'] ?? -1));
        $console->line();
    }

    /**
     * @CMD(run)
     * @Alias(php)
     * @Desc(Execute a standalone php script which is able to access all the functionalities of DOF)
     * @Argv(1){notes=The php script file to run}
     */
    public function run($console)
    {
        $php = $console->getParams()[0] ?? null;
        if (! $php) {
            return $console->fail('NoPhpScriptToRun');
        }
        if (! \is_file($php)) {
            $php = DOF::path($php);
            if (! \is_file($php)) {
                return $console->exceptor('PhpScriptNotExists', ['path' => $php]);
            }
        }

        try {
            (function ($php) use ($console) {
                require $php;
            })($php);
        } catch (Throwable $th) {
            $console->exceptor('FailedToExecutePhpScript', ['path' => $php], $th);
        }
    }

    /**
     * @CMD(root)
     * @Desc(Get root path of DOF)
     * @Option(project){notes=Get project root instead of framework root}
     */
    public function root($console)
    {
        $console->line(DOF::root($console->hasOption('project')));
    }

    /**
     * @CMD(etc.get)
     * @Desc(Get configs in this project)
     * @Argv(1){notes=The config key}
     */
    public function getETC($console)
    {
        // TODO
        $console->line(ETC::all());
    }

    /**
     * @CMD(ini.get)
     * @Desc(Get settings of this project)
     * @Argv(1){notes=The setting key}
     */
    public function getINI($console)
    {
        // TODO
        $console->line(INI::all());
    }

    /**
     * @CMD(env.get)
     * @Desc(Get environment variables of this project)
     * @Option(domain){notes=The domain name}
     * @Argv(1){notes=The environment variable names}
     */
    public function getENV($console)
    {
        // TODO
        $console->line(ENV::all());
    }

    /**
     * @CMD(lang.get)
     * @Desc(Get language assets)
     * @Option(type){notes=The type of language resource}
     */
    public function getLang($console)
    {
        I18N::init();

        switch ($console->getOption('type', null)) {
            case 'domain':
                $console->line(I18N::domainGet($console->getOption('domain', null)));
                break;
            case 'system':
                $console->line(I18N::systemGet());
                break;
            case 'vendor':
                $console->line(I18N::vendorGet($console->getOption('vendor', null)));
                break;
            default:
                $console->line(I18N::all());
                break;
        }
    }

    /**
     * @CMD(cmd.system)
     * @Alias(cmd.sys)
     * @Alias(cmd.default)
     * @Desc(List DOF builtin commands)
     */
    public function cmdSystem($console)
    {
        $console->setOption('system', true);

        $this->cmdAll($console);
    }

    /**
     * @CMD(cmd.vendor)
     * @DESC(List commands of DOF vendor packages)
     */
    public function cmdVendor($console)
    {
        $console->setOption('vendor', $console->getOption('vendor', true));

        $this->cmdAll($console);
    }

    /**
     * @CMD(cmd.domain)
     * @Desc(List commands of domains)
     * @Option(domain){notes=List commands in given domains}
     */
    public function cmdDomain($console)
    {
        $console->setOption('domain', $console->getOption('domain', true));

        $this->cmdAll($console);
    }

    /**
     * @CMD(cmd.all)
     * @Alias(cmd)
     * @Option(vendor){notes=List cmds from DOF vendor packages only}
     * @DESC(List all available commands in this DOF project)
     */
    public function cmdAll($console)
    {
        $commands = $data = CommandManager::getData();
        $system = CommandManager::getSystem();
        $domain = CommandManager::getDomain();
        $vendor = CommandManager::getVendor();
        $filter = $_filter = null;
        if ($console->hasOption('system')) {
            $filter = $system;
        } elseif ($_filter = $console->getOption('vendor')) {
            $filter = $vendor;
        } elseif ($_filter = $console->getOption('domain')) {
            $filter = $domain;
        }
        \ksort($commands);

        $console->line();
        foreach ($commands as $cmd => $attr) {
            if ($filter && (! isset($filter[$cmd]))) {
                continue;
            }
            if ($_filter && ($_filter != ($filter[$cmd] ?? null))) {
                continue;
            }

            \extract($attr);
            $color = Color::SUCCESS;
            if (isset($vendor[$cmd])) {
                $color = Color::WARNING;
            } elseif (isset($domain[$cmd])) {
                $color = Color::TITLE;
            }
            $console->line(\join("\t", [
                $console->render($cmd, $color),
                $console->render(Str::fixed($comment, 64), Color::INFO),
            ]));

            // group cmds alphabetically
            if (false !== \next($commands)) {
                if (! Str::eq(\mb_strcut($cmd, 0, 1), \mb_strcut(\key($commands), 0, 1))) {
                    $console->line();
                }
            } else {
                $console->line();
            }
        }
    }

    /**
    * @CMD(vendor.install)
    * @Desc(Install given vendor packages into this DOF project)
    * @Option(update){notes=Force reinstall the latest version}
    * @Option(all){notes=Install all valid DOF vendor packages from composer vendor directory}
    */
    public function vendorInstall($console)
    {
        $list = [];
        $composer = DOF::path(Convention::DIR_VENDOR_COMPOSER);
        if ($console->hasOption('all')) {
            $console->info('Scanning composer vendor packages in this project ...');
            FS::ls(function ($vendors, $dir) use (&$list, $console) {
                foreach ($vendors as $vendor) {
                    $_vendor = FS::path($dir, $vendor);
                    if ((! \is_dir($_vendor)) || ($vendor === 'composer')) {
                        continue;
                    }
                    FS::ls(function ($packages, $dir) use (&$list, $console, $vendor) {
                        foreach ($packages as $package) {
                            $port = FS::path($dir, $package, Convention::DIR_VENDOR_DOF_PORT);
                            $name = "{$vendor}/{$package}";
                            $valid = $console->render('pass', Color::WARNING);
                            if (\is_dir($port)) {
                                $valid = $console->render('valid', Color::SUCCESS);
                                $list[] = [$vendor, $package, $port];
                            }
                            $console->line(\join(' ', [$valid, $console->render($name, Color::INFO)]));
                        }
                    }, $_vendor);
                }
            }, $composer);
        } else {
            foreach ($console->getParams() as $name) {
                $arr = \explode('/', $name);
                $vendor = $arr[0] ?? null;
                $package = $arr[1] ?? null;
                if ((\count($arr) !== 2) || (! $vendor) || (! $package)) {
                    $console->warn('Invalid vendor package', ['name' => $name, 'expect' => '{vendor}/{package}']);
                    continue;
                }
                $_vendor = FS::path($composer, $vendor, $package);
                if (! \is_dir($_vendor)) {
                    $console->warn('Vendor package not exists', \compact('name'));
                    continue;
                }
                $port = FS::path($_vendor, Convention::DIR_VENDOR_DOF_PORT);
                if (! \is_dir($port)) {
                    $console->warn('Invalid DOF vendor package, port dir missing', \compact('port'));
                    continue;
                }
                $list[] = [$vendor, $package, $port];
            }
        }

        if (! $list) {
            return $console->info('No vendor packages to install/update');
        }

        foreach ($list as list($vendor, $package, $port)) {
            $console->info("Installing `{$vendor}/{$package}` ...");
            foreach (self::VENDORS as $porter => $item) {
                $booter = FS::path($port, $porter);
                if (!\is_file($booter)) {
                    continue;
                }
                $dist = DOF::path($item, $vendor, $package, $porter);
                if (\is_file($dist)) {
                    if (! $console->hasOption('update')) {
                        $console->warn('Vendor item already installed', [
                            'vendor' => "{$vendor}/{$package}",
                            'item' => $porter,
                        ]);
                        continue;
                    }
                    FS::unlink($dist);
                }

                FS::copy($booter, $dist);

                $result = $console->render("{$vendor}/{$package}: {$porter}", Color::SUCCESS);
                $update = $console->hasOption('update') ? $console->render('(force update)', Color::INFO) : '';
                $console->line(\join(' ', [$result, $update]));
            }
        }
    }

    /**
     * @CMD(vendor.update)
     * @Desc(Update given vendor packages to the latest version)
     * @Option(all){notes=Update all valid DOF vendor packages}
     */
    public function vendorUpdate($console)
    {
        $console->setOption('update', true);

        $this->vendorInstall($console);
    }

    /**
     * @CMD(vendor.remove)
     * @Desc(Remove given vendor packages from this DOF project)
     * @Option(all){notes=Remove all vendor packages from this DOF project}
     */
    public function vendorRemove($console)
    {
        $list = [];
        $root = DOF::root();
        if ($console->hasOption('all')) {
            foreach (self::VENDORS as $booter => $path) {
                FS::ls(function ($vendors, $dir) use (&$list, $booter) {
                    foreach ($vendors as $vendor) {
                        $path = FS::path($dir, $vendor);
                        if (\is_dir($path)) {
                            FS::ls(function ($packages, $dir) use (&$list, $booter, $vendor) {
                                foreach ($packages as $package) {
                                    $path = FS::path($dir, $package);
                                    if (\is_dir($path)) {
                                        $list["{$vendor}/{$package}"][$booter] = $path;
                                    }
                                }
                            }, $path);
                        }
                    }
                }, $path);
            }
        } else {
            foreach ($console->getParams() as $name) {
                $arr = \explode('/', $name);
                $vendor = $arr[0] ?? null;
                $package = $arr[1] ?? null;
                if ((\count($arr) !== 2) || (! $vendor) || (! $package)) {
                    $console->warn('Invalid vendor package', ['name' => $name, 'expect' => '{vendor}/{package}']);
                    continue;
                }
                $exists = false;
                foreach (self::VENDORS as $booter => $path) {
                    $path = FS::path($root, $path, $vendor, $package);
                    if (\is_dir($path)) {
                        $exists = true;
                        $list["{$vendor}/{$package}"][$booter] = $path;
                    }
                }

                if (! $exists) {
                    $console->warn('Vendor package not exists', \compact('name'));
                }
            }
        }

        if (! $list) {
            return $console->info(
                $console->hasOption('all')
                ? 'No vendor packages found in this project'
                : 'No vendor packages to remove'
            );
        }
        if ($list && $console->hasOption('all')) {
            $console->warn('Removing all the '.\count($list).' vendor packages in this project ...');
        }

        foreach ($list as $vendor => $items) {
            $console->info("Removing {$vendor} ...");
            foreach ($items as $booter => $path) {
                $console->info("Removing `{$booter}` of {$vendor} ... ");
                FS::unlink($path);
                FS::unlink(\dirname($path));
            }
        }
    }

    /**
     * @CMD(compile)
     * @Alias(c)
     * @DESC(Compile ETC/ENV/INI/DMN into cache)
     */
    public function compile($console)
    {
        $this->compileCFG($console);
        $this->compileETC($console);
        $this->compileDMN($console);
        $this->compileLang($console);
    }

    /**
     * @CMD(compile.cfg)
     * @Alias(c.cfg)
     * @DESC(Compile ETC/ENV into cache)
     */
    public function compileCFG($console)
    {
        $console->task('Compiling ETC', function () {
            ETC::removeCompileFile();
            ETC::init(true);
        });
    }

    /**
     * @CMD(compile.etc)
     * @Alias(c.etc)
     * @DESC(Compile INI into cache)
     */
    public function compileETC($console)
    {
        $console->task('Compiling INI', function () {
            INI::removeCompileFile();
            INI::init(true);
        });
    }

    /**
     * @CMD(compile.lang)
     * @Alias(c.lang)
     * @Alias(c.i18n)
     * @DESC(Compile INI into cache)
     */
    public function compileLang($console)
    {
        $console->task('Compiling I18N', function () {
            I18N::removeCompileFile();
            I18N::init(true);
        });
    }

    /**
    * @CMD(compile.dmn)
    * @Alias(c.dmn)
    * @DESC(Compile DMN into cache)
    */
    public function compileDMN($console)
    {
        $console->task('Compiling DMN', function () {
            DMN::removeCompileFile();
            DMN::init(true);
        });
    }

    /**
     * @CMD(compile.clear)
     * @Alias(cc)
     * @DESC(Clear compile cache of configs(ETC/ENV/INI), DMN, managers, etc)
     * @Option(all){notes=Clear all compile cache}
     */
    public function compileClear($console)
    {
        if ($console->hasOption('all')) {
            $console->task('Clearing All compile cache', function () {
                FS::unlink(DOF::path(Convention::DIR_RUNTIME, Convention::DIR_COMPILE));
            });
            return;
        }

        $console->task('Clearing ETC compile cache', function () {
            ETC::removeCompileFile();
        });
        $console->task('Clearing INI compile cache', function () {
            INI::removeCompileFile();
        });
        $console->task('Clearing DMN compile cache', function () {
            DMN::removeCompileFile();
        });
        $console->task('Clearing I18N compile cache', function () {
            I18N::removeCompileFile();
        });
    }

    /**
     * @CMD(tpl.env)
     * @Desc(Init environment config template)
     * @Option(force){notes=Force init even if target file exists}
     */
    public function initConfigENV($console)
    {
        if (! \is_file($tpl = FS::path(DOF::root(false), Convention::DIR_TEMPLATE, 'env'))) {
            $console->error('Environment config template not found', \compact('tpl'));
        }
        if (\is_file($env = DOF::path(Convention::DIR_CONFIG, 'env.php'))) {
            if ($console->hasOption('force')) {
                FS::unlink($env);
            } else {
                $console->fail('Env file already exists.', \compact('env'));
            }
        }

        FS::copy($tpl, $env);
        $console->ok(DOF::pathof($env));
    }

    /**
     * @CMD(tpl.domain)
     * @Desc(Init domain config template)
     * @Option(force){notes=Force init even if target file exists}
     */
    public function initConfigDomain($console)
    {
        if (! \is_file($tpl = FS::path(DOF::root(false), Convention::DIR_TEMPLATE, 'domain'))) {
            $console->error('Domain config template not found', \compact('tpl'));
        }
        if (\is_file($domain = DOF::path(Convention::DIR_SETTING, 'domain.php'))) {
            if ($console->hasOption('force')) {
                FS::unlink($domain);
            } else {
                $console->fail('Domain config file already exists.', \compact('domain'));
            }
        }

        FS::copy($tpl, $domain);
        $console->ok(DOF::pathof($domain));
    }

    /**
     * @CMD(tpl.framework)
     * @Desc(Init framework config template)
     * @Option(force){notes=Force init even if target file exists}
     */
    public function initConfigFramework($console)
    {
        if (! \is_file($tpl = FS::path(DOF::root(false), Convention::DIR_TEMPLATE, 'framework'))) {
            $console->error('Framework config template not found', \compact('tpl'));
        }
        if (\is_file($framework = DOF::path(Convention::DIR_SETTING, 'framework.php'))) {
            if ($console->hasOption('force')) {
                FS::unlink($framework);
            } else {
                $console->fail('Framework config file already exists.', \compact('framework'));
            }
        }

        FS::copy($tpl, $framework);
        $console->ok(DOF::pathof($framework));
    }

    /**
     * @CMD(tpl.docs)
     * @Desc(Init docs config template)
     * @Option(force){notes=Force init even if target file exists}
     */
    public function initConfigDocs($console)
    {
        if (! \is_file($tpl = FS::path(DOF::root(false), Convention::DIR_TEMPLATE, 'docs'))) {
            $console->error('Docs config template not found', \compact('tpl'));
        }
        if (\is_file($docs = DOF::path(Convention::DIR_SETTING, 'docs.php'))) {
            if ($console->hasOption('force')) {
                FS::unlink($docs);
            } else {
                $console->fail('Docs config file already exists.', \compact('docs'));
            }
        }

        FS::copy($tpl, $docs);
        $console->ok(DOF::pathof($docs));
    }

    /**
     * @CMD(err.add)
     * @Desc(Create Err class for a domain)
     * @Option(domain){notes=Domain name of Err class}
     * @Option(no){notes=Err code prefix&default=Domain NO}
     */
    public function addErr($console)
    {
        $name = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($name))) {
            $console->fail('DomainNotExists', \compact('name'));
        }
        $err = FS::path($path, Convention::FILE_ERR);
        $pathof = DOF::pathof($err);
        if (\is_file($err) && (! $console->hasOption('force'))) {
            $console->fail('ERR_EXISTS', ['err' => $pathof]);
        }

        $no = $console->getOption('no', DMN::meta($name, 'no'));

        $domain = Format::u2c($name, CASE_UPPER);
        $entity = \strtoupper($domain);
        $init = <<<PHP
<?php

declare(strict_types=1);

namespace Domain\\{$domain};

/**
 * User defined errors in domain {$domain}
 *
 * #0: int; Global unique error code among domains
 * #1: string; Error default description in a language, support variable placeholder
 * #2: string; Error default suggestion in a language
 * 
 * For example: `const USER_NOF_FOUND = [{$no}40401, 'User not exists', 'Please contact administrator for help'];`
 * 
 * @NO({$no})
 */
class Err
{
    // const {$entity}_NOT_FOUND = [{$no}40401];
    // const NOTHING_TO_UPDATE = [{$no}20201];
}
PHP;

        $console->task("Creating Err: {$pathof}", function () use ($err, $init) {
            FS::unlink($err);
            FS::save($err, $init);
        });
    }

    /**
     * @CMD(cmd.add)
     * @Desc(Create a domain command class)
     * @Option(cmd){notes=Command name to be Created}
     * @Option(domain){notes=Domain name of command to be created}
     * @Option(force){notes=Whether force recreate command when given command class exists}
     */
    public function addCMD($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('cmd', 'Command'), CASE_UPPER));
        if (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }

        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_COMMAND, "{$name}.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('CommandAlreadyExists', ['command' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }
        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, 'command'))) {
            $console->error('CommandClassTemplateNotExist', \compact('template'));
        }

        $_domain = Format::namespace(DMN::name($domain), '.', true);

        $command = \file_get_contents($template);
        $command = \str_replace('__DOMAIN__', $_domain, $command);
        $command = \str_replace('__DOMAIN_LOWER__', \strtolower($_domain), $command);
        $command = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $command);
        $command = \str_replace('__NAME__', \basename($name), $command);

        $console->task("Creating Command: {$pathof}", function () use ($class, $command) {
            FS::unlink($class);
            FS::save($class, $command);
        });
    }

    /**
     * @CMD(domain.add)
     * @Desc(Create a new domain)
     * @Option(title){notes=Domain title with a language&default=Domain Name}
     * @Option(no){notes=Domain NO&default=Auto Increment}
     * @Argv(1){notes=Domain name to be Created}
     */
    public function addDomain($console)
    {
        if (! ($name = $console->first())) {
            $console->fail('MISSING_DOMAIN_NAME');
        }
        if (DMN::path($name)) {
            $console->fail('DOMAIN_ALREADY_EXISTS', \compact('name'));
        }

        $no = $console->getOption('no', (\count(DMN::list()) + 1));

        $domain = Format::u2c($name, CASE_UPPER);
        $title  = $console->getOption('title', $domain);
        $init = <<<PHP
<?php

return [
    'no' => '{$no}',

    'title' => '{$title}',
];
PHP;
        $console->task("Creating Domain: {$domain}", function () use ($domain, $init) {
            FS::save(DOF::path(Convention::DIR_DOMAIN, $domain, Convention::FLAG_DOMAIN), $init);
            
            DMN::init();    // refresh DMN
        });
    }
}
