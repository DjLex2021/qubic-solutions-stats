<?php

/*
 * stat.php for Crontab
 * stat.php?show=1 to output infos to browser
 */

$walletId = "";

// TelegramBot
$sendTelegramMessage = false;
$telegramUserName = "";
$telegramBotToken = "";


$urlMinerstat = "https://pooltemp.qubic.solutions/info?miner=$walletId&list=true&ts=".time();
$statFile = "stat";


if(isset($_GET["delete"])) {
    $stat = json_decode(file_get_contents($statFile), true);
    unset($stat["devices"][ $_GET["delete"] ]);
    file_put_contents($statFile, json_encode($stat));

    echo "<meta http-equiv=\"refresh\" content=\"0; URL=stat.php?show=1\">";
}

if(isset($_GET["resetMessageId"])) {
    $stat = json_decode(file_get_contents($statFile), true);
    $stat["telegramMessageId"] = "";
    file_put_contents($statFile, json_encode($stat));

    echo "<meta http-equiv=\"refresh\" content=\"0; URL=stat.php?show=1\">";
}


/*
 * Extreme simplified Telegram sendMessage function
 *
 * Create a bot with BotFather
 * After '/start' you have to send at least one message from the bot chat,
 * to get getUpdates function response with your current message id
 */
function getTelegramMessageId($telegramUserName, $telegramBotToken) {
    $getUpdatesAPI = "https://api.telegram.org/bot".$telegramBotToken."/getUpdates";

    $updateResponse = file_get_contents($getUpdatesAPI);
    $updateData = json_decode($updateResponse, true);
    foreach($updateData["result"] AS $chat) {
        if($chat["message"]["from"]["username"] == $telegramUserName) {
            return $chat["message"]["chat"]["id"];
        }
    }
}

function sendTelegramMessage($message, $telegramBotToken = "", $chatID = "") {
    if($telegramBotToken == "" || $chatID == "") { return false; }

    $sendMessageAPI = "https://api.telegram.org/bot".$telegramBotToken."/sendMessage";

    if($chatID != "") {
        $data = [
            "chat_id" => $chatID,
            "text" => $message,
            "parse_mode" => "HTML"
        ];

        $options = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
                "content" => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        return file_get_contents($sendMessageAPI, false, $context);
    }
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="" />
    <meta http-equiv="refresh" content="30">

    <title>Stat</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.5/css/dataTables.bootstrap.min.css"/>
</head>
<body>

<?php
function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
    $sort_col = array();
    foreach ($arr as $key => $row) {
        $sort_col[$key] = $row[$col];
    }

    array_multisort($sort_col, $dir, $arr);
}


$stat = json_decode(file_get_contents($statFile), true);
if($stat == "") {
    $stat = [
        "date"                  => "",
        "epoch"                 => "",
        "iterrate"              => "",
        "deviceCount"           => "",
        "solutions"             => "",
        "telegramMessageId"     => "",
        "devices"               => [],
        "oldSolutions"          => [],
        "epochHistoryDevices"   => []
    ];
}

if($stat["telegramMessageId"] == "") {
    $stat["telegramMessageId"] = getTelegramMessageId($telegramUserName, $telegramBotToken);
}

if(time()-25 > strtotime($stat["date"])) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlMinerstat);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    $result = curl_exec($ch);
    $result = json_decode($result, true);
}

if(is_array($result)) {
    if($stat["solutions"] > $result["solutions"]) {
        $stat["oldSolutions"][$stat["epoch"]]["date"] = date("Y-m-d H:i:s");
        $stat["oldSolutions"][$stat["epoch"]]["solutions"] = $stat["solutions"];
    }

    if(!$stat["epochHistory"][$result["epoch"]][date("Y-m-d")]) {
        $stat["epochHistory"][$result["epoch"]][date("Y-m-d")] = 0;
    }

    if($stat["solutions"] < $result["solutions"]) {
        $foundBy = "";
        foreach($result["device_list"] AS $dev) {
            if($dev["solutions"] == 0) { continue; }
            if($dev["solutions"] > $stat["epochHistoryDevices"][$result["epoch"]][ $dev["label"] ]) {
                $foundBy = $dev["label"];
            }
        }

        $msg = "<b><u>Solution found by:</u></b>\n$foundBy\n\n<b><u>Total:</u></b>\n".$result["solutions"]."\n\n<b><u>Previously found:</u></b>\n";
        if(!is_array($stat["epochHistoryDevices"][$stat["epoch"]]) || count($stat["epochHistoryDevices"][$stat["epoch"]]) == 0) { $msg .= "none"; }
        foreach($stat["epochHistoryDevices"][$stat["epoch"]] AS $device => $sols) {
            $msg .= $device.": ".$sols."\n";
        }

        if($sendTelegramMessage) {
            sendTelegramMessage($msg, $telegramBotToken, $stat["telegramMessageId"]);
        }
        $stat["epochHistory"][$result["epoch"]][date("Y-m-d")]++;
    }

    $stat["date"] = date("Y-m-d H:i:s");
    $stat["epoch"] = $result["epoch"];
    $stat["iterrate"] = $result["iterrate"];
    $stat["deviceCount"] = $result["devices"];
    $stat["solutions"] = $result["solutions"];

    $activeDevices = [];
    foreach($result["device_list"] AS $dev) {
        $activeDevices[] = $dev["label"];
    }

    foreach($result["device_list"] AS $dev) {
        if($dev["solutions"] > 0) {
            $stat["epochHistoryDevices"][$result["epoch"]][ $dev["label"] != "" ? $dev["label"] : "noname" ] = $dev["solutions"];
        }

        $stat["devices"][ $dev["label"] ] = [
            "iterrate"          => $dev["last_iterrate"],
            "last_seen"         => date("Y-m-d H:i:s"),
            "solutions_current" => $dev["solutions"]
        ];
    }

    foreach($stat["devices"] AS $devId => $dev) {
        if(!in_array($devId, $activeDevices)) { $stat["devices"][$devId]["iterrate"] = 0; }
    }

    file_put_contents($statFile, json_encode($stat, JSON_PRETTY_PRINT));
}

