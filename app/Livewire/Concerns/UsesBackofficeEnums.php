<?php

namespace App\Livewire\Concerns;

use App\Support\BackofficeEnums;

trait UsesBackofficeEnums
{
    public function enumLabel(?string $value, string $fallback = '-'): string
    {
        return BackofficeEnums::label($value, $fallback);
    }

    public function enumOptions(string $enumClass, ?array $onlyValues = null): array
    {
        return BackofficeEnums::options($enumClass, $onlyValues);
    }

    public function enumListLabel(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => $this->enumLabel($value), $values));
    }
}
