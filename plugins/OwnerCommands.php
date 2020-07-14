<?php
/**
 * Restarts the bot
 *
 * @param array $update
 * @return Generator
 */
$command['owner']['restart'] = function (array $update): Generator {
    $text = "راه اندازی مجدد انجام شد!";
    yield $this->messages->sendMessage([
        'peer'       => $update,
        'message'    => $text,
        'parse_mode' => 'Markdown',
    ]);
    yield $this->messages->deleteMessages(['id' => [$update['message']['id']], 'revoke' => true]);
    yield $this->restart();
};

/**
 * Reloads plugins
 * @param array $update
 * @return Generator
 */
$command['owner']['pluginsReload'] = function (array $update): Generator {
    $sent = yield $this->messages->sendMessage([
        'peer'            => $update,
        'message'         => "در حال به روز رسانی پلاگین سیستم... " . '(1/2)',
        'parse_mode'      => 'Markdown',
        'reply_to_msg_id' => $update['message']['id'] ?? null,
    ]);
    yield $this->onStart();
    yield $this->messages->editMessage([
        'peer'       => $update,
        'message'    => "به روز رسانی پلاگین سیستم انجام شد ",
        'parse_mode' => 'Markdown',
        'id'         => $sent['id'],
    ]);
};

/**
 * Add admins to bot
 *
 * @param array $update
 * @return Generator
 */
$command['owner']['addAdmin'] = function (array $update): Generator {
    if (preg_match("/^(addadmin) (.*)$/i", $update['message']['message'], $matches)) {
        $user = yield $this->getInfo($matches[2])['User'];
        if (!isset($user['id'])) {
            $text = "آیدی کاربر وارد شده اشتباه است و یا ربات تا کنون با این کاربر ارتباط نداشته است!";
        }
        else {
            $this->databaseHandler->reset();
            $db = $this->databaseHandler->insert('realSamy_tabchi_admins', [
                'username'   => $user['username'],
                'first_name' => $user['first_name'] ?? $user['id'],
                'user_id'    => $user['id'],
                'last_name'  => $user['last_name'] ?? null,
            ]);
            if ($db) {
                $text = "کاربر [{$user['first_name']}](mention:{$user['id']}) در ربات ادمین شد!";
            }
            else {
                $text = "کاربر مورد نظر ادمین نشد!\nجزئیات خطا:\n" . $this->databaseHandler->getLastError();
            }
        }
    }
    elseif ($update['message']['reply_to_msg_id'] ?? false) {
        switch (yield $this->getInfo($update)['type']) {
            case 'channel':
            case 'supergroup':
                $user = yield $this->channels->getMessages(['channel' => $update, 'id' => [$update['message']['reply_to_msg_id']]])['users'][0];
                break;
            default:
                $user = yield $this->messages->getMessages(['id' => [$update['message']['reply_to_msg_id']]])['users'][0];
        }
        $this->databaseHandler->reset();
        $db = $this->databaseHandler->insert('realSamy_tabchi_admins', [
            'username'   => $user['username'],
            'first_name' => $user['first_name'],
            'user_id'    => $user['id'],
            'last_name'  => $user['last_name'] ?? null,
        ]);
        if ($db) {
            $text = "کاربر [{$user['first_name']}](mention:{$user['id']}) در ربات ادمین شد!";
        }
        else {
            $text = "کاربر مورد نظر ادمین نشد!\nجزئیات خطا:\n" . $this->databaseHandler->getLastError();
        }
    }
    else {
        $text = "**دستور را باید یا بصورت `addAdmin @username` و یا با ریپلی روی پیام شخص مورد نظر بفرستید!**";
    }
    yield $this->messages->sendMessage([
        'peer'            => $update,
        'message'         => $text,
        'parse_mode'      => 'Markdown',
        'reply_to_msg_id' => $update['message']['id'],
    ]);
};

/**
 * Deletes bot admins
 * @param array $update
 * @return Generator
 */
$command['owner']['delAdmin'] = function (array $update): Generator {
    if (preg_match("/^(deladmin) (.*)$/i", $update['message']['message'], $matches)) {
        $user = yield $this->getInfo($matches[2])['User'] ?? null;
        if (is_null($user)) {
            $text = "آیدی کاربر وارد شده اشتباه است و یا ربات تا کنون با این کاربر ارتباط نداشته است!";
        }
        else {
            $this->databaseHandler->reset();
            $this->databaseHandler->where('user_id', $user['id']);
            $db = $this->databaseHandler->delete('realSamy_tabchi_admins');
            if ($db) {
                $text = "کاربر [{$user['first_name']}](mention:{$user['id']}) از ادمینی ربات عزل شد!";
            }
            else {
                $text = "کاربر مورد نظر ادمین ربات نیست!\nجزئیات خطا:\n" . $this->databaseHandler->getLastError();
            }
        }
    }
    elseif ($update['message']['reply_to_msg_id'] ?? false) {
        switch (yield $this->getInfo($update)['type']) {
            case 'channel':
            case 'supergroup':
                $user = yield $this->channels->getMessages(['channel' => $update, 'id' => [$update['message']['reply_to_msg_id']]])['users'][0];
                break;
            default:
                $user = yield $this->messages->getMessages(['id' => [$update['message']['reply_to_msg_id']]])['users'][0];
        }
        $this->databaseHandler->reset();
        $this->databaseHandler->where('user_id', $user['id']);
        $db = $this->databaseHandler->delete('realSamy_tabchi_admins');
        if ($db) {
            $text = "کاربر [{$user['first_name']}](mention:{$user['id']}) از ادمینی ربات عزل شد!";
        }
        else {
            $text = "کاربر مورد نظر ادمین ربات نیست!\nجزئیات خطا:\n" . $this->databaseHandler->getLastError();
        }
    }
    else {
        $text = "**دستور را باید یا بصورت `delAdmin @username` و یا با ریپلی روی پیام شخص مورد نظر بفرستید!**";
    }
    yield $this->messages->sendMessage([
        'peer'            => $update,
        'message'         => $text,
        'parse_mode'      => 'Markdown',
        'reply_to_msg_id' => $update['message']['id'],
    ]);
};

/**
 * Sends admins list
 * @param array $update
 * @return Generator
 */
$command['owner']['listAdmin'] = function (array $update): Generator {
    $text = "لیست ادمین های ربات:\n";
    $c = 1;
    $this->databaseHandler->reset();
    $this->databaseHandler->orderBy('realSamy_tabchi_admins.first_name');
    $admins = $this->databaseHandler->get('realSamy_tabchi_admins', null, 'user_id, first_name, username');
    if ($admins !== []) {
        foreach ($admins as $admin) {
            $adminName = $admin['first_name'];
            $adminID = $admin['user_id'];
            $text .= "$c) [$adminName](mention:$adminID)\n";
            $c++;
        }
    }
    else {
        $text = "لیست ادمین های ربات خالی است!";
    }
    yield $this->messages->sendMessage([
        'peer'            => $update,
        'message'         => $text,
        'parse_mode'      => 'Markdown',
        'reply_to_msg_id' => $update['message']['id'],
    ]);
};

return $command;
