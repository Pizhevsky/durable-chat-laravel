<?php

namespace App\Application\Recovery;

final class RecoveryChecksum
{
    /** @param array<int, array<string, mixed>> $events */
    public function forEvents(array $events): string
    {
        return hash('sha256', $this->canonicalJson($events));
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->sortKeys($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param mixed $value @return mixed */
    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);
    }
}
