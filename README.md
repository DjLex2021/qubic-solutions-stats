# qubic-solutions-stats

Simple minimalist stats for Qubic Solutions pool with auto-reload and Telegram bot

It stores statistics in the “stat” file and sends a Telegram message to a bot when a solution is found with information about which device found the solution and an overview of all finds so far


#### Stats overview
![screenshot](https://github.com/DjLex2021/qubic-solutions-stats/blob/main/images/00.png?raw=true)
#### Device list
![screenshot](https://github.com/DjLex2021/qubic-solutions-stats/blob/main/images/01.png?raw=true)
#### Inactive devices list
![screenshot](https://github.com/DjLex2021/qubic-solutions-stats/blob/main/images/02.png?raw=true)
#### Current epoch luck
![screenshot](https://github.com/DjLex2021/qubic-solutions-stats/blob/main/images/03.png?raw=true)
#### Epoch history
![screenshot](https://github.com/DjLex2021/qubic-solutions-stats/blob/main/images/04.png?raw=true)
#### Device history by epoch
![screenshot](https://github.com/DjLex2021/qubic-solutions-stats/blob/main/images/05.png?raw=true)


## Installation
Edit variables in top of file stat.php
```php
$walletId = "YOUR QUBIC WALLET"

$sendTelegramMessage = true|false
$telegramUserName = "YOUR TELEGRAM USERNAME"
$telegramBotToken = "YOUT BOT ACCESS TOKEN"

```

Copy php file to your server

Create empty file 'stat' if folder is not writable and set write permissions to this file

## Usage

```
stat.php for Crontab
stat.php?show=1 to output infos to browser
```

## Known issues

From time to time, pooltemp.qubic.solutions no longer transmits miner labels until the miners are restarted. At this point, there will be a miner named 'noname' in the stats list.

