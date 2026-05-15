# AGENT.md - Project Intelligence Guide

This document provides essential context and guidelines for AI agents working on the **avali.ai** project.

## 🚀 Project Overview
**avali.ai** is a Laravel-based platform designed for academic evaluations and exam management. It leverages AI (Gemini) to assist in processing or generating evaluation materials.

**Key Features:**
- **Exam Generation System**: Allows teachers to request AI to generate exams based on parameters (questions count, topics) and uploaded supporting materials (PDFs, docs).
- **Automated Grading System**: Processes student exam submissions (images/PDFs) against an answer key using Gemini Multimodal OCR to automatically grade and provide pedagogical feedback per question.

## 🛠 Tech Stack
- **PHP**: 8.3
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
1. **The Laravel Way**: Follow standard Laravel conventions and patterns. Use Artisan for generating boilerplate (`php artisan make:*`).
2. **KISS & DRY**: Keep the code simple and readable (Keep It Simple, Stupid). Avoid logic duplication by centralizing business logic in Services or Traits (Don't Repeat Yourself).
3. **PSR Standards**: Strictly follow PHP Standard Recommendations (PSR-1, PSR-4, PSR-12). Maintain consistent naming (StudlyCaps for classes, camelCase for methods/variables).
4. **Reactivity**: Prefer Livewire 4 for interactive features. Avoid custom JS unless necessary; use Alpine.js for light client-side logic.
5. **UI Consistency**: Always use **Flux UI** components (`<flux:*>`) for buttons, inputs, modals, and layouts to maintain a premium aesthetic.
6. **Service Layer**: Encapsulate complex business logic in Service classes within `app/Services`. Controllers must remain thin (Skinny Controllers).
7. **Testing**: Write Pest tests for all new features. Ensure `vendor/bin/pest` passes before finalizing changes.
8. **Code Style**: Run `vendor/bin/pint` to format PHP code according to project standards.

## 🤖 Agent Tools & Workflow
- **Laravel Boost**: This project is optimized for Laravel Boost MCP. Use tools like `database-schema`, `database-query`, and `search-docs` for better context.
- **Documentation**: Always use `search-docs` before implementing new features to get version-specific advice for Laravel 13 and Flux UI.
- **Tinker**: Use `php artisan tinker --execute '...'` for quick data verification.

## ⚡ Livewire Single Page Components
When creating Full Page Components (formerly known as Volt):
- Use the command `php artisan make:livewire pages::component-name`.
- This generates a file in `resources/views/pages/` using the `⚡` prefix (e.g., `⚡component-name.blade.php`).
- **NO VOLT FACADES**: Do not import or use `Livewire\Volt\Component` or `Livewire\Volt\Volt`. 
- **Component Definition**: The class must extend `Livewire\Component` natively. Example: `new class extends Component` and import `use Livewire\Component;`.
- **Routing**: In `routes/web.php`, route to these components using the native `Route::livewire()` method and the `pages::` namespace prefix. Do not include the `⚡`.
  - Example: `Route::livewire('/path', 'pages::folder.component-name');`

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

# Create Full Page Components
php artisan make:livewire <Component>
```

## ⚠️ Important Rules
- **No Placeholders**: Never use placeholder images or text. Use the `generate_image` tool if UI assets are needed.
- **Security**: Ensure all routes are protected by appropriate middleware (e.g., `auth`).
- **Aesthetics & UI Components**: Flux UI is strictly used for a "premium" feel. 
  - **NEVER** use standard HTML form elements like `<input>`, `<select>`, `<button>`, or manual error spans (`@error`). 
  - **ALWAYS** use their Flux equivalents (e.g., `<flux:input>`, `<flux:select>`, `<flux:button>`, `<flux:error>`, `<flux:card>`). Flux components automatically handle styling, dark mode, labels, and validation errors.
- **Sidebar Integration**: Whenever you are asked to create a new functionality, page, or module in the system, you **MUST** automatically add a link to it in the Sidebar (`resources/views/layouts/sidebar.blade.php`), organizing it properly within groups if necessary.
