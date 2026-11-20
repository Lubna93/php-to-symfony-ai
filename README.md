<div align="center">

# PHP2Symfony AI 🚀

**Your legacy PHP project. A clearer path to Symfony.**

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?style=flat-square&logo=symfony&logoColor=white)
![Symfony AI](https://img.shields.io/badge/Symfony-AI-FFAA91?style=flat-square)
![OpenAI](https://img.shields.io/badge/OpenAI-412991?style=flat-square)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=flat-square&logo=mariadb&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=flat-square&logo=docker&logoColor=white)
![PHP-Parser](https://img.shields.io/badge/PHP--Parser-777BB4?style=flat-square)
![Bootstrap](https://img.shields.io/badge/Bootstrap-7952B3?style=flat-square&logo=bootstrap&logoColor=white)

</div>

Old PHP application, years of code, and no idea where to start?

PHP2Symfony AI helps you explore your codebase, identify migration priorities, and turn the analysis into a practical Symfony migration plan.

<p align="center">
  <em>Built by a developer working with legacy PHP applications — for developers facing the same challenge. 😮‍💨</em>
</p>

## What can you do?

<p align="center">
  <img align="middle" src="https://img.shields.io/badge/1._Upload_your_project-F4A7B9?style=for-the-badge" alt="Upload your project">
  &nbsp; → &nbsp;
  <img align="middle" src="https://img.shields.io/badge/2._Explore_your_code-FFD1BA?style=for-the-badge" alt="Explore your code">
  &nbsp; → &nbsp;
  <img align="middle" src="https://img.shields.io/badge/3._Get_your_migration_plan-F7C8DA?style=for-the-badge" alt="Get your migration plan">
</p>

<p align="center">
  <img align="middle" src="https://img.shields.io/badge/4._Generate_Symfony_code-B8DED3?style=for-the-badge" alt="Generate Symfony code">
  &nbsp; → &nbsp;
  <img align="middle" src="https://img.shields.io/badge/5._Download_code_%26_report-F5D6A5?style=for-the-badge" alt="Download code and report">
</p>


- **Upload a project** — Start with a ZIP of your PHP application.
- **Explore the code** — Review files, classes, methods, dependencies, and legacy patterns.
- **Find your starting point** — See which files may need more attention.
- **Plan the migration** — Generate a structured roadmap with Symfony AI.
- **Explore code generation** — Generate Symfony code for supported Plain PHP projects.
- **Download the generated code** — Get the generated project as a ZIP. Currently available for Plain PHP projects; support for other frameworks is coming soon.
- **Take the plan with you** — Download a PDF migration report.

Upload. Explore. Plan. Take the next step. ✨

## Try it locally

You’ll need **Git**, **Docker with Docker Compose**, and an **OpenAI API key with available API credit**.

No GitHub OAuth setup needed to try it — use **Try Demo** to sign in locally.

### 1. Get the code

```bash
git clone https://github.com/Lubna93/php-to-symfony-ai.git
cd php-to-symfony-ai
```

### 2. Add your settings

Create a `.env.local` file in the project root:

```dotenv
OPENAI_API_KEY=your_openai_api_key
```

Database settings are already configured in Docker Compose.

Keep `.env.local` private. Never commit API keys or OAuth secrets.


### 3. Start the containers

```bash
docker compose up -d --build
```

### 4. Install and prepare

Once the database is ready, run:

```bash
docker compose exec app composer install --no-interaction
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Open the application

Visit **http://localhost:8090**, click **Try Demo**, and create your first migration project.

Demo login is available only in the `dev` environment, which is enabled in the included Docker Compose configuration.

Upload a ZIP, explore the analysis, and generate your migration plan. Nice — you have a starting point!


### Optional: GitHub sign-in

To use **Continue with GitHub**, create an OAuth App in [GitHub Developer Settings](https://github.com/settings/developers).

Set the Homepage URL to `http://localhost:8090` and the Authorization callback URL to `http://localhost:8090/connect/github/check`.

Add your credentials to `.env.local`:

```dotenv
OAUTH_GITHUB_CLIENT_ID=your_github_client_id
OAUTH_GITHUB_CLIENT_SECRET=your_github_client_secret
```

## Why Symfony AI?

A useful migration plan starts with understanding the application.

**PHP-Parser** provides static analysis of the PHP code. **Symfony AI** connects that analysis to an AI agent that generates migration recommendations.

Instead of starting with a blank chat box, you start with your project’s structure and analysis.

## Before you upload

Use a sample project for your first run. Need a sample? [Download the Plain PHP blog ZIP](https://github.com/Lubna93/native-php-blog-test/archive/refs/heads/main.zip) and upload it to get started.

Files identified as sensitive are excluded from AI code migration. As an extra precaution, remove secrets and confidential data from your ZIP before uploading.

AI features send the context prepared by the application to OpenAI for processing. Running the application locally does not mean AI processing stays on your machine.

Generated plans and code are starting points: review them, adapt them, and test them before use. A generated migration is not a verified migration.


## About

Built solo for the [Symfony AI & MCP Hackathon 2026](https://symfony.com/hackathon), using Symfony AI.


<p align="center">
  <br>
  <em>Made with ❤️, PHP, and a lot of coffee ☕</em>
</p>