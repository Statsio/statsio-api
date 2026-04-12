<?php

namespace App\Domain\StatsData\Actions;

use App\Models\StatsData\StatsDataDocument;
use App\Models\User\User;
use Illuminate\Support\Str;

class StatsDataDocumentAction
{
    public function create(User $user, array $data): StatsDataDocument
    {
        return StatsDataDocument::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? '',
            'visibility' => $data['visibility'],
            'blocks' => $data['blocks'] ?? [],
            'slug' => $this->makeUniqueSlug($data['title']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  Clés normalisées (snake_case côté domaine)
     */
    public function updateForUser(User $user, string $id, array $data): ?StatsDataDocument
    {
        $doc = $this->findOwnedOrNull($user, $id);
        if (! $doc) {
            return null;
        }

        $fill = [];
        if (array_key_exists('title', $data)) {
            $fill['title'] = $data['title'];
        }
        if (array_key_exists('subtitle', $data)) {
            $fill['subtitle'] = $data['subtitle'];
        }
        if (array_key_exists('visibility', $data)) {
            $fill['visibility'] = $data['visibility'];
        }
        if (array_key_exists('blocks', $data)) {
            $fill['blocks'] = $data['blocks'];
        }

        if ($fill !== []) {
            $doc->update($fill);
        }

        return $doc->fresh();
    }

    public function deleteForUser(User $user, string $id): bool
    {
        $doc = $this->findOwnedOrNull($user, $id);

        if (! $doc) {
            return false;
        }

        return (bool) $doc->delete();
    }

    public function findOwnedOrNull(User $user, string $id): ?StatsDataDocument
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return StatsDataDocument::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function makeUniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'statsdata';
        $slug = $base;
        for ($n = 0; $n < 100 && StatsDataDocument::where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }
        if (StatsDataDocument::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::replace('-', '', (string) Str::uuid());
        }

        return $slug;
    }
}
