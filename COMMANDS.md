# Kesara Enterprises - Project Commands

Here is a list of commands you will need to start, develop, and manage the code for this project. You should run these commands in the terminal (Command Prompt, PowerShell, or VS Code Terminal) from the root of the project (`f:\Kesara-Enterprises`).

## 1. Starting the Project

You will typically need 3 separate terminal tabs to run all the local development tools at once.

### The PHP Server (Virtual Host)
You are using a virtual host for this project. There is no need to run a manual PHP command.
*You can view the website in your browser at `http://rasthaman.local/`*

### Tab 2: Start Tailwind CSS (Auto-compile)
To watch for CSS changes and automatically compile your styles as you edit them, run:
```bash
npm run watch
```
*Keep this running while you are editing HTML/PHP files.*

### Tab 3: Start MailDev (Email Testing)
To intercept and view emails locally (like contact form submissions or confirmations), run:
```bash
npx maildev
```
- **View Emails (Web Dashboard):** Open `http://localhost:1080` in your browser.
- **SMTP Server Port:** `1025` *(This is already configured correctly in your `src/Mailer.php`)*

---

## 2. Fixing and Installing Dependencies

If your code is broken because of missing packages (or if you just downloaded the code), run these commands:

### Install Node Modules (Tailwind & JavaScript dependencies)
```bash
npm install
```

### Install PHP Packages (PHPMailer & PHP dependencies)
```bash
composer install
```

### Update PHP Packages
To update your PHP packages to their latest versions according to `composer.json`:
```bash
composer update
```

---

## 3. Building for Production

When you are ready to deploy your website to a live server, you should build a final, optimized version of your CSS:

### Build final CSS
```bash
npm run build
```
