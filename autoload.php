<?php
/**
 * ConfigHandler Class
 *
 * @category  Autoloader
 * @package   MadelineProto Plugin System
 * @author    Saman Hoodaji <contact@realSamy.ir>
 * @copyright Copyright (c) 2019-2020
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link      http://github.com/realGuys/MadelineProtoPluginSystem
 * @version   1.0.0
 */
 
spl_autoload_register(function ($name) {
    $path = str_replace(['realSamy\\tools', '\\'], [__DIR__ . '/includes', '/'], $name) . '.php';
    if (file_exists($path)) include $path;
});
