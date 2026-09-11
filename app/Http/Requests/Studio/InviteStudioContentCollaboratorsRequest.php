<?php

namespace App\Http\Requests\Studio;

use App\Domain\Content\Enums\StudioContentAccessLevelEnum;
use App\Domain\Content\Enums\StudioContentAccessResourceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStudioContentCollaboratorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership checked in controller
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
            'emails' => ['required', 'array', 'min:1', 'max:20'],
            'emails.*' => ['required', 'email', 'max:255'],
            'permissions' => ['required', 'array'],
            ...$permissionRules,
        ];
    }
}
