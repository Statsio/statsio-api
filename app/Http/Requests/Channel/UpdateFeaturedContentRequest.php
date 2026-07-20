<?php

namespace App\Http\Requests\Channel;

use App\Domain\Channel\Actions\ChannelFeaturedContentAction;
use App\Models\Channel\ChannelUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdateFeaturedContentRequest extends FormRequest
{
    /** Request field => (StudioContent type value, slot key used by ChannelFeaturedContentAction). */
    private const SLOTS = [
        'featured_article_id' => ['type' => 'article', 'key' => 'article'],
        'featured_statsdata_id' => ['type' => 'statsdata', 'key' => 'statsdata'],
        'featured_survey_id' => ['type' => 'survey', 'key' => 'survey'],
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return ChannelUser::where('channel_id', $this->route('id'))
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function rules(): array
    {
        return [
            'featured_article_id' => 'sometimes|nullable|integer',
            'featured_statsdata_id' => 'sometimes|nullable|integer',
            'featured_survey_id' => 'sometimes|nullable|integer',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $channelId = (int) $this->route('id');
            $action = app(ChannelFeaturedContentAction::class);

            foreach (self::SLOTS as $field => $slot) {
                if (! $this->has($field) || $this->input($field) === null) {
                    continue;
                }

                $contentId = (int) $this->input($field);
                if (! $action->validateSlot($contentId, $channelId, $slot['type'])) {
                    $validator->errors()->add(
                        $field,
                        "Ce contenu n'appartient pas à cette chaîne, n'est pas publié, ou n'est pas du bon type."
                    );
                }
            }
        });
    }

    /**
     * Validated payload reshaped to the slot keys expected by ChannelFeaturedContentAction::updateFeatured().
     *
     * @return array{article?: int|null, statsdata?: int|null, survey?: int|null}
     */
    public function featuredSlots(): array
    {
        $validated = $this->validated();
        $result = [];

        foreach (self::SLOTS as $field => $slot) {
            if (array_key_exists($field, $validated)) {
                $result[$slot['key']] = $validated[$field] !== null ? (int) $validated[$field] : null;
            }
        }

        return $result;
    }
}
