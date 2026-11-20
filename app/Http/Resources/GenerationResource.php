<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GenerationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'provider_id' => $this->provider_id,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_urls ?? [],
            'status' => $this->status,
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'provider_cost' => $this->provider_cost,
            'image_url' => $this->image_url,
            'completed_at' => $this->completed_at?->format('d M Y, h:i A'),
            'created_at' => $this->created_at?->format('d M Y, h:i A'),
        ];
    }
}
