# Expense Splitter

A group expense tracking app built with Laravel, similar to Splitwise. Members log shared expenses, and the app calculates who owes whom and suggests the smallest possible number of payments to settle up.

## Features

* Groups with multiple members, created and managed by any registered user
* Expenses that split evenly across chosen participants, with remainder cents distributed correctly so totals always match
* A balance service that computes each member's net position and simplifies debts into the minimum number of transfers using a greedy algorithm
* Settlement recording so members can mark suggested payments as paid
* Full authorization through Laravel policies so only group members can view or act on a group
* AI features powered by Claude (optional, enabled by setting `ANTHROPIC_API_KEY`):
  * **Receipt scanning**: upload a photo or PDF of a receipt and the description, total and category are filled in
  * **Natural-language entry**: type "I paid 45 for pizza with Bob and Carol" and the form fills itself in
  * **Auto-categorisation**: expenses saved with the category left on "Auto" are categorised automatically
  * **Monthly insights**: per-group spending breakdown by category and payer, with an AI-written summary

  The AI only ever pre-fills the form; nothing is saved until you review it and click Add Expense.

## Tech Stack

* Laravel 11
* Blade with Laravel Breeze for authentication
* Tailwind CSS
* Pest for testing
* Anthropic PHP SDK (`anthropic-ai/sdk`) for the Claude-powered features
* SQLite for local development

## Setup

```
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

To turn on the AI features, add your Anthropic API key to `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-opus-5-5
```

Without a key the app works as before and the AI options are hidden.

If PHP or Composer are not on your system PATH, call them with their full path, for example `C:\xampp\php\php.exe artisan serve`.

## Demo Accounts

The seeder creates four members of a shared group called Roommates, along with a few sample expenses and one recorded settlement. Password for all accounts is `password`.

* alice@example.com
* bob@example.com
* carol@example.com
* dave@example.com

## Project Structure

* `app/Models` holds the Eloquent models: User, Group, Expense, ExpenseShare, Settlement
* `app/Services/BalanceService.php` holds the core business logic for computing balances and simplifying debts
* `app/Services/ExpenseAiService.php` holds every Claude call (receipts, text parsing, categories, insights)
* `app/Policies` holds the authorization rules for group access
* `app/Http/Controllers` holds the request handling logic, split by resource
* `resources/views` holds the Blade templates, grouped by feature area

## Testing

The balance and debt simplification logic is covered by Pest tests in `tests/Feature/BalanceServiceTest.php`. The AI features are covered in `tests/Feature/AiFeaturesTest.php` with the AI service mocked, so the suite needs no API key. Run the full suite with:

```
php artisan test
```
