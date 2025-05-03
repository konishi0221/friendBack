# Personal AI Assistant

A personal AI assistant web application with Vue.js frontend and PHP backend.

## Features

- Chat interface with GPT-4o or GPT-3.5 model selection
- Voice call capability
- File upload and processing
- Memory compression for long-term context
- PWA support for mobile installation

## Project Structure

```
friendBack/
├── api/                  # API endpoints
│   ├── chat/             # Chat-related functionality
│   ├── prompts/          # System prompts
│   ├── chat.php          # Chat API endpoint
│   ├── context.php       # Context API endpoint
│   ├── model.php         # Model selection API endpoint
│   ├── prompt.php        # Prompt API endpoint
│   └── upload.php        # File upload API endpoint
├── public/               # Public files served by PHP
│   ├── core/             # Core PHP functionality
│   │   ├── FirestoreDB.php    # Firestore database integration
│   │   ├── VectorEmbedding.php # Vector embedding for memory
│   │   └── config.php    # Configuration
│   └── .htaccess         # Apache configuration
├── src/                  # Source code
│   └── frontend/         # Vue.js frontend
│       ├── public/       # Static assets
│       └── src/          # Vue source code
├── .env                  # Environment variables
├── Dockerfile            # Docker configuration
├── docker-compose.yml    # Docker Compose configuration
└── package.json          # Project configuration
```

## Setup

1. Clone the repository:
```bash
git clone https://github.com/konishi0221/friendBack.git
cd friendBack
```

2. Create a `.env` file with your OpenAI API key and Google Cloud credentials:
```
OPENAI_API_KEY=your_openai_api_key
GOOGLE_APPLICATION_CREDENTIALS=/path/to/your/service-account-key.json
```

3. Install dependencies and build the frontend:
```bash
npm run setup
```

4. Start the development server:
```bash
npm run dev
```

5. Access the application at http://localhost:8000

## Development

- Frontend development: `npm run dev:frontend`
- Backend development: `npm run dev:backend`
- Build frontend: `npm run build:frontend`

## Deployment

You can deploy the application using Docker:

```bash
docker-compose up -d
```

Or deploy to a PHP hosting service by uploading the contents of the `public` directory.
