<?php
$botToken = "7547885601:AAHOXhN_t3Ac50rjAO9Ne054Iw89ITnu7VA";
$apiUrl = "https://api.telegram.org/bot$botToken/";
$lastUpdateId = 0;
$users = [];

// Hàm gửi tin nhắn
function sendMessage($chatId, $message) {
    global $apiUrl;
    file_get_contents($apiUrl . "sendMessage?chat_id=" . $chatId . "&text=" . urlencode($message));
}

// Hàm lấy key ngẫu nhiên
function generateRandomKey() {
    return 'EzGame' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
}

// Hàm rút gọn link với API mới
function getShortenedKey($key) {
    $link_goc = urlencode("https://tngming.online/bot/?key=$key");
    $api_token = '278b052cfc5ec76e3f918c9dae6c09b6c2bbd543fa1abfce8708fcfaa2f17e66';
    $api_url = "https://yeumoney.com/QL_api.php?token={$api_token}&url={$link_goc}&format=json";
    $result = @json_decode(file_get_contents($api_url), TRUE);
    if($result["status"] === 'success') {
        return $result["shortenedUrl"];
    } else {
        return false;
    }
}

// Hàm kiểm tra trạng thái key của người dùng
function checkKeyStatus($userId) {
    global $users;
    return isset($users[$userId]) ? $users[$userId] : null;
}

// Hàm lưu trạng thái key của người dùng
function saveUserKeyStatus($userId, $key, $usedFor) {
    global $users;
    $users[$userId] = [
        'key' => $key,
        'usedFor' => $usedFor,
        'usageCount' => isset($users[$userId]['usageCount']) ? $users[$userId]['usageCount'] + 1 : 1
    ];
}

// Hàm giới hạn số lần dùng key
function canUserGetKey($userId) {
    global $users;
    return isset($users[$userId]['usageCount']) && $users[$userId]['usageCount'] >= 2 ? false : true;
}

// Hàm lấy 1 dòng từ file và xóa dòng đó
function getAccountFromFile($filePath) {
    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if (count($lines) > 0) {
        $firstLine = $lines[0];
        $remainingLines = array_slice($lines, 1);
        file_put_contents($filePath, implode(PHP_EOL, $remainingLines));
        return $firstLine;
    }
    return false;
}

// Hàm lấy toàn bộ nội dung từ file
function getFileContent($filePath) {
    if (file_exists($filePath)) {
        return file_get_contents($filePath);
    }
    return false;
}

