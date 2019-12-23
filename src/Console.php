<?php

declare(strict_types=1);

namespace DOF\CLI;

use Throwable;
use Closure;
use DOF\Traits\Tracker;
use DOF\CLI\Color;
use DOF\CLI\Exceptor\CLIExceptor;
use DOF\Util\Exceptor;
use DOF\Util\Format;
use DOF\Util\JSON;
use DOF\Util\IS;
use DOF\Util\Str;
use DOF\Util\Collection;

class Console
{
    use Tracker;
    
    /** @var string: Entry of command */
    private $entry;

    /** @var string: Command name */
    private $name;

    /** @var array: Options of command */
    private $options;

    /** @var array: Parameters of command */
    private $params;

    public function __construct()
    {
        $this->options = new Collection([]);
    }

    public function wrap($text, string $padding, int $cnt = 1)
    {
        $padding = \str_repeat($padding, $cnt);

        return \sprintf("%s%s%s", $padding, $this->stringify($text), $padding);
    }

    public function task(string $desc, Closure $task)
    {
        $fail = 'Failed';
        $ok = 'OK';

        $output = [$this->render('[TASK]', Color::TITLE), $this->render($desc, Color::INFO), '...'];

        try {
            $status = $task($fail, $ok);
            if (false !== $task()) {
                $output[] = $this->render($ok, Color::SUCCESS);
            } else {
                $output[] = $this->render($fail, Color::FAIL);
            }
        } catch (Throwable $th) {
            $status = false;
            $output[] = $this->render('ERROR', Color::ERROR);
            $output[] = $this->render(JSON::pretty(Format::throwable($th)), Color::ERROR);
        }

        $this->line(\join(' ', $output));

        return $status;
    }

    public function line($text = null, int $cnt = 1, bool $wrap = false)
    {
        if (\is_null($text)) {
            echo \str_repeat(PHP_EOL, $cnt);
            return;
        }

        if ($wrap) {
            echo $this->wrap($text, PHP_EOL, $cnt), PHP_EOL;
            return;
        }

        echo $this->stringify($text), \str_repeat(PHP_EOL, $cnt);
    }

    public function output($result)
    {
        echo $this->stringify($result);
    }

    public function title($text)
    {
        $this->line($this->render($text, Color::TITLE));
    }

    public function progress(iterable $tasks, Closure $do, bool $outputProgress = false)
    {
        $current = 1;
        $total = \count($tasks);
        $output = [];

        $title = Str::buffer(function () use ($total) {
            $this->info(\sprintf("[%s] %s", Format::microtime('T Y-m-d H:i:s'), "Progress Tasks: {$total}"));
        });

        if (! $outputProgress) {
            $this->output($title);
        }

        $_output = '';
        foreach ($tasks as $key => $task) {
            $percent = ($current / $total) * 100;
            $_percent = \intval($percent);

            $done = $this->render(\str_repeat('*', $_percent), Color::SUCCESS);
            $left = $this->render(\str_repeat('·', (100 - $_percent)), Color::INFO);

            $__output = Str::buffer(function () use ($do, $key, $task) {
                $do($task, $key);
            });

            if ($outputProgress) {
                $_output .= $__output;
                $this->clear($title);
                $this->output($_output);
                $this->line();
            }

            \printf("\r(%d/%d) [%-100s] (%01.2f%%)", $current, $total, $done.$left, $percent);

            ++$current;
        }

        $this->line();

        $this->info(\sprintf("[%s] %s", Format::microtime('T Y-m-d H:i:s'), 'Progress Finished.'));
    }

    public function clear($output = null)
    {
        \printf("\033c");

        if (! \is_null($output)) {
            $this->output($output);
        }
    }

    public function info($text, array $context = [])
    {
        $this->line($this->render(\join(' ', ['[INFO]', $this->stringify($text)]), Color::INFO));
        if ($context) {
            $this->line($this->render(JSON::pretty($context), Color::INFO));
        }
    }

    public function warn($text, array $context = [])
    {
        $this->line($this->render(\join(' ', ['[WARN]', $this->stringify($text)]), Color::WARNING));
        if ($context) {
            $this->line($this->render(JSON::pretty($context), Color::WARNING));
        }
    }

    public function success($text = 'Success', array $context = [])
    {
        $this->ok($text, $context);
    }

    public function ok($text = 'OK', array $context = [])
    {
        $this->line($this->render(\join(' ', ['[OK]', $this->stringify($text)]), Color::SUCCESS));
        if ($context) {
            $this->line($this->render(JSON::pretty($context), Color::SUCCESS));
        }
    }

