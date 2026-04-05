<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_requests', 'country_code')) {
                $table->string('country_code', 2)
                    ->nullable()
                    ->after('company_name')
                    ->index();
            }
        });

        DB::table('finance_requests')
            ->select(['id', 'country_code', 'intake_details_json'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $details = $this->decodeIntakeDetails($row->intake_details_json);
                    $resolvedCode = $this->normalizeCountryCode((string) ($row->country_code ?? ''));

                    if (! $resolvedCode) {
                        $resolvedCode = $this->normalizeCountryCode((string) ($details['country_code'] ?? $details['country'] ?? ''));
                    }

                    $payload = [];

                    if ($resolvedCode && $resolvedCode !== $row->country_code) {
                        $payload['country_code'] = $resolvedCode;
                    }

                    if (array_key_exists('country', $details) || array_key_exists('country_code', $details)) {
                        unset($details['country'], $details['country_code']);
                        $payload['intake_details_json'] = json_encode($details, JSON_UNESCAPED_UNICODE);
                    }

                    if ($payload !== []) {
                        DB::table('finance_requests')
                            ->where('id', $row->id)
                            ->update($payload);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('finance_requests', function (Blueprint $table) {
            if (Schema::hasColumn('finance_requests', 'country_code')) {
                $table->dropIndex(['country_code']);
                $table->dropColumn('country_code');
            }
        });
    }

    private function decodeIntakeDetails(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeCountryCode(string $value): ?string
    {
        $normalized = strtoupper(trim($value));
        if (preg_match('/^[A-Z]{2}$/', $normalized) === 1) {
            return $normalized;
        }

        $legacyCountryMap = [
            'saudi arabia' => 'SA',
            'oman' => 'OM',
            'united arab emirates' => 'AE',
            'kuwait' => 'KW',
            'qatar' => 'QA',
            'bahrain' => 'BH',
            'egypt' => 'EG',
            'jordan' => 'JO',
            'lebanon' => 'LB',
            'united states' => 'US',
            'united kingdom' => 'GB',
            'india' => 'IN',
            'pakistan' => 'PK',
            'bangladesh' => 'BD',
            'turkey' => 'TR',
            'germany' => 'DE',
            'france' => 'FR',
            'china' => 'CN',
            'malaysia' => 'MY',
            'singapore' => 'SG',
        ];

        $legacyKey = strtolower(preg_replace('/\s+/', ' ', trim($value)) ?: '');

        return $legacyCountryMap[$legacyKey] ?? null;
    }
};
