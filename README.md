# 📧 Mail.TM OTP Tool

A modern PHP-based tool for generating temporary email addresses and automatically extracting OTP (One-Time Password) codes from incoming messages. Available as both an interactive terminal application and a sleek web interface.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green.svg)

## ✨ Features

- **🎲 Random Email Generation** - Instantly create temporary email addresses with secure random passwords
- **✏️ Manual Credentials** - Use your own existing Mail.TM email and password
- **🔐 Auto OTP Extraction** - Continuously monitors inbox and extracts 4-8 digit OTP codes automatically
- **🎨 Modern UI** - Clean web interface with real-time updates and notifications
- **🖥️ Terminal Support** - Fully-featured CLI with colored output for quick scripting
- **⚡ Efficient Polling** - Smart message tracking prevents duplicate OTP alerts
- **📋 Copy-to-Clipboard** - One-click copying of credentials in the web UI

## 🚀 Quick Start

### Requirements

- PHP 8.0 or higher
- cURL extension enabled

### Installation

```bash
git clone https://github.com/yourusername/mail-tm-otp-tool.git
cd mail-tm-otp-tool
```

No additional dependencies required!

## 📖 Usage

### Terminal Version

Run the interactive CLI:

```bash
php terminal.php
```

**Menu Options:**
1. **Generate Random** - Creates a new random email address
2. **Enter Manually** - Use existing Mail.TM credentials

Once logged in, the tool automatically monitors your inbox every 3 seconds and displays any detected OTP codes in real-time.

### Web UI Version

Start the built-in PHP server:

```bash
php -S localhost:8000 web-ui.php
```

Then open `http://localhost:8000` in your browser.

**Features:**
- Toggle between Random and Manual modes
- Visual OTP display with sender info
- Live status indicator
- Desktop notifications (when permitted)

## 🏗️ Architecture

```
mail-tm-scraper/
├── MailTMClient.php    # Shared API client & OTP monitor
├── terminal.php        # CLI application
├── web-ui.php         # Web interface
└── README.md          # This file
```

The codebase follows DRY principles:
- `MailTMClient` handles all Mail.TM API interactions
- `OTPMonitor` provides reusable continuous polling logic
- Both UIs share the same core functionality

## 🔧 API Endpoints Used

| Endpoint | Purpose |
|----------|---------|
| `GET /domains` | Fetch available email domains |
| `POST /accounts` | Create new email account |
| `POST /token` | Authenticate and get JWT token |
| `GET /messages` | Fetch inbox messages |
| `GET /messages/{id}` | Get full message content |

## 💡 Use Cases

- **Testing** - Quick signup testing without using personal email
- **Privacy** - Avoid spam in your primary inbox
- **Automation** - Script-based OTP extraction for testing workflows
- **Development** - Email verification testing during app development

## ⚠️ Disclaimer

This tool is for educational and testing purposes. Temporary emails from Mail.TM are public by design—do not use them for sensitive communications.

## 📄 License

MIT License - feel free to use and modify as needed.

---

Made with 💜 for developers who value efficiency
