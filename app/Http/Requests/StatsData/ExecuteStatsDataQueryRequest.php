<?php

namespace App\Http\Requests\StatsData;

use App\Domain\StatsData\Support\QuerySpecHydrator;
use Illuminate\Foundation\Http\FormRequest;

class ExecuteStatsDataQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('stats_data.max_query_rows', 10_000);

        return [
            'specVersion' => 'sometimes|integer|in:2',
            'sources' => 'required|array|min:1',
            'sources.*.alias' => 'required|string|max:64|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            'sources.*.sourceId' => 'required|uuid',
            'join' => 'sometimes|array',
            'join.type' => 'sometimes|string|in:inner,left',
            'join.on' => 'sometimes|array',
            'join.on.*' => 'string|max:255',
            // v1
            'columns' => 'sometimes|array|min:1',
            'columns.*.label' => 'required_with:columns|string|max:500',
            'columns.*.from' => 'required_with:columns|string|max:512',
            // v2
            'select' => 'sometimes|array|min:1',
            'select.*.kind' => 'required_with:select|string|in:from,formula',
            'select.*.label' => 'required_with:select|string|max:500',
            'select.*.from' => 'required_if:select.*.kind,from|string|max:512',
            'select.*.expr' => 'required_if:select.*.kind,formula|array',
            'select.*.expr.kind' => 'required_with:select.*.expr|string|max:32',
            'groupBy' => 'sometimes|array',
            'groupBy.*' => 'string|max:255',
            'aggregations' => 'sometimes|array',
            'aggregations.*.label' => 'required_with:aggregations|string|max:500',
            'aggregations.*.fn' => 'required_with:aggregations|string|in:count,sum,avg,min,max',
            'aggregations.*.expr' => 'sometimes|array',
            'aggregations.*.expr.kind' => 'required_with:aggregations.*.expr|string|max:32',
            'orderBy' => 'sometimes|array',
            'orderBy.*.by' => 'required_with:orderBy|string|max:500',
            'orderBy.*.dir' => 'required_with:orderBy|string|in:asc,desc',
            'where' => 'sometimes|array',
            'where.*.kind' => 'required_with:where|string|in:eq,ne,gt,gte,lt,lte',
            'where.*.left' => 'required_with:where|array',
            'where.*.left.kind' => 'required_with:where.*.left|string|in:column',
            'where.*.left.column' => 'required_with:where.*.left|string|max:255',
            'where.*.right' => 'required_with:where|array',
            'where.*.right.kind' => 'required_with:where.*.right|string|in:literal',
            'where.*.right.value' => 'present|nullable',
            'limit' => 'sometimes|integer|min:1|max:'.$max,
            'offset' => 'sometimes|integer|min:0|max:10000000',
            'search' => 'sometimes|array',
            'search.q' => 'sometimes|nullable|string|max:500',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $v2 = (int) $this->input('specVersion', 0) === 2;
            if ($v2) {
                $sel = $this->input('select');
                if (! is_array($sel) || $sel === []) {
                    $v->errors()->add('select', __('stats_data.query_columns_required'));
                }
            } else {
                $cols = $this->input('columns');
                if (! is_array($cols) || $cols === []) {
                    $v->errors()->add('columns', __('stats_data.query_columns_required'));
                }
            }

            $sources = $this->input('sources', []);
            if (! is_array($sources) || count($sources) <= 1) {
                return;
            }
            $on = data_get($this->input('join'), 'on');
            if (! is_array($on) || $on === []) {
                $v->errors()->add('join.on', __('stats_data.query_join_on_required'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function spec(): array
    {
        /** @var array<string,mixed> $validated */
        $validated = $this->validated();
        /** @var array<string,mixed> $raw */
        $raw = $this->all();

        return QuerySpecHydrator::hydrate($validated, $raw);
    }
}
