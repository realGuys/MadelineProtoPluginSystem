<?php

use danog\MadelineProto\API;
use danog\MadelineProto\APIWrapper;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Loop\Generic\GenericLoop;
use danog\MadelineProto\RPCErrorException;
use realSamy\tools\ConfigHelper;
use realSamy\tools\DatabaseHandler;

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
if ($configHandler->get('SETUP_DONE') === null) {
    if (!isset($_POST['OWNER'])) {
        echo <<<'HTML'
<!doctype html>
<html lang="fa">
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
<body class="" dir="rtl">

<main class="level-item py-6">
    <form class="control" method="post">
        <div class="card has-shadow">
            <div class="card-header px-4 py-3"> لطفا اطلاعات خواسته شده را با دقت وارد کنید!</div>
            <div class="card-content">
                <label class="label" for="adminID">آیدی ادمین: </label>
                <input class="input is-primary" id="adminID" name="OWNER" required type="text">
                <hr>
              <label class="label" for="MadelineVer">انتخاب ورژن میدلاین: </label>
              <select name="MADELINE_VERSION" dir="rtl" class="input" id="MadelineVer">
                <optgroup label = "نیازمند پی اچ پی 7.4 به بالا">
                <option value="new">جدید (همراه با استفاده از MySQL به جای رم)</option>
                  </optgroup>
                    <optgroup label="نیازمند پی اچ پی 7.0 به بالا">
                <option value="old">قدیمی</option>
                      </optgroup>
              </select>
              <hr>
                <h5 class="has-text-centered">اطلاعات دیتابیس</h5>
                <hr>
                <label class="label" for="host">آدرس هاست: </label>
                <input class="input is-primary" id="host" name="DATABASE_HOST" required type="text" value="localhost">
                <label class="label" for="username">نام کاربری: </label>
                <input class="input is-primary" id="username" name="DATABASE_USERNAME" required type="text">
                <label class="label" for="password">رمز:</label>
                <input class="input is-primary" id="password" name="DATABASE_PASSWORD" required type="password">
                <label class="label" for="database">نام دیتابیس:</label>
                <input class="input is-primary" id="database" name="DATABASE_NAME" required type="text">


            </div>
            <div class="card-footer">
                <input class="button is-primary input" type="submit" value="ثبت اطلاعات">
            </div>

        </div>
    </form>
</main>
</body>
</html>
HTML;
        exit();
    }
    $configHandler->set('SETUP_DONE', true);
    $configHandler->setArray($_POST);
    $configHandler->setArray([
        'BOT_STATE'                => true,
        'BOT_GET_REPORTS'          => false,
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

$databaseHandler = new DatabaseHandler($configHandler->get('DATABASE_HOST'), $configHandler->get('DATABASE_USERNAME'), $configHandler->get('DATABASE_PASSWORD'), $configHandler->get('DATABASE_NAME'));
try {
    $databaseHandler->rawQuery(
        <<<'sql'
CREATE TABLE IF NOT EXISTS bot_admins 
(
	`id` INT AUTO_INCREMENT PRIMARY KEY NOT NULL,
	`username` VARCHAR(30) NULL,
	`user_id` BIGINT UNIQUE NOT NULL,
	`first_name` VARCHAR(30) NOT NULL,
	`last_name` VARCHAR(30) NULL,
	`added` DATETIME DEFAULT current_timestamp() NOT NULL
) DEFAULT CHARSET=utf8mb4;
sql
    );

} catch (Exception $e) {
    Logger::log($e);
    die($e->getMessage());
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
     * @var ConfigHelper
     */
    public $configHandler;
    /**
     * @var DatabaseHandler
     */
    public $databaseHandler;

    public function __construct(?APIWrapper $MadelineProto)
    {
        $this->configHandler = new ConfigHelper(md5(__FILE__));
        $this->databaseHandler = new DatabaseHandler($this->configHandler->get('DATABASE_HOST'), $this->configHandler->get('DATABASE_USERNAME'), $this->configHandler->get('DATABASE_PASSWORD'), $this->configHandler->get('DATABASE_NAME'));
        parent::__construct($MadelineProto);
    }

    /**
     * Get peer(s) where to report errors
     *
     * @return int|string|array
     */
    final public function getReportPeers(): array
    {
        return $this->configHandler->get('BOT_GET_REPORTS') ? [$this->configHandler->get('OWNER')] : [];
    }

    /**
     * Called on startup, can contain async calls for initialization of the bot
     */
    final public function onStart(): void
    {
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
                    $closure = $closure->bind($this, self::class);
                } catch (Throwable $e) {
                    Logger::log($e);
                    $this->report($e->getMessage());
                }
            }
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
                switch ($roleName) {
                    case 'owner':
                        if ($update['message']['from_id'] !== yield $this->getInfo($this->configHandler->get('OWNER'))['bot_api_id']) {
                            return;
                        }
                        foreach ($closures as $command => $closure) {
                            if (stripos($update['message']['message'] ?? null, $command) === 0) {
                                yield $closure($update);
                            }
                        }
                        break;
                    case 'admin':
                        if (!in_array($update['message']['from_id'], array_merge([yield $this->getInfo($this->configHandler->get('OWNER'))['bot_api_id']], array_column($this->databaseHandler->get('realSamy_tabchi_admins'), 'user_id')), false)) {
                            return;
                        }
                        foreach ($closures as $command => $closure) {
                            if (stripos($update['message']['message'] ?? null, $command) === 0) {
                                yield $closure($update);
                            }
                        }
                        break;
                    case 'user':
                    default:
                        foreach ($closures as $command => $closure) {
                            if (stripos($update['message']['message'] ?? null, $command) === 0) {
                                yield $closure($update);
                            }
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            if (stripos($e->getMessage(), 'invalid constructor given') === false) {
                $this->report("Error: " . $e->getMessage());
            }
        }
    }
}

$MadelineProto = new API('realSamy.madeline', $settings);
$MadelineProto->startAndLoop(realGuys::class);
