<?php
require_once 'MailTMClient.php';

$client = new MailTMClient();
$action = $_POST['action'] ?? '';
$result = ['success' => false, 'message' => '', 'data' => []];

if ($action === 'generate') {
    try {
        $creds = $client->generateRandomCredentials();
        $account = $client->createAccount($creds['email'], $creds['password']);
        if ($account) {
            $token = $client->getToken($creds['email'], $creds['password']);
            if ($token) {
                $result = [
                    'success' => true,
                    'email' => $creds['email'],
                    'password' => $creds['password']
                ];
            } else {
                $result['message'] = 'Account created but authentication failed';
            }
        } else {
            $result['message'] = 'Failed to create account';
        }
    } catch (Exception $e) {
        $result['message'] = $e->getMessage();
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $token = $client->getToken($email, $password);
    if (!$token) {
        $account = $client->createAccount($email, $password);
        if ($account) {
            $token = $client->getToken($email, $password);
        }
    }

    if ($token) {
        $result = ['success' => true, 'email' => $email, 'password' => $password];
    } else {
        $result['message'] = 'Authentication failed';
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if ($action === 'check_otp') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $client->getToken($email, $password);
    $messages = $client->getMessages();

    $otp = null;
    $latestMsg = null;

    if ($messages && count($messages) > 0) {
        $latestMsg = $messages[0];
        $fullMessage = $client->getMessageContent($latestMsg['id']);
        $text = ($fullMessage['text'] ?? '') . ' ' . ($fullMessage['subject'] ?? '') . ' ' . ($latestMsg['intro'] ?? '');
        $otp = $client->extractOTP($text);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'otp' => $otp,
        'hasNewMessage' => !!$latestMsg,
        'from' => $latestMsg['from']['address'] ?? null,
        'subject' => $latestMsg['subject'] ?? null,
        'time' => $latestMsg['createdAt'] ?? null
    ]);
    exit;
}

if ($action === 'get_messages') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $client->getToken($email, $password);
    $messages = $client->getMessages();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'messages' => $messages ?? []
    ]);
    exit;
}

if ($action === 'get_full_message') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $messageId = $_POST['message_id'] ?? '';

    $client->getToken($email, $password);
    $fullMessage = $client->getMessageContent($messageId);

    // Extract body
    $body = '';
    if (isset($fullMessage['text']) && is_string($fullMessage['text'])) {
        $body = $fullMessage['text'];
    } elseif (isset($fullMessage['html']) && is_string($fullMessage['html'])) {
        $body = strip_tags($fullMessage['html']);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => !!$fullMessage,
        'message' => $fullMessage,
        'body' => $body
    ]);
    exit;
}

