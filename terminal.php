<?php
require_once 'MailTMClient.php';

class TerminalColors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const BOLD = "\033[1m";
}

function colored(string $text, string $color): string {
    return $color . $text . TerminalColors::RESET;
}

function showHeader(): void {
    system('clear 2>/dev/null || cls 2>/dev/null || echo ""');
    echo colored("╔══════════════════════════════════════════╗\n", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("║      ", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("📧  Mail.TM OTP Tool", TerminalColors::WHITE . TerminalColors::BOLD);
    echo colored("        ║\n", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("╚══════════════════════════════════════════╝\n", TerminalColors::CYAN . TerminalColors::BOLD);
    echo PHP_EOL;
}

function showMenu(): int {
    echo colored("[1] ", TerminalColors::YELLOW . TerminalColors::BOLD);
    echo colored("Generate Random Email/Password\n", TerminalColors::WHITE);
    echo colored("[2] ", TerminalColors::YELLOW . TerminalColors::BOLD);
    echo colored("Enter Email/Password Manually\n", TerminalColors::WHITE);
    echo colored("[3] ", TerminalColors::YELLOW . TerminalColors::BOLD);
    echo colored("Exit\n", TerminalColors::RED);
    echo PHP_EOL;
    
    while (true) {
        echo colored("Select option (1-3): ", TerminalColors::CYAN);
        $choice = trim(fgets(STDIN));
        if (in_array($choice, ['1', '2', '3'])) {
            return (int)$choice;
        }
        echo colored("Invalid choice. Try again.\n", TerminalColors::RED);
    }
}

function handleRandomEmail(MailTMClient $client): bool {
    echo colored("\n⏳ Generating random credentials...", TerminalColors::YELLOW);
    
    try {
        $creds = $client->generateRandomCredentials();
    } catch (Exception $e) {
        echo colored("\n✗ Error: " . $e->getMessage() . "\n", TerminalColors::RED);
        return false;
    }
    
    echo colored("\n✓ Done!\n", TerminalColors::GREEN);
    
    $account = $client->createAccount($creds['email'], $creds['password']);
    if (!$account) {
        echo colored("✗ Failed to create account\n", TerminalColors::RED);
        return false;
    }
    
    $token = $client->getToken($creds['email'], $creds['password']);
    if (!$token) {
        echo colored("✗ Failed to authenticate\n", TerminalColors::RED);
        return false;
    }
    
    displayCredentials($creds['email'], $creds['password']);
    return true;
}

function handleManualEmail(MailTMClient $client): bool {
    echo colored("\nEnter Email: ", TerminalColors::CYAN);
    $email = trim(fgets(STDIN));
    echo colored("Enter Password: ", TerminalColors::CYAN);
    $password = trim(fgets(STDIN));
    
    if (!$email || !$password) {
        echo colored("✗ Email and password required\n", TerminalColors::RED);
        return false;
    }
    
    $token = $client->getToken($email, $password);
    if (!$token) {
        echo colored("✗ Authentication failed. Trying to create account...\n", TerminalColors::YELLOW);
        $account = $client->createAccount($email, $password);
        if ($account) {
            $token = $client->getToken($email, $password);
        }
    }
    
    if (!$token) {
        echo colored("✗ Failed to authenticate\n", TerminalColors::RED);
        return false;
    }
    
    displayCredentials($email, $password);
    return true;
}

function displayCredentials(string $email, string $password): void {
    echo PHP_EOL;
    echo colored("══════════════════════════════════════════\n", TerminalColors::GREEN);
    echo colored("  ✓ Successfully Authenticated\n", TerminalColors::GREEN . TerminalColors::BOLD);
    echo colored("══════════════════════════════════════════\n", TerminalColors::GREEN);
    echo colored("  Email:    ", TerminalColors::YELLOW);
    echo colored($email . "\n", TerminalColors::WHITE);
    echo colored("  Password: ", TerminalColors::YELLOW);
    echo colored($password . "\n", TerminalColors::WHITE);
    echo colored("══════════════════════════════════════════\n", TerminalColors::GREEN);
    echo PHP_EOL;
}

function monitorOTP(MailTMClient $client): void {
    echo colored("🔄 Starting OTP Monitor...\n", TerminalColors::MAGENTA);
    echo colored("Checking inbox every 3 seconds...\n", TerminalColors::YELLOW);
    echo colored("Press Ctrl+C to stop\n\n", TerminalColors::RED);
    
    $monitor = new OTPMonitor($client, 3, function($msg) {
        $time = date('H:i:s');
        if (strpos($msg, 'OTP FOUND') !== false) {
            echo colored("[{$time}] ", TerminalColors::CYAN);
            echo colored("🎉 {$msg}\n", TerminalColors::GREEN . TerminalColors::BOLD);
        } else {
            echo colored("[{$time}] ", TerminalColors::CYAN);
            echo $msg . PHP_EOL;
        }
    });
    
    try {
        $monitor->start();
    } catch (Exception $e) {
        echo colored("\nMonitor stopped.\n", TerminalColors::YELLOW);
    }
}

// Main
showHeader();
$client = new MailTMClient();

while (true) {
    $choice = showMenu();
    
    switch ($choice) {
        case 1:
            if (handleRandomEmail($client)) {
                monitorOTP($client);
            }
            break;
            
        case 2:
            if (handleManualEmail($client)) {
                monitorOTP($client);
            }
            break;
            
        case 3:
            echo colored("\nGoodbye! 👋\n", TerminalColors::CYAN);
            exit(0);
    }
    
    echo PHP_EOL;
    echo colored("Press Enter to continue...", TerminalColors::YELLOW);
    fgets(STDIN);
    showHeader();
}
