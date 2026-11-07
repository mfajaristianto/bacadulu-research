<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application.

> BacaDulu note: this bootstrap is NOT a mandatory first step for every task. Laravel Boost is currently not installed (`composer.json`). Continue the user's request with existing capabilities. Installing PHP/Composer or Laravel Boost, or changing dependencies, requires the user's explicit request or approval. Project rules: `CLAUDE.md` and `.claude/rules/workflow-autonomy.md`.

## Prerequisites

Verify that PHP and Composer are available when needed:

```sh
php -v
composer -V
```

If either command is unavailable, report it to the user. Only with the user's approval, install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Only when the user explicitly requests or approves it, install Laravel Boost from the application root:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
