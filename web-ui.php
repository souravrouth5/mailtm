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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail.TM OTP Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
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
        .mode-btn:hover { border-color: #667eea; }
        .mode-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .form-group { margin-bottom: 20px; }
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
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .result-box {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            display: none;
        }
        .result-box.show { display: block; animation: slideIn 0.3s ease; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .credential-row:last-child { border-bottom: none; }
        .credential-label { color: #666; font-size: 13px; }
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
        .copy-btn:hover { background: #5a6fd6; }
        .otp-display {
            margin-top: 20px;
            padding: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 16px;
            text-align: center;
            display: none;
        }
        .otp-display.show { display: block; animation: pulse 0.5s ease; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .otp-label { color: rgba(255,255,255,0.8); font-size: 14px; margin-bottom: 10px; }
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
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            color: rgba(255,255,255,0.9);
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
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
        }
        .hidden { display: none !important; }
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
    </div>

    <script>
        let currentMode = 'generate';
        let monitorInterval = null;
        let seenOtps = new Set();
        let credentials = { email: '', password: '' };
        
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=generate'
            })
            .then(r => r.json())
            .then(data => {
                setLoading('genBtn', false);
                if (data.success) {
                    credentials = { email: data.email, password: data.password };
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
            })
            .then(r => r.json())
            .then(data => {
                setLoading('loginBtn', false);
                if (data.success) {
                    credentials = { email: data.email, password: data.password };
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                new Notification('OTP Received!', { body: `Code: ${otp}` });
            }
        }
        
        function copy(type) {
            const text = type === 'email' ? credentials.email : credentials.password;
            navigator.clipboard.writeText(text).then(() => {
                event.target.textContent = 'Copied!';
                setTimeout(() => event.target.textContent = 'Copy', 1500);
            });
        }
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
</body>
</html>
