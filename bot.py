import os
import subprocess
import sys
import requests
import json
import time

# Kiểm tra và cài đặt thư viện requests nếu chưa có
try:
    import requests
except ImportError:
    subprocess.check_call([sys.executable, "-m", "pip", "install", "requests"])

# Token của bot
botToken = "7547885601:AAHOXhN_t3Ac50rjAO9Ne054Iw89ITnu7VA"
apiUrl = f"https://api.telegram.org/bot{botToken}/"
lastUpdateId = 0
users = {}

# Hàm gửi tin nhắn
def sendMessage(chatId, message):
    url = f"{apiUrl}sendMessage?chat_id={chatId}&text={requests.utils.quote(message)}"
    requests.get(url)

# Hàm lấy key ngẫu nhiên
def generateRandomKey():
    import random
    import string
    return 'EzGame' + ''.join(random.choices(string.ascii_letters + string.digits, k=10))

# Hàm rút gọn link với API mới
def getShortenedKey(key):
    link_goc = requests.utils.quote(f"https://tngming.online/bot/?key={key}")
    api_token = '278b052cfc5ec76e3f918c9dae6c09b6c2bbd543fa1abfce8708fcfaa2f17e66'
    api_url = f"https://yeumoney.com/QL_api.php?token={api_token}&url={link_goc}&format=json"
    result = requests.get(api_url).json()

    if result["status"] == 'success':
        return result["shortenedUrl"]
    return False

# Hàm kiểm tra trạng thái key của người dùng
def checkKeyStatus(userId):
    return users.get(userId, None)

# Hàm lưu trạng thái key của người dùng
def saveUserKeyStatus(userId, key, usedFor):
    if userId in users:
        users[userId]['usageCount'] += 1
    else:
        users[userId] = {'key': key, 'usedFor': usedFor, 'usageCount': 1}

# Hàm giới hạn số lần dùng key
def canUserGetKey(userId):
    return users.get(userId, {}).get('usageCount', 0) < 2

# Hàm lấy 1 dòng từ file và xóa dòng đó
def getAccountFromFile(filePath):
    with open(filePath, 'r') as file:
        lines = file.readlines()

    if lines:
        firstLine = lines[0].strip()
        with open(filePath, 'w') as file:
            file.writelines(lines[1:])
        return firstLine
    return False

# Hàm lấy toàn bộ nội dung từ file
def getFileContent(filePath):
    if os.path.exists(filePath):
        with open(filePath, 'r') as file:
            return file.read()
    return False

# Vòng lặp chính để lấy tin nhắn từ bot
def main():
    global lastUpdateId
    while True:
        response = requests.get(f"{apiUrl}getUpdates?offset={lastUpdateId}").json()

        if response["result"]:
            for update in response["result"]:
                if "message" in update:
                    chatId = update["message"]["chat"]["id"]
                    message = update["message"]["text"]
                    userId = update["message"]["from"]["id"]
                    username = update["message"]["from"].get("username", "Người dùng")
                    lastUpdateId = update["update_id"] + 1

                    if message == "/start":
                        welcomeMessage = f"Xin chào {username}! Nhập /help để xem các lệnh của bot."
                        sendMessage(chatId, welcomeMessage)

                    elif message == "/help":
                        helpMessage = """Danh sách lệnh:
                        /getkey - Lấy key vượt link
                        /key [mã] - Xác nhận key
                        /redfinger - Lấy acc Redfinger
                        /ugphone - Lấy acc UGPhone"""
                        sendMessage(chatId, helpMessage)

                    elif message == "/getkey":
                        if not canUserGetKey(userId):
                            sendMessage(chatId, "Bạn đã yêu cầu quá 2 lần hôm nay. Vui lòng quay lại vào ngày mai.")
                            continue

                        randomKey = generateRandomKey()
                        shortLink = getShortenedKey(randomKey)

                        if shortLink:
                            saveUserKeyStatus(userId, randomKey, 'pending')
                            sendMessage(chatId, f"Vui lòng vượt qua link này: {shortLink}")
                        else:
                            sendMessage(chatId, "Đã có lỗi xảy ra khi tạo link. Vui lòng thử lại sau.")

                    elif message.startswith("/key"):
                        userKey = message.replace("/key", "").strip()
                        status = checkKeyStatus(userId)

                        if status and status['key'] == userKey and status['usedFor'] == 'pending':
                            sendMessage(chatId, "🔄 Đang xử lý...")
                            users[userId]['usedFor'] = 'success'
                            sendMessage(chatId, "✅ Xác nhận key thành công! Bạn có thể dùng lệnh /redfinger hoặc /ugphone.")
                        else:
                            sendMessage(chatId, "Mã key không hợp lệ hoặc đã được sử dụng.")

                    elif message == "/redfinger":
                        status = checkKeyStatus(userId)
                        if status and status['usedFor'] == 'success':
                            account = getAccountFromFile('redfinger.txt')
                            if account:
                                sendMessage(chatId, f"Tài khoản Redfinger của bạn là: {account}")
                                del users[userId]
                            else:
                                sendMessage(chatId, "Không còn tài khoản Redfinger.")
                        else:
                            sendMessage(chatId, "Bạn cần xác nhận key trước khi sử dụng lệnh này.")

                    elif message == "/ugphone":
                        status = checkKeyStatus(userId)
                        if status and status['usedFor'] == 'success':
                            account = getAccountFromFile('ugphone.txt')
                            if account:
                                sendMessage(chatId, f"Tài khoản UGPhone của bạn là: {account}")
                                del users[userId]
                            else:
                                sendMessage(chatId, "Không còn tài khoản UGPhone.")
                        else:
                            sendMessage(chatId, "Bạn cần xác nhận key trước khi sử dụng lệnh này.")

        time.sleep(1)

if __name__ == "__main__":
    main()