if($_GET["show"] == 1) {
    if(is_array($stat)) {
        $o = '<div class="list-group" style="width: 360px; margin-left: 12px; margin-top: 20px;">
                <div class="list-group-item" style="cursor: unset;">
                    Time
                    <span class="pull-right text-muted small"><em>'.$stat["date"].'</em>
                    </span>
                </div>
                <div class="list-group-item" style="cursor: unset;">
                    Telegram-Bot
                    <span class="pull-right text-muted small"><em>'.($sendTelegramMessage ? "active (".$stat["telegramMessageId"].")" : "inactive").' <a href="stat.php?resetMessageId=1" style="font-family: Lucida Sans Unicode">&#x21bb;</a></em>
                    </span>
                </div>
                <div class="list-group-item" style="cursor: unset;">
                    Epoch
                    <span class="pull-right text-muted small"><em>'.$stat["epoch"].'</em>
                    </span>
                </div>
                <div class="list-group-item" style="cursor: unset;">
                    Iterrate total
                    <span class="pull-right text-muted small"><em>'.$stat["iterrate"].'</em>
                    </span>
                </div>
                <div class="list-group-item" style="cursor: unset;">
                    Devices
                    <span class="pull-right text-muted small"><em>'.$stat["deviceCount"].'</em>
                    </span>
                </div>
                <div class="list-group-item" style="cursor: unset;">
                    Solutions
                    <span class="pull-right text-muted small"><em>'.$stat["solutions"].' ( '.number_format($stat["solutions"] / count($stat["epochHistory"][ $stat["epoch"] ]), 2, ",", "").' &Oslash; )</em>
                    </span>
                </div>
            </div>';

        $o .= '<div class="table-responsive" style="width: 460px; margin-left: 12px;">
                <table class="table table-striped table-bordered table-hover" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: center;">#</th>
                            <th>Name</th>
                            <th style="text-align: center;">Iterrate</th>
                            <th style="text-align: center;">Sols</th>
                            <th style="text-align: center;">&Oslash;</th>
                            <th style="text-align: center;">Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>';

        $i = 1;
        array_sort_by_column($stat["devices"], "iterrate", SORT_DESC);
        foreach($stat["devices"] AS $devId => $dev) {
            $rowColor = $dev["last_seen"] != $stat["date"] ? "orange" : "";
            $devId = $dev["last_seen"] != $stat["date"] ? "
                <a href=\"stat.php?delete=$devId\">
                    <svg width=\"16\" height=\"16\" fill=\"currentColor\">
                      <path d=\"M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z\"/>
                      <path d=\"M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z\"/>
                    </svg>
                </a><br />".$devId : $devId;

            $o .= '     <tr style="background-color: '.$rowColor.';">
                            <td style="text-align: right;">'.$i.'</td>
                            <td style="white-space: normal;">'.$devId.'</td>
                            <td style="text-align: right;">'.number_format($dev["iterrate"], 2, ",", "").'</td>
                            <td style="text-align: right;">'.$dev["solutions_current"].'</td>
                            <td style="text-align: right;">'.number_format($dev["solutions_current"] / count($stat["epochHistory"][ $stat["epoch"] ]), 2, ",", "").'</td>
                            <td style="text-align: center; white-space: normal;">'.$dev["last_seen"].'</td>
                        </tr>';

            $i++;
        }

        $o .= '         </tbody>
                </table>
            </div>';

        $day1 = array_slice($stat["epochHistory"][$stat["epoch"]], 0, 1);
        $day2 = array_slice($stat["epochHistory"][$stat["epoch"]], 1, 1);
        $day3 = array_slice($stat["epochHistory"][$stat["epoch"]], 2, 1);
        $day4 = array_slice($stat["epochHistory"][$stat["epoch"]], 3, 1);
        $day5 = array_slice($stat["epochHistory"][$stat["epoch"]], 4, 1);
        $day6 = array_slice($stat["epochHistory"][$stat["epoch"]], 5, 1);
        $day7 = array_slice($stat["epochHistory"][$stat["epoch"]], 6, 1);
        $day8 = array_slice($stat["epochHistory"][$stat["epoch"]], 7, 1);
        $o .= '<h4 style="margin-left: 20px;">Current</h4>
                <div class="table-responsive" style="width: 360px; margin-left: 12px;">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th style="text-align: center;">Day 1</th>
                            <th style="text-align: center;">Day 2</th>
                            <th style="text-align: center;">Day 3</th>
                            <th style="text-align: center;">Day 4</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">'.reset($day1).'</td>
                            <td style="text-align: center;">'.reset($day2).'</td>
                            <td style="text-align: center;">'.reset($day3).'</td>
                            <td style="text-align: center;">'.reset($day4).'</td>
                        </tr>
                    </tbody>
                    <thead>
                        <tr>
                            <th style="text-align: center;">Day 5</th>
                            <th style="text-align: center;">Day 6</th>
                            <th style="text-align: center;">Day 7</th>
                            <th style="text-align: center;">Day 8</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">'.reset($day5).'</td>
                            <td style="text-align: center;">'.reset($day6).'</td>
                            <td style="text-align: center;">'.reset($day7).'</td>
                            <td style="text-align: center;">'.reset($day8).'</td>
                        </tr>
                    </tbody>
                </table>
        </div>';

        $o .= '<h4 style="margin-left: 20px;">History</h4>
                <div class="table-responsive" style="width: 620px; margin-left: 12px;">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th style="text-align: center;">Date</th>
                            <th style="text-align: center;">Epoche</th>
                            <th style="text-align: center;">Sols</th>
                            <th style="text-align: center;">&Oslash;</th>
                            <th style="text-align: center;">Day<br />1<br />Wed</th>
                            <th style="text-align: center;">Day<br />2<br />Thu</th>
                            <th style="text-align: center;">Day<br />3<br />Fri</th>
                            <th style="text-align: center;">Day<br />4<br />Sat</th>
                            <th style="text-align: center;">Day<br />5<br />Sun</th>
                            <th style="text-align: center;">Day<br />6<br />Mon</th>
                            <th style="text-align: center;">Day<br />7<br />Tue</th>
                            <th style="text-align: center;">Day<br />8<br />Wed</th>
                        </tr>
                    </thead>
                    <tbody>';

        ksort($stat["oldSolutions"]);
        foreach($stat["oldSolutions"] AS $epoch => $hData) {
            $day1 = array_slice($stat["epochHistory"][$epoch], 0, 1);
            $day2 = array_slice($stat["epochHistory"][$epoch], 1, 1);
            $day3 = array_slice($stat["epochHistory"][$epoch], 2, 1);
            $day4 = array_slice($stat["epochHistory"][$epoch], 3, 1);
            $day5 = array_slice($stat["epochHistory"][$epoch], 4, 1);
            $day6 = array_slice($stat["epochHistory"][$epoch], 5, 1);
            $day7 = array_slice($stat["epochHistory"][$epoch], 6, 1);
            $day8 = array_slice($stat["epochHistory"][$epoch], 7, 1);

            $o .= '    <tr>
                            <td style="text-align: center;">'.$hData["date"].'</td>
                            <td style="text-align: center;">'.$epoch.'</td>
                            <td style="text-align: right;">'.$hData["solutions"].'</td>
                            <td style="text-align: right;">'.number_format($hData["solutions"] / count($stat["epochHistory"][ $stat["epoch"] ]), 2, ",", "").'</td>
                            <td style="text-align: center;">'.reset($day1).'</td>
                            <td style="text-align: center;">'.reset($day2).'</td>
                            <td style="text-align: center;">'.reset($day3).'</td>
                            <td style="text-align: center;">'.reset($day4).'</td>
                            <td style="text-align: center;">'.reset($day5).'</td>
                            <td style="text-align: center;">'.reset($day6).'</td>
                            <td style="text-align: center;">'.reset($day7).'</td>
                            <td style="text-align: center;">'.reset($day8).'</td>
                        </tr>';
        }

        $o .= '         </tbody>
                </table>
            </div>';


        $o .= '<h4 style="margin-left: 20px;">Device-History</h4>';
        foreach($stat["epochHistoryDevices"] AS $epoch => $devices) {
            $o .= ' <div class="table-responsive" style="width: 360px; margin-left: 12px;">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>EP '.$epoch.'</th>
                                <th>Name</th>
                                <th style="text-align: center;">Sols</th>
                            </tr>
                        </thead>
                        <tbody>';

            arsort($devices);
            foreach($devices AS $device => $sols) {
                $o .= '    <tr>
                            <td style="text-align: center;"></td>
                            <td style="text-align: left;">'.$device.'</td>
                            <td style="text-align: center;">'.$sols.'</td>
                        </tr>';
            }
        }

        $o .= '         </tbody>
                </table>
            </div>';
    }

    echo $o;
}

?>

</body>
</html>