// Vòng lặp chính để lấy tin nhắn từ bot
while (true) {
    $response = file_get_contents($apiUrl . "getUpdates?offset=" . $lastUpdateId);
    $response = json_decode($response, TRUE);

    if (isset($response["result"]) && count($response["result"]) > 0) {
        foreach ($response["result"] as $update) {
            if (isset($update["message"])) {
                $chatId = $update["message"]["chat"]["id"];
                $message = $update["message"]["text"];
                $userId = $update["message"]["from"]["id"];
                $username = isset($update["message"]["from"]["username"]) ? $update["message"]["from"]["username"] : "Người dùng";
                $lastUpdateId = $update["update_id"] + 1;

                // Lệnh khởi đầu khi /start
                if ($message == "/start") {
                    $welcomeMessage = "Xin chào $username! Nhập /help để xem các lệnh của bot.";
                    sendMessage($chatId, $welcomeMessage);
                }

                // Hiển thị danh sách lệnh khi /help
                elseif ($message == "/help") {
                    $helpMessage = "Danh sách lệnh:\n/getkey - Lấy key vượt link\n/key [mã] - Xác nhận key\n/redfinger - Lấy acc Redfinger\n/ugphone - Lấy acc UGPhone\n/hdsd_ugphone - Hướng dẫn UGPhone\n/hdsd_redfinger - Hướng dẫn Redfinger\nShop admin: http://tngming.online\nZalo liên hệ: 0348747253";
                    sendMessage($chatId, $helpMessage);
                }

                // Tạo key và gửi link rút gọn khi /getkey
                elseif ($message == "/getkey") {
                    if (!canUserGetKey($userId)) {
                        sendMessage($chatId, "Bạn đã yêu cầu quá 2 lần hôm nay. Vui lòng quay lại vào ngày mai.");
                        continue;
                    }

                    $randomKey = generateRandomKey();
                    $shortLink = getShortenedKey($randomKey);

                    if ($shortLink) {
                        saveUserKeyStatus($userId, $randomKey, 'pending');
                        sendMessage($chatId, "Vui lòng vượt qua link này: $shortLink");
                        sendMessage($chatId, "Lưu ý: Mỗi key chỉ sử dụng được một lần cho một lệnh. Nếu phát hiện gian lận, tài khoản sẽ bị khóa!");
                    } else {
                        sendMessage($chatId, "Đã có lỗi xảy ra khi tạo link. Vui lòng thử lại sau.");
                    }
                }

                // Kiểm tra key khi người dùng nhập /key [mã]
                elseif (strpos($message, "/key") === 0) {
                    $userKey = trim(str_replace("/key", "", $message));
                    $status = checkKeyStatus($userId);

                    if ($status && $status['key'] == $userKey && $status['usedFor'] == 'pending') {
                        sendMessage($chatId, "🔄 Đang xử lý...");

                        // Chuyển trạng thái key thành đã sử dụng
                        $users[$userId]['usedFor'] = 'success';
                        sendMessage($chatId, "✅ Xác nhận key thành công! Bạn có thể dùng lệnh /redfinger hoặc /ugphone.");
                    } else {
                        sendMessage($chatId, "Mã key không hợp lệ hoặc đã được sử dụng.");
                    }
                }

                // Xử lý lệnh /redfinger
                elseif ($message == "/redfinger") {
                    $status = checkKeyStatus($userId);
                    if ($status && $status['usedFor'] == 'success') {
                        $account = getAccountFromFile('redfinger.txt');
                        if ($account) {
                            sendMessage($chatId, "Tài khoản Redfinger của bạn là: $account");
                            sendMessage($chatId, "Key $status[key] đã được sử dụng cho lệnh /redfinger. Key sẽ bị xóa khỏi server.");
                            unset($users[$userId]); // Xóa key khỏi server
                        } else {
                            sendMessage($chatId, "Không còn tài khoản Redfinger.");
                        }
                    } else {
                        sendMessage($chatId, "Bạn cần xác nhận key trước khi sử dụng lệnh này.");
                    }
                }

                // Xử lý lệnh /ugphone
                elseif ($message == "/ugphone") {
                    $status = checkKeyStatus($userId);
                    if ($status && $status['usedFor'] == 'success') {
                        $account = getAccountFromFile('ugphone.txt');
                        if ($account) {
                            sendMessage($chatId, "Tài khoản UGPhone của bạn là: $account");
                            sendMessage($chatId, "Key $status[key] đã được sử dụng cho lệnh /ugphone. Key sẽ bị xóa khỏi server.");
                            unset($users[$userId]); // Xóa key khỏi server
                        } else {
                            sendMessage($chatId, "Không còn tài khoản UGPhone.");
                        }
                    } else {
                        sendMessage($chatId, "Bạn cần xác nhận key trước khi sử dụng lệnh này.");
                    }
                }

                // Xử lý lệnh /hdsd_ugphone
                elseif ($message == "/hdsd_ugphone") {
                    $guideContent = getFileContent('hdsdugphone.txt');
                    if ($guideContent) {
                        sendMessage($chatId, $guideContent);
                    } else {
                        sendMessage($chatId, "Không có hướng dẫn UGPhone.");
                    }
                }

                // Xử lý lệnh /hdsd_redfinger
                elseif ($message == "/hdsd_redfinger") {
                    $guideContent = getFileContent('hdsdredfinger.txt');
                    if ($guideContent) {
                        sendMessage($chatId, $guideContent);
                    } else {
                        sendMessage($chatId, "Không có hướng dẫn Redfinger.");
                    }
                }

                // Nếu lệnh không hợp lệ
                // Nếu lệnh không hợp lệ
                
// Nếu lệnh không hợp lệ
                else {
                    sendMessage($chatId, "Lệnh không hợp lệ. Vui lòng nhập /help để xem danh sách các lệnh.");
                }
            }
        }
    }

 // Tạm ngưng một giây trước khi tiếp tục lấy tin nhắn mới
}