if ($action === 'delete_message') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $messageId = $_POST['message_id'] ?? '';

    try {
        $client->getToken($email, $password);
        $success = $client->deleteMessage($messageId);
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail.TM OTP Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .mode-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }

        .mode-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-size: 14px;
        }

        .mode-btn:hover {
            border-color: #667eea;
        }

        .mode-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .result-box {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            display: none;
        }

        .result-box.show {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .credential-row:last-child {
            border-bottom: none;
        }

        .credential-label {
            color: #666;
            font-size: 13px;
        }

        .credential-value {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .copy-btn {
            padding: 6px 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .copy-btn:hover {
            background: #5a6fd6;
        }

        .otp-display {
            margin-top: 20px;
            padding: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 16px;
            text-align: center;
            display: none;
        }

        .otp-display.show {
            display: block;
            animation: pulse 0.5s ease;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.02);
            }
        }

        .otp-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .otp-code {
            color: white;
            font-size: 42px;
            font-weight: 700;
            font-family: 'SF Mono', Monaco, monospace;
            letter-spacing: 8px;
        }

        .email-info {
            margin-top: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
        }

        .status-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 10px;
            font-size: 13px;
            color: #666;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #28a745;
            animation: blink 1.5s infinite;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }
        }

        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
        }

        .hidden {
            display: none !important;
        }

        /* Inbox Styles */
        .inbox-container {
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            border-radius: 12px;
            background: #f8f9fa;
        }

        .message-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .message-item:hover {
            background: #e9ecef;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-unread {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #667eea;
            flex-shrink: 0;
        }

        .message-read {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #28a745;
            flex-shrink: 0;
        }

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-from {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-subject {
            color: #666;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 3px;
        }

        .message-date {
            color: #999;
            font-size: 12px;
            flex-shrink: 0;
        }

        .message-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .msg-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .msg-btn-view {
            background: #667eea;
            color: white;
        }

        .msg-btn-delete {
            background: #e74c3c;
            color: white;
        }

        .message-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .message-modal.show {
            display: flex;
        }

        .message-modal-content {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            padding: 25px;
        }

        .message-modal-header {
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .message-modal-subject {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .message-modal-meta {
            color: #666;
            font-size: 13px;
            margin-top: 8px;
        }

        .message-modal-body {
            line-height: 1.6;
            color: #444;
            white-space: pre-wrap;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
        }

        .modal-close {
            float: right;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .inbox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .inbox-title {
            font-weight: 600;
            color: #333;
        }

        .refresh-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
        }

        .no-messages {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>📧 Mail.TM OTP Tool</h1>
        <p class="subtitle">Generate temporary emails & extract OTPs instantly</p>

        <div class="mode-selector">
            <button class="mode-btn active" onclick="setMode('generate')">
                🎲 Random
            </button>
            <button class="mode-btn" onclick="setMode('manual')">
                ✏️ Manual
            </button>
        </div>

        <div id="generate-form">
            <button class="btn btn-primary" onclick="generateEmail()" id="genBtn">
                Generate Email & Start Monitor
            </button>
        </div>

        <div id="manual-form" class="hidden">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="email" placeholder="your@email.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" placeholder="Enter password">
            </div>
            <button class="btn btn-primary" onclick="loginManual()" id="loginBtn">
                Login & Start Monitor
            </button>
        </div>

        <div id="error" class="error-message"></div>

        <div id="result" class="result-box">
            <div class="credential-row">
                <span class="credential-label">Email</span>
                <span class="credential-value">
                    <span id="resEmail"></span>
                    <button class="copy-btn" onclick="copy('email')">Copy</button>
                </span>
            </div>
            <div class="credential-row">
                <span class="credential-label">Password</span>
                <span class="credential-value">
                    <span id="resPass"></span>
                    <button class="copy-btn" onclick="copy('pass')">Copy</button>
                </span>
            </div>
        </div>

        <div id="otpDisplay" class="otp-display">
            <div class="otp-label">🔐 OTP CODE DETECTED</div>
            <div class="otp-code" id="otpCode">------</div>
            <div class="email-info">
                <div>From: <span id="otpFrom"></span></div>
                <div>Subject: <span id="otpSubject"></span></div>
            </div>
        </div>

        <div id="statusBar" class="status-bar hidden">
            <div class="status-dot"></div>
            <span id="statusText">Waiting for emails...</span>
        </div>

        <div id="inbox" class="hidden">
            <div class="inbox-header">
                <span class="inbox-title">📬 Inbox (Auto-refreshing)</span>
            </div>
            <div id="inboxList" class="inbox-container"></div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="message-modal" onclick="closeModal(event)">
        <div class="message-modal-content" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="hideModal()">&times;</button>
            <div class="message-modal-header">
                <div class="message-modal-subject" id="modalSubject"></div>
                <div class="message-modal-meta">
                    <div>From: <span id="modalFrom"></span></div>
                    <div>To: <span id="modalTo"></span></div>
                    <div>Date: <span id="modalDate"></span></div>
                </div>
            </div>
            <div class="message-modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
        let currentMode = 'generate';
        let monitorInterval = null;
        let inboxInterval = null;
        let seenOtps = new Set();
        let credentials = {
            email: '',
            password: ''
        };

        function setMode(mode) {
            currentMode = mode;
            document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            document.getElementById('generate-form').classList.toggle('hidden', mode !== 'generate');
            document.getElementById('manual-form').classList.toggle('hidden', mode !== 'manual');
            document.getElementById('error').textContent = '';
        }

        function setLoading(btnId, loading) {
            const btn = document.getElementById(btnId);
            btn.disabled = loading;
            btn.innerHTML = loading ? '<div class="spinner"></div> Processing...' :
                (btnId === 'genBtn' ? 'Generate Email & Start Monitor' : 'Login & Start Monitor');
        }

        function showError(msg) {
            document.getElementById('error').textContent = msg;
        }

        function generateEmail() {
            setLoading('genBtn', true);
            showError('');

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=generate'
                })
                .then(r => r.json())
                .then(data => {
                    setLoading('genBtn', false);
                    if (data.success) {
                        credentials = {
                            email: data.email,
                            password: data.password
                        };
                        showResult(data.email, data.password);
                        startMonitor();
                    } else {
                        showError(data.message || 'Failed to generate email');
                    }
                })
                .catch(err => {
                    setLoading('genBtn', false);
                    showError('Network error: ' + err.message);
                });
        }

        function loginManual() {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!email || !password) {
                showError('Please enter both email and password');
                return;
            }

            setLoading('loginBtn', true);
            showError('');

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
                })
                .then(r => r.json())
                .then(data => {
                    setLoading('loginBtn', false);
                    if (data.success) {
                        credentials = {
                            email: data.email,
                            password: data.password
                        };
                        showResult(data.email, data.password);
                        startMonitor();
                    } else {
                        showError(data.message || 'Login failed');
                    }
                })
                .catch(err => {
                    setLoading('loginBtn', false);
                    showError('Network error: ' + err.message);
                });
        }

        function showResult(email, password) {
            document.getElementById('resEmail').textContent = email;
            document.getElementById('resPass').textContent = password;
            document.getElementById('result').classList.add('show');
        }

        function startMonitor() {
            document.getElementById('statusBar').classList.remove('hidden');
            document.getElementById('statusText').textContent = 'Checking inbox every 3 seconds...';

            if (monitorInterval) clearInterval(monitorInterval);

            let checkCount = 0;
            monitorInterval = setInterval(() => {
                checkCount++;
                checkForOTP();
                document.getElementById('statusText').textContent =
                    `Checking inbox... (${checkCount} checks)`;
            }, 3000);
        }

        function checkForOTP() {
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=check_otp&email=${encodeURIComponent(credentials.email)}&password=${encodeURIComponent(credentials.password)}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.otp && !seenOtps.has(data.otp)) {
                        seenOtps.add(data.otp);
                        showOTP(data.otp, data.from, data.subject);
                    }
                });
        }

        function showOTP(otp, from, subject) {
            document.getElementById('otpCode').textContent = otp;
            document.getElementById('otpFrom').textContent = from || 'Unknown';
            document.getElementById('otpSubject').textContent = subject || 'No subject';
            document.getElementById('otpDisplay').classList.add('show');
            document.getElementById('statusText').textContent = '✓ OTP received!';

            // Play notification sound if available
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('OTP Received!', {
                    body: `Code: ${otp}`
                });
            }
        }

        function copy(type) {
            const text = type === 'email' ? credentials.email : credentials.password;
            navigator.clipboard.writeText(text).then(() => {
                event.target.textContent = 'Copied!';
                setTimeout(() => event.target.textContent = 'Copy', 1500);
            });
        }

        // Inbox Functions
        function showInbox() {
            document.getElementById('inbox').classList.remove('hidden');
            loadInbox();
            // Auto-refresh inbox every 5 seconds
            if (inboxInterval) clearInterval(inboxInterval);
            inboxInterval = setInterval(() => {
                if (!document.getElementById('inbox').classList.contains('hidden')) {
                    loadInbox();
                }
            }, 5000);
        }

        function loadInbox() {
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=get_messages&email=${encodeURIComponent(credentials.email)}&password=${encodeURIComponent(credentials.password)}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderInbox(data.messages);
                    }
                });
        }

        function renderInbox(messages) {
            const container = document.getElementById('inboxList');
            if (!messages || messages.length === 0) {
                container.innerHTML = '<div class="no-messages">📭 No messages yet</div>';
                return;
            }

            container.innerHTML = messages.map(msg => {
                const from = msg.from.name ? `${msg.from.name} <${msg.from.address}>` : msg.from.address;
                const date = new Date(msg.createdAt).toLocaleString();
                const statusClass = msg.seen ? 'message-read' : 'message-unread';
                return `
                    <div class="message-item">
                        <div class="${statusClass}"></div>
                        <div class="message-content">
                            <div class="message-from">${escapeHtml(from)}</div>
                            <div class="message-subject">${escapeHtml(msg.subject || '(No Subject)')}</div>
                        </div>
                        <div class="message-date">${date}</div>
                        <div class="message-actions">
                            <button class="msg-btn msg-btn-view" onclick="viewMessage('${msg.id}')">View</button>
                            <button class="msg-btn msg-btn-delete" onclick="deleteMessage('${msg.id}')">Delete</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function viewMessage(messageId) {
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=get_full_message&email=${encodeURIComponent(credentials.email)}&password=${encodeURIComponent(credentials.password)}&message_id=${encodeURIComponent(messageId)}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const msg = data.message;
                        document.getElementById('modalSubject').textContent = msg.subject || '(No Subject)';
                        document.getElementById('modalFrom').textContent = msg.from.address;
                        document.getElementById('modalTo').textContent = msg.to[0]?.address || '';
                        document.getElementById('modalDate').textContent = new Date(msg.createdAt).toLocaleString();
                        document.getElementById('modalBody').textContent = data.body || '(No content)';
                        document.getElementById('messageModal').classList.add('show');
                    }
                });
        }

        function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message?')) return;

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=delete_message&email=${encodeURIComponent(credentials.email)}&password=${encodeURIComponent(credentials.password)}&message_id=${encodeURIComponent(messageId)}`
                })
                .then(r => r.text())
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned invalid response');
                    }
                })
                .then(data => {
                    if (data.success) {
                        loadInbox();
                    } else {
                        alert('Failed to delete message: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    alert('Error deleting message: ' + err.message);
                });
        }

        function hideModal() {
            document.getElementById('messageModal').classList.remove('show');
        }

        function closeModal(event) {
            if (event.target === document.getElementById('messageModal')) {
                hideModal();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Modify startMonitor to also show inbox
        const originalStartMonitor = startMonitor;
        startMonitor = function() {
            originalStartMonitor();
            showInbox();
        };

        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
</body>

</html>