<?php

namespace App\Http\Requests;

use App\Models\Lead;

class UpdateLeadRequest extends StoreLeadRequest
{
    public function authorize(): bool
    {
        $lead = Lead::query()->find((int) $this->route('lead'));

        return $lead !== null && ($this->user()?->can('update', $lead) ?? false);
    }
}
