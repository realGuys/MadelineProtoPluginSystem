<?php
/**
 * Owner commands
 *
 * @author  realSamy
 * @version 1.2
 */

/**
 * Restarts the bot
 *
 * @param array $update
 * @return Generator
 */
$command['owner']['restart'] = function (array $update): Generator {
    $text = "Bot restarted!";
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
 *
 * @param array $update
 * @return Generator
 */
$command['owner']['reload'] = function (array $update): Generator {
    $sent = yield $this->messages->sendMessage([
        'peer'            => $update,
        'message'         => "Reloading plugin system... (1/2)",
        'parse_mode'      => 'Markdown',
        'reply_to_msg_id' => $update['message']['id'] ?? null,
    ]);
    yield $this->onStart();
    yield $this->messages->editMessage([
        'peer'       => $update,
        'message'    => "Plugins reloaded!",
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
            $text = "Provided identity is not a valid user or bot don't meet that user yet";
        }
        else {
            try {
                $statement = yield static::$db->prepare("INSERT INTO bot_admins (username, first_name, user_id, last_name) VALUES (:username, :first_name, :user_id, :last_name)");
                yield $statement->execute([
                    'user_id'    => $user['id'],
                    'username'   => $user['username'] ?? null,
                    'first_name' => $user['first_name'] ?? $user['id'],
                    'last_name'  => $user['last_name'] ?? null,
                ]);
                $text = "User [{$user['first_name']}](mention:{$user['id']}) added to admin list";
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'Duplicate')) {
                    $text = "User [{$user['first_name']}](mention:{$user['id']}) already is an admin";
                }
                else {
                    $text = "Couldn't add user to admin list\nError:\n" . $e->getMessage();
                }
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
        try {
            $statement = yield static::$db->prepare("INSERT INTO bot_admins (username, first_name, user_id, last_name) VALUES (:username, :first_name, :user_id, :last_name)");
            yield $statement->execute([
                'user_id'    => $user['id'],
                'username'   => $user['username'] ?? null,
                'first_name' => $user['first_name'] ?? $user['id'],
                'last_name'  => $user['last_name'] ?? null,
            ]);
            $text = "User [{$user['first_name']}](mention:{$user['id']}) added to admin list";
        } catch (Throwable $e) {
            $text = "Couldn't add user to admin list\nError:\n" . $e->getMessage();
        }
    }
    else {
        $text = "Send the command like `addAdmin @username` or reply on a user and send `addAdmin`";
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
 *
 * @param array $update
 * @return Generator
 */
$command['owner']['delAdmin'] = function (array $update): Generator {
    if (preg_match("/^(deladmin) (.*)$/i", $update['message']['message'], $matches)) {
        $user = yield $this->getInfo($matches[2])['User'];
        if (!isset($user['id'])) {
            $text = "Provided identity is not a valid user or bot don't meet that user yet";
        }
        else {
            try {
                yield static::$db->execute("DELETE FROM bot_admins WHERE user_id = ?", [$user['id']]);
                $text = "User [{$user['first_name']}](mention:{$user['id']}) deleted from admin list";
            } catch (Throwable $e) {
                $text = "User couldn't be deleted!\nError:\n" . $e->getMessage();
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
        try {
            yield static::$db->execute("DELETE FROM bot_admins WHERE user_id = ?", [$user['id']]);
            $text = "User [{$user['first_name']}](mention:{$user['id']}) deleted from admin list";
        } catch (Throwable $e) {
            $text = "User couldn't be deleted!\nError:\n" . $e->getMessage();
        }
    }
    else {
        $text = "Send the command like `delAdmin @username` or reply on a user and send `delAdmin`";
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
 *
 * @param array $update
 * @return Generator
 */
$command['owner']['listAdmin'] = function (array $update): Generator {
    $strResult = null;
    $c = 1;
    $result = yield static::$db->query("SELECT * FROM bot_admins");
    while (yield $result->advance()) {
        $row = $result->getCurrent();
        $strResult .= "$c) [{$row['first_name']} {$row['last_name']}](mention:{$row['user_id']})" . PHP_EOL;
        $c++;
    }
    if (!is_null($strResult)) {
        $text = "Admins list:\n" . $strResult;
    }
    else {
        $text = "Admins list is empty!";
    }
    yield $this->messages->sendMessage([
        'peer'            => $update,
        'message'         => $text,
        'parse_mode'      => 'Markdown',
        'reply_to_msg_id' => $update['message']['id'],
    ]);
};
return $command;
