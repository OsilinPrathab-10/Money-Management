<?php

namespace App\Support;

class CollectedDocuments
{
    public static function url(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        return url('storage/' . $path);
    }

    public static function item(?string $path, string $type, string $title): ?array
    {
        $url = self::url($path);
        if (! $url) {
            return null;
        }

        return [
            'type' => $type,
            'title' => $title,
            'file_name' => basename((string) $path),
            'file_path' => $path,
            'file_url' => $url,
            'url' => $url,
        ];
    }

    /**
     * @param  array<string, string>  $named  type => title, looked up on $record
     * @return list<array<string, mixed>>
     */
    public static function named(object $record, array $named): array
    {
        $docs = [];
        foreach ($named as $type => $title) {
            $item = self::item($record->{$type} ?? null, $type, $title);
            if ($item) {
                $docs[] = $item;
            }
        }

        return $docs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function additional(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [$raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        $docs = [];
        foreach (array_values($raw) as $index => $entry) {
            if (is_string($entry)) {
                $item = self::item($entry, 'additional_document', 'Additional Document ' . ($index + 1));
            } elseif (is_array($entry)) {
                $path = $entry['file_path'] ?? $entry['path'] ?? $entry['file_url'] ?? $entry['url'] ?? null;
                $item = self::item(
                    is_string($path) ? $path : null,
                    (string) ($entry['type'] ?? 'additional_document'),
                    (string) ($entry['title'] ?? $entry['name'] ?? ('Additional Document ' . ($index + 1)))
                );
            } else {
                continue;
            }

            if ($item) {
                $docs[] = $item;
            }
        }

        return $docs;
    }

    /**
     * Loan disbursement: collateral / other / additional files collected at payout.
     *
     * @return list<array<string, mixed>>
     */
    public static function forLoanDisbursement(?object $disbursement, ?object $application = null): array
    {
        $labels = [
            'collateral_document' => 'Collateral / Vehicle RC Book / Property File',
            'other_document' => 'Other Document',
        ];

        $docs = [];
        if ($disbursement) {
            $docs = array_merge($docs, self::named($disbursement, $labels));
            $docs = array_merge($docs, self::additional($disbursement->additional_documents ?? null));
        }

        if ($application) {
            foreach ($labels as $type => $title) {
                $already = collect($docs)->contains(fn ($doc) => ($doc['type'] ?? null) === $type);
                if ($already) {
                    continue;
                }
                $item = self::item($application->{$type} ?? null, $type, $title);
                if ($item) {
                    $docs[] = $item;
                }
            }
        }

        return array_values($docs);
    }

    /**
     * Chit settlement: settlement deed / collateral / other / additional files.
     *
     * @return list<array<string, mixed>>
     */
    public static function forChitSettlement(object $payout): array
    {
        $docs = self::named($payout, [
            'settlement_document' => 'Settlement Deed / Release Document',
            'collateral_document' => 'Collateral Document',
            'other_document' => 'Collateral & Other Document',
        ]);

        return array_values(array_merge($docs, self::additional($payout->additional_documents ?? null)));
    }
}
