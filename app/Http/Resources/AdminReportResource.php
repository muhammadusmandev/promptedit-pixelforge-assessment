<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => data_get($this->resource, 'date'),

            'user_id' => data_get($this->resource, 'user_id') !== null
                ? (int) data_get($this->resource, 'user_id')
                : null,

            'user_name' => data_get($this->resource, 'user_name'),

            'email' => data_get($this->resource, 'email'),

            'generations' => (int) data_get(
                $this->resource,
                'generations',
                0
            ),

            'user_charges' => (string) data_get(
                $this->resource,
                'user_charges',
                '0'
            ),

            'provider_costs' => (string) data_get(
                $this->resource,
                'provider_costs',
                '0'
            ),

            'profit' => (string) data_get(
                $this->resource,
                'profit',
                '0'
            ),
        ];
    }
}