    public function error($text, array $context = [])
    {
        $this->line($this->render(\join(' ', ['[ERROR]', $this->stringify($text)]), Color::ERROR));
        if ($context) {
            $this->line($this->render(JSON::pretty($context), Color::ERROR));
        }
        $this->exit('error');
    }

    public function fail($text = 'Failed', array $context = [])
    {
        $this->line($this->render(\join(' ', ['[FAIL]', $this->stringify($text)]), Color::FAIL));
        if ($context) {
            $this->line($this->render(JSON::pretty($context), Color::FAIL));
        }
        $this->exit('fail');
    }

    public function render($text, string $color, bool $output = false) : ?string
    {
        $text = $this->stringify($text);

        if (! $this->hasOption('ascii')) {
            if (Color::has($color)) {
                $text = Color::render($text, $color);
            } else {
                $this->warn('Console color not found', \compact('color'));
            }
        }

        if ($output) {
            return $this->output($text);
        }

        return $text;
    }

    public function exceptor(...$params)
    {
        $this->line($this->render(\join(' ', ['[EXCEPTOR]', JSON::pretty(
            Format::throwable((new CLIExceptor(...$params))->setChain(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)))
        )]), Color::ERROR));

        $this->exit('exceptor');
    }

    public function throw(Throwable $throwable)
    {
        $this->line($this->render(\join(' ', ['[EXCEPTOR]', JSON::pretty(Format::throwable(($throwable)))]), Color::ERROR));

        $this->exit('throwable');
    }

    public function err(array $err, array $context = [], Throwable $previous = null)
    {
        $code = $code = ($err[0] ?? -1);
        $name = ErrManager::name();

        $exceptor = new Exceptor($name);
        $exceptor->setCode($code);
        $exceptor->setInfo($err[1] ?? null);
        $exceptor->setName($name);
        $exceptor->setSuggestion($err[2] ?? null);
        $exceptor->setContext($context);
        $exceptor->setPrevious($previous);
        $exceptor->setChain(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->line($this->render(\join(' ', ['[ERR]', JSON::pretty(Format::throwable(($exceptor)))]), Color::ERROR));

        $this->exit('ERR');
    }

    public function stringify($value) : string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_null($value)) {
            return '';
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if (\is_array($value)) {
            return JSON::encode($value, true);
        }

        if (\is_object($value)) {
            if (\method_exists($value, '__toString')) {
                return $this->stringify($value->__toString());
            }
            if (\method_exists($value, '__toArray')) {
                return $this->stringify($value->__toArray());
            }
        }

        throw new CLIExceptor('UNSTRINGIFIABLE_VALUE', \compact('value'));
    }

    public function exit(string $stderr)
    {
        $this->__TRACE_ROOT__->stderr = $stderr;

        exit;
    }

    public function first($default = null)
    {
        return $this->getParams()[0] ?? $default;
    }

    public function noOption(string $name) : bool
    {
        return !$this->options->has($name);
    }

    public function hasOption(string $name) : bool
    {
        return $this->options->has($name);
    }

    public function setOption(string $name, $val)
    {
        $this->options->set($name, $val);

        return $this;
    }

    public function confirmOption(string $name, $default) : bool
    {
        return IS::confirm($this->options->get($name, $default));
    }

    public function getOption(string $option, $default = null, bool $exception = false)
    {
        $_option = $this->options->get($option, $default);

        if ((IS::collection($_option) || \is_array($_option)) && (\count($_option) === 0)) {
            $_option = null;
        }

        if (\is_null($_option) && $exception) {
            return $this->fail('MISSING_OPTION', \compact('option'));
        }
        
        return \is_null($_option) ? $default : $_option;
    }

    /**
     * Getter for entry
     *
     * @return string
     */
    public function getEntry(): string
    {
        return $this->entry;
    }
    
    /**
     * Setter for entry
     *
     * @param string $entry
     * @return Console
     */
    public function setEntry(string $entry)
    {
        $this->entry = $entry;
    
        return $this;
    }

    /**
     * Getter for name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Setter for name
     *
     * @param string $name
     * @return Console
     */
    public function setName(string $name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Getter for options
     *
     * @return array
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }
    
    /**
     * Setter for options
     *
     * @param array $options
     * @return Console
     */
    public function setOptions(array $options)
    {
        $this->options = new Collection($options);
    
        return $this;
    }

    /**
     * Getter for params
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
    
    /**
     * Setter for params
     *
     * @param array $params
     * @return Console
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    
        return $this;
    }
}
