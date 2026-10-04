<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use App\Exceptions\AiUnavailableException;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * All Claude-powered features live here: receipt scanning, natural-language entry,
 * categorisation and monthly insights. Every call asks for JSON that matches a schema,
 * so the rest of the app only ever deals with plain PHP arrays.
 */
class ExpenseAiService
{
    private ?Client $client = null;

    public function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Read a receipt photo or PDF and pull out what to pre-fill on the expense form.
     *
     * @return array{description: string, amount: float, category: string}
     */
    public function scanReceipt(UploadedFile $file): array
    {
        $data = base64_encode($file->get());
        $mime = $file->getMimeType();

        $source = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mime, 'data' => $data]];

        $result = $this->ask(
            content: [
                $source,
                ['type' => 'text', 'text' => 'This is a receipt for a shared expense. Extract a short description '
                    .'(merchant plus what was bought, e.g. "Tesco groceries"), the final total actually paid '
                    .'including tax and tip, and the best-fitting category. Set readable to false if this is not '
                    .'a receipt or the total cannot be read.'],
            ],
            schema: [
                'type' => 'object',
                'properties' => [
                    'readable' => ['type' => 'boolean'],
                    'description' => ['type' => 'string'],
                    'amount' => ['type' => 'number'],
                    'category' => ['type' => 'string', 'enum' => Expense::CATEGORIES],
                ],
                'required' => ['readable', 'description', 'amount', 'category'],
                'additionalProperties' => false,
            ],
            effort: 'low',
        );

        if (! $result['readable'] || $result['amount'] <= 0) {
            throw new AiUnavailableException("Couldn't read a total from that receipt. Try a clearer photo.");
        }

        return [
            'description' => $result['description'],
            'amount' => round($result['amount'], 2),
            'category' => $result['category'],
        ];
    }

    /**
     * Turn a sentence like "Alice paid 60 for dinner with Bob and Carol" into form fields.
     * Returned ids are filtered to real group members; anything Claude invents is dropped.
     *
     * @param  Collection<int, User>  $members
     * @return array{description: string, amount: ?float, paid_by: ?int, participant_ids: list<int>, category: string}
     */
    public function parseText(string $text, Collection $members, int $currentUserId): array
    {
        $roster = $members
            ->map(fn ($m) => "- id {$m->id}: {$m->name}".($m->id === $currentUserId ? ' (this is "me"/"I")' : ''))
            ->implode("\n");

        $result = $this->ask(
            content: [
                ['type' => 'text', 'text' => "Group members:\n{$roster}\n\n"
                    ."Expense entered by the user:\n<entry>\n{$text}\n</entry>\n\n"
                    .'Fill in the expense. Use member ids from the list only. amount is the total spent; use 0 if no '
                    .'amount is given. paid_by_id is who paid; use 0 if unclear. participant_ids are the people who '
                    .'share the cost (include the payer if they share it); return an empty list if it should be split '
                    .'with everyone. description is a short label such as "Dinner at Nando\'s".'],
            ],
            schema: [
                'type' => 'object',
                'properties' => [
                    'description' => ['type' => 'string'],
                    'amount' => ['type' => 'number'],
                    'paid_by_id' => ['type' => 'integer'],
                    'participant_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'category' => ['type' => 'string', 'enum' => Expense::CATEGORIES],
                ],
                'required' => ['description', 'amount', 'paid_by_id', 'participant_ids', 'category'],
                'additionalProperties' => false,
            ],
            effort: 'low',
        );

        $memberIds = $members->pluck('id');
        $participants = $memberIds->intersect($result['participant_ids'])->values()->all();

        return [
            'description' => $result['description'],
            'amount' => $result['amount'] > 0 ? round($result['amount'], 2) : null,
            'paid_by' => $memberIds->contains($result['paid_by_id']) ? $result['paid_by_id'] : null,
            'participant_ids' => $participants ?: $memberIds->all(),
            'category' => $result['category'],
        ];
    }

    public function categorise(string $description): string
    {
        $result = $this->ask(
            content: [['type' => 'text', 'text' => "Categorise this shared expense: \"{$description}\""]],
            schema: [
                'type' => 'object',
                'properties' => ['category' => ['type' => 'string', 'enum' => Expense::CATEGORIES]],
                'required' => ['category'],
                'additionalProperties' => false,
            ],
            effort: 'low',
        );

        return $result['category'];
    }

    /**
     * Write a short, friendly summary of a group's month from pre-computed figures.
     * The numbers are calculated in PHP; Claude only interprets them.
     *
     * @param  array<string, mixed>  $stats
     * @return array{headline: string, highlights: list<string>, tip: string}
     */
    public function insights(string $groupName, array $stats): array
    {
        return $this->ask(
            content: [
                ['type' => 'text', 'text' => "Spending data for the shared-expense group \"{$groupName}\" "
                    ."(amounts in dollars):\n".json_encode($stats, JSON_PRETTY_PRINT)."\n\n"
                    .'Write a brief monthly summary for the group members: a one-sentence headline, 2-4 highlights '
                    .'(notable categories, changes versus last month, who has been covering the most), and one '
                    .'practical tip. Only state figures that are in the data. Keep a friendly, neutral tone and do '
                    .'not single anyone out negatively.'],
            ],
            schema: [
                'type' => 'object',
                'properties' => [
                    'headline' => ['type' => 'string'],
                    'highlights' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'tip' => ['type' => 'string'],
                ],
                'required' => ['headline', 'highlights', 'tip'],
                'additionalProperties' => false,
            ],
            effort: 'medium',
        );
    }

    /**
     * Single Claude request with structured JSON output.
     *
     * @return array<string, mixed>
     */
    private function ask(array $content, array $schema, string $effort): array
    {
        if (! $this->enabled()) {
            throw new AiUnavailableException('AI features are not configured. Set ANTHROPIC_API_KEY in .env.');
        }

        try {
            $message = $this->client()->beta->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 16000,
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                // If the model declines a request, the API retries it on a fallback model.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AnthropicException $e) {
            report($e);

            throw new AiUnavailableException('The AI service is unavailable right now. Please try again.', previous: $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new AiUnavailableException('The AI declined to process that request.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $decoded = json_decode($block->text, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        throw new AiUnavailableException('The AI returned an unexpected response. Please try again.');
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: config('services.anthropic.key'));
    }
}
