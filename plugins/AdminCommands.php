<?php
/**
 * Admin commands
 *
 * @author  realSamy
 * @version 1.0.0
 */

/**
 * Just a simple ping!
 *
 * @param array $update
 * @return Generator
 */
$command['admin']['ping'] = function (array $update): Generator {
    yield $this->messages->sendMessage(['peer' => $update, 'message' => 'Pong!', 'reply_to_msg_id' => $update['message']['id'] ?? null]);
};

return $command;
