# MadelineProto Plugin System V1.5
> A fully async plugin-friendly [MadelineProto](https://github.com/danog/MadelineProto) source base
___
Use this source code to make your ideas in a nice async way!
Just follow the way that source code itself is written in!

## Instructions
---
|**Table Of Contents**|
|---|
|[1-Installation](#1-Installation)|
|[1.1-Using Terminal](#11-Using-Terminal)|
|[1.1.1-Download Repository](#111-Download-Repository)|
|[1.1.2-Config And Run](#112-Config-And-Run)|
|[1.2-Using Browser](#12-Using-Browser)|
|[1.2.1-Download Repository](#121-Download-Repository)|
|[1.2.2-Config And Run](#122-Config-And-Run)|
|[2-Any time configuration](#2-Any-time-configuration)|
|[2.1-Using terminal](#21-Using-terminal)|
|[2.2-Using Browser](#22-Using-Browser)|
|[3-How To Use](#3-How-To-Use)|
|[3.1-Plugins](#31-Plugins)|
|[3.1.1-Syntax](#311-Syntax)|
|[3.1.2-Loading Plugins On Bot](#312-Loading-Plugins-On-Bot)|
|[3.1.3-Available Plugins](#313-Available-Plugins)|
|[3.1.4-Available Commands](#314-Available-Commands)|

### 1-Installation

#### 1.1-Using Terminal

##### 1.1.1-Download Repository
First clone this repository using git:
```bash
$ git clone https://github.com/realGuys/MadelineProtoPluginSystem.git
```
Go to it's directory:
```bash
$ cd MadelineProtoPluginSystem
```
##### 1.1.2-Config And Run
Just run it and config it through your terminal and answer prompts to config your bot settings:
```bash
$ php index.php
```
> Enjoy your first (user) bot using this Plugin System!
#### 1.2-Using Browser

##### 1.2.1-Download Repository
Download repository as zip from [here](https://github.com/realGuys/MadelineProtoPluginSystem/archive/master.zip), then upload it on your host and extract it.

##### 1.2.2-Config And Run
Run [index.php](https://github.com/realGuys/MadelineProtoPluginSystem/blob/master/index.php) file through your browser and fill asked forms to config your bot settings.

> Enjoy your first (user) bot using this Plugin System!


### 2-Any time configuration

#### 2.1-Using terminal
Run `index.php` file with `--config` flag:
```bash
$ php index.php --config
```
#### 2.2-Using Browser
Run `index.php` file in browser with `config` query:
```
http://yourdomain.ext/path/to/index.php?config
```

### 3-How To Use

#### 3.1-Plugins

##### 3.1.1-Syntax
Plugin files must return an array with two column of values, the first one describes `role` of user using the command, the second one is `command` itself!
You can make a plugin with two commands for two different roles just like this:
```php
<?php
/**
 * Just another simple plugin
**/

$commands['role1']['ping'] = function (array $update): \Generator
{
  yield $this->messages->sendMessage([
    'peer' => $update,
    'message' => 'Pong!'
  ]);
};
$commands['role2']['hello'] = function (array $update): \Generator
{
  yield $this->messages->sendMessage([
    'peer' => $update,
    'message' => 'Pong!'
  ]);
};

return $commands;
```

##### 3.1.2-Loading Plugins On Bot
No need to do that! The plugins will automatically load on starting bot.

##### 3.1.3-Available Plugins
There is some plugins for add admin access to other users, reloading plugins e.g. to apply new changes and so on in the `plugins` directory, use tham to make your own awesome plugins!
> Maybe I make some new plugins and add them to repository too, you may commit yours too!

##### 3.1.4-Available Commands
**Command**|**Information**
-----|-----
ping|Just a simple ping command!
getReports (`on`\|`off`)|Enable/Disable error reporting
addAdmin (`@username`\|`reply`)|Adds new admins to bot using addAdmin @username or simply reply this command on a message
addOwner (`@username`\|`reply`)|Adds new owners to bot, use just like `addAdmin`!
delAdmin (`@username`\|`reply`)|Removes admins from bot, use just like `addAdmin`!
listAdmin|Sends a list of current admins
reload|Reloads Plugin System `e.g. to apply new changes`
restart|Restarts the bot
shutdown|Shuts the bot down
