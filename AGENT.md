# AGENT.md - Project Intelligence Guide

This document provides essential context and guidelines for AI agents working on the **avali.ai** project.

## 🚀 Project Overview
**avali.ai** is a Laravel-based platform designed for academic evaluations and exam management. It leverages AI (Gemini) to assist in processing or generating evaluation materials.

## 🛠 Tech Stack
- **PHP**: 8.4
- **Framework**: Laravel 13 (v13.x)
- **Frontend**: Livewire 4, Flux UI v2 (Premium Component Library)
- **Styling**: Tailwind CSS (integrated via Flux UI)
- **Testing**: Pest 4, PHPUnit 12
- **Database**: SQLite (default for local development)
- **AI Integration**: Gemini PHP (`google-gemini-php/laravel`)

## 📂 Core Directory Structure
- `app/Models`: Eloquent models (`User`, `Exam`, etc.)
- `app/Livewire`: Livewire component classes (Reactivity)
- `resources/views/livewire`: Livewire blade templates
- `resources/views/components`: Shared Blade/Flux components
- `routes/web.php`: Web routes and middleware definitions
- `.docs/`: Reference documents (PDFs, templates)

## 💡 Architectural Principles
1. **The Laravel Way**: Follow standard Laravel conventions. Use Artisan for generating boilerplate (`php artisan make:*`).
2. **Reactivity**: Prefer Livewire 4 for interactive features. Avoid custom JS unless necessary; use Alpine.js for light client-side logic.
3. **UI Consistency**: Always use **Flux UI** components (`<flux:*>`) for buttons, inputs, modals, and layouts to maintain a premium aesthetic.
4. **Service Layer**: (If applicable) Encapsulate complex business logic in Service classes within `app/Services`.
5. **Testing**: Write Pest tests for all new features. Ensure `vendor/bin/pest` passes before finalizing changes.
6. **Code Style**: Run `vendor/bin/pint --format agent` to format PHP code according to project standards.

## 🤖 Agent Tools & Workflow
- **Laravel Boost**: This project is optimized for Laravel Boost MCP. Use tools like `database-schema`, `database-query`, and `search-docs` for better context.
- **Documentation**: Always use `search-docs` before implementing new features to get version-specific advice for Laravel 13 and Flux UI.
- **Tinker**: Use `php artisan tinker --execute '...'` for quick data verification.

## 📝 Key Commands
```bash
# Development
composer dev             # Start all dev services (Serve, Vite, Queue, Pail)
npm run build           # Build frontend assets

# Testing & Linting
php artisan test        # Run Pest tests
vendor/bin/pint         # Format code

# Discovery
php artisan route:list  # View all routes
php artisan list        # View all artisan commands
```

## ⚠️ Important Rules
- **No Placeholders**: Never use placeholder images or text. Use the `generate_image` tool if UI assets are needed.
- **Security**: Ensure all routes are protected by appropriate middleware (e.g., `auth`).
- **Aesthetics**: Flux UI is used for a "premium" feel. Ensure components are composed correctly and look modern.
