# Ori - AI-Powered Agent Operations Platform

Ori is an advanced AI-powered agent operations platform built with Laravel and Livewire. It provides a chat-room style interface where users can interact with an AI agent powered by Groq's API. The agent can perform tasks, answer questions, and engage in multi-step reasoning processes.

## Features

- Real-time chat interface with AI agent
- Multi-step reasoning process:
  1. Initial response
  2. Verified response
  3. Web search integration
  4. Validated reasoning
  5. Final response
- Chat history management
- Mobile-responsive design using Tailwind CSS and DaisyUI
- User authentication and authorization

## Technical Stack

- Laravel 10.x
- Livewire 3.x
- PHP 8.3+
- MySQL 8.0+
- Tailwind CSS 3.x
- DaisyUI
- Groq API

## Prerequisites

- PHP 8.3 or higher
- Composer
- Node.js and npm
- MySQL (MariaDB)

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/m4yc3x/agent.git
   cd agent
   ```

2. Install PHP dependencies:
   ```
   composer install
   ```

3. Install and compile frontend dependencies:
   ```
   npm install
   npm run dev
   ```

4. Create a copy of the `.env.example` file and rename it to `.env`:
   ```
   cp .env.example .env
   ```

5. Generate an application key:
   ```
   php artisan key:generate
   ```

6. Configure your database settings in the `.env` file:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=agentops
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

7. Add your Groq API key to the `.env` file:
   ```
   GROQ_KEY=your_groq_api_key
   ```

8. Run database migrations:
   ```
   php artisan migrate
   ```

9. Start the development server:
   ```
   php artisan serve
   ```

10. Visit `http://localhost:8000` in your browser to access the application.

## Usage

1. Register a new account or log in to an existing one.
2. On the dashboard, you'll see the chat interface.
3. Start a new chat or select an existing one from the sidebar.
4. Type your message or question in the input field and press Enter or click the Send button.
5. The AI agent will process your input through multiple steps and provide a detailed response.
6. You can view the different stages of the AI's reasoning process by expanding the accordions in the response.

## Customization

- Modify the `app/Livewire/Room.php` file to adjust the AI agent's behavior or add new features.
- Update the `resources/views/livewire/room.blade.php` file to change the chat interface layout.
- Customize the styling by editing `resources/css/app.css` and using Tailwind CSS classes.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
