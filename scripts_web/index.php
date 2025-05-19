<?php
// Start session at the very beginning
session_start();

// Function to check if user is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Function to load verification history
function loadVerificationHistory() {
    $jsonFile = 'verification_history.json';
    if (file_exists($jsonFile)) {
        $jsonData = file_get_contents($jsonFile);
        return json_decode($jsonData, true) ?: [];
    }
    return [];
}

// Function to save verification history
function saveVerificationHistory($history) {
    $jsonFile = 'verification_history.json';
    file_put_contents($jsonFile, json_encode($history, JSON_PRETTY_PRINT));
}

// Function to load user information
function loadUserInfo($username) {
    $jsonFile = 'users.json';
    if (file_exists($jsonFile)) {
        $jsonData = file_get_contents($jsonFile);
        $users = json_decode($jsonData, true) ?: [];
        return isset($users[$username]) ? $users[$username] : null;
    }
    return null;
}

// Handle API calls
if (isset($_POST['action'])) {
    $response = ['status' => '', 'message' => '', 'success' => false];

    // Handle login request
    if ($_POST['action'] === 'login') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            
            // Get verification history for initial load
            $history = loadVerificationHistory();
            $history = array_reverse($history);
            
            $response['status'] = 'success';
            $response['message'] = 'Login successful';
            $response['success'] = true;
            $response['username'] = $username;
            $response['history'] = $history;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Invalid credentials';
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Check authentication for protected endpoints
    if (in_array($_POST['action'], ['export_csv', 'get_history'])) {
        if (!isAdminLoggedIn()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized', 'success' => false]);
            exit;
        }
    }

    // Handle other API actions
    switch ($_POST['action']) {
        case 'export_csv':
            $history = loadVerificationHistory();
            $csv = "Name,Date & Time\n";
            foreach ($history as $record) {
                $csv .= '"' . str_replace('"', '""', $record['name']) . '","' . $record['timestamp'] . "\"\n";
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="verification_history.csv"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $csv;
            exit;

        case 'get_history':
            $history = loadVerificationHistory();
            $history = array_reverse($history);
            $response['success'] = true;
            $response['history'] = $history;
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;

        case 'logout':
            // Clear all session data
            session_unset();
            session_destroy();
            $response['status'] = 'success';
            $response['message'] = 'Logged out successfully';
            $response['success'] = true;
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;

        case 'capture':
            $output = shell_exec('/var/www/html/myenv/bin/python3 /var/www/html/capture_fingerprint.py 2>&1');
            if (strpos($output, 'Fingerprint image saved') !== false) {
                $response['status'] = 'success';
                $response['message'] = 'Fingerprint captured successfully';
                $response['success'] = true;
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Failed to capture fingerprint';
            }
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;

        case 'predict':
            if (file_exists('/var/www/html/captured_fingerprint.png')) {
                $raw_prediction = shell_exec('/var/www/html/myenv/bin/python3 /var/www/html/predict_fingerprint.py /var/www/html/captured_fingerprint.png 2>&1');
                if (strpos($raw_prediction, 'Unknown (low confidence)') !== false) {
                    $response['message'] = "Fingerprint not recognized. Please try again.";
                    $response['status'] = 'error';
                }
                elseif (preg_match('/Predicted person: ([^(]+)/', $raw_prediction, $matches)) {
                    $predicted_name = trim($matches[1]);
                    if ($predicted_name !== "Unknown") {
                        // Load user information
                        $userInfo = loadUserInfo($predicted_name);
                        
                        $response['message'] = "Welcome, " . $predicted_name . "!";
                        $response['status'] = 'success';
                        $response['success'] = true;
                        $response['userInfo'] = $userInfo;

                        // Add verification record to history
                        $history = loadVerificationHistory();
                        $history[] = [
                            'name' => $predicted_name,
                            'timestamp' => date('Y-m-d H:i:s'),
                            'status' => 'success'
                        ];
                        saveVerificationHistory($history);
                    }
                }
                else {
                    $response['message'] = "Unable to process fingerprint. Please try again.";
                    $response['status'] = 'error';
                }
            } else {
                $response['message'] = "No fingerprint image available. Please capture a fingerprint first.";
                $response['status'] = 'error';
            }
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
    }
}

// Handle admin login
if (isset($_POST['admin_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // For now, using simple credentials - we'll make this more secure later
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// If not an API call, display the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Recognition System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
        }
        .btn-capture, .btn-predict {
            padding: 12px 25px;
            font-size: 1.1em;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-capture {
            background-color: #3498db;
            color: white;
        }
        .btn-predict {
            background-color: #2ecc71;
            color: white;
        }
        .btn-capture:hover, .btn-predict:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .output-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }
        .prediction-result {
            font-weight: 600;
            font-size: 1.2em;
            color: #2c3e50;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 8px;
            text-align: center;
        }
        .image-container {
            text-align: center;
            margin-top: 30px;
            display: none;
        }
        .image-container img {
            max-width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-success { background-color: #2ecc71; }
        .status-error { background-color: #e74c3c; }
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .loading-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .main-content {
            display: flex;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .fingerprint-section {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .state-indicator {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .state-step {
            display: flex;
            align-items: center;
            position: relative;
            padding: 0 30px;
        }

        .state-step:not(:last-child):after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 2px;
            height: 30px;
            background: #dee2e6;
        }

        .state-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .state-text {
            color: #6c757d;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .state-step.active .state-number {
            background: #4a90e2;
            color: white;
        }

        .state-step.active .state-text {
            color: #4a90e2;
            font-weight: 500;
        }

        .state-step.completed .state-number {
            background: #2ecc71;
            color: white;
        }

        .state-step.completed .state-text {
            color: #2ecc71;
        }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
        }

        .btn-capture, .btn-predict {
            padding: 12px 25px;
            font-size: 1.1em;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 200px;
            justify-content: center;
        }

        .btn-capture {
            background-color: #3498db;
            color: white;
        }

        .btn-predict {
            background-color: #2ecc71;
            color: white;
        }

        .btn-capture:hover:not(:disabled), 
        .btn-predict:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-capture:disabled,
        .btn-predict:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-capture.processing,
        .btn-predict.processing {
            position: relative;
            pointer-events: none;
        }

        .btn-capture.processing:after,
        .btn-predict.processing:after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid white;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: button-loading 0.8s linear infinite;
        }

        @keyframes button-loading {
            from {
                transform: rotate(0turn);
            }
            to {
                transform: rotate(1turn);
            }
        }

        .admin-section {
            width: 350px;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-height: 80vh;
            overflow-y: auto;
        }

        .admin-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .admin-form .form-group {
            margin-bottom: 15px;
        }

        .admin-form label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 500;
        }

        .admin-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }

        .admin-form button {
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .admin-form button:hover {
            background: #34495e;
        }

        .admin-dashboard {
            text-align: center;
        }

        .admin-welcome {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .logout-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #e74c3c;
            color: white !important;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            width: auto;
            min-width: 120px;
        }

        .logout-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            color: white !important;
            text-decoration: none;
        }

        .logout-btn i {
            margin-right: 8px;
        }

        /* Verification History Styles */
        .verification-history {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .verification-history h4 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            font-size: 1.2em;
            font-weight: 600;
        }

        .table-responsive {
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            background: white;
        }

        .table th {
            background: #2c3e50;
            color: white;
            font-weight: 500;
            border: none;
        }

        .table td {
            vertical-align: middle;
            color: #2c3e50;
        }

        .table tbody tr:hover {
            background-color: #f5f6f7;
        }

        .export-buttons {
            margin: 15px 0;
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        }

        .export-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            min-width: 140px;
            justify-content: center;
            background-color: #4a90e2;
            color: white !important;
        }

        .export-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .export-btn i {
            font-size: 16px;
        }

        .user-info-card {
            display: none;
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .user-info-card .user-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .user-info-card .user-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4a90e2;
        }

        .user-info-card .user-details {
            flex: 1;
        }

        .user-info-card h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .user-info-card .position {
            color: #4a90e2;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .user-info-card .department {
            color: #7f8c8d;
        }

        .user-info-card .user-contact {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .user-info-card .user-contact p {
            margin: 5px 0;
            color: #34495e;
        }

        .user-info-card .user-contact i {
            width: 20px;
            color: #4a90e2;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="loading-content">
            <div class="spinner"></div>
            <p id="loading-text">Processing...</p>
        </div>
    </div>

    <div class="main-content">
        <!-- Fingerprint Section -->
        <div class="fingerprint-section">
            <div class="header">
                <h1>Fingerprint Recognition System</h1>
                <p>Place your finger on the scanner to begin</p>
            </div>

            <div class="state-indicator">
                <div class="state-step" id="captureStep">
                    <div class="state-number">1</div>
                    <div class="state-text">Capture Fingerprint</div>
                </div>
                <div class="state-step" id="verifyStep">
                    <div class="state-number">2</div>
                    <div class="state-text">Verify Identity</div>
                </div>
                <div class="state-step" id="resultStep">
                    <div class="state-number">3</div>
                    <div class="state-text">View Result</div>
                </div>
            </div>

            <div class="button-group">
                <button id="captureBtn" class="btn-capture">
                    <i class="fas fa-fingerprint"></i>
                    Capture Fingerprint
                </button>
                <button id="predictBtn" class="btn-predict" disabled>
                    <i class="fas fa-search"></i>
                    Verify Identity
                </button>
            </div>

            <div id="output-section" class="output-section">
                <h3>
                    <span id="status-indicator" class="status-indicator"></span>
                    <span id="status-title">Status</span>
                </h3>
                <div id="output-content" class="prediction-result"></div>
            </div>

            <div id="user-info" class="user-info-card">
                <div class="user-header">
                    <img id="user-picture" class="user-picture" src="" alt="User Picture">
                    <div class="user-details">
                        <h3 id="user-name"></h3>
                        <p id="user-position" class="position"></p>
                        <p id="user-department" class="department"></p>
                    </div>
                </div>
                <div class="user-contact">
                    <p><i class="fas fa-envelope"></i> <span id="user-email"></span></p>
                </div>
            </div>

            <div id="image-container" class="image-container">
                <h3>Captured Fingerprint</h3>
                <img id="fingerprint-image" alt="Captured Fingerprint">
            </div>
        </div>

        <!-- Admin Section -->
        <div class="admin-section">
            <div class="admin-header">
                <h2>Admin Panel</h2>
            </div>

            <?php if (!isset($_SESSION['admin_logged_in'])): ?>
            <form class="admin-form" id="loginForm" method="post" onsubmit="return false;">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div id="loginError" class="alert alert-danger" style="display: none; margin-bottom: 15px;"></div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            <?php else: ?>
            <div class="admin-dashboard">
                <div class="admin-welcome">
                    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</h3>
                    <p>You are logged in as administrator</p>
                </div>
                
                <div class="verification-history">
                    <h4>Verification History</h4>
                    <div class="export-buttons">
                        <button type="button" onclick="exportHistory('csv')" class="export-btn">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <?php
                                $history = loadVerificationHistory();
                                $history = array_reverse($history); // Show newest first
                                foreach ($history as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['timestamp']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <button id="logoutBtn" class="btn btn-danger logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const loading = document.querySelector('.loading');
        const loadingText = document.getElementById('loading-text');
        const outputSection = document.getElementById('output-section');
        const outputContent = document.getElementById('output-content');
        const statusIndicator = document.getElementById('status-indicator');
        const statusTitle = document.getElementById('status-title');
        const imageContainer = document.getElementById('image-container');
        const fingerprintImage = document.getElementById('fingerprint-image');
        const captureBtn = document.getElementById('captureBtn');
        const predictBtn = document.getElementById('predictBtn');
        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');
        const logoutBtn = document.getElementById('logoutBtn');

        function showLoading(message) {
            loadingText.textContent = message;
            loading.style.display = 'flex';
        }

        function hideLoading() {
            loading.style.display = 'none';
        }

        // Function to show login form
        function showLoginForm() {
            const adminSection = document.querySelector('.admin-section');
            adminSection.innerHTML = `
                <div class="admin-header">
                    <h2>Admin Panel</h2>
                </div>
                <form class="admin-form" id="loginForm" method="post" onsubmit="return false;">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div id="loginError" class="alert alert-danger" style="display: none; margin-bottom: 15px;"></div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            `;

            // Reattach event listener to the new form
            const newLoginForm = document.getElementById('loginForm');
            if (newLoginForm) {
                newLoginForm.addEventListener('submit', handleLogin);
            }
        }

        // Function to handle login
        async function handleLogin(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = e.target;
            const username = form.querySelector('#username').value;
            const password = form.querySelector('#password').value;
            const loginError = document.getElementById('loginError');
            
            showLoading('Logging in...');
            loginError.style.display = 'none';

            try {
                const formData = new URLSearchParams();
                formData.append('action', 'login');
                formData.append('username', username);
                formData.append('password', password);

                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString()
                });

                const data = await response.json();
                
                if (data.success) {
                    showDashboard(data.username, data.history);
                } else {
                    loginError.textContent = data.message;
                    loginError.style.display = 'block';
                }
            } catch (error) {
                loginError.textContent = 'An error occurred. Please try again.';
                loginError.style.display = 'block';
            } finally {
                hideLoading();
            }
        }

        // Function to handle logout
        async function handleLogout() {
            showLoading('Logging out...');
            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=logout'
                });

                const data = await response.json();
                if (data.success) {
                    showLoginForm();
                    window.location.reload(); // Reload page to reset all states
                }
            } catch (error) {
                console.error('Logout failed:', error);
            } finally {
                hideLoading();
            }
        }

        // Initialize login form if it exists
        if (loginForm) {
            loginForm.addEventListener('submit', handleLogin);
        }

        // Initialize logout button if it exists
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }

        // Function to handle exports
        async function exportHistory(type) {
            showLoading('Exporting CSV file...');
            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=export_csv'
                });

                if (!response.ok) throw new Error('Export failed');

                // Create blob from response
                const blob = await response.blob();
                
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'verification_history.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } catch (error) {
                console.error('Export failed:', error);
                alert('Failed to export file. Please try again.');
            } finally {
                hideLoading();
            }
        }

        function updateStatus(success, message, title = 'Status') {
            outputSection.style.display = 'block';
            outputContent.textContent = message;
            statusTitle.textContent = title;
            statusIndicator.className = 'status-indicator ' + (success ? 'status-success' : 'status-error');
        }

        function updateImage() {
            imageContainer.style.display = 'block';
            fingerprintImage.src = 'captured_fingerprint.png?' + Date.now();
        }

        // Function to update history table
        function updateHistoryTable(history) {
            const tbody = document.getElementById('historyTableBody');
            if (!tbody) return; // Exit if not on admin dashboard

            tbody.innerHTML = history.map(record => `
                <tr>
                    <td>${record.name}</td>
                    <td>${record.timestamp}</td>
                </tr>
            `).join('');
        }

        // Function to switch to dashboard view
        function showDashboard(username, history) {
            const adminSection = document.querySelector('.admin-section');
            adminSection.innerHTML = `
                <div class="admin-dashboard">
                    <div class="admin-welcome">
                        <h3>Welcome, ${username}!</h3>
                        <p>You are logged in as administrator</p>
                    </div>
                    
                    <div class="verification-history">
                        <h4>Verification History</h4>
                        <div class="export-buttons">
                            <button type="button" onclick="exportHistory('csv')" class="export-btn">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Date & Time</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <button id="logoutBtn" class="btn btn-danger logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            `;
            updateHistoryTable(history);
            
            // Add logout event listener to the new button
            document.getElementById('logoutBtn').addEventListener('click', handleLogout);
        }

        // State management functions
        function updateSteps(currentStep) {
            const steps = ['captureStep', 'verifyStep', 'resultStep'];
            const currentIndex = steps.indexOf(currentStep);
            
            steps.forEach((step, index) => {
                const stepElement = document.getElementById(step);
                if (index < currentIndex) {
                    stepElement.className = 'state-step completed';
                } else if (index === currentIndex) {
                    stepElement.className = 'state-step active';
                } else {
                    stepElement.className = 'state-step';
                }
            });
        }

        function resetStates() {
            updateSteps('captureStep');
            predictBtn.disabled = true;
            hideUserInfo();
            outputSection.style.display = 'none';
            imageContainer.style.display = 'none';
        }

        async function captureFingerprint() {
            try {
                // Reset all previous results first
                hideUserInfo();
                outputSection.style.display = 'none';
                imageContainer.style.display = 'none';
                predictBtn.disabled = true;
                
                // Start capture process
                captureBtn.disabled = true;
                captureBtn.classList.add('processing');
                updateSteps('captureStep');
                showLoading('Capturing fingerprint...');

                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=capture'
                });

                const data = await response.json();
                updateStatus(data.success, data.message, 'Capture Status');

                if (data.success) {
                    updateImage();
                    predictBtn.disabled = false;
                    updateSteps('verifyStep');
                }
            } catch (error) {
                updateStatus(false, 'Error: ' + error.message);
            } finally {
                hideLoading();
                captureBtn.disabled = false;
                captureBtn.classList.remove('processing');
            }
        }

        async function predictFingerprint() {
            try {
                predictBtn.disabled = true;
                predictBtn.classList.add('processing');
                updateSteps('verifyStep');
                showLoading('Analyzing fingerprint...');

                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=predict'
                });

                const data = await response.json();
                updateStatus(data.success, data.message, 'Verification Result');
                
                if (data.success && data.userInfo) {
                    displayUserInfo(data.userInfo);
                    updateSteps('resultStep');
                    await updateHistory();
                } else {
                    hideUserInfo();
                    // Return to capture state if verification shows unknown
                    updateSteps('captureStep');
                    predictBtn.disabled = true;
                }
            } catch (error) {
                updateStatus(false, 'Error: ' + error.message);
                hideUserInfo();
                updateSteps('captureStep');
                predictBtn.disabled = true;
            } finally {
                hideLoading();
                predictBtn.disabled = false;
                predictBtn.classList.remove('processing');
            }
        }

        // Initialize the state
        resetStates();

        captureBtn.addEventListener('click', captureFingerprint);
        predictBtn.addEventListener('click', predictFingerprint);

        function displayUserInfo(userInfo) {
            const userInfoCard = document.getElementById('user-info');
            const userPicture = document.getElementById('user-picture');
            const userName = document.getElementById('user-name');
            const userPosition = document.getElementById('user-position');
            const userDepartment = document.getElementById('user-department');
            const userEmail = document.getElementById('user-email');

            userPicture.src = userInfo.picture;
            userName.textContent = userInfo.name;
            userPosition.textContent = userInfo.position;
            userDepartment.textContent = userInfo.department;
            userEmail.textContent = userInfo.email;

            userInfoCard.style.display = 'block';
        }

        function hideUserInfo() {
            const userInfoCard = document.getElementById('user-info');
            if (userInfoCard) {
                userInfoCard.style.display = 'none';
            }
        }

        // Function to update history
        async function updateHistory() {
            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_history'
                });

                const data = await response.json();
                if (data.success) {
                    updateHistoryTable(data.history);
                }
            } catch (error) {
                console.error('Failed to update history:', error);
            }
        }
    </script>
</body>
</html>