<?php

$gwt->unit('Testing Console::wrap()', function ($t) {
    $console = new \DOF\CLI\Console;
    $t->eq($console->wrap('aaa', '-', 2), '--aaa--');
});

$gwt->unit('Testing Console::line()', function ($t) {
    $console = new \DOF\CLI\Console;
    $t->eq(\DOF\Util\Str::buffer(function () use ($console) {
        $console->line();
    }), PHP_EOL);
    $t->eq(\DOF\Util\Str::buffer(function () use ($console) {
        $console->line(null, 2);
    }), PHP_EOL.PHP_EOL);
});

$gwt->unit('Tesing Console::stringify()', function ($t) {
    $console = new \DOF\CLI\Console;
    $t->exceptor(function () use ($console) {
        $console->stringify(function () {
        });
    });
});
