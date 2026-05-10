<?php declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string'],
            'starting_price' => ['required', 'integer', 'min:1'],
            'bid_increment' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
