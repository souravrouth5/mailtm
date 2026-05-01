<?php
require_once 'MailTMClient.php';

class TerminalColors
{
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

function colored(string $text, string $color): string
{
    return $color . $text . TerminalColors::RESET;
}

function clearScreen(): void
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else {
        system('clear');
    }
}

function showHeader(): void
{
    clearScreen();
    echo colored("╔══════════════════════════════════════════╗\n", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("║      ", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("📧  Mail.TM OTP Tool", TerminalColors::WHITE . TerminalColors::BOLD);
    echo colored("        ║\n", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("╚══════════════════════════════════════════╝\n", TerminalColors::CYAN . TerminalColors::BOLD);
    echo PHP_EOL;
}

function showMenu(): int
{
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

function handleRandomEmail(MailTMClient $client): bool
{
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

function handleManualEmail(MailTMClient $client): bool
{
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

function displayCredentials(string $email, string $password): void
{
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

function showInboxMenu(): int
{
    echo PHP_EOL;
    echo colored("[1] ", TerminalColors::YELLOW . TerminalColors::BOLD);
    echo colored("View Inbox & Manage Messages\n", TerminalColors::WHITE);
    echo colored("[2] ", TerminalColors::YELLOW . TerminalColors::BOLD);
    echo colored("Start OTP Monitor\n", TerminalColors::WHITE);
    echo colored("[3] ", TerminalColors::YELLOW . TerminalColors::BOLD);
    echo colored("Back to Main Menu\n", TerminalColors::RED);
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

function viewInbox(MailTMClient $client): void
{
    while (true) {
        clearScreen();
        echo colored("╔══════════════════════════════════════════╗\n", TerminalColors::BLUE . TerminalColors::BOLD);
        echo colored("║           📬  INBOX MESSAGES             ║\n", TerminalColors::BLUE . TerminalColors::BOLD);
        echo colored("╚══════════════════════════════════════════╝\n", TerminalColors::BLUE . TerminalColors::BOLD);
        echo PHP_EOL;

        $messages = $client->getMessages();
        if (!$messages || count($messages) === 0) {
            echo colored("📭 No messages found.\n", TerminalColors::YELLOW);
            echo colored("\nPress Enter to return...", TerminalColors::CYAN);
            fgets(STDIN);
            return;
        }

        echo colored("Found " . count($messages) . " message(s):\n\n", TerminalColors::GREEN);

        foreach ($messages as $index => $msg) {
            $num = $index + 1;
            $from = $msg['from']['name'] ? $msg['from']['name'] . ' <' . $msg['from']['address'] . '>' : $msg['from']['address'];
            $subject = $msg['subject'] ?: '(No Subject)';
            $date = date('Y-m-d H:i', strtotime($msg['createdAt']));
            $seen = $msg['seen'] ? '✓' : '●';

            echo colored("[{$num}] ", TerminalColors::YELLOW . TerminalColors::BOLD);
            echo colored("{$seen} ", $msg['seen'] ? TerminalColors::GREEN : TerminalColors::RED);
            echo colored("From: ", TerminalColors::CYAN);
            echo colored("{$from}\n", TerminalColors::WHITE);
            echo colored("     Subject: ", TerminalColors::CYAN);
            echo colored("{$subject}\n", TerminalColors::WHITE);
            echo colored("     Date: ", TerminalColors::CYAN);
            echo colored("{$date}\n", TerminalColors::MAGENTA);
            echo PHP_EOL;
        }

        echo colored("[V#] ", TerminalColors::GREEN . TerminalColors::BOLD);
        echo colored("View message (e.g., V1)\n", TerminalColors::WHITE);
        echo colored("[D#] ", TerminalColors::RED . TerminalColors::BOLD);
        echo colored("Delete message (e.g., D2)\n", TerminalColors::WHITE);
        echo colored("[R] ", TerminalColors::YELLOW . TerminalColors::BOLD);
        echo colored("Refresh inbox\n", TerminalColors::WHITE);
        echo colored("[Q] ", TerminalColors::RED . TerminalColors::BOLD);
        echo colored("Return to menu\n", TerminalColors::WHITE);
        echo PHP_EOL;

        echo colored("Enter command: ", TerminalColors::CYAN);
        $input = strtoupper(trim(fgets(STDIN)));

        if ($input === 'Q') {
            return;
        }
        if ($input === 'R') {
            continue;
        }

        if (preg_match('/^V(\d+)$/i', $input, $matches)) {
            $idx = (int)$matches[1] - 1;
            if (isset($messages[$idx])) {
                viewFullMessage($client, $messages[$idx]);
            } else {
                echo colored("\n✗ Invalid message number\n", TerminalColors::RED);
                sleep(1);
            }
        } elseif (preg_match('/^D(\d+)$/i', $input, $matches)) {
            $idx = (int)$matches[1] - 1;
            if (isset($messages[$idx])) {
                deleteMessage($client, $messages[$idx]);
            } else {
                echo colored("\n✗ Invalid message number\n", TerminalColors::RED);
                sleep(1);
            }
        } elseif ($input !== '') {
            echo colored("\n✗ Invalid command\n", TerminalColors::RED);
            sleep(1);
        }
    }
}

function viewFullMessage(MailTMClient $client, array $msg): void
{
    clearScreen();
    echo colored("╔══════════════════════════════════════════╗\n", TerminalColors::BLUE . TerminalColors::BOLD);
    echo colored("║         📄  MESSAGE DETAILS              ║\n", TerminalColors::BLUE . TerminalColors::BOLD);
    echo colored("╚══════════════════════════════════════════╝\n", TerminalColors::BLUE . TerminalColors::BOLD);
    echo PHP_EOL;

    $fullMsg = $client->getMessageContent($msg['id']);

    $from = $msg['from']['name'] ? $msg['from']['name'] . ' <' . $msg['from']['address'] . '>' : $msg['from']['address'];
    $to = $msg['to'][0]['address'] ?? 'Unknown';
    $subject = $msg['subject'] ?: '(No Subject)';
    $date = date('Y-m-d H:i:s', strtotime($msg['createdAt']));

    echo colored("From:    ", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("{$from}\n", TerminalColors::WHITE);
    echo colored("To:      ", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("{$to}\n", TerminalColors::WHITE);
    echo colored("Subject: ", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("{$subject}\n", TerminalColors::WHITE);
    echo colored("Date:    ", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("{$date}\n", TerminalColors::MAGENTA);
    echo colored("ID:      ", TerminalColors::CYAN . TerminalColors::BOLD);
    echo colored("{$msg['id']}\n", TerminalColors::MAGENTA);
    echo PHP_EOL;
    echo colored("══════════════════════════════════════════\n", TerminalColors::YELLOW);
    echo colored("MESSAGE BODY:\n", TerminalColors::YELLOW . TerminalColors::BOLD);
    echo colored("══════════════════════════════════════════\n", TerminalColors::YELLOW);
    echo PHP_EOL;

    // Extract message body - handle various formats
    $body = '(No content)';
    if (isset($fullMsg['text']) && is_string($fullMsg['text']) && strlen($fullMsg['text']) > 0) {
        $body = $fullMsg['text'];
    } elseif (isset($fullMsg['html']) && is_string($fullMsg['html']) && strlen($fullMsg['html']) > 0) {
        $body = strip_tags($fullMsg['html']);
    } elseif (isset($msg['intro']) && is_string($msg['intro'])) {
        $body = $msg['intro'];
    }
    echo wordwrap($body, 70, PHP_EOL, true);
    echo PHP_EOL;

    // Check for OTP
    $otp = $client->extractOTP($body . ' ' . $subject);
    if ($otp) {
        echo PHP_EOL;
        echo colored("══════════════════════════════════════════\n", TerminalColors::GREEN);
        echo colored("🔐 OTP DETECTED: ", TerminalColors::GREEN . TerminalColors::BOLD);
        echo colored("{$otp}\n", TerminalColors::WHITE . TerminalColors::BOLD);
        echo colored("══════════════════════════════════════════\n", TerminalColors::GREEN);
    }

    echo PHP_EOL;
    echo colored("Press Enter to return...", TerminalColors::CYAN);
    fgets(STDIN);
}

function deleteMessage(MailTMClient $client, array $msg): void
{
    echo PHP_EOL;
    echo colored("⚠️  Are you sure you want to delete this message? (y/n): ", TerminalColors::YELLOW);
    $confirm = strtolower(trim(fgets(STDIN)));

    if ($confirm === 'y' || $confirm === 'yes') {
        $success = $client->deleteMessage($msg['id']);
        if ($success) {
            echo colored("\n✓ Message deleted successfully\n", TerminalColors::GREEN);
        } else {
            echo colored("\n✗ Failed to delete message\n", TerminalColors::RED);
        }
        sleep(1);
    } else {
        echo colored("\nCancelled\n", TerminalColors::YELLOW);
        sleep(1);
    }
}

function monitorOTP(MailTMClient $client): void
{
    echo colored("🔄 Starting OTP Monitor...\n", TerminalColors::MAGENTA);
    echo colored("Checking inbox every 3 seconds...\n", TerminalColors::YELLOW);
    echo colored("Press Ctrl+C to stop\n\n", TerminalColors::RED);

    $monitor = new OTPMonitor($client, 3, function ($msg) {
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

function handlePostLogin(MailTMClient $client): void
{
    while (true) {
        $choice = showInboxMenu();

        switch ($choice) {
            case 1:
                viewInbox($client);
                break;

            case 2:
                monitorOTP($client);
                break;

            case 3:
                return;
        }
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
                handlePostLogin($client);
            }
            break;

        case 2:
            if (handleManualEmail($client)) {
                handlePostLogin($client);
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
