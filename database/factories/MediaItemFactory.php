<?php

namespace Database\Factories;

use App\MediaType;
use App\Models\Clinic;
use App\Models\MediaItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MediaItem> */
class MediaItemFactory extends Factory
{
    protected $model = MediaItem::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->words(3, true),
            'type' => MediaType::IMAGE,
            'disk' => MediaItem::DISK,
            'file_path' => 'tv-media/'.fake()->numberBetween(1, 99).'/'.Str::lower(Str::random(20)).'.jpg',
            'external_url' => null,
            'external_id' => null,
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'duration_seconds' => 10,
            'play_with_audio' => false,
            'active' => true,
            'created_by_user_id' => null,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'type' => MediaType::VIDEO,
            'file_path' => 'tv-media/'.fake()->numberBetween(1, 99).'/'.Str::lower(Str::random(20)).'.mp4',
            'mime_type' => 'video/mp4',
            'duration_seconds' => null,
            'play_with_audio' => false,
            'file_size' => 2048,
            'external_url' => null,
            'external_id' => null,
        ]);
    }

    public function youtube(string $videoId = 'dQw4w9WgXcQ'): static
    {
        return $this->state(fn (): array => [
            'type' => MediaType::YOUTUBE,
            'file_path' => null,
            'mime_type' => null,
            'file_size' => null,
            'duration_seconds' => null,
            'play_with_audio' => false,
            'external_id' => $videoId,
            'external_url' => 'https://www.youtube.com/watch?v='.$videoId,
        ]);
    }

    public function withAudio(): static
    {
        return $this->state(fn (): array => [
            'play_with_audio' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
