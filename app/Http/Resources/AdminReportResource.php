<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->resource;

        return [
            'date' => $data['date'] ?? null,

            'user_id' => isset($data['user_id'])
                ? (int) $data['user_id']
                : null,

            'user_name' => $data['user_name'] ?? null,

            'email' => $data['email'] ?? null,

            'generations' => (int) (
                $data['generations'] ?? 0
            ),

            'user_charges' => (string) (
                $data['user_charges'] ?? '0.000000000'
            ),

            'provider_costs' => (string) (
                $data['provider_costs'] ?? '0.000000000'
            ),

            'profit' => (string) (
                $data['profit'] ?? '0.000000000'
            ),
        ];
    }
}