<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\ClinicSetting;
use App\Support\ClinicSettingCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClinicSettings
{
    public function cacheKey(int $clinicId): string
    {
        return "clinic-settings:{$clinicId}";
    }

    /**
     * @return array<string, mixed>
     */
    public function all(Clinic|int $clinic): array
    {
        $clinicId = $clinic instanceof Clinic ? (int) $clinic->id : $clinic;

        $cached = Cache::remember($this->cacheKey($clinicId), now()->addHour(), function () use ($clinicId): array {
            $stored = ClinicSetting::query()
                ->where('clinic_id', $clinicId)
                ->get(['key', 'value', 'type'])
                ->keyBy('key');

            $bag = ClinicSettingCatalog::defaults();

            foreach (ClinicSettingCatalog::keys() as $key) {
                if (! $stored->has($key)) {
                    continue;
                }

                $row = $stored->get($key);
                $bag[$key] = $this->castValue($row->value, ClinicSettingCatalog::definition($key)['type']);
            }

            return $bag;
        });

        return array_replace(ClinicSettingCatalog::defaults(), is_array($cached) ? $cached : []);
    }

    public function get(Clinic|int $clinic, string $key): mixed
    {
        if (! ClinicSettingCatalog::has($key)) {
            throw new \InvalidArgumentException("Unknown clinic setting key [{$key}].");
        }

        return $this->all($clinic)[$key];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function putMany(Clinic $clinic, array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! ClinicSettingCatalog::has($key)) {
                throw ValidationException::withMessages([
                    $key => 'Configuração desconhecida.',
                ]);
            }

            if (ClinicSettingCatalog::isLogoKey($key)) {
                continue;
            }

            $normalized[$key] = $this->normalizeAndValidate($key, $value);
        }

        foreach ($normalized as $key => $value) {
            $definition = ClinicSettingCatalog::definition($key);
            $stored = $this->serializeValue($value, $definition['type']);

            ClinicSetting::query()->updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'key' => $key,
                ],
                [
                    'value' => $stored,
                    'type' => $definition['type'],
                ],
            );
        }

        $this->forgetCache((int) $clinic->id);

        return $this->all($clinic);
    }

    public function putPath(Clinic $clinic, string $key, ?string $path): void
    {
        if (! ClinicSettingCatalog::isLogoKey($key)) {
            throw new \InvalidArgumentException("Key [{$key}] is not a logo path setting.");
        }

        ClinicSetting::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'key' => $key,
            ],
            [
                'value' => $path,
                'type' => 'path',
            ],
        );

        $this->forgetCache((int) $clinic->id);
    }

    public function resetKeys(Clinic $clinic, array $keys): void
    {
        $known = array_values(array_filter(
            $keys,
            fn (mixed $key): bool => is_string($key) && ClinicSettingCatalog::has($key),
        ));

        if ($known === []) {
            return;
        }

        ClinicSetting::query()
            ->where('clinic_id', $clinic->id)
            ->whereIn('key', $known)
            ->delete();

        $this->forgetCache((int) $clinic->id);
    }

    public function forgetCache(int $clinicId): void
    {
        Cache::forget($this->cacheKey($clinicId));
    }

    public function normalizeAndValidate(string $key, mixed $value): mixed
    {
        $definition = ClinicSettingCatalog::definition($key);
        $type = $definition['type'];

        return match ($type) {
            'bool' => $this->normalizeBool($key, $value),
            'int' => $this->normalizeInt($key, $value, $definition),
            'color' => $this->normalizeColor($key, $value),
            'string' => $this->normalizeString($key, $value, $definition),
            'enum' => $this->normalizeEnum($key, $value, $definition),
            'path' => is_string($value) || $value === null ? $value : throw ValidationException::withMessages([
                $key => 'Caminho inválido.',
            ]),
            default => throw new \InvalidArgumentException("Unsupported setting type [{$type}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normalizeString(string $key, mixed $value, array $definition): string
    {
        $nullable = (bool) ($definition['nullable'] ?? false);
        $max = (int) ($definition['max'] ?? 255);

        if ($value === null) {
            if ($nullable) {
                return '';
            }

            throw ValidationException::withMessages([$key => 'Campo obrigatório.']);
        }

        if (! is_string($value) && ! is_numeric($value)) {
            throw ValidationException::withMessages([$key => 'Texto inválido.']);
        }

        $text = trim(strip_tags((string) $value));

        $validator = Validator::make(
            [$key => $text],
            [$key => [$nullable ? 'nullable' : 'required', 'string', "max:{$max}"]],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $text;
    }

    private function normalizeBool(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true' || $value === 'on') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false' || $value === 'off' || $value === null || $value === '') {
            return false;
        }

        throw ValidationException::withMessages([$key => 'Valor booleano inválido.']);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normalizeInt(string $key, mixed $value, array $definition): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([$key => 'Número inválido.']);
        }

        $int = (int) $value;
        $min = (int) ($definition['min'] ?? PHP_INT_MIN);
        $max = (int) ($definition['max'] ?? PHP_INT_MAX);

        if ($int < $min || $int > $max) {
            throw ValidationException::withMessages([
                $key => "Informe um valor entre {$min} e {$max}.",
            ]);
        }

        return $int;
    }

    private function normalizeColor(string $key, mixed $value): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$key => 'Cor inválida.']);
        }

        $color = strtoupper(trim($value));

        if (! preg_match('/^#[0-9A-F]{6}$/', $color)) {
            throw ValidationException::withMessages([
                $key => 'Use uma cor hexadecimal no formato #RRGGBB.',
            ]);
        }

        return $color;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function normalizeEnum(string $key, mixed $value, array $definition): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw ValidationException::withMessages([$key => 'Opção inválida.']);
        }

        $option = strtolower(trim((string) $value));
        /** @var list<string> $options */
        $options = array_values(array_map(
            static fn (mixed $item): string => strtolower((string) $item),
            is_array($definition['options'] ?? null) ? $definition['options'] : [],
        ));

        if ($options === [] || ! in_array($option, $options, true)) {
            throw ValidationException::withMessages([$key => 'Opção inválida.']);
        }

        return $option;
    }

    private function serializeValue(mixed $value, string $type): ?string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'int' => (string) $value,
            'color', 'string', 'enum' => (string) $value,
            'path' => $value === null ? null : (string) $value,
            default => $value === null ? null : (string) $value,
        };
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => $value === '1' || $value === 'true',
            'int' => (int) $value,
            'path' => $value === null || $value === '' ? null : $value,
            'enum' => $value === null || $value === '' ? '' : strtolower($value),
            default => $value ?? '',
        };
    }
}
