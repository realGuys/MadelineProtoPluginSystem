<?php

namespace realSamy\tools;
/**
 * Class CliHandler
 * @method static set_color_reset()
 * @method static set_color_black()
 * @method static set_color_red()
 * @method static set_color_green()
 * @method static set_color_yellow()
 * @method static set_color_blue()
 * @method static set_color_purple()
 * @method static set_color_cyan()
 * @method static set_color_white()
 * @method static set_color_light_black()
 * @method static set_color_light_red()
 * @method static set_color_light_green()
 * @method static set_color_light_yellow()
 * @method static set_color_light_blue()
 * @method static set_color_light_purple()
 * @method static set_color_light_cyan()
 * @method static set_color_light_white()
 * @method static echo_black(string $text)
 * @method static echo_red(string $text)
 * @method static echo_green(string $text)
 * @method static echo_yellow(string $text)
 * @method static echo_blue(string $text)
 * @method static echo_purple(string $text)
 * @method static echo_cyan(string $text)
 * @method static echo_white(string $text)
 * @method static echo_light_black(string $text)
 * @method static echo_light_red(string $text)
 * @method static echo_light_green(string $text)
 * @method static echo_light_yellow(string $text)
 * @method static echo_light_blue(string $text)
 * @method static echo_light_purple(string $text)
 * @method static echo_light_cyan(string $text)
 * @method static echo_light_white(string $text)

 */
class CliTextHandler
{
    private const reset = "\033[0m";
    private const black = "\033[38;5;0m";
    private const red = "\033[38;5;1m";
    private const green = "\033[38;5;2m";
    private const yellow = "\033[38;5;3m";
    private const blue = "\033[38;5;4m";
    private const purple = "\033[38;5;5m";
    private const cyan = "\033[38;5;6m";
    private const white = "\033[38;5;7m";
    private const light_black = "\033[38;5;8m";
    private const light_red = "\033[38;5;9m";
    private const light_green = "\033[38;5;10m";
    private const light_yellow = "\033[38;5;11m";
    private const light_blue = "\033[38;5;12m";
    private const light_purple = "\033[38;5;13m";
    private const light_cyan = "\033[38;5;14m";
    private const light_white = "\033[38;5;15m";

    /**
     *
     * @param  string|null $prompt - Text to be asked from user
     * @param  int         $len    - Length of user answer
     * @param  bool        $force  - Force user to answer with given length
     * @return string
     *
     */
    public static function readline(string $prompt = null, int $len = 1, bool $force = false): string
    {
        $pos = strpos($prompt, '%s');
        if ($pos !== false) {
            $prompt = sprintf($prompt, '[' . str_repeat(' ', $len) . ']');
        }
        $back = $pos ? strlen($prompt) - $pos - 1 : -1;
        prompt:
        echo $prompt . static::left($back);
        while (($res = fgets(STDIN)) === false) {
            usleep(10);
        }
        $res = str_replace(PHP_EOL, null, $res);
        if ($force && strlen($res) !== $len) {
            echo static::set_color_red() . "Your answer length: " . strlen($res) . " required length: $len" . static::set_color_reset() . PHP_EOL;
            goto prompt;
        }
        return $res;
    }

    public static function left(int $column): string
    {
        return "\033[" . $column . "D";
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @return string|null
     */
    public static function __callStatic(string $name, array $arguments): ?string
    {
        if (preg_match('/^set_color_(.*)$/', $name, $match)) {
            return defined('self::' . $match[1]) ? constant('self::' . $match[1]) : null ;
        }
        if (preg_match('/^echo_(.*)$/', $name, $match)) {
            return defined('self::' . $match[1]) ? constant('self::' . $match[1]) . $arguments[0] . static::set_color_reset() : null;
        }
        return null;
    }

    public static function up(int $line): string
    {
        return "\033[" . $line . "A";
    }

    public static function down(int $line): string
    {
        return "\033[" . $line . "B";
    }

    public static function right(int $column): string
    {
        return "\033[" . $column . "C";
    }
}
