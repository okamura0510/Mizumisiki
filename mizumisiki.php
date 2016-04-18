<?php
// 定数
define('ACCESS_TOKEN', '***');
define('APP_URL',      'https://***/');
define('IMG_URL',      APP_URL.'img/');

$title         = '念の系統診断';
$image_url     = IMG_URL.'nen.jpg';
$start_message = 'よく来たな。念の系統を調べよう。';
$start_choice  = 'スタート';
$end_message   = 'あなたは【%s】です！！';
$end_choise    = 'もう1度調べる';
$questions = array(
    1 => array( '例えば誰か1人の命と引き換えに世界を救えるとしたら？', 
                '命を差し出す',
                '誰かを犠牲に',
                '待ってるだけ'
         ),
    2 => array( '旅行するならどこへ行きたい？', 
                '京都',
                'ハワイ',
                '北海道'
         ),
    3 => array( 'お前は？', 
                '無力',
                '希望',
                'トリコ'
         ),
);
$keitou = array('強化系' => 0, '放出系' => 0, '変化系' => 0, '操作系' => 0, '具現化系' => 0);
$keitou_special = '特質系';

// パラメータ取得
$json = file_get_contents('php://input');
$obj = json_decode($json);
$messaging = $obj->entry{0}->messaging{0};
$id = $messaging->sender->id;

// ユーザメッセージかpayloadがあるか？
$has_message = isset($messaging->message);
$has_payload = isset($messaging->postback->payload);
if(!$has_message && !$has_payload) {
    exit;
}

// 念能力者か判定
$nen = (!$has_payload) ? $messaging->message->text : '';
if(!$has_payload && $nen != '纏' && $nen != '絶' && $nen != '練' && $nen != '発') {
    exit;
}

// 問題番号取得
$payload = '';
$question_no = 0;
if($has_payload) {
    $payload = $messaging->postback->payload;
    $param = explode('_', $payload);
    $question_no = $param[0];
}

// 診断
$is_start = ($question_no == 0);
$is_end = ($question_no == 4);
if($is_start || $is_end) {
    // スタート or 結果
    $message = ($is_start) ? $start_message : $end_message;
    if($is_end) {
        // 結果の場合は系統判定
        // ポイント計算
        $answers = explode('/', $payload);
        for($i = 0; $i < 3; $i++) {
            $param = explode('_', $answers[2 - $i]); // 答えは逆順に保存されている
            $no = $param[1];
            if($i == 0) {
                if($no == 1)      { $keitou['放出系']++; }
                else if($no == 2) { $keitou['変化系']++; }
                else if($no == 3) { $keitou['強化系']++; }
            } else if($i == 1) {
                if($no == 1)      { $keitou['具現化系']++; }
                else if($no == 2) { $keitou['放出系']++; }
                else if($no == 3) { $keitou['操作系']++; }
            } else if($i == 2) {
                if($no == 1)      { $keitou['強化系']++; }
                else if($no == 2) { $keitou['変化系']++; }
                else if($no == 3) { $keitou['操作系']++; }
            } 
        }

        // 最大ポイント算出
        $max_point = 0;
        $max_name = '';
        foreach ($keitou as $name => $point) {
            if($point > $max_point) {
                $max_point = $point;
                $max_name = $name;
            }
        }
        if($max_point <= 1 && $keitou['強化系'] == 1 && $keitou['操作系'] == 1 && $keitou['具現化系'] == 1) {
            // 強化・操作・具現をバランスよく身に着けている場合は特質系(カリスマ性有り)
            $max_name = $keitou_special;
        }

        // 系統確定
        $message = sprintf($message, $max_name);
    }
    $choice = ($is_start) ? $start_choice : $end_choise;
    $payload = ($is_start) ? '1_0' : '0_0';
    $post = <<< EOM
    {
        "recipient":{
            "id":"{$id}"
        },
        "message":{
            "attachment":{
                "type":"template",
                "payload":{
                    "template_type":"generic",
                    "elements":[
                        {
                            "title":"{$title}",
                            "image_url":"{$image_url}",
                            "subtitle":"{$message}",
                            "buttons":[
                                {
                                    "type":"postback",
                                    "title":"{$choice}",
                                    "payload":"{$payload}"
                                }
                            ]
                        },
                    ]
                }
            }
        }
    }
EOM;
    api_send_request(ACCESS_TOKEN, $post);

} else {
    // 診断中
    $next_question_no = $question_no + 1;
    $question = $questions[$question_no];
    $post = <<< EOM
    {
        "recipient":{
            "id":"{$id}"
        },
        "message":{
            "attachment":{
                "type":"template",
                "payload":{
                    "template_type":"generic",
                    "elements":[
                        {
                            "title":"{$title}",
                            "image_url":"{$image_url}",
                            "subtitle":"{$question[0]}",
                            "buttons":[
                                {
                                    "type":"postback",
                                    "title":"{$question[1]}",
                                    "payload":"{$next_question_no}_1/{$payload}"
                                },
                                {
                                    "type":"postback",
                                    "title":"{$question[2]}",
                                    "payload":"{$next_question_no}_2/{$payload}"
                                },
                                {
                                    "type":"postback",
                                    "title":"{$question[3]}",
                                    "payload":"{$next_question_no}_3/{$payload}"
                                }
                            ]
                        },
                    ]
                }
            }
        }
    }
EOM;
    api_send_request(ACCESS_TOKEN, $post);
}

function api_send_request($access_token, $post) {
    $url = "https://graph.facebook.com/v2.6/me/messages?access_token={$access_token}";
    $headers = array(
            "Content-Type: application/json"
    );

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($curl);
}