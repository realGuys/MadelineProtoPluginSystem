<?php
/**
 * ConfigHandler Class
 *
 * @category  Local Config
 * @package   MadelineProto Plugin System
 * @author    Saman Hoodaji <contact@realSamy.ir>
 * @copyright Copyright (c) 2019-2020
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link      http://github.com/realGuys/MadelineProtoPluginSystem
 * @version   1.0.0
 */

namespace realSamy\tools;
class ConfigHelper
{
    private $configFile;
    private $configContents;
    private $configArray;

    public function __construct(string $file)
    {
        if (!file_exists($file)) {
            fclose(fopen($file, 'wb'));
        }
        $this->configFile = $file;
        $this->configContents = file_get_contents($file);
        $this->configArray = $this->configDecode($this->configContents);
    }

    private function configDecode(string $config): array
    {
        return json_decode($config, true) ?? [];
    }

    final public function get(string $key, string $default = null): ?string
    {
        return $this->configArray[strtoupper($key)] ?? $default;
    }
    final public function getArray(array $keys, array $default = null):array
    {
        $return = [];
        foreach ($keys as $index => $key) {
            $return[] = $this->configArray[$key] ?? $default[$index];
        }
        return $return;
    }
    final public function set(string $key, string $value): bool
    {
        $this->configArray[$key] = $value;
        return $this->configSave();
    }

    private function configSave(): bool
    {
        $this->configContents = $this->configEncode($this->configArray);
        return (boolean)file_put_contents($this->configFile, $this->configContents);
    }

    private function configEncode(array $config): string
    {
        return json_encode($config, 64|128|256);
    }

    final public function setArray(array $configArray): bool
    {
        foreach ($configArray as $key => $value) $this->configArray[$key] = $value;
        return $this->configSave();
    }
}
