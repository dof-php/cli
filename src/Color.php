<?php

declare(strict_types=1);

namespace DOF\CLI;

class Color
{
    const INFO = 'LIGHT_GRAY';
    const TIPS = 'LIGHT_GREEN';
    const FAIL = 'RED';
    const TITLE = 'LIGHT_BLUE';
    const ERROR = 'LIGHT_RED';
    const SUCCESS = 'GREEN';
    const WARNING = 'YELLOW';

    /**
     * Console usable colors
     *
     * See: <http://blog.lenss.nl/2012/05/adding-colors-to-php-cli-script-output>
     */
    private static $list = [
        'BLACK'  => '0;30',
        'BLUE'   => '0;34',
        'GREEN'  => '0;32',
        'CYAN'   => '0;36',
        'RED'    => '0;31',
        'PURPLE' => '0;35',
        'BROWN'  => '0;33',
        'YELLOW' => '1;33',
        'WHITE'  => '1;37',
        'LIGHT_GRAY'   => '0;37',
        'DARK_GRAY'    => '1;30',
        'LIGHT_BLUE'   => '1;34',
        'LIGHT_GREEN'  => '1;32',
        'LIGHT_CYAN'   => '1;36',
        'LIGHT_RED'    => '1;31',
        'LIGHT_PURPLE' => '1;35',
    ];

    public static function has(string $color) : bool
    {
        return isset(self::$list[$color]);
    }

    public static function get(string $color) : ?string
    {
        return self::$list[$color] ?? null;
    }

    public static function render(string $text, string $color) : string
    {
        if ($_color = Color::get($color)) {
            return "\033[{$_color}m{$text}\033[0m";
        }

        return $text;
    }
}
