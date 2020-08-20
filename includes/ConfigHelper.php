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
        $this->loader();
    }

    private function configDecode(string $config): array
    {
        return json_decode($config, true) ?? [];
    }

    final public function get(string $key, string $default = null, bool $reload = true): ?string
    {
        !$reload || $this->loader();
        return $this->configArray[strtoupper($key)] ?? $default;
    }

    /**
     * @param array      $keys
     * @param array|null $default
     * @param bool       $reload
     * @return array
     */
    final public function getArray(array $keys, array $default = null, bool $reload = true):array
    {
        !$reload || $this->loader();
        $return = [];
        foreach ($keys as $index => $key) {
            $return[] = $this->configArray[$key] ?? $default[$index];
        }
        return $return;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    final public function set(string $key, $value): bool
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

    final public function loader(string $file = null): void
    {
        $this->configContents = file_get_contents($file ?? $this->configFile);
        $this->configArray = $this->configDecode($this->configContents);
    }
    final public function setArray(array $configArray): bool
    {
        foreach ($configArray as $key => $value) $this->configArray[$key] = $value;
        return $this->configSave();
    }
}
