<?php

use Amp\Mysql;
use Amp\Mysql\Pool;
use Amp\Sql\ConnectionException;
use Amp\Sql\FailureException;
use danog\MadelineProto\API;
use danog\MadelineProto\APIWrapper;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Logger;
use realSamy\tools\CliTextHandler;
use realSamy\tools\ConfigHelper;

include 'autoload.php';

function arrayMerge(array $array1, array $array2)
{
    $merged = $array1;
    foreach ($array2 as $key => $value) {
        if (is_array($value) && isset ($merged [$key]) && is_array($merged [$key])) {
            $merged [$key] = arrayMerge($merged [$key], $value);
        }
        else {
            $merged [$key] = $value;
        }
    }
    return $merged;
}

$configHandler = new ConfigHelper(md5(__FILE__));
if (isset($_GET['config']) || (isset($argv) && is_array($argv) && in_array('--config', $argv, true)) || $configHandler->get('SETUP_DONE') === null) {
    if (PHP_SAPI === 'cli') {
        version:
        $madelineVersion = strtolower(CliTextHandler::readline('MadelineProto version (new/old): %s', 3, true));
        if (!in_array($madelineVersion, ['old', 'new'])) {
            echo CliTextHandler::echo_red('Please type and enter "old" or "new"') . PHP_EOL;
            goto version;
        }
        admin:
        $adminID = CliTextHandler::readline('Admin ID or @username: ');
        if (!preg_match('/^([@]?([a-z]+[a-z_\d]{4,}))|([\d]{6,})/i', $adminID)) {
            echo CliTextHandler::echo_red('Admin ID was not correct, enter valid username or id') . PHP_EOL;
            goto admin;
        }
        $configs = [
            'OWNER'             => $adminID,
            'MADELINE_VERSION'  => $madelineVersion,
            'DATABASE_HOST'     => CliTextHandler::readline('database host: '),
            'DATABASE_USERNAME' => CliTextHandler::readline('database username: '),
            'DATABASE_PASSWORD' => CliTextHandler::readline('database password: '),
            'DATABASE_NAME'     => CliTextHandler::readline('database name: '),
        ];
    }
    elseif (!isset($_POST['OWNER'])) {
        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <title>Tabchi config</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.0/css/bulma-rtl.min.css" rel="stylesheet">
    <style>
        @import url('https://cdn.fontcdn.ir/Font/Persian/Vazir/Vazir.css');

        body {
            background: linear-gradient(120deg, #00ff7b 0%, #00ffff 100%);
            font-family: Vazir, sans-serif;
        }
    </style>
</head>
<body class="">

<main class="level-item py-6">
    <form class="control" method="post">
        <div class="card has-shadow">
            <div class="card-header px-4 py-3"> Please Enter All Requested Fields!</div>
            <div class="card-content">
                <label class="label" for="adminID">Admin Identity: </label>
                <input class="input is-primary" id="adminID" name="OWNER" value="{$configHandler->get('OWNER')}" required type="text">
                <hr>
              <label class="label" for="MadelineVer">Select MadelineProto Version: </label>
              <select name="MADELINE_VERSION" class="input" id="MadelineVer">
                <optgroup label = "php7.4+">
                <option value="new">New (Unofficial Phar, Uses MySQL Instead Of Ram)</option>
                  </optgroup>
                    <optgroup label="php7+">
                <option value="old">Old</option>
                      </optgroup>
              </select>
              <hr>
                <h5 class="has-text-centered">Database</h5>
                <hr>
                <label class="label" for="host">Host: </label>
                <input class="input is-primary" id="host" name="DATABASE_HOST" required type="text" value="{$configHandler->get('DATABASE_HOST', 'localhost')}">
                <label class="label" for="username">Username: </label>
                <input class="input is-primary" id="username" name="DATABASE_USERNAME" value="{$configHandler->get('DATABASE_USERNAME')}" required type="text">
                <label class="label" for="password">Password:</label>
                <input class="input is-primary" id="password" name="DATABASE_PASSWORD" value="'{$configHandler->get('DATABASE_PASSWORD')}'" type="password">
                <label class="label" for="database">Database:</label>
                <input class="input is-primary" id="database" name="DATABASE_NAME" value="{$configHandler->get('DATABASE_NAME')}" required type="text">


            </div>
            <div class="card-footer">
                <input class="button is-primary input" type="submit" value="Start">
            </div>

        </div>
    </form>
</main>
</body>
</html>
HTML;
        exit();
    }
    else {
        $configs = $_POST;
    }
    $configHandler->set('SETUP_DONE', true);
    $configHandler->setArray($configs);
    $configHandler->setArray([
        'BOT_STATE'       => true,
        'BOT_GET_REPORTS' => false,
    ]);
}
switch ($configHandler->get('MADELINE_VERSION', 'new')) {
    case 'old':
        if (!file_exists('madeline.php')) {
            copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
        }
        include 'madeline.php';
        break;
    default:
        if (!file_exists('MadelineProto.php')) {
            copy('https://MadelineProto.realsamy.ir/', 'MadelineProto.php');
        }
        include 'MadelineProto.php';
        break;
}
$settings = [
    'logger'        => [
        'max_size' => 1 * 1024 * 1024,
    ],
    'serialization' => [
        'cleanup_before_serialization' => true,
    ],
    'app_info'      => [
        'api_id'   => 839407,
        'api_hash' => '0a310f9d03f51e8aa00d9262ef55d62e',
    ],
    'db'            => [
        'type'  => 'mysql',
        'mysql' => [
            'host'     => $configHandler->get('DATABASE_HOST'),
            'port'     => '3306',
            'user'     => $configHandler->get('DATABASE_USERNAME'),
            'password' => $configHandler->get('DATABASE_PASSWORD'),
            'database' => $configHandler->get('DATABASE_NAME'),
        ],
    ],
];

/**
 * Event handler class.
 */
class realGuys extends EventHandler
{
    /**
     * @var array
     */
    protected static $closures = [];
    /**
     * @var Pool
     */
    protected static $db;
    /**
     * @var ConfigHelper
     */
    public $configHandler;
    public $ownerID;

    public function __construct(?APIWrapper $MadelineProto)
    {
        $this->configHandler = new ConfigHelper(md5(__FILE__));
        $config = Mysql\ConnectionConfig::fromString(
            "host=" . $this->configHandler->get('DATABASE_HOST') . " user=" . $this->configHandler->get('DATABASE_USERNAME') . " password=" . $this->configHandler->get('DATABASE_PASSWORD') . " db=" . $this->configHandler->get('DATABASE_NAME')
        );
        static::$db = Mysql\pool($config, 10);
        parent::__construct($MadelineProto);
    }

    /**
     * Get peer(s) where to report errors
     *
     * @return int|string|array
     */
    final public function getReportPeers(): array
    {
        return (bool)($this->configHandler->get('BOT_GET_REPORTS', false) ?? false) ? [$this->ownerID ?? $this->configHandler->get('OWNER')] : [];
    }

    /**
     * Called on startup, can contain async calls for initialization of the bot
     *
     * @return Generator
     */
    final public function onStart(): Generator
    {
        try {
            $this->ownerID = yield $this->getInfo($this->configHandler->get('OWNER'))['bot_api_id'];
            yield static::$db->query('CREATE TABLE IF NOT EXISTS bot_admins 
(
	`id` INT AUTO_INCREMENT PRIMARY KEY NOT NULL,
	`username` VARCHAR(30) NULL,
	`user_id` BIGINT UNIQUE NOT NULL,
	`first_name` VARCHAR(30) NOT NULL,
	`last_name` VARCHAR(30) NULL,
	`added` DATETIME DEFAULT current_timestamp() NOT NULL
) DEFAULT CHARSET=utf8mb4;');
            static::$closures = [];
            $merged = [];
            foreach (glob('plugins/*') as $plugin) {
                try {
                    $include = include $plugin;
                    $merged = arrayMerge(static::$closures, $include);
                } catch (Throwable $e) {
                    Logger::log($e);
                    $this->report($e->getMessage());
                }
            }
            static::$closures = $merged;
            foreach (static::$closures as $role => $closures) {
                foreach ($closures as &$closure) {
                    try {
                        $closure = $closure::bind($closure, $this);
                    } catch (Throwable $e) {
                        Logger::log($e);
                        $this->report($e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            $this->report("Error:\n$e");
        }
    }

    /**
     * Handle updates from supergroups and channels
     *
     * @param array $update Update
     * @return Generator
     */
    final public function onUpdateNewChannelMessage(array $update): Generator
    {
        return $this->onUpdateNewMessage($update);
    }

    /**
     * Handle updates from users.
     *
     * @param array $update Update
     * @return Generator
     */
    final public function onUpdateNewMessage(array $update): Generator
    {
        if ($update['message']['_'] === 'messageEmpty' || $update['message']['out'] ?? false) {
            return;
        }
        try {
            foreach (static::$closures as $roleName => $closures) {
		if ($roleName === 'owner' && $update['message']['from_id'] !== $this->ownerID) {
                    continue;
                }
                if ($roleName === 'admin' && $update['message']['from_id'] !== $this->ownerID && !$this->isAdmin($update['message']['from_id'])) {
                    continue;
                }
                switch ($roleName) {
                    case 'owner':
                        foreach ($closures as $command => $closure) {
                            if (stripos($update['message']['message'] ?? '', $command) === 0) {
                                if (($result = $closure($update)) instanceof Generator) {
                                    yield from $result;
                                }
                                break 3;
                            }
                        }
                        break;
                    case 'admin':
                        foreach ($closures as $command => $closure) {
                            if (stripos($update['message']['message'] ?? '', $command) === 0) {
                                if (($result = $closure($update)) instanceof Generator) {
                                    yield from $result;
                                }
                                break 3;
                            }
                        }
                        break;
                    case 'user':
                    default:
                        foreach ($closures as $command => $closure) {
                            if (stripos($update['message']['message'] ?? '', $command) === 0) {
                                if (($result = $closure($update)) instanceof Generator) {
                                    yield from $result;
                                }
                                break 3;
                            }
                        }
                        break;
                }
            }
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'invalid constructor given') === false) {
                $this->report("Error: " . $e);
            }
        }
    }

    /**
     * @param $user
     * @return Generator
     * @throws ConnectionException
     * @throws FailureException
     * @throws Throwable
     */
    final public function isAdmin($user): Generator
    {
        $userID = yield $this->getInfo($user)['bot_api_id'];
        $result = yield static::$db->execute("SELECT * FROM bot_admins WHERE user_id = ? LIMIT 1", [$userID]);
        $row = [];
        while (yield $result->advance()) {
            $row += $result->getCurrent();
        }
        return $row !== [];
    }
}

$MadelineProto = new API('realSamy.madeline', $settings);
$MadelineProto->startAndLoop(realGuys::class);
