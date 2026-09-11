<?php

namespace App\Http\Requests\Studio;

use App\Domain\Content\Enums\StudioContentAccessLevelEnum;
use App\Domain\Content\Enums\StudioContentAccessResourceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudioContentCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $levels = StudioContentAccessLevelEnum::values();
        $resources = StudioContentAccessResourceEnum::values();

        $permissionRules = [];
        foreach ($resources as $resource) {
            $permissionRules["permissions.{$resource}"] = ['required', 'string', Rule::in($levels)];
        }

        return [
            'permissions' => ['required', 'array'],
            ...$permissionRules,
        ];
    }
}
